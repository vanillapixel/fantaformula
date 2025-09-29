<?php
// Fantasy Formula 1 - Team Selection API
// POST /backend/api/teams/index.php           -> create/update team selection for a race
// GET  /backend/api/teams/index.php?race_id=  -> get current user's selection for race
// GET  /backend/api/teams/index.php?race_id=&championship_id=&user_id= -> admin/mod view of another user
// GET  /backend/api/teams/index.php?race_id=&championship_id=&all=1 -> list all teams for a race (standings)

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

$method = getRequestMethod();

try {
    switch ($method) {
        case 'GET':
            handleGetTeam();
            break;
        case 'POST':
            requireAuth();
            handleUpsertTeam();
            break;
        default:
            sendMethodNotAllowed(['GET','POST']);
    }
} catch (Exception $e) {
    error_log('Team API error: ' . $e->getMessage());
    sendServerError();
}

function handleGetTeam() {
    $db = getDB();
    $raceId = (int)($_GET['race_id'] ?? 0);
    if (!$raceId) sendValidationError(['race_id' => 'Required']);
    $championshipId = isset($_GET['championship_id']) ? (int)$_GET['championship_id'] : 0;
    $viewAll = isset($_GET['all']);
    $requestedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    $currentUser = getCurrentUser();
    $currentUserId = $currentUser['user_id'] ?? null;

    if ($requestedUserId && $requestedUserId !== $currentUserId) {
        // For now restrict viewing others unless same championship admin
        if (!$championshipId || !isChampionshipAdmin($championshipId)) {
            sendForbidden('Not allowed to view other user team');
        }
    }

    if ($viewAll) {
        if (!$championshipId) sendValidationError(['championship_id' => 'Required when all=1']);
        $stmt = $db->prepare("SELECT urt.id, urt.user_id, urt.total_points, urt.total_cost, urt.drs_enabled, urt.submitted_at, u.username
                               FROM user_race_teams urt
                               JOIN users u ON urt.user_id = u.id
                               WHERE urt.race_id = :race AND urt.championship_id = :champ
                               ORDER BY urt.total_points DESC, urt.total_cost ASC, urt.submitted_at ASC");
        $stmt->execute([':race'=>$raceId, ':champ'=>$championshipId]);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Attach driver selections per team
        if ($teams) {
            $teamIds = array_column($teams,'id');
            $in = implode(',', array_fill(0,count($teamIds),'?'));
            $selStmt = $db->prepare("SELECT usd.user_race_team_id, rd.driver_id, d.first_name, d.last_name, rd.price
                                      FROM user_selected_drivers usd
                                      JOIN race_drivers rd ON usd.race_driver_id = rd.id
                                      JOIN drivers d ON rd.driver_id = d.id
                                      WHERE usd.user_race_team_id IN ($in)");
            $selStmt->execute($teamIds);
            $byTeam = [];
            foreach ($selStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $byTeam[$row['user_race_team_id']][] = [
                    'driver_id'=>(int)$row['driver_id'],
                    'name'=>$row['first_name'].' '.$row['last_name'],
                    'price'=>(float)$row['price']
                ];
            }
            foreach ($teams as &$t) {
                $t['drivers'] = $byTeam[$t['id']] ?? [];
            }
        }
        sendSuccess(['race_id'=>$raceId,'championship_id'=>$championshipId,'teams'=>$teams]);
    }

    // Single team (current or requested user)
    $userId = $requestedUserId ?: $currentUserId;
    if (!$userId) sendUnauthorized();

    $stmt = $db->prepare("SELECT * FROM user_race_teams WHERE user_id = :uid AND race_id = :race" . ($championshipId?" AND championship_id = :champ":"") . " LIMIT 1");
    $params = [':uid'=>$userId, ':race'=>$raceId];
    if ($championshipId) $params[':champ']=$championshipId;
    $stmt->execute($params);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$team) {
        sendSuccess(['race_id'=>$raceId,'championship_id'=>$championshipId,'team'=>null],'No team yet');
    }

    $sel = $db->prepare("SELECT usd.id, rd.driver_id, d.first_name, d.last_name, rd.price
                          FROM user_selected_drivers usd
                          JOIN race_drivers rd ON usd.race_driver_id = rd.id
                          JOIN drivers d ON rd.driver_id = d.id
                          WHERE usd.user_race_team_id = :tid");
    $sel->execute([':tid'=>$team['id']]);
    $drivers = [];
    $cost = 0.0;
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $drivers[] = [
            'driver_id'=>(int)$row['driver_id'],
            'name'=>$row['first_name'].' '.$row['last_name'],
            'price'=>(float)$row['price']
        ];
        $cost += (float)$row['price'];
    }
    $team['drivers'] = $drivers;
    $team['calculated_cost'] = $cost;
    sendSuccess($team, 'Team retrieved');
}

function handleUpsertTeam() {
    $db = getDB();
    $input = getJSONInput();
    $raceId = (int)($input['race_id'] ?? 0);
    $championshipId = (int)($input['championship_id'] ?? 0);
    $driverIds = $input['drivers'] ?? [];
    $drsEnabled = (bool)($input['drs_enabled'] ?? true);
    $userId = getCurrentUserId();
    if (!$raceId) sendValidationError(['race_id'=>'Required']);
    if (!$championshipId) sendValidationError(['championship_id'=>'Required']);
    if (!is_array($driverIds) || empty($driverIds)) sendValidationError(['drivers'=>'Array of driver_ids required']);

    // Load season rules (budget, max drivers)
    $seasonStmt = $db->prepare("SELECT s.id as season_id, sr.default_budget, sr.max_drivers_count
                                 FROM races r
                                 JOIN seasons s ON r.season_id = s.id
                                 JOIN season_rules sr ON sr.season_id = s.id
                                 WHERE r.id = ? LIMIT 1");
    $seasonStmt->execute([$raceId]);
    $season = $seasonStmt->fetch(PDO::FETCH_ASSOC);
    if (!$season) sendError('Race not found or season rules missing', 404);

    if (count($driverIds) > (int)$season['max_drivers_count']) {
        sendValidationError(['drivers'=>'Too many drivers (max '.$season['max_drivers_count'].')']);
    }
    // Ensure unique driver IDs
    if (count($driverIds) !== count(array_unique($driverIds))) {
        sendValidationError(['drivers'=>'Duplicate driver ids']);
    }

    // Fetch race_driver entries with prices
    $placeholders = implode(',', array_fill(0,count($driverIds),'?'));
    $priceStmt = $db->prepare("SELECT rd.id as race_driver_id, rd.driver_id, rd.price
                                FROM race_drivers rd
                                WHERE rd.race_id = ? AND rd.driver_id IN ($placeholders)");
    $priceStmt->execute(array_merge([$raceId], $driverIds));
    $rows = $priceStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($driverIds)) {
        sendValidationError(['drivers'=>'One or more drivers not available for this race']);
    }
    $totalCost = 0.0;
    $raceDriverMap = [];
    foreach ($rows as $r) {
        $totalCost += (float)$r['price'];
        $raceDriverMap[$r['driver_id']] = $r['race_driver_id'];
    }
    if ($totalCost > (float)$season['default_budget']) {
        sendValidationError(['total_cost'=>'Budget exceeded ('.$totalCost.' > '.$season['default_budget'].')']);
    }

    $db->beginTransaction();
    try {
        // Upsert user_race_team
        $teamStmt = $db->prepare("SELECT id FROM user_race_teams WHERE user_id = ? AND race_id = ? AND championship_id = ? LIMIT 1");
        $teamStmt->execute([$userId,$raceId,$championshipId]);
        $teamId = $teamStmt->fetchColumn();
        if ($teamId) {
            $upd = $db->prepare("UPDATE user_race_teams SET drs_enabled = ?, total_cost = ?, submitted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->execute([$drsEnabled ? 1:0, $totalCost, $teamId]);
            // Clear previous selections
            $db->prepare("DELETE FROM user_selected_drivers WHERE user_race_team_id = ?")->execute([$teamId]);
        } else {
            $ins = $db->prepare("INSERT INTO user_race_teams (user_id,race_id,championship_id,drs_enabled,total_cost,total_points,submitted_at) VALUES (?,?,?,?,?,0,CURRENT_TIMESTAMP)");
            $ins->execute([$userId,$raceId,$championshipId,$drsEnabled?1:0,$totalCost]);
            $teamId = (int)$db->lastInsertId();
        }
        // Insert selections
        $selIns = $db->prepare("INSERT INTO user_selected_drivers (user_race_team_id,race_driver_id) VALUES (?,?)");
        foreach ($driverIds as $did) {
            $selIns->execute([$teamId,$raceDriverMap[$did]]);
        }
        $db->commit();
        sendSuccess([
            'team_id'=>$teamId,
            'race_id'=>$raceId,
            'championship_id'=>$championshipId,
            'drivers'=>$driverIds,
            'total_cost'=>$totalCost,
            'budget'=>(float)$season['default_budget'],
            'remaining'=> (float)$season['default_budget'] - $totalCost
        ], 'Team saved');
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>
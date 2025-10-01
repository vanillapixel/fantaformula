<?php
// Fantasy Formula 1 - User Lineup Selection API (canonical, replaces legacy user_teams & teams)
// POST /backend/api/lineups/index.php           -> create/update lineup selection for a race
// GET  /backend/api/lineups/index.php?race_id=  -> get current user's lineup for race
// GET  /backend/api/lineups/index.php?race_id=&championship_id=&user_id= -> admin/mod view of another user
// GET  /backend/api/lineups/index.php?race_id=&championship_id=&all=1 -> list all lineups for a race (standings)

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

$method = getRequestMethod();

try {
    switch ($method) {
        case 'GET':
            ff_handleGetLineup();
            break;
        case 'POST':
            requireAuth();
            ff_handleUpsertLineup();
            break;
        default:
            sendMethodNotAllowed(['GET','POST']);
    }
} catch (Exception $e) {
    error_log('Lineup API error: ' . $e->getMessage());
    sendServerError();
}

function ff_handleGetLineup() {
    $db = getDB();
    $raceId = (int)($_GET['race_id'] ?? 0);
    if (!$raceId) sendValidationError(['race_id' => 'Required']);
    $championshipId = isset($_GET['championship_id']) ? (int)$_GET['championship_id'] : 0;
    $viewAll = isset($_GET['all']);
    $requestedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    $currentUser = getCurrentUser();
    $currentUserId = $currentUser['user_id'] ?? null;

    if ($requestedUserId && $requestedUserId !== $currentUserId) {
        if (!$championshipId || !isChampionshipAdmin($championshipId)) {
            sendForbidden('Not allowed to view other user lineup');
        }
    }

    if ($viewAll) {
        if (!$championshipId) sendValidationError(['championship_id' => 'Required when all=1']);
    $stmt = $db->prepare("SELECT url.id, url.user_id, url.drs_enabled, url.submitted_at, u.username
           FROM user_race_lineups url
           JOIN users u ON url.user_id = u.id
           WHERE url.race_id = :race AND url.championship_id = :champ");
        $stmt->execute([':race'=>$raceId, ':champ'=>$championshipId]);
        $lineups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($lineups) {
            $ids = array_column($lineups,'id');
            $in = implode(',', array_fill(0,count($ids),'?'));
            $selStmt = $db->prepare("SELECT usd.user_race_lineup_id, rd.driver_id, d.first_name, d.last_name, rd.price
                                      FROM user_selected_drivers usd
                                      JOIN race_drivers rd ON usd.race_driver_id = rd.id
                                      JOIN drivers d ON rd.driver_id = d.id
                                      WHERE usd.user_race_lineup_id IN ($in)");
            $selStmt->execute($ids);
            $byId = [];
            foreach ($selStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $byId[$row['user_race_lineup_id']][] = [
                    'driver_id'=>(int)$row['driver_id'],
                    'name'=>$row['first_name'].' '.$row['last_name'],
                    'price'=>(float)$row['price']
                ];
            }
            // Load rules + driver points once (shared raceId)
            $rules = ff_loadSeasonRulesForRace($db, $raceId) ?: [];
            $driverPts = ff_computeDriverPoints($db, $raceId, $rules);
            foreach ($lineups as &$l) {
                $l['drivers'] = $byId[$l['id']] ?? [];
                $l['calculated_cost'] = array_sum(array_column($l['drivers'],'price'));
                $l['calculated_points'] = ff_computeLineupPoints($db, (int)$l['id'], $driverPts);
            }
            // Sort in PHP by calculated_points DESC then cost ASC then submitted_at ASC
            usort($lineups, function($a,$b){
                if ($b['calculated_points'] == $a['calculated_points']) {
                    if ($a['calculated_cost'] == $b['calculated_cost']) {
                        return strcmp($a['submitted_at'], $b['submitted_at']);
                    }
                    return $a['calculated_cost'] <=> $b['calculated_cost'];
                }
                return $b['calculated_points'] <=> $a['calculated_points'];
            });
        }
        sendSuccess(['race_id'=>$raceId,'championship_id'=>$championshipId,'lineups'=>$lineups]);
    }

    $userId = $requestedUserId ?: $currentUserId;
    if (!$userId) sendUnauthorized();

    $stmt = $db->prepare("SELECT id, user_id, race_id, championship_id, drs_enabled, submitted_at FROM user_race_lineups WHERE user_id = :uid AND race_id = :race" . ($championshipId?" AND championship_id = :champ":"") . " LIMIT 1");
    $params = [':uid'=>$userId, ':race'=>$raceId];
    if ($championshipId) $params[':champ']=$championshipId;
    $stmt->execute($params);
    $lineup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lineup) {
        sendSuccess(['race_id'=>$raceId,'championship_id'=>$championshipId,'lineup'=>null],'No lineup yet');
    }

    $sel = $db->prepare("SELECT usd.id, rd.driver_id, d.first_name, d.last_name, rd.price
                          FROM user_selected_drivers usd
                          JOIN race_drivers rd ON usd.race_driver_id = rd.id
                          JOIN drivers d ON rd.driver_id = d.id
                          WHERE usd.user_race_lineup_id = :tid");
    $sel->execute([':tid'=>$lineup['id']]);
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
    $lineup['drivers'] = $drivers;
    $lineup['calculated_cost'] = $cost;
    // dynamic points (on-demand)
    $rules = ff_loadSeasonRulesForRace($db, $raceId) ?: [];
    $driverPts = ff_computeDriverPoints($db, $raceId, $rules);
    $lineup['calculated_points'] = ff_computeLineupPoints($db, (int)$lineup['id'], $driverPts);
    sendSuccess($lineup, 'Lineup retrieved');
}

function ff_handleUpsertLineup() {
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
    if (count($driverIds) !== count(array_unique($driverIds))) {
        sendValidationError(['drivers'=>'Duplicate driver ids']);
    }

    $placeholders = implode(',', array_fill(0,count($driverIds),'?'));
    $priceStmt = $db->prepare("SELECT rd.id as race_driver_id, rd.driver_id, rd.price
                                FROM race_drivers rd
                                WHERE rd.race_id = ? AND rd.driver_id IN ($placeholders)");
    $priceStmt->execute(array_merge([$raceId], $driverIds));
    $rows = $priceStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($driverIds)) {
        sendValidationError(['drivers'=>'One or more drivers not available for this race']);
    }
    $totalCost = 0.0; $raceDriverMap = [];
    foreach ($rows as $r) { $totalCost += (float)$r['price']; $raceDriverMap[$r['driver_id']] = $r['race_driver_id']; }
    if ($totalCost > (float)$season['default_budget']) {
        sendValidationError(['total_cost'=>'Budget exceeded ('.$totalCost.' > '.$season['default_budget'].')']);
    }

    $db->beginTransaction();
    try {
        $sel = $db->prepare("SELECT id FROM user_race_lineups WHERE user_id = ? AND race_id = ? AND championship_id = ? LIMIT 1");
        $sel->execute([$userId,$raceId,$championshipId]);
        $lineupId = $sel->fetchColumn();
        if ($lineupId) {
            $upd = $db->prepare("UPDATE user_race_lineups SET drs_enabled = ?, submitted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->execute([$drsEnabled?1:0,$lineupId]);
            $db->prepare("DELETE FROM user_selected_drivers WHERE user_race_lineup_id = ?")->execute([$lineupId]);
        } else {
            $ins = $db->prepare("INSERT INTO user_race_lineups (user_id,race_id,championship_id,drs_enabled,submitted_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)");
            $ins->execute([$userId,$raceId,$championshipId,$drsEnabled?1:0]);
            $lineupId = (int)$db->lastInsertId();
        }
        $insSel = $db->prepare("INSERT INTO user_selected_drivers (user_race_lineup_id,race_driver_id) VALUES (?,?)");
        foreach ($driverIds as $did) { $insSel->execute([$lineupId,$raceDriverMap[$did]]); }
        $db->commit();
        sendSuccess([
            'lineup_id'=>$lineupId,
            'race_id'=>$raceId,
            'championship_id'=>$championshipId,
            'drivers'=>$driverIds,
            'total_cost'=>$totalCost,
            'budget'=>(float)$season['default_budget'],
            'remaining'=>(float)$season['default_budget'] - $totalCost
        ], 'Lineup saved');
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>

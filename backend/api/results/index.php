<?php
// Race Results & Scoring API
// GET  /backend/api/results/index.php?race_id=1 -> list results (ordered by position)
// POST /backend/api/results/index.php           -> submit/update results + recompute lineup points
// Body: { race_id: int, results: [ { driver_id, position, fastest_lap(bool), dnf(bool) } ] }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();
$method = getRequestMethod();

try {
    if ($method === 'GET') {
        handleGetResults();
    } elseif ($method === 'POST') {
        requireSuperAdmin(); // restricted
        handlePostResults();
    } else {
        sendMethodNotAllowed(['GET','POST']);
    }
} catch (Exception $e) {
    error_log('Results API error: '.$e->getMessage());
    sendServerError();
}

function handleGetResults() {
    $raceId = (int)($_GET['race_id'] ?? 0);
    if (!$raceId) sendValidationError(['race_id'=>'Required']);
    $db = getDB();
    $stmt = $db->prepare("SELECT rr.id, rr.race_id, rr.driver_id, rr.position, rr.fastest_lap, rr.dnf, rr.points_awarded,
                                 d.first_name, d.last_name
                          FROM race_results rr
                          JOIN drivers d ON rr.driver_id = d.id
                          WHERE rr.race_id = :race
                          ORDER BY rr.position ASC");
    $stmt->execute([':race'=>$raceId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendSuccess(['race_id'=>$raceId,'results'=>$results]);
}

function handlePostResults() {
    $db = getDB();
    $input = getJSONInput();
    $raceId = (int)($input['race_id'] ?? 0);
    $results = $input['results'] ?? [];
    if (!$raceId) sendValidationError(['race_id'=>'Required']);
    if (!is_array($results) || empty($results)) sendValidationError(['results'=>'Non-empty array required']);

    // Load season scoring rules (simplified)
    $ruleStmt = $db->prepare("SELECT sr.* FROM races r JOIN season_rules sr ON sr.season_id = r.season_id WHERE r.id = ? LIMIT 1");
    $ruleStmt->execute([$raceId]);
    $rules = $ruleStmt->fetch(PDO::FETCH_ASSOC);
    if (!$rules) sendError('Season rules not found for race', 404);

    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM race_results WHERE race_id = ?')->execute([$raceId]);
        $ins = $db->prepare('INSERT INTO race_results (race_id, driver_id, position, fastest_lap, dnf, points_awarded) VALUES (?,?,?,?,?,?)');

        $positionPointsCache = [];
        $totalDriverPoints = [];
        foreach ($results as $row) {
            $driverId = (int)($row['driver_id'] ?? 0);
            $position = isset($row['position']) ? (int)$row['position'] : null;
            $fastest = !empty($row['fastest_lap']);
            $dnf = !empty($row['dnf']);
            if (!$driverId || !$position) {
                $db->rollBack();
                sendValidationError(['results'=>'Each result needs driver_id & position']);
            }
            if (!isset($positionPointsCache[$position])) {
                $col = 'p'.$position.'_points';
                $positionPointsCache[$position] = isset($rules[$col]) ? (float)$rules[$col] : 0.0;
            }
            $points = $positionPointsCache[$position];
            if ($fastest && isset($rules['fastest_lap_points'])) $points += (float)$rules['fastest_lap_points'];
            if ($dnf && isset($rules['dnf_penalty'])) $points -= (float)$rules['dnf_penalty'];
            $ins->execute([$raceId,$driverId,$position,$fastest?1:0,$dnf?1:0,$points]);
            $totalDriverPoints[$driverId] = $points;
        }

    // Recompute lineup points
    $teamSelect = $db->prepare("SELECT url.id, usd.race_driver_id, rd.driver_id
                     FROM user_race_lineups url
                     JOIN user_selected_drivers usd ON url.id = usd.user_race_lineup_id
                     JOIN race_drivers rd ON usd.race_driver_id = rd.id
                     WHERE url.race_id = ?");
        $teamSelect->execute([$raceId]);
        $teamDrivers = $teamSelect->fetchAll(PDO::FETCH_ASSOC);
        $pointsByTeam = [];
        foreach ($teamDrivers as $td) {
            $pid = (int)$td['id'];
            $drvId = (int)$td['driver_id'];
            $pointsByTeam[$pid] = ($pointsByTeam[$pid] ?? 0) + ($totalDriverPoints[$drvId] ?? 0);
        }
    $upd = $db->prepare('UPDATE user_race_lineups SET total_points = ? WHERE id = ?');
        foreach ($pointsByTeam as $teamId=>$pts) {
            $upd->execute([$pts,$teamId]);
        }

        $db->commit();
    sendSuccess(['race_id'=>$raceId,'updated_results'=>count($results),'lineups_scored'=>count($pointsByTeam)], 'Results saved & lineups scored');
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>
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
    $stmt = $db->prepare("SELECT rr.id, rr.race_id, rr.driver_id, rr.race_position as position, rr.fastest_lap, rr.dnf,
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
    $ins = $db->prepare('INSERT INTO race_results (race_id, driver_id, race_position, fastest_lap, dnf, calculated_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)');

        // Build position-based scoring from season_rules.driver_finish_points JSON
        $finishPoints = isset($rules['driver_finish_points']) && $rules['driver_finish_points'] ? json_decode($rules['driver_finish_points'], true) : [];
        if (!is_array($finishPoints) || empty($finishPoints)) {
            $finishPoints = [150,125,100,90,80,75,70,65,60,55,50,45,40,35,30,25,20,15,10,5];
        }
        $totalDriverPoints = [];
        foreach ($results as $row) {
            $driverId = (int)($row['driver_id'] ?? 0);
            $position = isset($row['position']) ? (int)$row['position'] : null;
            $fastest = !empty($row['fastest_lap']);
            $dnf = !empty($row['dnf']);
            if (!$driverId || !$position) { $db->rollBack(); sendValidationError(['results'=>'Each result needs driver_id & position']); }
            $base = ($position >=1 && $position <= count($finishPoints)) ? (float)$finishPoints[$position-1] : 0.0;
            // simple fastest lap bonus if configured
            $bonusFL = (!empty($fastest) && isset($rules['fastest_lap_points'])) ? (float)$rules['fastest_lap_points'] : 0.0;
            $penaltyDnf = (!empty($dnf) && isset($rules['dnf_penalty'])) ? (float)$rules['dnf_penalty'] : 0.0;
            $points = $base + $bonusFL - $penaltyDnf;
            $ins->execute([$raceId,$driverId,$position,$fastest?1:0,$dnf?1:0]);
            $totalDriverPoints[$driverId] = $points;
        }

    // No persistence of lineup totals anymore (dynamic scoring).
    $db->commit();
    sendSuccess(['race_id'=>$raceId,'updated_results'=>count($results)], 'Results saved (dynamic scoring)');
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>
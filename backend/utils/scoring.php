<?php
// Dynamic scoring helpers (no stored total_points)

function ff_loadSeasonRulesForRace(PDO $db, int $raceId): ?array {
    $stmt = $db->prepare('SELECT sr.* FROM races r JOIN season_rules sr ON sr.season_id = r.season_id WHERE r.id = ? LIMIT 1');
    $stmt->execute([$raceId]);
    $rules = $stmt->fetch(PDO::FETCH_ASSOC);
    return $rules ?: null;
}

function ff_computeDriverPoints(PDO $db, int $raceId, array $rules): array {
    // driver_finish_points JSON -> array
    $finish = [];
    if (!empty($rules['driver_finish_points'])) {
        $decoded = json_decode($rules['driver_finish_points'], true);
        if (is_array($decoded)) $finish = $decoded;
    }
    if (empty($finish)) {
        // fallback default distribution (matching migration seeding)
        $finish = [150,125,100,90,80,75,70,65,60,55,50,45,40,35,30,25,20,15,10,5];
    }
    $fastestLapBonus = isset($rules['fastest_lap_points']) ? (float)$rules['fastest_lap_points'] : 0.0;
    $dnfPenalty       = isset($rules['dnf_penalty']) ? (float)$rules['dnf_penalty'] : 0.0; // may not exist yet
    $map = [];
    $stmt = $db->prepare('SELECT driver_id, race_position, fastest_lap, dnf FROM race_results WHERE race_id = ?');
    $stmt->execute([$raceId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pos = (int)$row['race_position'];
        $base = ($pos >=1 && $pos <= count($finish)) ? (float)$finish[$pos-1] : 0.0;
        $bonus = !empty($row['fastest_lap']) ? $fastestLapBonus : 0.0;
        $pen   = !empty($row['dnf']) ? $dnfPenalty : 0.0;
        $map[(int)$row['driver_id']] = $base + $bonus - $pen;
    }
    return $map;
}

function ff_computeLineupPoints(PDO $db, int $lineupId, array $driverPoints): float {
    if (empty($driverPoints)) return 0.0;
    $stmt = $db->prepare('SELECT rd.driver_id FROM user_selected_drivers usd JOIN race_drivers rd ON usd.race_driver_id = rd.id WHERE usd.user_race_lineup_id = ?');
    $stmt->execute([$lineupId]);
    $sum = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $did = (int)$row['driver_id'];
        $sum += $driverPoints[$did] ?? 0.0;
    }
    return $sum;
}

?>
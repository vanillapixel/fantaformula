<?php
// Migration: Rebuild race_results to drop points_earned / points_awarded and unify race_position column
return function(PDO $db) {
    $cols = $db->query('PRAGMA table_info(race_results)')->fetchAll(PDO::FETCH_ASSOC);
    $hasPointsEarned=false; $hasPointsAwarded=false; $hasRacePos=false; $hasPos=false;
    foreach ($cols as $c) { $n=$c['name']; if($n==='points_earned') $hasPointsEarned=true; if($n==='points_awarded') $hasPointsAwarded=true; if($n==='race_position') $hasRacePos=true; if($n==='position') $hasPos=true; }
    if (!$hasPointsEarned && !$hasPointsAwarded && $hasRacePos && !$hasPos) { return; }
    $db->exec('CREATE TABLE IF NOT EXISTS __tmp_rr (id INTEGER PRIMARY KEY AUTOINCREMENT, race_id INTEGER NOT NULL, driver_id INTEGER NOT NULL, qualifying_position INTEGER, race_position INTEGER, fastest_lap BOOLEAN DEFAULT 0, dnf BOOLEAN DEFAULT 0, calculated_at DATETIME, FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE, FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE, UNIQUE(race_id, driver_id))');
    if ($hasPos && !$hasRacePos) {
        $db->exec('INSERT INTO __tmp_rr (id,race_id,driver_id,qualifying_position,race_position,fastest_lap,dnf,calculated_at) SELECT id,race_id,driver_id,qualifying_position,position,fastest_lap,dnf,calculated_at FROM race_results');
    } else {
        $colsSelect = 'id,race_id,driver_id,qualifying_position,' . ($hasRacePos ? 'race_position':'position') . ',fastest_lap,dnf,calculated_at';
        $db->exec('INSERT INTO __tmp_rr (id,race_id,driver_id,qualifying_position,race_position,fastest_lap,dnf,calculated_at) SELECT ' . $colsSelect . ' FROM race_results');
    }
    $db->exec('DROP TABLE race_results');
    $db->exec('ALTER TABLE __tmp_rr RENAME TO race_results');
};

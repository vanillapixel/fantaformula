<?php
// Migration: Add driver_finish_points JSON column to season_rules if missing and seed default distribution
return function(PDO $db) {
    $cols = $db->query('PRAGMA table_info(season_rules)')->fetchAll(PDO::FETCH_ASSOC);
    $has = false; foreach ($cols as $c) { if ($c['name']==='driver_finish_points') { $has=true; break; } }
    if (!$has) { $db->exec("ALTER TABLE season_rules ADD COLUMN driver_finish_points TEXT"); }
    $dist = json_encode([150,125,100,90,80,75,70,65,60,55,50,45,40,35,30,25,20,15,10,5]);
    $db->exec("UPDATE season_rules SET driver_finish_points = '$dist' WHERE driver_finish_points IS NULL");
};

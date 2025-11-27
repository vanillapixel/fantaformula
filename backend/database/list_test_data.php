<?php
// Simple script to list available races and championships for testing
require_once __DIR__ . '/../config/config.php';

echo "<h2>Available Test Data</h2>\n";
echo "<pre>\n";

try {
    $db = getDB();
    
    echo "=== SEASONS ===\n";
    $seasons = $db->query("SELECT * FROM seasons ORDER BY year DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($seasons as $season) {
        echo "Season {$season['id']}: {$season['year']} ({$season['status']})\n";
    }
    
    echo "\n=== RACES ===\n";
    $races = $db->query("SELECT * FROM races ORDER BY season_id, round_number")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($races as $race) {
        echo "Race {$race['id']}: {$race['name']} (Season {$race['season_id']}, Round {$race['round_number']})\n";
    }
    
    echo "\n=== CHAMPIONSHIPS ===\n";
    $championships = $db->query("SELECT * FROM championships ORDER BY season_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($championships as $champ) {
        echo "Championship {$champ['id']}: {$champ['name']} (Season {$champ['season_id']})\n";
    }
    
    echo "\n=== DRIVERS ===\n";
    $drivers = $db->query("SELECT * FROM drivers ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($drivers as $driver) {
        echo "Driver {$driver['id']}: {$driver['first_name']} {$driver['last_name']} (#{$driver['driver_number']})\n";
    }
    
    echo "\n=== RACE DRIVERS (with prices) ===\n";
    $raceDrivers = $db->query("
        SELECT rd.race_id, rd.driver_id, rd.price, d.first_name, d.last_name, r.name as race_name
        FROM race_drivers rd
        JOIN drivers d ON rd.driver_id = d.id
        JOIN races r ON rd.race_id = r.id
        ORDER BY rd.race_id, rd.price DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($raceDrivers as $rd) {
        echo "Race {$rd['race_id']} ({$rd['race_name']}): {$rd['first_name']} {$rd['last_name']} - \${$rd['price']}\n";
    }
    
    echo "\n✅ Data listing completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";

echo "<h3>Test URLs</h3>\n";
echo "<ul>\n";
echo "<li><a href='/backend/api/races/all.php?season=2025'>All Races for 2025</a></li>\n";
echo "<li><a href='/backend/api/drivers/all.php?race_id=1'>Drivers for Race 1</a></li>\n";
echo "<li><a href='/backend/api/championships/index.php'>All Championships</a></li>\n";
echo "<li><a href='http://localhost:3002/lineup/1/1' target='_blank'>Lineup Creation (Race 1, Championship 1)</a></li>\n";
echo "</ul>\n";
?>

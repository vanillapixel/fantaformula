<?php
// Simple test data seeding script for lineup testing
require_once __DIR__ . '/../config/config.php';

try {
    $db = getDB();
    $db->beginTransaction();

    echo "Starting test data seeding...\n";

    // Check if we have a 2025 season
    $seasonCheck = $db->query("SELECT id FROM seasons WHERE year = 2025 LIMIT 1")->fetch();
    if (!$seasonCheck) {
        echo "Creating 2025 season...\n";
        $db->exec("INSERT INTO seasons (year, status, created_at) VALUES (2025, 'current', CURRENT_TIMESTAMP)");
        $seasonId = $db->lastInsertId();
        
        // Add season rules
        $db->prepare("INSERT INTO season_rules (season_id, default_budget, max_drivers_count) VALUES (?, 250.0, 6)")->execute([$seasonId]);
    } else {
        $seasonId = $seasonCheck['id'];
        echo "Using existing 2025 season (ID: $seasonId)\n";
    }

    // Add some test drivers if they don't exist
    $driverCheck = $db->query("SELECT COUNT(*) as count FROM drivers")->fetch();
    if ($driverCheck['count'] < 5) {
        echo "Adding test drivers...\n";
        $drivers = [
            ['Max', 'VERSTAPPEN', 1, 'VER', 'Dutch'],
            ['Lewis', 'HAMILTON', 44, 'HAM', 'British'],
            ['Charles', 'LECLERC', 16, 'LEC', 'Monégasque'],
            ['Sergio', 'PEREZ', 11, 'PER', 'Mexican'],
            ['Lando', 'NORRIS', 4, 'NOR', 'British'],
            ['Carlos', 'SAINZ', 55, 'SAI', 'Spanish'],
        ];

        foreach ($drivers as $driver) {
            $stmt = $db->prepare("INSERT INTO drivers (first_name, last_name, driver_number, driver_code, nationality) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute($driver);
        }
        echo "Added " . count($drivers) . " test drivers\n";
    }

    // Add a test race if it doesn't exist
    $raceCheck = $db->query("SELECT COUNT(*) as count FROM races WHERE season_id = ?")->execute([$seasonId]);
    $raceCheck = $db->query("SELECT COUNT(*) as count FROM races WHERE season_id = $seasonId")->fetch();
    if ($raceCheck['count'] == 0) {
        echo "Adding test race...\n";
        $stmt = $db->prepare("INSERT INTO races (season_id, name, track_name, country, race_date, qualifying_date, round_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $seasonId,
            'Mexican Grand Prix',
            'Autódromo Hermanos Rodríguez',
            'Mexico',
            '2025-10-27 20:00:00',
            '2025-10-25 21:00:00',
            1
        ]);
        $raceId = $db->lastInsertId();
        echo "Added test race (ID: $raceId)\n";

        // Add race drivers with prices
        $driverIds = $db->query("SELECT id FROM drivers LIMIT 6")->fetchAll(PDO::FETCH_COLUMN);
        $prices = [45.5, 42.3, 38.7, 35.2, 32.8, 29.4];
        
        foreach ($driverIds as $index => $driverId) {
            $stmt = $db->prepare("INSERT INTO race_drivers (race_id, driver_id, price) VALUES (?, ?, ?)");
            $stmt->execute([$raceId, $driverId, $prices[$index] ?? 25.0]);
        }
        echo "Added race drivers with prices\n";
    }

    // Add a test championship if it doesn't exist
    $champCheck = $db->query("SELECT COUNT(*) as count FROM championships WHERE season_id = $seasonId")->fetch();
    if ($champCheck['count'] == 0) {
        echo "Adding test championship...\n";
        $stmt = $db->prepare("INSERT INTO championships (name, season_id, status, is_public, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute(['Test Championship 2025', $seasonId, 'active', 1]);
        $championshipId = $db->lastInsertId();
        echo "Added test championship (ID: $championshipId)\n";
    }

    $db->commit();
    echo "Test data seeding completed successfully!\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

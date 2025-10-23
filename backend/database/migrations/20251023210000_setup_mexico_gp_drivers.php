<?php
// Migration: Setup Mexico City Grand Prix 2025 race drivers with pricing
// Date: 2025-10-23
// Description: Adds all 20 F1 drivers to Mexico City GP with AI-calculated pricing

return function(PDO $db) {
    // First, check if race drivers already exist for Mexico GP (race_id = 68)
    $existingCount = $db->query("SELECT COUNT(*) FROM race_drivers WHERE race_id = 68")->fetchColumn();
    
    if ($existingCount > 0) {
        echo "✓ Mexico City Grand Prix already has $existingCount drivers configured\n";
        return;
    }
    
    // Driver pricing based on current 2025 championship standings and recent performance
    // Prices are in the fantasy budget system (default budget: 250.0)
    $driverPricing = [
        'VER' => 48.5,  // Max Verstappen (Red Bull) - Championship leader
        'NOR' => 45.2,  // Lando Norris (McLaren) - Strong contender
        'LEC' => 42.8,  // Charles Leclerc (Ferrari) - Consistent performer
        'PIA' => 40.1,  // Oscar Piastri (McLaren) - Rising star
        'RUS' => 38.7,  // George Russell (Mercedes) - Reliable points
        'HAM' => 36.9,  // Lewis Hamilton (Ferrari) - Experience + new team
        'ALO' => 34.5,  // Fernando Alonso (Aston Martin) - Veteran skill
        'TSU' => 32.8,  // Yuki Tsunoda (Red Bull) - Promotion boost
        'SAI' => 30.6,  // Carlos Sainz (Williams) - Solid performer
        'AAN' => 28.9,  // Kimi Antonelli (Mercedes) - Promising rookie
        'STR' => 27.2,  // Lance Stroll (Aston Martin) - Home track advantage
        'GAS' => 25.8,  // Pierre Gasly (Alpine) - Experienced
        'LAW' => 24.3,  // Liam Lawson (Racing Bulls) - Mid-season promotion
        'ALB' => 22.9,  // Alex Albon (Williams) - Consistent
        'OCO' => 21.5,  // Esteban Ocon (Haas) - New team dynamics
        'HUL' => 20.2,  // Nico Hulkenberg (Kick Sauber) - Veteran
        'DOO' => 18.8,  // Jack Doohan (Alpine) - Rookie season
        'HAD' => 17.4,  // Isack Hadjar (Racing Bulls) - F2 graduate
        'BOR' => 16.1,  // Gabriel Bortoleto (Kick Sauber) - F2 champion
        'BEA' => 14.7   // Oliver Bearman (Haas) - Young talent
    ];
    
    // Team mappings based on 2025 season lineup
    $teamMappings = [
        'VER' => 'Red Bull Racing',
        'TSU' => 'Red Bull Racing',
        'NOR' => 'McLaren',
        'PIA' => 'McLaren',
        'LEC' => 'Ferrari',
        'HAM' => 'Ferrari',
        'RUS' => 'Mercedes',
        'AAN' => 'Mercedes',
        'ALO' => 'Aston Martin',
        'STR' => 'Aston Martin',
        'GAS' => 'Alpine',
        'DOO' => 'Alpine',
        'LAW' => 'Racing Bulls',
        'HAD' => 'Racing Bulls',
        'SAI' => 'Williams',
        'ALB' => 'Williams',
        'OCO' => 'Haas',
        'BEA' => 'Haas',
        'HUL' => 'Kick Sauber',
        'BOR' => 'Kick Sauber'
    ];
    
    $insertedCount = 0;
    
    foreach ($driverPricing as $driverCode => $price) {
        // Get driver ID
        $driverStmt = $db->prepare("SELECT id FROM drivers WHERE driver_code = ?");
        $driverStmt->execute([$driverCode]);
        $driverId = $driverStmt->fetchColumn();
        
        if (!$driverId) {
            echo "⚠ Warning: Driver with code '$driverCode' not found\n";
            continue;
        }
        
        // Get constructor ID
        $constructorName = $teamMappings[$driverCode];
        $constructorStmt = $db->prepare("SELECT id FROM constructors WHERE name = ? AND season_id = 1");
        $constructorStmt->execute([$constructorName]);
        $constructorId = $constructorStmt->fetchColumn();
        
        if (!$constructorId) {
            echo "⚠ Warning: Constructor '$constructorName' not found\n";
            continue;
        }
        
        // Insert race_driver entry
        $insertStmt = $db->prepare("
            INSERT INTO race_drivers (race_id, driver_id, constructor_id, price, ai_calculated_at)
            VALUES (68, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        if ($insertStmt->execute([$driverId, $constructorId, $price])) {
            $insertedCount++;
            echo "✓ Added $driverCode ($constructorName) - $price credits\n";
        }
    }
    
    echo "✓ Successfully added $insertedCount drivers to Mexico City Grand Prix\n";
    echo "✓ Race weekend: October 24-26, 2025\n";
    echo "✓ Qualifying: October 26, 04:00 UTC (Oct 25, 23:00 CDT)\n";
    echo "✓ Race: October 27, 02:00 UTC (Oct 26, 21:00 CDT)\n";
    echo "✓ Total budget needed for all drivers: " . array_sum($driverPricing) . " credits\n";
    echo "✓ Average driver price: " . round(array_sum($driverPricing) / count($driverPricing), 2) . " credits\n";
};

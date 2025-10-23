<?php
// Migration: Add prediction rules to season_rules
// Date: 2025-10-23
// Description: Adds prediction features for fastest lap, GP winner, and DNF drivers

return function(PDO $db) {
    // Check which columns need to be added
    $cols = $db->query('PRAGMA table_info(season_rules)')->fetchAll(PDO::FETCH_ASSOC);
    $existingCols = array_column($cols, 'name');
    
    $newColumns = [
        'fastest_lap_prediction_enabled' => 'BOOLEAN DEFAULT 1',
        'gp_winner_prediction_enabled' => 'BOOLEAN DEFAULT 1', 
        'dnf_driver_prediction_enabled' => 'BOOLEAN DEFAULT 1',
        'dnf_driver_points' => 'DECIMAL(4,2) DEFAULT 10.0',
        'dnf_driver_in_lineup_multiplier' => 'DECIMAL(4,2) DEFAULT 2.0'
    ];
    
    $added = [];
    
    foreach ($newColumns as $colName => $colDefinition) {
        if (!in_array($colName, $existingCols)) {
            $db->exec("ALTER TABLE season_rules ADD COLUMN $colName $colDefinition");
            $added[] = $colName;
            echo "✓ Added column: $colName\n";
        } else {
            echo "✓ Column already exists: $colName\n";
        }
    }
    
    if (count($added) > 0) {
        echo "✓ Added " . count($added) . " prediction rule columns to season_rules\n";
        echo "✓ New prediction features:\n";
        echo "  - Fastest lap predictions (enabled by default)\n";
        echo "  - GP winner predictions (enabled by default)\n";
        echo "  - DNF driver predictions (enabled by default)\n";
        echo "  - DNF driver points: 10.0 (configurable)\n";
        echo "  - DNF driver in lineup multiplier: 2.0 (configurable)\n";
    } else {
        echo "✓ All prediction rule columns already present\n";
    }
};

<?php
// Migration: Add missing season_rules columns to match schema.sql
// Date: 2025-10-23
// Description: Adds all missing scoring and rule columns to season_rules table

return function(PDO $db) {
    // Check which columns are missing
    $cols = $db->query('PRAGMA table_info(season_rules)')->fetchAll(PDO::FETCH_ASSOC);
    $existingCols = array_column($cols, 'name');
    
    $requiredColumns = [
        'last_to_top10_points' => 'DECIMAL(4,2) DEFAULT 1.0',
        'top10_to_top5_points' => 'DECIMAL(4,2) DEFAULT 2.0', 
        'top4_points' => 'DECIMAL(4,2) DEFAULT 3.0',
        'position_loss_multiplier' => 'DECIMAL(4,2) DEFAULT -0.5',
        'bonus_cap_value' => 'DECIMAL(6,2) DEFAULT 50.0',
        'malus_cap_value' => 'DECIMAL(6,2) DEFAULT -30.0',
        'race_winner_points' => 'DECIMAL(4,2) DEFAULT 25.0',
        'fastest_lap_points' => 'DECIMAL(4,2) DEFAULT 1.0',
        'drs_multiplier_bonus' => 'DECIMAL(4,2) DEFAULT 1.2',
        'drs_cap_value' => 'DECIMAL(6,2) DEFAULT 30.0',
        'max_drivers_count' => 'INTEGER DEFAULT 6'
    ];
    
    $added = [];
    
    foreach ($requiredColumns as $colName => $colDefinition) {
        if (!in_array($colName, $existingCols)) {
            $db->exec("ALTER TABLE season_rules ADD COLUMN $colName $colDefinition");
            $added[] = $colName;
            echo "✓ Added column: $colName\n";
        } else {
            echo "✓ Column already exists: $colName\n";
        }
    }
    
    if (count($added) > 0) {
        echo "✓ Added " . count($added) . " missing columns to season_rules\n";
        echo "✓ Season rules now include complete scoring system:\n";
        echo "  - Position-based scoring (last_to_top10, top10_to_top5, top4)\n";
        echo "  - Bonus/malus caps and multipliers\n";
        echo "  - Race winner and fastest lap points\n";
        echo "  - DRS system with multiplier and cap\n";
        echo "  - Maximum drivers count limit\n";
    } else {
        echo "✓ All required season_rules columns already present\n";
    }
};

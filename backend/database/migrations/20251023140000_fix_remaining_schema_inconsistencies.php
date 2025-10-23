<?php
// Migration: Fix remaining schema inconsistencies
// Date: 2025-10-23
// Description: Adds missing columns to match complete schema.sql

return function(PDO $db) {
    $changes = [];
    
    // Check drivers table for missing active column
    $cols = $db->query('PRAGMA table_info(drivers)')->fetchAll(PDO::FETCH_ASSOC);
    $driverCols = array_column($cols, 'name');
    
    if (!in_array('active', $driverCols)) {
        $db->exec("ALTER TABLE drivers ADD COLUMN active BOOLEAN DEFAULT 1");
        $changes[] = "Added 'active' column to drivers table";
        echo "✓ Added active column to drivers table\n";
    }
    
    // Check race_drivers table for missing ai_calculated_at column
    $cols = $db->query('PRAGMA table_info(race_drivers)')->fetchAll(PDO::FETCH_ASSOC);
    $raceDriverCols = array_column($cols, 'name');
    
    if (!in_array('ai_calculated_at', $raceDriverCols)) {
        $db->exec("ALTER TABLE race_drivers ADD COLUMN ai_calculated_at DATETIME");
        $changes[] = "Added 'ai_calculated_at' column to race_drivers table";
        echo "✓ Added ai_calculated_at column to race_drivers table\n";
    }
    
    // Check user_race_lineups for missing team_id (should already exist from previous migration)
    $cols = $db->query('PRAGMA table_info(user_race_lineups)')->fetchAll(PDO::FETCH_ASSOC);
    $lineupCols = array_column($cols, 'name');
    
    if (!in_array('team_id', $lineupCols)) {
        $db->exec("ALTER TABLE user_race_lineups ADD COLUMN team_id INTEGER NULL REFERENCES championship_teams(id) ON DELETE SET NULL");
        $changes[] = "Added 'team_id' column to user_race_lineups table";
        echo "✓ Added team_id column to user_race_lineups table\n";
    }
    
    if (count($changes) > 0) {
        echo "✓ Schema inconsistencies fixed:\n";
        foreach ($changes as $change) {
            echo "  - $change\n";
        }
    } else {
        echo "✓ No schema inconsistencies found - all tables match schema.sql\n";
    }
};

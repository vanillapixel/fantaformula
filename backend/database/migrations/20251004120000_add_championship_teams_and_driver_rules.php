 <?php
// Migration: Add championship teams and driver selection rules
// Date: 2025-10-04
// Description: Adds team functionality within championships and new driver selection rules

return function(PDO $db) {
    // Add new rules to season_rules table (check if they don't exist first)
    $cols = $db->query('PRAGMA table_info(season_rules)')->fetchAll(PDO::FETCH_ASSOC);
    $hasCommonDrivers = false;
    $hasDifferentDrivers = false;
    
    foreach ($cols as $c) {
        if ($c['name'] === 'min_common_drivers_count') $hasCommonDrivers = true;
        if ($c['name'] === 'min_different_drivers_count') $hasDifferentDrivers = true;
    }
    
    if (!$hasCommonDrivers) {
        $db->exec("ALTER TABLE season_rules ADD COLUMN min_common_drivers_count INTEGER DEFAULT 2");
        echo "✓ Added min_common_drivers_count column\n";
    }
    
    if (!$hasDifferentDrivers) {
        $db->exec("ALTER TABLE season_rules ADD COLUMN min_different_drivers_count INTEGER DEFAULT 2");
        echo "✓ Added min_different_drivers_count column\n";
    }
    
    // Check if championship_teams table exists
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('championship_teams', $tables)) {
        // Create championship_teams table
        $db->exec("
            CREATE TABLE championship_teams (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                championship_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                max_members INTEGER DEFAULT NULL,
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (championship_id) REFERENCES championships(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(championship_id, name)
            )
        ");
        echo "✓ Created championship_teams table\n";
    }
    
    if (!in_array('championship_team_members', $tables)) {
        // Create championship_team_members table
        $db->exec("
            CREATE TABLE championship_team_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_captain BOOLEAN DEFAULT 0,
                FOREIGN KEY (team_id) REFERENCES championship_teams(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(team_id, user_id)
            )
        ");
        echo "✓ Created championship_team_members table\n";
    }
    
    // Check if team_id column exists in user_race_lineups
    $lineupCols = $db->query('PRAGMA table_info(user_race_lineups)')->fetchAll(PDO::FETCH_ASSOC);
    $hasTeamId = false;
    foreach ($lineupCols as $c) {
        if ($c['name'] === 'team_id') {
            $hasTeamId = true;
            break;
        }
    }
    
    if (!$hasTeamId) {
        $db->exec("ALTER TABLE user_race_lineups ADD COLUMN team_id INTEGER NULL REFERENCES championship_teams(id) ON DELETE SET NULL");
        echo "✓ Added team_id to user_race_lineups\n";
    }
    
    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_teams_championship ON championship_teams(championship_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_teams_creator ON championship_teams(created_by)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_team_members_team ON championship_team_members(team_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_team_members_user ON championship_team_members(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_lineups_team ON user_race_lineups(team_id)");
    
    echo "✓ Created indexes for championship teams\n";
    echo "✓ Migration completed: Championship teams and driver selection rules added\n";
};

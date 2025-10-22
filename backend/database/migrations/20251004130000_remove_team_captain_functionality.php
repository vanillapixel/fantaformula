<?php
// Migration: Remove team captain functionality - teams managed by championship admins
// Date: 2025-10-04
// Description: Removes is_captain column from championship_team_members

return function(PDO $db) {
    // Check if is_captain column exists in championship_team_members
    $cols = $db->query('PRAGMA table_info(championship_team_members)')->fetchAll(PDO::FETCH_ASSOC);
    $hasCaptainCol = false;
    
    foreach ($cols as $c) {
        if ($c['name'] === 'is_captain') {
            $hasCaptainCol = true;
            break;
        }
    }
    
    if ($hasCaptainCol) {
        // SQLite doesn't support DROP COLUMN, so we need to rebuild the table
        $db->exec('PRAGMA foreign_keys = OFF');
        
        try {
            $db->beginTransaction();
            
            // Create new table without is_captain column
            $db->exec("
                CREATE TABLE championship_team_members_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    team_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (team_id) REFERENCES championship_teams(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE(team_id, user_id)
                )
            ");
            
            // Copy data from old table (excluding is_captain column)
            $db->exec("
                INSERT INTO championship_team_members_new (id, team_id, user_id, joined_at)
                SELECT id, team_id, user_id, joined_at 
                FROM championship_team_members
            ");
            
            // Drop old table
            $db->exec("DROP TABLE championship_team_members");
            
            // Rename new table
            $db->exec("ALTER TABLE championship_team_members_new RENAME TO championship_team_members");
            
            // Recreate indexes
            $db->exec("CREATE INDEX IF NOT EXISTS idx_team_members_team ON championship_team_members(team_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_team_members_user ON championship_team_members(user_id)");
            
            $db->commit();
            echo "✓ Removed is_captain column from championship_team_members\n";
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        } finally {
            $db->exec('PRAGMA foreign_keys = ON');
        }
    } else {
        echo "✓ is_captain column not found (already removed or never existed)\n";
    }
    
    echo "✓ Teams are now managed exclusively by championship admins\n";
};

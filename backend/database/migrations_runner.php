<?php
// Lightweight migrations runner
// Adds new columns / minor schema changes safely (idempotent)

require_once __DIR__ . '/../config/database.php';

$__ff_migration_messages = [];

function ff_applyMigrations(PDO $db) {
    global $__ff_migration_messages;
    // 0. Rename f1_teams -> constructors and column f1_team_id -> constructor_id in race_drivers
    try {
        // Detect if old table exists and new one not yet created
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $hasOld = in_array('f1_teams', $tables);
        $hasNew = in_array('constructors', $tables);
        if ($hasOld && !$hasNew) {
            $db->exec('ALTER TABLE f1_teams RENAME TO constructors');
            $__ff_migration_messages[] = 'Renamed table f1_teams -> constructors';
        } else if ($hasNew) {
            $__ff_migration_messages[] = 'Constructors table present';
        }
        // Check race_drivers column
        $needColumnRename = false; $hasConstructorCol = false; $hasTeamCol = false;
        $cols = $db->query('PRAGMA table_info(race_drivers)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if (strcasecmp($c['name'],'constructor_id')===0) $hasConstructorCol = true;
            if (strcasecmp($c['name'],'f1_team_id')===0) $hasTeamCol = true;
        }
        if (!$hasConstructorCol && $hasTeamCol) {
            // Attempt direct rename (SQLite >= 3.25)
            try {
                $db->exec('ALTER TABLE race_drivers RENAME COLUMN f1_team_id TO constructor_id');
                $__ff_migration_messages[] = 'Renamed column race_drivers.f1_team_id -> constructor_id';
            } catch (Exception $inner) {
                // Fallback: rebuild table
                $db->beginTransaction();
                $__ff_migration_messages[] = 'Rebuilding race_drivers to rename f1_team_id -> constructor_id';
                $db->exec('CREATE TABLE IF NOT EXISTS __tmp_rd (id INTEGER PRIMARY KEY AUTOINCREMENT, race_id INTEGER NOT NULL, driver_id INTEGER NOT NULL, constructor_id INTEGER NOT NULL, price DECIMAL(6,2) NOT NULL, ai_calculated_at DATETIME, FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE, FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE, FOREIGN KEY (constructor_id) REFERENCES constructors(id) ON DELETE CASCADE, UNIQUE(race_id, driver_id))');
                $db->exec('INSERT INTO __tmp_rd (id,race_id,driver_id,constructor_id,price,ai_calculated_at) SELECT id,race_id,driver_id,f1_team_id,price,ai_calculated_at FROM race_drivers');
                $db->exec('DROP TABLE race_drivers');
                $db->exec('ALTER TABLE __tmp_rd RENAME TO race_drivers');
                $db->commit();
                $__ff_migration_messages[] = 'Rebuilt race_drivers with constructor_id column';
            }
        } else if ($hasConstructorCol) {
            $__ff_migration_messages[] = 'race_drivers.constructor_id already present';
        }
    } catch (Exception $e) {
        $__ff_migration_messages[] = 'Constructor rename migration failed: '.$e->getMessage();
        error_log('Migration (constructors rename) failed: '.$e->getMessage());
    }
    // 1. Add is_super_admin column to users if missing
    try {
        $stmt = $db->query('PRAGMA table_info(users)');
        $hasCol = false;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (strcasecmp($row['name'], 'is_super_admin') === 0) { $hasCol = true; break; }
        }
        if (!$hasCol) {
            $db->exec('ALTER TABLE users ADD COLUMN is_super_admin INTEGER DEFAULT 0');
            $__ff_migration_messages[] = 'Added column users.is_super_admin';
        }
    } catch (Exception $e) {
        error_log('Migration (is_super_admin) failed: ' . $e->getMessage());
        $__ff_migration_messages[] = 'Failed adding is_super_admin: ' . $e->getMessage();
    }

    // 3. Rename user_race_teams -> user_race_lineups if not yet renamed
    try {
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $hasOld = in_array('user_race_teams',$tables);
        $hasNew = in_array('user_race_lineups',$tables);
        if ($hasOld && !$hasNew) {
            // Simple rename
            $db->exec('ALTER TABLE user_race_teams RENAME TO user_race_lineups');
            $__ff_migration_messages[] = 'Renamed table user_race_teams -> user_race_lineups';
            // Rename indexes if they exist (SQLite doesn't auto-rename)
            $idx = $db->query("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name='user_race_lineups'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($idx as $i) {
                if (strpos($i['name'],'user_race_teams')!==false) {
                    $db->exec('DROP INDEX ' . $i['name']);
                }
            }
            // Recreate desired indexes
            $db->exec('CREATE INDEX IF NOT EXISTS idx_user_race_lineups_user ON user_race_lineups(user_id)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_user_race_lineups_race ON user_race_lineups(race_id)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_user_race_lineups_championship ON user_race_lineups(championship_id)');
        } else if ($hasNew) {
            $__ff_migration_messages[] = 'user_race_lineups already present';
        }
        // Adjust foreign key column names in user_selected_drivers if needed
        $usdCols = $db->query('PRAGMA table_info(user_selected_drivers)')->fetchAll(PDO::FETCH_ASSOC);
        $hasOldFk = false; $hasNewFk=false; foreach($usdCols as $c){ if($c['name']==='user_race_team_id') $hasOldFk=true; if($c['name']==='user_race_lineup_id') $hasNewFk=true; }
        if ($hasOldFk && !$hasNewFk) {
            try {
                $db->exec('ALTER TABLE user_selected_drivers RENAME COLUMN user_race_team_id TO user_race_lineup_id');
                $__ff_migration_messages[] = 'Renamed column user_selected_drivers.user_race_team_id -> user_race_lineup_id';
            } catch (Exception $inner) {
                // Rebuild table fallback
                $db->beginTransaction();
                $__ff_migration_messages[] = 'Rebuilding user_selected_drivers to rename FK column';
                $db->exec('CREATE TABLE __tmp_usd (id INTEGER PRIMARY KEY AUTOINCREMENT, user_race_lineup_id INTEGER NOT NULL, race_driver_id INTEGER NOT NULL, FOREIGN KEY (user_race_lineup_id) REFERENCES user_race_lineups(id) ON DELETE CASCADE, FOREIGN KEY (race_driver_id) REFERENCES race_drivers(id) ON DELETE CASCADE, UNIQUE(user_race_lineup_id, race_driver_id))');
                $db->exec('INSERT INTO __tmp_usd (id,user_race_lineup_id,race_driver_id) SELECT id,user_race_team_id,race_driver_id FROM user_selected_drivers');
                $db->exec('DROP TABLE user_selected_drivers');
                $db->exec('ALTER TABLE __tmp_usd RENAME TO user_selected_drivers');
                $db->commit();
                $__ff_migration_messages[] = 'Rebuilt user_selected_drivers with user_race_lineup_id';
            }
        } else if ($hasNewFk) {
            $__ff_migration_messages[] = 'user_selected_drivers.user_race_lineup_id already present';
        }
    } catch (Exception $e) {
        $__ff_migration_messages[] = 'Lineup rename migration failed: '.$e->getMessage();
        error_log('Migration (lineups rename) failed: '.$e->getMessage());
    }

    // 2. Seed 2025 race calendar (idempotent)
    try {
        $seasonStmt = $db->prepare('SELECT id FROM seasons WHERE year = ? LIMIT 1');
        $seasonStmt->execute([2025]);
        $seasonId = $seasonStmt->fetchColumn();
        if ($seasonId) {
            // Define race calendar (round_number order). Times are placeholder (UTC-ish) and dates approximate.
            $races = [
                // round, name, track, country, race_date, qualifying_date
                [1,  'Bahrain Grand Prix',           'Bahrain International Circuit', 'Bahrain',       '2025-03-16 15:00:00', '2025-03-15 15:00:00'],
                [2,  'Saudi Arabian Grand Prix',     'Jeddah Corniche Circuit',       'Saudi Arabia',  '2025-03-23 18:00:00', '2025-03-22 18:00:00'],
                [3,  'Australian Grand Prix',        'Albert Park Circuit',           'Australia',     '2025-04-06 05:00:00', '2025-04-05 05:00:00'],
                [4,  'Japanese Grand Prix',          'Suzuka International Racing Course','Japan',   '2025-04-13 05:00:00', '2025-04-12 05:00:00'],
                [5,  'Chinese Grand Prix',           'Shanghai International Circuit', 'China',        '2025-04-27 07:00:00', '2025-04-26 07:00:00'],
                [6,  'Miami Grand Prix',             'Miami International Autodrome',  'USA',          '2025-05-04 20:30:00', '2025-05-03 20:30:00'],
                [7,  'Emilia Romagna Grand Prix',    'Autodromo Internazionale Enzo e Dino Ferrari','Italy','2025-05-18 14:00:00','2025-05-17 14:00:00'],
                [8,  'Monaco Grand Prix',            'Circuit de Monaco',              'Monaco',       '2025-05-25 13:00:00', '2025-05-24 13:00:00'],
                [9,  'Canadian Grand Prix',          'Circuit Gilles Villeneuve',      'Canada',       '2025-06-08 18:00:00', '2025-06-07 18:00:00'],
                [10, 'Spanish Grand Prix',           'Circuit de Barcelona-Catalunya', 'Spain',        '2025-06-22 14:00:00', '2025-06-21 14:00:00'],
                [11, 'Austrian Grand Prix',          'Red Bull Ring',                  'Austria',      '2025-06-29 14:00:00', '2025-06-28 14:00:00'],
                [12, 'British Grand Prix',           'Silverstone Circuit',            'United Kingdom','2025-07-06 14:00:00', '2025-07-05 14:00:00'],
                [13, 'Hungarian Grand Prix',         'Hungaroring',                    'Hungary',      '2025-07-20 14:00:00', '2025-07-19 14:00:00'],
                [14, 'Belgian Grand Prix',           'Circuit de Spa-Francorchamps',   'Belgium',      '2025-07-27 14:00:00', '2025-07-26 14:00:00'],
                [15, 'Dutch Grand Prix',             'Circuit Zandvoort',              'Netherlands',  '2025-08-24 14:00:00', '2025-08-23 14:00:00'],
                [16, 'Italian Grand Prix',           'Autodromo Nazionale Monza',      'Italy',        '2025-08-31 14:00:00', '2025-08-30 14:00:00'],
                [17, 'Azerbaijan Grand Prix',        'Baku City Circuit',              'Azerbaijan',   '2025-09-14 13:00:00', '2025-09-13 13:00:00'],
                [18, 'Singapore Grand Prix',         'Marina Bay Street Circuit',      'Singapore',    '2025-09-21 12:00:00', '2025-09-20 12:00:00'],
                [19, 'United States Grand Prix',     'Circuit of the Americas',        'USA',          '2025-10-05 20:00:00', '2025-10-04 20:00:00'],
                [20, 'Mexico City Grand Prix',       'Autódromo Hermanos Rodríguez',   'Mexico',       '2025-10-26 21:00:00', '2025-10-25 21:00:00'],
                [21, 'São Paulo Grand Prix',         'Autódromo José Carlos Pace',     'Brazil',       '2025-11-02 17:00:00', '2025-11-01 17:00:00'],
                [22, 'Las Vegas Grand Prix',         'Las Vegas Strip Circuit',        'USA',          '2025-11-22 06:00:00', '2025-11-21 06:00:00'],
                [23, 'Qatar Grand Prix',             'Lusail International Circuit',   'Qatar',        '2025-11-30 17:00:00', '2025-11-29 17:00:00'],
                [24, 'Abu Dhabi Grand Prix',         'Yas Marina Circuit',             'UAE',          '2025-12-07 13:00:00', '2025-12-06 13:00:00'],
            ];

            $checkStmt = $db->prepare('SELECT id FROM races WHERE season_id = ? AND round_number = ? LIMIT 1');
            $insertStmt = $db->prepare('INSERT INTO races (season_id, name, track_name, country, race_date, qualifying_date, round_number) VALUES (?,?,?,?,?,?,?)');
            $inserted = 0;
            foreach ($races as $r) {
                [$round, $name, $track, $country, $raceDate, $qualDate] = $r;
                $checkStmt->execute([$seasonId, $round]);
                if ($checkStmt->fetchColumn()) { continue; } // already present
                $insertStmt->execute([$seasonId, $name, $track, $country, $raceDate, $qualDate, $round]);
                $inserted++;
            }
            if ($inserted) {
                $msg = "Seeded $inserted 2025 races";
                error_log("Migration: $msg");
                $__ff_migration_messages[] = $msg;
            } else {
                $__ff_migration_messages[] = 'Race calendar already present (no new races inserted)';
            }
        }
    } catch (Exception $e) {
        error_log('Migration (seed 2025 races) failed: ' . $e->getMessage());
        $__ff_migration_messages[] = 'Failed seeding races: ' . $e->getMessage();
    }
}

// Apply migrations immediately
try {
    ff_applyMigrations(getDB());
    $__ff_migration_messages[] = 'Migrations completed successfully';
} catch (Exception $e) {
    // Swallow errors so app can continue
    $err = 'Migration runner error: ' . $e->getMessage();
    error_log($err);
    $__ff_migration_messages[] = $err;
}

// If accessed directly via browser/CLI, output a simple status page (no output when included through config)
if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: text/html; charset=utf-8');
    $ok = true;
    foreach ($__ff_migration_messages as $m) {
        if (stripos($m, 'fail') !== false || stripos($m, 'error') !== false) { $ok = false; break; }
    }
    echo '<!DOCTYPE html><html><head><title>Migrations Status</title><style>body{background:#000;color:#eee;font-family:Arial,Helvetica,sans-serif;padding:40px;}code{background:#111;padding:2px 4px;border-radius:3px;} .ok{color:#4ade80;} .fail{color:#f87171;} ul{line-height:1.5;} a{color:#e62d2d;text-decoration:none;} a:hover{text-decoration:underline;} </style></head><body>'; 
    echo '<h1>Migrations ' . ($ok ? '<span class="ok">OK</span>' : '<span class="fail">ISSUES</span>') . '</h1>'; 
    echo '<ul>'; foreach ($__ff_migration_messages as $m) { echo '<li>' . htmlspecialchars($m) . '</li>'; } echo '</ul>'; 
    echo '<p><em>This page only appears when you open <code>migrations_runner.php</code> directly. Normal API requests stay clean.</em></p>';
    echo '</body></html>';
    exit;
}
?>

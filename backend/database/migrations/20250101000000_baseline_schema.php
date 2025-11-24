<?php
// Baseline schema migration (idempotent creates)
return [
  'up' => function(PDO $db) {
    // Create tables if not exist (subset core). Adjust as project evolves.
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, email TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, is_super_admin INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $db->exec("CREATE TABLE IF NOT EXISTS seasons (id INTEGER PRIMARY KEY AUTOINCREMENT, year INTEGER UNIQUE NOT NULL, status TEXT DEFAULT 'upcoming', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS season_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, season_id INTEGER NOT NULL, default_budget DECIMAL(6,2) DEFAULT 250.0, user_position_points TEXT DEFAULT '[25,18,14,10,6,3,1]', driver_finish_points TEXT, FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE, UNIQUE(season_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS championships (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, season_id INTEGER NOT NULL, settings TEXT, status TEXT DEFAULT 'upcoming', max_participants INTEGER, is_public BOOLEAN DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE)");
    $db->exec('CREATE TABLE IF NOT EXISTS championship_admins (id INTEGER PRIMARY KEY AUTOINCREMENT, championship_id INTEGER NOT NULL, user_id INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(championship_id,user_id), FOREIGN KEY (championship_id) REFERENCES championships(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)');
    $db->exec('CREATE TABLE IF NOT EXISTS championship_participants (id INTEGER PRIMARY KEY AUTOINCREMENT, championship_id INTEGER NOT NULL, user_id INTEGER NOT NULL, joined_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(championship_id,user_id), FOREIGN KEY (championship_id) REFERENCES championships(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)');
    $db->exec('CREATE TABLE IF NOT EXISTS races (id INTEGER PRIMARY KEY AUTOINCREMENT, season_id INTEGER NOT NULL, name TEXT NOT NULL, track_name TEXT NOT NULL, country TEXT NOT NULL, race_date DATETIME NOT NULL, qualifying_date DATETIME NOT NULL, round_number INTEGER NOT NULL, budget_override DECIMAL(6,2), FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE)');
    $db->exec('CREATE TABLE IF NOT EXISTS constructors (id INTEGER PRIMARY KEY AUTOINCREMENT, season_id INTEGER NOT NULL, name TEXT NOT NULL, short_name TEXT, color_primary TEXT, picture_url TEXT, logo_url TEXT, FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE)');
  // Drivers no longer have an 'active' flag (derived availability) & we avoid storing computed aggregates elsewhere.
  $db->exec('CREATE TABLE IF NOT EXISTS drivers (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT NOT NULL, last_name TEXT NOT NULL, driver_number INTEGER, driver_code TEXT, nationality TEXT, picture_url TEXT, logo_url TEXT)');
  // ai_calculated_at superseded by created_at for pricing timestamp.
  $db->exec('CREATE TABLE IF NOT EXISTS race_drivers (id INTEGER PRIMARY KEY AUTOINCREMENT, race_id INTEGER NOT NULL, driver_id INTEGER NOT NULL, constructor_id INTEGER NOT NULL, price DECIMAL(6,2) NOT NULL, created_at DATETIME, UNIQUE(race_id,driver_id), FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE, FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE, FOREIGN KEY (constructor_id) REFERENCES constructors(id) ON DELETE CASCADE)');
  // race_results stores only raw positions & flags; no fantasy points persisted.
  $db->exec('CREATE TABLE IF NOT EXISTS race_results (id INTEGER PRIMARY KEY AUTOINCREMENT, race_id INTEGER NOT NULL, driver_id INTEGER NOT NULL, starting_position INTEGER, race_position INTEGER, fastest_lap BOOLEAN DEFAULT 0, dnf BOOLEAN DEFAULT 0, dns BOOLEAN DEFAULT 0, created_at DATETIME, UNIQUE(race_id,driver_id), FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE, FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE)');
  // user_race_lineups: drop total_cost & total_points (computed on demand in API responses)
  $db->exec('CREATE TABLE IF NOT EXISTS user_race_lineups (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, race_id INTEGER NOT NULL, championship_id INTEGER NOT NULL, drs_enabled BOOLEAN DEFAULT 1, submitted_at DATETIME, UNIQUE(user_id,race_id,championship_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE, FOREIGN KEY (championship_id) REFERENCES championships(id) ON DELETE CASCADE)');
    $db->exec('CREATE TABLE IF NOT EXISTS user_selected_drivers (id INTEGER PRIMARY KEY AUTOINCREMENT, user_race_lineup_id INTEGER NOT NULL, race_driver_id INTEGER NOT NULL, UNIQUE(user_race_lineup_id,race_driver_id), FOREIGN KEY (user_race_lineup_id) REFERENCES user_race_lineups(id) ON DELETE CASCADE, FOREIGN KEY (race_driver_id) REFERENCES race_drivers(id) ON DELETE CASCADE)');
  },
  'down' => function(PDO $db) {
    // Drop tables (order matters). Intended only for development resets.
    $tables = ['user_selected_drivers','user_race_lineups','race_results','race_drivers','drivers','constructors','races','championship_participants','championship_admins','championships','season_rules','seasons','users'];
    foreach ($tables as $t) { $db->exec('DROP TABLE IF EXISTS ' . $t); }
  }
];

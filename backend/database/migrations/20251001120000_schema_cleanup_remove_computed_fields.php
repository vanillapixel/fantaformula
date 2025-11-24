<?php
// Migration: Remove stored computed fields & rename rule columns; add dns; rename *_calculated_at -> created_at
// Steps (SQLite-safe via table rebuilds):
//  - Drop user_race_lineups.total_cost,total_points
//  - Drop drivers.active
//  - race_drivers.ai_calculated_at -> created_at
//  - race_results: remove points_earned, calculated_at->created_at, add dns (default 0)
//  - season_rules: rename last_to_top10_points->gain_backfield_per_pos, top10_to_top5_points->gain_midfield_per_pos, top4_points->gain_front_per_pos
// NOTE: No application code updated yet; endpoints using old column names will break until refactor.
return [
  'up' => function(PDO $db) {
    $db->exec('PRAGMA foreign_keys = OFF');
    try {
      $db->beginTransaction();

      // 1. Rebuild user_race_lineups without total_cost,total_points
      $cols = $db->query("PRAGMA table_info(user_race_lineups)")->fetchAll(PDO::FETCH_ASSOC);
      $hasTotalCost = false; $hasTotalPoints = false; foreach($cols as $c){ if($c['name']==='total_cost') $hasTotalCost=true; if($c['name']==='total_points') $hasTotalPoints=true; }
      if ($hasTotalCost || $hasTotalPoints) {
        $db->exec("CREATE TABLE __tmp_url (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, race_id INTEGER NOT NULL, championship_id INTEGER NOT NULL, drs_enabled BOOLEAN DEFAULT 1, submitted_at DATETIME, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE, FOREIGN KEY (championship_id) REFERENCES championships(id) ON DELETE CASCADE, UNIQUE(user_id,race_id,championship_id))");
        $db->exec("INSERT INTO __tmp_url (id,user_id,race_id,championship_id,drs_enabled,submitted_at) SELECT id,user_id,race_id,championship_id,COALESCE(drs_enabled,1),submitted_at FROM user_race_lineups");
        $db->exec('DROP TABLE user_race_lineups');
        $db->exec('ALTER TABLE __tmp_url RENAME TO user_race_lineups');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_user_race_lineups_user ON user_race_lineups(user_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_user_race_lineups_race ON user_race_lineups(race_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_user_race_lineups_championship ON user_race_lineups(championship_id)');
      }

      // 2. Rebuild drivers without active column
      $cols = $db->query('PRAGMA table_info(drivers)')->fetchAll(PDO::FETCH_ASSOC);
      $hasActive=false; foreach($cols as $c){ if($c['name']==='active') $hasActive=true; }
      if ($hasActive) {
        $db->exec("CREATE TABLE __tmp_drivers (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT NOT NULL, last_name TEXT NOT NULL, driver_number INTEGER, driver_code TEXT, nationality TEXT, picture_url TEXT, logo_url TEXT)");
        $db->exec("INSERT INTO __tmp_drivers (id,first_name,last_name,driver_number,driver_code,nationality,picture_url,logo_url) SELECT id,first_name,last_name,driver_number,driver_code,nationality,picture_url,logo_url FROM drivers");
        $db->exec('DROP TABLE drivers');
        $db->exec('ALTER TABLE __tmp_drivers RENAME TO drivers');
      }

      // 3. race_drivers rename ai_calculated_at -> created_at
      $cols = $db->query('PRAGMA table_info(race_drivers)')->fetchAll(PDO::FETCH_ASSOC);
      $hasAI=false; $hasCreated=false; foreach($cols as $c){ if($c['name']==='ai_calculated_at') $hasAI=true; if($c['name']==='created_at') $hasCreated=true; }
      if ($hasAI && !$hasCreated) {
        // SQLite supports RENAME COLUMN in modern versions, fallback to rebuild
        try {
          $db->exec('ALTER TABLE race_drivers RENAME COLUMN ai_calculated_at TO created_at');
        } catch (Exception $e) {
          $db->exec("CREATE TABLE __tmp_rd (id INTEGER PRIMARY KEY AUTOINCREMENT, race_id INTEGER NOT NULL, driver_id INTEGER NOT NULL, constructor_id INTEGER NOT NULL, price DECIMAL(6,2) NOT NULL, created_at DATETIME, UNIQUE(race_id,driver_id), FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE, FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE, FOREIGN KEY (constructor_id) REFERENCES constructors(id) ON DELETE CASCADE)");
          $db->exec("INSERT INTO __tmp_rd (id,race_id,driver_id,constructor_id,price,created_at) SELECT id,race_id,driver_id,constructor_id,price,ai_calculated_at FROM race_drivers");
          $db->exec('DROP TABLE race_drivers');
          $db->exec('ALTER TABLE __tmp_rd RENAME TO race_drivers');
          $db->exec('CREATE INDEX IF NOT EXISTS idx_race_drivers_race ON race_drivers(race_id)');
          $db->exec('CREATE INDEX IF NOT EXISTS idx_race_drivers_driver ON race_drivers(driver_id)');
        }
      }

      // 4. race_results rebuild: remove points_earned, rename calculated_at->created_at, add dns
      $cols = $db->query('PRAGMA table_info(race_results)')->fetchAll(PDO::FETCH_ASSOC);
      $needRebuild=false; $hasPointsEarned=false; $hasCalc=false; $hasCreated=false; $hasDns=false;
      foreach($cols as $c){
        if($c['name']==='points_earned') $hasPointsEarned=true;
        if($c['name']==='calculated_at') $hasCalc=true;
        if($c['name']==='created_at') $hasCreated=true;
        if($c['name']==='dns') $hasDns=true;
      }
      if ($hasPointsEarned || ($hasCalc && !$hasCreated) || !$hasDns) { $needRebuild=true; }
      if ($needRebuild) {
        $db->exec("CREATE TABLE __tmp_rr (id INTEGER PRIMARY KEY AUTOINCREMENT, race_id INTEGER NOT NULL, driver_id INTEGER NOT NULL, starting_position INTEGER, race_position INTEGER, fastest_lap BOOLEAN DEFAULT 0, dnf BOOLEAN DEFAULT 0, dns BOOLEAN DEFAULT 0, created_at DATETIME, UNIQUE(race_id,driver_id), FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE, FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE)");
        // choose created_at source: calculated_at else existing created_at else CURRENT_TIMESTAMP
        $sourceCreated = $hasCalc && !$hasCreated ? 'calculated_at' : ($hasCreated ? 'created_at' : 'NULL');
        $qCols = $db->query("SELECT name FROM pragma_table_info('race_results')")->fetchAll(PDO::FETCH_COLUMN);
        $sel = [];
        if (in_array('id',$qCols)) $sel[]='id';
        $sel[]='race_id'; $sel[]='driver_id';
        $sel[] = in_array('starting_position',$qCols)?'starting_position':'NULL as starting_position';
        $sel[] = in_array('race_position',$qCols)?'race_position':(in_array('position',$qCols)?'position as race_position':'NULL as race_position');
        $sel[] = in_array('fastest_lap',$qCols)?'fastest_lap':'0 as fastest_lap';
        $sel[] = in_array('dnf',$qCols)?'dnf':'0 as dnf';
        $sel[] = '0 as dns';
        $sel[] = $sourceCreated . ' as created_at';
        $db->exec('INSERT INTO __tmp_rr (' . 'id,race_id,driver_id,starting_position,race_position,fastest_lap,dnf,dns,created_at' . ') SELECT ' . implode(',', $sel) . ' FROM race_results');
        $db->exec('DROP TABLE race_results');
        $db->exec('ALTER TABLE __tmp_rr RENAME TO race_results');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_race_results_race ON race_results(race_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_race_results_driver ON race_results(driver_id)');
      }

      // 5. season_rules rename columns (rebuild)
      $cols = $db->query('PRAGMA table_info(season_rules)')->fetchAll(PDO::FETCH_ASSOC);
      $hasOld1=false; $hasOld2=false; $hasOld3=false; foreach($cols as $c){ if($c['name']==='last_to_top10_points') $hasOld1=true; if($c['name']==='top10_to_top5_points') $hasOld2=true; if($c['name']==='top4_points') $hasOld3=true; }
      if ($hasOld1 || $hasOld2 || $hasOld3) {
        $db->exec("CREATE TABLE __tmp_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, season_id INTEGER NOT NULL, default_budget DECIMAL(6,2) DEFAULT 250.0, gain_backfield_per_pos DECIMAL(4,2), gain_midfield_per_pos DECIMAL(4,2), gain_front_per_pos DECIMAL(4,2), position_loss_multiplier DECIMAL(4,2) DEFAULT -0.5, bonus_cap_value DECIMAL(6,2) DEFAULT 50.0, malus_cap_value DECIMAL(6,2) DEFAULT -30.0, race_winner_points DECIMAL(4,2) DEFAULT 25.0, fastest_lap_points DECIMAL(4,2) DEFAULT 1.0, drs_multiplier_bonus DECIMAL(4,2) DEFAULT 1.2, drs_cap_value DECIMAL(6,2) DEFAULT 30.0, max_drivers_count INTEGER DEFAULT 6, user_position_points TEXT DEFAULT '[25,18,14,10,6,3,1]', driver_finish_points TEXT, FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE, UNIQUE(season_id))");
        $selectCols = [];
        $selectCols[]='id';
        $selectCols[]='season_id';
        $selectCols[]='default_budget';
        $selectCols[]= $hasOld1? 'last_to_top10_points as gain_backfield_per_pos' : 'NULL as gain_backfield_per_pos';
        $selectCols[]= $hasOld2? 'top10_to_top5_points as gain_midfield_per_pos' : 'NULL as gain_midfield_per_pos';
        $selectCols[]= $hasOld3? 'top4_points as gain_front_per_pos' : 'NULL as gain_front_per_pos';
        $selectCols[]='position_loss_multiplier';
        $selectCols[]='bonus_cap_value';
        $selectCols[]='malus_cap_value';
        $selectCols[]='race_winner_points';
        $selectCols[]='fastest_lap_points';
        $selectCols[]='drs_multiplier_bonus';
        $selectCols[]='drs_cap_value';
        $selectCols[]='max_drivers_count';
        $selectCols[]='user_position_points';
        $selectCols[]='driver_finish_points';
        $db->exec('INSERT INTO __tmp_rules (' . 'id,season_id,default_budget,gain_backfield_per_pos,gain_midfield_per_pos,gain_front_per_pos,position_loss_multiplier,bonus_cap_value,malus_cap_value,race_winner_points,fastest_lap_points,drs_multiplier_bonus,drs_cap_value,max_drivers_count,user_position_points,driver_finish_points' . ') SELECT ' . implode(',', $selectCols) . ' FROM season_rules');
        $db->exec('DROP TABLE season_rules');
        $db->exec('ALTER TABLE __tmp_rules RENAME TO season_rules');
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_season_rules_season ON season_rules(season_id)');
      }

      $db->commit();
    } catch (Exception $e) {
      $db->rollBack();
      throw $e;
    } finally {
      $db->exec('PRAGMA foreign_keys = ON');
    }
  },
  'down' => function(PDO $db) {
    // Best-effort partial rollback (does not restore dropped computed data values)
    $db->exec('PRAGMA foreign_keys = OFF');
    try {
      $db->beginTransaction();
      // Reverse season_rules rename if new columns exist
      $cols = $db->query('PRAGMA table_info(season_rules)')->fetchAll(PDO::FETCH_ASSOC);
      $hasNew1=$hasNew2=$hasNew3=false; foreach($cols as $c){ if($c['name']==='gain_backfield_per_pos') $hasNew1=true; if($c['name']==='gain_midfield_per_pos') $hasNew2=true; if($c['name']==='gain_front_per_pos') $hasNew3=true; }
      if ($hasNew1 || $hasNew2 || $hasNew3) {
        $db->exec("CREATE TABLE __tmp_rules_rev (id INTEGER PRIMARY KEY AUTOINCREMENT, season_id INTEGER NOT NULL, default_budget DECIMAL(6,2) DEFAULT 250.0, last_to_top10_points DECIMAL(4,2), top10_to_top5_points DECIMAL(4,2), top4_points DECIMAL(4,2), position_loss_multiplier DECIMAL(4,2), bonus_cap_value DECIMAL(6,2), malus_cap_value DECIMAL(6,2), race_winner_points DECIMAL(4,2), fastest_lap_points DECIMAL(4,2), drs_multiplier_bonus DECIMAL(4,2), drs_cap_value DECIMAL(6,2), max_drivers_count INTEGER, user_position_points TEXT, driver_finish_points TEXT, FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE, UNIQUE(season_id))");
        $db->exec('INSERT INTO __tmp_rules_rev (id,season_id,default_budget,last_to_top10_points,top10_to_top5_points,top4_points,position_loss_multiplier,bonus_cap_value,malus_cap_value,race_winner_points,fastest_lap_points,drs_multiplier_bonus,drs_cap_value,max_drivers_count,user_position_points,driver_finish_points) SELECT id,season_id,default_budget,gain_backfield_per_pos,gain_midfield_per_pos,gain_front_per_pos,position_loss_multiplier,bonus_cap_value,malus_cap_value,race_winner_points,fastest_lap_points,drs_multiplier_bonus,drs_cap_value,max_drivers_count,user_position_points,driver_finish_points FROM season_rules');
        $db->exec('DROP TABLE season_rules');
        $db->exec('ALTER TABLE __tmp_rules_rev RENAME TO season_rules');
      }
      // (No rollback for removed computed columns or renamed created_at columns)
      $db->commit();
    } catch (Exception $e) {
      $db->rollBack();
      throw $e;
    } finally { $db->exec('PRAGMA foreign_keys = ON'); }
  }
];

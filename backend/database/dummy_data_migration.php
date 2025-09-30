<?php
// Dummy data migration: populate users, a championship, constructors, full driver grid, races (incl. Singapore), race results, and user fantasy lineups.
// Safe to run multiple times (idempotent checks) for local dev/demo.

// Load full config (defines DB_PATH and sets up helpers)
require_once __DIR__ . '/../config/config.php';

// Supported actions: seed (default) or cleanup
$__ff_action = 'seed';
if (php_sapi_name() === 'cli') {
    global $argv; if (!empty($argv[1])) { $__ff_action = strtolower($argv[1]); }
} else {
    if (isset($_GET['action'])) { $__ff_action = strtolower($_GET['action']); }
    elseif (isset($_GET['cleanup'])) { $__ff_action = 'cleanup'; }
}

function ff_seedDummyData(PDO $db, array &$messages) {
    $add = function($cat,$ok,$text) use (&$messages) { if (!isset($messages[$cat])) { $messages[$cat]=[]; } $messages[$cat][] = ['ok'=>$ok,'text'=>$text]; };
    $db->beginTransaction();
    try {
        // Ensure season 2025 exists + season rules (budget now 200)
        $seasonId = $db->query("SELECT id FROM seasons WHERE year = 2025 LIMIT 1")->fetchColumn();
        if (!$seasonId) {
            $db->exec("INSERT INTO seasons (year, status) VALUES (2025, 'active')");
            $seasonId = $db->lastInsertId();
            $db->exec("INSERT INTO season_rules (season_id, default_budget) VALUES ($seasonId, 200)");
            $add('Season', true, 'Created season 2025 (budget 200)');
        } else {
            // Ensure season_rules row exists & update budget to 200
            $row = $db->query("SELECT default_budget FROM season_rules WHERE season_id = $seasonId LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $db->exec("INSERT INTO season_rules (season_id, default_budget) VALUES ($seasonId,200)");
                $add('Season', true, 'Added season_rules (budget 200) for 2025');
            } else if ((int)$row['default_budget'] !== 200) {
                $db->exec("UPDATE season_rules SET default_budget = 200 WHERE season_id = $seasonId");
                $add('Season', true, 'Updated budget to 200');
            } else {
                $add('Season', true, 'Season 2025 present (budget already 200)');
            }
        }

        // Users
        $dummyPasswordHash = password_hash('password123', PASSWORD_BCRYPT);
        $users = [ ['adminuser','adminuser@example.com',1], ['speedy','speedy@example.com',0], ['gripmaster','gripmaster@example.com',0], ['latebraker','latebraker@example.com',0] ];
        $userIds = [];
        $selectUser = $db->prepare('SELECT id, username FROM users WHERE username = ? OR email = ? LIMIT 1');
        $insertUser = $db->prepare('INSERT INTO users (username,email,password_hash,created_at,updated_at,is_super_admin) VALUES (?,?,?,?,?,?)');
        foreach ($users as $u) {
            [$username,$email,$isAdmin] = $u;
            $selectUser->execute([$username,$email]);
            $row = $selectUser->fetch();
            $id = $row['id'] ?? null;
            if (!$id) {
                $insertUser->execute([$username,$email,$dummyPasswordHash,date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$isAdmin]);
                $id = $db->lastInsertId();
                $add('Users', true, "Inserted user $username" . ($isAdmin ? ' (admin)' : ''));
            } else {
                if ($isAdmin) { $db->prepare('UPDATE users SET is_super_admin = 1 WHERE id = ?')->execute([$id]); }
                $add('Users', true, "User $username already present");
            }
            $userIds[$username] = $id;
        }

        // Championship
        $champName = 'Demo Championship';
        $champIdStmt = $db->prepare('SELECT c.id FROM championships c JOIN seasons s ON c.season_id = s.id WHERE c.name = ? AND s.year = 2025');
        $champIdStmt->execute([$champName]);
        $championshipId = $champIdStmt->fetchColumn();
        if (!$championshipId) {
            $stmt = $db->prepare('INSERT INTO championships (name, season_id, status, max_participants, is_public) VALUES (?,?,?,?,1)');
            $stmt->execute([$champName,$seasonId,'active',50]);
            $championshipId = $db->lastInsertId();
            $add('Championship', true, 'Created demo championship');
        } else { $add('Championship', true, 'Demo championship already exists'); }

        // Championship admin + participants
        $adminId = $userIds['adminuser'];
        $chkAdmin = $db->prepare('SELECT 1 FROM championship_admins WHERE championship_id = ? AND user_id = ?');
        $chkAdmin->execute([$championshipId,$adminId]);
        if (!$chkAdmin->fetch()) { $db->prepare('INSERT INTO championship_admins (championship_id,user_id) VALUES (?,?)')->execute([$championshipId,$adminId]); $add('Championship', true, 'Assigned adminuser as championship admin'); }
        $chkPart = $db->prepare('SELECT 1 FROM championship_participants WHERE championship_id = ? AND user_id = ?');
        $insPart = $db->prepare('INSERT INTO championship_participants (championship_id,user_id,joined_at) VALUES (?,?,CURRENT_TIMESTAMP)');
        foreach ($userIds as $uid) { $chkPart->execute([$championshipId,$uid]); if (!$chkPart->fetch()) { $insPart->execute([$championshipId,$uid]); $add('Participants', true, "Added participant (user id $uid)"); } else { $add('Participants', true, "Participant already linked (user id $uid)"); } }

        // Constructors (full 2025 grid)
        $constructors = [
            ['Red Bull Racing','RBR','#0600EF'],
            ['Mercedes','MER','#00D2BE'],
            ['Ferrari','FER','#DC0000'],
            ['McLaren','MCL','#FF8700'],
            ['Aston Martin','AMR','#006F62'],
            ['Alpine','ALP','#2293D1'],
            ['Williams','WIL','#005AFF'],
            ['RB','RB','#6692FF'],
            ['Haas','HAA','#B6BABD'],
            ['Sauber','SAU','#52E252']
        ];
        $constructorIds = [];
        $selCons = $db->prepare('SELECT id FROM constructors WHERE season_id = ? AND name = ?');
        $insCons = $db->prepare('INSERT INTO constructors (season_id,name,short_name,color_primary) VALUES (?,?,?,?)');
        foreach ($constructors as $c) {
            [$name,$short,$color] = $c; $selCons->execute([$seasonId,$name]); $cid = $selCons->fetchColumn();
            if (!$cid) { $insCons->execute([$seasonId,$name,$short,$color]); $cid = $db->lastInsertId(); $add('Constructors', true, "Inserted constructor $short"); }
            else { $add('Constructors', true, "Constructor $short already present"); }
            $constructorIds[$short] = $cid;
        }

        // Drivers (full grid)
        $drivers = [
            ['Max','Verstappen',1,'VER','Dutch'],
            ['Sergio','Perez',11,'PER','Mexican'],
            ['Lewis','Hamilton',44,'HAM','British'],
            ['George','Russell',63,'RUS','British'],
            ['Charles','Leclerc',16,'LEC','Monegasque'],
            ['Carlos','Sainz',55,'SAI','Spanish'],
            ['Lando','Norris',4,'NOR','British'],
            ['Oscar','Piastri',81,'PIA','Australian'],
            ['Fernando','Alonso',14,'ALO','Spanish'],
            ['Lance','Stroll',18,'STR','Canadian'],
            ['Esteban','Ocon',31,'OCO','French'],
            ['Pierre','Gasly',10,'GAS','French'],
            ['Alexander','Albon',23,'ALB','Thai'],
            ['Logan','Sargeant',2,'SAR','American'],
            ['Yuki','Tsunoda',22,'TSU','Japanese'],
            ['Daniel','Ricciardo',3,'RIC','Australian'],
            ['Kevin','Magnussen',20,'MAG','Danish'],
            ['Nico','Hulkenberg',27,'HUL','German'],
            ['Valtteri','Bottas',77,'BOT','Finnish'],
            ['Guanyu','Zhou',24,'ZHO','Chinese']
        ];
        $driverIds = [];
        $selDriver = $db->prepare('SELECT id FROM drivers WHERE driver_code = ?');
        $insDriver = $db->prepare('INSERT INTO drivers (first_name,last_name,driver_number,driver_code,nationality) VALUES (?,?,?,?,?)');
        foreach ($drivers as $d) {
            [$fn,$ln,$num,$code,$nat] = $d; $selDriver->execute([$code]); $did = $selDriver->fetchColumn();
            if (!$did) { $insDriver->execute([$fn,$ln,$num,$code,$nat]); $did = $db->lastInsertId(); $add('Drivers', true, "Inserted driver $code"); }
            else { $add('Drivers', true, "Driver $code already present"); }
            $driverIds[$code] = $did;
        }

        // Add races (Dutch, Italian, Singapore) later in season + keep existing early sample races if present
        $raceDefs = [
            ['Dutch Grand Prix','Circuit Zandvoort','Netherlands','2025-08-24 14:00:00','2025-08-23 14:00:00',15],
            ['Italian Grand Prix','Autodromo Nazionale Monza','Italy','2025-09-07 14:00:00','2025-09-06 14:00:00',16],
            ['Singapore Grand Prix','Marina Bay Street Circuit','Singapore','2025-09-21 12:00:00','2025-09-20 12:00:00',17]
        ];
        $raceIds = [];
        $selRace = $db->prepare('SELECT id FROM races WHERE season_id = ? AND name = ?');
        $insRace = $db->prepare('INSERT INTO races (season_id,name,track_name,country,race_date,qualifying_date,round_number) VALUES (?,?,?,?,?,?,?)');
        foreach ($raceDefs as $r) {
            [$name,$track,$country,$raceDate,$qualDate,$round] = $r; $selRace->execute([$seasonId,$name]); $rid = $selRace->fetchColumn();
            if (!$rid) { $insRace->execute([$seasonId,$name,$track,$country,$raceDate,$qualDate,$round]); $rid = $db->lastInsertId(); $add('Races', true, "Inserted race $name"); } else { $add('Races', true, "$name already present"); }
            $raceIds[$name] = $rid;
        }

        // Singapore pricing (chance-adjusted 35-50 range; highest = 50)
        $singaporePricing = [
            'VER'=>50,'NOR'=>47,'LEC'=>46,'SAI'=>44,'HAM'=>43,'PER'=>42,'RUS'=>41,'PIA'=>39,'ALO'=>38,'GAS'=>36,
            'OCO'=>35,'ALB'=>32,'TSU'=>31,'RIC'=>30,'STR'=>29,'HUL'=>28,'MAG'=>26,'BOT'=>25,'ZHO'=>24,'SAR'=>22
        ];
        // Map driver to constructor short name
        $driverConstructorMap = [
            'VER'=>'RBR','PER'=>'RBR','HAM'=>'MER','RUS'=>'MER','LEC'=>'FER','SAI'=>'FER','NOR'=>'MCL','PIA'=>'MCL',
            'ALO'=>'AMR','STR'=>'AMR','OCO'=>'ALP','GAS'=>'ALP','ALB'=>'WIL','SAR'=>'WIL','TSU'=>'RB','RIC'=>'RB',
            'MAG'=>'HAA','HUL'=>'HAA','BOT'=>'SAU','ZHO'=>'SAU'
        ];

        // Link drivers to each seeded race (idempotent). For non-Singapore races we slightly reduce top prices (-2) to vary.
        $selRD = $db->prepare('SELECT 1 FROM race_drivers WHERE race_id = ? AND driver_id = ?');
        $insRD = $db->prepare('INSERT INTO race_drivers (race_id,driver_id,constructor_id,price,ai_calculated_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)');
        foreach ($raceIds as $raceName => $rid) {
            foreach ($singaporePricing as $code=>$price) {
                $driverId = $driverIds[$code] ?? null; if (!$driverId) continue; $constructorShort = $driverConstructorMap[$code]; $consId = $constructorIds[$constructorShort] ?? null; if (!$consId) continue;
                $racePrice = $price;
                if (strpos($raceName,'Singapore') === false) { $racePrice = max(15, $price - 2); }
                $selRD->execute([$rid,$driverId]);
                if (!$selRD->fetch()) { $insRD->execute([$rid,$driverId,$consId,$racePrice]); $add('RaceDrivers', true, "Linked $code to $raceName"); }
                else { $add('RaceDrivers', true, "$code already linked to $raceName"); }
            }
        }

        // Insert simple race results for each new race if absent (top 10 finishers). Points here are illustrative only.
        $baseResultsOrder = [ ['VER',1,1,1,0,25.0], ['NOR',2,2,0,0,18.0], ['LEC',3,3,0,0,15.0], ['HAM',4,4,0,0,12.0], ['SAI',5,5,0,0,10.0], ['PER',6,6,0,0,8.0], ['RUS',7,7,0,0,6.0], ['PIA',8,8,0,0,4.0], ['ALO',9,9,0,0,2.0], ['GAS',10,10,0,0,1.0] ];
        $selRes = $db->prepare('SELECT 1 FROM race_results WHERE race_id = ? AND driver_id = ?');
        $insRes = $db->prepare('INSERT INTO race_results (race_id,driver_id,qualifying_position,race_position,fastest_lap,dnf,points_earned,calculated_at) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        foreach ($raceIds as $raceName=>$rid) {
            foreach ($baseResultsOrder as $r) {
                [$code,$q,$pos,$fast,$dnf,$pts] = $r; $did = $driverIds[$code] ?? null; if (!$did) continue; $selRes->execute([$rid,$did]);
                if (!$selRes->fetch()) { $insRes->execute([$rid,$did,$q,$pos,$fast,$dnf,$pts]); $add('RaceResults', true, "Inserted result $code for $raceName"); }
                else { $add('RaceResults', true, "Result $code already exists for $raceName"); }
            }
        }

        // Create user lineups for each seeded race (top 3 priced drivers of that race)
        $selLineup = $db->prepare('SELECT id FROM user_race_lineups WHERE user_id = ? AND race_id = ? AND championship_id = ?');
        $insLineup = $db->prepare('INSERT INTO user_race_lineups (user_id,race_id,championship_id,drs_enabled,total_cost,total_points,submitted_at) VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $insSel = $db->prepare('INSERT OR IGNORE INTO user_selected_drivers (user_race_lineup_id,race_driver_id) VALUES (?,?)');
        $topDriversStmt = $db->prepare('SELECT rd.id, rd.price FROM race_drivers rd WHERE rd.race_id = ? ORDER BY rd.price DESC LIMIT 3');
        foreach ($raceIds as $raceName=>$rid) {
            foreach ($userIds as $uname=>$uid) {
                $selLineup->execute([$uid,$rid,$championshipId]); $lid = $selLineup->fetchColumn();
                if (!$lid) {
                    $topDriversStmt->execute([$rid]); $rows = $topDriversStmt->fetchAll(PDO::FETCH_ASSOC);
                    $totalCost = array_sum(array_column($rows,'price'));
                    $totalPoints = rand(10,60); // demo points prior to scoring recalculation
                    $insLineup->execute([$uid,$rid,$championshipId,1,$totalCost,$totalPoints]);
                    $lid = $db->lastInsertId();
                    foreach ($rows as $row) { $insSel->execute([$lid,$row['id']]); }
                    $add('Lineups', true, "Created lineup for user $uid ($raceName)");
                } else { $add('Lineups', true, "Lineup already exists for user $uid ($raceName)"); }
            }
        }

        $db->commit();
        $add('Summary', true, 'Dummy data seeding completed (budget 200, Singapore pricing applied)');
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Dummy data seeding failed: '.$e->getMessage());
        $add('Summary', false, 'Dummy data seeding failed: '.$e->getMessage());
    }
}

function ff_cleanupDummyData(PDO $db, array &$messages) {
    $add = function($cat,$ok,$text) use (&$messages) { if (!isset($messages[$cat])) { $messages[$cat]=[]; } $messages[$cat][] = ['ok'=>$ok,'text'=>$text]; };
    $db->beginTransaction();
    try {
        $championshipId = $db->query("SELECT c.id FROM championships c JOIN seasons s ON c.season_id = s.id WHERE c.name='Demo Championship' AND s.year=2025 LIMIT 1")->fetchColumn();
        if ($championshipId) { $db->prepare('DELETE FROM championships WHERE id = ?')->execute([$championshipId]); $add('Championship', true, 'Deleted Demo Championship (cascade)'); }
        else { $add('Championship', true, 'Demo Championship not found'); }
        $delRaces = $db->exec("DELETE FROM races WHERE season_id IN (SELECT id FROM seasons WHERE year=2025) AND name LIKE '% Grand Prix'");
        if ($delRaces) { $add('Races', true, "Deleted $delRaces demo race(s)"); } else { $add('Races', true, 'No demo races to delete'); }
        $usernames = ['adminuser','speedy','gripmaster','latebraker'];
        $placeholders = implode(',', array_fill(0,count($usernames),'?'));
        $selUsers = $db->prepare("SELECT id, username FROM users WHERE username IN ($placeholders)");
        $selUsers->execute($usernames);
        $found = $selUsers->fetchAll(PDO::FETCH_ASSOC);
        if ($found) { $db->prepare("DELETE FROM users WHERE username IN ($placeholders)")->execute($usernames); $add('Users', true, 'Deleted demo users: '.implode(', ', array_column($found,'username'))); }
        else { $add('Users', true, 'No demo users to delete'); }
        $db->commit();
        $add('Summary', true, 'Cleanup completed');
    } catch (Exception $e) {
        $db->rollBack();
        $add('Summary', false, 'Cleanup failed: '.$e->getMessage());
        error_log('Dummy data cleanup failed: '.$e->getMessage());
    }
}

try {
    $messages = [];
    $pdo = getDB();
    if ($__ff_action === 'cleanup') {
        ff_cleanupDummyData($pdo, $messages);
    } else {
        ff_seedDummyData($pdo, $messages);
        if (!isset($messages['Summary'])) { $messages['Summary']=[]; }
        $messages['Summary'][] = ['ok'=>true,'text'=>'Admin login -> username: adminuser password: password123 (email: adminuser@example.com)'];
    }
    // Direct access feedback page
    if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
        header('Content-Type: text/html; charset=utf-8');
        $ok = true; foreach ($messages as $cat=>$list) { foreach ($list as $entry) { if (!$entry['ok']) { $ok=false; break 2; } } }
        echo '<!DOCTYPE html><html><head><title>Dummy Data Migration - '.htmlspecialchars(strtoupper($__ff_action)).'</title><style>body{background:#000;color:#eee;font-family:Arial;padding:40px;} .ok{color:#4ade80;} .fail{color:#f87171;} h2{margin-top:32px;} ul{margin:8px 0 0 0;padding:0;list-style:none;} li{line-height:1.4;padding:2px 0;} code{background:#111;padding:2px 4px;border-radius:3px;} a{color:#e62d2d;} .actions{margin-top:32px;} .actions a{margin-right:16px;} .cat{border-left:4px solid #222;padding-left:12px;margin-top:28px;} </style></head><body>';
        echo '<h1>Dummy Data '.($__ff_action==='cleanup' ? 'Cleanup' : 'Seed').' '.($ok ? '<span class="ok">OK</span>' : '<span class="fail">ISSUES</span>').'</h1>';
        $order = ['Season','Users','Championship','Participants','Constructors','Drivers','Races','RaceDrivers','RaceResults','Lineups','Summary'];
        $check='✅'; $cross='❌';
        foreach ($order as $cat) { if (!isset($messages[$cat])) continue; echo '<div class="cat"><h2>'.htmlspecialchars($cat).'</h2><ul>'; foreach ($messages[$cat] as $entry) { echo '<li>'.($entry['ok']?$check:$cross).' '.htmlspecialchars($entry['text']).'</li>'; } echo '</ul></div>'; }
        $base = basename(__FILE__);
        echo '<div class="actions">'
            .'<a href="'.$base.'?action=seed">Run Seed</a>'
            .'<a href="'.$base.'?action=cleanup">Run Cleanup</a>'
            .'</div><p><em>Development helper. Budget set to 200. Singapore pricing reflects 2025 probability-based ordering.</em></p></body></html>';
        exit;
    } else {
        foreach ($messages as $cat=>$list) { foreach ($list as $entry) { error_log("[$cat] ".($entry['ok']?'OK':'FAIL').' - '.$entry['text']); } }
    }
} catch (Exception $e) { error_log('Dummy data migration error: '.$e->getMessage()); }
?>

<?php
// Fantasy Formula 1 - Championship Stats / Standings API
// GET /championships/stats.php?championship_id=ID[&user_id=ID]
// Returns standings computed dynamically (aggregate per-race lineup points derived on demand) and optional user summary
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

$method = getRequestMethod();
if ($method !== 'GET') {
    sendMethodNotAllowed(['GET']);
}

try {
    $championshipId = getQueryParam('championship_id');
    if (!$championshipId || !is_numeric($championshipId)) {
        sendError('championship_id is required', 400);
    }
    $championshipId = (int)$championshipId;
    $userIdFilter = getQueryParam('user_id');
    $userIdFilter = $userIdFilter && is_numeric($userIdFilter) ? (int)$userIdFilter : null;

    $db = getDB();

    // Ensure championship exists
    $stmt = $db->prepare('SELECT id, name FROM championships WHERE id = ?');
    $stmt->execute([$championshipId]);
    if (!$stmt->fetch()) {
        sendNotFound('Championship not found');
    }

    // Load ranking points array from season rules
    $pointsRow = $db->prepare('SELECT sr.user_position_points FROM championships c JOIN seasons s ON c.season_id = s.id JOIN season_rules sr ON sr.season_id = s.id WHERE c.id = ? LIMIT 1');
    $pointsRow->execute([$championshipId]);
    $rankingConfig = $pointsRow->fetchColumn();
    $rankingArray = $rankingConfig ? json_decode($rankingConfig, true) : [25,18,14,10,6,3,1];
    if (!is_array($rankingArray) || empty($rankingArray)) { $rankingArray = [25,18,14,10,6,3,1]; }

    // Gather per-race raw totals per user to derive race rankings
    $raceStmt = $db->prepare('SELECT DISTINCT race_id FROM user_race_lineups WHERE championship_id = ?');
    $raceStmt->execute([$championshipId]);
    $raceIds = $raceStmt->fetchAll(PDO::FETCH_COLUMN);

    // Initialize cumulative championship points structure
    $userChampPoints = []; // user_id => championship points (from ranking scheme)
    $userRawSum = [];      // user_id => sum of raw lineup points (for tie-break display)

    if ($raceIds) {
        foreach ($raceIds as $rid) {
            $rules = ff_loadSeasonRulesForRace($db, $rid) ?: [];
            $driverPts = ff_computeDriverPoints($db, $rid, $rules);
            $lineups = $db->prepare('SELECT id, user_id, submitted_at FROM user_race_lineups WHERE championship_id = ? AND race_id = ?');
            $lineups->execute([$championshipId, $rid]);
            $calc = [];
            foreach ($lineups->fetchAll(PDO::FETCH_ASSOC) as $lu) {
                $pts = ff_computeLineupPoints($db, (int)$lu['id'], $driverPts);
                $uid = (int)$lu['user_id'];
                $calc[] = ['user_id'=>$uid,'points'=>$pts,'submitted_at'=>$lu['submitted_at']];
                $userRawSum[$uid] = ($userRawSum[$uid] ?? 0) + $pts;
            }
            usort($calc, function($a,$b){ if ($b['points']==$a['points']) return strcmp($a['submitted_at'],$b['submitted_at']); return $b['points'] <=> $a['points']; });
            $last=null; $rank=0; $idx=0; 
            foreach ($calc as $row) { 
                $idx++; $val=$row['points']; 
                if ($last===null || $val < $last){ $rank=$idx; $last=$val; } 
                if ($rank <= count($rankingArray)) { 
                    $userId = (int)$row['user_id']; // Ensure integer key
                    $userChampPoints[$userId] = ($userChampPoints[$userId] ?? 0) + $rankingArray[$rank-1]; 
                } 
            }
        }
    }

    // Ensure all participants appear even if no races
    $partStmt = $db->prepare('SELECT DISTINCT user_id FROM championship_participants WHERE championship_id = ?');
    $partStmt->execute([$championshipId]);
    foreach ($partStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        $uid = (int)$uid; 
        if (!array_key_exists($uid, $userChampPoints)) { $userChampPoints[$uid] = 0; }
        if (!array_key_exists($uid, $userRawSum)) { $userRawSum[$uid] = 0; }
    }

    // Normalize all keys to integers to prevent string/int key duplicates
    $normalizedUserChampPoints = [];
    $normalizedUserRawSum = [];
    foreach ($userChampPoints as $uid => $points) {
        $normalizedUid = (int)$uid;
        $normalizedUserChampPoints[$normalizedUid] = ($normalizedUserChampPoints[$normalizedUid] ?? 0) + $points;
    }
    foreach ($userRawSum as $uid => $points) {
        $normalizedUid = (int)$uid;
        $normalizedUserRawSum[$normalizedUid] = ($normalizedUserRawSum[$normalizedUid] ?? 0) + $points;
    }
    $userChampPoints = $normalizedUserChampPoints;
    $userRawSum = $normalizedUserRawSum;
    
    // Build final standings ordered by championship points then raw sum as tie-breaker
    $standings = [];
    
    // Get all unique user IDs and usernames in one query for efficiency
    $userIds = array_unique(array_keys($userChampPoints)); // Ensure unique user IDs
    if (!empty($userIds)) {
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $userNamesStmt = $db->prepare("SELECT id, username FROM users WHERE id IN ($placeholders)");
        $userNamesStmt->execute($userIds);
        $userNames = [];
        foreach ($userNamesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $userNames[(int)$row['id']] = $row['username'];
        }
        
        // Build standings ensuring no duplicates by using user_id as array key
        $standingsMap = [];
        foreach ($userChampPoints as $uid => $cpts) {
            $uid = (int)$uid;
            $standingsMap[$uid] = [
                'user_id' => $uid,
                'username' => $userNames[$uid] ?? ('user#'.$uid),
                'champ_points' => (float)$cpts,
                'raw_points' => (float)($userRawSum[$uid] ?? 0)
            ];
        }
        $standings = array_values($standingsMap); // Convert back to indexed array
    }
    usort($standings, function($a,$b){
        if ($b['champ_points'] == $a['champ_points']) {
            if ($b['raw_points'] == $a['raw_points']) return $a['user_id'] <=> $b['user_id'];
            return $b['raw_points'] <=> $a['raw_points'];
        }
        return $b['champ_points'] <=> $a['champ_points'];
    });
    $lastChamp = null; $rank = 0; $idx = 0;
    for ($i = 0; $i < count($standings); $i++) {
        $idx++; $cp = $standings[$i]['champ_points'];
        if ($lastChamp === null || $cp < $lastChamp) { $rank = $idx; $lastChamp = $cp; }
        $standings[$i]['position'] = $rank;
    }

    $userSummary = null;
    if ($userIdFilter) {
        foreach ($standings as $s) {
            if ($s['user_id'] == $userIdFilter) {  // Use == instead of === for type-flexible comparison
                $userSummary = [
                    'user_id' => $s['user_id'],
                    'position' => $s['position'],
                    'champ_points' => $s['champ_points'],
                    'raw_points' => $s['raw_points']
                ];
                break; 
            }
        }
        if (!$userSummary) {
            // user participates? if not found treat as zero points at bottom
            $stmt = $db->prepare('SELECT 1 FROM championship_participants WHERE championship_id = ? AND user_id = ?');
            $stmt->execute([$championshipId, $userIdFilter]);
            if ($stmt->fetch()) {
                $userSummary = [
                    'user_id' => $userIdFilter,
                    'position' => count($standings) + 1,
                    'champ_points' => 0.0,
                    'raw_points' => 0.0
                ];
            }
        }
    }

    sendSuccess([
        'championship_id' => $championshipId,
        'standings' => $standings,
        'user' => $userSummary
    ], 'Championship standings retrieved');

} catch (PDOException $e) {
    error_log('Championship stats error: ' . $e->getMessage());
    sendServerError('Failed to retrieve standings');
} catch (Exception $e) {
    error_log('Championship stats error: ' . $e->getMessage());
    sendServerError('Failed to retrieve standings');
}

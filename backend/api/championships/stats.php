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
            $last=null; $rank=0; $idx=0; foreach ($calc as $row) { $idx++; $val=$row['points']; if ($last===null || $val < $last){ $rank=$idx; $last=$val; } if ($rank <= count($rankingArray)) { $userChampPoints[$row['user_id']] = ($userChampPoints[$row['user_id']] ?? 0) + $rankingArray[$rank-1]; } }
        }
    }

    // Ensure all participants appear even if no races
    $partStmt = $db->prepare('SELECT user_id FROM championship_participants WHERE championship_id = ?');
    $partStmt->execute([$championshipId]);
    foreach ($partStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        $uid = (int)$uid; if (!isset($userChampPoints[$uid])) { $userChampPoints[$uid] = 0; }
        if (!isset($userRawSum[$uid])) { $userRawSum[$uid] = 0; }
    }

    // Build final standings ordered by championship points then raw sum as tie-breaker
    $standings = [];
    foreach ($userChampPoints as $uid=>$cpts) {
        $uname = $db->prepare('SELECT username FROM users WHERE id = ?');
        $uname->execute([$uid]);
        $standings[] = [
            'user_id' => $uid,
            'username' => $uname->fetchColumn() ?: ('user#'.$uid),
            'champ_points' => (float)$cpts,
            'raw_points' => (float)$userRawSum[$uid]
        ];
    }
    usort($standings, function($a,$b){
        if ($b['champ_points'] == $a['champ_points']) {
            if ($b['raw_points'] == $a['raw_points']) return $a['user_id'] <=> $b['user_id'];
            return $b['raw_points'] <=> $a['raw_points'];
        }
        return $b['champ_points'] <=> $a['champ_points'];
    });
    $lastChamp = null; $rank = 0; $idx = 0;
    foreach ($standings as &$s) {
        $idx++; $cp = $s['champ_points'];
        if ($lastChamp === null || $cp < $lastChamp) { $rank = $idx; $lastChamp = $cp; }
        $s['position'] = $rank;
    }

    $userSummary = null;
    if ($userIdFilter) {
        foreach ($standings as $s) {
            if ($s['user_id'] === $userIdFilter) {
                $userSummary = [
                    'user_id' => $s['user_id'],
                    'position' => $s['position'],
                    'champ_points' => $s['champ_points'],
                    'raw_points' => $s['raw_points']
                ];
                break; }
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

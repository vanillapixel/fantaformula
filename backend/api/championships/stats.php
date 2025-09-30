<?php
// Fantasy Formula 1 - Championship Stats / Standings API
// GET /championships/stats.php?championship_id=ID[&user_id=ID]
// Returns standings (points aggregated from user_race_lineups.total_points) and optional user summary
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

    // Aggregate points per user (SUM of user_race_lineups.total_points)
    $sql = "
        SELECT u.id as user_id, u.username,
               COALESCE(SUM(url.total_points), 0) AS total_points
        FROM championship_participants cp
        JOIN users u ON cp.user_id = u.id
        LEFT JOIN user_race_lineups url
            ON url.championship_id = cp.championship_id
           AND url.user_id = cp.user_id
        WHERE cp.championship_id = ?
        GROUP BY u.id, u.username
        ORDER BY total_points DESC, u.username ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$championshipId]);
    $rows = $stmt->fetchAll();

    // Compute ranking (dense)
    $standings = [];
    $lastPoints = null; $rank = 0; $index = 0;
    foreach ($rows as $row) {
        $index++;
        $pts = (float)$row['total_points'];
        if ($lastPoints === null || $pts < $lastPoints) {
            $rank = $index; // dense ranking
            $lastPoints = $pts;
        }
        $standings[] = [
            'position' => $rank,
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'points' => $pts,
        ];
    }

    $userSummary = null;
    if ($userIdFilter) {
        foreach ($standings as $s) {
            if ($s['user_id'] === $userIdFilter) {
                $userSummary = [
                    'user_id' => $s['user_id'],
                    'position' => $s['position'],
                    'points' => $s['points']
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
                    'points' => 0.0
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

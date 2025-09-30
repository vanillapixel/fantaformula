<?php
// Fantasy Formula 1 - Championships API
// GET /championships - List all championships (with filters)
// POST /championships - Create new championship
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

$method = getRequestMethod();

try {
    $db = getDB();
    
    switch ($method) {
    case 'GET':
            // Get query parameters
            $status = getQueryParam('status'); // upcoming, active, completed
            $public = getQueryParam('public'); // true/false
            $season = getQueryParam('season'); // season year
            $user_id = getQueryParam('user_id'); // championships user participates in
            [$page, $pageSize] = getPaginationParams();
            
            // Build query
            $whereConditions = [];
            $params = [];
            
            if ($status) {
                $whereConditions[] = "c.status = ?";
                $params[] = $status;
            }
            
            if ($public !== null) {
                $whereConditions[] = "c.is_public = ?";
                $params[] = $public === 'true' ? 1 : 0;
            }
            
            if ($season) {
                $whereConditions[] = "s.year = ?";
                $params[] = $season;
            }
            
            if ($user_id) {
                $whereConditions[] = "cp.user_id = ?";
                $params[] = $user_id;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Count total items
            $countSql = "
                SELECT COUNT(DISTINCT c.id) as total
                FROM championships c
                JOIN seasons s ON c.season_id = s.id
                " . ($user_id ? "JOIN championship_participants cp ON c.id = cp.championship_id" : "") . "
                $whereClause
            ";
            
            $stmt = $db->prepare($countSql);
            $stmt->execute($params);
            $totalItems = $stmt->fetch()['total'];
            
            // Get championships with details
            $offset = ($page - 1) * $pageSize;
            $sql = "
                SELECT DISTINCT
                    c.id,
                    c.name,
                    c.status,
                    c.max_participants,
                    c.is_public,
                    c.created_at,
                    s.year as season_year,
                    s.status as season_status,
                    COUNT(cp.user_id) as participant_count,
                    GROUP_CONCAT(DISTINCT u.username) as admin_usernames
                FROM championships c
                JOIN seasons s ON c.season_id = s.id
                LEFT JOIN championship_participants cp ON c.id = cp.championship_id
                LEFT JOIN championship_admins ca ON c.id = ca.championship_id
                LEFT JOIN users u ON ca.user_id = u.id
                " . ($user_id ? "JOIN championship_participants cpu ON c.id = cpu.championship_id" : "") . "
                $whereClause
                GROUP BY c.id, c.name, c.status, c.max_participants, c.is_public, c.created_at, s.year, s.status
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $pageSize;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $championships = $stmt->fetchAll();
            
            // Format response + attach per-user standings (points & position) if user_id filter was provided
            $userIdInt = $user_id ? (int)$user_id : null;
            $standStmt = null;
            if ($userIdInt) {
                $standStmt = $db->prepare(
                    'SELECT cp.user_id, COALESCE(SUM(url.total_points),0) AS total_points ' .
                    'FROM championship_participants cp ' .
                    'LEFT JOIN user_race_lineups url ON url.championship_id = cp.championship_id AND url.user_id = cp.user_id ' .
                    'WHERE cp.championship_id = ? ' .
                    'GROUP BY cp.user_id ' .
                    'ORDER BY total_points DESC, cp.user_id ASC'
                );
            }

            foreach ($championships as &$championship) {
                $championship['id'] = (int)$championship['id'];
                $championship['participant_count'] = (int)$championship['participant_count'];
                $championship['max_participants'] = (int)$championship['max_participants'];
                $championship['is_public'] = (bool)$championship['is_public'];
                $championship['season_year'] = (int)$championship['season_year'];
                $championship['admin_usernames'] = $championship['admin_usernames'] ? explode(',', $championship['admin_usernames']) : [];

                if ($userIdInt) {
                    $championship['user_points'] = 0.0;
                    $championship['user_position'] = null; // null -> frontend can render '-'
                    $standStmt->execute([$championship['id']]);
                    $rowsPts = $standStmt->fetchAll(PDO::FETCH_ASSOC);
                    $rank = 0; $lastPts = null; $index = 0; $found = false;
                    foreach ($rowsPts as $r) {
                        $index++; $pts = (float)$r['total_points'];
                        if ($lastPts === null || $pts < $lastPts) { $rank = $index; $lastPts = $pts; }
                        if ((int)$r['user_id'] === $userIdInt) {
                            $championship['user_points'] = $pts;
                            $championship['user_position'] = $rank;
                            $found = true; break; // we can break once found
                        }
                    }
                    if (!$found) {
                        // user not yet with any lineup -> points 0 position participant_count (at bottom)
                        if ($championship['participant_count'] > 0) {
                            $championship['user_points'] = 0.0;
                            $championship['user_position'] = $championship['participant_count'];
                        }
                    }
                }
            }
            
            sendPaginatedResponse($championships, $page, $pageSize, $totalItems);
            break;
            
        case 'POST':
            // Create new championship (requires authentication)
            requireAuth();
            $userId = getCurrentUserId();
            
            $input = getJSONInput();
            $errors = [];
            
            // Validation
            if (empty($input['name'])) {
                $errors['name'] = 'Championship name is required';
            } elseif (strlen($input['name']) > 100) {
                $errors['name'] = 'Championship name must be less than 100 characters';
            }
            
            if (empty($input['season_year'])) {
                $errors['season_year'] = 'Season year is required';
            } elseif (!is_numeric($input['season_year']) || $input['season_year'] < 2020 || $input['season_year'] > 2030) {
                $errors['season_year'] = 'Season year must be between 2020 and 2030';
            }
            
            if (isset($input['max_participants']) && (!is_numeric($input['max_participants']) || $input['max_participants'] < 2 || $input['max_participants'] > 100)) {
                $errors['max_participants'] = 'Max participants must be between 2 and 100';
            }
            
            if (!empty($errors)) {
                sendValidationError($errors);
            }
            
            // Check if season exists
            $stmt = $db->prepare("SELECT id FROM seasons WHERE year = ?");
            $stmt->execute([$input['season_year']]);
            $season = $stmt->fetch();
            
            if (!$season) {
                sendError('Season not found', 404);
            }
            
            $seasonId = $season['id'];
            
            // Check for duplicate championship name in same season
            $stmt = $db->prepare("SELECT id FROM championships WHERE name = ? AND season_id = ?");
            $stmt->execute([$input['name'], $seasonId]);
            
            if ($stmt->fetch()) {
                sendError('Championship name already exists in this season', 409);
            }
            
            // Create championship
            $isPublic = isset($input['is_public']) ? (bool)$input['is_public'] : true;
            $maxParticipants = isset($input['max_participants']) ? (int)$input['max_participants'] : null;
            $settings = isset($input['settings']) ? json_encode($input['settings']) : null;
            
            $stmt = $db->prepare("
                INSERT INTO championships (name, season_id, settings, max_participants, is_public, created_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            $stmt->execute([$input['name'], $seasonId, $settings, $maxParticipants, $isPublic ? 1 : 0]);
            $championshipId = $db->lastInsertId();
            
            // Add creator as admin
            $stmt = $db->prepare("
                INSERT INTO championship_admins (championship_id, user_id, created_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$championshipId, $userId]);
            
            // Add creator as participant
            $stmt = $db->prepare("
                INSERT INTO championship_participants (championship_id, user_id, joined_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$championshipId, $userId]);
            
            // Get created championship with details
            $stmt = $db->prepare("
                SELECT 
                    c.id,
                    c.name,
                    c.status,
                    c.max_participants,
                    c.is_public,
                    c.settings,
                    c.created_at,
                    s.year as season_year,
                    s.status as season_status
                FROM championships c
                JOIN seasons s ON c.season_id = s.id
                WHERE c.id = ?
            ");
            $stmt->execute([$championshipId]);
            $championship = $stmt->fetch();
            
            $championship['id'] = (int)$championship['id'];
            $championship['max_participants'] = (int)$championship['max_participants'];
            $championship['is_public'] = (bool)$championship['is_public'];
            $championship['season_year'] = (int)$championship['season_year'];
            $championship['settings'] = $championship['settings'] ? json_decode($championship['settings'], true) : null;
            
            sendSuccess($championship, 'Championship created successfully', 201);
            break;
            
        default:
            sendMethodNotAllowed(['GET', 'POST']);
    }
    
} catch (PDOException $e) {
    error_log("Championships API error: " . $e->getMessage());
    sendServerError('Championships operation failed');
} catch (Exception $e) {
    error_log("Championships API error: " . $e->getMessage());
    sendServerError('Championships operation failed');
}
?>

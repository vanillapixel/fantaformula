<?php
// Fantasy Formula 1 - Seasons API
// GET /seasons - List all seasons
// POST /seasons - Create new season with rules
// GET /seasons/{year} - Get season details with rules
// PUT /seasons/{year} - Update season (admin)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

$method = getRequestMethod();

// Get season year from URL if provided
$seasonYear = null;
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (preg_match('/^\/(\d{4})$/', $pathInfo, $matches)) {
    $seasonYear = (int)$matches[1];
}

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            if ($seasonYear) {
                // Get specific season with details
                $stmt = $db->prepare("
                    SELECT s.*, sr.*
                    FROM seasons s
                    LEFT JOIN season_rules sr ON s.id = sr.season_id
                    WHERE s.year = ?
                ");
                $stmt->execute([$seasonYear]);
                $season = $stmt->fetch();
                
                if (!$season) {
                    sendNotFound('Season not found');
                }
                
                // Get race count and championship count for this season
                $stmt = $db->prepare("SELECT COUNT(*) as race_count FROM races WHERE season_id = ?");
                $stmt->execute([$season['id']]);
                $raceCount = $stmt->fetch()['race_count'];
                
                $stmt = $db->prepare("SELECT COUNT(*) as championship_count FROM championships WHERE season_id = ?");
                $stmt->execute([$season['id']]);
                $championshipCount = $stmt->fetch()['championship_count'];
                
                // Format response
                $response = [
                    'id' => (int)$season['id'],
                    'year' => (int)$season['year'],
                    'status' => $season['status'],
                    'created_at' => $season['created_at'],
                    'race_count' => (int)$raceCount,
                    'championship_count' => (int)$championshipCount,
                    'rules' => null
                ];
                
                // Add rules if they exist
                if ($season['season_id']) {
                    $response['rules'] = [
                        'default_budget' => (float)$season['default_budget'],
                        'last_to_top10_points' => (float)$season['last_to_top10_points'],
                        'top10_to_top5_points' => (float)$season['top10_to_top5_points'],
                        'top4_points' => (float)$season['top4_points'],
                        'position_loss_multiplier' => (float)$season['position_loss_multiplier'],
                        'bonus_cap_value' => (float)$season['bonus_cap_value'],
                        'malus_cap_value' => (float)$season['malus_cap_value'],
                        'race_winner_points' => (float)$season['race_winner_points'],
                        'fastest_lap_points' => (float)$season['fastest_lap_points'],
                        'drs_multiplier_bonus' => (float)$season['drs_multiplier_bonus'],
                        'drs_cap_value' => (float)$season['drs_cap_value'],
                        'max_drivers_count' => (int)$season['max_drivers_count'],
                        'user_position_points' => json_decode($season['user_position_points'], true)
                    ];
                }
                
                sendSuccess($response, 'Season details retrieved');
                
            } else {
                // List all seasons
                $status = getQueryParam('status'); // upcoming, active, completed
                [$page, $pageSize] = getPaginationParams();
                
                $whereConditions = [];
                $params = [];
                
                if ($status) {
                    $whereConditions[] = "s.status = ?";
                    $params[] = $status;
                }
                
                $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
                
                // Count total seasons
                $countSql = "SELECT COUNT(*) as total FROM seasons s $whereClause";
                $stmt = $db->prepare($countSql);
                $stmt->execute($params);
                $totalItems = $stmt->fetch()['total'];
                
                // Get seasons with stats
                $offset = ($page - 1) * $pageSize;
                $sql = "
                    SELECT 
                        s.*,
                        COUNT(DISTINCT r.id) as race_count,
                        COUNT(DISTINCT c.id) as championship_count,
                        sr.default_budget
                    FROM seasons s
                    LEFT JOIN races r ON s.id = r.season_id
                    LEFT JOIN championships c ON s.id = c.season_id
                    LEFT JOIN season_rules sr ON s.id = sr.season_id
                    $whereClause
                    GROUP BY s.id, s.year, s.status, s.created_at, sr.default_budget
                    ORDER BY s.year DESC
                    LIMIT ? OFFSET ?
                ";
                
                $params[] = $pageSize;
                $params[] = $offset;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $seasons = $stmt->fetchAll();
                
                // Format response
                foreach ($seasons as &$season) {
                    $season['id'] = (int)$season['id'];
                    $season['year'] = (int)$season['year'];
                    $season['race_count'] = (int)$season['race_count'];
                    $season['championship_count'] = (int)$season['championship_count'];
                    $season['default_budget'] = $season['default_budget'] ? (float)$season['default_budget'] : null;
                }
                
                sendPaginatedResponse($seasons, $page, $pageSize, $totalItems);
            }
            break;
            
        case 'POST':
            // Create new season
            $input = getJSONInput();
            $errors = [];
            
            // Validation
            if (empty($input['year'])) {
                $errors['year'] = 'Year is required';
            } elseif (!is_numeric($input['year']) || $input['year'] < 2020 || $input['year'] > 2030) {
                $errors['year'] = 'Year must be between 2020 and 2030';
            } else {
                // Check for duplicate year
                $stmt = $db->prepare("SELECT id FROM seasons WHERE year = ?");
                $stmt->execute([$input['year']]);
                if ($stmt->fetch()) {
                    $errors['year'] = 'Season already exists for this year';
                }
            }
            
            if (!empty($errors)) {
                sendValidationError($errors);
            }
            
            $db->beginTransaction();
            
            try {
                // Create season
                $status = $input['status'] ?? 'upcoming';
                
                $stmt = $db->prepare("
                    INSERT INTO seasons (year, status, created_at)
                    VALUES (?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$input['year'], $status]);
                
                $seasonId = $db->lastInsertId();
                
                // Create default season rules
                $rules = $input['rules'] ?? [];
                
                $stmt = $db->prepare("
                    INSERT INTO season_rules (
                        season_id, default_budget, last_to_top10_points, top10_to_top5_points, top4_points,
                        position_loss_multiplier, bonus_cap_value, malus_cap_value, race_winner_points,
                        fastest_lap_points, drs_multiplier_bonus, drs_cap_value, max_drivers_count, user_position_points
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $seasonId,
                    $rules['default_budget'] ?? 250.0,
                    $rules['last_to_top10_points'] ?? 1.0,
                    $rules['top10_to_top5_points'] ?? 2.0,
                    $rules['top4_points'] ?? 3.0,
                    $rules['position_loss_multiplier'] ?? -0.5,
                    $rules['bonus_cap_value'] ?? 50.0,
                    $rules['malus_cap_value'] ?? -30.0,
                    $rules['race_winner_points'] ?? 25.0,
                    $rules['fastest_lap_points'] ?? 1.0,
                    $rules['drs_multiplier_bonus'] ?? 1.2,
                    $rules['drs_cap_value'] ?? 30.0,
                    $rules['max_drivers_count'] ?? 6,
                    json_encode($rules['user_position_points'] ?? [25,18,14,10,6,3,1])
                ]);
                
                $db->commit();
                
                // Get created season with rules
                $stmt = $db->prepare("
                    SELECT s.*, sr.*
                    FROM seasons s
                    JOIN season_rules sr ON s.id = sr.season_id
                    WHERE s.id = ?
                ");
                $stmt->execute([$seasonId]);
                $season = $stmt->fetch();
                
                $response = [
                    'id' => (int)$season['id'],
                    'year' => (int)$season['year'],
                    'status' => $season['status'],
                    'created_at' => $season['created_at'],
                    'rules' => [
                        'default_budget' => (float)$season['default_budget'],
                        'last_to_top10_points' => (float)$season['last_to_top10_points'],
                        'top10_to_top5_points' => (float)$season['top10_to_top5_points'],
                        'top4_points' => (float)$season['top4_points'],
                        'position_loss_multiplier' => (float)$season['position_loss_multiplier'],
                        'bonus_cap_value' => (float)$season['bonus_cap_value'],
                        'malus_cap_value' => (float)$season['malus_cap_value'],
                        'race_winner_points' => (float)$season['race_winner_points'],
                        'fastest_lap_points' => (float)$season['fastest_lap_points'],
                        'drs_multiplier_bonus' => (float)$season['drs_multiplier_bonus'],
                        'drs_cap_value' => (float)$season['drs_cap_value'],
                        'max_drivers_count' => (int)$season['max_drivers_count'],
                        'user_position_points' => json_decode($season['user_position_points'], true)
                    ]
                ];
                
                sendSuccess($response, 'Season created successfully', 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            if (!$seasonYear) {
                sendError('Season year is required in URL', 400);
            }
            
            // Update season
            $input = getJSONInput();
            
            // Check if season exists
            $stmt = $db->prepare("SELECT id FROM seasons WHERE year = ?");
            $stmt->execute([$seasonYear]);
            $season = $stmt->fetch();
            
            if (!$season) {
                sendNotFound('Season not found');
            }
            
            $seasonId = $season['id'];
            
            $db->beginTransaction();
            
            try {
                // Update season if status provided
                if (isset($input['status']) && in_array($input['status'], ['upcoming', 'active', 'completed'])) {
                    $stmt = $db->prepare("UPDATE seasons SET status = ? WHERE id = ?");
                    $stmt->execute([$input['status'], $seasonId]);
                }
                
                // Update rules if provided
                if (isset($input['rules']) && is_array($input['rules'])) {
                    $rules = $input['rules'];
                    $updateFields = [];
                    $updateValues = [];
                    
                    $ruleFields = [
                        'default_budget', 'last_to_top10_points', 'top10_to_top5_points', 'top4_points',
                        'position_loss_multiplier', 'bonus_cap_value', 'malus_cap_value', 'race_winner_points',
                        'fastest_lap_points', 'drs_multiplier_bonus', 'drs_cap_value', 'max_drivers_count'
                    ];
                    
                    foreach ($ruleFields as $field) {
                        if (isset($rules[$field])) {
                            $updateFields[] = "$field = ?";
                            $updateValues[] = $rules[$field];
                        }
                    }
                    
                    if (isset($rules['user_position_points'])) {
                        $updateFields[] = "user_position_points = ?";
                        $updateValues[] = json_encode($rules['user_position_points']);
                    }
                    
                    if (!empty($updateFields)) {
                        $updateValues[] = $seasonId;
                        $sql = "UPDATE season_rules SET " . implode(', ', $updateFields) . " WHERE season_id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute($updateValues);
                    }
                }
                
                $db->commit();
                
                sendSuccess(null, 'Season updated successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            sendMethodNotAllowed(['GET', 'POST', 'PUT']);
    }
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Seasons API error: " . $e->getMessage());
    sendServerError('Seasons operation failed');
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Seasons API error: " . $e->getMessage());
    sendServerError('Seasons operation failed');
}
?>

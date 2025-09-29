<?php
// Fantasy Formula 1 - Races API
// GET /races - List races for a season
// POST /races - Create new race (admin only)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

$method = getRequestMethod();

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            // Get query parameters
            $seasonYear = getQueryParam('season', date('Y')); // Default to current year
            $status = getQueryParam('status'); // upcoming, completed
            $upcoming = getQueryParam('upcoming'); // true/false - races from now onwards
            [$page, $pageSize] = getPaginationParams();
            
            // Get season
            $stmt = $db->prepare("SELECT id, year, status FROM seasons WHERE year = ?");
            $stmt->execute([$seasonYear]);
            $season = $stmt->fetch();
            
            if (!$season) {
                sendError('Season not found', 404);
            }
            
            // Build race query
            $whereConditions = ["r.season_id = ?"];
            $params = [$season['id']];
            
            if ($upcoming === 'true') {
                $whereConditions[] = "r.race_date > CURRENT_TIMESTAMP";
            } elseif ($upcoming === 'false') {
                $whereConditions[] = "r.race_date <= CURRENT_TIMESTAMP";
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Count total races
            $countSql = "SELECT COUNT(*) as total FROM races r $whereClause";
            $stmt = $db->prepare($countSql);
            $stmt->execute($params);
            $totalItems = $stmt->fetch()['total'];
            
            // Get races with driver availability
            $offset = ($page - 1) * $pageSize;
            $sql = "
                SELECT 
                    r.id,
                    r.name,
                    r.track_name,
                    r.country,
                    r.race_date,
                    r.qualifying_date,
                    r.round_number,
                    r.budget_override,
                    sr.default_budget,
                    CASE 
                        WHEN r.budget_override IS NOT NULL THEN r.budget_override
                        ELSE sr.default_budget
                    END as effective_budget
                FROM races r
                JOIN seasons s ON r.season_id = s.id
                JOIN season_rules sr ON s.id = sr.season_id
                $whereClause
                ORDER BY r.race_date ASC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $pageSize;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $races = $stmt->fetchAll();
            
            // Format response
            foreach ($races as &$race) {
                $race['id'] = (int)$race['id'];
                $race['round_number'] = (int)$race['round_number'];
                $race['budget_override'] = $race['budget_override'] ? (float)$race['budget_override'] : null;
                $race['default_budget'] = (float)$race['default_budget'];
                $race['effective_budget'] = (float)$race['effective_budget'];
                
                // Add race status based on dates
                $now = new DateTime();
                $raceDate = new DateTime($race['race_date']);
                $qualifyingDate = new DateTime($race['qualifying_date']);
                
                if ($now < $qualifyingDate) {
                    $race['race_status'] = 'upcoming';
                } elseif ($now >= $qualifyingDate && $now < $raceDate) {
                    $race['race_status'] = 'qualifying';
                } else {
                    $race['race_status'] = 'completed';
                }
            }
            
            $response = [
                'season' => $season,
                'races' => $races
            ];
            
            sendPaginatedResponse($response, $page, $pageSize, $totalItems);
            break;
            
        case 'POST':
            // Create new race (admin functionality - could be restricted later)
            $input = getJSONInput();
            $errors = [];
            
            // Validation
            if (empty($input['name'])) {
                $errors['name'] = 'Race name is required';
            }
            
            if (empty($input['track_name'])) {
                $errors['track_name'] = 'Track name is required';
            }
            
            if (empty($input['country'])) {
                $errors['country'] = 'Country is required';
            }
            
            if (empty($input['season_year'])) {
                $errors['season_year'] = 'Season year is required';
            }
            
            if (empty($input['race_date'])) {
                $errors['race_date'] = 'Race date is required';
            } elseif (!strtotime($input['race_date'])) {
                $errors['race_date'] = 'Invalid race date format';
            }
            
            if (empty($input['qualifying_date'])) {
                $errors['qualifying_date'] = 'Qualifying date is required';
            } elseif (!strtotime($input['qualifying_date'])) {
                $errors['qualifying_date'] = 'Invalid qualifying date format';
            }
            
            if (empty($input['round_number'])) {
                $errors['round_number'] = 'Round number is required';
            } elseif (!is_numeric($input['round_number']) || $input['round_number'] < 1) {
                $errors['round_number'] = 'Round number must be a positive integer';
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
            
            // Check for duplicate round number in season
            $stmt = $db->prepare("SELECT id FROM races WHERE season_id = ? AND round_number = ?");
            $stmt->execute([$season['id'], $input['round_number']]);
            
            if ($stmt->fetch()) {
                sendError('Round number already exists in this season', 409);
            }
            
            // Validate dates
            if (strtotime($input['qualifying_date']) >= strtotime($input['race_date'])) {
                sendError('Qualifying date must be before race date', 400);
            }
            
            // Create race
            $budgetOverride = isset($input['budget_override']) ? (float)$input['budget_override'] : null;
            
            $stmt = $db->prepare("
                INSERT INTO races (season_id, name, track_name, country, race_date, qualifying_date, round_number, budget_override)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $season['id'],
                $input['name'],
                $input['track_name'],
                $input['country'],
                $input['race_date'],
                $input['qualifying_date'],
                $input['round_number'],
                $budgetOverride
            ]);
            
            $raceId = $db->lastInsertId();
            
            // Get created race with details
            $stmt = $db->prepare("
                SELECT 
                    r.*,
                    s.year as season_year,
                    sr.default_budget,
                    CASE 
                        WHEN r.budget_override IS NOT NULL THEN r.budget_override
                        ELSE sr.default_budget
                    END as effective_budget
                FROM races r
                JOIN seasons s ON r.season_id = s.id
                JOIN season_rules sr ON s.id = sr.season_id
                WHERE r.id = ?
            ");
            $stmt->execute([$raceId]);
            $race = $stmt->fetch();
            
            $race['id'] = (int)$race['id'];
            $race['season_id'] = (int)$race['season_id'];
            $race['round_number'] = (int)$race['round_number'];
            $race['budget_override'] = $race['budget_override'] ? (float)$race['budget_override'] : null;
            $race['default_budget'] = (float)$race['default_budget'];
            $race['effective_budget'] = (float)$race['effective_budget'];
            $race['season_year'] = (int)$race['season_year'];
            
            sendSuccess($race, 'Race created successfully', 201);
            break;
            
        default:
            sendMethodNotAllowed(['GET', 'POST']);
    }
    
} catch (PDOException $e) {
    error_log("Races API error: " . $e->getMessage());
    sendServerError('Races operation failed');
} catch (Exception $e) {
    error_log("Races API error: " . $e->getMessage());
    sendServerError('Races operation failed');
}
?>

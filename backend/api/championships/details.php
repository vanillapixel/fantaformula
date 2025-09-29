<?php
// Fantasy Formula 1 - Specific Championship API
// GET /championships/{id} - Get championship details
// PUT /championships/{id} - Update championship (admin only)
// DELETE /championships/{id} - Delete championship (admin only)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

// Get championship ID from URL
$championshipId = null;
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (preg_match('/^\/(\d+)$/', $pathInfo, $matches)) {
    $championshipId = (int)$matches[1];
}

if (!$championshipId) {
    sendError('Championship ID is required', 400);
}

$method = getRequestMethod();

try {
    $db = getDB();
    
    // Check if championship exists
    $stmt = $db->prepare("
        SELECT c.*, s.year as season_year, s.status as season_status
        FROM championships c
        JOIN seasons s ON c.season_id = s.id
        WHERE c.id = ?
    ");
    $stmt->execute([$championshipId]);
    $championship = $stmt->fetch();
    
    if (!$championship) {
        sendNotFound('Championship not found');
    }
    
    switch ($method) {
        case 'GET':
            // Get championship details with participants and admins
            
            // Get participants
            $stmt = $db->prepare("
                SELECT u.id, u.username, u.email, cp.joined_at
                FROM championship_participants cp
                JOIN users u ON cp.user_id = u.id
                WHERE cp.championship_id = ?
                ORDER BY cp.joined_at ASC
            ");
            $stmt->execute([$championshipId]);
            $participants = $stmt->fetchAll();
            
            // Get admins
            $stmt = $db->prepare("
                SELECT u.id, u.username, u.email, ca.created_at
                FROM championship_admins ca
                JOIN users u ON ca.user_id = u.id
                WHERE ca.championship_id = ?
                ORDER BY ca.created_at ASC
            ");
            $stmt->execute([$championshipId]);
            $admins = $stmt->fetchAll();
            
            // Get upcoming races for this season
            $stmt = $db->prepare("
                SELECT id, name, track_name, country, race_date, qualifying_date, round_number
                FROM races
                WHERE season_id = ? AND race_date > CURRENT_TIMESTAMP
                ORDER BY race_date ASC
                LIMIT 5
            ");
            $stmt->execute([$championship['season_id']]);
            $upcomingRaces = $stmt->fetchAll();
            
            // Format response
            $championship['id'] = (int)$championship['id'];
            $championship['season_id'] = (int)$championship['season_id'];
            $championship['max_participants'] = (int)$championship['max_participants'];
            $championship['is_public'] = (bool)$championship['is_public'];
            $championship['season_year'] = (int)$championship['season_year'];
            $championship['settings'] = $championship['settings'] ? json_decode($championship['settings'], true) : null;
            $championship['participants'] = $participants;
            $championship['admins'] = $admins;
            $championship['upcoming_races'] = $upcomingRaces;
            $championship['participant_count'] = count($participants);
            
            sendSuccess($championship, 'Championship details retrieved');
            break;
            
        case 'PUT':
            // Update championship (admin only)
            requireChampionshipAdmin($championshipId);
            
            $input = getJSONInput();
            $errors = [];
            
            // Validation
            if (isset($input['name'])) {
                if (empty($input['name'])) {
                    $errors['name'] = 'Championship name cannot be empty';
                } elseif (strlen($input['name']) > 100) {
                    $errors['name'] = 'Championship name must be less than 100 characters';
                }
            }
            
            if (isset($input['max_participants'])) {
                if (!is_numeric($input['max_participants']) || $input['max_participants'] < 2 || $input['max_participants'] > 100) {
                    $errors['max_participants'] = 'Max participants must be between 2 and 100';
                }
            }
            
            if (isset($input['status'])) {
                if (!in_array($input['status'], ['upcoming', 'active', 'completed'])) {
                    $errors['status'] = 'Status must be: upcoming, active, or completed';
                }
            }
            
            if (!empty($errors)) {
                sendValidationError($errors);
            }
            
            // Build update query
            $updateFields = [];
            $updateValues = [];
            
            if (isset($input['name'])) {
                // Check for duplicate name in same season
                $stmt = $db->prepare("SELECT id FROM championships WHERE name = ? AND season_id = ? AND id != ?");
                $stmt->execute([$input['name'], $championship['season_id'], $championshipId]);
                if ($stmt->fetch()) {
                    sendError('Championship name already exists in this season', 409);
                }
                
                $updateFields[] = 'name = ?';
                $updateValues[] = $input['name'];
            }
            
            if (isset($input['status'])) {
                $updateFields[] = 'status = ?';
                $updateValues[] = $input['status'];
            }
            
            if (isset($input['max_participants'])) {
                $updateFields[] = 'max_participants = ?';
                $updateValues[] = (int)$input['max_participants'];
            }
            
            if (isset($input['is_public'])) {
                $updateFields[] = 'is_public = ?';
                $updateValues[] = (bool)$input['is_public'] ? 1 : 0;
            }
            
            if (isset($input['settings'])) {
                $updateFields[] = 'settings = ?';
                $updateValues[] = json_encode($input['settings']);
            }
            
            if (empty($updateFields)) {
                sendError('No valid fields to update');
            }
            
            $updateValues[] = $championshipId;
            
            $sql = "UPDATE championships SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($updateValues);
            
            // Get updated championship
            $stmt = $db->prepare("
                SELECT c.*, s.year as season_year, s.status as season_status
                FROM championships c
                JOIN seasons s ON c.season_id = s.id
                WHERE c.id = ?
            ");
            $stmt->execute([$championshipId]);
            $updatedChampionship = $stmt->fetch();
            
            $updatedChampionship['id'] = (int)$updatedChampionship['id'];
            $updatedChampionship['season_id'] = (int)$updatedChampionship['season_id'];
            $updatedChampionship['max_participants'] = (int)$updatedChampionship['max_participants'];
            $updatedChampionship['is_public'] = (bool)$updatedChampionship['is_public'];
            $updatedChampionship['season_year'] = (int)$updatedChampionship['season_year'];
            $updatedChampionship['settings'] = $updatedChampionship['settings'] ? json_decode($updatedChampionship['settings'], true) : null;
            
            sendSuccess($updatedChampionship, 'Championship updated successfully');
            break;
            
        case 'DELETE':
            // Delete championship (admin only)
            requireChampionshipAdmin($championshipId);
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Delete related records (cascade should handle this, but let's be explicit)
                $stmt = $db->prepare("DELETE FROM championship_participants WHERE championship_id = ?");
                $stmt->execute([$championshipId]);
                
                $stmt = $db->prepare("DELETE FROM championship_admins WHERE championship_id = ?");
                $stmt->execute([$championshipId]);
                
                $stmt = $db->prepare("DELETE FROM championships WHERE id = ?");
                $stmt->execute([$championshipId]);
                
                $db->commit();
                
                sendSuccess(null, 'Championship deleted successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            sendMethodNotAllowed(['GET', 'PUT', 'DELETE']);
    }
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Championship API error: " . $e->getMessage());
    sendServerError('Championship operation failed');
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Championship API error: " . $e->getMessage());
    sendServerError('Championship operation failed');
}
?>

<?php
// Fantasy Formula 1 - Championship Participants API
// POST /championships/{id}/join - Join championship
// DELETE /championships/{id}/leave - Leave championship
// POST /championships/{id}/admins - Add admin (admin only)
// DELETE /championships/{id}/admins/{user_id} - Remove admin (admin only)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

// Require authentication for all operations
requireAuth();
$currentUserId = getCurrentUserId();

// Get championship ID from URL
$championshipId = null;
$action = null;
$targetUserId = null;

$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (preg_match('/^\/(\d+)\/(join|leave|admins)(?:\/(\d+))?$/', $pathInfo, $matches)) {
    $championshipId = (int)$matches[1];
    $action = $matches[2];
    $targetUserId = isset($matches[3]) ? (int)$matches[3] : null;
}

if (!$championshipId || !$action) {
    sendError('Invalid URL format', 400);
}

$method = getRequestMethod();

try {
    $db = getDB();
    
    // Check if championship exists
    $stmt = $db->prepare("SELECT id, name, status, max_participants, is_public FROM championships WHERE id = ?");
    $stmt->execute([$championshipId]);
    $championship = $stmt->fetch();
    
    if (!$championship) {
        sendNotFound('Championship not found');
    }
    
    switch ($action) {
        case 'join':
            if ($method !== 'POST') {
                sendMethodNotAllowed(['POST']);
            }
            
            // Check if championship is public or user is invited (future feature)
            if (!$championship['is_public']) {
                sendForbidden('Championship is private');
            }
            
            // Check if already a participant
            $stmt = $db->prepare("SELECT 1 FROM championship_participants WHERE championship_id = ? AND user_id = ?");
            $stmt->execute([$championshipId, $currentUserId]);
            
            if ($stmt->fetch()) {
                sendError('Already a participant in this championship', 409);
            }
            
            // Check max participants limit
            if ($championship['max_participants']) {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM championship_participants WHERE championship_id = ?");
                $stmt->execute([$championshipId]);
                $currentCount = $stmt->fetch()['count'];
                
                if ($currentCount >= $championship['max_participants']) {
                    sendError('Championship is full', 409);
                }
            }
            
            // Join championship
            $stmt = $db->prepare("
                INSERT INTO championship_participants (championship_id, user_id, joined_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$championshipId, $currentUserId]);
            
            sendSuccess([
                'championship_id' => $championshipId,
                'user_id' => $currentUserId,
                'joined_at' => date('Y-m-d H:i:s')
            ], 'Successfully joined championship');
            break;
            
        case 'leave':
            if ($method !== 'DELETE') {
                sendMethodNotAllowed(['DELETE']);
            }
            
            // Check if user is a participant
            $stmt = $db->prepare("SELECT 1 FROM championship_participants WHERE championship_id = ? AND user_id = ?");
            $stmt->execute([$championshipId, $currentUserId]);
            
            if (!$stmt->fetch()) {
                sendError('Not a participant in this championship', 400);
            }
            
            // Check if user is the only admin - prevent leaving if so
            $stmt = $db->prepare("SELECT COUNT(*) as admin_count FROM championship_admins WHERE championship_id = ?");
            $stmt->execute([$championshipId]);
            $adminCount = $stmt->fetch()['admin_count'];
            
            $stmt = $db->prepare("SELECT 1 FROM championship_admins WHERE championship_id = ? AND user_id = ?");
            $stmt->execute([$championshipId, $currentUserId]);
            $isAdmin = $stmt->fetch();
            
            if ($isAdmin && $adminCount == 1) {
                sendError('Cannot leave - you are the only admin. Transfer admin rights first or delete the championship.', 400);
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Remove from participants
                $stmt = $db->prepare("DELETE FROM championship_participants WHERE championship_id = ? AND user_id = ?");
                $stmt->execute([$championshipId, $currentUserId]);
                
                // Remove from admins if admin
                if ($isAdmin) {
                    $stmt = $db->prepare("DELETE FROM championship_admins WHERE championship_id = ? AND user_id = ?");
                    $stmt->execute([$championshipId, $currentUserId]);
                }
                
                $db->commit();
                
                sendSuccess(null, 'Successfully left championship');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'admins':
            // Require admin access for admin management
            requireChampionshipAdmin($championshipId);
            
            if ($method === 'POST') {
                // Add admin
                $input = getJSONInput();
                
                if (empty($input['user_id'])) {
                    sendError('User ID is required', 400);
                }
                
                $newAdminUserId = (int)$input['user_id'];
                
                // Check if user exists and is a participant
                $stmt = $db->prepare("
                    SELECT u.username 
                    FROM users u
                    JOIN championship_participants cp ON u.id = cp.user_id
                    WHERE u.id = ? AND cp.championship_id = ?
                ");
                $stmt->execute([$newAdminUserId, $championshipId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    sendError('User not found or not a participant in this championship', 400);
                }
                
                // Check if already an admin
                $stmt = $db->prepare("SELECT 1 FROM championship_admins WHERE championship_id = ? AND user_id = ?");
                $stmt->execute([$championshipId, $newAdminUserId]);
                
                if ($stmt->fetch()) {
                    sendError('User is already an admin', 409);
                }
                
                // Add as admin
                $stmt = $db->prepare("
                    INSERT INTO championship_admins (championship_id, user_id, created_at)
                    VALUES (?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$championshipId, $newAdminUserId]);
                
                sendSuccess([
                    'championship_id' => $championshipId,
                    'user_id' => $newAdminUserId,
                    'username' => $user['username']
                ], 'Admin added successfully');
                
            } elseif ($method === 'DELETE') {
                // Remove admin
                if (!$targetUserId) {
                    sendError('User ID is required in URL', 400);
                }
                
                // Check if target user is an admin
                $stmt = $db->prepare("SELECT 1 FROM championship_admins WHERE championship_id = ? AND user_id = ?");
                $stmt->execute([$championshipId, $targetUserId]);
                
                if (!$stmt->fetch()) {
                    sendError('User is not an admin of this championship', 400);
                }
                
                // Check if this would leave no admins
                $stmt = $db->prepare("SELECT COUNT(*) as admin_count FROM championship_admins WHERE championship_id = ?");
                $stmt->execute([$championshipId]);
                $adminCount = $stmt->fetch()['admin_count'];
                
                if ($adminCount <= 1) {
                    sendError('Cannot remove the last admin', 400);
                }
                
                // Remove admin
                $stmt = $db->prepare("DELETE FROM championship_admins WHERE championship_id = ? AND user_id = ?");
                $stmt->execute([$championshipId, $targetUserId]);
                
                sendSuccess(null, 'Admin removed successfully');
                
            } else {
                sendMethodNotAllowed(['POST', 'DELETE']);
            }
            break;
            
        default:
            sendError('Invalid action', 400);
    }
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Championship participants API error: " . $e->getMessage());
    sendServerError('Operation failed');
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Championship participants API error: " . $e->getMessage());
    sendServerError('Operation failed');
}
?>

<?php
// Fantasy Formula 1 - User Profile API
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

// Require authentication
requireAuth();

$method = getRequestMethod();

try {
    $db = getDB();
    $userId = getCurrentUserId();
    
    switch ($method) {
        case 'GET':
            // Get user profile
            $stmt = $db->prepare("
                SELECT id, username, email, created_at, updated_at
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendNotFound('User not found');
            }
            
            // Get user's championship count
            $stmt = $db->prepare("
                SELECT COUNT(*) as championship_count
                FROM championship_participants 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch();
            
            $user['stats'] = [
                'championship_count' => (int)$stats['championship_count']
            ];
            
            sendSuccess($user, 'Profile retrieved successfully');
            break;
            
        case 'PUT':
            // Update user profile
            $input = getJSONInput();
            $errors = [];
            
            // Validate email if provided
            if (isset($input['email'])) {
                if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Invalid email format';
                } else {
                    // Check if email is already taken by another user
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$input['email'], $userId]);
                    if ($stmt->fetch()) {
                        $errors['email'] = 'Email already exists';
                    }
                }
            }
            
            // Validate password if provided
            if (isset($input['password'])) {
                if (strlen($input['password']) < 6) {
                    $errors['password'] = 'Password must be at least 6 characters';
                }
            }
            
            if (!empty($errors)) {
                sendValidationError($errors);
            }
            
            // Build update query
            $updateFields = [];
            $updateValues = [];
            
            if (isset($input['email'])) {
                $updateFields[] = 'email = ?';
                $updateValues[] = $input['email'];
            }
            
            if (isset($input['password'])) {
                $updateFields[] = 'password_hash = ?';
                $updateValues[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                sendError('No valid fields to update');
            }
            
            $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';
            $updateValues[] = $userId;
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($updateValues);
            
            // Get updated user info
            $stmt = $db->prepare("
                SELECT id, username, email, created_at, updated_at
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            sendSuccess($user, 'Profile updated successfully');
            break;
            
        default:
            sendMethodNotAllowed(['GET', 'PUT']);
    }
    
} catch (PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    sendServerError('Profile operation failed');
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    sendServerError('Profile operation failed');
}
?>

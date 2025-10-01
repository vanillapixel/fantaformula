<?php
// Fantasy Formula 1 - User Login API
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

// Handle OPTIONS preflight request
if (getRequestMethod() === 'OPTIONS') {
    // CORS headers are already set by config.php
    http_response_code(200);
    exit;
}

// Only allow POST requests
if (getRequestMethod() !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

try {
    // Get input data (support both JSON and x-www-form-urlencoded to avoid CORS preflight in dev)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === 0) {
        $input = getJSONInput();
    } else {
        // Fallback to POST variables
        $input = $_POST;
    }
    
    // Validation
    $errors = [];
    
    if (empty($input['username']) && empty($input['email'])) {
        $errors['login'] = 'Username or email is required';
    }
    
    if (empty($input['password'])) {
        $errors['password'] = 'Password is required';
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    
    // Database lookup
    $db = getDB();
    
    // Find user by username or email
    $loginField = !empty($input['username']) ? 'username' : 'email';
    $loginValue = !empty($input['username']) ? $input['username'] : $input['email'];
    
    $stmt = $db->prepare("
        SELECT id, username, email, password_hash, created_at, COALESCE(is_super_admin,0) as is_super_admin
        FROM users 
        WHERE " . $loginField . " = ?
    ");
    
    $stmt->execute([$loginValue]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('Invalid credentials', 401);
    }
    
    // Verify password
    if (!password_verify($input['password'], $user['password_hash'])) {
        sendError('Invalid credentials', 401);
    }
    
    // Update last login time
    $stmt = $db->prepare("UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Create JWT token
    $payload = [
        'user_id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'is_super_admin' => (int)$user['is_super_admin'],
        'iat' => time()
    ];
    
    $token = JWT::encode($payload);
    
    // Return success response
    sendSuccess([
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'created_at' => $user['created_at'],
            'is_super_admin' => (int)$user['is_super_admin']
        ],
        'token' => $token,
        'expires_in' => JWT_EXPIRY
    ], 'Login successful');
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    sendServerError('Login failed');
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    sendServerError('Login failed');
}
?>

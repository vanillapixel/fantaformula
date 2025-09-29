<?php
// Fantasy Formula 1 - User Registration API
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

// Only allow POST requests
if (getRequestMethod() !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

try {
    // Get input data
    $input = getJSONInput();
    
    // Validation
    $errors = [];
    
    if (empty($input['username'])) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($input['username']) < 3) {
        $errors['username'] = 'Username must be at least 3 characters';
    } elseif (strlen($input['username']) > 50) {
        $errors['username'] = 'Username must be less than 50 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $input['username'])) {
        $errors['username'] = 'Username can only contain letters, numbers, and underscores';
    }
    
    if (empty($input['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($input['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($input['password']) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    
    // Database operations
    $db = getDB();
    
    // Check if username or email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$input['username'], $input['email']]);
    
    if ($stmt->fetch()) {
        sendError('Username or email already exists', 409);
    }
    
    // Hash password
    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password_hash, created_at, updated_at) 
        VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([$input['username'], $input['email'], $passwordHash]);
    
    $userId = $db->lastInsertId();
    
    // Create JWT token
    $payload = [
        'user_id' => $userId,
        'username' => $input['username'],
        'email' => $input['email'],
        'iat' => time()
    ];
    
    $token = JWT::encode($payload);
    
    // Return success response
    sendSuccess([
        'user' => [
            'id' => $userId,
            'username' => $input['username'],
            'email' => $input['email']
        ],
        'token' => $token,
        'expires_in' => JWT_EXPIRY
    ], 'User registered successfully', 201);
    
} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());
    sendServerError('Registration failed');
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    sendServerError('Registration failed');
}
?>

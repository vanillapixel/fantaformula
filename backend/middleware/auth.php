<?php
// Fantasy Formula 1 - JWT Authentication Middleware
// Simple JWT implementation without external dependencies

class JWT {
    
    // Generate JWT token
    public static function encode($payload, $secret = null) {
        $secret = $secret ?: JWT_SECRET;
        
        // Header
        $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);
        $headerEncoded = self::base64UrlEncode($header);
        
        // Add expiry to payload
        $payload['exp'] = time() + JWT_EXPIRY;
        $payloadJson = json_encode($payload);
        $payloadEncoded = self::base64UrlEncode($payloadJson);
        
        // Signature
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    // Decode and verify JWT token
    public static function decode($token, $secret = null) {
        $secret = $secret ?: JWT_SECRET;
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        
        // Verify signature
        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        if (!$payload) {
            return false;
        }
        
        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    // Base64 URL encode
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    // Base64 URL decode
    private static function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

// Middleware function to require authentication
function requireAuth() {
    $token = getBearerToken();
    
    if (!$token) {
        sendUnauthorized('No token provided');
    }
    
    $payload = JWT::decode($token);
    
    if (!$payload) {
        sendUnauthorized('Invalid or expired token');
    }
    
    // Store user info globally for use in API endpoints
    $GLOBALS['current_user'] = $payload;
    
    return $payload;
}

// Get current authenticated user
function getCurrentUser() {
    return $GLOBALS['current_user'] ?? null;
}

// Get current user ID
function getCurrentUserId() {
    $user = getCurrentUser();
    return $user['user_id'] ?? null;
}

// Super admin helpers
function isSuperAdmin() {
    $user = getCurrentUser();
    return !empty($user['is_super_admin']);
}

function requireSuperAdmin() {
    requireAuth();
    if (!isSuperAdmin()) {
        sendForbidden('Super admin access required');
    }
}

// Check if current user is admin of a championship
function isChampionshipAdmin($championshipId) {
    $userId = getCurrentUserId();
    if (!$userId) return false;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT 1 FROM championship_admins 
            WHERE championship_id = ? AND user_id = ?
        ");
        $stmt->execute([$championshipId, $userId]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

// Middleware to require championship admin access
function requireChampionshipAdmin($championshipId) {
    requireAuth();
    
    if (!isChampionshipAdmin($championshipId)) {
        sendForbidden('Championship admin access required');
    }
}

// Get Bearer token from Authorization header
function getBearerToken() {
    $headers = getAuthorizationHeader();
    
    if (!empty($headers) && preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
        return $matches[1];
    }
    
    return null;
}

// Get Authorization header
function getAuthorizationHeader() {
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    return $headers;
}
?>

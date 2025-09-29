<?php
// Fantasy Formula 1 - CORS Middleware

function handleCORS() {
    // Skip CORS handling for CLI
    if (php_sapi_name() === 'cli') {
        return;
    }
    
    // Handle preflight OPTIONS request
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        setCORSHeaders();
        http_response_code(200);
        exit;
    }
    
    // Set CORS headers for all requests
    setCORSHeaders();
}

function setCORSHeaders() {
    // Allow origins (restrict in production)
    if (defined('CORS_ALLOWED_ORIGINS')) {
        header('Access-Control-Allow-Origin: ' . CORS_ALLOWED_ORIGINS);
    }
    
    // Allow methods
    if (defined('CORS_ALLOWED_METHODS')) {
        header('Access-Control-Allow-Methods: ' . CORS_ALLOWED_METHODS);
    }
    
    // Allow headers
    if (defined('CORS_ALLOWED_HEADERS')) {
        header('Access-Control-Allow-Headers: ' . CORS_ALLOWED_HEADERS);
    }
    
    // Allow credentials
    header('Access-Control-Allow-Credentials: true');
    
    // Cache preflight for 24 hours
    header('Access-Control-Max-Age: 86400');
}
?>

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
    // Minimal dev setup; no credentials header to avoid wildcard conflict.
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
}
?>

<?php
// Fantasy Formula 1 - Standard API Response Utilities

// Set JSON content type
function setJSONResponse() {
    header('Content-Type: application/json');
}

// Success response
function sendSuccess($data = null, $message = null, $code = 200) {
    setJSONResponse();
    http_response_code($code);
    
    $response = ['success' => true];
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Error response
function sendError($message, $code = 400, $details = null) {
    setJSONResponse();
    http_response_code($code);
    
    $response = [
        'success' => false,
        'error' => $message
    ];
    
    if ($details !== null) {
        $response['details'] = $details;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Validation error response
function sendValidationError($errors) {
    sendError('Validation failed', 422, $errors);
}

// Unauthorized response
function sendUnauthorized($message = 'Unauthorized') {
    sendError($message, 401);
}

// Forbidden response
function sendForbidden($message = 'Forbidden') {
    sendError($message, 403);
}

// Not found response
function sendNotFound($message = 'Resource not found') {
    sendError($message, 404);
}

// Method not allowed response
function sendMethodNotAllowed($allowedMethods = []) {
    if (!empty($allowedMethods)) {
        header('Allow: ' . implode(', ', $allowedMethods));
    }
    sendError('Method not allowed', 405);
}

// Internal server error response
function sendServerError($message = 'Internal server error') {
    sendError($message, 500);
}

// Paginated response
function sendPaginatedResponse($data, $currentPage, $pageSize, $totalItems, $message = null) {
    $totalPages = ceil($totalItems / $pageSize);
    
    $response = [
        'data' => $data,
        'pagination' => [
            'current_page' => (int)$currentPage,
            'page_size' => (int)$pageSize,
            'total_items' => (int)$totalItems,
            'total_pages' => (int)$totalPages,
            'has_next' => $currentPage < $totalPages,
            'has_prev' => $currentPage > 1
        ]
    ];
    
    sendSuccess($response, $message);
}

// Get request method
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

// Get JSON input
function getJSONInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON input', 400);
    }
    
    return $data ?? [];
}

// Get query parameters with defaults
function getQueryParam($key, $default = null) {
    return $_GET[$key] ?? $default;
}

// Get pagination parameters
function getPaginationParams() {
    $page = max(1, (int)getQueryParam('page', 1));
    $pageSize = min(MAX_PAGE_SIZE, max(1, (int)getQueryParam('page_size', DEFAULT_PAGE_SIZE)));
    
    return [$page, $pageSize];
}

// Log API request (for debugging)
function logRequest() {
    if (APP_ENV === 'development') {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => getRequestMethod(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        error_log('API Request: ' . json_encode($log));
    }
}
?>

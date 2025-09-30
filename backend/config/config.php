<?php
// Fantasy Formula 1 - Main Configuration
// Environment: Beta (Development)

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application settings
define('APP_NAME', 'Fantasy Formula 1');
define('APP_VERSION', '1.0.0-beta');
define('APP_ENV', 'development'); // development, production

// Database configuration
define('DB_PATH', __DIR__ . '/../database/fantaformula.db');
define('DB_TYPE', 'sqlite');

// JWT Configuration
define('JWT_SECRET', 'your-super-secret-jwt-key-change-in-production'); // CHANGE IN PRODUCTION!
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 24 * 60 * 60); // 24 hours in seconds

// API Configuration
define('API_BASE_URL', '/backend/api');
define('CORS_ALLOWED_ORIGINS', '*'); // Restrict in production
define('CORS_ALLOWED_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_ALLOWED_HEADERS', 'Content-Type, Authorization, X-Requested-With');

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// File upload settings (for future driver/team images)
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'webp']);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Fantasy game settings (can be overridden by season_rules)
define('DEFAULT_BUDGET', 250.0);
define('DEFAULT_MAX_DRIVERS', 6);

// Timezone
date_default_timezone_set('UTC');

# Auto-include commonly used files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../database/migrations_runner.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../middleware/cors.php';
// Re-enable PHP CORS handling (Apache centralized config not active in current container)
handleCORS();
?>

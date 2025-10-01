<?php
// Fantasy Formula 1 - Main Configuration
// Environment: Beta (Development)

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Environment loader: prefer phpdotenv if present, fallback to minimal parser
($___autoload = @include __DIR__ . '/../../vendor/autoload.php') || true;
if (class_exists(\Dotenv\Dotenv::class)) {
	$dotenv = \Dotenv\Dotenv::createImmutable(realpath(__DIR__ . '/..'));
	$dotenv->safeLoad();
} else {
	$envFile = __DIR__ . '/../.env';
	if (is_file($envFile)) {
		foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$trim = trim($line);
			if ($trim === '' || $trim[0] === '#') continue;
			if (!str_contains($line, '=')) continue;
			[$k,$v] = array_map('trim', explode('=', $line, 2));
			$v = trim($v, "'\"");
			if ($k !== '' && getenv($k) === false) { putenv($k.'='.$v); $_ENV[$k]=$v; }
		}
	}
}

// Application settings
define('APP_NAME', 'Fantasy Formula 1');
define('APP_VERSION', '1.0.0-beta');
$appEnv = getenv('APP_ENV') ?: 'development';
define('APP_ENV', $appEnv); // development, production

// Database configuration (allow environment override: DB_PATH or FANTA_DB_PATH)
if (!defined('DB_PATH')) {
	$envDb = getenv('DB_PATH') ?: getenv('FANTA_DB_PATH');
	if ($envDb && !str_starts_with($envDb, '/')) {
		// allow relative path inside backend
		$envDb = realpath(__DIR__ . '/..') . '/' . ltrim($envDb,'/');
	}
	define('DB_PATH', $envDb ?: (__DIR__ . '/../database/fantaformula.db'));
}
define('DB_TYPE', 'sqlite');

// JWT Configuration
$jwtSecret = getenv('JWT_SECRET') ?: 'replace-me-in-prod';
define('JWT_SECRET', $jwtSecret); // Loaded from env or placeholder
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
require_once __DIR__ . '/../utils/scoring.php';
require_once __DIR__ . '/../middleware/cors.php';
// Re-enable PHP CORS handling (Apache centralized config not active in current container)
handleCORS();
?>

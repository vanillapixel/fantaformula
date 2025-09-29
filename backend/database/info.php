<?php
// Fantasy Formula 1 - Database Info Endpoint
require_once __DIR__ . '/../config/config.php';

logRequest();

try {
    $db = Database::getInstance();
    $info = $db->getInfo();
    
    // Add some additional info
    $info['database_size'] = file_exists(DB_PATH) ? filesize(DB_PATH) : 0;
    $info['database_size_mb'] = round($info['database_size'] / 1024 / 1024, 2);
    $info['php_version'] = PHP_VERSION;
    $info['app_version'] = APP_VERSION;
    $info['app_env'] = APP_ENV;
    
    sendSuccess($info, 'Database information retrieved');
    
} catch (Exception $e) {
    sendServerError('Failed to retrieve database information: ' . $e->getMessage());
}
?>

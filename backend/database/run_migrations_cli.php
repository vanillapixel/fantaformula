<?php
// Simple CLI wrapper to trigger migrations (loads full config which in turn runs migrations_runner)
// Usage (inside container): php /var/www/html/backend/database/run_migrations_cli.php
if (php_sapi_name() !== 'cli') {
    echo "This script is intended for CLI usage.\n";
}
require_once __DIR__ . '/../config/config.php';
echo "Migrations triggered at " . date('c') . "\n";
?>

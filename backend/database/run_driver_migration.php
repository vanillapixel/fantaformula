<?php
// Simple script to run the driver image migration
require_once __DIR__ . '/../config/config.php';

echo "<h2>Driver Image Migration</h2>\n";
echo "<pre>\n";

try {
    $db = getDB();
    
    // Load and execute the migration
    $migrationFile = __DIR__ . '/migrations/20251024000000_download_driver_images.php';
    
    if (!file_exists($migrationFile)) {
        echo "Migration file not found: $migrationFile\n";
        exit(1);
    }
    
    $migration = require $migrationFile;
    
    if (is_callable($migration)) {
        $result = $migration($db);
        
        if ($result) {
            echo "\n✅ Migration completed successfully!\n";
        } else {
            echo "\n❌ Migration failed!\n";
        }
    } else {
        echo "Migration file did not return a callable function\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

echo "</pre>\n";
?>

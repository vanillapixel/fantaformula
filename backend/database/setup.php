<?php
// Fantasy Formula 1 - Database Setup Script
require_once __DIR__ . '/../config/config.php';

// Check if this is being run from command line or web
$isCommandLine = php_sapi_name() === 'cli';

if (!$isCommandLine) {
    // Web access - basic security
    if (!isset($_GET['setup']) || $_GET['setup'] !== 'confirm') {
        if (!headers_sent()) {
            http_response_code(403);
        }
        die('Access denied. Use ?setup=confirm to initialize database.');
    }
    
    if (!headers_sent()) {
        header('Content-Type: text/plain');
    }
}

echo "Fantasy Formula 1 - Database Setup\n";
echo "==================================\n\n";

try {
    // Get database instance
    $db = Database::getInstance();
    
    // Check if already initialized
    if ($db->isInitialized()) {
        echo "✅ Database already initialized!\n";
        echo "Database info:\n";
        print_r($db->getInfo());
        exit;
    }
    
    echo "📁 Creating database directory...\n";
    $dbDir = dirname(DB_PATH);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
        echo "✅ Directory created: $dbDir\n";
    } else {
        echo "✅ Directory exists: $dbDir\n";
    }
    
    echo "\n🗄️ Executing database schema...\n";
    $schemaPath = __DIR__ . '/../../schema.sql';
    
    if (!file_exists($schemaPath)) {
        throw new Exception("Schema file not found: $schemaPath");
    }
    
    if ($db->executeSchema($schemaPath)) {
        echo "✅ Schema executed successfully!\n";
    } else {
        throw new Exception("Failed to execute schema");
    }
    
    echo "\n📊 Database setup completed!\n";
    echo "Database info:\n";
    print_r($db->getInfo());
    
    echo "\n🎯 Next steps:\n";
    echo "1. Test the API endpoints\n";
    echo "2. Create your first user account\n";
    echo "3. Set up a championship\n";
    echo "\n🔗 Test URLs:\n";
    echo "- Database info: /backend/database/info.php\n";
    echo "- Register user: POST /backend/api/auth/register.php\n";
    echo "- Login user: POST /backend/api/auth/login.php\n";
    
} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    
    if ($isCommandLine) {
        exit(1);
    } else {
        http_response_code(500);
    }
}
?>

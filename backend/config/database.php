<?php
// Fantasy Formula 1 - Database Connection

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            // Create database directory if it doesn't exist
            $dbDir = dirname(DB_PATH);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            // SQLite connection
            $this->connection = new PDO('sqlite:' . DB_PATH);
            
            // Set error mode to exceptions
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Enable foreign key constraints
            $this->connection->exec('PRAGMA foreign_keys = ON');
            
            // Set SQLite to return associative arrays by default
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }

    // Singleton pattern
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get PDO connection
    public function getConnection() {
        return $this->connection;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {}

    // Execute schema file (for setup)
    public function executeSchema($schemaPath) {
        try {
            $schema = file_get_contents($schemaPath);
            if ($schema === false) {
                throw new Exception("Could not read schema file");
            }

            // Remove comments and split by semicolon
            $lines = explode("\n", $schema);
            $cleanLines = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip empty lines and comment lines
                if (!empty($line) && !str_starts_with($line, '--') && !str_starts_with($line, '#')) {
                    $cleanLines[] = $line;
                }
            }
            
            $cleanSchema = implode("\n", $cleanLines);
            $statements = array_filter(
                array_map('trim', explode(';', $cleanSchema)),
                function($stmt) { return !empty($stmt); }
            );

            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->connection->exec($statement);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Schema execution failed: " . $e->getMessage());
            return false;
        }
    }

    // Check if database is initialized
    public function isInitialized() {
        try {
            $stmt = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Get database info
    public function getInfo() {
        try {
            $stmt = $this->connection->query("SELECT sqlite_version() as version");
            $version = $stmt->fetch()['version'];
            
            $stmt = $this->connection->query("SELECT COUNT(*) as count FROM sqlite_master WHERE type='table'");
            $tableCount = $stmt->fetch()['count'];
            
            return [
                'sqlite_version' => $version,
                'database_path' => DB_PATH,
                'table_count' => $tableCount,
                'is_initialized' => $this->isInitialized()
            ];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// Global function to get database instance
function getDB() {
    return Database::getInstance()->getConnection();
}
?>

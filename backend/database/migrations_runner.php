<?php
// Lightweight migrations runner
// Adds new columns / minor schema changes safely (idempotent)

require_once __DIR__ . '/../config/database.php';

function ff_applyMigrations(PDO $db) {
    // 1. Add is_super_admin column to users if missing
    try {
        $stmt = $db->query('PRAGMA table_info(users)');
        $hasCol = false;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (strcasecmp($row['name'], 'is_super_admin') === 0) { $hasCol = true; break; }
        }
        if (!$hasCol) {
            $db->exec('ALTER TABLE users ADD COLUMN is_super_admin INTEGER DEFAULT 0');
        }
    } catch (Exception $e) {
        error_log('Migration (is_super_admin) failed: ' . $e->getMessage());
    }
}

// Apply migrations immediately
try {
    ff_applyMigrations(getDB());
} catch (Exception $e) {
    // Swallow errors so app can continue
    error_log('Migration runner error: ' . $e->getMessage());
}
?>

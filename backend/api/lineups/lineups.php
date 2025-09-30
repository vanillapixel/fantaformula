<?php
// Lineup Selection API (renamed from user_teams)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

$method = getRequestMethod();

try {
    switch ($method) {
        case 'GET':
            ff_handleGetLineup();
            break;
        case 'POST':
            requireAuth();
            ff_handleUpsertLineup();
            break;
        default:
            sendMethodNotAllowed(['GET','POST']);
    }
} catch (Exception $e) {
    error_log('Lineup API error: ' . $e->getMessage());
    sendServerError();
}

// Redundant duplicate file retained temporarily; delegate to canonical index.
require_once __DIR__ . '/index.php';
?>

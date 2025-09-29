<?php
// Fantasy Formula 1 - All Drivers (no pagination)
// GET /backend/api/drivers/all.php?race_id=1
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';
logRequest();
if (getRequestMethod() !== 'GET') { sendMethodNotAllowed(['GET']); }
try {
    $db = getDB();
    $raceId = isset($_GET['race_id']) ? (int)$_GET['race_id'] : null;
    $drivers = $db->query("SELECT id, first_name, last_name, driver_number, driver_code, nationality, picture_url, logo_url, active FROM drivers ORDER BY last_name ASC, first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    if ($raceId && $drivers) {
        $ids = array_column($drivers,'id');
        if ($ids) {
            $in = implode(',', array_fill(0,count($ids),'?'));
            $stmt = $db->prepare("SELECT driver_id, price FROM race_drivers WHERE race_id = ? AND driver_id IN ($in)");
            $stmt->execute(array_merge([$raceId], $ids));
            $pricing = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $pricing[$row['driver_id']] = (float)$row['price']; }
            foreach ($drivers as &$d) { $d['price'] = $pricing[$d['id']] ?? null; }
        }
    }
    sendSuccess(['count'=>count($drivers),'race_id'=>$raceId,'drivers'=>$drivers],'All drivers retrieved');
} catch (Exception $e) {
    error_log('All Drivers error: '.$e->getMessage());
    sendServerError();
}
?>

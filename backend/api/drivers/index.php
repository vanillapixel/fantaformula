<?php
// Fantasy Formula 1 - Drivers API
// GET  /backend/api/drivers/index.php            -> list drivers (optional ?page=&page_size=&race_id=)
// POST /backend/api/drivers/index.php            -> create driver (auth required)

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

$method = getRequestMethod();

try {
    switch ($method) {
        case 'GET':
            handleGetDrivers();
            break;
        case 'POST':
            requireAuth();
            handleCreateDriver();
            break;
        default:
            sendMethodNotAllowed(['GET','POST']);
    }
} catch (Exception $e) {
    error_log('Drivers API error: ' . $e->getMessage());
    sendServerError();
}

function handleGetDrivers() {
    [$page,$pageSize] = getPaginationParams();
    $offset = ($page - 1) * $pageSize;
    $raceId = isset($_GET['race_id']) ? (int)$_GET['race_id'] : null;
    $db = getDB();

    if ($raceId) {
        // ensure race exists (silent if not to still show drivers without pricing)
        $raceStmt = $db->prepare('SELECT id FROM races WHERE id = ?');
        $raceStmt->execute([$raceId]);
        $raceExists = (bool)$raceStmt->fetch();
    }

    // Total active drivers
    $total = (int)$db->query("SELECT COUNT(*) FROM drivers WHERE active = 1")->fetchColumn();

    $stmt = $db->prepare("SELECT id, first_name, last_name, driver_number, driver_code, nationality, picture_url, logo_url, active
                          FROM drivers WHERE active = 1
                          ORDER BY last_name ASC, first_name ASC
                          LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit',$pageSize,PDO::PARAM_INT);
    $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
    $stmt->execute();
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Attach race pricing if race_id provided
    if ($raceId && !empty($drivers)) {
        $ids = array_column($drivers,'id');
        $in  = implode(',', array_fill(0,count($ids),'?'));
        $priceStmt = $db->prepare("SELECT driver_id, price FROM race_drivers WHERE race_id = ? AND driver_id IN ($in)");
        $priceStmt->execute(array_merge([$raceId], $ids));
        $pricingMap = [];
        foreach ($priceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pricingMap[$row['driver_id']] = (float)$row['price'];
        }
        foreach ($drivers as &$d) {
            $d['price'] = $pricingMap[$d['id']] ?? null;
        }
    }

    sendPaginatedResponse(['drivers'=>$drivers, 'race_id'=>$raceId], $page,$pageSize,$total,'Drivers retrieved');
}

function handleCreateDriver() {
    $data = getJSONInput();
    $required = ['first_name','last_name'];
    $errors = [];
    foreach ($required as $f) {
        if (empty($data[$f])) $errors[$f] = 'Required';
    }
    if ($errors) sendValidationError($errors);

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO drivers (first_name,last_name,driver_number,driver_code,nationality,picture_url,logo_url,active)
                          VALUES (:first_name,:last_name,:driver_number,:driver_code,:nationality,:picture_url,:logo_url,1)");
    $stmt->execute([
        ':first_name'=>$data['first_name'],
        ':last_name'=>$data['last_name'],
        ':driver_number'=>$data['driver_number'] ?? null,
        ':driver_code'=>$data['driver_code'] ?? null,
        ':nationality'=>$data['nationality'] ?? null,
        ':picture_url'=>$data['picture_url'] ?? null,
        ':logo_url'=>$data['logo_url'] ?? null,
    ]);

    $id = (int)$db->lastInsertId();
    $driver = $db->query("SELECT id, first_name, last_name, driver_number, driver_code, nationality, picture_url, logo_url, active FROM drivers WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    sendSuccess($driver,'Driver created',201);
}
?>

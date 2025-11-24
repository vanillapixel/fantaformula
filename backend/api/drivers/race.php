<?php
// Fantasy Formula 1 - Drivers API for specific race
// GET /drivers/{race_id} - Get available drivers for a race with prices
// POST /drivers/{race_id}/prices - Update driver prices (admin/AI)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';

logRequest();

// Get race ID from URL
$raceId = null;
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (preg_match('/^\/(\d+)(?:\/(prices))?$/', $pathInfo, $matches)) {
    $raceId = (int)$matches[1];
    $action = $matches[2] ?? null;
}

if (!$raceId) {
    sendError('Race ID is required', 400);
}

$method = getRequestMethod();

try {
    $db = getDB();
    
    // Check if race exists
    $stmt = $db->prepare("
        SELECT r.*, s.year as season_year, sr.default_budget,
               CASE WHEN r.budget_override IS NOT NULL THEN r.budget_override ELSE sr.default_budget END as effective_budget
        FROM races r
        JOIN seasons s ON r.season_id = s.id
        JOIN season_rules sr ON s.id = sr.season_id
        WHERE r.id = ?
    ");
    $stmt->execute([$raceId]);
    $race = $stmt->fetch();
    
    if (!$race) {
        sendNotFound('Race not found');
    }
    
    switch ($method) {
        case 'GET':
            if ($action) {
                sendMethodNotAllowed(['GET']);
            }
            
            // Get drivers available for this race with their constructors and prices
            $stmt = $db->prepare("
                SELECT 
                    d.id as driver_id,
                    d.first_name,
                    d.last_name,
                    d.driver_number,
                    d.driver_code,
                    d.nationality,
                    d.picture_url,
                    d.logo_url,
                    ft.id as constructor_id,
                    ft.name as constructor_name,
                    ft.short_name as constructor_short_name,
                    ft.color_primary as constructor_color,
                    ft.picture_url as constructor_picture_url,
                    ft.logo_url as constructor_logo_url,
                    rd.id as race_driver_id,
                    rd.price,
                    rd.created_at,
                    rr.starting_position,
                    rr.race_position,
                    rr.fastest_lap,
                    rr.dnf,
                    NULL as points_earned
                FROM race_drivers rd
                JOIN drivers d ON rd.driver_id = d.id
                JOIN constructors ft ON rd.constructor_id = ft.id
                LEFT JOIN race_results rr ON rd.race_id = rr.race_id AND rd.driver_id = rr.driver_id
                WHERE rd.race_id = ?
                ORDER BY rd.price DESC, d.last_name ASC
            ");
            $stmt->execute([$raceId]);
            $drivers = $stmt->fetchAll();
            
            // Format response
            foreach ($drivers as &$driver) {
                $driver['driver_id'] = (int)$driver['driver_id'];
                $driver['driver_number'] = (int)$driver['driver_number'];
                $driver['constructor_id'] = (int)$driver['constructor_id'];
                $driver['race_driver_id'] = (int)$driver['race_driver_id'];
                $driver['price'] = (float)$driver['price'];
                $driver['starting_position'] = $driver['starting_position'] ? (int)$driver['starting_position'] : null;
                $driver['race_position'] = $driver['race_position'] ? (int)$driver['race_position'] : null;
                $driver['fastest_lap'] = (bool)$driver['fastest_lap'];
                $driver['dnf'] = (bool)$driver['dnf'];
                // points_earned removed from schema; keep field for backward compatibility (always null)
                $driver['points_earned'] = null;
                
                // Add affordability info
                $driver['affordable'] = $driver['price'] <= $race['effective_budget'];
                $driver['full_name'] = $driver['first_name'] . ' ' . $driver['last_name'];
            }
            
            // Get price statistics
            $prices = array_column($drivers, 'price');
            $priceStats = [
                'min_price' => !empty($prices) ? min($prices) : 0,
                'max_price' => !empty($prices) ? max($prices) : 0,
                'avg_price' => !empty($prices) ? round(array_sum($prices) / count($prices), 2) : 0,
                'median_price' => !empty($prices) ? $prices[floor(count($prices) / 2)] : 0
            ];
            
            $response = [
                'race' => [
                    'id' => (int)$race['id'],
                    'name' => $race['name'],
                    'track_name' => $race['track_name'],
                    'country' => $race['country'],
                    'race_date' => $race['race_date'],
                    'qualifying_date' => $race['qualifying_date'],
                    'round_number' => (int)$race['round_number'],
                    'season_year' => (int)$race['season_year'],
                    'effective_budget' => (float)$race['effective_budget']
                ],
                'drivers' => $drivers,
                'price_stats' => $priceStats,
                'driver_count' => count($drivers)
            ];
            
            sendSuccess($response, 'Race drivers retrieved successfully');
            break;
            
        case 'POST':
            if ($action !== 'prices') {
                sendMethodNotAllowed(['POST']);
            }
            
            // Update driver prices (this would typically be called by AI system)
            // For now, we'll allow it without strict authentication for testing
            
            $input = getJSONInput();
            
            if (empty($input['drivers']) || !is_array($input['drivers'])) {
                sendError('Drivers array is required', 400);
            }
            
            $errors = [];
            $updated = 0;
            
            $db->beginTransaction();
            
            try {
                foreach ($input['drivers'] as $index => $driverUpdate) {
                    if (empty($driverUpdate['driver_id']) || empty($driverUpdate['price'])) {
                        $errors["drivers.$index"] = 'Driver ID and price are required';
                        continue;
                    }
                    
                    $driverId = (int)$driverUpdate['driver_id'];
                    $price = (float)$driverUpdate['price'];
                    
                    if ($price < 0 || $price > 999.99) {
                        $errors["drivers.$index.price"] = 'Price must be between 0 and 999.99';
                        continue;
                    }
                    
                    // Update price for this race
                    $stmt = $db->prepare("
                        UPDATE race_drivers 
                        SET price = ?, ai_calculated_at = CURRENT_TIMESTAMP
                        WHERE race_id = ? AND driver_id = ?
                    ");
                    
                    $result = $stmt->execute([$price, $raceId, $driverId]);
                    
                    if ($result && $stmt->rowCount() > 0) {
                        $updated++;
                    }
                }
                
                if (!empty($errors)) {
                    $db->rollBack();
                    sendValidationError($errors);
                }
                
                $db->commit();
                
                sendSuccess([
                    'race_id' => $raceId,
                    'updated_drivers' => $updated,
                    'update_timestamp' => date('Y-m-d H:i:s')
                ], "Driver prices updated successfully");
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            sendMethodNotAllowed(['GET', 'POST']);
    }
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Drivers API error: " . $e->getMessage());
    sendServerError('Drivers operation failed');
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Drivers API error: " . $e->getMessage());
    sendServerError('Drivers operation failed');
}
?>

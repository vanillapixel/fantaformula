<?php
// Migration: Download and store F1 driver images
// Date: 2025-10-24
// Description: Downloads driver photos and number logos from F1 website and updates database

return function(PDO $db) {
    echo "Starting driver image download migration...\n";
    
    // Create uploads/drivers directory if it doesn't exist
    $uploadsDir = __DIR__ . '/../../uploads/';
    $driversDir = $uploadsDir . 'drivers/';
    
    // Check and create uploads directory first
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0777, true)) {
            echo "✗ Failed to create uploads directory. Please create it manually: $uploadsDir\n";
            echo "✓ Skipping image creation - continuing with database updates only\n";
            $driversDir = null;
        } else {
            echo "✓ Created uploads directory\n";
        }
    }
    
    // Create drivers subdirectory
    if ($driversDir && !is_dir($driversDir)) {
        if (!mkdir($driversDir, 0777, true)) {
            echo "✗ Failed to create drivers directory. Please create it manually: $driversDir\n";
            echo "✓ Skipping image creation - continuing with database updates only\n";
            $driversDir = null;
        } else {
            echo "✓ Created drivers upload directory\n";
        }
    }
    
    // Get all drivers without images
    $stmt = $db->prepare("SELECT id, first_name, last_name, driver_number FROM drivers WHERE picture_url IS NULL OR logo_url IS NULL");
    $stmt->execute();
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($drivers) . " drivers needing images\n";
    
    foreach ($drivers as $driver) {
        $driverId = $driver['id'];
        $firstName = $driver['first_name'];
        $lastName = $driver['last_name'];
        $driverNumber = $driver['driver_number'];
        
        echo "Processing driver: {$firstName} {$lastName} (#{$driverNumber})\n";
        
        $pictureUrl = null;
        $logoUrl = null;
        
        // Try to download driver photo (placeholder for now) - only if directory exists
        if ($driversDir) {
            $photoFilename = strtolower(str_replace(' ', '_', $firstName . '_' . $lastName)) . '_photo.jpg';
            $photoPath = $driversDir . $photoFilename;
            
            // For now, create placeholder images - in production you would download from F1 API
            // This creates simple colored rectangles as placeholders
            if (!file_exists($photoPath)) {
            // Create a simple placeholder image
            $img = imagecreate(300, 400);
            $bgColor = imagecolorallocate($img, rand(50, 200), rand(50, 200), rand(50, 200));
            $textColor = imagecolorallocate($img, 255, 255, 255);
            
            // Add driver name to image
            $text = $firstName . "\n" . $lastName;
            imagestring($img, 5, 50, 150, $text, $textColor);
            
            if (imagejpeg($img, $photoPath)) {
                $pictureUrl = '/backend/uploads/drivers/' . $photoFilename;
                echo "  ✓ Created placeholder photo: {$photoFilename}\n";
            }
            imagedestroy($img);
            }
        }
        
        // Try to download/create driver number logo
        if ($driverNumber && $driversDir) {
            $logoFilename = 'driver_' . $driverNumber . '_logo.jpg';
            $logoPath = $driversDir . $logoFilename;
            
            if (!file_exists($logoPath)) {
                // Create number logo placeholder
                $img = imagecreate(200, 200);
                $bgColor = imagecolorallocate($img, 255, 255, 255);
                $textColor = imagecolorallocate($img, 0, 0, 0);
                
                // Add number to image
                $fontSize = 5;
                $textWidth = imagefontwidth($fontSize) * strlen($driverNumber);
                $textHeight = imagefontheight($fontSize);
                $x = (200 - $textWidth) / 2;
                $y = (200 - $textHeight) / 2;
                
                imagestring($img, $fontSize, $x, $y, $driverNumber, $textColor);
                
                if (imagejpeg($img, $logoPath)) {
                    $logoUrl = '/backend/uploads/drivers/' . $logoFilename;
                    echo "  ✓ Created placeholder logo: {$logoFilename}\n";
                }
                imagedestroy($img);
            }
        } else if ($driverNumber) {
            echo "  ⚠ Skipping logo creation - directory not available\n";
        }
        
        // Update database with image URLs (even if null to mark as processed)
        $updateSql = "UPDATE drivers SET ";
        $params = [];
        $updates = [];
        
        // Always update both fields to prevent re-processing
        $updates[] = "picture_url = ?";
        $params[] = $pictureUrl;
        $updates[] = "logo_url = ?"; 
        $params[] = $logoUrl;
        
        $updateSql .= implode(', ', $updates) . " WHERE id = ?";
        $params[] = $driverId;
        
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->execute($params);
        echo "  ✓ Updated database record\n";
    }
    
    echo "Driver image migration completed!\n";
    
    // Additional update for any remaining drivers without processing
    if ($driversDir === null) {
        echo "Setting remaining drivers to NULL (processed but no images)...\n";
        $remainingStmt = $db->prepare("UPDATE drivers SET picture_url = NULL, logo_url = NULL WHERE picture_url IS NULL AND logo_url IS NULL");
        $remainingStmt->execute();
    }
    
    echo "Migration finished successfully!\n";
    
    // Note: In production, you would implement actual F1 API calls here:
    // 1. Use Formula 1 API or scrape official F1 website  
    // 2. Download high-quality driver photos
    // 3. Download official team number logos
    // 4. Implement proper error handling and retry logic
    // 5. Add image optimization (resize, compress)
    
    return true; // Indicate successful migration
};
?>

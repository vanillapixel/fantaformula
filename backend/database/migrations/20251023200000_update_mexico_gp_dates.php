<?php
// Migration: Update Mexico City Grand Prix 2025 dates
// Date: 2025-10-23
// Description: Updates the Mexico City GP with correct dates from official F1 calendar

return function(PDO $db) {
    // Update Mexico City Grand Prix with correct 2025 dates
    // Race: October 26, 2025 at 21:00 local time (CDT, UTC-5)
    // Qualifying: October 25, 2025 at 23:00 local time (CDT, UTC-5)
    // Converting to UTC: Race = 02:00 UTC Oct 27, Qualifying = 04:00 UTC Oct 26
    
    $result = $db->exec("
        UPDATE races 
        SET race_date = '2025-10-27 02:00:00',
            qualifying_date = '2025-10-26 04:00:00'
        WHERE country = 'Mexico' AND season_id = 1
    ");
    
    if ($result > 0) {
        echo "✓ Updated Mexico City Grand Prix dates to correct 2025 schedule\n";
        echo "  - Race: October 27, 2025 02:00:00 UTC (Oct 26 21:00 CDT)\n";
        echo "  - Qualifying: October 26, 2025 04:00:00 UTC (Oct 25 23:00 CDT)\n";
    } else {
        echo "✓ No Mexico City Grand Prix found to update\n";
    }
};

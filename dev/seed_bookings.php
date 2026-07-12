<?php
require 'backend/config/db.php';

try {
    $db = Database::getConnection();

    // Make some assets bookable
    $db->query("UPDATE assets SET is_shared_bookable = 0");
    $db->query("
        UPDATE assets 
        SET is_shared_bookable = 1 
        WHERE asset_tag IN ('AF-0001', 'AF-0002', 'AF-0003', 'AF-0004')
        LIMIT 4
    ");
    
    // Create a booking for today for AF-0001 from 09:00 to 11:00
    $today = date('Y-m-d');
    
    $stmt = $db->query("SELECT id FROM assets WHERE asset_tag = 'AF-0001' LIMIT 1");
    $assetId = $stmt->fetchColumn();
    
    if ($assetId) {
        $db->query("DELETE FROM bookings WHERE asset_id = $assetId");

        $start = "$today 09:00:00";
        $end = "$today 11:00:00";
        
        $ins = $db->prepare("
            INSERT INTO bookings (asset_id, booked_by, title, purpose, start_time, end_time, status)
            VALUES (?, 1, 'Maintenance Window', 'Routine checks', ?, ?, 'Upcoming')
        ");
        $ins->execute([$assetId, $start, $end]);
    }

    // Add another booking for another asset starting soon!
    $stmt2 = $db->query("SELECT id FROM assets WHERE asset_tag = 'AF-0002' LIMIT 1");
    $asset2Id = $stmt2->fetchColumn();
    
    if ($asset2Id) {
        $startSoon = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $endSoon = date('Y-m-d H:i:s', strtotime('+90 minutes'));
        
        $ins = $db->prepare("
            INSERT INTO bookings (asset_id, booked_by, title, purpose, start_time, end_time, status)
            VALUES (?, 1, 'Team Sync', 'Sync meeting', ?, ?, 'Upcoming')
        ");
        $ins->execute([$asset2Id, $startSoon, $endSoon]);
    }

    echo "Bookings seeded successfully.\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

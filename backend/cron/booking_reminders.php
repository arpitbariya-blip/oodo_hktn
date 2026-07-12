<?php
// backend/cron/booking_reminders.php
// Run every 15 mins via cron

require_once __DIR__ . '/../config/db.php';

try {
    $db = Database::getConnection();
    
    // Find active bookings starting within the next 60 minutes
    // We check if a notification has already been sent to avoid spamming
    $stmt = $db->query("
        SELECT b.id, b.asset_id, b.user_id, b.start_time, ast.name as asset_name 
        FROM asset_bookings b
        JOIN assets ast ON b.asset_id = ast.id
        WHERE b.status = 'Approved' 
        AND b.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)
        AND NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.reference_type = 'booking_reminder' AND n.reference_id = b.id
        )
    ");
    $upcomingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($upcomingBookings)) {
        echo "No new booking reminders to send.\n";
        exit;
    }

    $db->beginTransaction();

    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, type, message, reference_type, reference_id, created_at) VALUES (?, 'event', ?, 'booking_reminder', ?, NOW())");

    $count = 0;
    foreach ($upcomingBookings as $booking) {
        $startTime = date('H:i', strtotime($booking['start_time']));
        $msg = "Booking Reminder: Your booking for {$booking['asset_name']} starts at {$startTime}.";
        
        $notifStmt->execute([$booking['user_id'], $msg, $booking['id']]);
        $count++;
    }

    $db->commit();
    echo "Sent $count booking reminders.\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error processing booking reminders: " . $e->getMessage() . "\n";
}

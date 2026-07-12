<?php
// backend/cron/overdue_check.php
// Run daily via cron to check for overdue allocations.

require_once __DIR__ . '/../config/db.php';

try {
    $db = Database::getConnection();
    
    // Find all active allocations that are past their expected return date
    $stmt = $db->query("
        SELECT a.id, a.asset_id, a.user_id, ast.tag, ast.name as asset_name 
        FROM allocations a
        JOIN assets ast ON a.asset_id = ast.id
        WHERE a.status = 'Active' 
        AND a.expected_return_date < CURRENT_DATE
    ");
    $overdueAllocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($overdueAllocations)) {
        echo "No new overdue allocations found.\n";
        exit;
    }

    $db->beginTransaction();

    $updateStmt = $db->prepare("UPDATE allocations SET status = 'Overdue' WHERE id = ?");
    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, type, message, reference_type, reference_id, created_at) VALUES (?, 'warning', ?, 'allocation', ?, NOW())");
    $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, module, created_at) VALUES (0, ?, 'Allocations', NOW())");

    $count = 0;
    foreach ($overdueAllocations as $alloc) {
        $updateStmt->execute([$alloc['id']]);
        
        $msg = "Overdue Alert: {$alloc['asset_name']} ({$alloc['tag']}) is overdue for return.";
        
        // Notify the user who has the asset
        $notifStmt->execute([$alloc['user_id'], $msg, $alloc['id']]);
        
        // Also notify asset managers (Optional but good practice; keeping simple per spec)
        // We'll log the system action
        $logStmt->execute(["System auto-flagged allocation #{$alloc['id']} as Overdue"]);
        
        $count++;
    }

    $db->commit();
    echo "Marked $count allocations as Overdue and sent notifications.\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error processing overdue checks: " . $e->getMessage() . "\n";
}

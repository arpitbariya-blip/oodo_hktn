<?php
require 'backend/config/db.php';

try {
    $db = Database::getConnection();

    // Clear old maintenance requests
    $db->query("DELETE FROM maintenance_requests");

    $stmt = $db->query("SELECT id FROM assets LIMIT 2");
    $assets = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($assets) > 0) {
        $a1 = $assets[0];
        // Active Request
        $ins = $db->prepare("
            INSERT INTO maintenance_requests (asset_id, raised_by, issue_description, priority, status)
            VALUES (?, 1, 'Spindle bearing noise exceeding acceptable decibel levels.', 'High', 'In Progress')
        ");
        $ins->execute([$a1]);

        // Resolved Request
        $ins = $db->prepare("
            INSERT INTO maintenance_requests (asset_id, raised_by, issue_description, priority, status, resolved_at)
            VALUES (?, 1, 'Coolant pump failure. Replaced unit and verified flow rates.', 'Medium', 'Resolved', DATE_SUB(NOW(), INTERVAL 3 DAY))
        ");
        $ins->execute([$a1]);
    }

    echo "Maintenance seeded successfully.\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

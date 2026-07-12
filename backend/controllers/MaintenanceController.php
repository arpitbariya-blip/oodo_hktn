<?php
class MaintenanceController {
    
    public function getAll() {
        try {
            $db = Database::getConnection();
            $query = "
                SELECT m.*, a.name as asset_name, a.asset_tag, u.name as raised_by_name
                FROM maintenance_requests m
                JOIN assets a ON m.asset_id = a.id
                JOIN users u ON m.raised_by = u.id
                ORDER BY m.raised_at DESC
            ";
            $stmt = $db->query($query);
            Response::json($stmt->fetchAll());
        } catch(PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function create() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['asset_id']) || empty($data['issue_description'])) {
            Response::error('Asset and Description are required.', 400);
            return;
        }

        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO maintenance_requests (asset_id, raised_by, issue_description, priority, status)
                VALUES (?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([
                $data['asset_id'],
                $user_id,
                $data['issue_description'],
                $data['priority'] ?? 'Medium'
            ]);

            Response::json(['message' => 'Maintenance request submitted successfully.']);
        } catch(Exception $e) {
            Response::error('Error creating request: ' . $e->getMessage(), 500);
        }
    }

    public function updateStatus() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['request_id']) || empty($data['status'])) {
            Response::error('Request ID and Status are required.', 400);
            return;
        }

        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            // 1. Update the Maintenance Request
            $reqId = $data['request_id'];
            $newStatus = $data['status'];
            
            $stmt = $db->prepare("
                UPDATE maintenance_requests 
                SET status = ?, 
                    approved_by = IF(? = 'Approved', ?, approved_by),
                    approved_at = IF(? = 'Approved', NOW(), approved_at),
                    resolved_at = IF(? = 'Resolved', NOW(), resolved_at)
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $newStatus, $user_id, $newStatus, $newStatus, $reqId]);

            // 2. Fetch asset_id for this request
            $astmt = $db->prepare("SELECT asset_id FROM maintenance_requests WHERE id = ?");
            $astmt->execute([$reqId]);
            $assetId = $astmt->fetchColumn();

            // 3. Update Asset Lifecycle Status intelligently
            if ($newStatus === 'Approved') {
                $db->prepare("UPDATE assets SET lifecycle_status = 'Under Maintenance' WHERE id = ?")->execute([$assetId]);
            } else if ($newStatus === 'Resolved') {
                // Check if actively allocated
                $allocCheck = $db->prepare("SELECT id FROM allocations WHERE asset_id = ? AND status = 'Active' LIMIT 1");
                $allocCheck->execute([$assetId]);
                $hasAllocation = $allocCheck->fetch();
                
                $finalStatus = $hasAllocation ? 'Allocated' : 'Available';
                $db->prepare("UPDATE assets SET lifecycle_status = ? WHERE id = ?")->execute([$finalStatus, $assetId]);
            }

            $db->commit();
            Response::json(['message' => 'Status updated successfully.']);
        } catch(Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::error('Error updating status: ' . $e->getMessage(), 500);
        }
    }
}

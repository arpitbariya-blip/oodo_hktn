<?php
class AuditController {
    
    // Gets the active (Planned or In Progress) audit cycle and its items
    public function getActive() {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM audit_cycles WHERE status IN ('Planned', 'In Progress') ORDER BY id DESC LIMIT 1");
            $cycle = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cycle) {
                Response::json(['cycle' => null]);
                return;
            }

            $stmt2 = $db->prepare("
                SELECT ai.*, a.asset_tag, a.name as asset_name, a.location, a.lifecycle_status
                FROM audit_items ai
                JOIN assets a ON ai.asset_id = a.id
                WHERE ai.audit_cycle_id = ?
            ");
            $stmt2->execute([$cycle['id']]);
            $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            // Get counts
            $counts = [
                'total' => count($items),
                'verified' => 0,
                'missing' => 0,
                'damaged' => 0,
                'pending' => 0
            ];

            foreach ($items as $item) {
                $counts[strtolower($item['result'])]++;
            }

            Response::json([
                'cycle' => $cycle,
                'items' => $items,
                'counts' => $counts
            ]);
        } catch(PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function create() {
        $data = json_decode(file_get_contents("php://input"), true);
        $user_id = $_SESSION['user_id'] ?? null;
        
        if (!$user_id) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $db = Database::getConnection();
            
            // Check if one is already active
            $check = $db->query("SELECT id FROM audit_cycles WHERE status IN ('Planned', 'In Progress') LIMIT 1");
            if ($check->fetch()) {
                Response::error('An active audit cycle already exists. Please close it first.', 400);
                return;
            }

            $db->beginTransaction();

            $name = $data['name'] ?? 'General Audit ' . date('Y-m-d');
            $dept_id = !empty($data['department_id']) ? $data['department_id'] : null;

            // 1. Create Cycle
            $stmt = $db->prepare("
                INSERT INTO audit_cycles (name, scope_department_id, start_date, end_date, status, created_by)
                VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'In Progress', ?)
            ");
            $stmt->execute([$name, $dept_id, $user_id]);
            $cycle_id = $db->lastInsertId();

            // 2. Insert Auditor (current user)
            $db->prepare("INSERT INTO audit_cycle_auditors (audit_cycle_id, auditor_user_id) VALUES (?, ?)")
               ->execute([$cycle_id, $user_id]);

            // 3. Find Assets and inject into audit_items
            // We only audit assets that are not Disposed or Retired
            $query = "SELECT id FROM assets WHERE lifecycle_status NOT IN ('Retired', 'Disposed')";
            $params = [];
            if ($dept_id) {
                $query .= " AND department_id = ?";
                $params[] = $dept_id;
            }

            $astmt = $db->prepare($query);
            $astmt->execute($params);
            $assets = $astmt->fetchAll(PDO::FETCH_COLUMN);

            $insItem = $db->prepare("INSERT INTO audit_items (audit_cycle_id, asset_id, result) VALUES (?, ?, 'Pending')");
            foreach ($assets as $a_id) {
                $insItem->execute([$cycle_id, $a_id]);
            }

            $db->commit();
            Response::json(['message' => 'Audit cycle created successfully.', 'cycle_id' => $cycle_id, 'asset_count' => count($assets)]);
        } catch(Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            Response::error('Error creating audit: ' . $e->getMessage(), 500);
        }
    }

    public function updateItem() {
        $data = json_decode(file_get_contents("php://input"), true);
        $user_id = $_SESSION['user_id'] ?? null;
        
        if (empty($data['item_id']) || empty($data['result'])) {
            Response::error('Item ID and Result are required.', 400);
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE audit_items 
                SET result = ?, verified_by = ?, verified_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$data['result'], $user_id, $data['item_id']]);
            
            Response::json(['message' => 'Item updated successfully.']);
        } catch(Exception $e) {
            Response::error('Error updating item: ' . $e->getMessage(), 500);
        }
    }

    public function closeCycle() {
        $data = json_decode(file_get_contents("php://input"), true);
        $user_id = $_SESSION['user_id'] ?? null;
        
        if (empty($data['cycle_id'])) {
            Response::error('Cycle ID is required.', 400);
            return;
        }
        $cycle_id = $data['cycle_id'];

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            // 1. Close the cycle
            $db->prepare("UPDATE audit_cycles SET status = 'Closed', closed_by = ?, closed_at = NOW() WHERE id = ?")
               ->execute([$user_id, $cycle_id]);

            // 2. Fetch all missing/damaged items to auto-update asset statuses
            $stmt = $db->prepare("SELECT asset_id, result FROM audit_items WHERE audit_cycle_id = ? AND result IN ('Missing', 'Damaged')");
            $stmt->execute([$cycle_id]);
            $flagged = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $updateAsset = $db->prepare("
                UPDATE assets 
                SET lifecycle_status = IF(:res = 'Missing', 'Lost', IF(:res = 'Damaged', 'Under Maintenance', lifecycle_status)),
                    condition_status = IF(:res = 'Damaged', 'Damaged', condition_status)
                WHERE id = :aid
            ");

            foreach ($flagged as $item) {
                $updateAsset->execute([
                    ':res' => $item['result'],
                    ':aid' => $item['asset_id']
                ]);
            }

            $db->commit();
            Response::json(['message' => 'Audit closed successfully. ' . count($flagged) . ' assets auto-resolved.']);
        } catch(Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            Response::error('Error closing audit: ' . $e->getMessage(), 500);
        }
    }
}

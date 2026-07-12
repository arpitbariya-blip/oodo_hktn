<?php
class AllocationController {
    public function getAll() {
        try {
            $db = Database::getConnection();
            $query = "
                SELECT a.*, 
                       ast.asset_tag, ast.name as asset_name, 
                       u.name as custodian_name, 
                       d.name as location_name
                FROM allocations a
                JOIN assets ast ON a.asset_id = ast.id
                LEFT JOIN users u ON a.allocated_to_user_id = u.id
                LEFT JOIN departments d ON a.allocated_to_department_id = d.id
                WHERE a.status IN ('Active', 'Overdue')
                ORDER BY a.expected_return_date ASC, a.allocation_date DESC
            ";
            
            $stmt = $db->query($query);
            $allocations = $stmt->fetchAll();
            
            Response::json($allocations);
        } catch(PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function getTransfers() {
        try {
            $db = Database::getConnection();
            $query = "
                SELECT tr.*, 
                       ast.asset_tag, ast.name as asset_name, 
                       cu.name as current_custodian,
                       ru.name as requested_custodian
                FROM transfer_requests tr
                JOIN assets ast ON tr.asset_id = ast.id
                LEFT JOIN allocations a ON tr.current_allocation_id = a.id
                LEFT JOIN users cu ON a.allocated_to_user_id = cu.id
                LEFT JOIN users ru ON tr.requested_to_user_id = ru.id
                WHERE tr.status = 'Requested'
                ORDER BY tr.requested_at ASC
            ";
            
            $stmt = $db->query($query);
            $transfers = $stmt->fetchAll();
            
            Response::json($transfers);
        } catch(PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function returnAsset() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['allocation_id']) || empty($data['condition'])) {
            Response::error('Allocation ID and Condition are required.', 400);
            return;
        }

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM allocations WHERE id = ?");
            $stmt->execute([$data['allocation_id']]);
            $allocation = $stmt->fetch();

            if (!$allocation) {
                Response::error('Allocation not found.', 404);
                return;
            }

            // Update allocation
            $updateAlloc = $db->prepare("
                UPDATE allocations 
                SET status = 'Returned', actual_return_date = NOW(), condition_check_in_notes = ?
                WHERE id = ?
            ");
            $updateAlloc->execute([$data['notes'] ?? '', $data['allocation_id']]);

            // Update asset
            $updateAsset = $db->prepare("
                UPDATE assets 
                SET lifecycle_status = 'Available', condition_status = ?
                WHERE id = ?
            ");
            // Map condition string to ENUM ('Excellent','Good','Fair','Poor','Damaged')
            $conditionMap = [
                'Good - Ready for Re-allocation' => 'Good',
                'Fair - Minor Wear' => 'Fair',
                'Poor - Needs Maintenance' => 'Poor',
                'Damaged - Write-off' => 'Damaged'
            ];
            $cond = $conditionMap[$data['condition']] ?? 'Good';
            $updateAsset->execute([$cond, $allocation['asset_id']]);

            $db->commit();
            Response::json(['message' => 'Asset returned successfully.']);
        } catch(Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            Response::error('Error processing return: ' . $e->getMessage(), 500);
        }
    }

    public function resolveTransfer() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['request_id']) || empty($data['action'])) {
            Response::error('Request ID and Action are required.', 400);
            return;
        }

        $user_id = $_SESSION['user_id'] ?? null;

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM transfer_requests WHERE id = ? AND status = 'Requested'");
            $stmt->execute([$data['request_id']]);
            $req = $stmt->fetch();

            if (!$req) {
                Response::error('Transfer request not found or already resolved.', 404);
                return;
            }

            if ($data['action'] === 'deny') {
                $upd = $db->prepare("UPDATE transfer_requests SET status = 'Rejected', resolved_at = NOW(), approved_by = ? WHERE id = ?");
                $upd->execute([$user_id, $req['id']]);
                $db->commit();
                Response::json(['message' => 'Transfer denied.']);
                return;
            }

            if ($data['action'] === 'approve') {
                // Update transfer request
                $upd = $db->prepare("UPDATE transfer_requests SET status = 'Approved', resolved_at = NOW(), approved_by = ? WHERE id = ?");
                $upd->execute([$user_id, $req['id']]);

                // Close current allocation
                $closeAlloc = $db->prepare("UPDATE allocations SET status = 'Returned', actual_return_date = NOW(), condition_check_in_notes = 'Transferred' WHERE id = ?");
                $closeAlloc->execute([$req['current_allocation_id']]);

                // Create new allocation
                $newAlloc = $db->prepare("
                    INSERT INTO allocations (asset_id, allocated_to_user_id, allocated_to_department_id, allocated_by, purpose)
                    VALUES (?, ?, ?, ?, 'Asset Transfer')
                ");
                $newAlloc->execute([
                    $req['asset_id'],
                    $req['requested_to_user_id'],
                    $req['requested_to_department_id'],
                    $user_id
                ]);

                // Asset status remains 'Allocated'
                
                $db->commit();
                Response::json(['message' => 'Transfer approved and asset re-allocated.']);
            }
        } catch(Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            Response::error('Error resolving transfer: ' . $e->getMessage(), 500);
        }
    }

    public function create() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['asset_id']) || empty($data['allocated_to_user_id'])) {
            Response::error('Asset and Assignee are required.', 400);
            return;
        }

        $user_id = $_SESSION['user_id'] ?? null;

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            // Check if asset is already allocated
            $stmt = $db->prepare("SELECT lifecycle_status FROM assets WHERE id = ? FOR UPDATE");
            $stmt->execute([$data['asset_id']]);
            $asset = $stmt->fetch();

            if (!$asset) {
                Response::error('Asset not found.', 404);
                return;
            }

            if ($asset['lifecycle_status'] === 'Allocated' || $asset['lifecycle_status'] === 'Reserved') {
                // Fetch current custodian
                $curAllocStmt = $db->prepare("
                    SELECT a.id, u.name as custodian_name
                    FROM allocations a
                    LEFT JOIN users u ON a.allocated_to_user_id = u.id
                    WHERE a.asset_id = ? AND a.status IN ('Active', 'Overdue')
                    ORDER BY a.id DESC LIMIT 1
                ");
                $curAllocStmt->execute([$data['asset_id']]);
                $curAlloc = $curAllocStmt->fetch();

                if ($curAlloc) {
                    $db->rollBack();
                    Response::error('Asset is already allocated.', 409, [
                        'conflict_data' => [
                            'current_allocation_id' => $curAlloc['id'],
                            'current_custodian' => $curAlloc['custodian_name']
                        ]
                    ]);
                    return;
                }
            }

            // Create Allocation
            $allocStmt = $db->prepare("
                INSERT INTO allocations (asset_id, allocated_to_user_id, allocated_by, expected_return_date)
                VALUES (?, ?, ?, ?)
            ");
            $allocStmt->execute([
                $data['asset_id'],
                $data['allocated_to_user_id'],
                $user_id,
                !empty($data['expected_return_date']) ? $data['expected_return_date'] : null
            ]);

            // Update Asset
            $updateAsset = $db->prepare("UPDATE assets SET lifecycle_status = 'Allocated' WHERE id = ?");
            $updateAsset->execute([$data['asset_id']]);

            $db->commit();
            Response::json(['message' => 'Asset allocated successfully.']);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function requestTransfer() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['asset_id']) || empty($data['current_allocation_id']) || empty($data['requested_to_user_id'])) {
            Response::error('Missing required fields for transfer request.', 400);
            return;
        }

        $user_id = $_SESSION['user_id'] ?? null;

        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO transfer_requests (asset_id, current_allocation_id, requested_by, requested_to_user_id, status)
                VALUES (?, ?, ?, ?, 'Requested')
            ");
            $stmt->execute([
                $data['asset_id'],
                $data['current_allocation_id'],
                $user_id,
                $data['requested_to_user_id']
            ]);

            Response::json(['message' => 'Transfer requested successfully.']);
        } catch (Exception $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}

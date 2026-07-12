<?php
class AssetController {
    public function getAll() {
        try {
            $db = Database::getConnection();
            $query = "SELECT a.*, c.name as category_name, d.name as department_name 
                      FROM assets a
                      LEFT JOIN asset_categories c ON a.category_id = c.id
                      LEFT JOIN departments d ON a.department_id = d.id
                      WHERE 1=1";
            
            $params = [];
            
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $query .= " AND (a.asset_tag LIKE ? OR a.serial_number LIKE ? OR a.name LIKE ?)";
                $searchTerm = '%' . $_GET['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (isset($_GET['category_id']) && !empty($_GET['category_id'])) {
                $query .= " AND a.category_id = ?";
                $params[] = $_GET['category_id'];
            }
            
            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $query .= " AND a.lifecycle_status = ?";
                $params[] = $_GET['status'];
            }
            
            $query .= " ORDER BY a.id DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::json($assets);
        } catch(PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function getDetails() {
        if (!isset($_GET['id'])) {
            Response::error('Asset ID required', 400);
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT a.*, c.name as category_name, d.name as department_name 
                FROM assets a
                LEFT JOIN asset_categories c ON a.category_id = c.id
                LEFT JOIN departments d ON a.department_id = d.id
                WHERE a.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($asset) {
                Response::json($asset);
            } else {
                Response::error('Asset not found', 404);
            }
        } catch(PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function create() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['category_id']) || empty($data['name'])) {
            Response::error('Name and Category are required.', 400);
            return;
        }

        try {
            $db = Database::getConnection();
            
            // Auto-generate Asset Tag (e.g. AST-0001)
            $tagStmt = $db->query("SELECT id FROM assets ORDER BY id DESC LIMIT 1");
            $lastAsset = $tagStmt->fetch(PDO::FETCH_ASSOC);
            $nextId = $lastAsset ? ($lastAsset['id'] + 1) : 1;
            
            $asset_tag = 'AST-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            // Optionally, if user provided a manual tag, we could use that. Let's use the provided tag or generate one.
            if (!empty($data['asset_tag'])) {
                $asset_tag = $data['asset_tag'];
            }

            // Check if tag exists
            $checkStmt = $db->prepare("SELECT id FROM assets WHERE asset_tag = ?");
            $checkStmt->execute([$asset_tag]);
            if ($checkStmt->fetch()) {
                Response::error('Asset tag already exists.', 400);
                return;
            }

            $user_id = $_SESSION['user_id'] ?? null;

            $stmt = $db->prepare("
                INSERT INTO assets (
                    asset_tag, name, category_id, serial_number, 
                    acquisition_date, acquisition_cost, is_shared_bookable, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $asset_tag,
                $data['name'],
                $data['category_id'],
                $data['serial_number'] ?? null,
                !empty($data['acquisition_date']) ? $data['acquisition_date'] : null,
                !empty($data['acquisition_cost']) ? $data['acquisition_cost'] : null,
                !empty($data['is_shared_bookable']) ? 1 : 0,
                $user_id
            ]);
            
            Response::json(['id' => $db->lastInsertId(), 'asset_tag' => $asset_tag, 'message' => 'Asset registered successfully.']);
            
        } catch(PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}

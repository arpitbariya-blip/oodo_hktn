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
            Response::error('Database error: ' , 500);
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
                // Fetch allocation history
                $allocStmt = $db->prepare("SELECT a.*, u.name as user_name FROM allocations a LEFT JOIN users u ON a.user_id = u.id WHERE a.asset_id = ? ORDER BY a.allocated_at DESC");
                $allocStmt->execute([$_GET['id']]);
                $asset['allocation_history'] = $allocStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fetch maintenance history
                $maintStmt = $db->prepare("SELECT m.*, u.name as reported_by_name FROM maintenance_requests m LEFT JOIN users u ON m.reported_by = u.id WHERE m.asset_id = ? ORDER BY m.reported_at DESC");
                $maintStmt->execute([$_GET['id']]);
                $asset['maintenance_history'] = $maintStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fetch documents
                $docStmt = $db->prepare("SELECT * FROM asset_documents WHERE asset_id = ?");
                $docStmt->execute([$_GET['id']]);
                $asset['documents'] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            if ($asset) {
                Response::json($asset);
            } else {
                Response::error('Asset not found', 404);
            }
        } catch(PDOException $e) {
            Response::error('Database error: ' , 500);
        }
    }

    public function create() {
        // If this is a FormData post, we look at $_POST. Otherwise JSON.
        $data = !empty($_POST) ? $_POST : json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['category_id']) || empty($data['name'])) {
            Response::error('Name and Category are required.', 400);
            return;
        }

        try {
            $db = Database::getConnection();
            $db->beginTransaction();
            
            $asset_tag = $data['asset_tag'] ?? '';
            if (empty($asset_tag)) {
                // Auto-generate Asset Tag (e.g. AF-0001) with FOR UPDATE lock
                $tagStmt = $db->query("SELECT id FROM assets ORDER BY id DESC LIMIT 1 FOR UPDATE");
                $lastAsset = $tagStmt->fetch(PDO::FETCH_ASSOC);
                $nextId = $lastAsset ? ($lastAsset['id'] + 1) : 1;
                $asset_tag = 'AF-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }

            // Check if tag exists
            $checkStmt = $db->prepare("SELECT id FROM assets WHERE asset_tag = ?");
            $checkStmt->execute([$asset_tag]);
            if ($checkStmt->fetch()) {
                $db->rollBack();
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
            
            $assetId = $db->lastInsertId();
            
            // Handle Uploads
            if (!empty($_FILES['documents'])) {
                $uploadDir = __DIR__ . '/../../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $docStmt = $db->prepare("INSERT INTO asset_documents (asset_id, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                
                // If multiple files
                if (is_array($_FILES['documents']['name'])) {
                    for ($i = 0; $i < count($_FILES['documents']['name']); $i++) {
                        if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                            $name = basename($_FILES['documents']['name'][$i]);
                            $path = 'uploads/' . time() . '_' . $name;
                            if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], __DIR__ . '/../../' . $path)) {
                                $docStmt->execute([$assetId, $name, $path, $user_id]);
                            }
                        }
                    }
                } else {
                    // Single file
                    if ($_FILES['documents']['error'] === UPLOAD_ERR_OK) {
                        $name = basename($_FILES['documents']['name']);
                        $path = 'uploads/' . time() . '_' . $name;
                        if (move_uploaded_file($_FILES['documents']['tmp_name'], __DIR__ . '/../../' . $path)) {
                            $docStmt->execute([$assetId, $name, $path, $user_id]);
                        }
                    }
                }
            }

            $db->commit();
            Response::json(['success' => true, 'message' => 'Asset registered successfully', 'asset_id' => $assetId]);
        } catch(Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            Response::error('Error creating asset: ' , 500);
        }
    }

    public function update() {
        $data = !empty($_POST) ? $_POST : json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['id']) || empty($data['name']) || empty($data['category_id'])) {
            Response::error('ID, Name and Category are required.', 400);
            return;
        }

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            $stmt = $db->prepare("
                UPDATE assets SET 
                    name = ?, category_id = ?, serial_number = ?, 
                    acquisition_date = ?, acquisition_cost = ?, is_shared_bookable = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['category_id'],
                $data['serial_number'] ?? null,
                !empty($data['acquisition_date']) ? $data['acquisition_date'] : null,
                !empty($data['acquisition_cost']) ? $data['acquisition_cost'] : null,
                !empty($data['is_shared_bookable']) ? 1 : 0,
                $data['id']
            ]);
            
            $user_id = $_SESSION['user_id'] ?? null;
            
            // Handle Uploads
            if (!empty($_FILES['documents'])) {
                $uploadDir = __DIR__ . '/../../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $docStmt = $db->prepare("INSERT INTO asset_documents (asset_id, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                
                if (is_array($_FILES['documents']['name'])) {
                    for ($i = 0; $i < count($_FILES['documents']['name']); $i++) {
                        if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                            $name = basename($_FILES['documents']['name'][$i]);
                            $path = 'uploads/' . time() . '_' . $name;
                            if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], __DIR__ . '/../../' . $path)) {
                                $docStmt->execute([$data['id'], $name, $path, $user_id]);
                            }
                        }
                    }
                }
            }

            $db->commit();
            Response::json(['success' => true, 'message' => 'Asset updated successfully']);
        } catch(Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            Response::error('Error updating asset: ' , 500);
        }
    }

    public function getBookable() {
        try {
            $db = Database::getConnection();
            $query = "
                SELECT a.id, a.name, a.asset_tag, d.name as location_name 
                FROM assets a
                LEFT JOIN departments d ON a.department_id = d.id
                WHERE a.is_shared_bookable = 1
                ORDER BY a.name ASC
            ";
            $stmt = $db->query($query);
            Response::json($stmt->fetchAll());
        } catch(PDOException $e) {
            Response::error('Database error: ' , 500);
        }
    }
}

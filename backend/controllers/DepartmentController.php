<?php
class DepartmentController {
    public function getAll() {
        try {
            $pdo = Database::getConnection();
            $sql = "
                SELECT 
                    d.*,
                    u.name as head_name,
                    u.email as head_email
                FROM departments d
                LEFT JOIN users u ON u.department_id = d.id AND u.role = 'Department Head'
                ORDER BY d.parent_department_id ASC, d.name ASC
            ";
            $stmt = $pdo->query($sql);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::json($departments);
        } catch (Exception $e) {
            Response::error("Failed to load departments: " , 500);
        }
    }

    public function getDetails() {
        try {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                Response::error("Department ID required", 400);
            }

            $pdo = Database::getConnection();
            
            // Get department details
            $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $dept = $stmt->fetch();
            
            if (!$dept) {
                Response::error("Department not found", 404);
            }

            // Get assigned manager (department head)
            // In our system, the head is a user with role 'Department Head' in this department
            $stmtHead = $pdo->prepare("SELECT id, name, email, role FROM users WHERE department_id = ? AND role = 'Department Head' LIMIT 1");
            $stmtHead->execute([$id]);
            $head = $stmtHead->fetch();
            
            $dept['head'] = $head; // Could be false if no head exists
            
            Response::json($dept);
        } catch (Exception $e) {
            Response::error("Failed to load department details: " , 500);
        }
    }
    public function create() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['name']) || empty(trim($data['name']))) {
                Response::error('Department name is required', 400);
            }
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO departments (name, parent_id, status) VALUES (?, ?, 'Active')");
            $parentId = !empty($data['parent_id']) ? $data['parent_id'] : null;
            $stmt->execute([trim($data['name']), $parentId]);
            Response::json(['message' => 'Department created', 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            Response::error("Failed to create department: ", 500);
        }
    }

    public function update() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id']) || !isset($data['name'])) {
                Response::error('ID and name are required', 400);
            }
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE departments SET name = ?, parent_id = ? WHERE id = ?");
            $parentId = !empty($data['parent_id']) ? $data['parent_id'] : null;
            $stmt->execute([trim($data['name']), $parentId, $data['id']]);
            Response::json(['message' => 'Department updated']);
        } catch (Exception $e) {
            Response::error("Failed to update department: ", 500);
        }
    }

    public function delete() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id'])) {
                Response::error('ID is required', 400);
            }
            $pdo = Database::getConnection();
            // Just deactivate it rather than full delete to avoid foreign key issues
            $stmt = $pdo->prepare("UPDATE departments SET status = 'Inactive' WHERE id = ?");
            $stmt->execute([$data['id']]);
            Response::json(['message' => 'Department deactivated']);
        } catch (Exception $e) {
            Response::error("Failed to deactivate department: ", 500);
        }
    }
}

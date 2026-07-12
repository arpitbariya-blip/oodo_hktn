<?php
class DepartmentController {
    public function getAll() {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT id, name, parent_department_id FROM departments ORDER BY name ASC");
            $departments = $stmt->fetchAll();
            Response::json($departments);
        } catch (Exception $e) {
            Response::error("Failed to load departments: " . $e->getMessage(), 500);
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
            Response::error("Failed to load department details: " . $e->getMessage(), 500);
        }
    }
}

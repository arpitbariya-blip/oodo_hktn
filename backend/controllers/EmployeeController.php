<?php
class EmployeeController {
    public function getDirectory() {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("
                SELECT u.id, u.name, u.email, u.role, u.status, d.name as department_name 
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                ORDER BY u.name ASC
            ");
            $employees = $stmt->fetchAll();
            Response::json($employees);
        } catch (Exception $e) {
            Response::error("Failed to load directory: " . $e->getMessage(), 500);
        }
    }

    public function promote() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $input['id'] ?? null;
            
            if (!$userId) {
                Response::error("User ID required", 400);
            }

            $pdo = Database::getConnection();
            
            // Check current role
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                Response::error("User not found", 404);
            }

            if ($user['role'] === 'Admin') {
                Response::error("User is already an Admin", 400);
            }

            // Promote to Admin
            $update = $pdo->prepare("UPDATE users SET role = 'Admin' WHERE id = ?");
            $update->execute([$userId]);
            
            Response::json(['message' => 'User promoted to Admin successfully']);
        } catch (Exception $e) {
            Response::error("Failed to promote user: " . $e->getMessage(), 500);
        }
    }
}

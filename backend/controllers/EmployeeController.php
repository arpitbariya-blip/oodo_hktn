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
            Response::error("Failed to load directory: " , 500);
        }
    }

    public function updateRole() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $input['id'] ?? null;
            $newRole = $input['role'] ?? null;
            
            if (!$userId || !$newRole) {
                Response::error("User ID and new role are required", 400);
            }

            $validRoles = ['Employee', 'Asset Manager', 'Department Head'];
            if (!in_array($newRole, $validRoles)) {
                if ($newRole === 'Admin') {
                    Response::error("Cannot promote to Admin via API. Contact System Administrator.", 403);
                } else {
                    Response::error("Invalid role provided.", 400);
                }
            }

            $pdo = Database::getConnection();
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                Response::error("User not found", 404);
            }

            if ($user['role'] === $newRole) {
                Response::error("User already has this role.", 400);
            }

            // Update role
            $update = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $update->execute([$newRole, $userId]);
            
            Response::json(['message' => 'Role updated successfully']);
        } catch (Exception $e) {
            Response::error("Failed to update user role: " , 500);
        }
    }
}

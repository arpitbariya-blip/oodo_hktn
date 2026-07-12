<?php
class RoleMiddleware {
    // Usage: RoleMiddleware::handle(['Admin', 'Asset Manager']);
    public static function handle($allowedRoles) {
        if (!isset($_SESSION['user_id'])) {
            Response::error("Unauthorized access. Please log in.", 401);
        }
        
        $userRole = $_SESSION['role'] ?? '';
        
        if (!in_array($userRole, $allowedRoles)) {
            Response::error("Forbidden. You do not have permission to access this resource.", 403);
        }
    }
}

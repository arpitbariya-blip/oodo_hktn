<?php
class AuthMiddleware {
    public static function handle() {
        if (!isset($_SESSION['user_id'])) {
            Response::error("Unauthorized access. Please log in.", 401);
        }
    }

    public static function getUser() {
        if (!isset($_SESSION['user_id'])) return null;
        
        // Return a basic user array or fetch full user if needed. 
        // For our current usage, we just need the ID and role.
        return [
            'id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? 'Employee'
        ];
    }
}

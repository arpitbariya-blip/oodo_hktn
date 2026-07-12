<?php
class AuthMiddleware {
    public static function handle() {
        if (!isset($_SESSION['user_id'])) {
            Response::error("Unauthorized access. Please log in.", 401);
        }
    }
}

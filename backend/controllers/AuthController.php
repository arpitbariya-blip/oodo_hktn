<?php
require_once __DIR__ . '/../models/UserModel.php';

class AuthController {
    public function login() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';

            if (empty($email) || empty($password)) {
                Response::error("Email and password are required.", 400);
            }

            $user = UserModel::findByEmail($email);
            
            if (!$user) {
                Response::error("Invalid credentials.", 401);
            }

            if (password_verify($password, $user['password_hash'])) {
                // Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                
                // Fetch full data to return to client
                $userData = UserModel::findById($user['id']);
                Response::json(['message' => 'Logged in successfully', 'user' => $userData]);
            } else {
                Response::error("Invalid credentials.", 401);
            }
        } catch (Exception $e) {
            Response::error("Server error: " . $e->getMessage(), 500);
        }
    }

    public function logout() {
        session_destroy();
        Response::json(['message' => 'Logged out successfully']);
    }

    public function me() {
        if (!isset($_SESSION['user_id'])) {
            Response::error("Not authenticated", 401);
        }

        try {
            $user = UserModel::findById($_SESSION['user_id']);
            if (!$user) {
                session_destroy();
                Response::error("User not found or inactive", 401);
            }
            Response::json($user);
        } catch (Exception $e) {
            Response::error("Server error: " . $e->getMessage(), 500);
        }
    }
}

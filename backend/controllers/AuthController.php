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

    public function signup() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $firstName = trim($input['firstName'] ?? '');
            $lastName = trim($input['lastName'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            $departmentId = $input['departmentId'] ?? null;

            if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($departmentId)) {
                Response::error("All fields are required.", 400);
            }

            // Check if email already exists
            if (UserModel::findByEmail($email)) {
                Response::error("An account with this email already exists.", 409);
            }

            $name = $firstName . ' ' . $lastName;
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Force role to 'Employee' for safety
            $userId = UserModel::create($name, $email, $passwordHash, $departmentId, 'Employee');
            
            // Log them in
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = 'Employee';
            
            $userData = UserModel::findById($userId);
            Response::json(['message' => 'Account created successfully', 'user' => $userData], 201);
            
        } catch (Exception $e) {
            Response::error("Failed to create account: " . $e->getMessage(), 500);
        }
    }

    public function forgotPassword() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = trim($input['email'] ?? '');

            if (empty($email)) {
                Response::error("Email is required.", 400);
            }

            // Mock sending email
            // In a real scenario, we'd generate a token, save to DB, and send an email via SMTP.
            // For now, if the email format is valid, just return success.
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error("Invalid email address.", 400);
            }
            
            Response::json(['message' => 'If that email exists in our system, a password reset link has been sent to it.']);
        } catch (Exception $e) {
            Response::error("Server error: " . $e->getMessage(), 500);
        }
    }
}

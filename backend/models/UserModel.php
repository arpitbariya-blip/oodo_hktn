<?php
class UserModel {
    public static function findByEmail($email) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND status = 'Active'");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public static function findById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.role, u.department_id, d.name as department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = :id AND u.status = 'Active'
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function create($name, $email, $passwordHash, $departmentId, $role = 'Employee') {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, department_id, status) 
            VALUES (:name, :email, :password_hash, :role, :department_id, 'Active')
        ");
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role,
            'department_id' => $departmentId
        ]);
        return $pdo->lastInsertId();
    }
}

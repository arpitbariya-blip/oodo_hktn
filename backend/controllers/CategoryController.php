<?php
class CategoryController {
    public function getAll() {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT * FROM asset_categories ORDER BY name ASC");
            $categories = $stmt->fetchAll();
            Response::json($categories);
        } catch (Exception $e) {
            Response::error("Failed to load categories: " . $e->getMessage(), 500);
        }
    }
}

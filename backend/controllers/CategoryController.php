<?php
class CategoryController {
    public function getAll() {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT * FROM asset_categories ORDER BY name ASC");
            $categories = $stmt->fetchAll();
            
            // Also fetch custom fields
            $stmtFields = $pdo->query("SELECT * FROM category_custom_fields");
            $fields = $stmtFields->fetchAll();
            
            // Map fields to categories
            $fieldMap = [];
            foreach ($fields as $f) {
                $fieldMap[$f['category_id']][] = $f;
            }
            
            foreach ($categories as &$c) {
                $c['custom_fields'] = $fieldMap[$c['id']] ?? [];
            }
            
            Response::json($categories);
        } catch (Exception $e) {
            Response::error("Failed to load categories: " , 500);
        }
    }

    public function create() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['name']) || empty(trim($data['name']))) {
                Response::error('Category name is required', 400);
            }
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO asset_categories (name, description) VALUES (?, ?)");
            $stmt->execute([trim($data['name']), $data['description'] ?? null]);
            $categoryId = $pdo->lastInsertId();
            
            if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                $stmtField = $pdo->prepare("INSERT INTO category_custom_fields (category_id, field_name, field_type, is_required) VALUES (?, ?, ?, ?)");
                foreach ($data['custom_fields'] as $cf) {
                    if (empty($cf['field_name'])) continue;
                    $stmtField->execute([
                        $categoryId, 
                        $cf['field_name'], 
                        $cf['field_type'] ?? 'text', 
                        $cf['is_required'] ? 1 : 0
                    ]);
                }
            }
            
            $pdo->commit();
            Response::json(['message' => 'Category created', 'id' => $categoryId]);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            Response::error("Failed to create category: ", 500);
        }
    }

    public function update() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id']) || !isset($data['name'])) {
                Response::error('ID and name are required', 400);
            }
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE asset_categories SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([trim($data['name']), $data['description'] ?? null, $data['id']]);
            
            // Re-create custom fields
            if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                $pdo->prepare("DELETE FROM category_custom_fields WHERE category_id = ?")->execute([$data['id']]);
                
                $stmtField = $pdo->prepare("INSERT INTO category_custom_fields (category_id, field_name, field_type, is_required) VALUES (?, ?, ?, ?)");
                foreach ($data['custom_fields'] as $cf) {
                    if (empty($cf['field_name'])) continue;
                    $stmtField->execute([
                        $data['id'], 
                        $cf['field_name'], 
                        $cf['field_type'] ?? 'text', 
                        $cf['is_required'] ? 1 : 0
                    ]);
                }
            }
            
            $pdo->commit();
            Response::json(['message' => 'Category updated']);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            Response::error("Failed to update category: ", 500);
        }
    }

    public function delete() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id'])) {
                Response::error('ID is required', 400);
            }
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM asset_categories WHERE id = ?");
            $stmt->execute([$data['id']]);
            Response::json(['message' => 'Category deleted']);
        } catch (Exception $e) {
            // Usually will fail due to foreign key constraints if assets exist
            Response::error("Failed to delete category (likely has associated assets)", 500);
        }
    }
}

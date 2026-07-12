<?php
session_start();
$_SESSION['user_id'] = 1; // Assuming 1 is Admin
$_SESSION['role'] = 'Admin';

// Simulate POST payload
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/Enterprise Asset & Resource Management System/backend/index.php/api/assets';

// Inject payload
$json = json_encode([
    'asset_tag' => '',
    'category_id' => 1,
    'name' => 'Test Asset',
    'serial_number' => '123',
    'acquisition_date' => '2023-01-01',
    'acquisition_cost' => 100,
    'is_shared_bookable' => 1
]);

// Since php://input is read-only stream and cannot be overwritten easily in CLI without some hacks, 
// we'll just require index.php and override the controller data parsing inside AssetController
// Actually, it's easier to just invoke the controller directly!

require 'backend/config/db.php';
require 'backend/utils/Response.php';
require 'backend/middleware/AuthMiddleware.php';
require 'backend/middleware/RoleMiddleware.php';
require 'backend/controllers/AssetController.php';

$controller = new AssetController();
// But $controller->create() reads php://input.
// I'll rewrite the test script to just execute the SQL to see if it throws an error.

$db = Database::getConnection();
$tagStmt = $db->query("SELECT id FROM assets ORDER BY id DESC LIMIT 1");
$lastAsset = $tagStmt->fetch(PDO::FETCH_ASSOC);
$nextId = $lastAsset ? ($lastAsset['id'] + 1) : 1;
$asset_tag = 'AST-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

$stmt = $db->prepare("
    INSERT INTO assets (
        asset_tag, name, category_id, serial_number, 
        acquisition_date, acquisition_cost, is_shared_bookable, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

try {
    $stmt->execute([
        $asset_tag,
        'Test Asset',
        1, // Must be a valid category_id! If no categories exist, this will fail with a foreign key constraint!
        '123',
        '2023-01-01',
        100,
        1,
        1 // Must be a valid user_id!
    ]);
    echo "SUCCESS!";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

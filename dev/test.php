<?php
require_once __DIR__ . '/backend/config/db.php';
require_once __DIR__ . '/backend/utils/Response.php';
require_once __DIR__ . '/backend/controllers/LogController.php';

try {
    // Mock user for testing AuthMiddleware dependency if any
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'Admin';
    
    // Test getLogs
    $controller = new LogController();
    $controller->getLogs();
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

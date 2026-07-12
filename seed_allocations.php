<?php
require 'backend/config/db.php';
$db = Database::getConnection();

try {
    // 1. Create a Category
    $db->query("INSERT IGNORE INTO asset_categories (id, name) VALUES (1, 'IT Equipment')");

    // 2. Create Users (Admin=1, UserA=2, UserB=3)
    $db->query("INSERT IGNORE INTO users (id, name, email, password_hash, role) VALUES (1, 'Admin', 'admin@test.com', 'hash', 'Admin')");
    $db->query("INSERT IGNORE INTO users (id, name, email, password_hash, role) VALUES (2, 'Alice (Current)', 'alice@test.com', 'hash', 'Employee')");
    $db->query("INSERT IGNORE INTO users (id, name, email, password_hash, role) VALUES (3, 'Bob (Requestor)', 'bob@test.com', 'hash', 'Employee')");

    // 3. Create Asset
    $db->query("INSERT IGNORE INTO assets (id, asset_tag, name, category_id, lifecycle_status) VALUES (99, 'AST-9999', 'Test Laptop', 1, 'Allocated')");

    // 4. Create Overdue Allocation
    $db->query("INSERT IGNORE INTO allocations (id, asset_id, allocated_to_user_id, allocated_by, expected_return_date, status) 
                VALUES (98, 99, 2, 1, '2023-01-01', 'Overdue')");

    // 5. Create Active Allocation for Transfer
    $db->query("INSERT IGNORE INTO assets (id, asset_tag, name, category_id, lifecycle_status) VALUES (100, 'AST-8888', 'Transfer Monitor', 1, 'Allocated')");
    $db->query("INSERT IGNORE INTO allocations (id, asset_id, allocated_to_user_id, allocated_by, expected_return_date, status) 
                VALUES (99, 100, 2, 1, '2029-01-01', 'Active')");

    // 6. Create Transfer Request
    $db->query("INSERT IGNORE INTO transfer_requests (id, asset_id, current_allocation_id, requested_by, requested_to_user_id, status) 
                VALUES (99, 100, 99, 3, 3, 'Requested')");

    echo "Seed data inserted successfully.\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

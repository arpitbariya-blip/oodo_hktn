-- =========================================================
-- AssetFlow — Seed / Demo Data
-- Run AFTER schema.sql
-- =========================================================
USE assetflow;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- DEPARTMENTS (insert without head first)
-- =========================================================
INSERT INTO departments (id, name, cost_center_code, description, parent_department_id, status) VALUES
(1, 'Global Headquarters', 'CC-GHQ-00', 'Primary administrative hub overseeing global operations.', NULL, 'Active'),
(2, 'Engineering',          'CC-ENG-01', 'Software and hardware engineering teams.',                1,    'Active'),
(3, 'Operations',           'CC-OPS-02', 'Day-to-day operational management.',                     1,    'Active'),
(4, 'Facilities',           'CC-FAC-03', 'Office and facility management.',                        1,    'Active'),
(5, 'Logistics',            'CC-LOG-04', 'Transport and supply chain.',                            3,    'Active');

-- =========================================================
-- USERS
-- password_hash is bcrypt of "Admin@1234" for admin,
-- and "Employee@1234" for the rest.
-- Generate fresh hashes via: php -r "echo password_hash('Admin@1234', PASSWORD_BCRYPT);"
-- =========================================================
INSERT INTO users (id, name, email, password_hash, role, department_id, status) VALUES
(1,  'System Admin',      'admin@assetflow.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin',            1, 'Active'),
(2,  'Sarah Alvarez',     'salvarez@assetflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Asset Manager',    3, 'Active'),
(3,  'Michael Jenkins',   'mjenkins@assetflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Department Head',  2, 'Active'),
(4,  'Emily Watson',      'ewatson@assetflow.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Department Head',  3, 'Active'),
(5,  'James Carter',      'jcarter@assetflow.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee',         2, 'Active'),
(6,  'Priya Nair',        'pnair@assetflow.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee',         2, 'Active'),
(7,  'David Chen',        'dchen@assetflow.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee',         3, 'Active'),
(8,  'Sarah Jenkins',     'sjenkins@assetflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee',         5, 'Active'),
(9,  'Mike Torres',       'mtorres@assetflow.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee',         4, 'Active'),
(10, 'Alice Johnson',     'ajohnson@assetflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee',         3, 'Active');

-- =========================================================
-- Assign department heads (now that users exist)
-- =========================================================
UPDATE departments SET head_user_id = 1 WHERE id = 1;   -- Admin heads GHQ
UPDATE departments SET head_user_id = 3 WHERE id = 2;   -- Michael heads Engineering
UPDATE departments SET head_user_id = 4 WHERE id = 3;   -- Emily heads Operations
UPDATE departments SET head_user_id = 9 WHERE id = 4;   -- Mike heads Facilities
UPDATE departments SET head_user_id = 8 WHERE id = 5;   -- Sarah heads Logistics

-- =========================================================
-- ASSET CATEGORIES
-- =========================================================
INSERT INTO asset_categories (id, name, description) VALUES
(1, 'IT Equipment',  'Laptops, servers, networking gear, peripherals'),
(2, 'Vehicles',      'Company cars, vans, forklifts'),
(3, 'Machinery',     'Industrial and manufacturing machines'),
(4, 'Furniture',     'Office furniture and fixtures'),
(5, 'AV Equipment',  'Projectors, displays, cameras');

-- =========================================================
-- CATEGORY CUSTOM FIELDS
-- =========================================================
INSERT INTO category_custom_fields (category_id, field_name, field_type, field_options, is_required) VALUES
(1, 'Warranty Expiry',    'date',     NULL,                                      0),
(1, 'OS',                 'dropdown', '["Windows","macOS","Linux","ChromeOS"]',  0),
(1, 'RAM (GB)',            'number',   NULL,                                      0),
(2, 'License Plate',      'text',     NULL,                                      1),
(2, 'Fuel Type',          'dropdown', '["Petrol","Diesel","Electric","Hybrid"]', 0),
(3, 'Machine Model',      'text',     NULL,                                      1),
(3, 'Calibration Due',    'date',     NULL,                                      0),
(4, 'Colour',             'text',     NULL,                                      0);

-- =========================================================
-- ASSETS
-- =========================================================
INSERT INTO assets (id, asset_tag, name, category_id, serial_number, acquisition_date, acquisition_cost, condition_status, location, department_id, is_shared_bookable, lifecycle_status, created_by) VALUES
(1,  'AF-0001', 'MacBook Pro 16"',         1, 'SN-998234A', '2023-01-15', 2499.00, 'Excellent', 'HQ - Floor 3',    2, 0, 'Allocated',          1),
(2,  'AF-0002', 'Dell PowerEdge R740',     1, 'SN-SVR001',  '2022-06-01', 8500.00, 'Good',      'Datacenter Rack A', 2, 0, 'Available',          1),
(3,  'AF-0003', 'Ford Transit Van',        2, 'SN-882711B', '2021-03-20', 32000.00,'Good',      'Depot A',         5, 0, 'Allocated',          1),
(4,  'AF-0004', 'Haas VF-2 CNC Mill',     3, 'SN-110029X', '2020-09-10', 75000.00,'Fair',      'Workshop B',      3, 0, 'Under Maintenance',  1),
(5,  'AF-0005', 'Epson Projector EBS-X',  5, 'SN-PRJ-005', '2022-11-01', 899.00,  'Good',      'Conf Room 4A',    1, 1, 'Available',          1),
(6,  'AF-0006', 'iPhone 14 Pro',          1, 'SN-MOB-449', '2023-03-01', 1199.00, 'Excellent', 'NYC Office',      3, 0, 'Allocated',          1),
(7,  'AF-0007', 'Dell UltraSharp 27"',    1, 'SN-MNT-102', '2022-08-15', 650.00,  'Good',      'Desk 412',        2, 0, 'Available',          1),
(8,  'AF-0008', 'Server Rack A2',         1, 'SN-SRK-001', '2021-01-01', 12000.00,'Good',      'Datacenter',      2, 1, 'Available',          1),
(9,  'AF-0009', 'Conference Room 4B',     4, NULL,          '2019-05-01', 5000.00, 'Good',      'Floor 4',         1, 1, 'Available',          1),
(10, 'AF-0010', 'DJI Mavic 3 Drone',      5, 'SN-DRN-005', '2023-02-14', 2200.00, 'Excellent', 'Equipment Room',  3, 0, 'Allocated',          1);

-- =========================================================
-- ASSET CUSTOM FIELD VALUES
-- =========================================================
INSERT INTO asset_custom_field_values (asset_id, custom_field_id, value) VALUES
(1, 1, '2026-01-15'),   -- MacBook warranty
(1, 2, 'macOS'),        -- MacBook OS
(1, 3, '32'),           -- MacBook RAM
(3, 4, 'KL-01-TN-2211'),-- Van plate
(3, 5, 'Diesel'),        -- Van fuel
(4, 6, 'Haas VF-2'),    -- CNC model
(4, 7, '2024-09-10');   -- CNC calibration due

-- =========================================================
-- ALLOCATIONS
-- =========================================================
INSERT INTO allocations (id, asset_id, allocated_to_user_id, allocated_to_department_id, allocated_by, allocation_date, expected_return_date, status, purpose) VALUES
(1, 1,  5,  NULL, 2, '2023-10-15 09:00:00', '2024-01-15', 'Active',  'Project Alpha development'),
(2, 3,  NULL, 5, 2, '2023-09-01 08:00:00', '2023-10-14', 'Overdue', 'Logistics Team B field ops'),
(3, 6, 10,  NULL, 2, '2023-10-01 10:00:00', '2024-03-01', 'Active',  'Sales team mobile device'),
(4, 10, 7,  NULL, 2, '2023-10-20 09:00:00', '2023-10-16', 'Overdue', 'Site Survey Team mapping');

-- =========================================================
-- BOOKINGS
-- =========================================================
INSERT INTO bookings (asset_id, booked_by, department_id, title, purpose, start_time, end_time, status) VALUES
(5,  9, 4, 'Quarterly Review',       'Q3 presentation',       '2023-10-27 14:00:00', '2023-10-27 16:00:00', 'Completed'),
(9,  5, 2, 'Design Sync',            'Team weekly sync',      '2023-10-27 10:00:00', '2023-10-27 11:30:00', 'Completed'),
(8,  2, 2, 'Server Maintenance',     'Rack inspection',       '2023-10-27 09:00:00', '2023-10-27 11:00:00', 'Completed'),
(5,  8, 5, 'Client Pitch',           'Sales demo',            '2023-10-29 13:00:00', '2023-10-29 15:00:00', 'Upcoming'),
(9,  6, 2, 'Sprint Planning',        'Sprint 22 kickoff',     '2023-10-30 09:00:00', '2023-10-30 10:00:00', 'Upcoming');

-- =========================================================
-- MAINTENANCE REQUESTS
-- =========================================================
INSERT INTO maintenance_requests (id, asset_id, raised_by, issue_description, priority, status, approved_by, technician_name, raised_at, approved_at) VALUES
(1, 4, 7, 'Spindle bearing noise exceeding acceptable decibel levels during high-speed roughing cycles. Requires immediate inspection.', 'Critical', 'In Progress', 2, 'Tech A. Smith', '2023-10-10 08:00:00', '2023-10-11 09:00:00');

-- =========================================================
-- AUDIT CYCLES
-- =========================================================
INSERT INTO audit_cycles (id, name, scope_department_id, scope_location, start_date, end_date, status, created_by) VALUES
(1, 'Q4 2023 Datacenter Audit', 2, 'Facility Alpha (Datacenter)', '2023-10-01', '2023-10-31', 'In Progress', 1);

INSERT INTO audit_cycle_auditors (audit_cycle_id, auditor_user_id) VALUES
(1, 3), (1, 5);

INSERT INTO audit_items (audit_cycle_id, asset_id, result, remarks) VALUES
(1, 2,  'Verified', NULL),
(1, 7,  'Verified', NULL),
(1, 8,  'Pending',  NULL),
(1, 10, 'Missing',  'Not found at expected location - site survey team last used');

-- =========================================================
-- NOTIFICATIONS
-- =========================================================
INSERT INTO notifications (user_id, type, message, reference_type, reference_id, is_read) VALUES
(2, 'Overdue Return Alert',    'Ford Transit Van (AF-0003) is 14 days overdue for return.',       'allocation',    2, 0),
(2, 'Overdue Return Alert',    'DJI Mavic 3 (AF-0010) return is overdue. Contact custodian.',     'allocation',    4, 0),
(1, 'Software License Expiry', 'Adobe CC suite licence expires in 5 days.',                       NULL,          NULL, 0),
(7, 'Asset Assigned',          'MacBook Pro 16" (AF-0001) has been assigned to you.',             'allocation',    1, 1),
(5, 'Maintenance Update',      'Maintenance request MR-0001 for Haas CNC Mill is In Progress.',   'maintenance',   1, 0),
(2, 'Server Rack Power Alert', 'Server Rack B reported a power anomaly. Datacenter 2.',           NULL,          NULL, 0);

-- =========================================================
-- ACTIVITY LOGS
-- =========================================================
INSERT INTO activity_logs (user_id, action, module, reference_id) VALUES
(1, 'System initialised and seed data loaded',       'Auth',        NULL),
(2, 'Allocated AF-0001 to James Carter',             'Asset',       1),
(2, 'Allocated AF-0003 to Logistics Team B',         'Asset',       2),
(2, 'Allocated AF-0006 to Alice Johnson',            'Asset',       3),
(2, 'Allocated AF-0010 to David Chen',               'Asset',       4),
(7, 'Raised maintenance request for AF-0004',        'Maintenance', 1),
(2, 'Approved maintenance request MR-0001',          'Maintenance', 1),
(1, 'Created Q4 2023 Datacenter Audit cycle',        'Audit',       1),
(3, 'Marked SRV-892-XT (AF-0002) as Verified',       'Audit',       2),
(3, 'Marked DJI Mavic 3 (AF-0010) as Missing',       'Audit',       4);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Summary counts
-- =========================================================
SELECT 'departments'    AS tbl, COUNT(*) AS row_count FROM departments
UNION ALL SELECT 'users',              COUNT(*) FROM users
UNION ALL SELECT 'asset_categories',   COUNT(*) FROM asset_categories
UNION ALL SELECT 'assets',             COUNT(*) FROM assets
UNION ALL SELECT 'allocations',        COUNT(*) FROM allocations
UNION ALL SELECT 'bookings',           COUNT(*) FROM bookings
UNION ALL SELECT 'maintenance_requests', COUNT(*) FROM maintenance_requests
UNION ALL SELECT 'audit_cycles',       COUNT(*) FROM audit_cycles
UNION ALL SELECT 'audit_items',        COUNT(*) FROM audit_items
UNION ALL SELECT 'notifications',      COUNT(*) FROM notifications
UNION ALL SELECT 'activity_logs',      COUNT(*) FROM activity_logs;

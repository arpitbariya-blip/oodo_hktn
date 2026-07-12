-- =========================================================
-- AssetFlow — Enterprise Asset & Resource Management System
-- Database Schema  (MariaDB / MySQL 8.x compatible)
-- Run: mysql -u root -p < schema.sql
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS assetflow;
CREATE DATABASE assetflow
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE assetflow;

-- =========================================================
-- 1. DEPARTMENTS
--    Created FIRST (without head_user_id FK) to avoid
--    circular dependency with users. FK added via ALTER later.
-- =========================================================
CREATE TABLE departments (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    name                 VARCHAR(150)                          NOT NULL,
    cost_center_code     VARCHAR(50)                           NULL,
    description          TEXT                                  NULL,
    head_user_id         INT                                   NULL,   -- FK added after users
    parent_department_id INT                                   NULL,
    status               ENUM('Active','Inactive')             NOT NULL DEFAULT 'Active',
    created_at           DATETIME                              DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME                              DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 2. USERS
-- =========================================================
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)                                                          NOT NULL,
    email           VARCHAR(150)                                                          NOT NULL UNIQUE,
    password_hash   VARCHAR(255)                                                          NOT NULL,
    role            ENUM('Admin','Asset Manager','Department Head','Employee')             NOT NULL DEFAULT 'Employee',
    department_id   INT                                                                   NULL,
    status          ENUM('Active','Inactive')                                             NOT NULL DEFAULT 'Active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- Resolve circular FK: departments.head_user_id → users
-- =========================================================
ALTER TABLE departments
    ADD CONSTRAINT fk_dept_head
    FOREIGN KEY (head_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- =========================================================
-- 3. PASSWORD RESETS
-- =========================================================
CREATE TABLE password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    token      VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 4. ASSET CATEGORIES
-- =========================================================
CREATE TABLE asset_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 5. CATEGORY CUSTOM FIELDS
-- =========================================================
CREATE TABLE category_custom_fields (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    category_id    INT          NOT NULL,
    field_name     VARCHAR(100) NOT NULL,
    field_type     ENUM('text','number','date','dropdown') DEFAULT 'text',
    field_options  TEXT         NULL,   -- JSON array for dropdown choices
    is_required    TINYINT(1)   DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES asset_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 6. ASSETS
-- =========================================================
CREATE TABLE assets (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    asset_tag         VARCHAR(20)  NOT NULL UNIQUE,   -- e.g. AF-0001
    name              VARCHAR(150) NOT NULL,
    category_id       INT          NOT NULL,
    serial_number     VARCHAR(100) NULL,
    qr_code           VARCHAR(150) NULL,
    acquisition_date  DATE         NULL,
    acquisition_cost  DECIMAL(12,2) NULL,
    condition_status  ENUM('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
    location          VARCHAR(150) NULL,
    department_id     INT          NULL,
    is_shared_bookable TINYINT(1)  DEFAULT 0,
    lifecycle_status  ENUM(
        'Available',
        'Allocated',
        'Reserved',
        'Under Maintenance',
        'Lost',
        'Retired',
        'Disposed'
    ) NOT NULL DEFAULT 'Available',
    created_by        INT          NULL,
    created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id)   REFERENCES asset_categories(id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)    REFERENCES users(id)       ON DELETE SET NULL,
    INDEX idx_asset_tag      (asset_tag),
    INDEX idx_lifecycle      (lifecycle_status),
    INDEX idx_category       (category_id),
    INDEX idx_department     (department_id)
) ENGINE=InnoDB;

-- =========================================================
-- 7. ASSET CUSTOM FIELD VALUES
-- =========================================================
CREATE TABLE asset_custom_field_values (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    asset_id        INT          NOT NULL,
    custom_field_id INT          NOT NULL,
    value           VARCHAR(255) NULL,
    UNIQUE KEY uq_asset_field (asset_id, custom_field_id),
    FOREIGN KEY (asset_id)        REFERENCES assets(id)                ON DELETE CASCADE,
    FOREIGN KEY (custom_field_id) REFERENCES category_custom_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 8. ASSET DOCUMENTS
-- =========================================================
CREATE TABLE asset_documents (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    asset_id    INT          NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    file_type   ENUM('photo','document','invoice','warranty') DEFAULT 'photo',
    uploaded_by INT          NULL,
    uploaded_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id)    REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 9. ALLOCATIONS
-- =========================================================
CREATE TABLE allocations (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    asset_id                  INT      NOT NULL,
    allocated_to_user_id      INT      NULL,
    allocated_to_department_id INT     NULL,
    allocated_by              INT      NOT NULL,
    allocation_date           DATETIME DEFAULT CURRENT_TIMESTAMP,
    expected_return_date      DATE     NULL,
    actual_return_date        DATETIME NULL,
    condition_check_in_notes  TEXT     NULL,
    purpose                   VARCHAR(255) NULL,
    status ENUM('Active','Returned','Overdue') NOT NULL DEFAULT 'Active',
    FOREIGN KEY (asset_id)                   REFERENCES assets(id)       ON DELETE CASCADE,
    FOREIGN KEY (allocated_to_user_id)       REFERENCES users(id)        ON DELETE SET NULL,
    FOREIGN KEY (allocated_to_department_id) REFERENCES departments(id)  ON DELETE SET NULL,
    FOREIGN KEY (allocated_by)               REFERENCES users(id),
    INDEX idx_alloc_asset  (asset_id, status),
    INDEX idx_alloc_user   (allocated_to_user_id),
    INDEX idx_alloc_return (expected_return_date, status)
) ENGINE=InnoDB;

-- =========================================================
-- 10. TRANSFER REQUESTS
-- =========================================================
CREATE TABLE transfer_requests (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    asset_id                  INT      NOT NULL,
    current_allocation_id     INT      NOT NULL,
    requested_by              INT      NOT NULL,
    requested_to_user_id      INT      NULL,
    requested_to_department_id INT     NULL,
    reason                    TEXT     NULL,
    status ENUM('Requested','Approved','Rejected','Re-allocated') DEFAULT 'Requested',
    approved_by               INT      NULL,
    requested_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at               DATETIME NULL,
    FOREIGN KEY (asset_id)                    REFERENCES assets(id)      ON DELETE CASCADE,
    FOREIGN KEY (current_allocation_id)       REFERENCES allocations(id),
    FOREIGN KEY (requested_by)                REFERENCES users(id),
    FOREIGN KEY (requested_to_user_id)        REFERENCES users(id)       ON DELETE SET NULL,
    FOREIGN KEY (requested_to_department_id)  REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by)                 REFERENCES users(id)       ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 11. BOOKINGS
-- =========================================================
CREATE TABLE bookings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    asset_id      INT      NOT NULL,
    booked_by     INT      NOT NULL,
    department_id INT      NULL,
    title         VARCHAR(150) NULL,
    purpose       TEXT     NULL,
    start_time    DATETIME NOT NULL,
    end_time      DATETIME NOT NULL,
    status        ENUM('Upcoming','Ongoing','Completed','Cancelled') DEFAULT 'Upcoming',
    cancelled_by  INT      NULL,
    cancel_reason VARCHAR(255) NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id)      REFERENCES assets(id)       ON DELETE CASCADE,
    FOREIGN KEY (booked_by)     REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)  ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by)  REFERENCES users(id)        ON DELETE SET NULL,
    INDEX idx_booking_asset_time (asset_id, start_time, end_time),
    INDEX idx_booking_status     (status),
    INDEX idx_booking_user       (booked_by)
) ENGINE=InnoDB;

-- =========================================================
-- 12. MAINTENANCE REQUESTS
-- =========================================================
CREATE TABLE maintenance_requests (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    asset_id          INT      NOT NULL,
    raised_by         INT      NOT NULL,
    issue_description TEXT     NOT NULL,
    priority          ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
    photo_path        VARCHAR(255) NULL,
    status ENUM(
        'Pending',
        'Approved',
        'Rejected',
        'Technician Assigned',
        'In Progress',
        'Resolved'
    ) DEFAULT 'Pending',
    approved_by       INT      NULL,
    technician_name   VARCHAR(150) NULL,
    resolved_notes    TEXT     NULL,
    raised_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at       DATETIME NULL,
    resolved_at       DATETIME NULL,
    FOREIGN KEY (asset_id)    REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (raised_by)   REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)  ON DELETE SET NULL,
    INDEX idx_maint_asset  (asset_id),
    INDEX idx_maint_status (status),
    INDEX idx_maint_raised (raised_at)
) ENGINE=InnoDB;

-- =========================================================
-- 13. AUDIT CYCLES
-- =========================================================
CREATE TABLE audit_cycles (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    scope_department_id INT          NULL,
    scope_location      VARCHAR(150) NULL,
    start_date          DATE         NOT NULL,
    end_date            DATE         NOT NULL,
    status              ENUM('Planned','In Progress','Closed') DEFAULT 'Planned',
    created_by          INT          NOT NULL,
    closed_by           INT          NULL,
    closed_at           DATETIME     NULL,
    created_at          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scope_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)          REFERENCES users(id),
    FOREIGN KEY (closed_by)           REFERENCES users(id)       ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 14. AUDIT CYCLE AUDITORS (Many-to-Many)
-- =========================================================
CREATE TABLE audit_cycle_auditors (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    audit_cycle_id  INT NOT NULL,
    auditor_user_id INT NOT NULL,
    assigned_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cycle_auditor (audit_cycle_id, auditor_user_id),
    FOREIGN KEY (audit_cycle_id)  REFERENCES audit_cycles(id) ON DELETE CASCADE,
    FOREIGN KEY (auditor_user_id) REFERENCES users(id)        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 15. AUDIT ITEMS
-- =========================================================
CREATE TABLE audit_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    audit_cycle_id INT      NOT NULL,
    asset_id       INT      NOT NULL,
    verified_by    INT      NULL,
    result         ENUM('Pending','Verified','Missing','Damaged') DEFAULT 'Pending',
    remarks        TEXT     NULL,
    verified_at    DATETIME NULL,
    UNIQUE KEY uq_cycle_asset (audit_cycle_id, asset_id),
    FOREIGN KEY (audit_cycle_id) REFERENCES audit_cycles(id) ON DELETE CASCADE,
    FOREIGN KEY (asset_id)       REFERENCES assets(id)       ON DELETE CASCADE,
    FOREIGN KEY (verified_by)    REFERENCES users(id)        ON DELETE SET NULL,
    INDEX idx_audit_items_result (result)
) ENGINE=InnoDB;

-- =========================================================
-- 16. NOTIFICATIONS
-- =========================================================
CREATE TABLE notifications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    type            VARCHAR(80)  NOT NULL,
    message         VARCHAR(255) NOT NULL,
    reference_type  VARCHAR(50)  NULL,   -- 'allocation','booking','maintenance','audit'
    reference_id    INT          NULL,
    is_read         TINYINT(1)   DEFAULT 0,
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user   (user_id, is_read),
    INDEX idx_notif_created (created_at)
) ENGINE=InnoDB;

-- =========================================================
-- 17. ACTIVITY LOGS
-- =========================================================
CREATE TABLE activity_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NULL,      -- NULL = system/cron generated
    action       VARCHAR(255) NOT NULL,
    module       VARCHAR(50)  NOT NULL,  -- 'Asset','Booking','Maintenance','Audit','Org Setup','Auth'
    reference_id INT          NULL,
    ip_address   VARCHAR(45)  NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_log_user    (user_id),
    INDEX idx_log_module  (module),
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB;

-- =========================================================
-- Re-enable FK checks
-- =========================================================
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Verify creation
-- =========================================================
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ENGINE,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'assetflow'
ORDER BY TABLE_NAME;

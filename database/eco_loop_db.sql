CREATE DATABASE IF NOT EXISTS eco_loop_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE eco_loop_db;

DROP TABLE IF EXISTS admin_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reward_redemptions;
DROP TABLE IF EXISTS rewards;
DROP TABLE IF EXISTS points_transactions;
DROP TABLE IF EXISTS pickup_status_history;
DROP TABLE IF EXISTS pickup_assignments;
DROP TABLE IF EXISTS pickup_items;
DROP TABLE IF EXISTS pickup_verified_items;
DROP TABLE IF EXISTS pickup_verifications;
DROP TABLE IF EXISTS pickup_requests;
DROP TABLE IF EXISTS collection_points;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS collectors;
DROP TABLE IF EXISTS user_addresses;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    mobile_number VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin', 'collector') NOT NULL DEFAULT 'user',
    address TEXT,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    failed_login_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    is_primary TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_addresses_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE collectors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    full_name VARCHAR(150) NOT NULL,
    mobile_number VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_collectors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_collectors_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pickup_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    customer_name VARCHAR(150) DEFAULT NULL,
    contact_number VARCHAR(20) DEFAULT NULL,
    reference_number VARCHAR(50) NOT NULL UNIQUE,
    service_type ENUM('saver', 'priority') NOT NULL,
    service_fee DECIMAL(10,2) NOT NULL,
    base_fee DECIMAL(10,2) DEFAULT NULL,
    distance_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    distance_km DECIMAL(8,2) DEFAULT NULL,
    distance_method VARCHAR(100) DEFAULT NULL,
    status ENUM('Pending','Approved','Scheduled','Collector Assigned','On the Way','Collected','Completed','Cancelled','Rejected') NOT NULL DEFAULT 'Pending',
    estimated_quantity VARCHAR(100) DEFAULT NULL,
    description TEXT,
    pickup_address TEXT NOT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    collection_point_id INT DEFAULT NULL,
    preferred_date DATE DEFAULT NULL,
    preferred_time TIME DEFAULT NULL,
    hazardous TINYINT(1) NOT NULL DEFAULT 0,
    photo_path VARCHAR(255) DEFAULT NULL,
    request_token CHAR(64) DEFAULT NULL,
    cancellation_reason TEXT DEFAULT NULL,
    collected_weight_kg DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pickup_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pickup_requests_user (user_id),
    INDEX idx_pickup_requests_status (status),
    INDEX idx_pickup_requests_service (service_type),
    INDEX idx_pickup_requests_collection_point (collection_point_id),
    UNIQUE KEY uq_pickup_request_token (request_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pickup_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_request_id INT NOT NULL,
    item_category VARCHAR(100) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pickup_items_pickup FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    INDEX idx_pickup_items_pickup (pickup_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pickup_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_request_id INT NOT NULL UNIQUE,
    verified_by INT NOT NULL,
    total_points INT NOT NULL DEFAULT 0,
    phone_value_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pickup_verification_pickup FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_pickup_verification_admin FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pickup_verified_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    verification_id INT NOT NULL,
    item_category VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    points_per_piece INT NOT NULL DEFAULT 0,
    subtotal_points INT NOT NULL DEFAULT 0,
    monetary_value_per_piece DECIMAL(10,2) NOT NULL DEFAULT 0,
    condition_notes VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_verified_item_verification FOREIGN KEY (verification_id) REFERENCES pickup_verifications(id) ON DELETE CASCADE,
    INDEX idx_verified_item_verification (verification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pickup_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_request_id INT NOT NULL,
    collector_id INT NOT NULL,
    assigned_by INT NOT NULL,
    status ENUM('Assigned','On the Way','Collected','Completed') NOT NULL DEFAULT 'Assigned',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pickup_assignments_pickup FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_pickup_assignments_collector FOREIGN KEY (collector_id) REFERENCES collectors(id) ON DELETE CASCADE,
    CONSTRAINT fk_pickup_assignments_admin FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pickup_assignments_pickup (pickup_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pickup_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_request_id INT NOT NULL,
    previous_status VARCHAR(40) DEFAULT NULL,
    new_status VARCHAR(40) NOT NULL,
    changed_by INT NULL,
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_status_history_pickup FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE points_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL,
    transaction_type ENUM('earned','redeemed','adjustment') NOT NULL,
    related_pickup_id INT NULL,
    related_redemption_id INT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_points_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_points_pickup FOREIGN KEY (related_pickup_id) REFERENCES pickup_requests(id) ON DELETE SET NULL,
    CONSTRAINT fk_points_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_points_user (user_id),
    UNIQUE KEY uq_points_pickup_type (related_pickup_id, transaction_type),
    UNIQUE KEY uq_points_redemption_type (related_redemption_id, transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    points_required INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image_path VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reward_redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reward_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    points_used INT NOT NULL,
    status ENUM('Pending','Approved','Ready for Collection','Completed','Rejected') NOT NULL DEFAULT 'Pending',
    request_token CHAR(64) DEFAULT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_redemptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_redemptions_reward FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE,
    INDEX idx_redemptions_user (user_id),
    INDEX idx_redemptions_status (status),
    UNIQUE KEY uq_redemption_request_token (request_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE collection_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    operating_hours VARCHAR(255) NULL,
    accepted_categories TEXT NULL,
    contact_details VARCHAR(150) NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_collection_points_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_collection_points_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE pickup_requests ADD CONSTRAINT fk_pickup_collection_point FOREIGN KEY (collection_point_id) REFERENCES collection_points(id) ON DELETE SET NULL;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('pickup_saver_fee', '40'),
('pickup_priority_fee', '90'),
('points_per_completed_pickup', '50'),
('saver_rate_3_8', '12'), ('saver_rate_over_8', '20'),
('priority_rate_3_8', '25'), ('priority_rate_over_8', '40'),
('reward_earphones', '5'), ('reward_charger', '10'), ('reward_phone', '0'),
('reward_battery', '5'), ('reward_motherboard', '15'), ('reward_keyboard', '15'), ('reward_mouse', '10');

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    related_pickup_id INT NULL,
    related_redemption_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(200) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_logs_user FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the first administrator with `php scripts/admin-account.php create`.
-- Add real rewards and verified collectors through the administrator interface.
SELECT 'eco_loop_db initialized successfully. Create an administrator with the CLI setup command.' AS status;

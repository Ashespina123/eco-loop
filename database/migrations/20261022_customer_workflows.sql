USE eco_loop_db;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('pickup_saver_fee', '40'),
('pickup_priority_fee', '90'),
('points_per_completed_pickup', '50');

ALTER TABLE pickup_requests
    MODIFY status ENUM('Pending','Approved','Scheduled','Collector Assigned','On the Way','Collected','Completed','Cancelled','Rejected') NOT NULL DEFAULT 'Pending',
    ADD COLUMN request_token CHAR(64) NULL,
    ADD UNIQUE KEY uq_pickup_request_token (request_token);

ALTER TABLE reward_redemptions
    ADD COLUMN request_token CHAR(64) NULL,
    ADD UNIQUE KEY uq_redemption_request_token (request_token);

ALTER TABLE points_transactions
    ADD UNIQUE KEY uq_points_pickup_type (related_pickup_id, transaction_type),
    ADD UNIQUE KEY uq_points_redemption_type (related_redemption_id, transaction_type);

CREATE TABLE IF NOT EXISTS collection_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_collection_points_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_collection_points_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

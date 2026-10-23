USE eco_loop_db;

-- Safe additive migration: existing pickup and center rows are preserved.
ALTER TABLE pickup_requests
    ADD COLUMN collection_point_id INT NULL AFTER longitude,
    ADD COLUMN base_fee DECIMAL(10,2) NULL AFTER service_fee,
    ADD COLUMN distance_charge DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER base_fee,
    ADD COLUMN distance_km DECIMAL(8,2) NULL AFTER distance_charge,
    ADD COLUMN distance_method VARCHAR(100) NULL AFTER distance_km,
    ADD INDEX idx_pickup_requests_collection_point (collection_point_id);

ALTER TABLE collection_points
    ADD COLUMN operating_hours VARCHAR(255) NULL AFTER address,
    ADD COLUMN accepted_categories TEXT NULL AFTER operating_hours,
    ADD COLUMN contact_details VARCHAR(150) NULL AFTER accepted_categories;

ALTER TABLE pickup_requests
    ADD CONSTRAINT fk_pickup_collection_point FOREIGN KEY (collection_point_id) REFERENCES collection_points(id) ON DELETE SET NULL;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('saver_rate_3_8', '12'), ('saver_rate_over_8', '20'),
('priority_rate_3_8', '25'), ('priority_rate_over_8', '40'),
('reward_earphones', '5'), ('reward_charger', '10'), ('reward_battery', '5'),
('reward_phone', '0'), ('reward_motherboard', '15'), ('reward_keyboard', '15'), ('reward_mouse', '10');

CREATE TABLE IF NOT EXISTS pickup_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_request_id INT NOT NULL UNIQUE,
    verified_by INT NOT NULL,
    total_points INT NOT NULL DEFAULT 0,
    phone_value_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pickup_verification_pickup FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_pickup_verification_admin FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pickup_verified_items (
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

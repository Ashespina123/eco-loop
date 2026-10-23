USE eco_loop_db;

-- Keep the contact details confirmed for each request, even if a customer later
-- updates their profile. Nullable columns preserve all existing pickup rows.
ALTER TABLE pickup_requests
    ADD COLUMN customer_name VARCHAR(150) NULL AFTER user_id,
    ADD COLUMN contact_number VARCHAR(20) NULL AFTER customer_name;

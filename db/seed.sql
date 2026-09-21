-- ============================================================================
-- LIFELINK COMPREHENSIVE SAMPLE / SEED DATA
-- Course: Database Management Systems Laboratory (CSE 3522)
-- Note: Dates are calculated relative to CURDATE() so sample data is ALWAYS
--       fresh and valid whenever evaluated or demonstrated to examiners.
-- Passwords:
--   Admin     : admin@lifelink.org      / Admin@123
--   Donors    : john@gmail.com          / Password@123
--               sarah@gmail.com         / Password@123
--   Requesters: dr.miller@cityhospital.org / Password@123
-- ============================================================================

USE blood_bank_db;

-- 1. INSERT BLOOD GROUPS (ABO & Rh Master Catalog)
INSERT INTO blood_groups (blood_group_id, group_name, rh_factor, can_give_to, can_receive_from, description) VALUES
(1, 'A+',  '+', 'A+, AB+',         'A+, A-, O+, O-',         'Second most common blood group. High clinical demand.'),
(2, 'A-',  '-', 'A+, A-, AB+, AB-', 'A-, O-',                 'Rare. Compatible with all A and AB types.'),
(3, 'B+',  '+', 'B+, AB+',         'B+, B-, O+, O-',         'Common in Asian populations. Vital for trauma care.'),
(4, 'B-',  '-', 'B+, B-, AB+, AB-', 'B-, O-',                 'Rare blood type. High priority when low.'),
(5, 'AB+', '+', 'AB+',             'All Blood Groups',        'Universal recipient for red blood cells.'),
(6, 'AB-', '-', 'AB+, AB-',         'AB-, A-, B-, O-',        'Rarest blood group in worldwide distribution.'),
(7, 'O+',  '+', 'O+, A+, B+, AB+',  'O+, O-',                 'Most common blood group; frequently requested.'),
(8, 'O-',  '-', 'All Blood Groups', 'O-',                     'Universal red cell donor. Crucial in emergency rooms.');

-- 2. INSERT USERS (Password hashes generated using PHP password_hash PASSWORD_BCRYPT)
-- Hash for 'Admin@123': $2y$10$qNkuuMI.fiIsjUGogreBd.7uKncaD..eyFKmZXfWoT7mLOSDaxUbe
-- Hash for 'Password@123': $2y$10$QmXx1HEEmsML0Q2mlhzdf.2TQRvZxEypNFgLBZ7r8WChLiCebneqS
INSERT INTO users (user_id, full_name, email, password_hash, phone, role) VALUES
(1, 'System Administrator', 'admin@lifelink.org', '$2y$10$qNkuuMI.fiIsjUGogreBd.7uKncaD..eyFKmZXfWoT7mLOSDaxUbe', '01711000001', 'ADMIN'),
(2, 'John Doe',             'john@gmail.com',     '$2y$10$QmXx1HEEmsML0Q2mlhzdf.2TQRvZxEypNFgLBZ7r8WChLiCebneqS', '01711000002', 'DONOR'),
(3, 'Sarah Jenkins',        'sarah@gmail.com',    '$2y$10$QmXx1HEEmsML0Q2mlhzdf.2TQRvZxEypNFgLBZ7r8WChLiCebneqS', '01711000003', 'DONOR'),
(4, 'Michael Brown',        'michael@gmail.com',  '$2y$10$QmXx1HEEmsML0Q2mlhzdf.2TQRvZxEypNFgLBZ7r8WChLiCebneqS', '01711000004', 'DONOR'),
(5, 'Emily Chen',           'emily@gmail.com',    '$2y$10$QmXx1HEEmsML0Q2mlhzdf.2TQRvZxEypNFgLBZ7r8WChLiCebneqS', '01711000005', 'DONOR'),
(6, 'Dr. Robert Miller',    'dr.miller@cityhospital.org', '$2y$10$QmXx1HEEmsML0Q2mlhzdf.2TQRvZxEypNFgLBZ7r8WChLiCebneqS', '01711000006', 'REQUESTER'),
(7, 'Dr. Lisa Ray',         'lisa@metrotrauma.org',       '$2y$10$QmXx1HEEmsML0Q2mlhzdf.2TQRvZxEypNFgLBZ7r8WChLiCebneqS', '01711000007', 'REQUESTER');

-- 3. INSERT DONORS
INSERT INTO donors (donor_id, user_id, blood_group_id, date_of_birth, gender, last_donation_date, address, city, is_eligible) VALUES
(1, 2, 7, '1995-04-12', 'MALE',   DATE_SUB(CURDATE(), INTERVAL 45 DAY),  'House 12, Road 4, Dhanmondi', 'Dhaka', TRUE),
(2, 3, 2, '1998-08-23', 'FEMALE', DATE_SUB(CURDATE(), INTERVAL 110 DAY), 'Sector 7, Uttara',           'Dhaka', TRUE),
(3, 4, 3, '1992-11-05', 'MALE',   DATE_SUB(CURDATE(), INTERVAL 20 DAY),  'GEC Circle',                  'Chittagong', TRUE),
(4, 5, 8, '2001-02-17', 'FEMALE', NULL,                                  'Zindabazar',                  'Sylhet', TRUE);
-- Note: Emily Chen (donor_id=4) has last_donation_date = NULL and no donation records.
-- Perfect demonstration of LEFT JOIN (showing registered donors who haven't yet donated).

-- 4. INSERT DONATION SESSIONS
INSERT INTO donations (donation_id, donor_id, blood_group_id, donation_date, units_donated, blood_pressure, hemoglobin, remarks) VALUES
(1, 1, 7, DATE_SUB(CURDATE(), INTERVAL 45 DAY),  1, '120/80', 14.2, 'Routine blood drive donation. Healthy donor.'),
(2, 2, 2, DATE_SUB(CURDATE(), INTERVAL 110 DAY), 1, '118/76', 13.0, 'Red Cross university camp donation.'),
(3, 3, 3, DATE_SUB(CURDATE(), INTERVAL 20 DAY),  1, '122/82', 15.1, 'Voluntary walk-in donation.');

-- 5. INSERT BLOOD INVENTORY (Physical serialized bags with dynamic shelf-life)
-- Bag statuses: 'AVAILABLE', 'RESERVED', 'ISSUED', 'DISCARDED'
INSERT INTO blood_inventory (inventory_id, bag_code, blood_group_id, donation_id, collection_date, expiry_date, status, storage_location) VALUES
-- A+ Units (Group 1)
(1,  'BAG-2026-A101', 1, NULL, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'AVAILABLE', 'Cold Vault 1 / Shelf A1'),
(2,  'BAG-2026-A102', 1, NULL, DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'AVAILABLE', 'Cold Vault 1 / Shelf A1'),
(3,  'BAG-2026-A103', 1, NULL, DATE_SUB(CURDATE(), INTERVAL 33 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY),  'AVAILABLE', 'Cold Vault 1 / Shelf A2'), -- EXPIRING SOON!
(4,  'BAG-2026-A104', 1, NULL, DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'AVAILABLE', 'Cold Vault 1 / Shelf A2'),

-- A- Units (Group 2) - Low stock
(5,  'BAG-2026-A201', 2, 2,    DATE_SUB(CURDATE(), INTERVAL 31 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY),  'AVAILABLE', 'Cold Vault 1 / Shelf B1'), -- EXPIRING SOON!
(6,  'BAG-2026-A202', 2, NULL, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'AVAILABLE', 'Cold Vault 1 / Shelf B1'),

-- B+ Units (Group 3) - Healthy stock
(7,  'BAG-2026-B101', 3, 3,    DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'AVAILABLE', 'Cold Vault 2 / Shelf C1'),
(8,  'BAG-2026-B102', 3, NULL, DATE_SUB(CURDATE(), INTERVAL 8 DAY),  DATE_ADD(CURDATE(), INTERVAL 27 DAY), 'AVAILABLE', 'Cold Vault 2 / Shelf C1'),
(9,  'BAG-2026-B103', 3, NULL, DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_ADD(CURDATE(), INTERVAL 23 DAY), 'AVAILABLE', 'Cold Vault 2 / Shelf C2'),
(10, 'BAG-2026-B104', 3, NULL, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  DATE_ADD(CURDATE(), INTERVAL 33 DAY), 'AVAILABLE', 'Cold Vault 2 / Shelf C2'),
(11, 'BAG-2026-B105', 3, NULL, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(), INTERVAL 21 DAY), 'AVAILABLE', 'Cold Vault 2 / Shelf C3'),

-- B- Units (Group 4) - Critical stock (Only 1 unit)
(12, 'BAG-2026-B201', 4, NULL, DATE_SUB(CURDATE(), INTERVAL 18 DAY), DATE_ADD(CURDATE(), INTERVAL 17 DAY), 'AVAILABLE', 'Cold Vault 2 / Shelf D1'),

-- AB+ Units (Group 5) - Low stock (2 units)
(13, 'BAG-2026-AB101', 5, NULL, DATE_SUB(CURDATE(), INTERVAL 6 DAY),  DATE_ADD(CURDATE(), INTERVAL 29 DAY), 'AVAILABLE', 'Cold Vault 3 / Shelf E1'),
(14, 'BAG-2026-AB102', 5, NULL, DATE_SUB(CURDATE(), INTERVAL 16 DAY), DATE_ADD(CURDATE(), INTERVAL 19 DAY), 'AVAILABLE', 'Cold Vault 3 / Shelf E1'),

-- AB- Units (Group 6) - Zero stock! (Critical) - No available bags entered intentionally

-- O+ Units (Group 7) - High demand & Healthy stock
(15, 'BAG-2026-O101', 7, 1,    DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY),  'AVAILABLE', 'Cold Vault 4 / Shelf F1'), -- EXPIRING TOMORROW!
(16, 'BAG-2026-O102', 7, NULL, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'AVAILABLE', 'Cold Vault 4 / Shelf F1'),
(17, 'BAG-2026-O103', 7, NULL, DATE_SUB(CURDATE(), INTERVAL 11 DAY), DATE_ADD(CURDATE(), INTERVAL 24 DAY), 'AVAILABLE', 'Cold Vault 4 / Shelf F2'),
(18, 'BAG-2026-O104', 7, NULL, DATE_SUB(CURDATE(), INTERVAL 4 DAY),  DATE_ADD(CURDATE(), INTERVAL 31 DAY), 'AVAILABLE', 'Cold Vault 4 / Shelf F2'),
(19, 'BAG-2026-O105', 7, NULL, DATE_SUB(CURDATE(), INTERVAL 19 DAY), DATE_ADD(CURDATE(), INTERVAL 16 DAY), 'AVAILABLE', 'Cold Vault 4 / Shelf F3'),
(20, 'BAG-2026-O106', 7, NULL, DATE_SUB(CURDATE(), INTERVAL 22 DAY), DATE_ADD(CURDATE(), INTERVAL 13 DAY), 'AVAILABLE', 'Cold Vault 4 / Shelf F3'),

-- O- Units (Group 8) - Critical universal stock (2 units)
(21, 'BAG-2026-O201', 8, NULL, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'AVAILABLE', 'Cold Vault 4 / Shelf G1'),
(22, 'BAG-2026-O202', 8, NULL, DATE_SUB(CURDATE(), INTERVAL 9 DAY),  DATE_ADD(CURDATE(), INTERVAL 26 DAY), 'AVAILABLE', 'Cold Vault 4 / Shelf G1'),

-- Units already ISSUED (for historical demonstration and blood journey)
(23, 'BAG-2026-A199', 1, NULL, DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY),  'ISSUED',    'Cold Vault 1 / Shelf A1'),
(24, 'BAG-2026-A198', 1, NULL, DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY),  'ISSUED',    'Cold Vault 1 / Shelf A1'),

-- Expired Unit (for Expiry management / Discard feature)
(25, 'BAG-2026-EXP01', 3, NULL, DATE_SUB(CURDATE(), INTERVAL 50 DAY), DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'AVAILABLE', 'Cold Vault 2 / Shelf C9');

-- 6. INSERT BLOOD REQUESTS
INSERT INTO blood_requests (request_id, user_id, patient_name, hospital_name, blood_group_id, units_requested, urgency, required_date, status, reason) VALUES
-- Emergency Request (Immediate attention in triage)
(1, 6, 'Kamal Hossain',    'Dhaka Medical College Emergency Trauma Unit', 8, 2, 'EMERGENCY', CURDATE(), 'PENDING', 'Severe hemorrhaging from highway accident. Immediate transfusion needed.'),

-- Urgent Request
(2, 7, 'Rehana Akter',     'Square Hospital Coronary Care Unit',          3, 1, 'URGENT',    DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'PENDING', 'Emergency open-heart bypass surgery scheduled tomorrow morning.'),

-- Normal Request (Fulfilled - used to demo Issuance History)
(3, 6, 'Anisur Rahman',    'City General Hospital Ward 4',                1, 2, 'NORMAL',    DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'FULFILLED', 'Chronic anemia patient red cell support.');

-- 7. INSERT BLOOD ISSUANCE (Links fulfilled request 3 to issued bags 23 and 24)
INSERT INTO blood_issuances (issuance_id, request_id, inventory_id, issued_by_user_id, issuance_date, remarks) VALUES
(1, 3, 23, 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 DAY), 'Units crossmatched and issued via cold transport container #4.'),
(2, 3, 24, 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 DAY), 'Crossmatched unit 2. Dispensed to Ward 4 Nurse in charge.');

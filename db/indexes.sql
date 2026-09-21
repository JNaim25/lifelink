-- ============================================================================
-- LIFELINK MANUAL DATABASE INDEXES & OPTIMIZATION
-- Course: Database Management Systems Laboratory (CSE 3522)
-- Demonstrates: CREATE INDEX, Query Plan Optimization, B-Tree Index Mechanics
-- ============================================================================

USE blood_bank_db;

-- ============================================================================
-- INDEX 1: idx_inventory_status_expiry
-- Columns: (blood_group_id, status, expiry_date)
-- Table: blood_inventory
-- Rationale:
--   The Smart Blood Match, Public Search, and Blood Issuance transactions 
--   frequently execute queries of the form:
--     SELECT * FROM blood_inventory 
--     WHERE blood_group_id = ? AND status = 'AVAILABLE' AND expiry_date >= CURDATE()
--     ORDER BY expiry_date ASC;
--
-- Performance Benefit:
--   Without an index, MySQL must execute a full table scan O(N) over thousands
--   of historical bags. With this composite B-Tree index:
--   1. Column 1 (blood_group_id) rapidly narrows down to the requested type.
--   2. Column 2 (status) immediately prunes issued and discarded bags.
--   3. Column 3 (expiry_date) satisfies the date range comparison and provides
--      an already-sorted order for FIFO dispatching without an extra 'filesort'.
-- ============================================================================
CREATE INDEX idx_inventory_status_expiry 
ON blood_inventory (blood_group_id, status, expiry_date);

-- ============================================================================
-- INDEX 2: idx_requests_urgency_status
-- Columns: (urgency, status, request_date)
-- Table: blood_requests
-- Rationale:
--   The Emergency Queue and Admin Triage polling runs high-frequency queries:
--     SELECT * FROM blood_requests 
--     WHERE status = 'PENDING' AND urgency = 'EMERGENCY'
--     ORDER BY request_date ASC;
--
-- Performance Benefit:
--   Ensures life-saving emergency requests are retrieved in sub-millisecond
--   time without scanning low-priority historical fulfilled requests.
-- ============================================================================
CREATE INDEX idx_requests_urgency_status 
ON blood_requests (urgency, status, request_date);

-- ============================================================================
-- INDEX 3: idx_donations_donor_date
-- Columns: (donor_id, donation_date)
-- Table: donations
-- Rationale:
--   Validating donor 90-day cooldown requires checking:
--     SELECT MAX(donation_date) FROM donations WHERE donor_id = ?;
-- ============================================================================
CREATE INDEX idx_donations_donor_date 
ON donations (donor_id, donation_date);

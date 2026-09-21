-- ============================================================================
-- LIFELINK DBMS LABORATORY QUERIES SHOWCASE & VIVA CHEAT-SHEET
-- Course: Database Management Systems Laboratory (CSE 3522)
-- Database: blood_bank_db
-- ============================================================================

USE blood_bank_db;

-- ============================================================================
-- 1. DML OPERATIONS (Data Manipulation Language)
-- ============================================================================

-- 1.1 SELECT: Retrieve all available blood units with blood group details
SELECT 
    bi.bag_code, 
    bg.group_name, 
    bi.collection_date, 
    bi.expiry_date, 
    bi.storage_location
FROM blood_inventory bi
JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
WHERE bi.status = 'AVAILABLE' AND bi.expiry_date >= CURDATE()
ORDER BY bi.expiry_date ASC;

-- 1.2 INSERT: Register a new donation session
INSERT INTO donations (donor_id, blood_group_id, donation_date, units_donated, blood_pressure, hemoglobin, remarks)
VALUES (1, 7, CURDATE(), 1, '120/80', 14.5, 'Blood drive walk-in donation');

-- 1.3 UPDATE: Update requester contact phone
UPDATE users 
SET phone = '01799999999' 
WHERE user_id = 6;

-- 1.4 DELETE: Cancel a pending blood request (clean cancellation)
DELETE FROM blood_requests 
WHERE request_id = 999 AND status = 'PENDING';


-- ============================================================================
-- 2. AGGREGATION, GROUP BY, AND HAVING
-- ============================================================================

-- 2.1 Aggregates: COUNT, SUM, AVG, MIN, MAX across blood inventory and donations
SELECT 
    COUNT(bi.inventory_id) AS total_inventory_bags,
    SUM(CASE WHEN bi.status = 'AVAILABLE' THEN 1 ELSE 0 END) AS total_available_bags,
    MIN(bi.expiry_date) AS earliest_expiring_bag,
    MAX(bi.collection_date) AS freshest_collected_bag
FROM blood_inventory bi;

-- Average hemoglobin of donors who donated blood
SELECT 
    ROUND(AVG(hemoglobin), 2) AS avg_donor_hemoglobin,
    MIN(hemoglobin) AS min_hemoglobin,
    MAX(hemoglobin) AS max_hemoglobin
FROM donations;

-- 2.2 GROUP BY: Count available units per blood group
SELECT 
    bg.group_name,
    COUNT(bi.inventory_id) AS total_bags_stored,
    SUM(CASE WHEN bi.status = 'AVAILABLE' AND bi.expiry_date >= CURDATE() THEN 1 ELSE 0 END) AS available_units
FROM blood_groups bg
LEFT JOIN blood_inventory bi ON bg.blood_group_id = bi.blood_group_id
GROUP BY bg.blood_group_id, bg.group_name;

-- 2.3 GROUP BY with HAVING: Detect blood groups with CRITICAL stock (< 3 units)
SELECT 
    bg.group_name,
    COUNT(bi.inventory_id) AS available_count
FROM blood_groups bg
LEFT JOIN blood_inventory bi ON bg.blood_group_id = bi.blood_group_id 
    AND bi.status = 'AVAILABLE' 
    AND bi.expiry_date >= CURDATE()
GROUP BY bg.blood_group_id, bg.group_name
HAVING available_count < 3
ORDER BY available_count ASC;


-- ============================================================================
-- 3. JOIN OPERATIONS
-- ============================================================================

-- 3.1 INNER JOIN: Join Requests, Groups, and Issuances to track dispensed blood
SELECT 
    br.request_id,
    br.patient_name,
    br.hospital_name,
    bg.group_name AS blood_group,
    bi.bag_code AS dispensed_bag_barcode,
    iss.issuance_date,
    u.full_name AS issued_by_officer
FROM blood_issuances iss
INNER JOIN blood_requests br ON iss.request_id = br.request_id
INNER JOIN blood_inventory bi ON iss.inventory_id = bi.inventory_id
INNER JOIN blood_groups bg ON br.blood_group_id = bg.blood_group_id
INNER JOIN users u ON iss.issued_by_user_id = u.user_id;

-- 3.2 LEFT JOIN: List all registered donors and their donations (if any)
-- Explains to examiners: Shows registered donors even if they have NOT yet donated.
SELECT 
    u.full_name AS donor_name,
    u.email,
    bg.group_name,
    d.city,
    d.last_donation_date,
    COUNT(don.donation_id) AS total_completed_donations
FROM donors d
INNER JOIN users u ON d.user_id = u.user_id
INNER JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
LEFT JOIN donations don ON d.donor_id = don.donor_id
GROUP BY d.donor_id, u.full_name, u.email, bg.group_name, d.city, d.last_donation_date;


-- ============================================================================
-- 4. SUBQUERIES (Nested and Correlated)
-- ============================================================================

-- 4.1 Nested Subquery: Find blood groups that currently have unfulfilled EMERGENCY requests
SELECT group_name, can_receive_from
FROM blood_groups
WHERE blood_group_id IN (
    SELECT DISTINCT blood_group_id 
    FROM blood_requests 
    WHERE urgency = 'EMERGENCY' AND status = 'PENDING'
);

-- 4.2 Correlated Subquery: Find donors whose total donations are greater than the
-- overall average donations per donor across the entire system.
SELECT 
    u.full_name,
    bg.group_name,
    (SELECT COUNT(*) FROM donations don WHERE don.donor_id = d.donor_id) AS donor_donations
FROM donors d
JOIN users u ON d.user_id = u.user_id
JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
WHERE (SELECT COUNT(*) FROM donations don WHERE don.donor_id = d.donor_id) >= (
    SELECT AVG(donation_count) 
    FROM (
        SELECT COUNT(*) AS donation_count 
        FROM donations 
        GROUP BY donor_id
    ) AS avg_table
);


-- ============================================================================
-- 5. REUSABLE MYSQL VIEW
-- ============================================================================

-- Query the pre-compiled view directly from the application
SELECT 
    group_name, 
    available_units, 
    expiring_soon_units, 
    earliest_expiry, 
    stock_status
FROM view_blood_availability
ORDER BY available_units ASC;


-- ============================================================================
-- 6. TRANSACTION IMPLEMENTATION (Issuance Engine)
-- Demonstrates: START TRANSACTION, FOR UPDATE lock, Conditional COMMIT / ROLLBACK
-- ============================================================================

DELIMITER //

DROP PROCEDURE IF EXISTS sp_issue_blood_unit //

CREATE PROCEDURE sp_issue_blood_unit(
    IN p_request_id INT,
    IN p_admin_user_id INT,
    OUT p_status_message VARCHAR(100)
)
proc_label: BEGIN
    DECLARE v_req_blood_group INT;
    DECLARE v_units_needed INT;
    DECLARE v_available_bag_id INT;
    DECLARE v_req_status VARCHAR(20);

    -- 1. Start the transaction
    START TRANSACTION;

    -- 2. Inspect request
    SELECT blood_group_id, units_requested, status 
    INTO v_req_blood_group, v_units_needed, v_req_status
    FROM blood_requests 
    WHERE request_id = p_request_id;

    IF v_req_status != 'PENDING' AND v_req_status != 'APPROVED' THEN
        SET p_status_message = 'ERROR: Request is not in pending/approved state.';
        ROLLBACK;
        LEAVE proc_label;
    END IF;

    -- 3. Find earliest expiring available bag and lock it (Pessimistic concurrency)
    SELECT inventory_id 
    INTO v_available_bag_id
    FROM blood_inventory
    WHERE blood_group_id = v_req_blood_group 
      AND status = 'AVAILABLE' 
      AND expiry_date >= CURDATE()
    ORDER BY expiry_date ASC 
    LIMIT 1
    FOR UPDATE;

    -- 4. Check if bag was found
    IF v_available_bag_id IS NULL THEN
        SET p_status_message = 'ROLLBACK: Insufficient inventory stock!';
        ROLLBACK;
        LEAVE proc_label;
    END IF;

    -- 5. Create issuance log
    INSERT INTO blood_issuances (request_id, inventory_id, issued_by_user_id, issuance_date, remarks)
    VALUES (p_request_id, v_available_bag_id, p_admin_user_id, NOW(), 'Automated issuance transaction');

    -- 6. Update inventory status
    UPDATE blood_inventory 
    SET status = 'ISSUED' 
    WHERE inventory_id = v_available_bag_id;

    -- 7. Update request status to FULFILLED
    UPDATE blood_requests 
    SET status = 'FULFILLED' 
    WHERE request_id = p_request_id;

    -- 8. Commit everything atomically
    COMMIT;
    SET p_status_message = 'SUCCESS: Unit successfully issued via transaction!';
END //

DELIMITER ;


-- ============================================================================
-- 7. INDEX PERFORMANCE VERIFICATION
-- Demonstrates: EXPLAIN plan showing index usage
-- ============================================================================

-- Inspect query execution plan to prove index usage to examiners
EXPLAIN 
SELECT inventory_id, bag_code, expiry_date 
FROM blood_inventory 
WHERE blood_group_id = 7 
  AND status = 'AVAILABLE' 
  AND expiry_date >= CURDATE();

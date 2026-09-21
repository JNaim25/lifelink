-- ============================================================================
-- LIFELINK REUSABLE DATABASE VIEWS
-- Course: Database Management Systems Laboratory (CSE 3522)
-- Demonstrates: SQL Views, Aggregation (COUNT, SUM, MIN), CASE expressions
-- ============================================================================

USE blood_bank_db;

DROP VIEW IF EXISTS view_blood_availability;
DROP VIEW IF EXISTS view_emergency_queue;

-- ============================================================================
-- VIEW 1: view_blood_availability
-- Computes real-time inventory levels, cold-chain expiry warning metrics,
-- and categorized stock health indicators for each blood group.
-- Application Usage: Polled by Homepage Radar and Admin Dashboard.
-- ============================================================================
CREATE VIEW view_blood_availability AS
SELECT 
    bg.blood_group_id,
    bg.group_name,
    bg.rh_factor,
    bg.can_give_to,
    bg.can_receive_from,
    COUNT(bi.inventory_id) AS total_tracked_bags,
    
    -- Count only viable, non-expired bags currently available for issuance
    COALESCE(SUM(CASE 
        WHEN bi.status = 'AVAILABLE' AND bi.expiry_date >= CURDATE() THEN 1 
        ELSE 0 
    END), 0) AS available_units,
    
    -- Units that will expire within the next 7 days (Cold-Chain Alert)
    COALESCE(SUM(CASE 
        WHEN bi.status = 'AVAILABLE' 
             AND bi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 
        ELSE 0 
    END), 0) AS expiring_soon_units,
    
    -- Expired units in storage that must be quarantined/discarded
    COALESCE(SUM(CASE 
        WHEN bi.expiry_date < CURDATE() AND bi.status != 'ISSUED' THEN 1 
        ELSE 0 
    END), 0) AS expired_units,
    
    -- Earliest expiration date among currently available units (FIFO dispatching)
    MIN(CASE 
        WHEN bi.status = 'AVAILABLE' AND bi.expiry_date >= CURDATE() THEN bi.expiry_date 
        ELSE NULL 
    END) AS earliest_expiry,
    
    -- Status categorization for UI badges
    CASE 
        WHEN COALESCE(SUM(CASE WHEN bi.status = 'AVAILABLE' AND bi.expiry_date >= CURDATE() THEN 1 ELSE 0 END), 0) = 0 THEN 'CRITICAL'
        WHEN COALESCE(SUM(CASE WHEN bi.status = 'AVAILABLE' AND bi.expiry_date >= CURDATE() THEN 1 ELSE 0 END), 0) < 5 THEN 'LOW'
        ELSE 'AVAILABLE'
    END AS stock_status

FROM blood_groups bg
LEFT JOIN blood_inventory bi ON bg.blood_group_id = bi.blood_group_id
GROUP BY 
    bg.blood_group_id, 
    bg.group_name, 
    bg.rh_factor, 
    bg.can_give_to, 
    bg.can_receive_from;

-- ============================================================================
-- VIEW 2: view_emergency_queue
-- Pre-filters high-priority pending blood requisitions joined with contact info.
-- Application Usage: Admin Emergency Triage Header and Live Alert Banner.
-- ============================================================================
CREATE VIEW view_emergency_queue AS
SELECT 
    br.request_id,
    br.patient_name,
    br.hospital_name,
    bg.group_name AS blood_group,
    br.units_requested,
    br.urgency,
    br.required_date,
    br.status,
    br.request_date,
    u.full_name AS requester_name,
    u.phone AS requester_phone,
    u.email AS requester_email
FROM blood_requests br
INNER JOIN blood_groups bg ON br.blood_group_id = bg.blood_group_id
INNER JOIN users u ON br.user_id = u.user_id
WHERE br.status = 'PENDING' AND br.urgency IN ('EMERGENCY', 'URGENT');

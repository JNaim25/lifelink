<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Request Service
 * Requisitions management, emergency triage queue, and demand-vs-supply matrix.
 */

require_once __DIR__ . '/../config/database.php';

function createBloodRequest($userId, $patientName, $hospitalName, $bloodGroupId, $unitsRequested, $urgency, $requiredDate, $reason = null) {
    return executeDML("
        INSERT INTO blood_requests 
        (user_id, patient_name, hospital_name, blood_group_id, units_requested, urgency, required_date, status, reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)
    ", [$userId, $patientName, $hospitalName, $bloodGroupId, $unitsRequested, $urgency, $requiredDate, $reason]);
}

function getRequests($status = null, $urgency = null, $userId = null, $limit = null) {
    $conditions = [];
    $params = [];
    
    if ($status) {
        $conditions[] = "br.status = ?";
        $params[] = $status;
    }
    if ($urgency) {
        $conditions[] = "br.urgency = ?";
        $params[] = $urgency;
    }
    if ($userId) {
        $conditions[] = "br.user_id = ?";
        $params[] = $userId;
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $limitClause = $limit ? "LIMIT " . intval($limit) : "";
    
    $sql = "
        SELECT 
            br.request_id,
            br.user_id,
            br.patient_name,
            br.hospital_name,
            br.blood_group_id,
            bg.group_name AS blood_group,
            br.units_requested,
            br.urgency,
            br.required_date,
            br.status,
            br.reason,
            br.request_date,
            u.full_name AS requester_name,
            u.phone AS requester_phone,
            u.email AS requester_email
        FROM blood_requests br
        INNER JOIN blood_groups bg ON br.blood_group_id = bg.blood_group_id
        INNER JOIN users u ON br.user_id = u.user_id
        $whereClause
        ORDER BY 
            CASE br.urgency 
                WHEN 'EMERGENCY' THEN 1 
                WHEN 'URGENT' THEN 2 
                ELSE 3 
            END ASC,
            br.request_date DESC
        $limitClause
    ";
    
    return queryAll($sql, $params);
}

function getRequestById($requestId) {
    return queryOne("
        SELECT 
            br.request_id,
            br.user_id,
            br.patient_name,
            br.hospital_name,
            br.blood_group_id,
            bg.group_name AS blood_group,
            br.units_requested,
            br.urgency,
            br.required_date,
            br.status,
            br.reason,
            br.request_date,
            u.full_name AS requester_name,
            u.phone AS requester_phone,
            u.email AS requester_email
        FROM blood_requests br
        INNER JOIN blood_groups bg ON br.blood_group_id = bg.blood_group_id
        INNER JOIN users u ON br.user_id = u.user_id
        WHERE br.request_id = ?
    ", [$requestId]);
}

/**
 * Feature 2: Emergency Triage Queue (View Query)
 */
function getEmergencyQueue() {
    return queryAll("SELECT * FROM view_emergency_queue ORDER BY urgency ASC, request_date ASC");
}

function updateRequestStatus($requestId, $newStatus) {
    return executeDML("
        UPDATE blood_requests 
        SET status = ? 
        WHERE request_id = ?
    ", [$newStatus, $requestId]);
}

/**
 * Demonstrates DML DELETE for clean cancellation of pending requests.
 */
function cancelPendingRequest($requestId, $userId, $isAdmin = false) {
    if ($isAdmin) {
        return executeDML("DELETE FROM blood_requests WHERE request_id = ? AND status = 'PENDING'", [$requestId]);
    } else {
        return executeDML("DELETE FROM blood_requests WHERE request_id = ? AND user_id = ? AND status = 'PENDING'", [$requestId, $userId]);
    }
}

/**
 * Feature 9: Blood Demand vs. Available Supply Matrix
 * Uses correlated subqueries to compute stock, pending demand, and balance difference.
 */
function getDemandVsSupplyMatrix() {
    $sql = "
        SELECT 
            bg.blood_group_id,
            bg.group_name,
            
            -- Subquery 1: Available viable bags
            COALESCE((
                SELECT COUNT(*) 
                FROM blood_inventory bi 
                WHERE bi.blood_group_id = bg.blood_group_id 
                  AND bi.status = 'AVAILABLE' 
                  AND bi.expiry_date >= CURDATE()
            ), 0) AS available_units,
            
            -- Subquery 2: Pending requisition demand
            COALESCE((
                SELECT SUM(br.units_requested) 
                FROM blood_requests br 
                WHERE br.blood_group_id = bg.blood_group_id 
                  AND br.status IN ('PENDING', 'APPROVED')
            ), 0) AS requested_units,
            
            -- Subquery 3: Emergency active demand
            COALESCE((
                SELECT SUM(br.units_requested) 
                FROM blood_requests br 
                WHERE br.blood_group_id = bg.blood_group_id 
                  AND br.status = 'PENDING'
                  AND br.urgency = 'EMERGENCY'
            ), 0) AS emergency_demand

        FROM blood_groups bg
        ORDER BY bg.blood_group_id ASC
    ";
    
    $rows = queryAll($sql);
    foreach ($rows as &$row) {
        $diff = intval($row['available_units']) - intval($row['requested_units']);
        $row['net_difference'] = $diff;
        
        if ($row['available_units'] == 0 || $diff < -3 || $row['emergency_demand'] > $row['available_units']) {
            $row['balance_status'] = 'CRITICAL DEFICIT';
            $row['badge_class'] = 'bg-danger';
        } elseif ($diff < 0) {
            $row['balance_status'] = 'MODERATE SHORTAGE';
            $row['badge_class'] = 'bg-warning text-dark';
        } elseif ($diff == 0) {
            $row['balance_status'] = 'TIGHT SUPPLY';
            $row['badge_class'] = 'bg-info text-dark';
        } else {
            $row['balance_status'] = 'HEALTHY SURPLUS';
            $row['badge_class'] = 'bg-success';
        }
    }
    return $rows;
}

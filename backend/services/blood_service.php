<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Blood Service
 * Master catalog, live radar view queries, and Smart Blood Match algorithms.
 */

require_once __DIR__ . '/../config/database.php';

function getAllBloodGroups() {
    return queryAll("SELECT * FROM blood_groups ORDER BY blood_group_id ASC");
}

function getBloodGroupById($bloodGroupId) {
    return queryOne("SELECT * FROM blood_groups WHERE blood_group_id = ?", [$bloodGroupId]);
}

/**
 * Query the reusable MySQL VIEW `view_blood_availability`.
 * Demonstrates the VIEW requirement in the application.
 */
function getBloodAvailabilityRadar() {
    return queryAll("SELECT * FROM view_blood_availability ORDER BY blood_group_id ASC");
}

/**
 * Feature 1: Smart Blood Match Algorithm
 * 1. Checks exact match viable units.
 * 2. Identifies compatible donor groups based on `can_receive_from`.
 * 3. Evaluates available stock for both exact and compatible alternatives.
 */
function smartBloodMatch($bloodGroupId, $unitsNeeded = 1) {
    $targetGroup = getBloodGroupById($bloodGroupId);
    if (!$targetGroup) {
        return null;
    }
    
    // 1. Exact match available inventory
    $exactUnits = queryAll("
        SELECT bi.inventory_id, bi.bag_code, bg.group_name, bi.collection_date, 
               bi.expiry_date, bi.storage_location,
               DATEDIFF(bi.expiry_date, CURDATE()) AS days_to_expiry
        FROM blood_inventory bi
        JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
        WHERE bi.blood_group_id = ? 
          AND bi.status = 'AVAILABLE' 
          AND bi.expiry_date >= CURDATE()
        ORDER BY bi.expiry_date ASC
    ", [$bloodGroupId]);
    
    // 2. Extract compatible group names
    $canReceiveStr = $targetGroup['can_receive_from'];
    $compatibleUnits = [];
    
    if (strpos($canReceiveStr, 'All Blood Groups') !== false) {
        $compatibleUnits = queryAll("
            SELECT bi.inventory_id, bi.bag_code, bg.group_name, bi.collection_date, 
                   bi.expiry_date, bi.storage_location,
                   DATEDIFF(bi.expiry_date, CURDATE()) AS days_to_expiry
            FROM blood_inventory bi
            JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
            WHERE bi.blood_group_id != ?
              AND bi.status = 'AVAILABLE' 
              AND bi.expiry_date >= CURDATE()
            ORDER BY bi.expiry_date ASC
        ", [$bloodGroupId]);
    } else {
        $parts = array_map('trim', explode(',', $canReceiveStr));
        $otherGroups = array_values(array_filter($parts, function($g) use ($targetGroup) {
            return $g !== $targetGroup['group_name'] && !empty($g);
        }));
        
        if (!empty($otherGroups)) {
            $placeholders = implode(',', array_fill(0, count($otherGroups), '?'));
            $compatibleUnits = queryAll("
                SELECT bi.inventory_id, bi.bag_code, bg.group_name, bi.collection_date, 
                       bi.expiry_date, bi.storage_location,
                       DATEDIFF(bi.expiry_date, CURDATE()) AS days_to_expiry
                FROM blood_inventory bi
                JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
                WHERE bg.group_name IN ($placeholders)
                  AND bi.status = 'AVAILABLE' 
                  AND bi.expiry_date >= CURDATE()
                ORDER BY bi.expiry_date ASC
            ", $otherGroups);
        }
    }
    
    $exactCount = count($exactUnits);
    $compatibleCount = count($compatibleUnits);
    $totalViable = $exactCount + $compatibleCount;
    
    $status = 'DEPLETED';
    if ($exactCount >= $unitsNeeded) {
        $status = 'EXACT_AVAILABLE';
    } elseif ($totalViable >= $unitsNeeded) {
        $status = 'COMPATIBLE_AVAILABLE';
    } elseif ($totalViable > 0) {
        $status = 'PARTIALLY_AVAILABLE';
    }
    
    return [
        'target_group' => $targetGroup,
        'units_needed' => $unitsNeeded,
        'exact_available_count' => $exactCount,
        'exact_units' => $exactUnits,
        'compatible_available_count' => $compatibleCount,
        'compatible_units' => $compatibleUnits,
        'total_viable_count' => $totalViable,
        'match_status' => $status
    ];
}

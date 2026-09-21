<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Inventory Service
 * Manages serialized blood bags, shelf-life (35-day countdown), and cold-chain alerts.
 */

require_once __DIR__ . '/../config/database.php';

function getInventory($bloodGroupId = null, $status = null, $search = null) {
    $conditions = [];
    $params = [];
    
    if ($bloodGroupId) {
        $conditions[] = "bi.blood_group_id = ?";
        $params[] = $bloodGroupId;
    }
    
    if ($status) {
        $conditions[] = "bi.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $conditions[] = "(bi.bag_code LIKE ? OR bi.storage_location LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    $sql = "
        SELECT 
            bi.inventory_id,
            bi.bag_code,
            bg.group_name AS blood_group,
            bi.collection_date,
            bi.expiry_date,
            bi.status,
            bi.storage_location,
            DATEDIFF(bi.expiry_date, CURDATE()) AS days_left,
            u.full_name AS donor_name
        FROM blood_inventory bi
        INNER JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
        LEFT JOIN donations don ON bi.donation_id = don.donation_id
        LEFT JOIN donors d ON don.donor_id = d.donor_id
        LEFT JOIN users u ON d.user_id = u.user_id
        $whereClause
        ORDER BY bi.expiry_date ASC
    ";
    
    return queryAll($sql, $params);
}

/**
 * Feature 4: Cold-Chain Expiry Warning Alert
 * Retrieves viable units expiring within the threshold (default: 7 days).
 */
function getExpiringSoonBags($days = 7) {
    return queryAll("
        SELECT 
            bi.inventory_id,
            bi.bag_code,
            bg.group_name,
            bi.collection_date,
            bi.expiry_date,
            bi.storage_location,
            DATEDIFF(bi.expiry_date, CURDATE()) AS days_remaining
        FROM blood_inventory bi
        JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
        WHERE bi.status = 'AVAILABLE'
          AND bi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY bi.expiry_date ASC
    ", [$days]);
}

function getExpiredBags() {
    return queryAll("
        SELECT 
            bi.inventory_id,
            bi.bag_code,
            bg.group_name,
            bi.collection_date,
            bi.expiry_date,
            bi.status,
            bi.storage_location,
            DATEDIFF(CURDATE(), bi.expiry_date) AS days_past_expiry
        FROM blood_inventory bi
        JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
        WHERE bi.expiry_date < CURDATE()
          AND bi.status != 'ISSUED'
          AND bi.status != 'DISCARDED'
        ORDER BY bi.expiry_date ASC
    ");
}

function addInventoryBag($bagCode, $bloodGroupId, $collectionDate, $storageLocation, $donationId = null) {
    $colDate = new DateTime($collectionDate);
    $expDate = clone $colDate;
    $expDate->modify('+35 days'); // Standard red cell preservation window
    
    return executeDML("
        INSERT INTO blood_inventory 
        (bag_code, blood_group_id, donation_id, collection_date, expiry_date, status, storage_location)
        VALUES (?, ?, ?, ?, ?, 'AVAILABLE', ?)
    ", [
        $bagCode,
        $bloodGroupId,
        $donationId,
        $colDate->format('Y-m-d'),
        $expDate->format('Y-m-d'),
        $storageLocation
    ]);
}

function discardInventoryBag($inventoryId) {
    return executeDML("
        UPDATE blood_inventory 
        SET status = 'DISCARDED' 
        WHERE inventory_id = ?
    ", [$inventoryId]);
}

/**
 * Aggregations: Total units, available, issued, expiring soon, and expired.
 */
function getInventorySummaryKPIs() {
    return queryOne("
        SELECT 
            COUNT(inventory_id) AS total_tracked_units,
            COALESCE(SUM(CASE WHEN status = 'AVAILABLE' AND expiry_date >= CURDATE() THEN 1 ELSE 0 END), 0) AS total_available,
            COALESCE(SUM(CASE WHEN status = 'ISSUED' THEN 1 ELSE 0 END), 0) AS total_issued,
            COALESCE(SUM(CASE WHEN status = 'AVAILABLE' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS total_expiring_soon,
            COALESCE(SUM(CASE WHEN expiry_date < CURDATE() AND status != 'ISSUED' AND status != 'DISCARDED' THEN 1 ELSE 0 END), 0) AS total_expired
        FROM blood_inventory
    ");
}

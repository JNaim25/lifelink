<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Journey API Endpoint
 * Provides 5-stage chain-of-custody traceability for any blood bag.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../services/issuance_service.php';

$bagCode = trim($_GET['bag_code'] ?? '');

if (empty($bagCode)) {
    // If no bag_code provided, return a list of recent tracked bags so the UI can offer quick selection
    $recentBags = queryAll("
        SELECT bi.bag_code, bg.group_name AS blood_group, bi.status, bi.collection_date, bi.expiry_date
        FROM blood_inventory bi
        JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
        ORDER BY bi.inventory_id DESC
        LIMIT 10
    ");
    jsonSuccess(['recent_bags' => $recentBags], 'Provide a ?bag_code= parameter for full journey.');
}

try {
    $journey = getBloodJourney($bagCode);
    if (!$journey) {
        jsonError("Blood bag with code '{$bagCode}' was not found in the inventory registry.", 404);
    }
    
    jsonSuccess($journey, 'Blood journey timeline retrieved successfully.');
} catch (Exception $e) {
    jsonError('Failed to retrieve blood journey: ' . $e->getMessage(), 500);
}

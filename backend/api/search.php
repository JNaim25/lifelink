<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Search API Endpoint (Smart Blood Match)
 * Executes ABO/Rh cross-compatibility and inventory/donor matching.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../services/blood_service.php';

$bloodGroupId = isset($_GET['blood_group_id']) ? intval($_GET['blood_group_id']) : 0;
$unitsNeeded = isset($_GET['units']) ? max(1, intval($_GET['units'])) : 1;

if ($bloodGroupId <= 0) {
    jsonError('A valid blood_group_id is required.');
}

try {
    $requestedGroup = getBloodGroupById($bloodGroupId);
    if (!$requestedGroup) {
        jsonError('Blood group not found.', 404);
    }

    $match = smartBloodMatch($bloodGroupId, $unitsNeeded);

    // Parse compatible group types array
    $canReceiveStr = $requestedGroup['can_receive_from'];
    $compatibleTypes = array_map('trim', explode(',', $canReceiveStr));

    // Retrieve compatible voluntary donors who have passed 90-day cooldown
    $eligibleDonors = [];
    if (strpos($canReceiveStr, 'All Blood Groups') !== false) {
        $eligibleDonors = queryAll("
            SELECT d.donor_id, u.full_name, u.phone, bg.group_name AS blood_group, d.city, d.last_donation_date
            FROM donors d
            JOIN users u ON d.user_id = u.user_id
            JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
            WHERE d.is_eligible = 1
              AND (d.last_donation_date IS NULL OR d.last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 90 DAY))
            ORDER BY d.last_donation_date ASC
            LIMIT 10
        ");
    } else {
        $placeholders = implode(',', array_fill(0, count($compatibleTypes), '?'));
        $eligibleDonors = queryAll("
            SELECT d.donor_id, u.full_name, u.phone, bg.group_name AS blood_group, d.city, d.last_donation_date
            FROM donors d
            JOIN users u ON d.user_id = u.user_id
            JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
            WHERE bg.group_name IN ($placeholders)
              AND d.is_eligible = 1
              AND (d.last_donation_date IS NULL OR d.last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 90 DAY))
            ORDER BY d.last_donation_date ASC
            LIMIT 10
        ", $compatibleTypes);
    }

    // Map units to include days_left for frontend display
    $exactMatches = array_map(function($u) {
        $u['blood_group'] = $u['group_name'];
        $u['days_left'] = $u['days_to_expiry'];
        return $u;
    }, $match['exact_units']);

    $compatibleAlternatives = array_map(function($u) {
        $u['blood_group'] = $u['group_name'];
        $u['days_left'] = $u['days_to_expiry'];
        return $u;
    }, $match['compatible_units']);

    jsonSuccess([
        'requested_group' => $requestedGroup,
        'exact_matches' => $exactMatches,
        'compatible_alternatives' => $compatibleAlternatives,
        'eligible_donors' => $eligibleDonors,
        'compatible_types' => $compatibleTypes,
        'total_available_units' => $match['total_viable_count'],
        'match_status' => $match['match_status']
    ], 'Smart match calculated successfully.');
} catch (Exception $e) {
    jsonError('Search calculation failed: ' . $e->getMessage(), 500);
}

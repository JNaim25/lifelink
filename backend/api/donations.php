<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Donations API Endpoint
 * Handles intake sessions, auto-generation of inventory bags, and donation history.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../services/donor_service.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

$inputData = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
    }
}
$data = array_merge($_POST, $inputData);

if ($method === 'GET') {
    $currentUser = requireLoginAPI();
    
    if ($currentUser['role'] === 'DONOR') {
        $donor = getDonorByUserId($currentUser['user_id']);
        if (!$donor) {
            jsonError('Donor profile not found for this account.', 404);
        }
        $donations = getDonorDonations($donor['donor_id']);
        jsonSuccess([
            'donor' => $donor,
            'donations' => $donations
        ], 'Donor history retrieved.');
    } else {
        // ADMIN access: can fetch all donations or for a specific donor
        $donorId = isset($_GET['donor_id']) ? intval($_GET['donor_id']) : null;
        if ($donorId) {
            $donations = getDonorDonations($donorId);
        } else {
            $donations = queryAll("
                SELECT 
                    don.donation_id,
                    don.donation_date,
                    don.units_donated,
                    don.blood_pressure,
                    don.hemoglobin,
                    don.remarks,
                    bg.group_name AS blood_group,
                    u.full_name AS donor_name,
                    u.phone AS donor_phone,
                    bi.bag_code
                FROM donations don
                INNER JOIN donors d ON don.donor_id = d.donor_id
                INNER JOIN users u ON d.user_id = u.user_id
                INNER JOIN blood_groups bg ON don.blood_group_id = bg.blood_group_id
                LEFT JOIN blood_inventory bi ON don.donation_id = bi.donation_id
                ORDER BY don.donation_date DESC, don.donation_id DESC
            ");
        }
        jsonSuccess($donations, 'Donations list retrieved.');
    }
}

if ($method === 'POST') {
    requireAdminAPI();
    
    $donorId = intval($data['donor_id'] ?? 0);
    $bloodGroupId = intval($data['blood_group_id'] ?? 0);
    $bp = trim($data['blood_pressure'] ?? '120/80');
    $hb = floatval($data['hemoglobin'] ?? 14.0);
    $remarks = trim($data['remarks'] ?? 'Standard voluntary donation');
    $storageLocation = trim($data['storage_location'] ?? 'Cold Vault 1 / Shelf A1');

    if ($donorId <= 0) {
        jsonError('Valid donor must be selected.');
    }

    if ($bloodGroupId <= 0) {
        // Look up donor's registered blood group
        $donor = queryOne("SELECT blood_group_id FROM donors WHERE donor_id = ?", [$donorId]);
        if ($donor) {
            $bloodGroupId = $donor['blood_group_id'];
        } else {
            jsonError('Donor not found.');
        }
    }

    try {
        list($donationId, $bagCode) = recordDonationAndCreateBag(
            $donorId,
            $bloodGroupId,
            $bp,
            $hb,
            $remarks,
            $storageLocation
        );

        jsonSuccess([
            'donation_id' => $donationId,
            'bag_code' => $bagCode,
            'storage_location' => $storageLocation
        ], "Donation session recorded successfully! Bag {$bagCode} added to inventory.");
    } catch (Exception $e) {
        jsonError('Failed to record donation session: ' . $e->getMessage(), 500);
    }
}

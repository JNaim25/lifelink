<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Donors API Endpoint
 * Manages donor directory (demonstrating LEFT JOIN), profile, and cooldown checking.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../services/donor_service.php';

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    switch ($action) {
        case 'profile':
            $currentUser = requireLoginAPI();
            $donor = getDonorByUserId($currentUser['user_id']);
            if (!$donor) {
                jsonError('Donor profile not found.', 404);
            }
            jsonSuccess($donor);
            break;

        case 'list':
        default:
            $currentUser = currentUser();
            // Both ADMIN and authorized users can view donor directory
            $donors = getAllDonors();
            jsonSuccess($donors, 'Donor directory retrieved successfully.');
            break;
    }
}

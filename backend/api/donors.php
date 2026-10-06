<?php
/**
 * LifeLink Blood Bank Management System
 * 
 * Donors API Endpoint
 * Manages donor directory, profile, and 90-day cooldown checking.
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

        case 'cooldown':
            $donorId = isset($_GET['donor_id']) ? intval($_GET['donor_id']) : 0;
            if ($donorId <= 0) {
                jsonError('Valid donor_id is required.');
            }
            $cooldown = checkDonorCooldown($donorId);
            jsonSuccess($cooldown);
            break;

        case 'list':
        default:
            $currentUser = currentUser();
            $donors = getAllDonors();
            if (isset($_GET['eligible_only']) && $_GET['eligible_only'] == '1') {
                $donors = array_values(array_filter($donors, function($d) {
                    return !empty($d['can_donate']);
                }));
            }
            jsonSuccess($donors, 'Donor directory retrieved successfully.');
            break;
    }
}

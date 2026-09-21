<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Emergency Requisitions API Endpoint
 * Handles critical blood requisitions and live emergency queue broadcasting.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../services/request_service.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $emergencyQueue = getEmergencyQueue();
        jsonSuccess($emergencyQueue, 'Active emergency queue retrieved successfully.');
    } catch (Exception $e) {
        jsonError('Failed to retrieve emergency queue: ' . $e->getMessage(), 500);
    }
} elseif ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = array_merge($_POST, json_decode($rawInput, true) ?? []);

    $patientName = trim($data['patient_name'] ?? '');
    $hospitalName = trim($data['hospital_name'] ?? '');
    $bloodGroupId = intval($data['blood_group_id'] ?? 0);
    $unitsRequested = intval($data['units_requested'] ?? 1);
    $reason = trim($data['reason'] ?? 'Emergency Requisition');
    $contactPhone = trim($data['contact_phone'] ?? '');

    if (empty($patientName) || empty($hospitalName) || $bloodGroupId <= 0 || $unitsRequested <= 0) {
        jsonError('Patient name, hospital name, blood group, and units requested are required.');
    }

    $currentUser = currentUser();
    $userId = $currentUser ? $currentUser['user_id'] : null;

    if (!$userId) {
        $hotlineUser = queryOne("SELECT user_id FROM users WHERE role = 'REQUESTER' LIMIT 1");
        $userId = $hotlineUser ? $hotlineUser['user_id'] : 1;
    }

    $requiredDate = date('Y-m-d');
    $fullReason = $contactPhone ? ($reason . " (Direct Contact: " . $contactPhone . ")") : $reason;

    try {
        $requestId = createBloodRequest(
            $userId,
            $patientName,
            $hospitalName,
            $bloodGroupId,
            $unitsRequested,
            'EMERGENCY',
            $requiredDate,
            $fullReason
        );

        jsonSuccess([
            'request_id' => $requestId,
            'urgency' => 'EMERGENCY',
            'patient_name' => $patientName,
            'hospital_name' => $hospitalName
        ], 'Emergency requisition broadcasted successfully to all emergency response staff.');
    } catch (Exception $e) {
        jsonError('Emergency broadcast failed: ' . $e->getMessage(), 500);
    }
} else {
    jsonError('Method Not Allowed.', 405);
}

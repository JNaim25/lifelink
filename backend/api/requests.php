<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Requests API Endpoint
 * Handles hospital requisitions, status changes, cancellations (DML DELETE),
 * and ACID transactional blood issuance.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../services/request_service.php';
require_once __DIR__ . '/../services/issuance_service.php';

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
    if ($action === 'matrix') {
        $matrix = getDemandVsSupplyMatrix();
        jsonSuccess($matrix, 'Demand vs Supply matrix retrieved.');
    }

    $currentUser = currentUser();
    $requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

    if ($requestId > 0) {
        $request = getRequestById($requestId);
        if (!$request) {
            jsonError('Request not found.', 404);
        }
        if ($currentUser && $currentUser['role'] === 'REQUESTER' && $request['user_id'] != $currentUser['user_id']) {
            jsonError('Access denied to this requisition.', 403);
        }
        jsonSuccess($request);
    }

    $status = !empty($_GET['status']) ? trim($_GET['status']) : null;
    $urgency = !empty($_GET['urgency']) ? trim($_GET['urgency']) : null;
    $limit = !empty($_GET['limit']) ? intval($_GET['limit']) : null;

    $filterUserId = null;
    if ($currentUser && $currentUser['role'] === 'REQUESTER') {
        $filterUserId = $currentUser['user_id'];
    }

    $requests = getRequests($status, $urgency, $filterUserId, $limit);
    jsonSuccess($requests, 'Requisitions retrieved successfully.');
}

if ($method === 'POST') {
    switch ($action) {
        case 'create':
            $currentUser = requireLoginAPI();
            $patientName = trim($data['patient_name'] ?? '');
            $hospitalName = trim($data['hospital_name'] ?? '');
            $bloodGroupId = intval($data['blood_group_id'] ?? 0);
            $unitsRequested = intval($data['units_requested'] ?? 1);
            $urgency = strtoupper(trim($data['urgency'] ?? 'NORMAL'));
            $requiredDate = trim($data['required_date'] ?? date('Y-m-d'));
            $reason = trim($data['reason'] ?? '');

            if (empty($patientName) || empty($hospitalName) || $bloodGroupId <= 0 || $unitsRequested <= 0) {
                jsonError('Please complete all required fields.');
            }

            if ($urgency === 'ROUTINE') {
                $urgency = 'NORMAL';
            }
            if (!in_array($urgency, ['NORMAL', 'URGENT', 'EMERGENCY'])) {
                $urgency = 'NORMAL';
            }

            try {
                $reqId = createBloodRequest(
                    $currentUser['user_id'],
                    $patientName,
                    $hospitalName,
                    $bloodGroupId,
                    $unitsRequested,
                    $urgency,
                    $requiredDate,
                    $reason
                );
                jsonSuccess(['request_id' => $reqId], 'Blood requisition ticket submitted successfully.');
            } catch (Exception $e) {
                jsonError('Failed to create requisition: ' . $e->getMessage(), 500);
            }
            break;

        case 'cancel':
            $currentUser = requireLoginAPI();
            $requestId = intval($data['request_id'] ?? 0);
            if ($requestId <= 0) {
                jsonError('Valid request_id is required.');
            }

            $isAdmin = ($currentUser['role'] === 'ADMIN');
            $affected = cancelPendingRequest($requestId, $currentUser['user_id'], $isAdmin);
            
            if ($affected > 0) {
                jsonSuccess(null, 'Requisition cancelled and removed from active queue.');
            } else {
                jsonError('Unable to cancel request. Only pending requests can be cancelled.', 400);
            }
            break;

        case 'update_status':
            requireAdminAPI();
            $requestId = intval($data['request_id'] ?? 0);
            $newStatus = strtoupper(trim($data['status'] ?? ''));

            if ($requestId <= 0 || !in_array($newStatus, ['APPROVED', 'REJECTED'])) {
                jsonError('Valid request_id and target status (APPROVED/REJECTED) are required.');
            }

            $affected = updateRequestStatus($requestId, $newStatus);
            jsonSuccess(['affected' => $affected], "Request status updated to {$newStatus}.");
            break;

        case 'issue':
            $currentUser = requireAdminAPI();
            $requestId = intval($data['request_id'] ?? 0);
            $remarks = trim($data['remarks'] ?? 'Units issued via transactional verification.');

            if ($requestId <= 0) {
                jsonError('Valid request_id is required for issuance.');
            }

            $result = executeIssuanceTransaction($requestId, $currentUser['user_id'], $remarks);
            
            if ($result['success']) {
                jsonSuccess($result, $result['message']);
            } else {
                jsonError($result['message'], 400, $result);
            }
            break;

        default:
            jsonError('Unknown action for requests endpoint.', 400);
            break;
    }
}

<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Inventory API Endpoint
 * Provides serialized blood bag inventory querying, cold-chain expiry alerts,
 * and bag management operations.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../services/inventory_service.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
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
    switch ($action) {
        case 'kpis':
            $kpis = getInventorySummaryKPIs();
            jsonSuccess($kpis, 'Inventory KPIs retrieved.');
            break;

        case 'expiring':
            $days = isset($_GET['days']) ? intval($_GET['days']) : 7;
            $expiring = getExpiringSoonBags($days);
            $expired = getExpiredBags();
            jsonSuccess([
                'expiring_soon' => $expiring,
                'already_expired' => $expired
            ], 'Cold chain expiry alerts retrieved.');
            break;

        case 'list':
        default:
            $bloodGroupId = !empty($_GET['blood_group_id']) ? intval($_GET['blood_group_id']) : null;
            $status = !empty($_GET['status']) ? trim($_GET['status']) : null;
            $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

            $items = getInventory($bloodGroupId, $status, $search);
            jsonSuccess($items, 'Inventory bags retrieved.');
            break;
    }
}

if ($method === 'POST') {
    requireAdminAPI();

    switch ($action) {
        case 'add':
            $bagCode = trim($data['bag_code'] ?? '');
            $bloodGroupId = intval($data['blood_group_id'] ?? 0);
            $collectionDate = trim($data['collection_date'] ?? date('Y-m-d'));
            $storageLocation = trim($data['storage_location'] ?? 'Chamber A-1');
            $donationId = !empty($data['donation_id']) ? intval($data['donation_id']) : null;

            if (empty($bagCode)) {
                $bagCode = 'BAG-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
            }

            if ($bloodGroupId <= 0) {
                jsonError('Valid blood group must be selected.');
            }

            try {
                $invId = addInventoryBag($bagCode, $bloodGroupId, $collectionDate, $storageLocation, $donationId);
                jsonSuccess(['inventory_id' => $invId, 'bag_code' => $bagCode], 'Blood bag successfully added to inventory.');
            } catch (Exception $e) {
                jsonError('Failed to add blood bag: ' . $e->getMessage(), 500);
            }
            break;

        case 'discard':
            $inventoryId = intval($data['inventory_id'] ?? 0);
            if ($inventoryId <= 0) {
                jsonError('Valid inventory_id is required.');
            }

            try {
                $affected = discardInventoryBag($inventoryId);
                jsonSuccess(['affected' => $affected], 'Blood bag flagged as DISCARDED.');
            } catch (Exception $e) {
                jsonError('Failed to discard bag: ' . $e->getMessage(), 500);
            }
            break;

        default:
            jsonError('Unknown inventory action.', 400);
            break;
    }
}

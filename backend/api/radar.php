<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Radar API Endpoint
 * Provides real-time stock metrics querying view_blood_availability.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../services/blood_service.php';

try {
    $radarData = getBloodAvailabilityRadar();
    $allGroups = getAllBloodGroups();
    
    jsonSuccess([
        'radar' => $radarData,
        'groups' => $allGroups,
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Blood availability radar retrieved successfully.');
} catch (Exception $e) {
    jsonError('Failed to fetch radar data: ' . $e->getMessage(), 500);
}

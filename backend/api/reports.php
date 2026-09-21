<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Reports & DBMS Demonstration API
 * Executes syllabus queries (Aggregations, Joins, Subqueries, Views, Indexes)
 * and returns query execution time, formatted SQL, and dataset.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

$queryType = $_GET['query'] ?? 'all';

function executeBenchmarkedQuery($title, $concept, $sql, $params = []) {
    $pdo = getDBConnection();
    $start = microtime(true);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    $durationMs = round((microtime(true) - $start) * 1000, 2);
    
    return [
        'title' => $title,
        'concept' => $concept,
        'sql' => trim($sql),
        'execution_time_ms' => $durationMs,
        'row_count' => count($rows),
        'results' => $rows
    ];
}

$queries = [];

// 1. Aggregation with GROUP BY & HAVING
$queries['aggregation'] = [
    'title' => 'Aggregations with GROUP BY and HAVING',
    'concept' => 'Calculates COUNT, SUM, AVG, MIN, MAX for inventory bags grouped by blood group, filtering for groups with at least 1 unit.',
    'sql' => "
SELECT 
    bg.group_name,
    COUNT(bi.inventory_id) AS total_units,
    SUM(CASE WHEN bi.status = 'AVAILABLE' THEN 1 ELSE 0 END) AS available_units,
    ROUND(AVG(DATEDIFF(bi.expiry_date, bi.collection_date)), 1) AS avg_shelf_life_days,
    MIN(bi.collection_date) AS oldest_collection,
    MAX(bi.collection_date) AS newest_collection
FROM blood_groups bg
JOIN blood_inventory bi ON bg.blood_group_id = bi.blood_group_id
GROUP BY bg.blood_group_id, bg.group_name
HAVING COUNT(bi.inventory_id) >= 1
ORDER BY available_units DESC;
    "
];

// 2. INNER JOIN
$queries['inner_join'] = [
    'title' => 'Multi-Table INNER JOIN (Donation Intake Ledger)',
    'concept' => 'Combines donations, donors, users, and blood_groups tables where relationships are strictly present.',
    'sql' => "
SELECT 
    don.donation_id,
    don.donation_date,
    u.full_name AS donor_name,
    u.phone AS donor_phone,
    bg.group_name AS blood_group,
    don.blood_pressure,
    don.hemoglobin,
    don.units_donated
FROM donations don
INNER JOIN donors d ON don.donor_id = d.donor_id
INNER JOIN users u ON d.user_id = u.user_id
INNER JOIN blood_groups bg ON don.blood_group_id = bg.blood_group_id
ORDER BY don.donation_date DESC
LIMIT 10;
    "
];

// 3. LEFT JOIN
$queries['left_join'] = [
    'title' => 'SQL LEFT JOIN (Complete Donor Registry)',
    'concept' => 'Returns all registered donors, including voluntary registrants who have zero completed donation sessions.',
    'sql' => "
SELECT 
    d.donor_id,
    u.full_name,
    u.email,
    bg.group_name AS blood_group,
    d.city,
    d.last_donation_date,
    COUNT(don.donation_id) AS total_sessions
FROM donors d
INNER JOIN users u ON d.user_id = u.user_id
INNER JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
LEFT JOIN donations don ON d.donor_id = don.donor_id
GROUP BY d.donor_id, u.full_name, u.email, bg.group_name, d.city, d.last_donation_date
ORDER BY total_sessions DESC, d.donor_id ASC;
    "
];

// 4. Correlated Subquery
$queries['correlated_subquery'] = [
    'title' => 'Correlated Subquery (High-Yield Donors)',
    'concept' => 'Finds donors whose total donations exceed the average donation count of all donors.',
    'sql' => "
SELECT 
    u.full_name,
    bg.group_name AS blood_group,
    d.city,
    (SELECT COUNT(*) FROM donations don WHERE don.donor_id = d.donor_id) AS donor_donations
FROM donors d
JOIN users u ON d.user_id = u.user_id
JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
WHERE (
    SELECT COUNT(*) FROM donations don WHERE don.donor_id = d.donor_id
) > (
    SELECT AVG(session_count) FROM (
        SELECT COUNT(donation_id) AS session_count 
        FROM donors d2 
        LEFT JOIN donations don2 ON d2.donor_id = don2.donor_id 
        GROUP BY d2.donor_id
    ) AS avg_table
);
    "
];

// 5. Nested Subquery
$queries['nested_subquery'] = [
    'title' => 'Nested Subquery (Requests with Immediate Available Stock)',
    'concept' => 'Finds pending blood requests whose required blood group currently has AVAILABLE stock in blood_inventory.',
    'sql' => "
SELECT 
    br.request_id,
    br.patient_name,
    br.hospital_name,
    bg.group_name AS blood_group,
    br.units_requested,
    br.urgency
FROM blood_requests br
JOIN blood_groups bg ON br.blood_group_id = bg.blood_group_id
WHERE br.status = 'PENDING'
  AND br.blood_group_id IN (
      SELECT DISTINCT bi.blood_group_id 
      FROM blood_inventory bi 
      WHERE bi.status = 'AVAILABLE' 
        AND bi.expiry_date >= CURDATE()
  )
ORDER BY br.request_date DESC;
    "
];

// 6. View Query: view_blood_availability
$queries['view_radar'] = [
    'title' => 'Querying MySQL View: view_blood_availability',
    'concept' => 'Demonstrates view encapsulation. Live aggregation computed across 8 blood groups.',
    'sql' => "
SELECT * FROM view_blood_availability ORDER BY blood_group_id ASC;
    "
];

// 7. View Query: view_emergency_queue
$queries['view_emergency'] = [
    'title' => 'Querying MySQL View: view_emergency_queue',
    'concept' => 'Demonstrates triage queue view with priority sorting and time elapsed calculations.',
    'sql' => "
SELECT * FROM view_emergency_queue ORDER BY urgency ASC, request_date ASC;
    "
];

try {
    if ($queryType !== 'all' && isset($queries[$queryType])) {
        $q = $queries[$queryType];
        $result = executeBenchmarkedQuery($q['title'], $q['concept'], $q['sql']);
        jsonSuccess($result);
    } else {
        $allResults = [];
        foreach ($queries as $key => $q) {
            $allResults[$key] = executeBenchmarkedQuery($q['title'], $q['concept'], $q['sql']);
        }
        jsonSuccess($allResults, 'All syllabus benchmark queries executed successfully.');
    }
} catch (Exception $e) {
    jsonError('Query execution error: ' . $e->getMessage(), 500);
}

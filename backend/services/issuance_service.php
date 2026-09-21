<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Issuance Service
 * Implements Feature 7 (ACID Transactional Issuance) and Feature 6 (The Blood Journey).
 */

require_once __DIR__ . '/../config/database.php';

/**
 * FEATURE 7: Transaction-Based Blood Issuance.
 * Mandatory DBMS requirement: beginTransaction, commit, and rollBack.
 * 
 * Transaction Steps:
 * 1. Acquire DB connection and call $pdo->beginTransaction().
 * 2. Lock blood request using SELECT ... FOR UPDATE.
 * 3. Lock viable FIFO inventory bags using SELECT ... FOR UPDATE.
 * 4. Verify sufficient inventory. If deficit: rollBack().
 * 5. Insert rows into `blood_issuances`.
 * 6. Update `blood_inventory` status to 'ISSUED'.
 * 7. Update `blood_requests` status to 'FULFILLED'.
 * 8. Commit the transaction atomically ($pdo->commit()).
 */
function executeIssuanceTransaction($requestId, $adminUserId, $remarks = null) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        // 1. Lock the request
        $stmtReq = $pdo->prepare("
            SELECT request_id, patient_name, hospital_name, blood_group_id, units_requested, status 
            FROM blood_requests 
            WHERE request_id = ? 
            FOR UPDATE
        ");
        $stmtReq->execute([$requestId]);
        $req = $stmtReq->fetch();
        
        if (!$req) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Request #$requestId does not exist."];
        }
        
        if (!in_array($req['status'], ['PENDING', 'APPROVED'])) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Cannot issue blood for request with status '{$req['status']}'."];
        }
        
        $unitsNeeded = intval($req['units_requested']);
        
        // 2. Lock earliest-expiring viable bags (FIFO allocation)
        $stmtBags = $pdo->prepare("
            SELECT inventory_id, bag_code, expiry_date 
            FROM blood_inventory 
            WHERE blood_group_id = ? 
              AND status = 'AVAILABLE' 
              AND expiry_date >= CURDATE()
            ORDER BY expiry_date ASC 
            LIMIT ? 
            FOR UPDATE
        ");
        // Bind integer parameter for limit in PDO
        $stmtBags->bindValue(1, $req['blood_group_id'], PDO::PARAM_INT);
        $stmtBags->bindValue(2, $unitsNeeded, PDO::PARAM_INT);
        $stmtBags->execute();
        $bagsToIssue = $stmtBags->fetchAll();
        
        if (count($bagsToIssue) < $unitsNeeded) {
            $pdo->rollBack();
            $availCount = count($bagsToIssue);
            return [
                'success' => false,
                'message' => "Transaction ROLLBACK: Insufficient inventory! Requested $unitsNeeded unit(s), but only $availCount viable unit(s) found in stock."
            ];
        }
        
        // 3. Insert issuances and update bag status
        $issuedCodes = [];
        $stmtInsertIss = $pdo->prepare("
            INSERT INTO blood_issuances 
            (request_id, inventory_id, issued_by_user_id, issuance_date, remarks)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmtUpdateBag = $pdo->prepare("
            UPDATE blood_inventory 
            SET status = 'ISSUED' 
            WHERE inventory_id = ?
        ");
        
        foreach ($bagsToIssue as $bag) {
            $bagId = $bag['inventory_id'];
            $issuedCodes[] = $bag['bag_code'];
            
            $dispenseRemarks = $remarks ?: "Issued to fulfill request #$requestId for {$req['patient_name']}";
            $stmtInsertIss->execute([$requestId, $bagId, $adminUserId, $dispenseRemarks]);
            $stmtUpdateBag->execute([$bagId]);
        }
        
        // 4. Update request status to FULFILLED
        $stmtUpdateReq = $pdo->prepare("
            UPDATE blood_requests 
            SET status = 'FULFILLED' 
            WHERE request_id = ?
        ");
        $stmtUpdateReq->execute([$requestId]);
        
        // 5. Commit atomically
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Transaction COMMIT: Successfully issued " . count($bagsToIssue) . " unit(s) (" . implode(', ', $issuedCodes) . ") to {$req['patient_name']}.",
            'issued_bags' => $issuedCodes
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'message' => "Transaction ROLLBACK triggered due to database error: " . $e->getMessage()
        ];
    }
}

/**
 * FEATURE 6: The Signature Blood Journey / Traceability Visualizer.
 * Multi-table join across all 5 chain-of-custody stages.
 */
function getBloodJourney($bagCode) {
    $sql = "
        SELECT 
            -- Inventory Bag Details
            bi.inventory_id,
            bi.bag_code,
            bg.group_name AS blood_group,
            bi.collection_date,
            bi.expiry_date,
            bi.status AS bag_status,
            bi.storage_location,
            
            -- Donor & Donation Details
            don.donation_id,
            don.donation_date,
            don.blood_pressure,
            don.hemoglobin,
            don.remarks AS donation_remarks,
            d.donor_id,
            d.city AS donor_city,
            donor_user.full_name AS donor_name,
            
            -- Request & Patient Details
            br.request_id,
            br.patient_name,
            br.hospital_name,
            br.urgency,
            br.required_date,
            br.reason AS request_reason,
            br.request_date,
            req_user.full_name AS requester_name,
            
            -- Issuance Details
            iss.issuance_id,
            iss.issuance_date,
            iss.remarks AS issuance_remarks,
            admin_user.full_name AS issued_by_officer
            
        FROM blood_inventory bi
        INNER JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
        LEFT JOIN donations don ON bi.donation_id = don.donation_id
        LEFT JOIN donors d ON don.donor_id = d.donor_id
        LEFT JOIN users donor_user ON d.user_id = donor_user.user_id
        LEFT JOIN blood_issuances iss ON bi.inventory_id = iss.inventory_id
        LEFT JOIN blood_requests br ON iss.request_id = br.request_id
        LEFT JOIN users req_user ON br.user_id = req_user.user_id
        LEFT JOIN users admin_user ON iss.issued_by_user_id = admin_user.user_id
        WHERE bi.bag_code = ?
    ";
    
    return queryOne($sql, [$bagCode]);
}

function getAllIssuances() {
    return queryAll("
        SELECT 
            iss.issuance_id,
            iss.issuance_date,
            bi.bag_code,
            bg.group_name AS blood_group,
            br.request_id,
            br.patient_name,
            br.hospital_name,
            br.urgency,
            u_admin.full_name AS issued_by,
            iss.remarks
        FROM blood_issuances iss
        INNER JOIN blood_inventory bi ON iss.inventory_id = bi.inventory_id
        INNER JOIN blood_groups bg ON bi.blood_group_id = bg.blood_group_id
        INNER JOIN blood_requests br ON iss.request_id = br.request_id
        INNER JOIN users u_admin ON iss.issued_by_user_id = u_admin.user_id
        ORDER BY iss.issuance_date DESC
    ");
}

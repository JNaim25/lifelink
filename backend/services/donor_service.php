<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Donor Service
 * Manages donor medical profiles, 90-day cooldown rules, and donation session logging.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/inventory_service.php';

const DONATION_INTERVAL_DAYS = 90;

function getDonorByUserId($userId) {
    $donor = queryOne("
        SELECT 
            d.donor_id,
            d.user_id,
            u.full_name,
            u.email,
            u.phone,
            d.blood_group_id,
            bg.group_name AS blood_group,
            d.date_of_birth,
            d.gender,
            d.last_donation_date,
            d.address,
            d.city,
            d.is_eligible,
            (SELECT COUNT(*) FROM donations don WHERE don.donor_id = d.donor_id) AS total_donations
        FROM donors d
        INNER JOIN users u ON d.user_id = u.user_id
        INNER JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
        WHERE d.user_id = ?
    ", [$userId]);
    
    if (!$donor) {
        return null;
    }
    
    // Calculate 90-day cooldown interval
    $lastDate = $donor['last_donation_date'];
    if ($lastDate) {
        $lastDt = new DateTime($lastDate);
        $nextEligible = clone $lastDt;
        $nextEligible->modify('+' . DONATION_INTERVAL_DAYS . ' days');
        $today = new DateTime();
        
        $diff = $today->diff($nextEligible);
        if ($today >= $nextEligible) {
            $donor['is_cooldown_passed'] = true;
            $donor['days_until_eligible'] = 0;
            $donor['next_eligible_date'] = $today->format('Y-m-d');
        } else {
            $donor['is_cooldown_passed'] = false;
            $donor['days_until_eligible'] = $diff->days;
            $donor['next_eligible_date'] = $nextEligible->format('Y-m-d');
        }
    } else {
        // First-time voluntary donor
        $donor['is_cooldown_passed'] = true;
        $donor['days_until_eligible'] = 0;
        $donor['next_eligible_date'] = date('Y-m-d');
    }
    
    return $donor;
}

function getDonorDonations($donorId) {
    return queryAll("
        SELECT 
            don.donation_id,
            don.donation_date,
            don.units_donated,
            don.blood_pressure,
            don.hemoglobin,
            don.remarks,
            bg.group_name AS blood_group
        FROM donations don
        INNER JOIN blood_groups bg ON don.blood_group_id = bg.blood_group_id
        WHERE don.donor_id = ?
        ORDER BY don.donation_date DESC
    ", [$donorId]);
}

/**
 * Demonstrates SQL LEFT JOIN:
 * Retrieves all registered donors even if they have zero completed donation sessions.
 */
function getAllDonors() {
    return queryAll("
        SELECT 
            d.donor_id,
            u.full_name,
            u.email,
            u.phone,
            bg.group_name AS blood_group,
            d.city,
            d.last_donation_date,
            d.is_eligible,
            COUNT(don.donation_id) AS total_donations
        FROM donors d
        INNER JOIN users u ON d.user_id = u.user_id
        INNER JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
        LEFT JOIN donations don ON d.donor_id = don.donor_id
        GROUP BY d.donor_id, u.full_name, u.email, u.phone, bg.group_name, d.city, d.last_donation_date, d.is_eligible
        ORDER BY d.donor_id ASC
    ");
}

function registerDonor($userId, $bloodGroupId, $dob, $gender, $address, $city) {
    return executeDML("
        INSERT INTO donors 
        (user_id, blood_group_id, date_of_birth, gender, address, city, is_eligible)
        VALUES (?, ?, ?, ?, ?, ?, TRUE)
    ", [$userId, $bloodGroupId, $dob, $gender, $address, $city]);
}

function recordDonationAndCreateBag($donorId, $bloodGroupId, $bp, $hb, $remarks, $storageLocation = 'Cold Vault 1 / Shelf A1') {
    $todayStr = date('Y-m-d');
    
    // 1. Insert donation log
    $donationId = executeDML("
        INSERT INTO donations 
        (donor_id, blood_group_id, donation_date, units_donated, blood_pressure, hemoglobin, remarks)
        VALUES (?, ?, ?, 1, ?, ?, ?)
    ", [$donorId, $bloodGroupId, $todayStr, $bp, $hb, $remarks]);
    
    // 2. Update donor cooldown date
    executeDML("
        UPDATE donors 
        SET last_donation_date = ? 
        WHERE donor_id = ?
    ", [$todayStr, $donorId]);
    
    // 3. Generate unique serial barcode
    $bg = queryOne("SELECT group_name FROM blood_groups WHERE blood_group_id = ?", [$bloodGroupId]);
    $cleanGrp = str_replace(['+', '-'], ['P', 'N'], $bg['group_name']);
    $bagCode = sprintf("BAG-%s-%s%03d", date('Y'), $cleanGrp, $donationId);
    
    // 4. Spawn unit into inventory
    addInventoryBag($bagCode, $bloodGroupId, $todayStr, $storageLocation, $donationId);
    
    return [$donationId, $bagCode];
}

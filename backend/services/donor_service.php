<?php
/**
 * LifeLink Blood Bank Management System
 * 
 * Donor Service
 * Manages donor medical profiles, 90-day cooldown rules, and donation session logging.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/inventory_service.php';

const DONATION_INTERVAL_DAYS = 90;

/**
 * Check if a donor is currently eligible to donate or is under the 90-day cooldown period.
 */
function checkDonorCooldown($donorId) {
    $donor = queryOne("
        SELECT d.donor_id, d.last_donation_date, d.is_eligible, d.blood_group_id, bg.group_name, u.full_name
        FROM donors d
        JOIN users u ON d.user_id = u.user_id
        JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
        WHERE d.donor_id = ?
    ", [$donorId]);
    
    if (!$donor) {
        return [
            'can_donate' => false,
            'days_until_eligible' => 0,
            'next_eligible_date' => null,
            'message' => 'Donor record not found.'
        ];
    }
    
    if (!$donor['is_eligible']) {
        return [
            'can_donate' => false,
            'days_until_eligible' => 0,
            'next_eligible_date' => null,
            'message' => "Donor {$donor['full_name']} is currently marked as ineligible for donations."
        ];
    }
    
    if ($donor['last_donation_date']) {
        $lastDate = new DateTime($donor['last_donation_date']);
        $nextEligible = clone $lastDate;
        $nextEligible->modify('+' . DONATION_INTERVAL_DAYS . ' days');
        $today = new DateTime();
        
        if ($today < $nextEligible) {
            $diff = $today->diff($nextEligible);
            $daysLeft = $diff->days;
            return [
                'can_donate' => false,
                'days_until_eligible' => $daysLeft,
                'next_eligible_date' => $nextEligible->format('Y-m-d'),
                'last_donation_date' => $donor['last_donation_date'],
                'donor_name' => $donor['full_name'],
                'blood_group' => $donor['group_name'],
                'message' => "Donor {$donor['full_name']} gave blood on {$donor['last_donation_date']} and cannot donate again until {$nextEligible->format('Y-m-d')} ($daysLeft day(s) cooldown remaining)."
            ];
        }
    }
    
    return [
        'can_donate' => true,
        'days_until_eligible' => 0,
        'next_eligible_date' => date('Y-m-d'),
        'last_donation_date' => $donor['last_donation_date'],
        'donor_name' => $donor['full_name'],
        'blood_group' => $donor['group_name'],
        'message' => 'Donor has cleared the 90-day cooldown and is eligible to donate.'
    ];
}

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
            $donor['can_donate'] = (bool)$donor['is_eligible'];
        } else {
            $donor['is_cooldown_passed'] = false;
            $donor['days_until_eligible'] = $diff->days;
            $donor['next_eligible_date'] = $nextEligible->format('Y-m-d');
            $donor['can_donate'] = false;
        }
    } else {
        // First-time voluntary donor
        $donor['is_cooldown_passed'] = true;
        $donor['days_until_eligible'] = 0;
        $donor['next_eligible_date'] = date('Y-m-d');
        $donor['can_donate'] = (bool)$donor['is_eligible'];
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
 * Retrieves all registered donors and computes live 90-day cooldown status.
 */
function getAllDonors() {
    $rows = queryAll("
        SELECT 
            d.donor_id,
            d.user_id,
            u.full_name,
            u.email,
            u.phone,
            d.blood_group_id,
            bg.group_name AS blood_group,
            d.city,
            d.last_donation_date,
            d.is_eligible,
            COUNT(don.donation_id) AS total_donations
        FROM donors d
        INNER JOIN users u ON d.user_id = u.user_id
        INNER JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id
        LEFT JOIN donations don ON d.donor_id = don.donor_id
        GROUP BY d.donor_id, d.user_id, u.full_name, u.email, u.phone, d.blood_group_id, bg.group_name, d.city, d.last_donation_date, d.is_eligible
        ORDER BY d.donor_id ASC
    ");
    
    $today = new DateTime();
    foreach ($rows as &$d) {
        if ($d['last_donation_date']) {
            $lastDt = new DateTime($d['last_donation_date']);
            $nextEligible = clone $lastDt;
            $nextEligible->modify('+' . DONATION_INTERVAL_DAYS . ' days');
            
            if ($today >= $nextEligible) {
                $d['is_cooldown_passed'] = true;
                $d['days_until_eligible'] = 0;
                $d['next_eligible_date'] = $today->format('Y-m-d');
                $d['can_donate'] = (bool)$d['is_eligible'];
            } else {
                $diff = $today->diff($nextEligible);
                $d['is_cooldown_passed'] = false;
                $d['days_until_eligible'] = $diff->days;
                $d['next_eligible_date'] = $nextEligible->format('Y-m-d');
                $d['can_donate'] = false;
            }
        } else {
            $d['is_cooldown_passed'] = true;
            $d['days_until_eligible'] = 0;
            $d['next_eligible_date'] = $today->format('Y-m-d');
            $d['can_donate'] = (bool)$d['is_eligible'];
        }
    }
    unset($d);
    return $rows;
}

function registerDonor($userId, $bloodGroupId, $dob, $gender, $address, $city) {
    return executeDML("
        INSERT INTO donors 
        (user_id, blood_group_id, date_of_birth, gender, address, city, is_eligible)
        VALUES (?, ?, ?, ?, ?, ?, TRUE)
    ", [$userId, $bloodGroupId, $dob, $gender, $address, $city]);
}

/**
 * Record a blood intake session for a donor and spawn a unit bag in inventory.
 * Strictly verifies that the donor has cleared the 90-day cooldown before proceeding.
 */
function recordDonationAndCreateBag($donorId, $bloodGroupId, $bp, $hb, $remarks, $storageLocation = 'Cold Vault 1 / Shelf A1') {
    // Enforce 90-day cooldown verification
    $check = checkDonorCooldown($donorId);
    if (!$check['can_donate']) {
        throw new Exception($check['message']);
    }

    $todayStr = date('Y-m-d');
    
    // 1. Insert donation log
    $donationId = executeDML("
        INSERT INTO donations 
        (donor_id, blood_group_id, donation_date, units_donated, blood_pressure, hemoglobin, remarks)
        VALUES (?, ?, ?, 1, ?, ?, ?)
    ", [$donorId, $bloodGroupId, $todayStr, $bp, $hb, $remarks]);
    
    // 2. Update donor cooldown date to today
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

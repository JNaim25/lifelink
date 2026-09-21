-- ============================================================================
-- LIFELINK BLOOD BANK MANAGEMENT SYSTEM
-- Course: Database Management Systems Laboratory (CSE 3522)
-- Relational Database Schema (Normalized to 3NF)
-- Target RDBMS: MySQL 8.0 / MariaDB (XAMPP)
-- Storage Engine: InnoDB (Supports ACID Transactions and Foreign Keys)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS blood_bank_db 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE blood_bank_db;

-- Drop dependent tables in reverse order of foreign keys for clean re-creation
DROP TABLE IF EXISTS blood_issuances;
DROP TABLE IF EXISTS blood_inventory;
DROP TABLE IF EXISTS blood_requests;
DROP TABLE IF EXISTS donations;
DROP TABLE IF EXISTS donors;
DROP TABLE IF EXISTS blood_groups;
DROP TABLE IF EXISTS users;

-- ============================================================================
-- 1. USERS TABLE
-- Stores credentials and common account information for all system actors.
-- Roles: 'ADMIN', 'DONOR', 'REQUESTER'
-- ============================================================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role ENUM('ADMIN', 'DONOR', 'REQUESTER') NOT NULL DEFAULT 'REQUESTER',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_email (email)
) ENGINE=InnoDB;

-- ============================================================================
-- 2. BLOOD_GROUPS TABLE (Lookup / Master Table)
-- Normalization: Eliminates duplicate string descriptions across tables (3NF).
-- Contains the 8 standard ABO and Rh blood types.
-- ============================================================================
CREATE TABLE blood_groups (
    blood_group_id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(5) NOT NULL UNIQUE, -- 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'
    rh_factor ENUM('+', '-') NOT NULL,
    can_give_to VARCHAR(100) NOT NULL,     -- e.g., 'A+, AB+'
    can_receive_from VARCHAR(100) NOT NULL, -- e.g., 'A+, A-, O+, O-'
    description VARCHAR(255)
) ENGINE=InnoDB;

-- ============================================================================
-- 3. DONORS TABLE
-- Medical profile and contact details for registered blood donors.
-- 1:1 relationship with 'users' (one user account can represent a donor).
-- ============================================================================
CREATE TABLE donors (
    donor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    blood_group_id INT NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('MALE', 'FEMALE', 'OTHER') NOT NULL,
    last_donation_date DATE NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    is_eligible BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(blood_group_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================================
-- 4. DONATIONS TABLE
-- Records each physical blood donation event (camp/hospital visit).
-- 1:N relationship with 'donors'.
-- ============================================================================
CREATE TABLE donations (
    donation_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    blood_group_id INT NOT NULL,
    donation_date DATE NOT NULL,
    units_donated INT NOT NULL DEFAULT 1 CHECK (units_donated > 0),
    blood_pressure VARCHAR(20) NOT NULL,   -- e.g. '120/80'
    hemoglobin DECIMAL(4,1) NOT NULL,      -- Minimum acceptable level typically 12.5 g/dL
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(donor_id) ON DELETE RESTRICT,
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(blood_group_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================================
-- 5. BLOOD_INVENTORY TABLE
-- Represents individual serialized blood bags in refrigerated storage.
-- Whole blood / RBC shelf-life is strictly 35 to 42 days.
-- ============================================================================
CREATE TABLE blood_inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    bag_code VARCHAR(30) NOT NULL UNIQUE,     -- Barcode serial e.g., 'BAG-2026-A101'
    blood_group_id INT NOT NULL,
    donation_id INT NULL,                     -- Linked donation session (nullable for legacy/import stock)
    collection_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('AVAILABLE', 'RESERVED', 'ISSUED', 'DISCARDED') NOT NULL DEFAULT 'AVAILABLE',
    storage_location VARCHAR(50) NOT NULL,    -- e.g. 'Cold Room 1 / Shelf A2'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(blood_group_id) ON DELETE RESTRICT,
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE SET NULL,
    CONSTRAINT chk_dates CHECK (expiry_date >= collection_date)
) ENGINE=InnoDB;

-- ============================================================================
-- 6. BLOOD_REQUESTS TABLE
-- Captures blood requisition tickets from patients and hospital attendants.
-- Includes urgency levels for immediate emergency triage.
-- ============================================================================
CREATE TABLE blood_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                     -- Requester user account
    patient_name VARCHAR(100) NOT NULL,
    hospital_name VARCHAR(150) NOT NULL,
    blood_group_id INT NOT NULL,
    units_requested INT NOT NULL CHECK (units_requested > 0),
    urgency ENUM('NORMAL', 'URGENT', 'EMERGENCY') NOT NULL DEFAULT 'NORMAL',
    required_date DATE NOT NULL,
    status ENUM('PENDING', 'APPROVED', 'FULFILLED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    reason TEXT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(blood_group_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================================
-- 7. BLOOD_ISSUANCES TABLE
-- Audit trail linking fulfilled requests to the specific serialized blood bags.
-- Each bag can be issued at most once (inventory_id is UNIQUE).
-- ============================================================================
CREATE TABLE blood_issuances (
    issuance_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    inventory_id INT NOT NULL UNIQUE,         -- Guarantee a physical bag cannot be issued twice!
    issued_by_user_id INT NOT NULL,           -- Admin / Officer performing transaction
    issuance_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    remarks VARCHAR(255) NULL,
    FOREIGN KEY (request_id) REFERENCES blood_requests(request_id) ON DELETE RESTRICT,
    FOREIGN KEY (inventory_id) REFERENCES blood_inventory(inventory_id) ON DELETE RESTRICT,
    FOREIGN KEY (issued_by_user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

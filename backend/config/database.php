<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Database Configuration & Connection Manager
 * Uses PHP Data Objects (PDO) with automatic port failover (handles XAMPP 3307 & 3306).
 */

class DatabaseConfig {
    const DB_HOST = '127.0.0.1';
    const DB_NAME = 'blood_bank_db';
    const DB_USER = 'root';
    const DB_PASS = '';
    const PRIMARY_PORT = 3307;
    const FALLBACK_PORT = 3306;
}

$GLOBALS['pdo_instance'] = null;

/**
 * Acquire or reuse an active PDO connection to MySQL.
 * 
 * @return PDO
 * @throws Exception if connection fails on all candidate ports
 */
function getDBConnection() {
    global $pdo_instance;
    
    if ($pdo_instance instanceof PDO) {
        return $pdo_instance;
    }
    
    $ports = [DatabaseConfig::PRIMARY_PORT, DatabaseConfig::FALLBACK_PORT];
    $lastException = null;
    
    foreach ($ports as $port) {
        try {
            $dsn = "mysql:host=" . DatabaseConfig::DB_HOST . ";port=" . $port . ";dbname=" . DatabaseConfig::DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DatabaseConfig::DB_USER, DatabaseConfig::DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo_instance = $pdo;
            return $pdo_instance;
        } catch (PDOException $e) {
            $lastException = $e;
            continue;
        }
    }
    
    throw new Exception("Unable to connect to MySQL database '" . DatabaseConfig::DB_NAME . "' on ports 3307 or 3306. Please ensure MySQL is started in XAMPP. Details: " . $lastException->getMessage());
}

/**
 * Execute a SELECT query and fetch all matching rows.
 */
function queryAll($sql, $params = []) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute a SELECT query and fetch a single matching row.
 */
function queryOne($sql, $params = []) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result !== false ? $result : null;
}

/**
 * Execute an INSERT, UPDATE, or DELETE statement.
 * Returns the last inserted ID (for INSERT) or the number of affected rows (for UPDATE/DELETE).
 */
function executeDML($sql, $params = []) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $lastId = $pdo->lastInsertId();
    if ($lastId && intval($lastId) > 0) {
        return intval($lastId);
    }
    return $stmt->rowCount();
}

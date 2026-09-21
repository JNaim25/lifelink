<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Database Reset Utility (PHP)
 * Re-executes schema.sql, views.sql, indexes.sql, and seed.sql to restore
 * a pristine, clean demonstration database state.
 */

require_once __DIR__ . '/../backend/config/database.php';

function splitSqlStatements($sqlText) {
    $lines = [];
    foreach (explode("\n", $sqlText) as $line) {
        $trimmed = trim($line);
        if (strpos($trimmed, '--') === 0) {
            continue;
        }
        $lines[] = $line;
    }
    $cleanSql = implode("\n", $lines);
    
    // Split on semicolons that terminate a statement
    $parts = preg_split('/;\s*(?:\r?\n|$)/', $cleanSql);
    $statements = [];
    foreach ($parts as $part) {
        $trimmed = trim($part);
        if (!empty($trimmed)) {
            $statements[] = $trimmed;
        }
    }
    return $statements;
}

try {
    echo "Connecting to MySQL via LifeLink PDO connection manager...\n";
    $pdo = getDBConnection();
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    $scripts = [
        __DIR__ . '/schema.sql',
        __DIR__ . '/views.sql',
        __DIR__ . '/indexes.sql',
        __DIR__ . '/seed.sql'
    ];
    
    foreach ($scripts as $scriptPath) {
        $relName = basename($scriptPath);
        echo "Executing db/$relName...\n";
        
        $sqlContent = file_get_contents($scriptPath);
        $statements = splitSqlStatements($sqlContent);
        
        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    echo "  [Notice on " . substr($stmt, 0, 45) . "...]: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "\nDatabase reset completed successfully! Pristine demonstration state restored.\n";
} catch (Exception $e) {
    echo "\nError resetting database: " . $e->getMessage() . "\n";
    exit(1);
}

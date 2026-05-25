<?php
define('APP_LOADED', true);
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->exec("ALTER TABLE visitor_logs ADD COLUMN resolution VARCHAR(20) DEFAULT NULL AFTER device_type");
    echo "SUCCESS";
} catch(PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "SUCCESS"; // Již existuje
    } else {
        echo "ERROR: " . $e->getMessage();
    }
}


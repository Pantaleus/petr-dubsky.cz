<?php
// config/database.php - Centrální konfigurace a připojení k databázi

if (!defined('APP_LOADED')) {
    define('APP_LOADED', true);
}

// Načtení tajných údajů, pokud ještě nebyly načteny
$secure_config_path = __DIR__ . '/secure_settings.php';
if (file_exists($secure_config_path)) {
    require_once $secure_config_path;
} else {
    error_log("FATAL ERROR in database.php: Secure config file not found at {$secure_config_path}");
    die("A critical configuration error occurred (DB config missing secure settings).");
}

if (!defined('DB_SERVER')) {
    error_log("FATAL ERROR in database.php: DB constants not defined.");
    die("Critical DB configuration constants are missing.");
}

try {
    // Vytvoření globální instance PDO
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME_SECURE, DB_PASSWORD_SECURE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("FATAL ERROR in database.php: DB Connection failed - " . $e->getMessage());
    die("A critical database connection error occurred.");
}

if (!function_exists('close_db_connection')) {
    function close_db_connection(&$pdo_link) {
        $pdo_link = null;
    }
}
?>

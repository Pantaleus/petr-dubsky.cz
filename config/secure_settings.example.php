<?php
// petr-dubsky.cz/config/secure_settings.example.php
if (!defined('APP_LOADED')) { 
    die('Access denied to secure_settings.php. This file should not be accessed directly.');
}

// SMTP Configuration
define('SMTP_HOST', 'exchange.dub-net.eu');
define('SMTP_USER', 'pietro@petr-dubsky.cz'); // Váš email pro odesílání i login
define('SMTP_PASS', 'YOUR_SMTP_PASSWORD');       // VAŠE SKUTEČNÉ SMTP HESLO
define('SMTP_PORT', 465);
define('SMTP_SECURE_STRING', 'ssl');     // 'ssl' pro port 465 (SMTPS)
define('SMTP_FROM_EMAIL', 'pietro@petr-dubsky.cz');
define('SMTP_FROM_NAME', 'Pietro Dubsky');

// Database Credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME_SECURE', 'c1_dubsky');
define('DB_PASSWORD_SECURE', 'YOUR_DB_PASSWORD'); // VAŠE DB HESLO - mělo by být jiné než SMTP heslo
define('DB_NAME', 'c1_petr_dubsky_cz');
?>

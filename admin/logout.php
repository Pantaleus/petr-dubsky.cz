<?php
session_start(); // Startujeme session, abychom k ní měli přístup

// Zrušení všech session proměnných
$_SESSION = array();

// Pokud je žádoucí zničit i session cookie, je to také možné.
// Poznámka: Toto zničí session, nikoli pouze data session!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Nastavení času do minulosti
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Nakonec zničte session.
session_destroy();

// Přesměrování na login stránku
header("Location: login.php");
exit;
?>
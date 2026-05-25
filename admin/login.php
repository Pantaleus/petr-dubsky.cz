<?php
session_start(); // Startujeme session pro uchování stavu přihlášení

// Pokud je uživatel již přihlášen, přesměrujeme ho na admin dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

define('APP_LOADED', true); // Pro případné kontroly v includovaných souborech
// Předpokládáme, že db_config.php je v adresáři blog/ o úroveň výše než admin/
// a config/secure_settings.php je v kořeni webu o úroveň výše než admin/
// Cesta z admin/ do kořene je '../', pak do 'config/' nebo 'blog/'
require_once __DIR__ . '/../config/secure_settings.php'; 
require_once __DIR__ . '/../config/database.php'; // Vytvoří $pdo spojení

$login_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_admin'])) {
    $username_attempt = trim($_POST['username']);
    $password_attempt = trim($_POST['password']);

    if (empty($username_attempt) || empty($password_attempt)) {
        $login_error = "Prosím, vyplňte uživatelské jméno i heslo.";
    } else {
        // === ANTI-BRUTEFORCE ŠTÍT ===
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $shield_file = __DIR__ . '/../config/auth_shield.json';
        $shield_data = [];
        $now = time();
        $ban_duration = 30 * 60; // 30 minut

        if (file_exists($shield_file)) {
            $shield_data = json_decode(file_get_contents($shield_file), true) ?? [];
        }

        // Vyčistit staré expirace pro odlehčení souboru
        foreach($shield_data as $sip => $sdata) {
            if ($now - $sdata['time'] > $ban_duration) unset($shield_data[$sip]);
        }

        if (isset($shield_data[$ip]) && $shield_data[$ip]['attempts'] >= 5) {
            $time_left = ceil(($ban_duration - ($now - $shield_data[$ip]['time'])) / 60);
            $login_error = "Příliš mnoho neúspěšných pokusů! Přístup zablokován na $time_left minut.";
        } else {
            try {
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1");
            $stmt->bindParam(':username', $username_attempt, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password_attempt, $user['password_hash'])) {
                // === SESSION FIXATION OCHRANA ===
                session_regenerate_id(true);

                // Zrušit počitadlo špatných pokusů
                if(isset($shield_data[$ip])) {
                    unset($shield_data[$ip]);
                    file_put_contents($shield_file, json_encode($shield_data));
                }

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                
                // Přesměrování na hlavní stránku administrace
                header("Location: dashboard.php"); 
                exit;
            } else {
                $login_error = "Nesprávné uživatelské jméno nebo heslo.";
                
                // Přičíst špatný pokus
                if (!isset($shield_data[$ip])) $shield_data[$ip] = ['attempts' => 0, 'time' => $now];
                $shield_data[$ip]['attempts']++;
                $shield_data[$ip]['time'] = $now;
                file_put_contents($shield_file, json_encode($shield_data));
            }
        } catch (PDOException $e) {
            error_log("Admin login DB error: " . $e->getMessage());
            $login_error = "Došlo k chybě při přihlašování. Zkuste to prosím později.";
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení do Administrace</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        /* Jednoduché styly pro login, pokud admin_style.css není dostatečný */
        body.login-page { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #333; }
        .login-container { background-color: #fff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
        .login-container h1 { text-align: center; margin-bottom: 25px; color: #333; }
        .login-container .form-group { margin-bottom: 20px; }
        .login-container label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        .login-container input[type="text"],
        .login-container input[type="password"] { width: calc(100% - 24px); padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        .login-container button[type="submit"] { width: 100%; padding: 12px; background-color: #5cb85c; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1.1rem; font-weight: bold; }
        .login-container button[type="submit"]:hover { background-color: #4cae4c; }
        .login-container .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <h1>Administrace</h1>
        <?php if (!empty($login_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($login_error); ?></p>
        <?php endif; ?>
        <form action="login.php" method="post">
            <div class="form-group">
                <label for="username">Uživatelské jméno:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Heslo:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login_admin">Přihlásit se</button>
        </form>
    </div>
</body>
</html>

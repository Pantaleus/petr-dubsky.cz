<?php
// scripts/send_email.php - Vylepšené zpracování kontaktního formuláře s databází a ochranou proti spamu

// Nastavíme správnou časovou zónu
date_default_timezone_set('Europe/Prague');

define('APP_LOADED', true); 
require_once __DIR__ . '/../config/secure_settings.php'; 
require_once __DIR__ . '/../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once __DIR__ . '/../PHPMailer/Exception.php';
    require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/SMTP.php';
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// --- Funkce pro detekci prohlížeče a OS ---
function parseUserAgent($userAgent) {
    $data = [
        'browser_name' => 'Unknown',
        'browser_version' => '',
        'operating_system' => 'Unknown',
        'device_type' => 'Desktop'
    ];
    
    // Detekce prohlížeče
    if (preg_match('/Firefox\/([0-9\.]+)/', $userAgent, $matches)) {
        $data['browser_name'] = 'Firefox';
        $data['browser_version'] = $matches[1];
    } elseif (preg_match('/Chrome\/([0-9\.]+)/', $userAgent, $matches)) {
        if (strpos($userAgent, 'Edge') !== false) {
            $data['browser_name'] = 'Edge';
        } elseif (strpos($userAgent, 'OPR') !== false) {
            $data['browser_name'] = 'Opera';
        } else {
            $data['browser_name'] = 'Chrome';
        }
        $data['browser_version'] = $matches[1];
    } elseif (preg_match('/Safari\/([0-9\.]+)/', $userAgent, $matches)) {
        $data['browser_name'] = 'Safari';
        $data['browser_version'] = $matches[1];
    }
    
    // Detekce OS
    if (strpos($userAgent, 'Windows NT 10.0') !== false) {
        $data['operating_system'] = 'Windows 10/11';
    } elseif (strpos($userAgent, 'Windows NT') !== false) {
        $data['operating_system'] = 'Windows';
    } elseif (strpos($userAgent, 'Mac OS X') !== false) {
        $data['operating_system'] = 'macOS';
    } elseif (strpos($userAgent, 'Linux') !== false) {
        $data['operating_system'] = 'Linux';
    } elseif (strpos($userAgent, 'Android') !== false) {
        $data['operating_system'] = 'Android';
        $data['device_type'] = 'Mobile';
    } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
        $data['operating_system'] = 'iOS';
        $data['device_type'] = strpos($userAgent, 'iPad') !== false ? 'Tablet' : 'Mobile';
    }
    
    return $data;
}

// --- Funkce pro získání geolokace ---
function getGeolocation($ip) {
    $data = [
        'country_code' => null,
        'country_name' => null,
        'city' => null,
        'timezone' => null
    ];
    
    // Pokud je IP lokální, vrátíme prázdné údaje
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return $data;
    }
    
    try {
        // Použijeme ip-api.com (zdarma, bez klíče, 1000 požadavků/hodina)
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (compatible; ContactForm/1.0)'
            ]
        ]);
        
        $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city,timezone", false, $context);
        
        if ($response !== false) {
            $geo = json_decode($response, true);
            if ($geo && $geo['status'] === 'success') {
                $data['country_code'] = $geo['countryCode'] ?? null;
                $data['country_name'] = $geo['country'] ?? null;
                $data['city'] = $geo['city'] ?? null;
                $data['timezone'] = $geo['timezone'] ?? null;
            }
        }
    } catch (Exception $e) {
        error_log("Geolocation API error: " . $e->getMessage());
    }
    
    return $data;
}

// --- Funkce pro získání skutečné IP adresy ---
function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // Pokud je IP seznam, vezmeme první
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    
    return $ip;
}

// --- Funkce pro generování CSRF tokenu ---
function generateCSRFToken($pdo, $ip, $userAgent) {
    // Nastavíme časovou zónu pro MySQL session
    $pdo->exec("SET time_zone = '+01:00'");
    
    $token = bin2hex(random_bytes(32));
    $userAgentHash = hash('sha256', $userAgent);
    
    // Použijeme UNIX timestamp pro konzistentní práci s časem
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // Token platný 1 hodinu
    
    try {
        // Vyčistíme staré tokeny - používáme UNIX_TIMESTAMP pro konzistenci
        $stmt = $pdo->prepare("DELETE FROM csrf_tokens WHERE UNIX_TIMESTAMP(expires_at) < UNIX_TIMESTAMP(NOW()) OR (ip_address = ? AND user_agent_hash = ?)");
        $stmt->execute([$ip, $userAgentHash]);
        
        // Vložíme nový token
        $stmt = $pdo->prepare("INSERT INTO csrf_tokens (token, ip_address, user_agent_hash, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$token, $ip, $userAgentHash, $expiresAt]);
        
        // error_log("CSRF token generated successfully for IP {$ip}, token: " . substr($token, 0, 10) . "..., expires: {$expiresAt}, current time: " . date('Y-m-d H:i:s') . " (PHP timezone: " . date_default_timezone_get() . ")");
        
        return $token;
    } catch (PDOException $e) {
        error_log("CSRF token generation error: " . $e->getMessage());
        return false;
    }
}

// --- Funkce pro ověření CSRF tokenu ---
function verifyCSRFToken($pdo, $token, $ip, $userAgent) {
    if (empty($token)) {
        error_log("CSRF verification failed: Empty token provided");
        return false;
    }
    
    // Nastavíme časovou zónu pro MySQL session
    $pdo->exec("SET time_zone = '+01:00'");
    
    $userAgentHash = hash('sha256', $userAgent);
    
    try {
        // Debug: Zkontrolujeme co máme v databázi
        $debug_stmt = $pdo->prepare("SELECT COUNT(*) as count, MAX(expires_at) as max_expires FROM csrf_tokens WHERE ip_address = ? AND user_agent_hash = ?");
        $debug_stmt->execute([$ip, $userAgentHash]);
        $debug_info = $debug_stmt->fetch();
        error_log("CSRF Debug: Found {$debug_info['count']} tokens for IP {$ip}, max expires: {$debug_info['max_expires']}, current time: " . date('Y-m-d H:i:s') . " (PHP timezone: " . date_default_timezone_get() . ")");
        
        // Používáme UNIX_TIMESTAMP pro konzistentní porovnání času
        $stmt = $pdo->prepare("SELECT id, expires_at FROM csrf_tokens WHERE token = ? AND ip_address = ? AND user_agent_hash = ? AND UNIX_TIMESTAMP(expires_at) > UNIX_TIMESTAMP(NOW()) AND used = 0");
        $stmt->execute([$token, $ip, $userAgentHash]);
        $result = $stmt->fetch();
        
        if ($result) {
            error_log("CSRF verification successful for token " . substr($token, 0, 10) . "...");
            // Označíme token jako použitý
            $stmt = $pdo->prepare("UPDATE csrf_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            return true;
        } else {
            error_log("CSRF verification failed: Token not found or expired. Token: " . substr($token, 0, 10) . "..., IP: {$ip}, UA Hash: " . substr($userAgentHash, 0, 10) . "...");
            return false;
        }
    } catch (PDOException $e) {
        error_log("CSRF token verification error: " . $e->getMessage());
        return false;
    }
}

// --- Funkce pro rate limiting ---
function checkRateLimit($pdo, $ip, $email) {
    try {
        // Vyčistíme staré záznamy (starší než 24 hodin)
        $stmt = $pdo->prepare("DELETE FROM contact_rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        
        // Zkontrolujeme aktuální limit
        $stmt = $pdo->prepare("SELECT attempts, blocked_until FROM contact_rate_limits WHERE ip_address = ? AND (email = ? OR email IS NULL) ORDER BY last_attempt DESC LIMIT 1");
        $stmt->execute([$ip, $email]);
        $limit = $stmt->fetch();
        
        if ($limit) {
            // Pokud je blokován, zkontrolujeme do kdy
            if ($limit['blocked_until'] && $limit['blocked_until'] > date('Y-m-d H:i:s')) {
                return ['allowed' => false, 'message' => 'Too many attempts. Please try again later.'];
            }
            
            // Pokud má více než 5 pokusů za posledních 24 hodin
            if ($limit['attempts'] >= 5) {
                // Zablokujeme na 1 hodinu
                $stmt = $pdo->prepare("UPDATE contact_rate_limits SET blocked_until = DATE_ADD(NOW(), INTERVAL 1 HOUR), attempts = attempts + 1 WHERE ip_address = ? AND email = ?");
                $stmt->execute([$ip, $email]);
                return ['allowed' => false, 'message' => 'Too many attempts. Please try again later.'];
            }
        }
        
        // Přidáme/aktualizujeme pokus
        $stmt = $pdo->prepare("INSERT INTO contact_rate_limits (ip_address, email, attempts) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
        $stmt->execute([$ip, $email]);
        
        return ['allowed' => true];
    } catch (PDOException $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return ['allowed' => true]; // V případě chyby povolíme pokračování
    }
}

// --- Funkce pro výpočet spam skóre ---
function calculateSpamScore($name, $email, $message, $timeOnSite, $userAgent) {
    $score = 0.0;
    
    // Příliš krátký čas na webu (méně než 10 sekund)
    if ($timeOnSite !== null && $timeOnSite < 10) {
        $score += 0.3;
    }
    
    // Podezřelé vzory ve jméně
    if (preg_match('/[0-9]{3,}/', $name) || strlen($name) < 2) {
        $score += 0.2;
    }
    
    // Podezřelé vzory ve zprávě
    $suspiciousWords = ['viagra', 'casino', 'loan', 'credit', 'bitcoin', 'investment', 'profit', 'money', 'win', 'free'];
    foreach ($suspiciousWords as $word) {
        if (stripos($message, $word) !== false) {
            $score += 0.1;
        }
    }
    
    // Příliš mnoho odkazů ve zprávě
    if (preg_match_all('/http[s]?:\/\//', $message) > 2) {
        $score += 0.3;
    }
    
    // Velmi krátká nebo velmi dlouhá zpráva
    $messageLength = strlen($message);
    if ($messageLength < 10 || $messageLength > 2000) {
        $score += 0.1;
    }
    
    // Podezřelý user agent
    if (empty($userAgent) || strpos($userAgent, 'bot') !== false) {
        $score += 0.2;
    }
    
    return min($score, 1.0); // Maximum 1.0
}

// --- Načtení Jazykového Souboru ---
$available_langs_for_script = array(
    'en' => __DIR__ . '/../lang/en.php',
    'cz' => __DIR__ . '/../lang/cz.php',
    'it' => __DIR__ . '/../lang/it.php'
);
$default_lang_for_script = 'en';
$current_lang_from_post = isset($_POST['lang']) && array_key_exists($_POST['lang'], $available_langs_for_script) 
                            ? $_POST['lang'] 
                            : $default_lang_for_script;

$lang_file_to_load = $available_langs_for_script[$current_lang_from_post];
$lang_script = [];

if (file_exists($lang_file_to_load)) {
    include $lang_file_to_load;
    if (isset($lang) && is_array($lang)) {
        $lang_script = $lang;
    }
    unset($lang);
} else {
    error_log("Error: Language file for contact form not found for '{$current_lang_from_post}'. Path: {$lang_file_to_load}");
    $lang_script = [
        'contact_form_name_required' => 'Name is required.',
        'contact_form_email_required' => 'Email is required.',
        'contact_form_invalid_email_format' => 'Invalid email format.',
        'contact_form_message_required' => 'Message is required.',
        'contact_form_success_server' => 'Message sent successfully! I will get back to you soon.',
        'contact_form_error_server' => 'Unable to send message. Please try again later.',
        'contact_form_error_generic_server' => 'An error occurred while sending the message.',
        'contact_form_invalid_request' => 'Invalid request method.',
        'contact_form_rate_limit' => 'Too many attempts. Please try again later.',
        'contact_form_invalid_token' => 'Invalid security token. Please refresh the page.',
        'contact_form_spam_detected' => 'Your message appears to be spam. Please contact us directly.',
        'contact_form_email_subject' => 'New Message from petr-dubsky.cz Contact Form',
        'contact_form_subject_from' => ' from ',
        'contact_form_email_intro' => 'You have received a new message from the contact form on petr-dubsky.cz:',
        'contact_form_email_name_label' => 'Name:',
        'contact_form_email_email_label' => 'Email:',
        'contact_form_email_message_label' => 'Message:'
    ];
}

// --- Konfigurace ---
$recipient_email_contact_form = "pietro@petr-dubsky.cz";
$email_subject_contact_form = $lang_script['contact_form_email_subject'] ?? "New Message from petr-dubsky.cz Contact Form";

// --- Získání základních informací ---
$ip = getRealIpAddr();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

// --- Zpracování GET požadavku pro CSRF token ---
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] === 'get_csrf_token') {
    try {
        $token = generateCSRFToken($pdo, $ip, $userAgent);
        if ($token) {
            echo json_encode(['success' => true, 'csrf_token' => $token]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unable to generate security token.']);
        }
    } catch (Exception $e) {
        error_log("CSRF token generation failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Unable to generate security token.']);
    }
    exit;
}

// --- Zpracování POST požadavku ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Základní validace
    $name_from_form = isset($_POST['name']) ? strip_tags(trim($_POST['name'])) : '';
    $email_from_user_form = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message_content_form = isset($_POST['message']) ? strip_tags(trim($_POST['message'])) : '';
    $honeypot = isset($_POST['hp-email']) ? trim($_POST['hp-email']) : '';
    $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
    $timeOnSite = isset($_POST['time_on_site']) ? (int)$_POST['time_on_site'] : null;
    $screenResolution = isset($_POST['screen_resolution']) ? $_POST['screen_resolution'] : null;

    // Honeypot kontrola
    if (!empty($honeypot)) {
        echo json_encode(['success' => true, 'message' => ($lang_script['contact_form_success_server'] ?? 'Message processed.')]);
        exit;
    }

    // CSRF token kontrola
    if (!verifyCSRFToken($pdo, $csrf_token, $ip, $userAgent)) {
        error_log("CSRF token verification failed. Token: " . substr($csrf_token, 0, 10) . "..., IP: $ip");
        echo json_encode(['success' => false, 'message' => ($lang_script['contact_form_invalid_token'] ?? 'Invalid security token. Please refresh the page.')]);
        exit;
    }

    // Rate limiting
    $rateLimitResult = checkRateLimit($pdo, $ip, $email_from_user_form);
    if (!$rateLimitResult['allowed']) {
        echo json_encode(['success' => false, 'message' => ($lang_script['contact_form_rate_limit'] ?? 'Too many attempts. Please try again later.')]);
        exit;
    }

    // Validace polí
    $errors_contact = [];
    if (empty($name_from_form)) $errors_contact[] = $lang_script['contact_form_name_required'];
    if (empty($email_from_user_form)) $errors_contact[] = $lang_script['contact_form_email_required'];
    elseif (!filter_var($email_from_user_form, FILTER_VALIDATE_EMAIL)) $errors_contact[] = $lang_script['contact_form_invalid_email_format'];
    if (empty($message_content_form)) $errors_contact[] = $lang_script['contact_form_message_required'];

    if (!empty($errors_contact)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors_contact)]);
        exit;
    }

    // Analýza user agenta a geolokace
    $userAgentData = parseUserAgent($userAgent);
    $geoData = getGeolocation($ip);
    
    // Výpočet spam skóre
    $spamScore = calculateSpamScore($name_from_form, $email_from_user_form, $message_content_form, $timeOnSite, $userAgent);

    // Pokud je spam skóre příliš vysoké, odmítneme
    if ($spamScore > 0.7) {
        echo json_encode(['success' => false, 'message' => ($lang_script['contact_form_spam_detected'] ?? 'Your message appears to be spam. Please contact us directly.')]);
        exit;
    }

    // Vložení do databáze
    try {
        $stmt = $pdo->prepare("
            INSERT INTO contact_messages 
            (name, email, message, language, ip_address, user_agent, browser_name, browser_version, 
             operating_system, device_type, screen_resolution, time_on_site, referrer, country_code, 
             country_name, city, timezone, session_id, csrf_token_used, spam_score) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $name_from_form,
            $email_from_user_form,
            $message_content_form,
            $current_lang_from_post,
            $ip,
            $userAgent,
            $userAgentData['browser_name'],
            $userAgentData['browser_version'],
            $userAgentData['operating_system'],
            $userAgentData['device_type'],
            $screenResolution,
            $timeOnSite,
            $referrer,
            $geoData['country_code'],
            $geoData['country_name'],
            $geoData['city'],
            $geoData['timezone'],
            session_id(),
            $csrf_token,
            $spamScore
        ]);
        
        $messageId = $pdo->lastInsertId();
        
        // Odeslání emailu pouze pokud spam skóre není příliš vysoké
        $emailSent = false;
        if ($spamScore < 0.5) {
            $mail_contact = new PHPMailer(true);
            try {
                // SMTP konfigurace s debug módem
                $mail_contact->isSMTP();
                $mail_contact->Host       = SMTP_HOST;
                $mail_contact->SMTPAuth   = true;
                $mail_contact->Username   = SMTP_USER;
                $mail_contact->Password   = SMTP_PASS;
                $mail_contact->SMTPSecure = SMTP_SECURE_STRING;
                $mail_contact->Port       = SMTP_PORT;
                $mail_contact->CharSet    = 'UTF-8';
                $mail_contact->Timeout    = 30;
                
                // Přidáme SSL verifikační možnosti pro problematické servery
                $mail_contact->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Debug informace (pouze do logu)
                error_log("SMTP Config - Host: " . SMTP_HOST . ", Port: " . SMTP_PORT . ", Secure: " . SMTP_SECURE_STRING . ", User: " . SMTP_USER);
                
                $mail_contact->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail_contact->addAddress($recipient_email_contact_form);
                $mail_contact->addReplyTo($email_from_user_form, $name_from_form);

                $mail_contact->isHTML(false);
                $mail_contact->Subject = $email_subject_contact_form . " from " . $name_from_form . " (ID: {$messageId})";
                
                $email_body_contact  = "You have received a new message from the contact form on petr-dubsky.cz:\n\n";
                $email_body_contact .= "Message ID: {$messageId}\n";
                $email_body_contact .= "Name: " . htmlspecialchars($name_from_form) . "\n";
                $email_body_contact .= "Email: " . htmlspecialchars($email_from_user_form) . "\n";
                $email_body_contact .= "Language: " . $current_lang_from_post . "\n";
                $email_body_contact .= "Country: " . ($geoData['country_name'] ?? 'Unknown') . "\n";
                $email_body_contact .= "Browser: " . $userAgentData['browser_name'] . " " . $userAgentData['browser_version'] . "\n";
                $email_body_contact .= "OS: " . $userAgentData['operating_system'] . "\n";
                $email_body_contact .= "Time on site: " . ($timeOnSite ? $timeOnSite . " seconds" : "Unknown") . "\n";
                $email_body_contact .= "Spam score: " . number_format($spamScore, 2) . "\n\n";
                $email_body_contact .= "Message:\n" . htmlspecialchars($message_content_form) . "\n";
                
                $mail_contact->Body = $email_body_contact;

                if ($mail_contact->send()) {
                    $emailSent = true;
                    error_log("Email sent successfully to {$recipient_email_contact_form} for message ID {$messageId}");
                } else {
                    error_log("PHPMailer send failed: " . $mail_contact->ErrorInfo);
                }
            } catch (Exception $e) {
                error_log("PHPMailer EXCEPTION: " . $e->getMessage() . " | ErrorInfo: " . $mail_contact->ErrorInfo);
            }
        } else {
            error_log("Email not sent due to high spam score ({$spamScore}) for message ID {$messageId}");
        }
        
        // Aktualizace záznamu o odeslání emailu
        $stmt = $pdo->prepare("UPDATE contact_messages SET email_sent = ? WHERE id = ?");
        $stmt->execute([$emailSent ? 1 : 0, $messageId]);
        
        echo json_encode(['success' => true, 'message' => ($lang_script['contact_form_success_server'] ?? 'Message sent successfully!')]);
        
    } catch (PDOException $e) {
        error_log("Database error in contact form: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => ($lang_script['contact_form_error_generic_server'] ?? 'An error occurred while sending the message.')]);
    }
} else {
    echo json_encode(['success' => false, 'message' => ($lang_script['contact_form_invalid_request'] ?? 'Invalid request method.')]);
}
?>

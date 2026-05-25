<?php
// scripts/track_init.php
define('APP_LOADED', true);
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

function parse_user_agent($ua) {
    $browser = 'Neznámý';
    $os = 'Neznámý OS';
    $device_type = 'desktop';
    $is_bot = 0;

    // Detect browser
    if (preg_match('/OPR\/|Opera/i', $ua)) $browser = 'Opera';
    elseif (preg_match('/Edg/i', $ua)) $browser = 'Edge';
    elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/MSIE|Trident/i', $ua)) $browser = 'Internet Explorer';

    // Detect OS
    if (preg_match('/windows|win32/i', $ua)) $os = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $os = 'macOS';
    elseif (preg_match('/linux/i', $ua)) {
        if (preg_match('/android/i', $ua)) $os = 'Android';
        else $os = 'Linux';
    } elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $os = 'iOS';

    // Detect Device
    if (preg_match('/mobile|android|touch|webos|hpwos/i', $ua) && !preg_match('/ipad|tablet/i', $ua)) {
        $device_type = 'mobile';
    } elseif (preg_match('/tablet|ipad/i', $ua)) {
        $device_type = 'tablet';
    }

    // Bot detection
    $ua_lower = strtolower($ua);
    $bots = [
        'googlebot', 'bingbot', 'yandexbot', 'ahrefsbot', 'semrushbot', 'mj12bot',
        'baiduspider', 'twitterbot', 'facebookexternalhit', 'rogerbot', 'linkedinbot',
        'embedly', 'quora link preview', 'showyoubot', 'outbrain', 'pinterest', 'slackbot',
        'vkshare', 'w3c_validator', 'duckduckbot', 'yahoo', 'slurp', 'seznam', 'bot', 'spider', 'crawler', 'scraper'
    ];
    foreach ($bots as $bot) {
        if (strpos($ua_lower, $bot) !== false) {
            $is_bot = 1;
            break;
        }
    }

    return [$browser, $os, $device_type, $is_bot];
}

function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    
    // Použijeme první IP v případě seznamu
    $ips = explode(',', $ipaddress);
    return trim($ips[0]);
}

try {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE) ?? [];

    $ip = get_client_ip();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $url = $input['url'] ?? $_SERVER['HTTP_REFERER'] ?? '/';
    $referer = $input['referer'] ?? '';
    if ($referer === $url) $referer = ''; // Zamezení zACYKLENÍ
    if(empty($referer) && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false) {
       $referer = $_SERVER['HTTP_REFERER'];
    }

    $method = 'GET'; // Původní metoda na frontend je vždy GET pro page load

    // Omezíme ukládání na cesty jako admin, abychom nelogovali administraci
    if (strpos($url, '/admin/') === false) {
        [$browser, $os, $device_type, $is_bot] = parse_user_agent($ua);
        $status_code = 200; // Předpoklad pro asynchronní

        $resolution = $_COOKIE['screen_res'] ?? null;
        if ($resolution && !preg_match('/^[0-9]{3,5}x[0-9]{3,5}$/', $resolution)) {
            $resolution = null;
        }

        $stmt = $pdo->prepare("INSERT INTO `visitor_logs` (`ip_address`, `user_agent`, `requested_url`, `referer_url`, `request_method`, `browser`, `os`, `device_type`, `is_bot`, `status_code`, `resolution`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            substr($ip, 0, 45), 
            substr($ua, 0, 1000), 
            substr($url, 0, 500), 
            substr($referer, 0, 500), 
            substr($method, 0, 10), 
            $browser, 
            $os, 
            $device_type, 
            $is_bot,
            $status_code,
            $resolution
        ]);
        
        $id = $pdo->lastInsertId();
        echo json_encode(["status" => "success", "id" => intval($id)]);
    } else {
        echo json_encode(["status" => "ignored", "id" => 0]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "id" => 0, "message" => "DB Error"]);
}
?>

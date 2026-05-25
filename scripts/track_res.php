<?php
// includes/track_res.php
// Skript pro zápis rozlišení k poslední návštěvě

// CORS hlavičky (pokud je JS volán z jiné subdomény např.)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

define('APP_LOADED', true);
require_once __DIR__ . '/../config/database.php';

function get_client_ip_res() {
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
    return $ipaddress;
}

$ip = get_client_ip_res();
$res = $_GET['res'] ?? '';

// Basic sanitization
if (preg_match('/^[0-9]{3,5}x[0-9]{3,5}$/', $res) && $ip !== 'UNKNOWN') {
    try {
        // Najdi nejnovější log uživatele se stejnou IP adresou
        $stmt = $pdo->prepare("SELECT id FROM visitor_logs WHERE ip_address = ? ORDER BY visit_time DESC LIMIT 1");
        $stmt->execute([substr($ip, 0, 45)]);
        
        $id = $stmt->fetchColumn();
        if ($id) {
            // Update the record with resolution
            $upd = $pdo->prepare("UPDATE visitor_logs SET resolution = ? WHERE id = ?");
            $upd->execute([$res, $id]);
        }
        echo json_encode(['status' => 'success', 'message' => "IP: $ip, Res: $res, Updated ID: $id"]);
    } catch (PDOException $e) {
        // Fail silently or output for debugging
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
}
?>


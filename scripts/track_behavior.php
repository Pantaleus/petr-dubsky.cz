<?php
// Endpoint pro aktualizaci času na stránce a hloubky scrollování pro konkrétní návštěvu
define('APP_LOADED', true);
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Čtení vstupů z GET nebo POST parametrů (navigator.sendBeacon posílá surová data nebo formu)
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE); // Převod z JSON, pokud to posílá Fetch API jako payload

// Basic Rate Limiting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$now = time();
if (!isset($_SESSION['track_limit_time']) || ($now - $_SESSION['track_limit_time']) > 60) {
    $_SESSION['track_limit_time'] = $now;
    $_SESSION['track_limit_count'] = 0;
}
$_SESSION['track_limit_count']++;

if ($_SESSION['track_limit_count'] > 40) {
    // Příliš mnoho požadavků za minutu (cca víc jak 1 za 1.5s = zjevný spam)
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Too many requests"]);
    exit;
}

$log_id = intval($_GET['id'] ?? $_POST['id'] ?? $input['id'] ?? 0);
$time_on_page = intval($_GET['time'] ?? $_POST['time'] ?? $input['time'] ?? 0);
$scroll_depth = intval($_GET['scroll'] ?? $_POST['scroll'] ?? $input['scroll'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? '';

if ($log_id > 0) {
    try {
        if (!empty($action)) {
            // Pokud přijde action, připojíme jej k dosavadním akcím přes CONCAT_WS
            // (pozn. bezpečný přístup k db)
            $action_clean = substr(strip_tags($action), 0, 100);
            $stmt = $pdo->prepare("UPDATE visitor_logs SET actions_taken = CONCAT_WS(', ', actions_taken, ?) WHERE id = ?");
            $stmt->execute([$action_clean, $log_id]);
        }
        
        $stmt = $pdo->prepare("UPDATE visitor_logs SET time_on_page = GREATEST(IFNULL(time_on_page, 0), ?), scroll_depth = GREATEST(IFNULL(scroll_depth, 0), ?) WHERE id = ?");
        $stmt->execute([$time_on_page, $scroll_depth, $log_id]);
        echo json_encode(["status" => "success"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "DB error"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid ID"]);
}
?>

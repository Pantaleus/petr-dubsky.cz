<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Získání filtrů z URL generované ve statistics.php
$range_filter = $_GET['range'] ?? '30';
$type_filter = $_GET['type'] ?? 'all';

$where_clause = "1=1";
$params = [];

if ($range_filter !== 'all') {
    $days = (int)$range_filter;
    $where_clause .= " AND visit_time >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = $days;
}

if ($type_filter === 'humans') {
    $where_clause .= " AND is_bot = 0";
} elseif ($type_filter === 'bots') {
    $where_clause .= " AND is_bot = 1";
}

if (!empty($_GET['hide_ip'])) {
    $where_clause .= " AND ip_address != ?";
    $params[] = $_GET['hide_ip'];
}

// Stažení záznamů
$sql = "SELECT id, ip_address, visit_time, requested_url, referer_url, request_method, browser, os, device_type, is_bot 
        FROM visitor_logs 
        WHERE $where_clause 
        ORDER BY visit_time DESC 
        LIMIT 10000"; // Maximálně 10 000 řádků pro ochranu paměti při exportu

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Nastavení hlaviček pro download
$filename = "export_navstevnost_" . date('Y-m-d') . ".csv";

// Pro Excel v CZ prostředí je často lepší použít středník (;) a přidat BOM
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
// Přidání BOM pro správný import do Excelu s diakritikou
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Názvy sloupců
fputcsv($output, ['ID', 'IP Adresa', 'Datum Návštěvy', 'URL', 'Zdroj (Referer)', 'Metoda', 'Prohlížeč', 'Operační Systém', 'Zařízení', 'Typ (1 = Bot, 0 = Clovek)'], ';');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['id'],
        $row['ip_address'],
        $row['visit_time'],
        $row['requested_url'],
        $row['referer_url'],
        $row['request_method'],
        $row['browser'],
        $row['os'],
        $row['device_type'],
        $row['is_bot']
    ], ';');
}

fclose($output);
exit;
?>


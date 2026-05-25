<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

define('APP_LOADED', true); 
require_once __DIR__ . '/../config/secure_settings.php'; 
require_once __DIR__ . '/../config/database.php';

if (!$pdo) {
    die("<div style='color:red;font-family:sans-serif;text-align:center;margin-top:50px;'><h1>Chyba databáze</h1><p>Nelze se připojit k databázi.</p></div>");
}

// Časové filtry
$today = date('Y-m-d');
$first_day_of_month = date('Y-m-01');

// === Hlavní statistiky (Dnes) ===
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) FROM visitor_logs WHERE is_bot = 0 AND DATE(visit_time) = ?");
$stmt->execute([$today]);
$today_visitors = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(id) FROM visitor_logs WHERE is_bot = 0 AND DATE(visit_time) = ?");
$stmt->execute([$today]);
$today_views = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(id) FROM visitor_logs WHERE is_bot = 1 AND DATE(visit_time) = ?");
$stmt->execute([$today]);
$today_bots = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(id) FROM visitor_logs WHERE status_code = 404 AND DATE(visit_time) = ?");
$stmt->execute([$today]);
$today_404 = $stmt->fetchColumn() ?: 0;

// === Hlavní statistiky (Měsíc) ===
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) FROM visitor_logs WHERE is_bot = 0 AND DATE(visit_time) >= ?");
$stmt->execute([$first_day_of_month]);
$month_visitors = $stmt->fetchColumn() ?: 0;

// Celkem unikátních IP (ever)
$stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_logs WHERE is_bot = 0");
$total_visitors = $stmt->fetchColumn() ?: 0;

// === Tabulky & Grafy ===
// Posledních 50 návštěv (včetně botů pro filtr)
$recent_visitors = $pdo->query("
    SELECT *
    FROM visitor_logs 
    ORDER BY visit_time DESC LIMIT 50
")->fetchAll();

// Získání geolokace pro nedávné návštěvníky z ip-api (batch request)
$ip_data = [];
$unique_ips = [];
foreach ($recent_visitors as $v) {
    if (!empty($v['ip_address']) && !in_array($v['ip_address'], ['::1', '127.0.0.1', 'UNKNOWN']) && !str_starts_with($v['ip_address'], '192.168.') && !str_starts_with($v['ip_address'], '10.')) {
        $unique_ips[$v['ip_address']] = true;
    }
}
$ips_to_query = array_keys($unique_ips);
if (!empty($ips_to_query)) {
    // Použití zdarma dostupného HTTP endpointu server-side
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'timeout' => 2, // rychlý timeout, aby se dashboard nezasekl
            'content' => json_encode(array_values($ips_to_query))
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents('http://ip-api.com/batch?fields=status,country,countryCode,city,query', false, $context);
    if ($response) {
        $json = json_decode($response, true);
        if (is_array($json)) {
            foreach ($json as $loc) {
                if (isset($loc['status']) && $loc['status'] === 'success') {
                    $ip_data[$loc['query']] = [
                        'country' => $loc['country'],
                        'countryCode' => $loc['countryCode'],
                        'city' => $loc['city']
                    ];
                }
            }
        }
    }
}

function get_flag_html($countryCode) {
    if (!$countryCode || strlen($countryCode) !== 2) return '<i class="fas fa-globe"></i>';
    $code = strtolower($countryCode);
    return '<img src="https://flagcdn.com/20x15/' . $code . '.png" width="20" height="15" alt="' . htmlspecialchars($countryCode) . '" style="border-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.2); display: inline-block;">';
}

// Prohlížeče
$browsers = $pdo->query("
    SELECT browser, COUNT(id) as cnt 
    FROM visitor_logs 
    WHERE is_bot = 0 
    GROUP BY browser 
    ORDER BY cnt DESC LIMIT 5
")->fetchAll();

// TOP 10 odchycených URL
$recent_events = $pdo->query("
    SELECT requested_url, COUNT(id) as cnt 
    FROM visitor_logs 
    WHERE is_bot = 0 
    GROUP BY requested_url 
    ORDER BY cnt DESC LIMIT 10
")->fetchAll();

// Denní obrat (posledních 14 dní)
$daily_chart = $pdo->query("
    SELECT DATE(visit_time) as date, COUNT(id) as views 
    FROM visitor_logs 
    WHERE is_bot = 0 AND visit_time >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) 
    GROUP BY DATE(visit_time) ORDER BY date ASC
")->fetchAll();

// Měsíční (12 měsíců)
$monthly_chart = $pdo->query("
    SELECT DATE_FORMAT(visit_time, '%Y-%m') as month, COUNT(id) as views 
    FROM visitor_logs 
    WHERE is_bot = 0 AND visit_time >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
    GROUP BY month ORDER BY month ASC
")->fetchAll();

// Příprava dat pro JSON do Chart.js
$chart_daily_labels = array_map(function($i) { return date('d.m.', strtotime($i['date'])); }, $daily_chart);
$chart_daily_data = array_column($daily_chart, 'views');

$chart_monthly_labels = array_map(function($i) { 
    $m = explode('-', $i['month']); 
    return $m[1] . '/' . $m[0]; 
}, $monthly_chart);
$chart_monthly_data = array_column($monthly_chart, 'views');

// Helper
function truncate_url($url, $length = 40) {
    if (empty($url) || $url == '-' || $url == '—') return 'Přímý vstup';
    return (strlen($url) > $length) ? substr($url, 0, $length) . '...' : $url;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokročilý Dashboard | Petr Dubský</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* === RESET & BASE === */
        :root {
            --bg-color: #0f172a;
            --surface-color: rgba(30, 41, 59, 0.7);
            --surface-hover: rgba(51, 65, 85, 0.9);
            --glass-border: rgba(255, 255, 255, 0.1);
            --accent-primary: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.5);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.15), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.15), transparent 25%);
            background-attachment: fixed;
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        ::-webkit-scrollbar-thumb { background: var(--surface-hover); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-primary); }

        .app-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* === NAVBAR === */
        .navbar {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            background: rgba(15, 23, 42, 0.8);
            border-bottom: 1px solid var(--glass-border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .nav-brand {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: linear-gradient(135deg, #60a5fa, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .btn-glass {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--glass-border);
            color: var(--text-main);
        }

        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }

        .dashboard-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            flex-grow: 1;
        }

        .page-header {
            margin-bottom: 2rem;
            animation: slideDown 0.5s ease-out forwards;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .glass-card {
            background: var(--surface-color);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.6s ease-out both;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            opacity: 0.5;
        }

        .stats-grid .glass-card:nth-child(1) { animation-delay: 0.1s; }
        .stats-grid .glass-card:nth-child(2) { animation-delay: 0.2s; }
        .stats-grid .glass-card:nth-child(3) { animation-delay: 0.3s; }
        .stats-grid .glass-card:nth-child(4) { animation-delay: 0.4s; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.875rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .icon-blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .icon-green { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .icon-purple { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
        .icon-orange { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .icon-red { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-desc {
            font-size: 0.875rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .trend-up { color: var(--success); }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 1024px) {
            .content-grid { grid-template-columns: 1fr; }
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 1rem 0.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        td {
            padding: 1.25rem 0.5rem;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            vertical-align: top;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .progress-wrapper { margin-top: 0.5rem; }
        .progress-bar {
            width: 100%; height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px; overflow: hidden;
            margin-bottom: 0.25rem;
        }
        .progress-fill {
            height: 100%; border-radius: 3px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transition: width 1s ease-out;
        }
        .progress-label {
            font-size: 0.75rem; color: var(--text-muted);
            display: flex; justify-content: space-between;
        }

        .event-list { list-style: none; }
        .event-item {
            display: flex; gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: background 0.2s;
        }
        .event-item:last-child { border-bottom: none; }
        .event-item:hover { background: rgba(255,255,255,0.02); border-radius: 0.5rem; padding-left: 0.5rem; }

        .event-icon-wrap {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.875rem; flex-shrink: 0;
            background: rgba(59, 130, 246, 0.15); color: #60a5fa;
        }

        .event-details { flex-grow: 1; overflow: hidden; }
        .event-name { font-size: 0.9rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 0.25rem; }
        .event-meta { font-size: 0.75rem; color: var(--text-muted); display: flex; justify-content: space-between; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <main class="dashboard-content">
        <header class="page-header">
            <h1 class="page-title">Přehled provozu</h1>
            <p class="page-subtitle"><i class="far fa-calendar-alt"></i> Dnes: <?php echo date('d.m.Y'); ?> | Živá analytika přímo z jádra webu</p>
        </header>

        <div class="stats-grid">
            <div class="glass-card">
                <div class="stat-header">
                    <div class="stat-title">Lidé (Dnes)</div>
                    <div class="stat-icon icon-blue"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-value"><?php echo $today_visitors; ?></div>
                <div class="stat-desc">
                    <span class="trend-up"><i class="fas fa-calendar-week"></i> <?php echo $month_visitors; ?></span> tento měsíc
                </div>
            </div>

            <div class="glass-card">
                <div class="stat-header">
                    <div class="stat-title">Zobrazení URL (Dnes)</div>
                    <div class="stat-icon icon-green"><i class="fas fa-eye"></i></div>
                </div>
                <div class="stat-value"><?php echo $today_views; ?></div>
                <div class="stat-desc">
                    <i class="fas fa-history"></i> Historicky <?php echo $total_visitors; ?> unikátních IP
                </div>
            </div>

            <div class="glass-card">
                <div class="stat-header">
                    <div class="stat-title">Chyby (404 Not Found)</div>
                    <div class="stat-icon icon-red"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-value"><?php echo $today_404; ?></div>
                <div class="stat-desc">
                    Mrtvé odkazy za dnešní den
                </div>
            </div>

            <div class="glass-card">
                <div class="stat-header">
                    <div class="stat-title">Bot Traffic (Dnes)</div>
                    <div class="stat-icon icon-orange"><i class="fas fa-robot"></i></div>
                </div>
                <div class="stat-value"><?php echo $today_bots; ?></div>
                <div class="stat-desc">
                    Googlebot, AI enginy a scrapery
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="glass-card" style="grid-column: 1 / -1; animation-delay: 0.45s;">
                <h2 class="section-title"><i class="fas fa-chart-line" style="color: var(--accent-primary)"></i> Vývoj návštěvnosti</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div style="display: flex; flex-direction: column;">
                        <h3 style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem; text-align: center;">Denní trend (14 dní)</h3>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <h3 style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem; text-align: center;">Dlouhodobý trend (12 měsíců)</h3>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card" style="animation-delay: 0.5s;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                    <h2 class="section-title" style="margin-bottom: 0;"><i class="fas fa-list-ul" style="color: var(--accent-primary)"></i> Detailní historie (Posledních 50 hitů)</h2>
                    
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <label style="color: var(--text-muted); font-size: 0.85rem; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="filterBotsToggle" checked> Skrýt roboty
                        </label>
                        <div style="position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                            <input type="text" id="filterSearchInput" placeholder="Hledat IP, URL, referrer..." style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 6px 15px 6px 32px; color: #fff; font-size: 0.85rem; width: 220px; outline: none;">
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="visitorsTable">
                        <thead>
                            <tr>
                                <th>Kdy / IP adresa</th>
                                <th>Zařízení</th>
                                <th>Zdrojová stránka (Referer)</th>
                                <th style="min-width: 120px;">Cílová URL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_visitors as $v): ?>
                            <tr class="visitor-row" data-bot="<?php echo $v['is_bot']; ?>" data-search="<?php echo htmlspecialchars(strtolower($v['ip_address'] . ' ' . $v['requested_url'] . ' ' . $v['referer_url'])); ?>">
                                <td>
                                    <div style="font-weight: 500; font-size: 1.05rem; margin-bottom: 0.2rem;">
                                        <?php echo date('d.m. H:i', strtotime($v['visit_time'])); ?>
                                    </div>
                                    <div style="color: var(--text-muted); margin-bottom: 0.2rem; display: flex; align-items: center; gap: 6px; min-height: 20px;">
                                        <?php if (isset($ip_data[$v['ip_address']])): ?>
                                            <?php 
                                            $loc = $ip_data[$v['ip_address']]; 
                                            $flag_html = get_flag_html($loc['countryCode']);
                                            ?>
                                            <span style="display: inline-flex; align-items: center;" title="<?php echo htmlspecialchars($loc['country']); ?>"><?php echo $flag_html; ?></span> 
                                            <span style="font-weight: 500; font-size: 0.85rem; padding-top: 1px;"><?php echo htmlspecialchars($loc['city'] . ', ' . $loc['country']); ?></span>
                                        <?php elseif (in_array($v['ip_address'], ['::1', '127.0.0.1', 'UNKNOWN']) || str_starts_with($v['ip_address'], '192.168.') || str_starts_with($v['ip_address'], '10.')): ?>
                                            <span style="font-size: 0.8rem; opacity: 0.5;"><i class="fas fa-network-wired"></i> Lokální spojení</span>
                                        <?php else: ?>
                                            <span style="font-size: 0.8rem; opacity: 0.5;"><i class="fas fa-globe"></i> Stát neznámý</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 0.85rem; opacity: 0.7;">
                                        IP: <?php echo substr($v['ip_address'], 0, 15); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="margin-bottom: 0.25rem;">
                                        <?php 
                                        $d = strtolower($v['device_type'] ?? 'desktop');
                                        $icon = ($d == 'mobile') ? 'fa-mobile-alt' : (($d == 'tablet') ? 'fa-tablet-alt' : 'fa-desktop'); 
                                        $d_name = ($d == 'mobile') ? 'Mobil' : (($d == 'tablet') ? 'Tablet' : 'PC');
                                        ?>
                                        <i class="fas <?php echo $icon; ?>" style="color: var(--text-muted); margin-right: 5px;"></i>
                                        <?php echo htmlspecialchars($d_name . ' - ' . $v['os'] . ' - ' . $v['browser']); ?>
                                    </div>
                                    <?php if (!array_key_exists('resolution', $v)): ?>
                                    <div style="color: #ffaa00; font-size: 0.8rem; margin-top: 5px; opacity: 0.8;">
                                        <i class="fas fa-exclamation-triangle" style="margin-right: 4px;"></i> Databáze nepřijala nový sloupec
                                    </div>
                                    <?php elseif (!empty($v['resolution'])): ?>
                                    <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 5px; opacity: 0.8;">
                                        <i class="fas fa-expand" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($v['resolution']); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; color: var(--text-muted); word-break: break-all; margin-bottom: 8px;">
                                        <i class="fas fa-arrow-right" style="margin-right:4px;"></i>
                                        <?php echo htmlspecialchars(truncate_url($v['referer_url'], 50)); ?>
                                    </div>
                                    <div>
                                    <?php if ($v['is_bot'] == 1): 
                                        $botName = 'Bot';
                                        $ua_lower = strtolower($v['user_agent']);
                                        if (strpos($ua_lower, 'googlebot') !== false) $botName = 'Google';
                                        elseif (strpos($ua_lower, 'bingbot') !== false) $botName = 'Bing';
                                        elseif (strpos($ua_lower, 'yandexbot') !== false) $botName = 'Yandex';
                                        elseif (strpos($ua_lower, 'seznam') !== false) $botName = 'Seznam';
                                        elseif (strpos($ua_lower, 'yahoo') !== false || strpos($ua_lower, 'slurp') !== false) $botName = 'Yahoo';
                                        elseif (strpos($ua_lower, 'duckduckbot') !== false) $botName = 'DuckDuckGo';
                                        elseif (strpos($ua_lower, 'ahrefsbot') !== false) $botName = 'Ahrefs';
                                        elseif (strpos($ua_lower, 'semrushbot') !== false) $botName = 'Semrush';
                                        elseif (strpos($ua_lower, 'facebook') !== false) $botName = 'Facebook';
                                        elseif (strpos($ua_lower, 'twitter') !== false || strpos($ua_lower, 'xbot') !== false) $botName = 'Twitter';
                                        else {
                                            if (preg_match('/([a-z0-9]+bot)/i', $v['user_agent'], $m)) {
                                                $botName = ucfirst($m[1]);
                                            }
                                        }
                                    ?>
                                        <span style="display: inline-block; background: rgba(255, 180, 50, 0.1); border: 1px solid rgba(255, 180, 50, 0.3); padding: 4px 12px; border-radius: 20px; color: #ffbc42; font-size: 0.8rem; font-weight: 500; white-space: nowrap;">
                                            <i class="fas fa-robot" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($botName); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; background: rgba(100, 150, 255, 0.1); border: 1px solid rgba(100, 150, 255, 0.3); padding: 4px 12px; border-radius: 20px; color: #8bb3ff; font-size: 0.8rem; font-weight: 500; white-space: nowrap;">
                                            <i class="fas fa-user" style="margin-right: 5px;"></i> Člověk
                                        </span>
                                    <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem; font-weight: 500; color: #fff; word-break: break-all; margin-bottom: 15px;">
                                        <?php echo htmlspecialchars(truncate_url($v['requested_url'], 50)); ?>
                                    </div>
                                    
                                    <?php 
                                    // Zobrazení času a scroll_depth v jednom celku
                                    $top = array_key_exists('time_on_page', $v) ? (int)$v['time_on_page'] : 0;
                                    $sdepth = array_key_exists('scroll_depth', $v) ? (int)$v['scroll_depth'] : 0;
                                    
                                    // Formátování času
                                    if ($top < 60) $t_str = $top . 's';
                                    else {
                                        $m = floor($top / 60);
                                        $s = $top % 60;
                                        $t_str = $m . 'm ' . $s . 's';
                                    }
                                    
                                    // Ochrana procent
                                    if ($sdepth > 100) $sdepth = 100;
                                    
                                    // Barva baru - pokud 0% tak šedá, jinak gradient
                                    $barBg = $sdepth > 0 ? "linear-gradient(90deg, #4b6cb7 0%, #aa77ff 100%)" : "rgba(255,255,255,0.1)";
                                    $shadow = $sdepth > 0 ? "box-shadow: 0 0 10px rgba(170, 119, 255, 0.4);" : "";
                                    ?>
                                    <div style="display: flex; flex-direction: column; gap: 6px; width: 100%; max-width: 180px;">
                                        <div style="font-size: 0.85rem; font-weight: 600; color: #fff; display: flex; align-items: center; gap: 6px;">
                                            <i class="far fa-clock" style="color: var(--text-muted); font-size: 0.9rem;"></i> <?php echo $t_str; ?>
                                        </div>
                                        
                                        <div style="width: 100%; height: 6px; background: rgba(0,0,0,0.3); border-radius: 4px; overflow: hidden; margin-top: 2px;">
                                            <div style="width: <?php echo $sdepth; ?>%; height: 100%; background: <?php echo $barBg; ?>; border-radius: 4px; transition: width 1s ease-in-out; <?php echo $shadow; ?>"></div>
                                        </div>
                                        
                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">
                                            <span>Sroll</span>
                                            <span><?php echo $sdepth; ?>%</span>
                                        </div>
                                        
                                        <?php if (!empty($v['actions_taken'])): ?>
                                        <div style="margin-top: 5px; padding: 5px 8px; background: rgba(255, 107, 107, 0.1); border-left: 2px solid #ff6b6b; color: #ff9e9e; font-size: 0.75rem; font-weight: 500; border-radius: 0 4px 4px 0;">
                                            <i class="fas fa-bolt" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($v['actions_taken']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_visitors)): ?>
                            <tr><td colspan="4" style="text-align:center; color: var(--text-muted);">Zatím žádná data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="glass-card" style="animation-delay: 0.6s;">
                    <h2 class="section-title"><i class="fas fa-fire" style="color: var(--purple)"></i> TOP 10 Nejčastějších URL</h2>
                    <ul class="event-list">
                        <?php foreach ($recent_events as $e): ?>
                        <li class="event-item">
                            <div class="event-icon-wrap"><i class="fas fa-link"></i></div>
                            <div class="event-details">
                                <div class="event-name" title="<?php echo htmlspecialchars($e['requested_url']); ?>">
                                    <?php echo htmlspecialchars($e['requested_url'] === '/' ? '/ (Home)' : truncate_url($e['requested_url'], 35)); ?>
                                </div>
                                <div class="event-meta">
                                    <span>Zobrazení</span>
                                    <span style="font-weight: 600; color: var(--text-main);"><?php echo $e['cnt']; ?>x</span>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($recent_events)): ?>
                            <li style="text-align:center; font-size: 0.9rem; color: var(--text-muted); padding: 1rem;">Zatím žádná data</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="glass-card" style="animation-delay: 0.7s;">
                    <h2 class="section-title"><i class="fas fa-chart-bar" style="color: var(--success)"></i> Top Prohlížeče</h2>
                    <div style="display:flex; flex-direction:column; gap: 1rem; margin-top: 1rem;">
                        <?php 
                        $total_b = array_sum(array_column($browsers, 'cnt')) ?: 1;
                        foreach ($browsers as $b): 
                            $pct = round(($b['cnt'] / $total_b) * 100);
                        ?>
                        <div class="progress-wrapper" style="margin: 0;">
                            <div class="progress-label" style="font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--text-main);">
                                <span><?php echo htmlspecialchars($b['browser']); ?></span>
                                <span><?php echo $pct; ?>% (<?php echo $b['cnt']; ?>)</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $pct; ?>%; background: linear-gradient(90deg, #10b981, #34d399);"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Outfit', sans-serif";
    
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false } },
            y: { grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false }, beginAtZero: true }
        }
    };

    new Chart(document.getElementById('dailyChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_daily_labels); ?>,
            datasets: [{
                label: 'Zobrazení',
                data: <?php echo json_encode($chart_daily_data); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#0f172a',
                pointBorderColor: '#3b82f6',
                pointHoverBackgroundColor: '#3b82f6'
            }]
        },
        options: commonOptions
    });

    new Chart(document.getElementById('monthlyChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_monthly_labels); ?>,
            datasets: [{
                label: 'Zobrazení',
                data: <?php echo json_encode($chart_monthly_data); ?>,
                backgroundColor: 'rgba(139, 92, 246, 0.7)',
                hoverBackgroundColor: 'rgba(139, 92, 246, 1)',
                borderRadius: 4
            }]
        },
        options: commonOptions
    });

    // === JS Logika pro Instantní Filtrování tabulky v Dashboardu ===
    const searchInput = document.getElementById('filterSearchInput');
    const bToggle = document.getElementById('filterBotsToggle');
    const rows = document.querySelectorAll('.visitor-row');
    
    function applyFilters() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const hideBots = bToggle ? bToggle.checked : false;
        
        rows.forEach(row => {
            const isBot = row.getAttribute('data-bot') === "1";
            const searchData = row.getAttribute('data-search') || '';
            
            let show = true;
            if (hideBots && isBot) show = false;
            if (query !== "" && searchData.indexOf(query) === -1) show = false;
            
            row.style.display = show ? "" : "none";
        });
    }

    if(searchInput) searchInput.addEventListener('input', applyFilters);
    if(bToggle) bToggle.addEventListener('change', applyFilters);
    
    // Spuštění po načtení stránky (např. aplikuje defaultní Hide Bots)
    if(rows.length > 0) applyFilters();
</script>

</body>
</html>


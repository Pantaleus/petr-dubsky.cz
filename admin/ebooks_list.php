<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

define('APP_LOADED', true); // Pro config_secure.php, pokud by ho db_config.php načítal podmíněně
require_once __DIR__ . '/../config/secure_settings.php'; // Načtení konstant
require_once __DIR__ . '/../config/database.php';      // Vytvoření $pdo

$ebooks = [];
$admin_lang_for_list = 'cz'; // Jazyk pro zobrazení titulků v seznamu (můžete změnit nebo udělat dynamické)

// Načtení zpráv ze session (pro přesměrování po uložení/smazání)
$admin_message = $_SESSION['admin_message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['admin_message'], $_SESSION['message_type']); // Smazat po zobrazení

$db_listing_error = null; // Pro chyby při načítání z DB

try {
    $sql = "SELECT e.id, e.slug, e.publish_date, e.author, et.title 
            FROM ebooks e
            LEFT JOIN ebook_translations et ON e.id = et.ebook_id AND et.lang_code = :lang_code
            ORDER BY e.publish_date DESC, e.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':lang_code', $admin_lang_for_list, PDO::PARAM_STR);
    $stmt->execute();
    $ebooks = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_listing_error = "Chyba při načítání e-booků: " . $e->getMessage();
    error_log($db_listing_error . " SQL: " . $sql); // Logování chyby
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-booky Administrace | P.D. Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* === RESET & BASE === */
        :root {
            --bg-color: #0f172a;
            --surface-color: rgba(30, 41, 59, 0.7);
            --surface-hover: rgba(51, 65, 85, 0.9);
            --glass-border: rgba(255, 255, 255, 0.1);
            --accent-primary: #3b82f6;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
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

        /* === NAVBAR EXTERNAL COMPATIBILITY === */
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

        .btn-sm {
            padding: 0.3rem 0.75rem;
            font-size: 0.8rem;
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

        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.5s ease-out forwards;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
        }

        /* === E-BOOK CARDS === */
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
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            opacity: 0.5;
        }

        .ebook-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        .ebook-grid > * {
            animation: fadeUp 0.6s ease-out both;
        }

        .ebook-card {
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }

        .ebook-date {
            font-size: 0.8rem;
            color: #c084fc;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(192, 132, 252, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            width: fit-content;
        }

        .ebook-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-main);
            line-height: 1.4;
            flex-grow: 1;
        }

        .ebook-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .badge {
            padding: 0.25rem 0.6rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px;
            font-size: 0.75rem;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .ebook-actions {
            display: flex;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--glass-border);
            margin-top: auto;
        }

        .ebook-actions .btn {
            flex: 1;
            justify-content: center;
        }

        .message-card {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #34d399;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
        }

        .message-card.error {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        /* Animations */
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
    <?php include 'navbar.php'; ?>

    <main class="dashboard-content">
        <header class="page-header">
            <div>
                <h1 class="page-title">Publikace E-booků</h1>
                <p class="page-subtitle"><i class="fas fa-book-open"></i> Přehled e-booků volně ke stažení</p>
            </div>
            <div>
                <a href="edit_ebook.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Nový e-book
                </a>
            </div>
        </header>

        <?php if (!empty($admin_message)): ?>
            <div class="message-card <?php echo $message_type === 'error' ? 'error' : ''; ?>">
                <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                <?php echo htmlspecialchars($admin_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($db_listing_error)): ?>
            <div class="message-card error">
                <i class="fas fa-bomb"></i>
                <?php echo htmlspecialchars($db_listing_error); ?>
            </div>
        <?php endif; ?>

        <div class="ebook-grid">
            <?php if (!empty($ebooks)): ?>
                <?php foreach ($ebooks as $index => $ebook_item): ?>
                    <div class="glass-card ebook-card" style="animation-delay: <?php echo ($index % 5) * 0.1; ?>s;">
                        <div class="ebook-date">
                            <i class="far fa-calendar-alt"></i> 
                            <?php echo htmlspecialchars(date("d.m.Y", strtotime($ebook_item['publish_date']))); ?>
                        </div>
                        
                        <h3 class="ebook-title">
                            <?php echo htmlspecialchars($ebook_item['title'] ?? 'N/A - Chybí překlad (' . strtoupper($admin_lang_for_list) . ')'); ?>
                        </h3>
                        
                        <div class="ebook-meta">
                            <span class="badge"><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($ebook_item['author']); ?></span>
                            <span class="badge"><i class="fas fa-link"></i> /<?php echo htmlspecialchars($ebook_item['slug']); ?></span>
                            <span class="badge"><i class="fas fa-hashtag"></i> ID: <?php echo $ebook_item['id']; ?></span>
                        </div>
                        
                        <div class="ebook-actions">
                            <a href="edit_ebook.php?id=<?php echo $ebook_item['id']; ?>" class="btn btn-glass btn-sm">
                                <i class="fas fa-pen"></i> Upravit
                            </a>
                            <a href="delete_ebook.php?id=<?php echo $ebook_item['id']; ?>" onclick="return confirm('Opravdu chcete smazat tento e-book a všechny jeho související údaje? Tato akce je nevratná!');" class="btn btn-danger btn-sm" style="flex: 0 0 auto;">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php elseif (empty($db_listing_error)): ?>
                <div class="glass-card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                    <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Zatím zde nejsou žádné e-booky</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Nahrajte svůj první e-book do knihovny.</p>
                    <a href="edit_ebook.php" class="btn btn-success"><i class="fas fa-plus"></i> Nový PDF E-book</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>

<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

define('APP_LOADED', true); 
require_once __DIR__ . '/../config/secure_settings.php'; 
require_once __DIR__ . '/../config/database.php';      

// === CSRF OCHRANA ===
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$articles = [];
$admin_lang_for_list = 'cz'; // Jazyk pro zobrazení titulků v seznamu

// Načtení zpráv ze session
$admin_message = $_SESSION['admin_message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['admin_message'], $_SESSION['message_type']); 

$db_listing_error = null;

$article_type = isset($_GET['type']) && $_GET['type'] === 'raspberry' ? 'raspberry' : 'blog';

try {
    $sql = "SELECT a.id, a.slug, a.publish_date, a.author, at.title 
            FROM articles a
            LEFT JOIN article_translations at ON a.id = at.article_id AND at.lang_code = :lang_code
            WHERE a.article_type = :article_type
            ORDER BY a.publish_date DESC, a.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':lang_code', $admin_lang_for_list, PDO::PARAM_STR);
    $stmt->bindParam(':article_type', $article_type, PDO::PARAM_STR);
    $stmt->execute();
    $articles = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_listing_error = "Chyba při načítání článků blogu: " . $e->getMessage();
    error_log($db_listing_error . " SQL: " . $sql);
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $article_type === 'raspberry' ? 'Raspberry Pi' : 'Blog'; ?> Administrace | Petr Dubský</title>
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

        /* === BLOG CARDS === */
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

        .article-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        .article-grid > * {
            animation: fadeUp 0.6s ease-out both;
        }
        .article-grid > *:nth-child(n) { animation-delay: 0.1s; }
        .article-grid > *:nth-child(2n) { animation-delay: 0.2s; }
        .article-grid > *:nth-child(3n) { animation-delay: 0.3s; }

        .article-card {
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }

        .article-date {
            font-size: 0.8rem;
            color: var(--accent-primary);
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(59, 130, 246, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            width: fit-content;
        }

        .article-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-main);
            line-height: 1.4;
            flex-grow: 1;
        }

        .article-meta {
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

        .article-actions {
            display: flex;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--glass-border);
            margin-top: auto;
        }

        .article-actions .btn {
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
    <!-- Navbar component -->
    <?php include 'navbar.php'; ?>

    <main class="dashboard-content">
        <header class="page-header">
            <div>
                <h1 class="page-title"><?php echo $article_type === 'raspberry' ? 'Raspberry Pi články' : 'Publikační činnost'; ?></h1>
                <p class="page-subtitle"><i class="<?php echo $article_type === 'raspberry' ? 'fas fa-microchip' : 'fas fa-newspaper'; ?>"></i> Správa, úprava a tvorba <?php echo $article_type === 'raspberry' ? 'záznamů k RPI' : 'článků v magazínu'; ?></p>
            </div>
            <div>
                <a href="edit_article.php?type=<?php echo $article_type; ?>" class="btn btn-success">
                    <i class="fas fa-plus"></i> Nový záznam
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

        <div class="article-grid">
            <?php if (!empty($articles)): ?>
                <?php foreach ($articles as $index => $article_item): ?>
                    <div class="glass-card article-card" style="animation-delay: <?php echo ($index % 5) * 0.1; ?>s;">
                        <div class="article-date">
                            <i class="far fa-calendar-alt"></i> 
                            <?php echo htmlspecialchars(date("d.m.Y", strtotime($article_item['publish_date']))); ?>
                        </div>
                        
                        <h3 class="article-title">
                            <?php echo htmlspecialchars($article_item['title'] ?? 'N/A - Chybí překlad (' . strtoupper($admin_lang_for_list) . ')'); ?>
                        </h3>
                        
                        <div class="article-meta">
                            <span class="badge"><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($article_item['author']); ?></span>
                            <span class="badge"><i class="fas fa-link"></i> /<?php echo htmlspecialchars($article_item['slug']); ?></span>
                            <span class="badge"><i class="fas fa-hashtag"></i> ID: <?php echo $article_item['id']; ?></span>
                        </div>
                        
                        <div class="article-actions">
                            <a href="edit_article.php?id=<?php echo $article_item['id']; ?>" class="btn btn-glass btn-sm">
                                <i class="fas fa-pen"></i> Upravit
                            </a>
                            <a href="delete_article.php?id=<?php echo $article_item['id']; ?>&token=<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" onclick="return confirm('Opravdu chcete smazat tento článek a všechny jeho související údaje? Tato akce je nevratná!');" class="btn btn-danger btn-sm" style="flex: 0 0 auto;">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php elseif (empty($db_listing_error)): ?>
                <div class="glass-card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                    <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Zatím zde nejsou žádné články</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Vytvořte svůj první příspěvek a začněte tvořit obsah.</p>
                    <a href="edit_article.php?type=<?php echo $article_type; ?>" class="btn btn-success"><i class="fas fa-plus"></i> Napsat první článek</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>


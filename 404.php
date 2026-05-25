<?php
// 404.php - Vlastní chybová stránka 404

define('APP_LOADED', true);
define('IS_HOME_PAGE', false);
$base_path = '';

// Nastavíme správný HTTP kód
http_response_code(404);

// Načtení jazykové konfigurace
require_once __DIR__ . '/lang/config.php';
require_once $available_langs[$current_lang_code];

// Tracker analytiky a DB konfigurace
require_once __DIR__ . '/config/secure_settings.php';

$html_lang_map = [
    'cz' => 'cs',
    'en' => 'en',
    'it' => 'it'
];
$html_lang_code = $html_lang_map[$current_lang_code] ?? 'cs';

// SEO Meta tagy (základní pro 404)
$seo = [
    'title' => '404 - ' . ($lang['article_not_found_title'] ?? 'Stránka nenalezena / Page not found'),
    'description' => $lang['article_not_found_message'] ?? 'Omlouváme se, ale požadovaná stránka neexistuje. Můžete se vrátit na úvodní stránku kliknutím na tlačítko níže.',
    'keywords' => '',
    'og_title' => '404 - Stránka nenalezena / Page not found',
    'og_description' => 'Omlouváme se, ale požadovaná stránka neexistuje.'
];

ob_start();
include __DIR__ . '/includes/cookie-notice.php';
$cookie_notice_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($html_lang_code); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seo['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon a ikony -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">
    
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .error-404-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
            text-align: center;
            padding: 2rem;
            color: var(--text-light);
        }
        .error-404-title {
            font-size: 6rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .error-404-subtitle {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .error-404-text {
            font-size: 1.2rem;
            max-width: 600px;
            margin-bottom: 3rem;
            color: var(--text-muted);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <?php echo $cookie_notice_html; ?>
    
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="error-404-container">
        <h1 class="error-404-title">404</h1>
        <h2 class="error-404-subtitle"><?php echo htmlspecialchars($seo['title']); ?></h2>
        <p class="error-404-text"><?php echo htmlspecialchars($seo['description']); ?></p>
        <a href="/index.php?lang=<?php echo $current_lang_code; ?>" class="btn btn-primary">
            <?php echo htmlspecialchars($lang['nav_home'] ?? 'Home'); ?>
        </a>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script src="/scripts/lang-constants.php?lang=<?php echo $current_lang_code; ?>"></script>
    <script src="/scripts/script.js" defer></script>
    <script src="/scripts/aria.js" defer></script>
</body>
</html>


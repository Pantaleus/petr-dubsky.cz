<?php
// blog/article.php - Zobrazení detailu článku (načítání z DB)

// --- Načtení konfigurace databáze ---
require_once __DIR__ . '/../config/database.php'; // Použije se globální $pdo proměnná

// --- Načtení konfigurace hlavního webu (jazyky atd.) ---
if (!defined('APP_LOADED')) define('APP_LOADED', true);
define('IS_HOME_PAGE', false);
$base_path = '../';
require_once __DIR__ . '/../lang/config.php';

// --- Získání slug článku z URL a načtení dat z DB ---
$article_slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$article_full_data = null; // Bude obsahovat všechna data článku (metadata i překlad)
$db_error_message_article = null;

if ($article_slug) {
    try {
        $sql = "SELECT 
                    a.id, 
                    a.slug, 
                    a.author, 
                    a.publish_date,
                    at.meta_title,
                    at.meta_description,
                    at.title,
                    at.excerpt,
                    at.content
                FROM articles a
                JOIN article_translations at ON a.id = at.article_id
                WHERE a.slug = :slug AND at.lang_code = :lang_code AND a.article_type = 'blog'
                LIMIT 1"; // Očekáváme jen jeden záznam

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':slug', $article_slug, PDO::PARAM_STR);
        $stmt->bindParam(':lang_code', $current_lang_code, PDO::PARAM_STR);
        $stmt->execute();

        $article_full_data = $stmt->fetch(); // Načteme jeden řádek

        if (!$article_full_data) {
            // Pokus o načtení v defaultním jazyce, pokud v aktuálním není
            if ($current_lang_code !== $default_lang) {
                $stmt_default = $pdo->prepare(str_replace("at.lang_code = :lang_code", "at.lang_code = :default_lang_code", $sql));
                $stmt_default->bindParam(':slug', $article_slug, PDO::PARAM_STR);
                $stmt_default->bindParam(':default_lang_code', $default_lang, PDO::PARAM_STR);
                $stmt_default->execute();
                $article_full_data = $stmt_default->fetch();
                if ($article_full_data) {
                    // Pokud nalezeno v defaultním jazyce, můžeme upozornit nebo přesměrovat
                    // Pro jednoduchost zde jen zobrazíme obsah v defaultním jazyce
                    // Můžete zde přidat logiku pro zobrazení zprávy o chybějícím překladu
                }
            }
        }

    } catch(PDOException $e) {
        error_log("Chyba při načítání článku '{$article_slug}' z DB: " . $e->getMessage());
        $db_error_message_article = $lang['article_db_error'] ?? "An error occurred while loading the article. Please try again later."; // Přidejte do lang
    }
}

// Pokud článek nebyl nalezen ani po fallbacku nebo je slug prázdný
if (!$article_full_data) {
    $page_title_override = $lang['article_not_found_title'] ?? "Article Not Found";
    $error_message_display = $db_error_message_article ?: ($lang['article_not_found_message'] ?? "The requested article could not be found.");
}


// Pomocná funkce pro generování URL s jazykovým parametrem
function lang_url_blog_article($lang_code_param, $article_slug_param, $base_url = 'article.php') {
    global $current_lang_code;
    // Pokud je slug null (např. chyba načítání), odkazujeme na blog index
    if ($article_slug_param === null) {
        return 'index.php?lang=' . $lang_code_param;
    }
    return $base_url . '?slug=' . urlencode($article_slug_param) . '&lang=' . $lang_code_param;
}

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang_code); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo $article_full_data ? htmlspecialchars($article_full_data['meta_title']) : htmlspecialchars($page_title_override ?? $lang['nav_blog']); ?> - <?php echo htmlspecialchars($lang['meta_title']); ?></title>
    <?php if ($article_full_data && !empty($article_full_data['meta_description'])): ?>
        <meta name="description" content="<?php echo htmlspecialchars($article_full_data['meta_description']); ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="../css/style.css"> <!-- Cesta opravena -->
    <link rel="stylesheet" href="css/blog.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <!-- ========= MAIN ARTICLE CONTENT ========= -->
    <main class="blog-article-main section-padding">
        <div class="container">
            <?php if ($article_full_data): ?>
                <article class="article-full">
                    <header class="article-full-header">
                        <h1 class="article-full-title"><?php echo htmlspecialchars($article_full_data['title']); ?></h1>
                        <div class="article-full-meta">
                            <span><?php echo htmlspecialchars(date("F j, Y", strtotime($article_full_data['publish_date']))); ?></span>
                            <?php if (!empty($article_full_data['author'])): ?>
                                <span>| By <?php echo htmlspecialchars($article_full_data['author']); ?></span>
                            <?php endif; ?>
                        </div>
                    </header>
                    
                    <div class="article-full-content">
                        <?php echo $article_full_data['content']; // Obsah je již HTML, takže bez htmlspecialchars zde ?>
                    </div>

                    <div class="article-navigation">
                        <a href="index.php?lang=<?php echo $current_lang_code; ?>" class="btn btn-secondary">
                            « <?php echo htmlspecialchars($lang['blog_back_to_list'] ?? 'Back to Blog'); // Přidejte do lang ?>
                        </a>
                        <!-- Zde by mohly být odkazy na předchozí/následující článek -->
                    </div>
                </article>
            <?php else: ?>
                <div class="article-not-found">
                    <h2><?php echo htmlspecialchars($page_title_override ?? ($lang['article_not_found_title'] ?? "Error")); ?></h2>
                    <p><?php echo htmlspecialchars($error_message_display); ?></p>
                    <a href="index.php?lang=<?php echo $current_lang_code; ?>" class="btn btn-primary">
                        « <?php echo htmlspecialchars($lang['blog_back_to_list'] ?? 'Back to Blog'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script src="../scripts/lang-constants.php?lang=<?php echo $current_lang_code; ?>"></script>
    <script src="../scripts/script.js" defer></script>
    <script src="../scripts/aria.js" defer></script>
</body>
</html>


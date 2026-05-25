<?php
// blog/index.php - Hlavní stránka blogu (načítání z DB)


// --- Načtení konfigurace databáze ---

require_once __DIR__ . '/../config/database.php'; // Použije se globální $pdo proměnná

// --- Načtení konfigurace hlavního webu (jazyky atd.) ---
if (!defined('APP_LOADED')) define('APP_LOADED', true);
define('IS_HOME_PAGE', false);
$base_path = '../';
require_once __DIR__ . '/../lang/config.php';
// --- Načtení článků z databáze ---
$articles_from_db = [];
try {
    // Připravíme SQL dotaz pro získání všech článků a jejich překladů pro aktuální jazyk
    // Seřadíme podle data publikování sestupně
    $sql = "SELECT 
                a.id, 
                a.slug, 
                a.author, 
                a.publish_date,
                at.title,
                at.excerpt,
                at.meta_title AS article_meta_title,       -- Přejmenováno, aby nekolidovalo
                at.meta_description AS article_meta_description -- Přejmenováno
            FROM articles a
            JOIN article_translations at ON a.id = at.article_id
            WHERE at.lang_code = :lang_code AND a.article_type = 'blog'
            ORDER BY a.publish_date DESC, a.id DESC"; // Sekundární řazení podle ID pro konzistenci

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':lang_code', $current_lang_code, PDO::PARAM_STR);
    $stmt->execute();

    $articles_from_db = $stmt->fetchAll();

} catch(PDOException $e){
    // Zalogovat chybu a případně zobrazit uživateli přátelskou zprávu
    error_log("Chyba při načítání článků z DB: " . $e->getMessage());
    // Pro uživatele můžete nastavit prázdné pole nebo chybovou zprávu
    // $articles_from_db = []; // Už je definováno
    $db_error_message = "Během načítání článků došlo k chybě. Zkuste to prosím později."; // Toto by mělo být také v jazykovém souboru
}


// Pomocná funkce pro generování URL s jazykovým parametrem (z hlavního index.php)
function lang_url_blog($lang_code_param, $base_url = 'index.php', $article_slug_param = null) {
    global $current_lang_code; // Přístup k aktuálnímu jazyku pro odkazování
    $url = $base_url . '?lang=' . $lang_code_param;
    if ($article_slug_param && $base_url === 'article.php') {
        $url .= '&slug=' . $article_slug_param;
    }
    return $url;
}

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang_code); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo htmlspecialchars($lang['nav_blog']); ?> - <?php echo htmlspecialchars($lang['meta_title']); ?></title>
    <meta name="description" content="Insights and articles from Pietro Dubsky on IT development, security, and system administration.">

    <link rel="stylesheet" href="../css/style.css"> <!-- Cesta opravena -->
    <link rel="stylesheet" href="css/blog.css">

</head>
<body>

    <!-- ========= HEADER (Stejný jako na hlavním webu) ========= -->
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <!-- ========= MAIN BLOG CONTENT ========= -->
    <main class="blog-main section-padding">
        <div class="container">
            <h1 class="page-title"><?php echo htmlspecialchars($lang['nav_blog']); ?></h1>

            <?php if (isset($db_error_message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($db_error_message); ?></p>
            <?php endif; ?>

            <div class="view-toggle-container">
                <span class="view-toggle-label"><?php echo htmlspecialchars($lang['blog_view_format'] ?? 'Formát výpisu:'); ?></span>
                <div class="view-toggle-buttons">
                    <button class="view-toggle-btn" data-view="list" aria-label="<?php echo htmlspecialchars($lang['blog_view_classic_title'] ?? 'Klasické zobrazení'); ?>" title="<?php echo htmlspecialchars($lang['blog_view_classic_title'] ?? 'Klasické zobrazení'); ?>">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <?php echo htmlspecialchars($lang['blog_view_classic'] ?? 'Klasický'); ?>
                    </button>
                    <button class="view-toggle-btn" data-view="grid" aria-label="<?php echo htmlspecialchars($lang['blog_view_modern_title'] ?? 'Moderní rozvržení'); ?>" title="<?php echo htmlspecialchars($lang['blog_view_modern_title'] ?? 'Moderní rozvržení'); ?>">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <?php echo htmlspecialchars($lang['blog_view_modern'] ?? 'Moderní'); ?>
                    </button>
                </div>
            </div>

            <div class="articles-list" id="articles-list-container">
                <?php if (!empty($articles_from_db)): ?>
                    <?php foreach ($articles_from_db as $article_item): ?>
                        <article class="article-item">
                            <h2 class="article-item-title">
                                <a href="article.php?slug=<?php echo urlencode($article_item['slug']); ?>&lang=<?php echo $current_lang_code; ?>">
                                    <?php echo htmlspecialchars($article_item['title']); ?>
                                </a>
                            </h2>
                            <div class="article-item-meta">
                                <span><?php echo htmlspecialchars(date("F j, Y", strtotime($article_item['publish_date']))); ?></span>
                                <?php if (!empty($article_item['author'])): ?>
                                    <span>| By <?php echo htmlspecialchars($article_item['author']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="article-item-excerpt">
                                <p><?php echo htmlspecialchars($article_item['excerpt']); ?></p>
                            </div>
                            <a href="article.php?slug=<?php echo urlencode($article_item['slug']); ?>&lang=<?php echo $current_lang_code; ?>" class="btn btn-secondary btn-small">
                                <?php echo htmlspecialchars($lang['read_more']); ?>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php elseif (!isset($db_error_message)): // Zobrazit "No articles" jen pokud nebyla DB chyba ?>
                    <p><?php echo htmlspecialchars($lang['blog_no_articles_found'] ?? 'No articles found yet. Check back soon!'); // Přidejte klíč do hlavních lang souborů ?></p>
                <?php endif; ?>
            </div>
            <!-- Zde by mohla být paginace, pokud by bylo mnoho článků -->
        </div>
    </main>

    <!-- ========= FOOTER (Stejný jako na hlavním webu) ========= -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script src="../scripts/lang-constants.php?lang=<?php echo $current_lang_code; ?>"></script>
    <script src="../scripts/script.js" defer></script>
    <script src="../scripts/aria.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const articlesList = document.getElementById('articles-list-container');
            const toggleBtns = document.querySelectorAll('.view-toggle-btn');
            
            // Check saved preference
            const savedView = localStorage.getItem('blogViewPreference') || 'list';
            
            // Apply initial view
            applyView(savedView);
            
            // Handle clicks
            toggleBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const view = btn.getAttribute('data-view');
                    applyView(view);
                    localStorage.setItem('blogViewPreference', view);
                });
            });
            
            function applyView(viewType) {
                // Update active state on buttons
                toggleBtns.forEach(btn => {
                    if (btn.getAttribute('data-view') === viewType) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });
                
                // Toggle classes on container
                if (viewType === 'grid') {
                    articlesList.classList.add('articles-list-grid');
                } else {
                    articlesList.classList.remove('articles-list-grid');
                }
            }
        });
    </script>
    <script src="../js/main.js"></script> <!-- Cesta opravena -->
</body>
</html>


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

// Jazyky pro překlady v adminu
$available_admin_langs = ['en', 'cz', 'it']; 

$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_editing = $article_id > 0;

$article_data = [
    'slug' => '',
    'author' => $_SESSION['admin_username'], 
    'publish_date' => date('Y-m-d'),
    'article_type' => isset($_GET['type']) && $_GET['type'] === 'raspberry' ? 'raspberry' : 'blog',
    'translations' => []
];

foreach ($available_admin_langs as $lang_code) {
    $article_data['translations'][$lang_code] = [
        'meta_title' => '',
        'meta_description' => '',
        'title' => '',
        'excerpt' => '',
        'content' => ''
    ];
}

// ... (zbytek kódu pro načítání dat a zpracování formuláře zůstává stejný jako v předchozí odpovědi) ...
// Tento kód je zde pro kontext, ale není třeba ho měnit, pokud již funguje správně.
if ($is_editing) {
    try {
        $stmt_meta = $pdo->prepare("SELECT slug, author, publish_date, article_type FROM articles WHERE id = :id");
        $stmt_meta->bindParam(':id', $article_id, PDO::PARAM_INT);
        $stmt_meta->execute();
        $meta = $stmt_meta->fetch();

        if ($meta) {
            $article_data['slug'] = $meta['slug'];
            $article_data['author'] = $meta['author'];
            $article_data['publish_date'] = $meta['publish_date'];
            $article_data['article_type'] = $meta['article_type'] ?? 'blog';

            $stmt_translations = $pdo->prepare("SELECT lang_code, meta_title, meta_description, title, excerpt, content FROM article_translations WHERE article_id = :article_id");
            $stmt_translations->bindParam(':article_id', $article_id, PDO::PARAM_INT);
            $stmt_translations->execute();
            while ($trans = $stmt_translations->fetch()) {
                if (isset($article_data['translations'][$trans['lang_code']])) {
                    $article_data['translations'][$trans['lang_code']] = $trans;
                }
            }
        } else {
            header("Location: index.php?status=notfound");
            exit;
        }
    } catch (PDOException $e) {
        $admin_error = "Chyba při načítání článku pro editaci: " . $e->getMessage();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ověření CSRF tokenu
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security Error: Neplatný bezpečnostní CSRF klíč.");
    }

    // ... (zpracování POST dat, validace, ukládání do DB - tento kód zůstává stejný) ...
    // Ujistěte se, že obsah z TinyMCE ($_POST['translations'][$lang_code]['content']) se správně ukládá.
    // TinyMCE by měl automaticky aktualizovat hodnotu textarea před odesláním formuláře.
    $slug = trim($_POST['slug']);
    $author = trim($_POST['author']);
    $publish_date = trim($_POST['publish_date']);
    $article_type_post = isset($_POST['article_type']) && $_POST['article_type'] === 'raspberry' ? 'raspberry' : 'blog';
    $translations_post = $_POST['translations'] ?? [];

    if (empty($slug) || empty($publish_date)) {
        $admin_error = "Slug a datum publikování jsou povinné.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($is_editing) {
                $stmt_update_article = $pdo->prepare("UPDATE articles SET slug = :slug, author = :author, publish_date = :publish_date, article_type = :article_type WHERE id = :id");
                $stmt_update_article->execute([
                    ':slug' => $slug,
                    ':author' => $author,
                    ':publish_date' => $publish_date,
                    ':article_type' => $article_type_post,
                    ':id' => $article_id
                ]);
                $current_article_id = $article_id;
            } else {
                $stmt_insert_article = $pdo->prepare("INSERT INTO articles (slug, author, publish_date, article_type) VALUES (:slug, :author, :publish_date, :article_type)");
                $stmt_insert_article->execute([
                    ':slug' => $slug,
                    ':author' => $author,
                    ':publish_date' => $publish_date,
                    ':article_type' => $article_type_post
                ]);
                $current_article_id = $pdo->lastInsertId();
            }

            foreach ($available_admin_langs as $lang_code) {
                if (isset($translations_post[$lang_code])) {
                    $trans_data = $translations_post[$lang_code];
                    $stmt_check_trans = $pdo->prepare("SELECT id FROM article_translations WHERE article_id = :article_id AND lang_code = :lang_code");
                    $stmt_check_trans->execute([':article_id' => $current_article_id, ':lang_code' => $lang_code]);
                    $existing_trans_id = $stmt_check_trans->fetchColumn();

                    if ($existing_trans_id) {
                        $stmt_trans = $pdo->prepare("UPDATE article_translations SET meta_title = :meta_title, meta_description = :meta_description, title = :title, excerpt = :excerpt, content = :content WHERE id = :id");
                        $stmt_trans->execute([
                            ':meta_title' => $trans_data['meta_title'],
                            ':meta_description' => $trans_data['meta_description'],
                            ':title' => $trans_data['title'],
                            ':excerpt' => $trans_data['excerpt'],
                            ':content' => $trans_data['content'], // Obsah z TinyMCE
                            ':id' => $existing_trans_id
                        ]);
                    } else {
                        if (!empty(trim($trans_data['title']))) {
                             $stmt_trans = $pdo->prepare("INSERT INTO article_translations (article_id, lang_code, meta_title, meta_description, title, excerpt, content) VALUES (:article_id, :lang_code, :meta_title, :meta_description, :title, :excerpt, :content)");
                            $stmt_trans->execute([
                                ':article_id' => $current_article_id,
                                ':lang_code' => $lang_code,
                                ':meta_title' => $trans_data['meta_title'],
                                ':meta_description' => $trans_data['meta_description'],
                                ':title' => $trans_data['title'],
                                ':excerpt' => $trans_data['excerpt'],
                                ':content' => $trans_data['content'] // Obsah z TinyMCE
                            ]);
                        }
                    }
                }
            }
            $pdo->commit();
            header("Location: blog.php?type=" . $article_type_post . "&status=saved");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $admin_error = "Chyba při ukládání článku: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_editing ? 'Upravit záznam' : 'Nový záznam'; ?> | P.D. Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- TinyMCE s API KLÍČEM -->
    <script src="https://cdn.tiny.cloud/1/n49p4gpzb6k70f9vj6k5nyow8on710aecvc1wzb5h0ib7h0g/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        tinymce.init({
          selector: 'textarea.tinymce-editor',
          // Zapnutí dark mode pro editor, aby hezky ladil s designem
          skin: 'oxide-dark',
          content_css: 'dark',
          plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
          toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help | code',
          height: 450,
          setup: function (editor) {
            editor.on('change', function () {
              tinymce.triggerSave();
            });
          }
        });
      });
    </script>
    
    <style>
        /* === RESET & BASE (Z dashboardu) === */
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.15), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.15), transparent 25%);
            background-attachment: fixed;
            color: var(--text-main);
            min-height: 100vh;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        ::-webkit-scrollbar-thumb { background: var(--surface-hover); border-radius: 4px; }

        .app-container { display: flex; flex-direction: column; min-height: 100vh; }

        .dashboard-content {
            padding: 2rem; max-width: 1200px; margin: 0 auto; width: 100%; flex-grow: 1;
        }

        .page-header {
            margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; 
            animation: slideDown 0.5s ease-out forwards;
        }
        .page-title { font-size: 2rem; font-weight: 600; margin-bottom: 0.25rem; }
        .page-subtitle { color: var(--text-muted); font-size: 1rem; }

        .btn {
            padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 500; font-size: 1rem;
            text-decoration: none; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;
            border: 1px solid transparent; cursor: pointer;
        }
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669); color: white; border: none;
        }
        .btn-success:hover {
            transform: translateY(-2px); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }

        /* === FORM GLASS CARDS === */
        .glass-card {
            background: var(--surface-color); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border); border-radius: 1rem; padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); margin-bottom: 2rem;
            animation: fadeUp 0.6s ease-out both;
        }
        .section-title {
            font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--accent-primary);
            display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.75rem;
        }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-muted); font-size: 0.9rem;
        }
        .form-control {
            width: 100%; padding: 0.75rem 1rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border);
            border-radius: 0.5rem; color: var(--text-main); font-family: 'Outfit', sans-serif; font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none; border-color: var(--accent-primary); background: rgba(0,0,0,0.4);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        /* === MODERN TABS === */
        .tabs {
            display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem;
        }
        .tablink {
            background: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px solid var(--glass-border);
            padding: 0.5rem 1.5rem; border-radius: 999px; cursor: pointer; font-family: 'Outfit', sans-serif; font-weight: 500;
            transition: all 0.3s;
        }
        .tablink:hover { background: rgba(255,255,255,0.1); color: var(--text-main); }
        .tablink.active {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; border-color: transparent;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }
        .tabcontent { display: none; animation: fadeUp 0.3s ease-out; }

        .message-card {
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5;
            padding: 1rem 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;
        }

        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Navbar z dashboardu sem natáhneme externě, ale CSS mu dáme zde preventivně, logistika: */
        .navbar {
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); background: rgba(15, 23, 42, 0.8);
            border-bottom: 1px solid var(--glass-border); padding: 1rem 2rem; display: flex;
            justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;
        }
        .nav-brand { font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; background: linear-gradient(135deg, #60a5fa, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .btn-glass { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); color: var(--text-main); padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; font-size:0.875rem;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
        .btn-danger-nav { background: rgba(239, 68, 68, 0.1); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; font-size:0.875rem;}
        .btn-danger-nav:hover { background: var(--danger); color: white; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'navbar.php'; ?>

    <main class="dashboard-content">
        <header class="page-header">
            <div>
                <h1 class="page-title"><?php echo $is_editing ? 'Upravit záznam' : 'Přidat nový záznam'; ?></h1>
                <p class="page-subtitle"><i class="fas fa-edit"></i> Editace parametrů a jazykových mutací</p>
            </div>
            <div>
                <a href="blog.php?type=<?php echo $article_data['article_type']; ?>" class="btn btn-glass"><i class="fas fa-arrow-left"></i> Zpět na seznam</a>
            </div>
        </header>

        <?php if (!empty($admin_error)): ?>
            <div class="message-card">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($admin_error); ?>
            </div>
        <?php endif; ?>

        <form action="edit_article.php<?php echo $is_editing ? '?id=' . $article_id : ''; ?>" method="post" id="articleForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="article_type" value="<?php echo htmlspecialchars($article_data['article_type']); ?>">
            
            <div class="glass-card">
                <h2 class="section-title"><i class="fas fa-cog"></i> Serverové Informace</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div class="form-group">
                        <label for="slug">Slug (URL identifikátor):</label>
                        <input type="text" id="slug" name="slug" class="form-control" value="<?php echo htmlspecialchars($article_data['slug']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="author">Autor:</label>
                        <input type="text" id="author" name="author" class="form-control" value="<?php echo htmlspecialchars($article_data['author']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="publish_date">Datum publikování:</label>
                        <input type="date" id="publish_date" name="publish_date" class="form-control" value="<?php echo htmlspecialchars($article_data['publish_date']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="glass-card" style="animation-delay: 0.2s;">
                <h2 class="section-title"><i class="fas fa-language"></i> Jazykové mutace záznamu</h2>
                
                <div class="tabs">
                    <?php $first_lang = true; foreach ($available_admin_langs as $lang_code): ?>
                        <button type="button" class="tablink <?php if ($first_lang) echo 'active';?>" onclick="openLangTab(event, '<?php echo $lang_code; ?>')">
                            <i class="fas fa-flag"></i> <?php echo strtoupper($lang_code); ?>
                        </button>
                        <?php $first_lang = false; ?>
                    <?php endforeach; ?>
                </div>

                <?php $first_lang_content = true; foreach ($available_admin_langs as $lang_code): 
                    $trans = $article_data['translations'][$lang_code];
                ?>
                    <div id="tab_<?php echo $lang_code; ?>" class="tabcontent" style="<?php if (!$first_lang_content) echo 'display:none;';?>">
                        
                        <div class="form-group">
                            <label for="title_<?php echo $lang_code; ?>">Titulek článku:</label>
                            <input type="text" id="title_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][title]" class="form-control" value="<?php echo htmlspecialchars($trans['title']); ?>">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                            <div class="form-group">
                                <label for="meta_title_<?php echo $lang_code; ?>">Meta Title (SEO):</label>
                                <input type="text" id="meta_title_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][meta_title]" class="form-control" value="<?php echo htmlspecialchars($trans['meta_title']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="meta_description_<?php echo $lang_code; ?>">Meta Description (SEO):</label>
                                <textarea id="meta_description_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][meta_description]" class="form-control" rows="2"><?php echo htmlspecialchars($trans['meta_description']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="excerpt_<?php echo $lang_code; ?>">Krátký úryvek (Excerpt):</label>
                            <textarea id="excerpt_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][excerpt]" class="form-control" rows="3"><?php echo htmlspecialchars($trans['excerpt']); ?></textarea>
                        </div>
                        
                        <div class="form-group" style="background: white; border-radius:0.5rem; padding: 2px;">
                            <label for="content_editor_<?php echo $lang_code; ?>" style="padding:10px; background:var(--surface-color); margin:0; border-radius:0.5rem 0.5rem 0 0;">Obsah článku:</label>
                            <textarea id="content_editor_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][content]" class="tinymce-editor"><?php echo htmlspecialchars($trans['content']); ?></textarea>
                        </div>
                        
                    </div>
                    <?php $first_lang_content = false; ?>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: right;">
                <button type="submit" name="save_article" class="btn btn-success">
                    <i class="fas fa-save"></i> <?php echo $is_editing ? 'Uložit úpravy článku' : 'Vytvořit nový článek'; ?>
                </button>
            </div>
            
        </form>
    </main>
</div>

<script>
function openLangTab(evt, langName) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablink");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById('tab_' + langName).style.display = "block";
  evt.currentTarget.className += " active";
}

document.addEventListener('DOMContentLoaded', function() {
    var firstTabLink = document.querySelector('.tablink');
    if (firstTabLink) {
        firstTabLink.click();
    }
});
</script>

</body>
</html>

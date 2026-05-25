<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

define('APP_LOADED', true); 
require_once __DIR__ . '/../config/secure_settings.php'; 
require_once __DIR__ . '/../config/database.php';      

$available_admin_langs = ['en', 'cz', 'it']; 
$default_lang = 'en'; // Definujte váš výchozí jazyk

define('EBOOK_PDF_DISPLAY_PATH_PREFIX', 'assets/downloads/ebooks/'); // Pouze prefix cesty
define('EBOOK_PDF_UPLOAD_DIR_SERVER', $_SERVER['DOCUMENT_ROOT'] . '/' . EBOOK_PDF_DISPLAY_PATH_PREFIX);
define('EBOOK_COVER_PUBLIC_PATH', 'assets/images/ebook_covers/');

$ebook_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_editing = $ebook_id > 0;
$page_title = $is_editing ? 'Upravit E-book' : 'Přidat Nový E-book';
$form_action_url = "edit_ebook.php" . ($is_editing ? "?id=" . $ebook_id : "");

$admin_message = '';
$message_type = '';

$ebook_data = [
    'slug' => '',
    'author' => $_SESSION['admin_username'], 
    'publish_date' => date('Y-m-d'),
    // pdf_base_filename a cover_base_filename se již nenačítají/neukládají do hlavní tabulky 'ebooks' tímto způsobem
    'translations' => []
];
foreach ($available_admin_langs as $lang_code) {
    $ebook_data['translations'][$lang_code] = ['title' => '', 'description' => '', 'meta_title' => '', 'meta_description' => '', 'cover_path' => '', 'pdf_path' => ''];
}

if ($is_editing) {
    try {
        $stmt_main = $pdo->prepare("SELECT id, slug, author, publish_date FROM ebooks WHERE id = :id");
        $stmt_main->bindParam(':id', $ebook_id, PDO::PARAM_INT);
        $stmt_main->execute();
        $main_data = $stmt_main->fetch();

        if ($main_data) {
            $ebook_data['id'] = $main_data['id'];
            $ebook_data['slug'] = $main_data['slug'];
            $ebook_data['author'] = $main_data['author'];
            $ebook_data['publish_date'] = $main_data['publish_date'];

            $stmt_translations = $pdo->prepare("SELECT lang_code, title, description, meta_title, meta_description, cover_path, pdf_path FROM ebook_translations WHERE ebook_id = :ebook_id");
            $stmt_translations->bindParam(':ebook_id', $ebook_id, PDO::PARAM_INT);
            $stmt_translations->execute();
            while ($trans = $stmt_translations->fetch()) {
                if (isset($ebook_data['translations'][$trans['lang_code']])) {
                    $ebook_data['translations'][$trans['lang_code']] = $trans;
                }
            }
        } else { 
            $_SESSION['admin_message'] = "E-book s ID {$ebook_id} nebyl nalezen.";
            $_SESSION['message_type'] = "error";
            header("Location: ebooks_list.php");
            exit;
        }
    } catch (PDOException $e) {
        $admin_message = "Chyba při načítání e-booku pro editaci: " . $e->getMessage();
        $message_type = "error";
        error_log($admin_message . " Ebook ID: " . $ebook_id);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_ebook'])) {
    $slug = trim($_POST['slug']);
    $author = trim($_POST['author']);
    $publish_date = trim($_POST['publish_date']);
    $translations_post = $_POST['translations'] ?? [];

    if (empty($slug) || empty($publish_date)) {
        $admin_message = "Slug a datum publikování jsou povinné.";
        $message_type = "error";
    } else {
        try {
            $pdo->beginTransaction();

            if ($is_editing) {
                // Aktualizujeme pouze metadata v tabulce 'ebooks'
                $stmt = $pdo->prepare("UPDATE ebooks SET slug = :slug, author = :author, publish_date = :publish_date WHERE id = :id");
                $stmt->execute([':slug' => $slug, ':author' => $author, ':publish_date' => $publish_date, ':id' => $ebook_id]);
                $current_ebook_id = $ebook_id;
            } else {
                // Vložíme nový záznam do 'ebooks' bez pdf_base_filename a cover_base_filename
                $stmt = $pdo->prepare("INSERT INTO ebooks (slug, author, publish_date) VALUES (:slug, :author, :publish_date)");
                $stmt->execute([':slug' => $slug, ':author' => $author, ':publish_date' => $publish_date]);
                $current_ebook_id = $pdo->lastInsertId();
            }

            // Zpracování překladů, PDF a cest k obálkám
            foreach ($available_admin_langs as $lang_code) {
                if (isset($translations_post[$lang_code])) {
                    $trans_data = $translations_post[$lang_code];
                    $cover_path_for_lang = trim($trans_data['cover_path']);
                    // Získáme aktuální PDF cestu ze skrytého pole, pokud existuje
                    $pdf_path_to_save_for_lang = trim($trans_data['current_pdf_path'] ?? ($ebook_data['translations'][$lang_code]['pdf_path'] ?? ''));


                    // Zpracování nahrání PDF pro tento jazyk
                    if (isset($_FILES['translations']['name'][$lang_code]['pdf_file']) && $_FILES['translations']['error'][$lang_code]['pdf_file'] == UPLOAD_ERR_OK && $_FILES['translations']['size'][$lang_code]['pdf_file'] > 0) {
                        $pdf_tmp_name = $_FILES['translations']['tmp_name'][$lang_code]['pdf_file'];
                        $pdf_original_name = basename($_FILES['translations']['name'][$lang_code]['pdf_file']);
                        // Vytvoření unikátnějšího názvu souboru
                        $pdf_safe_name = $slug . '_' . $lang_code . '_' . time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "_", $pdf_original_name);
                        $pdf_target_path_server = EBOOK_PDF_UPLOAD_DIR_SERVER . $pdf_safe_name;
                        $pdf_relative_path_for_db = EBOOK_PDF_DISPLAY_PATH_PREFIX . $pdf_safe_name;

                        if (move_uploaded_file($pdf_tmp_name, $pdf_target_path_server)) {
                            // Smazání starého PDF pro tento jazyk, pokud existuje a liší se
                            if (!empty($pdf_path_to_save_for_lang) && $pdf_path_to_save_for_lang !== $pdf_relative_path_for_db && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($pdf_path_to_save_for_lang, '/'))) {
                                @unlink($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($pdf_path_to_save_for_lang, '/'));
                            }
                            $pdf_path_to_save_for_lang = $pdf_relative_path_for_db;
                        } else {
                            $admin_message .= " Chyba při nahrávání PDF pro jazyk {$lang_code}."; $message_type = "error";
                        }
                    }
                    
                    if (!$is_editing && $lang_code === $default_lang && empty($pdf_path_to_save_for_lang)) {
                         $admin_message .= " PDF soubor pro výchozí jazyk ({$default_lang}) je povinný pro nový e-book."; $message_type = "error";
                    }
                    if (!empty($cover_path_for_lang) && !preg_match('/^assets\/images\/ebook_covers\/[a-zA-Z0-9._-]+$/', $cover_path_for_lang)) {
                         $admin_message .= " Neplatný formát cesty k obálce pro jazyk {$lang_code}. Příklad: assets/images/ebook_covers/nazev.png.";
                         $message_type = "error";
                    }

                    $stmt_check_trans = $pdo->prepare("SELECT id FROM ebook_translations WHERE ebook_id = :ebook_id AND lang_code = :lang_code");
                    $stmt_check_trans->execute([':ebook_id' => $current_ebook_id, ':lang_code' => $lang_code]);
                    $existing_trans_id = $stmt_check_trans->fetchColumn();

                    $params_trans = [
                        ':ebook_id' => $current_ebook_id, ':lang_code' => $lang_code,
                        ':title' => trim($trans_data['title']), 
                        ':description' => trim($trans_data['description']),
                        ':meta_title' => trim($trans_data['meta_title']), 
                        ':meta_description' => trim($trans_data['meta_description']),
                        ':cover_path' => $cover_path_for_lang,
                        ':pdf_path' => $pdf_path_to_save_for_lang
                    ];

                    if ($existing_trans_id) {
                        if (!empty($params_trans[':title']) || !empty($params_trans[':cover_path']) || !empty($params_trans[':pdf_path'])) { 
                            $sql_trans = "UPDATE ebook_translations SET title = :title, description = :description, meta_title = :meta_title, meta_description = :meta_description, cover_path = :cover_path, pdf_path = :pdf_path WHERE id = :id";
                            $params_trans[':id'] = $existing_trans_id;
                        } else { $sql_trans = "DELETE FROM ebook_translations WHERE id = :id"; $params_trans = [':id' => $existing_trans_id];}
                    } else {
                        if (!empty($params_trans[':title']) || !empty($params_trans[':cover_path']) || !empty($params_trans[':pdf_path'])) { 
                            $sql_trans = "INSERT INTO ebook_translations (ebook_id, lang_code, title, description, meta_title, meta_description, cover_path, pdf_path) VALUES (:ebook_id, :lang_code, :title, :description, :meta_title, :meta_description, :cover_path, :pdf_path)";
                        } else { $sql_trans = null; }
                    }
                    if ($sql_trans) {
                        $stmt_trans_action = $pdo->prepare($sql_trans);
                        $stmt_trans_action->execute($params_trans);
                    }
                }
            }
            
            if ($message_type !== "error") {
                $pdo->commit();
                $_SESSION['admin_message'] = "E-book byl úspěšně uložen.";
                $_SESSION['message_type'] = "success";
                header("Location: ebooks_list.php");
                exit;
            } else {
                $pdo->rollBack();
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $admin_message = "Chyba při ukládání e-booku do DB: " . $e->getMessage();
            $message_type = "error";
            error_log($admin_message . " Data: " . print_r($_POST, true) . " Files: " . print_r($_FILES, true));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | P.D. Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <script src="https://cdn.tiny.cloud/1/n49p4gpzb6k70f9vj6k5nyow8on710aecvc1wzb5h0ib7h0g/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        tinymce.init({
          selector: 'textarea.tinymce-editor',
          skin: 'oxide-dark',
          content_css: 'dark',
          plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
          toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help | code',
          height: 350, 
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

        /* Input type file styling fix pre dark rezim */
        input[type="file"].form-control {
            padding: 0.5rem 1rem;
            color: var(--text-muted);
        }
        input[type="file"]::file-selector-button {
            background: rgba(255,255,255,0.1);
            color: white; border: 1px solid var(--glass-border); padding: 0.4rem 1rem; border-radius: 0.3rem; margin-right: 1rem;
            cursor: pointer; transition: 0.3s; font-family: 'Outfit', sans-serif;
        }
        input[type="file"]::file-selector-button:hover { background: rgba(255,255,255,0.2); }

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
            background: linear-gradient(135deg, #10b981, #059669); color: white; border-color: transparent;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        }
        .tabcontent { display: none; animation: fadeUp 0.3s ease-out; }

        .message-card {
            background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399;
            padding: 1rem 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;
        }
        .message-card.error {
            background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #fca5a5;
        }

        .cover-preview { max-height: 80px; border-radius: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 1px solid var(--glass-border); margin-top:0.5rem;}
        .current-file { font-size: 0.85rem; color: var(--success); display: inline-flex; align-items: center; gap: 0.4rem; padding-top:0.5rem;}
        .current-file a { color: var(--accent-primary); text-decoration: none;}

        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .btn-glass { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); color: var(--text-main); padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; font-size:0.875rem;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'navbar.php'; ?>

    <main class="dashboard-content">
        <header class="page-header">
            <div>
                <h1 class="page-title"><?php echo $page_title; ?></h1>
                <p class="page-subtitle"><i class="fas fa-book"></i> Nastavení PDF dokumentu a vizuálů dle jazyků</p>
            </div>
            <div>
                <a href="ebooks_list.php" class="btn btn-glass"><i class="fas fa-arrow-left"></i> Zpět na seznam</a>
            </div>
        </header>

        <?php if (!empty($admin_message)): ?>
            <div class="message-card <?php echo $message_type === 'error' ? 'error' : ''; ?>">
                <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                <?php echo htmlspecialchars($admin_message); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($form_action_url); ?>" method="post" enctype="multipart/form-data" id="ebookForm">
            
            <div class="glass-card">
                <h2 class="section-title"><i class="fas fa-cog"></i> Jádro E-booku</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div class="form-group">
                        <label for="slug">Slug (URL identifikátor):</label>
                        <input type="text" id="slug" name="slug" class="form-control" value="<?php echo htmlspecialchars($ebook_data['slug']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="author">Autor:</label>
                        <input type="text" id="author" name="author" class="form-control" value="<?php echo htmlspecialchars($ebook_data['author']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="publish_date">Datum Publikování:</label>
                        <input type="date" id="publish_date" name="publish_date" class="form-control" value="<?php echo htmlspecialchars($ebook_data['publish_date']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="glass-card" style="animation-delay: 0.2s;">
                <h2 class="section-title"><i class="fas fa-language"></i> Cizojazyčné verze PDF souborů a popisů</h2>
                
                <div class="tabs">
                    <?php $first_lang_tab = true; foreach ($available_admin_langs as $lang_code_tab): ?>
                        <button type="button" class="tablink <?php if ($first_lang_tab) echo 'active';?>" onclick="openLangTab(event, '<?php echo $lang_code_tab; ?>')">
                            <i class="fas fa-flag"></i> <?php echo strtoupper($lang_code_tab); ?>
                        </button>
                        <?php $first_lang_tab = false; ?>
                    <?php endforeach; ?>
                </div>

                <?php $first_lang_content_tab = true; foreach ($available_admin_langs as $lang_code): 
                    $trans = $ebook_data['translations'][$lang_code] ?? ['title' => '', 'description' => '', 'meta_title' => '', 'meta_description' => '', 'cover_path' => '', 'pdf_path' => ''];
                ?>
                    <div id="tab_<?php echo $lang_code; ?>" class="tabcontent" style="<?php echo $first_lang_content_tab ? '' : 'display:none;';?>">
                        
                        <div class="form-group">
                            <label for="title_<?php echo $lang_code; ?>">Titulek e-booku:</label>
                            <input type="text" id="title_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][title]" class="form-control" value="<?php echo htmlspecialchars($trans['title']); ?>" <?php echo ($lang_code === $default_lang || ($is_editing && !empty($trans['title']))) ? 'required' : ''; ?>>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                            <div class="form-group">
                                <label for="pdf_file_<?php echo $lang_code; ?>">PDF Soubor z disku pro <?php echo strtoupper($lang_code); ?>:</label>
                                <input type="file" id="pdf_file_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][pdf_file]" class="form-control" accept=".pdf">
                                <?php if ($is_editing && !empty($trans['pdf_path'])): ?>
                                    <div class="current-file">
                                        <i class="fas fa-file-pdf"></i> Přiloženo: <?php echo htmlspecialchars(basename($trans['pdf_path'])); ?>
                                        (<a href="<?php echo '../' . htmlspecialchars($trans['pdf_path']); ?>" target="_blank"><i class="fas fa-external-link-alt"></i> zobrazit</a>)
                                    </div>
                                <?php endif; ?>
                                <?php if (!$is_editing && $lang_code === $default_lang): ?>
                                    <small style="color:var(--text-muted); display:block; margin-top:0.4rem;">Povinné pro výchozí jazyk nového e-booku.</small>
                                <?php endif; ?>
                                <input type="hidden" name="translations[<?php echo $lang_code; ?>][current_pdf_path]" value="<?php echo htmlspecialchars($trans['pdf_path']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="cover_path_<?php echo $lang_code; ?>">Manuální cesta k obálce (např. assets/images/ebook_covers/cover.png):</label>
                                <input type="text" id="cover_path_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][cover_path]" class="form-control" value="<?php echo htmlspecialchars($trans['cover_path']); ?>">
                                <?php if (!empty($trans['cover_path'])): 
                                      $cover_preview_path = '../' . ltrim($trans['cover_path'], '/'); 
                                      if (file_exists($cover_preview_path)): ?>
                                    <img src="<?php echo htmlspecialchars($cover_preview_path); ?>" alt="Náhled <?php echo strtoupper($lang_code); ?>" class="cover-preview">
                                <?php elseif ($is_editing): ?>
                                    <small style="color:var(--danger); display:block; margin-top:0.4rem;"><i class="fas fa-times-circle"></i> Soubor nenalezen</small>
                                <?php endif; endif; ?>
                            </div>
                        </div>

                        <div class="form-group" style="background: white; border-radius:0.5rem; padding: 2px;">
                            <label for="description_editor_<?php echo $lang_code; ?>" style="padding:10px; background:var(--surface-color); margin:0; border-radius:0.5rem 0.5rem 0 0;">Popis E-booku pro veřejnou stránku:</label>
                            <textarea id="description_editor_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][description]" class="tinymce-editor"><?php echo htmlspecialchars($trans['description']); ?></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top:1.5rem;">
                            <div class="form-group">
                                <label for="meta_title_<?php echo $lang_code; ?>">Meta Title (SEO):</label>
                                <input type="text" id="meta_title_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][meta_title]" class="form-control" value="<?php echo htmlspecialchars($trans['meta_title']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="meta_description_<?php echo $lang_code; ?>">Meta Description (SEO):</label>
                                <textarea id="meta_description_<?php echo $lang_code; ?>" name="translations[<?php echo $lang_code; ?>][meta_description]" class="form-control" rows="2"><?php echo htmlspecialchars($trans['meta_description']); ?></textarea>
                            </div>
                        </div>
                        
                    </div>
                    <?php $first_lang_content_tab = false; ?>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: right;">
                <button type="submit" name="save_ebook" class="btn btn-success">
                    <i class="fas fa-save"></i> <?php echo $is_editing ? 'Uložit úpravy E-booku' : 'Vytvořit Nový E-book'; ?>
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
  if(evt && evt.currentTarget) { 
    evt.currentTarget.className += " active";
  } else if (evt === null) { 
      var btnToActivate = document.querySelector('.tabs button.tablink[onclick*="\'' + langName + '\'"]');
      if(btnToActivate) btnToActivate.classList.add('active');
  }
}

document.addEventListener('DOMContentLoaded', function() {
    var firstTabButton = document.querySelector('.tabs button.tablink');
    if (firstTabButton) {
        var langName = firstTabButton.getAttribute('onclick').match(/'([^']+)'/)[1];
        openLangTab(null, langName); 
        if(!firstTabButton.classList.contains('active')){
             firstTabButton.classList.add('active');
        }
    }
});
</script>
</body>
</html>

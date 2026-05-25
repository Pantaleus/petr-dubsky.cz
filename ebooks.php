<?php
// ebooks.php
define('APP_LOADED', true); 
define('IS_HOME_PAGE', false);
$base_path = '';

$secure_config_path = __DIR__ . '/config/secure_settings.php'; 
if (file_exists($secure_config_path)) {
    require_once $secure_config_path; 
} else {
    error_log("FATAL ERROR: Secure config file not found at {$secure_config_path}");
    die("A critical configuration error occurred. Please contact the site administrator.");
}

require_once __DIR__ . '/includes/send_ebook_email.php'; 
require_once __DIR__ . '/config/database.php'; 

$available_langs = array(
    'en' => 'lang/en.php', 
    'cz' => 'lang/cz.php',
    'it' => 'lang/it.php'
);
$default_lang = 'en';
$current_lang_code = isset($_GET['lang']) && array_key_exists($_GET['lang'], $available_langs) ? $_GET['lang'] : $default_lang;
$main_lang_file_path = $available_langs[$current_lang_code];
$lang = [];
if (file_exists($main_lang_file_path)) {
    require_once($main_lang_file_path); 
} else {
    error_log("Error: Main language file '{$main_lang_file_path}' not found for '{$current_lang_code}' in ebooks.php.");
    // --- FALLBACK TEXTY - UJISTĚTE SE, ŽE JSOU KOMPLETNÍ A SPRÁVNĚ ESCAPOVANÉ ---
    $lang['ebook_page_title'] = 'Our E-books';
    $lang['ebook_get_this_ebook_button'] = 'Get This E-book';
    $lang['ebook_form_name_label'] = 'Your Name (Optional)';
    $lang['ebook_form_email_label'] = 'Your Email';
    $lang['ebook_form_submit_button'] = 'Download Now';
    $lang['ebook_consent_text'] = 'By submitting, you agree to receive the e-book and occasional updates.';
    $lang['meta_title'] = 'Pietro Dubsky';
    $lang['footer_copyright'] = '© ' . date("Y") . ' Pietro Dubsky';
    $lang['nav_ebooks'] = 'E-books';
    $lang['ebook_nis2_title'] = 'NIS2 E-book Title Fallback'; 
    $lang['ebook_nis2_description'] = 'NIS2 E-book Description Fallback'; 
    $lang['nav_home'] = "Home";
    $lang['nav_about'] = "O mně"; // atd. pro všechny navigační a další texty
    $lang['nav_services'] = "Služby";
    $lang['nav_portfolio'] = "Portfolio";
    $lang['nav_why_me'] = "Proč já?";
    $lang['nav_contact'] = "Kontakt";
    $lang['nav_blog'] = "Blog";
    $lang['lang_switch_en'] = "English";
    $lang['lang_switch_cz'] = "Česky";
    $lang['lang_switch_it'] = "Italiano";
    $lang['ebook_back_to_list_link'] = 'Back to E-book List';
    $lang['ebooks_none_available'] = 'No e-books currently available.';
    $lang['ebook_cover_unavailable'] = 'Cover Unavailable';
    $lang['ebook_error_invalid_email'] = 'Please enter a valid email address.';
    $lang['ebook_info_already_subscribed'] = 'You have already requested this e-book. Please check your email or contact us if you haven\'t received it.'; // Escapovaný apostrof
    $lang['ebook_success_message'] = 'Thank you! The e-book download link has been sent to your email address. Please also check your spam folder.';
    $lang['ebook_error_sending_email'] = 'We encountered an issue sending the email with the download link. Please try again later or contact support.';
    $lang['ebook_error_generic'] = 'An unexpected error occurred. Please try again.';
    $lang['ebook_email_subject_prefix'] = 'Your Free E-book: '; 
    $lang['ebook_email_greeting'] = 'Hello';
    $lang['ebook_email_body_prefix'] = 'Thank you for your interest in our e-book "';
    $lang['ebook_email_body_suffix'] = '". You can download it using the link below:';
    $lang['ebook_email_signature'] = "Best regards,\nPietro Dubsky\npetr-dubsky.cz";
}

$available_ebooks = [
    'nis2-ebook' => [
        'title_key' => 'ebook_nis2_title', 
        'description_key' => 'ebook_nis2_description', 
        'pdf_url' => 'assets/downloads/6JUNeLnLohk3z9dS/NIS2.pdf', // Přímá cesta k PDF
        'cover_images' => [ 
            'en' => 'assets/images/covers/nis2_cover_en.png', 
            'cz' => 'assets/images/covers/nis2_cover_cz.png', 
            'it' => 'assets/images/covers/nis2_cover_it.png'  
        ]
    ],
];

$selected_ebook_slug = isset($_GET['download']) ? trim($_GET['download']) : null;
$selected_ebook_details = null;
$form_message = '';
$form_message_type = '';

if ($selected_ebook_slug && isset($available_ebooks[$selected_ebook_slug])) {
    $selected_ebook_details = $available_ebooks[$selected_ebook_slug];

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_ebook_form'])) {
        $name = isset($_POST['name']) ? strip_tags(trim($_POST['name'])) : null;
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_message = $lang['ebook_error_invalid_email'];
            $form_message_type = 'error';
        } else {
            try {
                $stmt_check = $pdo->prepare("SELECT id FROM ebook_subscribers WHERE email = :email AND ebook_slug = :ebook_slug");
                $stmt_check->execute([':email' => $email, ':ebook_slug' => $selected_ebook_slug]);
                
                if ($stmt_check->fetch()) {
                    $form_message = $lang['ebook_info_already_subscribed'];
                    $form_message_type = 'info';
                } else {
                    $stmt_insert = $pdo->prepare("INSERT INTO ebook_subscribers (email, name, ebook_slug) VALUES (:email, :name, :ebook_slug)");
                    $stmt_insert->execute([
                        ':email' => $email,
                        ':name' => $name,
                        ':ebook_slug' => $selected_ebook_slug
                    ]);

                    $ebook_title_for_email = $lang[$selected_ebook_details['title_key']] ?? $selected_ebook_slug;
                    $ebook_actual_pdf_url = $selected_ebook_details['pdf_url']; 

                    if (strpos($ebook_actual_pdf_url, 'http') !== 0) {
                        $base_site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                        $ebook_actual_pdf_url = $base_site_url . '/' . ltrim($ebook_actual_pdf_url, '/');
                    }

                    $subject = ($lang['ebook_email_subject_prefix']) . $ebook_title_for_email;
                    $body_greeting = ($lang['ebook_email_greeting']) . ($name ? ' ' . htmlspecialchars($name) : '');
                    $body_main = ($lang['ebook_email_body_prefix']) . $ebook_title_for_email . ($lang['ebook_email_body_suffix']);
                    $body_signature = ($lang['ebook_email_signature']);
                    $email_body_text = $body_greeting . ",\n\n" . $body_main . "\n" . $ebook_actual_pdf_url . "\n\n" . $body_signature;
                    
                    $smtp_config_values = [
                        'host'          => SMTP_HOST,
                        'user'          => SMTP_USER,
                        'pass'          => SMTP_PASS, 
                        'port'          => SMTP_PORT,
                        'secure_string' => SMTP_SECURE_STRING // OPRAVA: Používáme správný klíč pro funkci
                    ];

                    if (sendEbookViaPHPMailer($email, $name, $subject, $email_body_text, $smtp_config_values, 
                                                SMTP_FROM_EMAIL, SMTP_FROM_NAME, 
                                                SMTP_FROM_EMAIL, SMTP_FROM_NAME 
                                            )) {
                        $form_message = $lang['ebook_success_message'];
                        $form_message_type = 'success';
                    } else {
                        $form_message = $lang['ebook_error_sending_email'];
                        $form_message_type = 'error';
                    }
                }
            } catch (PDOException $e) {
                error_log("Ebook subscription DB error: " . $e->getMessage());
                $form_message = $lang['ebook_error_generic'];
                $form_message_type = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<!-- ... ZBYTEK VAŠEHO HTML KÓDU PRO ebooks.php ZŮSTÁVÁ STEJNÝ ... -->
<!-- (Tj. celá část od <!DOCTYPE html> až po </html> ) -->
<html lang="<?php echo htmlspecialchars($current_lang_code); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lang['ebook_page_title'] ?? 'E-books'); ?> - <?php echo htmlspecialchars($lang['meta_title'] ?? 'Pietro Dubsky'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($lang['ebook_page_meta_description'] ?? 'Download free e-books on IT security and technology.'); ?>">

    <link rel="stylesheet" href="css/bundle.min.css"> 
    <link rel="stylesheet" href="css/ebooks_page_style.css"> 
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="ebooks-main section-padding">
        <div class="container">
            <h1 class="page-title-ebooks"><?php echo htmlspecialchars($lang['ebook_page_title'] ?? 'Our E-books'); ?></h1>

            <?php if ($selected_ebook_details): // Zobrazujeme formulář pro konkrétní e-book ?>
                <section class="ebook-detail-section">
                    <article class="ebook-item ebook-detail-view">
                        <div class="ebook-cover-container"> 
                            <?php 
                            $cover_path_to_check = $selected_ebook_details['cover_images'][$current_lang_code] 
                                                 ?? $selected_ebook_details['cover_images'][$default_lang] 
                                                 ?? null;
                            
                            $full_server_path_detail_cover = null;
                            if ($cover_path_to_check) {
                                $full_server_path_detail_cover = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($cover_path_to_check, '/');
                            }

                            if ($cover_path_to_check && file_exists($full_server_path_detail_cover)):
                            ?>
                                <?php 
                                $cover_webp = preg_replace('/\.(png|jpe?g)$/i', '.webp', $cover_path_to_check);
                                $full_server_path_webp = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($cover_webp, '/');
                                ?>
                                <picture>
                                    <?php if (file_exists($full_server_path_webp)): ?>
                                        <source srcset="<?php echo htmlspecialchars($cover_webp); ?>" type="image/webp">
                                    <?php endif; ?>
                                    <img src="<?php echo htmlspecialchars($cover_path_to_check); ?>" alt="<?php echo htmlspecialchars($lang[$selected_ebook_details['title_key']] ?? $selected_ebook_slug); ?> Cover" class="ebook-cover">
                                </picture>
                            <?php else: ?>
                                <div class="ebook-cover-placeholder"><span><?php echo htmlspecialchars($lang['ebook_cover_unavailable'] ?? 'Cover'); ?></span></div>
                            <?php endif; ?>
                        </div> 

                        <div class="ebook-details">
                           <h2><?php echo htmlspecialchars($lang[$selected_ebook_details['title_key']] ?? $selected_ebook_slug); ?></h2>
                            <p class="ebook-description"><?php echo htmlspecialchars($lang[$selected_ebook_details['description_key']] ?? 'Download this e-book.'); ?></p>
                            
                            <?php if (!empty($form_message)): ?>
                                <div class="form-message <?php echo $form_message_type; ?>">
                                    <?php echo $form_message; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($form_message_type !== 'success'): ?>
                            <form action="ebooks.php?lang=<?php echo $current_lang_code; ?>&download=<?php echo urlencode($selected_ebook_slug); ?>" method="post" class="ebook-form">
                                <input type="hidden" name="ebook_slug" value="<?php echo htmlspecialchars($selected_ebook_slug); ?>">
                                <div class="form-group">
                                    <label for="name"><?php echo htmlspecialchars($lang['ebook_form_name_label'] ?? 'Name (Optional)'); ?></label>
                                    <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email"><?php echo htmlspecialchars($lang['ebook_form_email_label'] ?? 'Email'); ?>*</label>
                                    <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>
                                <div class="form-group consent-group">
                                    <input type="checkbox" id="consent" name="consent" value="yes" checked required>
                                    <label for="consent" class="consent-label"><?php echo htmlspecialchars($lang['ebook_consent_text'] ?? 'I agree to receive this e-book and occasional updates.'); ?></label>
                                </div>
                                <button type="submit" name="submit_ebook_form" class="btn btn-primary"><?php echo htmlspecialchars($lang['ebook_form_submit_button'] ?? 'Download Now'); ?></button>
                            </form>
                            <?php endif; ?>
                            <p class="back-to-list"><a href="ebooks.php?lang=<?php echo $current_lang_code; ?>">« <?php echo $lang['ebook_back_to_list_link'] ?? 'Back to E-book List'; ?></a></p>
                        </div>
                    </article>
                </section>
            <?php else: // Zobrazujeme seznam všech e-booků ?>
                <section class="ebook-list-section">
                    <?php if (empty($available_ebooks)): ?>
                        <p class="no-ebooks-message"><?php echo $lang['ebooks_none_available'] ?? 'No e-books currently available.'; ?></p>
                    <?php else: ?>
                        <div class="ebook-list">
                        <?php foreach ($available_ebooks as $slug => $ebook_meta_item): ?>
                            <article class="ebook-item-summary">
                                <?php
                                $list_cover_to_display = $ebook_meta_item['cover_images'][$current_lang_code] 
                                                         ?? $ebook_meta_item['cover_images'][$default_lang] 
                                                         ?? null;
                                
                                $full_server_path_list_cover = null;
                                if ($list_cover_to_display) {
                                    $full_server_path_list_cover = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($list_cover_to_display, '/');
                                }

                                if ($list_cover_to_display && file_exists($full_server_path_list_cover)):
                                ?>
                                    <a href="ebooks.php?lang=<?php echo $current_lang_code; ?>&download=<?php echo urlencode($slug); ?>" class="ebook-cover-link">
                                        <?php 
                                        $list_cover_webp = preg_replace('/\.(png|jpe?g)$/i', '.webp', $list_cover_to_display);
                                        $full_server_path_list_webp = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($list_cover_webp, '/');
                                        ?>
                                        <picture>
                                            <?php if (file_exists($full_server_path_list_webp)): ?>
                                                <source srcset="<?php echo htmlspecialchars($list_cover_webp); ?>" type="image/webp">
                                            <?php endif; ?>
                                            <img src="<?php echo htmlspecialchars($list_cover_to_display); ?>" alt="<?php echo htmlspecialchars($lang[$ebook_meta_item['title_key']] ?? $slug); ?> Cover" class="ebook-cover-summary">
                                        </picture>
                                    </a>
                                <?php else: ?>
                                     <a href="ebooks.php?lang=<?php echo $current_lang_code; ?>&download=<?php echo urlencode($slug); ?>" class="ebook-cover-link">
                                        <div class="ebook-cover-placeholder"><span><?php echo htmlspecialchars($lang[$ebook_meta_item['title_key']] ?? $slug); ?></span></div>
                                     </a>
                                <?php endif; ?>
                                <div class="ebook-summary-details">
                                    <h3><a href="ebooks.php?lang=<?php echo $current_lang_code; ?>&download=<?php echo urlencode($slug); ?>"><?php echo htmlspecialchars($lang[$ebook_meta_item['title_key']] ?? $slug); ?></a></h3>
                                    <p><?php echo htmlspecialchars($lang[$ebook_meta_item['description_key']] ?? 'Learn more.'); ?></p>
                                    <a href="ebooks.php?lang=<?php echo $current_lang_code; ?>&download=<?php echo urlencode($slug); ?>" class="btn btn-secondary btn-small">
                                        <?php echo htmlspecialchars($lang['ebook_get_this_ebook_button'] ?? 'Get This E-book'); ?>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script src="scripts/lang-constants.php?lang=<?php echo $current_lang_code; ?>"></script>
    <script src="scripts/script.js" defer></script>
    <script src="scripts/aria.js" defer></script>
</body>
</html>


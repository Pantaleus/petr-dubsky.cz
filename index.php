<?php
// index.php - Hlavní soubor webu

// Zabezpečení proti přímému přístupu k include souborům
define('APP_LOADED', true);
define('IS_HOME_PAGE', true);
$base_path = '';

// Načtení jazykové konfigurace
require_once __DIR__ . '/lang/config.php';
require_once $available_langs[$current_lang_code];

// Tracker analytiky a DB konfigurace
require_once __DIR__ . '/config/secure_settings.php';

// Převod interního kódu na oficiální ISO kód pro HTML lang
$html_lang_map = [
    'cz' => 'cs',
    'en' => 'en',
    'it' => 'it'
];
$html_lang_code = $html_lang_map[$current_lang_code] ?? 'cs';


// Načtení SEO konfigurace
require_once __DIR__ . '/includes/seo-meta.php';

// Načtení cookie notice
ob_start();
require_once __DIR__ . '/includes/cookie-notice.php';
$cookie_notice_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($html_lang_code); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo htmlspecialchars($seo['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo['keywords']); ?>">
    <meta name="author" content="Pietro Dubsky">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    
    <link rel="canonical" href="https://petr-dubsky.cz<?php echo $current_lang_code !== $default_lang ? '?lang=' . $current_lang_code : ''; ?>">
    
    <link rel="alternate" hreflang="cs" href="https://petr-dubsky.cz">
    <link rel="alternate" hreflang="en" href="https://petr-dubsky.cz?lang=en">
    <link rel="alternate" hreflang="it" href="https://petr-dubsky.cz?lang=it">
    <link rel="alternate" hreflang="x-default" href="https://petr-dubsky.cz">
    
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($seo['og_title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo['og_description']); ?>">
    <meta property="og:url" content="https://petr-dubsky.cz<?php echo $current_lang_code !== $default_lang ? '?lang=' . $current_lang_code : ''; ?>">
    <meta property="og:site_name" content="Pietro Dubsky - IT Specialist">
    <meta property="og:image" content="https://petr-dubsky.cz/assets/images/pietro-dubsky-og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Pietro Dubsky - Freelance IT Specialist and Web Developer">
    <meta property="og:locale" content="<?php echo $seo['hreflang']; ?>_<?php echo strtoupper($seo['canonical_lang'] === 'en' ? 'US' : ($seo['canonical_lang'] === 'cs' ? 'CZ' : 'IT')); ?>">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($seo['twitter_title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seo['twitter_description']); ?>">
    <meta name="twitter:image" content="https://petr-dubsky.cz/assets/images/pietro-dubsky-twitter-card.jpg">
    <meta name="twitter:image:alt" content="Pietro Dubsky - IT Specialist">
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#1DB954">
    
    <link rel="preload" href="css/bundle.min.css" as="style">
    <link rel="preload" href="scripts/lang-constants.php?lang=<?php echo $current_lang_code; ?>" as="script">
    
    <link rel="stylesheet" href="css/bundle.min.css">
    
    <script type="application/ld+json">
    <?php echo json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
    </script>
    
    <script type="application/ld+json">
    <?php echo json_encode($breadcrumb_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>
    
    <script type="application/ld+json">
    <?php echo json_encode($faq_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>
    
    <script type="application/ld+json">
    <?php echo json_encode($website_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>
    
    <script type="application/ld+json">
    <?php echo json_encode($service_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>
    
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-00THZ794SJ"></script>
</head>
<body>
    <?php echo $cookie_notice_html; ?>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main aria-label="Hlavní obsah">

        <section id="home" class="hero-section section-padding">
            <div class="container">
                <div class="hero-content">
                    <p class="hero-greeting"><?php echo htmlspecialchars($lang['hero_greeting']); ?></p>
                    <h1 class="hero-main-title"><?php echo htmlspecialchars($lang['hero_main_title']); ?></h1>
                    <p class="hero-subtitle"><?php echo htmlspecialchars($lang['hero_subtitle']); ?></p>
                    <div class="hero-buttons">
                        <a href="#services" class="btn btn-primary"><?php echo htmlspecialchars($lang['hero_cta_button']); ?></a>
                        <a href="#contact" class="btn btn-secondary"><?php echo htmlspecialchars($lang['hero_contact_button']); ?></a>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="about-section section-padding">
            <div class="container">
                <h2 class="section-title"><?php echo htmlspecialchars($lang['about_title']); ?></h2>
                <div class="about-content">
                    <div class="about-text">
                        <p><?php echo htmlspecialchars($lang['about_intro']); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($lang['about_paragraph1'])); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($lang['about_paragraph2'])); ?></p>
                    </div>
                    <div class="about-image">
                        <picture>
                            <source srcset="assets/images/pietro-dubsky.webp" type="image/webp">
                            <img src="assets/images/pietro-dubsky.jpg" loading="lazy" alt="<?php echo htmlspecialchars($lang['about_photo_alt']); ?>"> 
                        </picture>
                    </div>
                </div>
            </div>
        </section>

        <section id="services" class="services-section section-padding">
            <div class="container">
                <h2 class="section-title"><?php echo htmlspecialchars($lang['services_title']); ?></h2>
                <p class="section-intro"><?php echo htmlspecialchars($lang['services_intro_text']); ?></p>
                <div class="services-grid">
                    <div class="service-item">
                        <h3><?php echo htmlspecialchars($lang['service_webdev_title']); ?></h3>
                        <p><?php echo htmlspecialchars($lang['service_webdev_description']); ?></p>
                        <h4><?php echo htmlspecialchars($lang['service_webdev_benefits_title']); ?></h4>
                        <ul>
                            <li><?php echo htmlspecialchars($lang['service_webdev_benefit1']); ?></li>
                            <li><?php echo htmlspecialchars($lang['service_webdev_benefit2']); ?></li>
                            <li><?php echo htmlspecialchars($lang['service_webdev_benefit3']); ?></li>
                        </ul>
                    </div>
                    <div class="service-item">
                        <h3><?php echo htmlspecialchars($lang['service_automation_title']); ?></h3>
                        <p><?php echo htmlspecialchars($lang['service_automation_description']); ?></p>
                        <h4><?php echo htmlspecialchars($lang['service_automation_benefits_title']); ?></h4>
                        <ul>
                            <li><?php echo htmlspecialchars($lang['service_automation_benefit1']); ?></li>
                            <li><?php echo htmlspecialchars($lang['service_automation_benefit2']); ?></li>
                            <li><?php echo htmlspecialchars($lang['service_automation_benefit3']); ?></li>
                        </ul>
                    </div>
                    <div class="service-item">
                        <h3><?php echo htmlspecialchars($lang['service_sysadmin_title']); ?></h3>
                        <p><?php echo htmlspecialchars($lang['service_sysadmin_description']); ?></p>
                        <h4><?php echo htmlspecialchars($lang['service_sysadmin_benefits_title']); ?></h4>
                        <ul>
                            <li><?php echo htmlspecialchars($lang['service_sysadmin_benefit1']); ?></li>
                            <li><?php echo htmlspecialchars($lang['service_sysadmin_benefit2']); ?></li>
                            <li><?php echo htmlspecialchars($lang['service_sysadmin_benefit3']); ?></li>
                        </ul>
                    </div>
                </div>
                <div class="other-technologies">
                    <h3><?php echo htmlspecialchars($lang['services_other_tech_title']); ?></h3>
                    <p><?php echo htmlspecialchars($lang['services_other_tech_list']); ?></p>
                </div>
                <div class="payment-options">
                    <h3><?php echo htmlspecialchars($lang['services_payment_options_title']); ?></h3>
                    <p><?php echo htmlspecialchars($lang['services_payment_options_text']); ?></p>
                </div>
            </div>
        </section>

        <section id="portfolio" class="portfolio-section section-padding">
            <div class="container">
                <h2 class="section-title"><?php echo htmlspecialchars($lang['portfolio_title']); ?></h2>
                <p class="section-intro"><?php echo htmlspecialchars($lang['portfolio_intro_text']); ?></p>
                <div class="portfolio-grid">
                    <div class="portfolio-item">
                        <div class="portfolio-item-content">
                            <h3><?php echo htmlspecialchars($lang['portfolio_project1_title']); ?></h3>
                            <p><?php echo htmlspecialchars($lang['portfolio_project1_description']); ?></p>
                            <p><strong><?php echo $current_lang_code === 'cz' ? 'Použité technologie:' : ($current_lang_code === 'it' ? 'Tecnologie utilizzate:' : 'Technologies used:'); ?></strong> <?php echo htmlspecialchars($lang['portfolio_project1_technologies']); ?></p>
                        </div>
                    </div>
                    <div class="portfolio-item">
                        <div class="portfolio-item-content">
                            <h3><?php echo htmlspecialchars($lang['portfolio_project2_title']); ?></h3>
                            <p><?php echo htmlspecialchars($lang['portfolio_project2_description']); ?></p>
                            <p><strong><?php echo $current_lang_code === 'cz' ? 'Použité technologie:' : ($current_lang_code === 'it' ? 'Tecnologie utilizzate:' : 'Technologies used:'); ?></strong> <?php echo htmlspecialchars($lang['portfolio_project2_technologies']); ?></p>
                        </div>
                    </div>
                    <div class="portfolio-item">
                        <div class="portfolio-item-content">
                            <h3><?php echo htmlspecialchars($lang['portfolio_project3_title']); ?></h3>
                            <p><?php echo htmlspecialchars($lang['portfolio_project3_description']); ?></p>
                            <p><strong><?php echo $current_lang_code === 'cz' ? 'Použité technologie:' : ($current_lang_code === 'it' ? 'Tecnologie utilizzate:' : 'Technologies used:'); ?></strong> <?php echo htmlspecialchars($lang['portfolio_project3_technologies']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="why-me" class="why-me-section section-padding">
            <div class="container">
                <h2 class="section-title"><?php echo htmlspecialchars($lang['why_me_title']); ?></h2>
                <div class="why-me-grid">
                    <div class="why-me-item"><h3><?php echo htmlspecialchars($lang['why_me_point1_title']); ?></h3><p><?php echo htmlspecialchars($lang['why_me_point1_text']); ?></p></div>
                    <div class="why-me-item"><h3><?php echo htmlspecialchars($lang['why_me_point2_title']); ?></h3><p><?php echo htmlspecialchars($lang['why_me_point2_text']); ?></p></div>
                    <div class="why-me-item"><h3><?php echo htmlspecialchars($lang['why_me_point3_title']); ?></h3><p><?php echo htmlspecialchars($lang['why_me_point3_text']); ?></p></div>
                    <div class="why-me-item"><h3><?php echo htmlspecialchars($lang['why_me_point4_title']); ?></h3><p><?php echo htmlspecialchars($lang['why_me_point4_text']); ?></p></div>
                </div>
            </div>
        </section>

        <section id="contact" class="contact-section section-padding">
            <div class="container">
                <h2 class="section-title"><?php echo htmlspecialchars($lang['contact_title']); ?></h2>
                <p class="section-intro"><?php echo htmlspecialchars($lang['contact_intro_text']); ?></p>
                <form id="contact-form" class="contact-form" method="POST">
                    <div class="form-group">
                        <label for="name"><?php echo htmlspecialchars($lang['contact_form_name_label']); ?></label>
                        <input type="text" id="name" name="name" placeholder="<?php echo htmlspecialchars($lang['contact_form_name_placeholder']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><?php echo htmlspecialchars($lang['contact_form_email_label']); ?></label>
                        <input type="email" id="email" name="email" placeholder="<?php echo htmlspecialchars($lang['contact_form_email_placeholder']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="message"><?php echo htmlspecialchars($lang['contact_form_message_label']); ?></label>
                        <textarea id="message" name="message" rows="5" placeholder="<?php echo htmlspecialchars($lang['contact_form_message_placeholder']); ?>" required></textarea>
                    </div>
                    <div class="form-group honeypot-field">
                        <label for="hp-email">Leave this field empty</label>
                        <input type="text" id="hp-email" name="hp-email">
                    </div>
                    <button type="submit" class="btn btn-primary" id="submit-button">
                        <?php echo htmlspecialchars($lang['contact_form_submit_button']); ?>
                    </button>
                    <div id="form-status" role="status" aria-live="polite"></div>
                </form>
                <div class="direct-contact">
                    <p><?php echo htmlspecialchars($lang['contact_direct_email_text']); ?> <script>document.write('<a href="mai'+'lto:'+'mail'+'@'+'petr-dubsky.cz">mail'+'@'+'petr-dubsky.cz</a>');</script><noscript>pietro (zavinac) dubsky.it</noscript></p>
                </div>
            </div>
        </section>
        <section id="faq" class="faq-section section-padding">
            <div class="container">
                <h2 class="section-title"><?php echo htmlspecialchars($lang['faq_title'] ?? 'Často kladené dotazy'); ?></h2>
                <div class="faq-grid">
                    <div class="faq-item">
                        <h3><?php echo htmlspecialchars($lang['faq_q1'] ?? 'Jak probíhá vzdálená spolupráce?'); ?></h3>
                        <p><?php echo htmlspecialchars($lang['faq_a1'] ?? 'Spolupracujeme plně na dálku. Komunikace probíhá přes e-mail, videohovory a nástroje pro správu projektů, takže máte vždy přehled o průběhu.'); ?></p>
                    </div>
                    <div class="faq-item">
                        <h3><?php echo htmlspecialchars($lang['faq_q2'] ?? 'S jakými technologiemi nejčastěji pracujete?'); ?></h3>
                        <p><?php echo htmlspecialchars($lang['faq_a2'] ?? 'Vyvíjím hlavně v PHP, Pythonu, JavaScriptu a spravuji systémy Linux. Rád se přizpůsobím vaší aktuální infrastruktuře.'); ?></p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script src="scripts/lang-constants.php?lang=<?php echo $current_lang_code; ?>"></script>
    <script src="scripts/bundle.min.js" defer></script>

</body>
</html>

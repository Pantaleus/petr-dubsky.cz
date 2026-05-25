<?php
// includes/cookie-notice.php - GDPR Cookie Notice

if (!defined('APP_LOADED') || defined('COOKIE_NOTICE_INCLUDED')) {
    return;
}
define('COOKIE_NOTICE_INCLUDED', true);

// Cookie Notice texty podle jazyka
$cookie_texts = [
    'en' => [
        'title' => 'We use cookies',
        'message' => 'This website uses essential cookies to ensure proper functionality and analytics cookies to understand how you interact with our site. We do not share your data with third parties.',
        'accept_all' => 'Accept All',
        'accept_essential' => 'Essential Only',
        'learn_more' => 'Learn More',
        'privacy_policy_url' => '/privacy-policy.php?lang=en'
    ],
    'cz' => [
        'title' => 'Používáme cookies',
        'message' => 'Tyto webové stránky používají nezbytné soubory cookie pro zajištění správné funkčnosti a analytické soubory cookie pro pochopení toho, jak s naším webem interagujete. Vaše data nesdílíme s třetími stranami.',
        'accept_all' => 'Přijmout vše',
        'accept_essential' => 'Pouze nezbytné',
        'learn_more' => 'Zjistit více',
        'privacy_policy_url' => '/privacy-policy.php?lang=cz'
    ],
    'it' => [
        'title' => 'Utilizziamo i cookie',
        'message' => 'Questo sito web utilizza cookie essenziali per garantire il corretto funzionamento e cookie analitici per capire come interagisci con il nostro sito. Non condividiamo i tuoi dati con terze parti.',
        'accept_all' => 'Accetta tutto',
        'accept_essential' => 'Solo essenziali',
        'learn_more' => 'Per saperne di più',
        'privacy_policy_url' => '/privacy-policy.php?lang=it'
    ]
];

$cookie_lang = $cookie_texts[$current_lang_code] ?? $cookie_texts['en'];
?>

<!-- Cookie Notice -->
<div id="cookie-notice" class="cookie-notice cookie-notice-hidden">
    <div class="cookie-notice-container">
        <div class="cookie-notice-content">
            <div class="cookie-notice-title"><?php echo htmlspecialchars($cookie_lang['title']); ?></div>
            <p class="cookie-notice-message"><?php echo htmlspecialchars($cookie_lang['message']); ?></p>
        </div>
        <div class="cookie-notice-actions">
            <button id="cookie-accept-essential" class="cookie-btn cookie-btn-secondary">
                <?php echo htmlspecialchars($cookie_lang['accept_essential']); ?>
            </button>
            <button id="cookie-accept-all" class="cookie-btn cookie-btn-primary">
                <?php echo htmlspecialchars($cookie_lang['accept_all']); ?>
            </button>
            <a href="<?php echo htmlspecialchars($cookie_lang['privacy_policy_url']); ?>" class="cookie-learn-more" target="_blank">
                <?php echo htmlspecialchars($cookie_lang['learn_more']); ?>
            </a>
        </div>
    </div>
</div>
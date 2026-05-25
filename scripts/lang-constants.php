<?php
// scripts/lang-constants.php - Generuje JavaScript s jazykovými konstantami

// Ochrana proti přímému přístupu
define('APP_LOADED', true);

// Načtení jazykové konfigurace
require_once __DIR__ . '/../lang/config.php';

// Nastavení správného content-type pro JavaScript
header('Content-Type: application/javascript; charset=utf-8');

// Cachování na 1 hodinu (3600 sekund)
header('Cache-Control: public, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// JavaScript výstup
?>
// Automaticky generované jazykové konstanty pro JavaScript
// Generováno: <?php echo date('Y-m-d H:i:s'); ?>

const jsLang = {
    contact_form_sending: <?php echo json_encode($lang['contact_form_sending'] ?? 'Odesílám...', JSON_UNESCAPED_UNICODE); ?>,
    contact_form_success_js: <?php echo json_encode($lang['contact_form_success_js'] ?? 'Zpráva úspěšně odeslána! Brzy se Vám ozvu.', JSON_UNESCAPED_UNICODE); ?>,
    contact_form_error_js: <?php echo json_encode($lang['contact_form_error_js'] ?? 'Chyba při odesílání zprávy. Zkuste to prosím později.', JSON_UNESCAPED_UNICODE); ?>,
    contact_form_fill_all_fields: <?php echo json_encode($lang['contact_form_fill_all_fields'] ?? 'Vyplňte prosím všechna povinná pole.', JSON_UNESCAPED_UNICODE); ?>,
    contact_form_invalid_email: <?php echo json_encode($lang['contact_form_invalid_email'] ?? 'Neplatný formát e-mailu.', JSON_UNESCAPED_UNICODE); ?>,
    contact_form_error_network: <?php echo json_encode($lang['contact_form_error_network'] ?? 'Došlo k síťové chybě. Zkuste to prosím znovu.', JSON_UNESCAPED_UNICODE); ?>
};

const currentSiteLanguage = <?php echo json_encode($current_lang_code, JSON_UNESCAPED_UNICODE); ?>;

// Debug informace (pouze pro vývoj)
if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
    console.log('Language constants loaded:', jsLang);
    console.log('Current language:', currentSiteLanguage);
}
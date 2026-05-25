<?php
// includes/navbar.php
if (!defined('APP_LOADED')) exit;

$is_home = defined('IS_HOME_PAGE') && IS_HOME_PAGE;
$base = isset($base_path) ? $base_path : '';

// Helper for language links ensuring we don't duplicate ?lang=...
if (!function_exists('get_lang_switch_url')) {
    function get_lang_switch_url($target_lang) {
        $uri = $_SERVER['REQUEST_URI'];
        $uri = preg_replace('/([?&])lang=[^&]+/', '', $uri);
        $uri = preg_replace('/[&?]$/', '', $uri);
        $separator = (strpos($uri, '?') !== false) ? '&' : '?';
        return $uri . $separator . 'lang=' . $target_lang;
    }
}

$link_home = $is_home ? '#home' : $base . 'index.php?lang=' . $current_lang_code . '#home';
$link_about = $is_home ? '#about' : $base . 'index.php?lang=' . $current_lang_code . '#about';
$link_services = $is_home ? '#services' : $base . 'index.php?lang=' . $current_lang_code . '#services';
$link_portfolio = $is_home ? '#portfolio' : $base . 'index.php?lang=' . $current_lang_code . '#portfolio';
$link_why_me = $is_home ? '#why-me' : $base . 'index.php?lang=' . $current_lang_code . '#why-me';
$link_contact = $is_home ? '#contact' : $base . 'index.php?lang=' . $current_lang_code . '#contact';

$link_blog = $base . 'blog/index.php?lang=' . $current_lang_code;
$link_raspberry = $base . 'raspberry/index.php?lang=' . $current_lang_code;
$link_ebooks = $base . 'ebooks.php?lang=' . $current_lang_code;
?>
<header id="header">
    <div class="container header-container">
        <div class="logo">
            <a href="<?php echo htmlspecialchars($link_home); ?>" aria-label="<?php echo htmlspecialchars($lang['nav_home']); ?>">
                <svg width="60" height="30" viewBox="0 0 60 30" fill="none" xmlns="http://www.w3.org/2000/svg" class="logo-svg">
                  <text x="5" y="23" class="logo-svg-p">P</text>
                  <text x="28" y="23" class="logo-svg-d">D</text>
                </svg>
            </a>
        </div>
        <nav class="main-nav" aria-label="Main navigation">
            <ul id="main-nav-ul">
                <li><a href="<?php echo htmlspecialchars($link_home); ?>"><?php echo htmlspecialchars($lang['nav_home']); ?></a></li>
                <li><a href="<?php echo htmlspecialchars($link_about); ?>"><?php echo htmlspecialchars($lang['nav_about']); ?></a></li>
                <li><a href="<?php echo htmlspecialchars($link_services); ?>"><?php echo htmlspecialchars($lang['nav_services']); ?></a></li>
                <li><a href="<?php echo htmlspecialchars($link_portfolio); ?>"><?php echo htmlspecialchars($lang['nav_portfolio']); ?></a></li>
                <li><a href="<?php echo htmlspecialchars($link_why_me); ?>"><?php echo htmlspecialchars($lang['nav_why_me']); ?></a></li>
                <li><a href="<?php echo htmlspecialchars($link_contact); ?>"><?php echo htmlspecialchars($lang['nav_contact']); ?></a></li>
                
                <?php 
                $is_blog_active = strpos($_SERVER['REQUEST_URI'], '/blog/') !== false ? 'class="active"' : '';
                $is_rasp_active = strpos($_SERVER['REQUEST_URI'], '/raspberry/') !== false ? 'class="active"' : '';
                $is_ebooks_active = strpos($_SERVER['REQUEST_URI'], 'ebooks.php') !== false ? 'class="active"' : '';
                ?>
                <li><a href="<?php echo htmlspecialchars($link_blog); ?>" <?php echo $is_blog_active; ?>><?php echo htmlspecialchars($lang['nav_blog']); ?></a></li>
                <li><a href="<?php echo htmlspecialchars($link_raspberry); ?>" <?php echo $is_rasp_active; ?>><?php echo htmlspecialchars($lang['nav_raspberry'] ?? 'Raspberry Pi'); ?></a></li>
                <li><a href="<?php echo htmlspecialchars($link_ebooks); ?>" <?php echo $is_ebooks_active; ?>><?php echo htmlspecialchars($lang['nav_ebooks'] ?? 'E-books'); ?></a></li>
            </ul>
        </nav>
        <nav class="language-switcher" aria-label="Language selection">
            <ul>
                <?php if ($current_lang_code !== 'en'): ?>
                    <li><a href="<?php echo htmlspecialchars(get_lang_switch_url('en')); ?>" lang="en" hreflang="en"><?php echo htmlspecialchars($lang['lang_switch_en']); ?></a></li>
                <?php endif; ?>
                <?php if ($current_lang_code !== 'cz'): ?>
                    <li><a href="<?php echo htmlspecialchars(get_lang_switch_url('cz')); ?>" lang="cs" hreflang="cs"><?php echo htmlspecialchars($lang['lang_switch_cz']); ?></a></li>
                <?php endif; ?>
                <?php if ($current_lang_code !== 'it'): ?>
                    <li><a href="<?php echo htmlspecialchars(get_lang_switch_url('it')); ?>" lang="it" hreflang="it"><?php echo htmlspecialchars($lang['lang_switch_it']); ?></a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <button class="mobile-nav-toggle" aria-controls="main-nav-ul" aria-expanded="false">
            <span class="sr-only">Menu</span>
            <span class="hamburger-icon"></span>
        </button>
    </div>
</header>

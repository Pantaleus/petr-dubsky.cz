<?php
// lang/config.php - Konfigurace jazyků

// Ochrana proti přímému přístupu
if (!defined('APP_LOADED')) {
    die('Direct access not allowed.');
}

// --- Konfigurace dostupných jazyků ---
$available_langs = array(
    'en' => __DIR__ . '/en.php', 
    'cz' => __DIR__ . '/cz.php', 
    'it' => __DIR__ . '/it.php'  
);

$default_lang = 'cz';

// Získání aktuálního jazyka z URL parametru
$current_lang_code = isset($_GET['lang']) && array_key_exists($_GET['lang'], $available_langs) 
                    ? $_GET['lang'] 
                    : $default_lang;

$lang_file_path = $available_langs[$current_lang_code];

// Načtení jazykového souboru
if (file_exists($lang_file_path)) {
    require_once($lang_file_path);
} else {
    // Základní fallback, pokud jazykový soubor neexistuje
    error_log("FATAL: Language file '{$lang_file_path}' not found for language '{$current_lang_code}'.");
    
    // Fallback texty v angličtině
    $lang = [
        'meta_title' => 'Pietro Dubsky - IT Specialist',
        'meta_description' => 'Freelance IT services: web development, server administration, and more.',
        'meta_keywords' => 'freelance, IT, web developer, server admin, PHP, Python',
        'nav_home' => 'Home',
        'nav_about' => 'About Me',
        'nav_services' => 'Services',
        'nav_portfolio' => 'Portfolio',
        'nav_why_me' => 'Why Me?',
        'nav_contact' => 'Contact',
        'nav_blog' => 'Blog',
        'nav_ebooks' => 'E-books',
        'lang_switch_en' => 'English',
        'lang_switch_cz' => 'Česky',
        'lang_switch_it' => 'Italiano',
        'hero_greeting' => "Hi, I'm Pietro",
        'hero_main_title' => 'Your Reliable Partner for IT Solutions',
        'hero_subtitle' => 'Specializing in web development, automation, and server management.',
        'hero_cta_button' => 'My Services',
        'hero_contact_button' => 'Get in Touch',
        'about_title' => 'About Me',
        'about_intro' => 'Hello! I am a passionate IT professional...',
        'about_paragraph1' => 'My work ethic is built on diligence...',
        'about_paragraph2' => 'I embrace the freedom of remote work...',
        'about_photo_alt' => 'Photo of Pietro Dubsky',
        'services_title' => 'What I Offer',
        'services_intro_text' => 'A comprehensive range of IT services...',
        'service_webdev_title' => 'Web App Development',
        'service_webdev_description' => 'Custom web applications tailored to your needs...',
        'service_webdev_benefits_title' => 'Benefits:',
        'service_webdev_benefit1' => 'Tailored solutions',
        'service_webdev_benefit2' => 'Scalable architecture',
        'service_webdev_benefit3' => 'User-friendly design',
        'service_automation_title' => 'Process Automation',
        'service_automation_description' => 'Streamlining workflows with scripts...',
        'service_automation_benefits_title' => 'Benefits:',
        'service_automation_benefit1' => 'Increased efficiency',
        'service_automation_benefit2' => 'Error reduction',
        'service_automation_benefit3' => 'Focus on core tasks',
        'service_sysadmin_title' => 'System Administration',
        'service_sysadmin_description' => 'Managing Linux/Windows servers, databases...',
        'service_sysadmin_benefits_title' => 'Benefits:',
        'service_sysadmin_benefit1' => 'Reliable performance',
        'service_sysadmin_benefit2' => 'Proactive support',
        'service_sysadmin_benefit3' => 'Optimized infrastructure',
        'services_other_tech_title' => 'Other Technologies',
        'services_other_tech_list' => 'HTML, CSS, JavaScript, Docker...',
        'services_payment_options_title' => 'Payment Models',
        'services_payment_options_text' => 'Hourly, SLA, or project-based.',
        'portfolio_title' => 'My Work',
        'portfolio_intro_text' => 'Selected projects...',
        'portfolio_project1_title' => 'Project Alpha',
        'portfolio_project1_description' => 'Description of Project Alpha...',
        'portfolio_project1_technologies' => 'PHP, MySQL, JS',
        'why_me_title' => 'Why Choose Me?',
        'why_me_point1_title' => 'Reliability',
        'why_me_point1_text' => 'I deliver on promises.',
        'why_me_point2_title' => 'Expertise',
        'why_me_point2_text' => 'Broad technological knowledge.',
        'why_me_point3_title' => 'Detail-Oriented',
        'why_me_point3_text' => 'Focus on quality and precision.',
        'why_me_point4_title' => 'Partnership Approach',
        'why_me_point4_text' => 'I see clients as partners.',
        'contact_title' => 'Get in Touch',
        'contact_intro_text' => "Have a project? Let's talk.",
        'contact_form_name_label' => 'Your Name',
        'contact_form_name_placeholder' => 'Enter your name',
        'contact_form_email_label' => 'Your Email',
        'contact_form_email_placeholder' => 'Enter your email',
        'contact_form_message_label' => 'Your Message',
        'contact_form_message_placeholder' => 'How can I help you?',
        'contact_form_submit_button' => 'Send Message',
        'contact_direct_email_text' => 'Or email me at:',
        'contact_form_sending' => 'Sending...',
        'contact_form_success_js' => 'Message sent successfully!',
        'contact_form_error_js' => 'Error sending message. Please try again.',
        'contact_form_fill_all_fields' => 'Please fill in all required fields.',
        'contact_form_invalid_email' => 'Please enter a valid email address.',
        'contact_form_error_network' => 'A network error occurred. Please try again.',
        'footer_copyright' => '© ' . date("Y") . ' Pietro Dubsky. All rights reserved.',
        'footer_linkedin_link_title' => 'My LinkedIn Profile',
        'footer_github_link_title' => 'My GitHub Profile'
    ];
}

// Pomocná funkce pro generování URL s jazykem
function lang_url($lang_code_param, $base_url = 'index.php') {
    global $default_lang;
    return $lang_code_param === $default_lang ? (($base_url === 'index.php') ? '/' : $base_url) : $base_url . '?lang=' . $lang_code_param;
}
?>
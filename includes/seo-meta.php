<?php
// includes/seo-meta.php - Pokročilé SEO meta tagy a strukturovaná data

if (!defined('APP_LOADED')) {
    die('Direct access not allowed.');
}

// SEO konfigurace podle jazyka
$seo_config = [
    'en' => [
        'title' => 'Pietro Dubsky - PHP & Python Developer | Linux Admin',
        'description' => 'Expert freelance developer specializing in PHP, Python programming, and Linux system administration. Remote work worldwide.',
        'keywords' => 'freelance PHP developer, Python programmer, Linux system administrator, remote web developer, server management, automation scripting, API development, database optimization, DevOps consultant',
        'og_title' => 'Pietro Dubsky - Expert PHP/Python Developer & Linux Admin',
        'og_description' => 'Professional freelance developer: PHP & Python programming, Linux administration, server automation. Working remotely worldwide.',
        'twitter_title' => 'Pietro Dubsky - Freelance Developer & Linux Admin',
        'twitter_description' => 'Expert PHP/Python developer and Linux system administrator available for remote projects worldwide.',
        'canonical_lang' => 'en',
        'hreflang' => 'en'
    ],
    'cz' => [
        'title' => 'Pietro Dubsky - PHP & Python Vývojář | Linux Admin',
        'description' => 'Odborný freelance vývojář specializující se na PHP, Python programování a Linux administraci. Práce na dálku po celém světě.',
        'keywords' => 'freelance PHP vývojář, Python programátor, Linux administrátor, vzdálený webový vývojář, správa serverů, automatizační skripty, API vývoj, optimalizace databází',
        'og_title' => 'Pietro Dubsky - Odborný PHP/Python Vývojář a Linux Admin',
        'og_description' => 'Profesionální freelance vývojář: PHP & Python programování, Linux administrace, automatizace serverů. Práce na dálku.',
        'twitter_title' => 'Pietro Dubsky - Freelance Vývojář a Linux Admin',
        'twitter_description' => 'Odborný PHP/Python vývojář a Linux administrátor dostupný pro vzdálené projekty po celém světě.',
        'canonical_lang' => 'cs',
        'hreflang' => 'cs'
    ],
    'it' => [
        'title' => 'Pietro Dubsky - Sviluppatore PHP & Python | Admin Linux',
        'description' => 'Sviluppatore freelance esperto specializzato in programmazione PHP, Python e amministrazione Linux. Lavoro remoto mondiale.',
        'keywords' => 'sviluppatore freelance PHP, programmatore Python, amministratore Linux, sviluppatore web remoto, gestione server, script automazione, sviluppo API, ottimizzazione database',
        'og_title' => 'Pietro Dubsky - Sviluppatore Esperto PHP/Python e Admin Linux',
        'og_description' => 'Sviluppatore freelance professionale: programmazione PHP & Python, amministrazione Linux, automazione server. Lavoro remoto.',
        'twitter_title' => 'Pietro Dubsky - Sviluppatore Freelance e Admin Linux',
        'twitter_description' => 'Sviluppatore esperto PHP/Python e amministratore Linux disponibile per progetti remoti in tutto il mondo.',
        'canonical_lang' => 'it',
        'hreflang' => 'it'
    ]
];

// Získání SEO dat pro aktuální jazyk
$seo = $seo_config[$current_lang_code] ?? $seo_config['en'];

// Strukturovaná data (JSON-LD)
$structured_data = [
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => 'Pietro Dubsky',
    'alternateName' => 'Petr Dubský',
    'jobTitle' => 'Freelance Software Developer',
    'description' => $seo['description'],
    'url' => 'https://petr-dubsky.cz',
    'image' => 'https://petr-dubsky.cz/assets/images/pietro-dubsky-profile.jpg',
    'email' => 'mailto:pietro@petr-dubsky.cz',
    'telephone' => '+420704735550',
    'address' => [
        '@type' => 'PostalAddress',
        'addressLocality' => 'Praha',
        'addressCountry' => 'CZ'
    ],
    'sameAs' => [
        'https://www.linkedin.com/in/pietro-dubsky',
        'https://gitlab.petr-dubsky.cz/pantaleus',
        'https://www.facebook.com/pietro.dubsky',
        'https://www.instagram.com/pietro.dubsky',
        'https://x.com/PietroDubsky'
    ],
    'worksFor' => [
        '@type' => 'Organization',
        'name' => 'Dubsky.it',
        'url' => 'https://petr-dubsky.cz'
    ],
    'hasOccupation' => [
        '@type' => 'Occupation',
        'name' => 'Freelance Software Developer',
        'skills' => 'PHP, Python, Linux, MySQL, PostgreSQL, Docker, Git'
    ],
    'knowsAbout' => [
        'PHP', 'Python', 'Linux', 'Web Development', 'DevOps', 'Docker',
        'Server Management', 'Database Optimization', 'API Development',
        'Automation Scripting', 'MySQL', 'PostgreSQL', 'Git', 'RESTful APIs'
    ],
    'workLocation' => [
        '@type' => 'Place',
        'name' => 'Remote Worldwide'
    ]
];



// Breadcrumb strukturovaná data
$breadcrumb_data = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => 'https://petr-dubsky.cz'
        ]
    ]
];

// FAQ strukturovaná data (AEO)
$faq_data = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => $lang['faq_q1'] ?? 'Jak probíhá vzdálená spolupráce?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $lang['faq_a1'] ?? 'Spolupracujeme plně na dálku. Komunikace probíhá přes e-mail, videohovory a nástroje pro správu projektů, takže máte vždy přehled o průběhu.'
            ]
        ],
        [
            '@type' => 'Question',
            'name' => $lang['faq_q2'] ?? 'S jakými technologiemi nejčastěji pracujete?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $lang['faq_a2'] ?? 'Vyvíjím hlavně v PHP, Pythonu, JavaScriptu a spravuji systémy Linux. Rád se přizpůsobím vaší aktuální infrastruktuře.'
            ]
        ]
    ]
];

$structured_data_organization = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => 'Dubsky.it',
    'url' => 'https://petr-dubsky.cz',
    'logo' => 'https://petr-dubsky.cz/assets/images/logo.svg',
    'sameAs' => [
        'https://www.linkedin.com/in/pietro-dubsky',
        'https://gitlab.petr-dubsky.cz/pantaleus',
        'https://www.facebook.com/pietro.dubsky',
        'https://www.instagram.com/pietro.dubsky',
        'https://x.com/PietroDubsky'
    ],
    'contactPoint' => [
        '@type' => 'ContactPoint',
        'telephone' => '+420704735550',
        'contactType' => 'customer support',
        'areaServed' => 'Worldwide',
        'availableLanguage' => ['Czech', 'English', 'Italian']
    ],
    'address' => [
        '@type' => 'PostalAddress',
        'addressLocality' => 'Praha',
        'addressCountry' => 'CZ'
    ],
    'department' => [
        '@type' => 'Organization',
        'name' => 'IT Services',
        'serviceType' => [
            'Custom Software Development',
            'Web Application Development',
            'Linux Server Administration',
            'DevOps & Automation',
            'SEO & Online Marketing'
        ],
        'areaServed' => 'Worldwide'
    ]
];


// Website strukturovaná data
$website_data = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'Pietro Dubsky - IT Specialist',
    'url' => 'https://petr-dubsky.cz',
    'description' => $seo['description'],
    'author' => [
        '@type' => 'Person',
        'name' => 'Pietro Dubsky'
    ],
    'inLanguage' => [$seo['hreflang']],
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => 'https://petr-dubsky.cz/?search={search_term_string}',
        'query-input' => 'required name=search_term_string'
    ]
];

// Professional Service strukturovaná data
$service_data = [
    '@context' => 'https://schema.org',
    '@type' => 'ProfessionalService',
    'name' => 'Pietro Dubsky - Freelance Development Services',
    'description' => $seo['description'],
    'provider' => [
        '@type' => 'Person',
        'name' => 'Pietro Dubsky'
    ],
    'areaServed' => [
        [
            '@type' => 'Place',
            'name' => 'Worldwide'
        ]
    ],
    'serviceType' => [
        'PHP Development',
        'Python Programming',
        'Linux System Administration',
        'Web Application Development',
        'Server Management',
        'Database Optimization',
        'API Development',
        'Automation Scripting',
        'DevOps Consulting'
    ],
    'availableChannel' => [
        '@type' => 'ServiceChannel',
        'serviceType' => 'Remote Work',
        'availableLanguage' => ['English', 'Czech', 'Italian']
    ],
    'url' => 'https://petr-dubsky.cz'
];
?>
<?php
// sitemap.xml.php – Dynamický XML sitemap generátor

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

$base_url = 'https://petr-dubsky.cz';
$last_modified = date('c');
$languages = ['en', 'cz', 'it'];
$static_pages = [
    ['url'=>'/','priority'=>'1.0','changefreq'=>'monthly','multilang'=>true],
    ['url'=>'/blog/','priority'=>'0.8','changefreq'=>'weekly','multilang'=>true],
    ['url'=>'/ebooks.php','priority'=>'0.7','changefreq'=>'monthly','multilang'=>true],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . PHP_EOL;
echo '        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . PHP_EOL;
echo '        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ' .
     'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL . PHP_EOL;

foreach ($static_pages as $page) {
    foreach ($languages as $lang) {
        $url = $base_url;
        if ($page['multilang']) {
            if ($lang !== 'cz') {
                $url .= ($page['url']==='/' ? '?lang='.$lang : $page['url'].'?lang='.$lang);
            } else {
                $url .= ($page['url']==='/' ? '' : $page['url']);
            }
        } else {
            $url .= $page['url'];
        }

        echo "  <url>" . PHP_EOL;
        echo "    <loc>" . htmlspecialchars($url) . "</loc>" . PHP_EOL;
        echo "    <lastmod>" . $last_modified . "</lastmod>" . PHP_EOL;
        echo "    <changefreq>" . $page['changefreq'] . "</changefreq>" . PHP_EOL;
        echo "    <priority>" . $page['priority'] . "</priority>" . PHP_EOL;

        if ($page['multilang']) {
            foreach ($languages as $alt) {
                $alt_url = $base_url;
                if ($alt !== 'cz') {
                    $alt_url .= ($page['url']==='/' ? '?lang='.$alt : $page['url'].'?lang='.$alt);
                } else {
                    $alt_url .= ($page['url']==='/' ? '' : $page['url']);
                }
                $hreflang = ($alt === 'cz' ? 'cs' : $alt);
                echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$hreflang}\" href=\"" .
                     htmlspecialchars($alt_url) . "\" />" . PHP_EOL;
            }
            $default_url = $base_url . ($page['url']==='/' ? '' : $page['url']);
            echo "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" .
                 htmlspecialchars($default_url) . "\" />" . PHP_EOL;
        }

        echo "  </url>" . PHP_EOL . PHP_EOL;
    }
}

echo '</urlset>' . PHP_EOL;
?>

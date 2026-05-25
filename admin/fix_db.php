<?php
$dir = new RecursiveDirectoryIterator(__DIR__ . '/..');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$count = 0;
foreach ($files as $file) {
    if (strpos($file[0], 'fix_db.php') !== false) continue;
    $content = file_get_contents($file[0]);
    if (strpos($content, 'blog/db_config.php') !== false || strpos($content, 'blog/config_db.php') !== false) {
        $content = str_replace(
            ["'/../config/database.php'", "'/../config/database.php'", "'/config/database.php'", "'/config/database.php'"],
            ["'/../config/database.php'", "'/../config/database.php'", "'/config/database.php'", "'/config/database.php'"],
            $content
        );
        file_put_contents($file[0], $content);
        echo "Updated: " . $file[0] . "\n";
        $count++;
    }
}
echo "Done. Updated $count files.\n";


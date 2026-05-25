<?php
define('APP_LOADED', true);
require 'config/secure_settings.php';
require 'blog/db_config.php';
$stmt = $pdo->query('SHOW CREATE TABLE articles');
print_r($stmt->fetch());
$stmt = $pdo->query('SHOW CREATE TABLE article_translations');
print_r($stmt->fetch());
?>


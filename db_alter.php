<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_LOADED', true);
require 'config/secure_settings.php';
require 'blog/db_config.php';

try {
    $sql = "ALTER TABLE articles ADD COLUMN article_type ENUM('blog', 'raspberry') NOT NULL DEFAULT 'blog' AFTER slug;";
    $pdo->exec($sql);
    echo "SUCCESS";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "SUCCESS_ALREADY_EXISTS";
    } else {
        echo "ERROR: " . $e->getMessage();
    }
}
?>


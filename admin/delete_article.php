<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

define('APP_LOADED', true);
require_once __DIR__ . '/../config/secure_settings.php';
require_once __DIR__ . '/../config/database.php';

$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = $_GET['token'] ?? '';

// === CSRF OCHRANA ===
if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    die("Security Error: Neplatný nebo chybějící CSRF klíč zabránil smazání.");
}

if ($article_id > 0) {
    try {
        $stmtGet = $pdo->prepare("SELECT article_type FROM articles WHERE id = :id");
        $stmtGet->bindParam(':id', $article_id, PDO::PARAM_INT);
        $stmtGet->execute();
        $type = $stmtGet->fetchColumn() ?: 'blog';

        $stmt = $pdo->prepare("DELETE FROM articles WHERE id = :id");
        $stmt->bindParam(':id', $article_id, PDO::PARAM_INT);
        $stmt->execute();

        header("Location: blog.php?type=" . $type . "&status=deleted");
        exit;

    } catch (PDOException $e) {
        // Zde byste měli mít lepší error handling, např. přesměrování s chybovou zprávou
        die("Chyba při mazání článku: " . $e->getMessage());
    }
} else {
    header("Location: index.php"); // Pokud není ID, přesměruj zpět
    exit;
}
?>

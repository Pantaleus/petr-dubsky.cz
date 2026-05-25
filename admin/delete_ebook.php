<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
define('APP_LOADED', true);
require_once __DIR__ . '/../config/secure_settings.php';
require_once __DIR__ . '/../config/database.php';

$ebook_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ebook_id > 0) {
    try {
        // Nejprve získáme názvy souborů k smazání
        $stmt_files = $pdo->prepare("SELECT pdf_base_filename, cover_base_filename FROM ebooks WHERE id = :id");
        $stmt_files->bindParam(':id', $ebook_id, PDO::PARAM_INT);
        $stmt_files->execute();
        $files_to_delete = $stmt_files->fetch();

        // Smazání záznamu z DB (díky ON DELETE CASCADE se smažou i překlady)
        $stmt_delete = $pdo->prepare("DELETE FROM ebooks WHERE id = :id");
        $stmt_delete->bindParam(':id', $ebook_id, PDO::PARAM_INT);
        
        if ($stmt_delete->execute()) {
            // Pokud smazání z DB proběhlo, pokusíme se smazat soubory
            if ($files_to_delete) {
                if (!empty($files_to_delete['pdf_base_filename'])) {
                    $pdf_path = $_SERVER['DOCUMENT_ROOT'] . '/' . EBOOK_PDF_DISPLAY_PATH . $files_to_delete['pdf_base_filename'];
                    if (file_exists($pdf_path)) {
                        @unlink($pdf_path);
                    }
                }
                if (!empty($files_to_delete['cover_base_filename'])) {
                    // Smazání základní obálky a jejích jazykových mutací
                    $cover_base = $files_to_delete['cover_base_filename'];
                    $cover_name_no_ext = pathinfo($cover_base, PATHINFO_FILENAME);
                    $cover_ext = pathinfo($cover_base, PATHINFO_EXTENSION);
                    
                    $langs_to_check = ['en', 'cz', 'it', '']; // Prázdný pro základní soubor bez jaz. přípony
                    foreach ($langs_to_check as $lc) {
                        $cover_path_variant = $_SERVER['DOCUMENT_ROOT'] . '/' . EBOOK_COVER_DISPLAY_PATH . $cover_name_no_ext . ($lc ? '_' . $lc : '') . '.' . $cover_ext;
                        if (empty($lc) && !file_exists($cover_path_variant)) { // Pokud základní soubor bez _en/_cz/... neexistuje, zkusíme jen s příponou
                             $cover_path_variant = $_SERVER['DOCUMENT_ROOT'] . '/' . EBOOK_COVER_DISPLAY_PATH . $cover_name_no_ext . '.' . $cover_ext;
                        }
                         if (file_exists($cover_path_variant)) {
                            @unlink($cover_path_variant);
                        }
                    }
                    // Pro jistotu ještě smazání souboru přesně podle DB (pokud by neměl _lang koncovku)
                     $cover_path_direct = $_SERVER['DOCUMENT_ROOT'] . '/' . EBOOK_COVER_DISPLAY_PATH . $cover_base;
                     if (file_exists($cover_path_direct)) {
                        @unlink($cover_path_direct);
                     }
                }
            }
            $_SESSION['admin_message'] = "E-book byl úspěšně smazán.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['admin_message'] = "E-book se nepodařilo smazat z databáze.";
            $_SESSION['message_type'] = "error";
        }
        header("Location: ebooks_list.php");
        exit;

    } catch (PDOException $e) {
        error_log("Chyba při mazání e-booku ID {$ebook_id}: " . $e->getMessage());
        $_SESSION['admin_message'] = "Chyba při mazání e-booku: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: ebooks_list.php");
        exit;
    }
} else {
    header("Location: ebooks_list.php"); 
    exit;
}
?>

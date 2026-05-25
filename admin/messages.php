<?php
// admin/messages.php - Jednoduchý admin panel pro správu kontaktních zpráv
// POZNÁMKA: Toto je pouze ukázka. V produkci přidejte autentifikaci!

define('APP_LOADED', true);
require_once __DIR__ . '/../config/secure_settings.php';
require_once __DIR__ . '/../config/database.php';

// Základní ochrana (v produkci použijte robustnější řešení)
//session_start();
//if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Zde byste měli mít přihlašovací formulář
//    die('Přístup odepřen. Implementujte autentifikaci.');
//}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtry
$filter_processed = isset($_GET['processed']) ? $_GET['processed'] : 'all';
$filter_spam = isset($_GET['spam']) ? (float)$_GET['spam'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sestavení WHERE podmínek
$where_conditions = [];
$params = [];

if ($filter_processed !== 'all') {
    $where_conditions[] = "is_processed = ?";
    $params[] = $filter_processed === 'yes' ? 1 : 0;
}

if ($filter_spam !== null) {
    $where_conditions[] = "spam_score >= ?";
    $params[] = $filter_spam;
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR message LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Dotaz na zprávy
try {
    $sql = "SELECT * FROM contact_messages {$where_clause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Počet celkem
    $count_sql = "SELECT COUNT(*) FROM contact_messages {$where_clause}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_messages = $count_stmt->fetchColumn();
    $total_pages = ceil($total_messages / $limit);
} catch (PDOException $e) {
    die('Chyba databáze: ' . $e->getMessage());
}

// Zpracování akcí
if ($_POST) {
    if (isset($_POST['action']) && isset($_POST['message_id'])) {
        $message_id = (int)$_POST['message_id'];
        
        try {
            switch ($_POST['action']) {
                case 'mark_processed':
                    $stmt = $pdo->prepare("UPDATE contact_messages SET is_processed = 1, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$message_id]);
                    break;
                    
                case 'mark_unprocessed':
                    $stmt = $pdo->prepare("UPDATE contact_messages SET is_processed = 0, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$message_id]);
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
                    $stmt->execute([$message_id]);
                    break;
                    
                case 'add_note':
                    $note = trim($_POST['note'] ?? '');
                    $stmt = $pdo->prepare("UPDATE contact_messages SET notes = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$note, $message_id]);
                    break;
            }
            
            // Přesměrování pro zabránění opětovnému odeslání
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (PDOException $e) {
            $error = "Chyba při zpracování: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa kontaktních zpráv</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .filters { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .filters form { display: inline-block; margin-right: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .spam-high { background-color: #ffebee; }
        .spam-medium { background-color: #fff3e0; }
        .processed { opacity: 0.7; }
        .message-text { max-width: 300px; max-height: 100px; overflow-y: auto; font-size: 0.9em; }
        .action-buttons { white-space: nowrap; }
        .action-buttons button { margin: 2px; padding: 4px 8px; font-size: 0.8em; }
        .pagination { margin: 20px 0; }
        .pagination a { padding: 8px 12px; margin: 0 2px; text-decoration: none; border: 1px solid #ddd; }
        .pagination .current { background-color: #007cba; color: white; }
        .note-form { margin-top: 5px; }
        .note-form textarea { width: 100%; height: 40px; font-size: 0.8em; }
    </style>
</head>
<body>
    <h1>Správa kontaktních zpráv</h1>
    
    <?php if (isset($error)): ?>
        <div style="color: red; margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Filtry -->
    <div class="filters">
        <form method="GET">
            <label>Stav:</label>
            <select name="processed">
                <option value="all" <?php echo $filter_processed === 'all' ? 'selected' : ''; ?>>Všechny</option>
                <option value="no" <?php echo $filter_processed === 'no' ? 'selected' : ''; ?>>Nezpracované</option>
                <option value="yes" <?php echo $filter_processed === 'yes' ? 'selected' : ''; ?>>Zpracované</option>
            </select>
            <button type="submit">Filtrovat</button>
        </form>
        
        <form method="GET">
            <input type="hidden" name="processed" value="<?php echo htmlspecialchars($filter_processed); ?>">
            <label>Spam skóre min:</label>
            <input type="number" name="spam" step="0.1" min="0" max="1" value="<?php echo $filter_spam; ?>" style="width: 80px;">
            <button type="submit">Filtrovat</button>
        </form>
        
        <form method="GET">
            <input type="hidden" name="processed" value="<?php echo htmlspecialchars($filter_processed); ?>">
            <?php if ($filter_spam !== null): ?>
                <input type="hidden" name="spam" value="<?php echo $filter_spam; ?>">
            <?php endif; ?>
            <label>Hledat:</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Jméno, email nebo zpráva">
            <button type="submit">Hledat</button>
            <?php if (!empty($search)): ?>
                <a href="?processed=<?php echo $filter_processed; ?>&spam=<?php echo $filter_spam; ?>">Zrušit</a>
            <?php endif; ?>
        </form>
    </div>
    
    <p>Zobrazeno: <?php echo count($messages); ?> zpráv z celkem <?php echo $total_messages; ?></p>
    
    <!-- Tabulka zpráv -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Datum</th>
                <th>Jméno</th>
                <th>Email</th>
                <th>Zpráva</th>
                <th>Země</th>
                <th>Prohlížeč</th>
                <th>Čas na webu</th>
                <th>Spam skóre</th>
                <th>Email odeslán</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($messages as $msg): ?>
                <?php
                $spam_class = '';
                if ($msg['spam_score'] > 0.7) $spam_class = 'spam-high';
                elseif ($msg['spam_score'] > 0.3) $spam_class = 'spam-medium';
                
                $row_class = $msg['is_processed'] ? 'processed' : '';
                ?>
                <tr class="<?php echo $spam_class . ' ' . $row_class; ?>">
                    <td><?php echo $msg['id']; ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($msg['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($msg['name']); ?></td>
                    <td><a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>"><?php echo htmlspecialchars($msg['email']); ?></a></td>
                    <td>
                        <div class="message-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                        <?php if ($msg['notes']): ?>
                            <div style="margin-top: 5px; font-size: 0.8em; color: #666; border-left: 3px solid #ccc; padding-left: 8px;">
                                <strong>Poznámka:</strong> <?php echo nl2br(htmlspecialchars($msg['notes'])); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($msg['country_name']): ?>
                            <?php echo htmlspecialchars($msg['country_name']); ?>
                            <?php if ($msg['city']): ?>
                                <br><small><?php echo htmlspecialchars($msg['city']); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($msg['browser_name'] . ' ' . $msg['browser_version']); ?>
                        <br><small><?php echo htmlspecialchars($msg['operating_system']); ?></small>
                        <?php if ($msg['device_type'] !== 'Desktop'): ?>
                            <br><small><?php echo htmlspecialchars($msg['device_type']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($msg['time_on_site']): ?>
                            <?php echo gmdate("H:i:s", $msg['time_on_site']); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-weight: bold; color: <?php echo $msg['spam_score'] > 0.7 ? 'red' : ($msg['spam_score'] > 0.3 ? 'orange' : 'green'); ?>">
                            <?php echo number_format($msg['spam_score'], 2); ?>
                        </span>
                    </td>
                    <td><?php echo $msg['email_sent'] ? '✓' : '✗'; ?></td>
                    <td class="action-buttons">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                            <?php if ($msg['is_processed']): ?>
                                <button type="submit" name="action" value="mark_unprocessed" title="Označit jako nezpracované">Neproc.</button>
                            <?php else: ?>
                                <button type="submit" name="action" value="mark_processed" title="Označit jako zpracované">Proc.</button>
                            <?php endif; ?>
                        </form>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Opravdu smazat tuto zprávu?');">
                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                            <button type="submit" name="action" value="delete" style="color: red;" title="Smazat zprávu">Smazat</button>
                        </form>
                        
                        <div class="note-form">
                            <form method="POST">
                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                <textarea name="note" placeholder="Přidat poznámku..."><?php echo htmlspecialchars($msg['notes'] ?? ''); ?></textarea>
                                <button type="submit" name="action" value="add_note">Uložit</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Stránkování -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php
                $url_params = http_build_query(array_merge($_GET, ['page' => $i]));
                $current_class = ($i === $page) ? 'current' : '';
                ?>
                <a href="?<?php echo $url_params; ?>" class="<?php echo $current_class; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    
    <hr>
    <p><small>Celkem zpráv v databázi: <?php echo $total_messages; ?> | Aktuální stránka: <?php echo $page; ?>/<?php echo $total_pages; ?></small></p>
</body>
</html>

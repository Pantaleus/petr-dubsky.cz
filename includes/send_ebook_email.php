<?php
// petr-dubsky.cz/includes/send_ebook_email.php

// Načtení tříd PHPMailer, pokud nejsou již načteny
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // Cesta z /includes/ o úroveň výše do kořene, pak do /PHPMailer/
    // Ověřte si tyto cesty podle vaší struktury
    require_once __DIR__ . '/../PHPMailer/Exception.php'; 
    require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/SMTP.php';
}

/**
 * Funkce pro odeslání e-mailu s e-bookem pomocí PHPMailer.
 * @param array $smtpConfig Pole s SMTP konfigurací (host, user, pass, port, secure_string).
 *                       'secure_string' by měla být hodnota jako 'ssl' nebo 'tls'.
 * ... (ostatní parametry)
 */
function sendEbookViaPHPMailer(
    string $recipientEmail, 
    ?string $recipientName, 
    string $subject, 
    string $body,
    array $smtpConfig, 
    string $fromEmail,
    string $fromName,
    string $replyToEmail,
    string $replyToName
): bool {
    $mail = new PHPMailer(true); // Povolí výjimky

    try {
        // Nastavení serveru
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Odkomentujte pro detailní log při ladění
        $mail->isSMTP();
        $mail->Host       = $smtpConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpConfig['user'];
        $mail->Password   = $smtpConfig['pass']; 
        $mail->SMTPSecure = $smtpConfig['secure_string']; 
        $mail->Port       = $smtpConfig['port'];
        $mail->CharSet    = 'UTF-8';

        // ---- ZAČÁTEK ÚPRAVY PRO VYPNUTÍ OVĚŘENÍ SSL (POUZE PRO TESTOVÁNÍ!) ----
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true 
            )
        );
        // ---- KONEC ÚPRAVY PRO VYPNUTÍ OVĚŘENÍ SSL ----

        // Příjemci a odesílatel
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipientEmail, $recipientName ?? ''); 
        $mail->addReplyTo($replyToEmail, $replyToName);

        // Obsah
        $mail->isHTML(false); 
        $mail->Subject = $subject;
        $mail->Body    = $body;

        if ($mail->send()) {
            return true;
        } else {
            // Toto by se nemělo stát, pokud je povoleno true pro výjimky, chyba by měla být zachycena v catch bloku
            error_log("PHPMailer send() returned false without an exception for '{$recipientEmail}': {$mail->ErrorInfo}");
            return false;
        }

    } catch (Exception $e) {
        error_log("PHPMailer error sending e-book to '{$recipientEmail}': {$mail->ErrorInfo} (Underlying exception: {$e->getMessage()})");
        return false;
    }
}
?>
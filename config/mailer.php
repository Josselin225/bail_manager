<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Crée et retourne une instance PHPMailer préconfigurée.
 * Les paramètres SMTP sont lus depuis .env
 */
function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);

    $host       = $_ENV['MAIL_HOST']       ?? 'smtp.gmail.com';
    $port       = (int)($_ENV['MAIL_PORT'] ?? 587);
    $username   = $_ENV['MAIL_USER']       ?? '';
    $password   = $_ENV['MAIL_PASS']       ?? '';
    $fromEmail  = $_ENV['MAIL_FROM']       ?? $username;
    $fromName   = $_ENV['MAIL_FROM_NAME']  ?? 'BailManager';

    if (!empty($username)) {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = ($port === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;
    }

    $mail->CharSet  = 'UTF-8';
    $mail->setFrom($fromEmail, $fromName);

    return $mail;
}

/**
 * Envoie un email simple.
 * Retourne true en cas de succès, false sinon (l'erreur est loggée).
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject  = $subject;
        $mail->isHTML(true);
        $mail->Body     = $htmlBody;
        $mail->AltBody  = strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi email à $toEmail : " . $e->getMessage());
        return false;
    }
}

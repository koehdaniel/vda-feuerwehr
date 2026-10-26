<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Verschickt eine Text-Mail, optional mit Anhängen [Dateiname => Inhalt] und einer CC-Adresse.
 * Wirft bei Fehlern eine PHPMailer\PHPMailer\Exception.
 */
function send_mail(string $to, string $subject, string $body, array $attachments = [], ?string $cc = null): void
{
    $c = config()['smtp'];
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $c['host'];
    $mail->Port = (int)$c['port'];
    $mail->SMTPSecure = $c['secure'];
    $mail->SMTPAutoTLS = $c['secure'] !== '';
    $mail->SMTPAuth = $c['user'] !== '';
    $mail->Username = $c['user'];
    $mail->Password = $c['pass'];
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->setFrom($c['from'], $c['from_name']);
    $mail->addAddress($to);
    if ($cc !== null && $cc !== '' && strcasecmp($cc, $to) !== 0) {
        $mail->addCC($cc);
    }
    $mail->Subject = $subject;
    $mail->Body = $body;
    foreach ($attachments as $filename => $content) {
        $mail->addStringAttachment($content, $filename, PHPMailer::ENCODING_BASE64, 'application/pdf');
    }

    // Lokale Entwicklung: Mail als .eml-Datei ablegen statt verschicken
    $dir = config()['mail_dir'] ?? '';
    if ($dir !== '') {
        $mail->preSend();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . date('Ymd-His') . '_' . slug($to) . '_' . bin2hex(random_bytes(3)) . '.eml', $mail->getSentMIMEMessage());
        return;
    }
    $mail->send();
}

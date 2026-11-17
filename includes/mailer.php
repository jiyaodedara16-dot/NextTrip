<?php
require_once __DIR__ . '/../config/mail_config.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/* Sends one HTML email. $attachments: [['data' => string, 'name' => string, 'type' => mime], ...] */
function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = '', array $attachments = []): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_SMTP_USER;
        $mail->Password   = MAIL_SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_SMTP_PORT;
        $mail->Timeout    = 8;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        foreach ($attachments as $a) {
            $mail->addStringAttachment($a['data'], $a['name'], 'base64', $a['type'] ?? 'application/octet-stream');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('send_mail failed for ' . $toEmail . ': ' . $mail->ErrorInfo);
        return false;
    }
}
<?php
/**
 * includes/otp.php
 * ------------------------------------------------------------
 * 6-digit email OTP helpers shared by the user and agent signup
 * flows in auth/ajax/*.php. The OTP itself is never stored on
 * disk/DB in plain text long-term — only its hash sits in the
 * visitor's own session for the few minutes the signup wizard is
 * open (see auth/ajax/send_otp.php).
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/mailer.php';

function generate_otp(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function otp_email_html(string $name, string $code): string {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    return '
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:420px;margin:0 auto;padding:32px 28px;background:#fdfbf6;border-radius:16px;">
      <p style="color:#556155;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin:0 0 8px;">NextTrip verification code</p>
      <h2 style="color:#12301d;margin:0 0 16px;">Hi ' . $safeName . ',</h2>
      <p style="color:#556155;font-size:14px;line-height:1.6;margin:0 0 22px;">Use this code to finish creating your NextTrip account. It expires in 10 minutes.</p>
      <div style="font-family:\'Courier New\',monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#17452a;background:#eaf7f0;border-radius:12px;padding:18px 0;text-align:center;">' . $code . '</div>
      <p style="color:#8b988a;font-size:12px;line-height:1.6;margin:22px 0 0;">Didn\'t request this? You can safely ignore this email.</p>
    </div>';
}

/**
 * Generates + emails an OTP. Always logs to logs/otp_debug.log when
 * MAIL_DEBUG_LOG_OTP is on (see config/mail_config.php) so the code
 * is recoverable during development even if the SMTP send itself
 * fails or hasn't been confirmed working yet.
 *
 * Returns ['code' => '123456', 'sent' => bool] — the caller hashes
 * and stashes `code` in the session either way, but only `sent`
 * tells you whether the email actually left the server.
 */
function send_otp_email(string $email, string $name): array {
    $code = generate_otp();

    if (defined('MAIL_DEBUG_LOG_OTP') && MAIL_DEBUG_LOG_OTP) {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents(
            $dir . '/otp_debug.log',
            '[' . date('Y-m-d H:i:s') . "] {$email} -> {$code}\n",
            FILE_APPEND
        );
    }

    $sent = send_mail($email, $name, 'Your NextTrip verification code', otp_email_html($name, $code));

    return ['code' => $code, 'sent' => $sent];
}

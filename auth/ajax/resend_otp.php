<?php
/**
 * auth/ajax/resend_otp.php
 * ------------------------------------------------------------
 * Re-sends a fresh code for whatever signup is already staged in
 * the session by send_otp.php — no need to re-validate or re-upload
 * anything, that data is already sitting in $_SESSION['pending_signup'].
 * ------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/otp.php';

$pending = $_SESSION['pending_signup'] ?? null;
if (!$pending) {
    http_response_code(410);
    echo json_encode(['success' => false, 'error' => 'expired', 'details' => ['otp_session_expired']]);
    exit;
}

$now = time();
if ($now - $pending['last_sent_at'] < 20) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'cooldown', 'details' => ['otp_cooldown']]);
    exit;
}

$displayName = $pending['kind'] === 'agent' ? $pending['fields']['fullName'] : $pending['fields']['firstName'];
$otp = send_otp_email($pending['email'], $displayName);

$_SESSION['pending_signup']['otp_hash']     = hash('sha256', $otp['code']);
$_SESSION['pending_signup']['otp_expires']  = $now + 600;
$_SESSION['pending_signup']['otp_attempts'] = 0;
$_SESSION['pending_signup']['last_sent_at'] = $now;

echo json_encode(['success' => true, 'mail_sent' => $otp['sent']]);

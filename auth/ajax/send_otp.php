<?php
/**
 * auth/ajax/send_otp.php
 * ------------------------------------------------------------
 * Step 1 of signup. Validates everything server-side (never trust
 * the client-side checks in auth.js alone), stages the validated
 * data — and, for agents, the uploaded files — in the session, then
 * emails a 6-digit code. NOTHING is written to `accounts` yet; that
 * only happens in verify_otp.php once the code checks out, which is
 * the whole point of gating signup behind email verification.
 *
 * POST kind = 'user' | 'agent', plus that kind's fields (see below).
 * Response: { success:true } or { success:false, details:[codes] }
 * — auth.js maps each code to the field it belongs to, same pattern
 * as contact.js.
 * ------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/otp.php';

function fail(array $details, int $code = 422): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => 'validation_failed', 'details' => array_values(array_unique($details))]);
    exit;
}

$kind = trim($_POST['kind'] ?? '');
if (!in_array($kind, ['user', 'agent'], true)) fail(['invalid_kind']);

// --- Simple per-session cooldown so the send button can't be hammered. ---
$now = time();
$last = $_SESSION['pending_signup']['last_sent_at'] ?? 0;
if ($now - $last < 20) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'cooldown', 'details' => ['otp_cooldown']]);
    exit;
}

$errors = [];

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'invalid_email';

$password = (string) ($_POST['password'] ?? '');
$confirm  = (string) ($_POST['password_confirm'] ?? '');
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    $errors[] = 'weak_password';
}
if ($password !== $confirm) $errors[] = 'password_mismatch';

if (empty($_POST['terms'])) $errors[] = 'terms_required';

$phone = trim($_POST['phone'] ?? '');
if (!preg_match('/^[+\d][\d\s\-()]{6,19}$/', $phone)) $errors[] = 'invalid_phone';

// Email must be free across the whole `accounts` table (shared by
// travellers, agents and admins alike).
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $chk = mysqli_prepare($conn, "SELECT account_id FROM accounts WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($chk, 's', $email);
    mysqli_stmt_execute($chk);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk))) $errors[] = 'email_taken';
    mysqli_stmt_close($chk);
}

$fields = [];
$files  = [];
$uploadToken = bin2hex(random_bytes(8));
$pendingDir  = __DIR__ . '/../../uploads/agents/pending/' . $uploadToken;

if ($kind === 'user') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $city      = trim($_POST['city'] ?? '');

    if ($firstName === '') $errors[] = 'first_name_required';
    if ($lastName === '')  $errors[] = 'last_name_required';

    if ($errors) fail($errors);

    $fields = compact('firstName', 'lastName', 'phone', 'address', 'city', 'email');
    $displayName = $firstName;

} else { // agent
    $fullName = trim($_POST['full_name'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $city     = trim($_POST['city'] ?? '');

    if ($fullName === '') $errors[] = 'full_name_required';
    if ($address === '')  $errors[] = 'address_required';
    if ($city === '')     $errors[] = 'city_required';

    // --- File uploads: Aadhaar + PAN required, business document optional ---
    $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $maxBytes    = 5 * 1024 * 1024; // 5 MB

    function validate_upload(string $field, bool $required, array $allowedMime, int $maxBytes, array &$errors, string $errorCode): ?array {
        $file = $_FILES[$field] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            if ($required) $errors[] = $errorCode;
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = $errorCode; return null; }
        if ($file['size'] <= 0 || $file['size'] > $maxBytes) { $errors[] = $errorCode; return null; }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset($allowedMime[$mime])) { $errors[] = $errorCode; return null; }

        return ['tmp_name' => $file['tmp_name'], 'ext' => $allowedMime[$mime]];
    }

    $aadhaar  = validate_upload('aadhaar_file', true, $allowedMime, $maxBytes, $errors, 'aadhaar_file_required');
    $pan      = validate_upload('pan_file', true, $allowedMime, $maxBytes, $errors, 'pan_file_required');
    $business = validate_upload('business_document_file', false, $allowedMime, $maxBytes, $errors, 'business_document_invalid');

    if ($errors) fail($errors);

    // Only now (validation passed) do we touch the filesystem.
    if (!is_dir($pendingDir) && !mkdir($pendingDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'upload_failed']);
        exit;
    }
    foreach (['aadhaar_file' => $aadhaar, 'pan_file' => $pan, 'business_document_file' => $business] as $key => $info) {
        if (!$info) continue;
        $dest = $pendingDir . '/' . $key . '.' . $info['ext'];
        if (!move_uploaded_file($info['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'upload_failed']);
            exit;
        }
        $files[$key] = $key . '.' . $info['ext']; // stored relative to the pending/final folder
    }

    $fields = compact('fullName', 'phone', 'address', 'city', 'email');
    $displayName = $fullName;
}

// --- Stage everything and send the code ---
$otp = send_otp_email($email, $displayName);

$_SESSION['pending_signup'] = [
    'kind'         => $kind,
    'email'        => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'fields'       => $fields,
    'upload_token' => $uploadToken,
    'files'        => $files,
    'otp_hash'     => hash('sha256', $otp['code']),
    'otp_expires'  => $now + 600, // 10 minutes
    'otp_attempts' => 0,
    'last_sent_at' => $now,
];

echo json_encode(['success' => true, 'email' => $email, 'mail_sent' => $otp['sent']]);

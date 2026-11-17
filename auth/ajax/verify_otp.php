<?php
/**
 * auth/ajax/verify_otp.php
 * ------------------------------------------------------------
 * Step 2 of signup. Checks the submitted code against the hash
 * staged by send_otp.php. Only on a correct, unexpired code does
 * this actually write to the database — one `accounts` row plus
 * one `users` or `agents` row, in a transaction. Agent document
 * uploads are moved from uploads/agents/pending/{token}/ to their
 * permanent uploads/agents/{account_id}/ home at the same time.
 *
 * Response on success: { success:true, redirect:"..." } and the
 * visitor is logged in immediately (see the $_SESSION writes below
 * — same keys auth/login.php and admin/includes/auth.php expect).
 * ------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';

$pending = $_SESSION['pending_signup'] ?? null;
if (!$pending) {
    http_response_code(410);
    echo json_encode(['success' => false, 'error' => 'expired', 'details' => ['otp_session_expired']]);
    exit;
}

if (time() > $pending['otp_expires']) {
    unset($_SESSION['pending_signup']);
    http_response_code(410);
    echo json_encode(['success' => false, 'error' => 'expired', 'details' => ['otp_expired']]);
    exit;
}

if ($pending['otp_attempts'] >= 6) {
    unset($_SESSION['pending_signup']);
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'too_many_attempts', 'details' => ['otp_too_many_attempts']]);
    exit;
}

$code = preg_replace('/\D/', '', trim($_POST['otp'] ?? ''));
if (strlen($code) !== 6 || !hash_equals($pending['otp_hash'], hash('sha256', $code))) {
    $_SESSION['pending_signup']['otp_attempts'] = $pending['otp_attempts'] + 1;
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'invalid_code', 'details' => ['otp_invalid']]);
    exit;
}

// --- Code is correct: create the account now, not a moment before. ---
$kind   = $pending['kind'];
$email  = $pending['email'];
$hash   = $pending['password_hash'];
$fields = $pending['fields'];

$roleRes = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name = '" . ($kind === 'agent' ? 'Agent' : 'User') . "' LIMIT 1");
$roleId  = (int) (mysqli_fetch_assoc($roleRes)['role_id'] ?? ($kind === 'agent' ? 2 : 1));

mysqli_begin_transaction($conn);
try {
    $stmt1 = mysqli_prepare($conn, "INSERT INTO accounts (role_id, email, password_hash, status) VALUES (?, ?, ?, 'Active')");
    mysqli_stmt_bind_param($stmt1, 'iss', $roleId, $email, $hash);
    mysqli_stmt_execute($stmt1);
    $accountId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt1);

    if ($kind === 'user') {
        $stmt2 = mysqli_prepare($conn, "INSERT INTO users (account_id, first_name, last_name, phone, address, city)
                                        VALUES (?, ?, ?, ?, ?, ?)");
        $address = $fields['address'] !== '' ? $fields['address'] : null;
        $city    = $fields['city'] !== '' ? $fields['city'] : null;
        mysqli_stmt_bind_param($stmt2, 'isssss', $accountId, $fields['firstName'], $fields['lastName'], $fields['phone'], $address, $city);
        mysqli_stmt_execute($stmt2);
        $profileId = mysqli_insert_id($conn);
        $displayName = trim($fields['firstName'] . ' ' . $fields['lastName']);
        mysqli_stmt_close($stmt2);
    } else {
        // Move the validated documents from the pending/{token} staging
        // folder to their permanent, account-scoped home.
        $finalDir = __DIR__ . '/../../uploads/agents/' . $accountId;
        if (!is_dir($finalDir) && !mkdir($finalDir, 0755, true)) throw new RuntimeException('mkdir failed');

        $pendingDir = __DIR__ . '/../../uploads/agents/pending/' . $pending['upload_token'];
        $finalPaths = [];
        foreach (['aadhaar_file', 'pan_file', 'business_document_file'] as $key) {
            if (empty($pending['files'][$key])) { $finalPaths[$key] = null; continue; }
            $from = $pendingDir . '/' . $pending['files'][$key];
            $to   = $finalDir . '/' . $pending['files'][$key];
            if (!@rename($from, $to)) throw new RuntimeException('file move failed: ' . $key);
            $finalPaths[$key] = 'uploads/agents/' . $accountId . '/' . $pending['files'][$key];
        }
        @rmdir($pendingDir);

        $stmt2 = mysqli_prepare($conn, "INSERT INTO agents
            (account_id, full_name, phone, address, city, aadhaar_file, pan_file, business_document_file, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        mysqli_stmt_bind_param(
            $stmt2, 'isssssss',
            $accountId, $fields['fullName'], $fields['phone'], $fields['address'], $fields['city'],
            $finalPaths['aadhaar_file'], $finalPaths['pan_file'], $finalPaths['business_document_file']
        );
        mysqli_stmt_execute($stmt2);
        $profileId = mysqli_insert_id($conn);
        $displayName = $fields['fullName'];
        mysqli_stmt_close($stmt2);
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log('verify_otp account creation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'account_creation_failed']);
    exit;
}

unset($_SESSION['pending_signup']);

if ($kind === 'user') {
    $_SESSION['user_id']    = (int) $profileId;
    $_SESSION['account_id'] = (int) $accountId;
    $_SESSION['user_name']  = $displayName;
    $redirect = '../index.php';
} else {
    $_SESSION['agent_id']   = (int) $profileId;
    $_SESSION['account_id'] = (int) $accountId;
    $_SESSION['agent_name'] = $displayName;
    $redirect = '../agent/index.php';
}

echo json_encode(['success' => true, 'redirect' => $redirect]);

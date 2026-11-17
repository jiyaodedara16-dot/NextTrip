<?php
/* User guard and shared helpers. Reuses the shared helper library (same database, same design). */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../admin/includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?redirect=' . urlencode('user/index.php'));
    exit;
}
$user_id = (int) $_SESSION['user_id'];
$me = row("SELECT u.*, a.email, a.account_id AS acc FROM users u JOIN accounts a ON a.account_id = u.account_id WHERE u.user_id = $user_id");
if (!$me) {
    unset($_SESSION['user_id']);
    header('Location: ../auth/login.php');
    exit;
}
$user_acc   = (int) $me['acc'];
$user_name  = trim($me['first_name'] . ' ' . $me['last_name']);
$user_email = $me['email'];

function user_ajax_guard() {
    if (empty($_SESSION['user_id'])) json_fail('Your session has expired. Please sign in again.', 401);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) json_fail('Security token mismatch. Refresh and try again.', 403);
    }
}

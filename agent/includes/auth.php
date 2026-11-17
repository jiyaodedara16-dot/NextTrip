<?php
/* Agent guard and shared agent helpers.
   Reuses the admin helper library (same database, same design system). */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../admin/includes/functions.php';

if (empty($_SESSION['agent_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}
$agent_id = (int) $_SESSION['agent_id'];
$current_agent = row("SELECT ag.*, a.email FROM agents ag JOIN accounts a ON a.account_id = ag.account_id
                      WHERE ag.agent_id = $agent_id AND ag.status = 'Approved'");
if (!$current_agent) {
    unset($_SESSION['agent_id'], $_SESSION['account_id']);
    header('Location: ../../auth/login.php');
    exit;
}
$agent_acc   = (int) $current_agent['account_id'];
$agent_name  = $current_agent['full_name'];
$agent_email = $current_agent['email'];
$agency_name = $current_agent['city'] ?: 'Travel partner';

/* AJAX guard for agent endpoints */
function agent_ajax_guard() {
    if (empty($_SESSION['agent_id'])) json_fail('Your session has expired. Please sign in again.', 401);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) json_fail('Security token mismatch. Refresh and try again.', 403);
    }
}
/* Form POST guard */
function csrf_ok() {
    return !empty($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}

/* Image upload: returns the stored relative path or null */
function store_image($tmp, $name, $size, $prefix) {
    $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true)) return null;
    if ((int) $size > 5 * 1024 * 1024 || !is_uploaded_file($tmp)) return null;
    $dir = __DIR__ . '/../../assets/images/uploads';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $file = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    return move_uploaded_file($tmp, "$dir/$file") ? "assets/images/uploads/$file" : null;
}
/* Upload for an indexed field such as hotel_img[3] */
function store_image_at($field, $i, $prefix) {
    if (empty($_FILES[$field]['name'][$i]) || ($_FILES[$field]['error'][$i] ?? 1) !== UPLOAD_ERR_OK) return null;
    return store_image($_FILES[$field]['tmp_name'][$i], $_FILES[$field]['name'][$i], $_FILES[$field]['size'][$i], $prefix);
}

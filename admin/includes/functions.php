<?php
/* ==========================================================================
   NextTrip Admin — shared helpers
   Plain procedural mysqli only (no PDO, no classes).
   ========================================================================== */
date_default_timezone_set('Asia/Kolkata');

function h($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* Returns the stored image path only if the file really exists on disk, otherwise null */
function existing_image($path) {
    if (!$path) return null;
    return is_file(__DIR__ . '/../../' . ltrim($path, '/')) ? $path : null;
}

/* ---------- Database (simple mysqli_query helpers) ---------- */
function q($sql) {
    global $conn;
    $res = mysqli_query($conn, $sql);
    if ($res === false) error_log('[admin] SQL error: ' . mysqli_error($conn) . ' :: ' . $sql);
    return $res;
}
function rows($sql) {
    $out = [];
    $res = q($sql);
    if ($res) while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
    return $out;
}
function row($sql) {
    $res = q($sql);
    return $res ? (mysqli_fetch_assoc($res) ?: null) : null;
}
function scalar($sql) {
    $r = row($sql);
    return $r ? reset($r) : 0;
}
function esc($value) {
    global $conn;
    return mysqli_real_escape_string($conn, (string) $value);
}
function last_id() {
    global $conn;
    return (int) mysqli_insert_id($conn);
}
function like_sql($column, $term) {
    $term = trim((string) $term);
    if ($term === '') return '';
    return " AND $column LIKE '%" . esc($term) . "%'";
}
function slugify($text) {
    $s = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $text), '-'));
    return $s !== '' ? $s : 'item';
}
function unique_slug($table, $base) {
    $slug = $base;
    $n = 2;
    while (scalar("SELECT COUNT(*) FROM `$table` WHERE slug = '" . esc($slug) . "'") > 0) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

/* ---------- Formatting ---------- */
function fmt_date($dt) {
    return $dt ? date('d M Y', strtotime($dt)) : '—';
}
function fmt_datetime($dt) {
    return $dt ? date('d M Y, h:i A', strtotime($dt)) : '—';
}
function time_ago($dt) {
    if (!$dt) return '—';
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return fmt_date($dt);
}
function money($amount) {
    return '₹' . number_format((float) $amount, 2);
}
function initials($name) {
    $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1));
    return $out !== '' ? $out : '?';
}
function badge($status) {
    $status = $status ?: 'Unknown';
    return '<span class="badge badge-' . h(strtolower($status)) . '">' . h($status) . '</span>';
}
/* Paths stored in the DB are relative to the project root; admin pages sit one level down. */
function asset_url($path, $fallback = 'assets/images/destinations/placeholder.jpg') {
    $p = $path ? $path : $fallback;
    if (preg_match('#^https?://#i', $p)) return $p;
    return '../' . ltrim($p, '/');
}

/* ---------- Pagination ---------- */
function paginate($total, $perPage, $current) {
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, (int) $current), $pages);
    return ['page' => $page, 'pages' => $pages, 'total' => (int) $total, 'per' => $perPage, 'offset' => ($page - 1) * $perPage];
}
function pager($p, $params = []) {
    if ($p['total'] === 0) return '';
    $from = $p['offset'] + 1;
    $to = min($p['offset'] + $p['per'], $p['total']);
    $link = function ($page) use ($params) {
        return '?' . http_build_query(array_merge($params, ['page' => $page]));
    };
    $html = '<div class="pager"><span class="pager-info">Showing ' . $from . '–' . $to . ' of ' . $p['total'] . '</span><div class="pager-links">';
    if ($p['page'] > 1) $html .= '<a class="pg" href="' . h($link($p['page'] - 1)) . '"><i class="fa-solid fa-chevron-left"></i></a>';
    for ($i = max(1, $p['page'] - 2); $i <= min($p['pages'], $p['page'] + 2); $i++) {
        $html .= '<a class="pg' . ($i === $p['page'] ? ' active' : '') . '" href="' . h($link($i)) . '">' . $i . '</a>';
    }
    if ($p['page'] < $p['pages']) $html .= '<a class="pg" href="' . h($link($p['page'] + 1)) . '"><i class="fa-solid fa-chevron-right"></i></a>';
    return $html . '</div></div>';
}

/* ---------- Security + JSON for AJAX endpoints ---------- */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function json_fail($message, $code = 400) {
    json_out(['success' => false, 'message' => $message], $code);
}
function ajax_guard() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) json_fail('Your session has expired. Please sign in again.', 401);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
            json_fail('Security token mismatch. Refresh the page and try again.', 403);
        }
    }
}
function require_post_id() {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_fail('Invalid record.');
    return $id;
}

/* ---------- Mail (real SMTP via includes/mailer.php + audit trail in mail_log) ---------- */
function send_mail_log($to, $subject, $body) {
    q("INSERT INTO mail_log (to_email, subject, body) VALUES ('" . esc($to) . "', '" . esc($subject) . "', '" . esc($body) . "')");
}
function notify_email($to, $name, $subject, $body) {
    require_once __DIR__ . '/../../includes/email-template.php';
    $paras = '';
    foreach (preg_split('/\n\s*\n/', trim($body)) as $p) {
        $paras .= '<p style="color:#33443b;font-size:14.5px;line-height:1.7;margin:0 0 12px;">' . ne($p) . '</p>';
    }
    $html = email_layout('NextTrip update', $subject,
        '<p style="color:#33443b;font-size:14.5px;margin:0 0 12px;">Hi ' . ne($name) . ',</p>' . $paras);
    $sent = send_mail($to, $name, $subject, $html, "Hi $name,\n\n$body\n\n— NextTrip Team");
    send_mail_log($to, $subject, "Hi $name,\n\n$body");
    return $sent;
}

/* ---------- Recursive delete for uploaded document folders ---------- */
function remove_dir($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $f;
        is_dir($p) ? remove_dir($p) : @unlink($p);
    }
    @rmdir($dir);
}

/* NULL-safe quoted SQL value */
function sql_val($value) {
    return ($value === null || $value === '') ? 'NULL' : "'" . esc($value) . "'";
}
/* Shared "status" text for the initials avatar colour */
function avatar_class($status) {
    $s = strtolower((string) $status);
    if ($s === 'pending') return 'amber';
    if ($s === 'rejected') return 'blue';
    return '';
}

/* Entry fee can be a plain number ("50"), free text ("₹150 (Indians) / ₹400 (Foreigners)") or empty */
function entry_label($fee) {
    $f = trim((string) $fee);
    if ($f === '' || $f === '0' || strcasecmp($f, 'Free') === 0) return 'Free';
    if (preg_match('/^\d/', $f)) return '₹' . $f;
    return $f;
}

/* In-app notification for an account (shown in the agent console bell and feed) */
function notify_account($accountId, $type, $title, $message, $link = null) {
    q("INSERT INTO notifications (account_id, type, title, message, link) VALUES ("
      . (int) $accountId . ", '" . esc($type) . "', " . sql_val($title) . ", " . sql_val($message) . ", " . sql_val($link) . ")");
}

/* Transaction helpers used by every save/delete that writes several tables */
function begin_tx() {
    global $conn;
    mysqli_begin_transaction($conn);
}
function commit_tx() {
    global $conn;
    mysqli_commit($conn);
}
function rollback_tx() {
    global $conn;
    mysqli_rollback($conn);
}
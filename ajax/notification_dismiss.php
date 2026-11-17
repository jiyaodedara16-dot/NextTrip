<?php
/* POST key: hides one notification for the signed-in agent or traveller.
   Keys: n:<notifications id>, b:<booking id> (agent requests), h:<status history id> (traveller updates). */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';

function nd_out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') nd_out(['success' => false, 'message' => 'Method not allowed.'], 405);

$acc = 0;
if (!empty($_SESSION['agent_id'])) {
    $res = mysqli_query($conn, 'SELECT account_id FROM agents WHERE agent_id = ' . (int) $_SESSION['agent_id']);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $acc = (int) ($row['account_id'] ?? 0);
} elseif (!empty($_SESSION['user_id'])) {
    $res = mysqli_query($conn, 'SELECT account_id FROM users WHERE user_id = ' . (int) $_SESSION['user_id']);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $acc = (int) ($row['account_id'] ?? 0);
}
if ($acc <= 0) nd_out(['success' => false, 'message' => 'Please sign in again.'], 401);

$key = trim($_POST['key'] ?? '');
if (!preg_match('/^[nbh]:\d+$/', $key)) nd_out(['success' => false, 'message' => 'This notification could not be found.'], 422);

$k = mysqli_real_escape_string($conn, $key);
if (!mysqli_query($conn, "INSERT IGNORE INTO dismissed_notifications (account_id, item_key) VALUES ($acc, '$k')")) {
    nd_out(['success' => false, 'message' => 'Could not remove this notification.', 'detail' => mysqli_error($conn)], 500);
}
nd_out(['success' => true, 'message' => 'Notification removed.']);
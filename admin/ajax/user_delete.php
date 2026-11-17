<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id = require_post_id();
$u = row("SELECT account_id FROM users WHERE user_id = $id");
if (!$u) json_fail('User not found.', 404);

/* users -> cascades bookings, reviews, favourites and payments; then remove the login account */
begin_tx();
$ok = q("DELETE FROM users WHERE user_id = $id") && q("DELETE FROM accounts WHERE account_id = " . (int) $u['account_id']);
if (!$ok) { rollback_tx(); json_fail('Could not delete this user. Please try again.', 500); }
commit_tx();
json_out(['success' => true, 'message' => 'User deleted successfully.']);

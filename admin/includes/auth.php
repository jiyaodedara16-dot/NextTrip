<?php
/* Guard for every admin page: must be signed in with an Admin-role account. */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
$admin_email = $_SESSION['admin_email'] ?? '';
$admin_name  = $_SESSION['admin_name'] ?? 'Administrator';

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Location: ' . (empty($_SESSION['admin_id']) ? 'login.php' : 'dashboard.php'));
exit;

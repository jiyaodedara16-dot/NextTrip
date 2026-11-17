<?php
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['user_id'], $_SESSION['account_id'], $_SESSION['user_name']);
header('Location: ../auth/login.php');
exit;

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['agent_id'], $_SESSION['account_id']);
header('Location: ../auth/login.php');
exit;

<?php
/**
 * auth/register.php
 * ------------------------------------------------------------
 * Sign-up now lives inside auth/login.php as the sliding card's
 * other panel. This just preserves old links/bookmarks to
 * auth/register.php by redirecting straight to that panel.
 * ------------------------------------------------------------
 */
$qs = $_GET;
$qs['mode'] = 'signup';
header('Location: login.php?' . http_build_query($qs));
exit;

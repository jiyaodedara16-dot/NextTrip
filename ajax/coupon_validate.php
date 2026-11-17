<?php
/* POST code, package_id, adult_count, child_count: checks a coupon for the logged-in traveller and returns totals. */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/coupon-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bk_json(['error' => 'method_not_allowed'], 405);
if (empty($_SESSION['user_id'])) bk_json(['ok' => false, 'message' => 'Please log in to use a coupon.'], 401);

$uid      = (int) $_SESSION['user_id'];
$pkgId    = (int) ($_POST['package_id'] ?? 0);
$adults   = max(1, (int) ($_POST['adult_count'] ?? 1));
$children = max(0, (int) ($_POST['child_count'] ?? 0));

$pkg = bk_row("SELECT price_per_person FROM packages WHERE package_id = $pkgId AND status = 'Active'");
if (!$pkg) bk_json(['ok' => false, 'message' => 'This package is not available.'], 404);

$base = booking_base($pkg['price_per_person'], $adults, $children);
$ev = coupon_evaluate($_POST['code'] ?? '', $uid, $pkgId, $base);
if (!$ev['ok']) bk_json(['ok' => false, 'message' => $ev['message']], 422);

$totals = booking_totals($pkg['price_per_person'], $adults, $children, $ev['discount']);
bk_json(['ok' => true, 'code' => $ev['code'], 'percent' => $ev['percent'], 'capped' => $ev['capped'],
         'message' => $ev['message'], 'totals' => $totals]);

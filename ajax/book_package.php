<?php
/* POST: creates a booking request (Pending). Coupon re-checked here; discount deducted from the package price;
   one coupon use reserved. Traveller names are optional: missing names are filled from the contact name. */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/coupon-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bk_json(['error' => 'method_not_allowed'], 405);
if (empty($_SESSION['user_id'])) bk_json(['error' => 'not_logged_in', 'message' => 'Please log in to book a package.'], 401);

$uid        = (int) $_SESSION['user_id'];
$pkgId      = (int) ($_POST['package_id'] ?? 0);
$travel     = trim($_POST['travel_date'] ?? '');
$adults     = (int) ($_POST['adult_count'] ?? 0);
$children   = (int) ($_POST['child_count'] ?? 0);
$cName      = trim($_POST['contact_name'] ?? '');
$cPhone     = trim($_POST['contact_phone'] ?? '');
$cEmail     = trim($_POST['contact_email'] ?? '');
$special    = trim($_POST['special_requests'] ?? '');
$couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
$terms      = ($_POST['terms'] ?? '') === '1';

$errors = [];
if ($pkgId <= 0) $errors[] = 'The package is not valid.';
if ($adults < 1) $errors[] = 'At least one adult is required.';
if ($children < 0) $errors[] = 'Children cannot be negative.';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $travel) || $travel <= date('Y-m-d')) $errors[] = 'Choose a travel date after today.';
if ($cName === '') $errors[] = 'Your full name is required.';
if (!preg_match('/^[0-9 ]{10,14}$/', $cPhone)) $errors[] = 'Enter a valid mobile number.';
if (!filter_var($cEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
if (!$terms) $errors[] = 'Please accept the booking terms.';
if (mb_strlen($special) > 500) $errors[] = 'Notes must be under 500 characters.';
if ($errors) bk_json(['error' => 'validation_failed', 'details' => $errors, 'message' => implode(' ', $errors)], 422);

$pkg = bk_row("SELECT p.package_id, p.package_name, p.agent_id, p.price_per_person, p.max_adults, p.max_children,
                      ag.account_id AS agent_acc, ag.full_name AS agent_name
               FROM packages p JOIN agents ag ON ag.agent_id = p.agent_id
               WHERE p.package_id = $pkgId AND p.status = 'Active'");
if (!$pkg) bk_json(['error' => 'package_not_found', 'message' => 'This package is no longer available.'], 404);
if ($pkg['max_adults'] !== null && $adults > (int) $pkg['max_adults']) bk_json(['error' => 'validation_failed', 'message' => 'Too many adults for this package.'], 422);
if ($pkg['max_children'] !== null && $children > (int) $pkg['max_children']) bk_json(['error' => 'validation_failed', 'message' => 'Too many children for this package.'], 422);

$user = bk_row("SELECT user_id, account_id FROM users WHERE user_id = $uid");
if (!$user) bk_json(['error' => 'user_not_found', 'message' => 'Your account could not be found.'], 404);

/* Coupon: re-checked on the server, never trusted from the browser */
$discount = 0.0;
$couponId = null;
$ev = null;
if ($couponCode !== '') {
    $base = booking_base($pkg['price_per_person'], $adults, $children);
    $ev = coupon_evaluate($couponCode, $uid, $pkgId, $base);
    if (!$ev['ok']) bk_json(['error' => 'coupon_invalid', 'message' => $ev['message']], 422);
    $discount = $ev['discount'];
    $couponId = $ev['coupon_id'];
}
$t = booking_totals($pkg['price_per_person'], $adults, $children, $discount);

/* Traveller names: the contact is the first traveller; other guests are labelled Adult 2, Child 1 and so on */
$guests = $adults + $children;
$names = [$cName];
for ($i = 2; $i <= $guests; $i++) {
    $names[] = $i <= $adults ? 'Adult ' . $i : 'Child ' . ($i - $adults);
}

bk_q("START TRANSACTION");
$ok = bk_q("INSERT INTO bookings (user_id, package_id, agent_id, travel_date, adult_count, child_count, package_price,
                                  subtotal, gst_amount, discount_amount, total_amount, booking_status, special_requests, coupon_id, coupon_code)
            VALUES ($uid, $pkgId, " . (int) $pkg['agent_id'] . ", " . bk_sql($travel) . ", $adults, $children, " . (float) $pkg['price_per_person'] . ", "
          . $t['subtotal'] . ", " . $t['gst'] . ", " . $t['discount'] . ", " . $t['total'] . ", 'Pending', " . bk_sql($special) . ", "
          . ($couponId ? (int) $couponId : 'NULL') . ", " . ($couponId ? bk_sql($ev['code']) : 'NULL') . ")");
$bookingId = bk_last_id();
if ($ok) {
    foreach ($names as $i => $name) {
        $ok = $ok && bk_q("INSERT INTO booking_members (booking_id, full_name, phone, email)
                           VALUES ($bookingId, " . bk_sql($name) . ", " . ($i === 0 ? bk_sql($cPhone) : 'NULL') . ", " . ($i === 0 ? bk_sql($cEmail) : 'NULL') . ")");
    }
}
if ($ok && $couponId) $ok = coupon_reserve($couponId, $uid, $bookingId, $discount);
if ($ok) {
    $ok = bk_q("INSERT INTO booking_status_history (booking_id, status, changed_by, changed_at, message)
                VALUES ($bookingId, 'Pending', " . (int) $user['account_id'] . ", NOW(), 'Booking request submitted')");
}
if (!$ok) {
    bk_q("ROLLBACK");
    bk_json(['error' => 'db_error', 'message' => 'Could not save your request. Please try again.'], 500);
}
bk_q("COMMIT");

$when = date('d M Y', strtotime($travel));
$guestText = $adults . ' adult' . ($adults === 1 ? '' : 's') . ($children ? ', ' . $children . ' child' . ($children === 1 ? '' : 'ren') : '');
bk_notify_account($pkg['agent_acc'], 'booking_request', 'New booking request',
    "$cName requested \"{$pkg['package_name']}\" for $when ($guestText)" . ($couponId ? " with coupon {$ev['code']}" : '') . '. Accept or reject it from Booking Requests.', 'bookings.php');
require_once __DIR__ . '/../includes/email-template.php';
$bd = email_booking_data($bookingId);
email_send_booking($cEmail, $cName, 'Booking request received — #' . $bookingId, 'Booking request',
    'We have received your request',
    'Thank you for choosing NextTrip. Your request is with ' . $pkg['agent_name'] . ' for review. Once it is accepted, you will get a secure payment link by email.',
    $bd, [EMAIL_SITE_URL . '/user/bookings.php', 'View my booking']);
if (!empty($bd['agent_email'])) {
    email_send_booking($bd['agent_email'], $pkg['agent_name'], 'New booking request — #' . $bookingId, 'New booking request',
        'A traveller has requested your package',
        $cName . ' requested this package. Please accept or reject it from your Booking Requests page.',
        $bd, [EMAIL_SITE_URL . '/agent/bookings.php', 'Review request']);
}

bk_json(['success' => true, 'booking' => ['booking_id' => $bookingId, 'total_amount' => $t['total'], 'discount' => $discount, 'status' => 'Pending']]);

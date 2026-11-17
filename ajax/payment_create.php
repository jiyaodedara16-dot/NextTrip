<?php
/* POST booking_id: starts a Razorpay order for an ACCEPTED booking that belongs to the logged-in traveller. */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/booking-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bk_json(['error' => 'method_not_allowed'], 405);
if (empty($_SESSION['user_id'])) bk_json(['error' => 'not_logged_in'], 401);
$uid = (int) $_SESSION['user_id'];
$bid = (int) ($_POST['booking_id'] ?? 0);

$b = bk_row("SELECT b.booking_id, b.total_amount, b.booking_status, p.package_name,
                    u.first_name, u.last_name, u.phone, a.email
             FROM bookings b
             JOIN packages p ON p.package_id = b.package_id
             JOIN users u ON u.user_id = b.user_id
             JOIN accounts a ON a.account_id = u.account_id
             WHERE b.booking_id = $bid AND b.user_id = $uid");
if (!$b) bk_json(['error' => 'not_found', 'message' => 'Booking not found.'], 404);
if ($b['booking_status'] !== 'Accepted') bk_json(['error' => 'not_payable', 'message' => 'This booking is not waiting for payment.'], 409);

$paise = (int) round((float) $b['total_amount'] * 100);
$order = bk_razorpay_order($paise, 'bk_' . $bid . '_' . time(), ['booking_id' => (string) $bid]);
if (isset($order['error']) || empty($order['id'])) bk_json(['error' => 'payment_gateway', 'message' => 'Payment could not be started. Please try again.'], 502);

bk_q("INSERT INTO payments (booking_id, user_id, amount, payment_method, payment_status, razorpay_order_id)
      VALUES ($bid, $uid, " . (float) $b['total_amount'] . ", 'Razorpay', 'Pending', " . bk_sql($order['id']) . ")");

// Show every payment method enabled on the Razorpay account. The traveller's
// choice is only used as a shortcut for card / net banking, so Razorpay never
// gets a method list that the account cannot satisfy ("No appropriate payment
// method found").
$allowed = ['upi' => true, 'card' => true, 'netbanking' => true, 'wallet' => true, 'emi' => false, 'paylater' => false];
$prefillFor = ['card' => 'card', 'netbanking' => 'netbanking'];
$choice = $_POST['method'] ?? 'all';
$checkout = ['method' => $allowed, 'prefill_method' => $prefillFor[$choice] ?? null, 'display' => new stdClass()];

bk_json([
    'key'         => RAZORPAY_KEY_ID,
    'order_id'    => $order['id'],
    'amount'      => $paise,
    'currency'    => RAZORPAY_CURRENCY,
    'name'        => 'NextTrip',
    'description' => $b['package_name'] . ' · Booking #' . $bid,
    'prefill'     => ['name' => trim($b['first_name'] . ' ' . $b['last_name']), 'email' => $b['email'], 'contact' => $b['phone']],
    'booking_id'  => $bid,
    'checkout'    => $checkout,
]);
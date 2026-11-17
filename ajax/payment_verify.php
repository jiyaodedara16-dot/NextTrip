<?php
/* POST after Razorpay checkout succeeds. Verifies the signature on the server, confirms the booking,
   notifies the agent in-app, emails the traveller and the agent, and attaches the PDF receipt. */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/booking-lib.php';
require_once __DIR__ . '/../includes/email-template.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bk_json(['error' => 'method_not_allowed'], 405);
if (empty($_SESSION['user_id'])) bk_json(['error' => 'not_logged_in'], 401);

$uid     = (int) $_SESSION['user_id'];
$bid     = (int) ($_POST['booking_id'] ?? 0);
$orderId = trim($_POST['razorpay_order_id'] ?? '');
$payId   = trim($_POST['razorpay_payment_id'] ?? '');
$sig     = trim($_POST['razorpay_signature'] ?? '');

if ($orderId === '' || $payId === '' || $sig === '') {
    bk_json(['error' => 'missing_fields', 'message' => 'Payment details are incomplete.'], 422);
}

if (!bk_verify_signature($orderId, $payId, $sig)) {
    bk_q("UPDATE payments SET payment_status = 'Failed' WHERE razorpay_order_id = " . bk_sql($orderId));
    bk_json(['error' => 'signature_mismatch', 'message' => 'Payment could not be verified. Please try again.'], 400);
}

$pay = bk_row("SELECT p.payment_id, p.payment_status FROM payments p JOIN bookings b ON b.booking_id = p.booking_id
               WHERE p.razorpay_order_id = " . bk_sql($orderId) . " AND p.booking_id = $bid AND b.user_id = $uid");
if (!$pay) bk_json(['error' => 'not_found', 'message' => 'Payment record not found.'], 404);
if ($pay['payment_status'] === 'Success') bk_json(['success' => true, 'message' => 'Payment already recorded.', 'booking_id' => $bid]);

$b = bk_row("SELECT b.booking_id, b.total_amount, b.booking_status, b.travel_date, b.adult_count, b.child_count,
                    p.package_name, ag.account_id AS agent_acc, ag.full_name AS agent_name,
                    u.account_id AS user_acc, u.first_name, u.last_name, a.email AS user_email
             FROM bookings b
             JOIN packages p ON p.package_id = b.package_id
             JOIN agents ag ON ag.agent_id = b.agent_id
             JOIN users u ON u.user_id = b.user_id
             JOIN accounts a ON a.account_id = u.account_id
             WHERE b.booking_id = $bid");
if (!$b || $b['booking_status'] !== 'Accepted') bk_json(['error' => 'not_payable', 'message' => 'This booking is not awaiting payment.'], 409);

bk_q("START TRANSACTION");
$ok = bk_q("UPDATE payments SET payment_status = 'Success', razorpay_payment_id = " . bk_sql($payId) . ",
            razorpay_signature = " . bk_sql($sig) . ", paid_at = NOW() WHERE payment_id = " . (int) $pay['payment_id'])
   && bk_q("UPDATE bookings SET booking_status = 'Confirmed' WHERE booking_id = $bid")
   && bk_q("INSERT INTO booking_status_history (booking_id, status, changed_by, changed_at, message)
            VALUES ($bid, 'Confirmed', " . (int) $b['user_acc'] . ", NOW(), 'Payment received: " . bk_esc($payId) . "')");
if (!$ok) {
    bk_q("ROLLBACK");
    bk_json(['error' => 'db_error', 'message' => 'Payment received but the booking could not be updated. Contact support with payment ID ' . $payId . '.'], 500);
}
bk_q("COMMIT");

/* ---- Notifications and emails (the booking is already confirmed at this point) ---- */
$traveller = trim($b['first_name'] . ' ' . $b['last_name']);
$no = 'NT-' . str_pad((string) $bid, 6, '0', STR_PAD_LEFT);

bk_notify_account($b['agent_acc'], 'booking_paid', 'Booking confirmed and paid',
    $traveller . ' paid ' . money_pdf_or_rs($b['total_amount']) . ' for "' . $b['package_name'] . '". The booking is confirmed.', 'bookings.php');

$bd = email_booking_data($bid);
$receipt = ['data' => email_receipt_pdf($bd), 'name' => 'NextTrip-Receipt-' . $no . '.pdf', 'type' => 'application/pdf'];

email_send_booking($b['user_email'], $traveller, 'Booking confirmed — ' . $no, 'Payment confirmed',
    'Your trip is confirmed',
    'Your payment of ' . email_money($b['total_amount']) . ' was received. Your receipt is attached to this email as a PDF.',
    $bd, [EMAIL_SITE_URL . '/user/bookings.php', 'View my booking'], [$receipt]);

if (!empty($bd['agent_email'])) {
    email_send_booking($bd['agent_email'], $b['agent_name'], 'Booking paid — ' . $no, 'Booking paid',
        'A traveller has paid and the booking is confirmed',
        $traveller . ' has paid for this booking. It is now confirmed.',
        $bd, [EMAIL_SITE_URL . '/agent/bookings.php', 'View booking']);
}

bk_json(['success' => true, 'message' => 'Payment successful. Your booking is confirmed.', 'booking_id' => $bid]);

/* Small helper used only in the in-app notification text above */
function money_pdf_or_rs($n) { return 'Rs. ' . number_format((float) $n, 2); }
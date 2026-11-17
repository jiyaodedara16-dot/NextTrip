<?php
/* Coupon rules and pricing. Every rule is enforced here on the server; the browser only displays results. */
require_once __DIR__ . '/booking-lib.php';

  // system maximum discount per booking (₹)
const COUPON_MAX_PERCENT  = 50;        // discount must be LESS than this
const COUPON_GST_RATE     = 0.05;

/* Base fare before any coupon: adults at full price, children at 50%. */
function booking_base($price, $adults, $children) {
    return round((float) $price * $adults + (float) $price * 0.5 * $children, 2);
}

/* Totals after the discount is deducted from the package price, then GST on the discounted amount. */
function booking_totals($price, $adults, $children, $discount) {
    $base = booking_base($price, $adults, $children);
    $discount = min((float) $discount, $base);
    $subtotal = round($base - $discount, 2);
    $gst = round($subtotal * COUPON_GST_RATE, 2);
    return ['base' => $base, 'discount' => round($discount, 2), 'subtotal' => $subtotal, 'gst' => $gst, 'total' => round($subtotal + $gst, 2)];
}

/* Checks every rule for a code and returns the discount, or the reason it cannot be used. */
function coupon_evaluate($code, $uid, $pkgId, $base) {
    $code = strtoupper(trim((string) $code));
    if ($code === '') return ['ok' => false, 'message' => 'Enter a coupon code.'];

    $c = bk_row("SELECT * FROM coupons WHERE code = '" . bk_esc($code) . "'");
    if (!$c) return ['ok' => false, 'message' => 'This coupon code does not exist.'];
    if ($c['approval_status'] === 'Pending')  return ['ok' => false, 'message' => 'This coupon is waiting for admin approval.'];
    if ($c['approval_status'] === 'Rejected') return ['ok' => false, 'message' => 'This coupon is not available.'];
    if ((int) $c['package_id'] !== (int) $pkgId) return ['ok' => false, 'message' => 'This coupon can only be used on its own package.'];

    $today = date('Y-m-d');
    if ($today < $c['start_date']) return ['ok' => false, 'message' => 'This coupon starts on ' . date('d M Y', strtotime($c['start_date'])) . '.'];
    if ($today > $c['expiry_date']) return ['ok' => false, 'message' => 'This coupon has expired.'];
    if ((int) $c['usage_count'] >= (int) $c['usage_limit']) return ['ok' => false, 'message' => 'This coupon has been fully used.'];

    $eligible = bk_row("SELECT 1 AS x FROM coupon_users WHERE coupon_id = " . (int) $c['coupon_id'] . " AND user_id = " . (int) $uid);
    if (!$eligible) return ['ok' => false, 'message' => 'This coupon is not assigned to your account.'];

    if ((float) $base < (float) $c['min_amount']) {
        return ['ok' => false, 'message' => 'This coupon needs a booking of at least ' . bk_money($c['min_amount']) . '.'];
    }

    $percent  = min((float) $c['discount_percent'], COUPON_MAX_PERCENT - 0.01);
    $discount = round((float) $base * $percent / 100, 2);   // no maximum amount

    return [
        'ok' => true, 'coupon_id' => (int) $c['coupon_id'], 'code' => $c['code'], 'percent' => $percent,
        'discount' => $discount, 'capped' => false,
        'message' => 'Coupon applied. You save ' . bk_money($discount) . '.',
    ];
}
/* Reserves one use of the coupon for a booking. Fails if the limit was reached in the meantime. */
function coupon_reserve($couponId, $uid, $bookingId, $discount) {
    global $conn;
    $ok = bk_q("UPDATE coupons SET usage_count = usage_count + 1 WHERE coupon_id = " . (int) $couponId . " AND usage_count < usage_limit");
    if (!$ok || mysqli_affected_rows($conn) !== 1) return false;
    return bk_q("INSERT INTO coupon_usage (coupon_id, user_id, booking_id, discount_amount)
                 VALUES (" . (int) $couponId . ", " . (int) $uid . ", " . (int) $bookingId . ", " . (float) $discount . ")");
}

/* Gives back a use when a booking is rejected, so the coupon is not lost. */
function coupon_release($bookingId) {
    $u = bk_row("SELECT coupon_id FROM coupon_usage WHERE booking_id = " . (int) $bookingId);
    if (!$u) return;
    bk_q("DELETE FROM coupon_usage WHERE booking_id = " . (int) $bookingId);
    bk_q("UPDATE coupons SET usage_count = GREATEST(usage_count - 1, 0) WHERE coupon_id = " . (int) $u['coupon_id']);
}

/* Live status shown in agent and admin lists */
function coupon_state($c) {
    if ($c['approval_status'] === 'Pending')  return 'Pending approval';
    if ($c['approval_status'] === 'Rejected') return 'Rejected';
    $today = date('Y-m-d');
    if ($today < $c['start_date']) return 'Scheduled';
    if ($today > $c['expiry_date']) return 'Expired';
    if ((int) $c['usage_count'] >= (int) $c['usage_limit']) return 'Used up';
    return 'Active';
}

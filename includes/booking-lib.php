<?php
/* Shared helpers for the booking and payment flow (plain mysqli, procedural). */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/razorpay.php';

function bk_esc($v) { global $conn; return mysqli_real_escape_string($conn, (string) $v); }
function bk_sql($v) { return ($v === null || $v === '') ? 'NULL' : "'" . bk_esc($v) . "'"; }
function bk_q($sql) {
    global $conn;
    $ok = mysqli_query($conn, $sql);
    if (!$ok) error_log('[booking] ' . mysqli_error($conn) . ' :: ' . $sql);
    return $ok;
}
function bk_row($sql) { global $conn; $r = mysqli_query($conn, $sql); return $r ? (mysqli_fetch_assoc($r) ?: null) : null; }
function bk_rows($sql) {
    global $conn;
    $out = [];
    $r = mysqli_query($conn, $sql);
    if ($r) while ($x = mysqli_fetch_assoc($r)) $out[] = $x;
    return $out;
}
function bk_last_id() { global $conn; return (int) mysqli_insert_id($conn); }
function bk_money($n) { return '₹' . number_format((float) $n, 2); }
function bk_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function bk_notify_account($accountId, $type, $title, $message, $link = null) {
    bk_q("INSERT INTO notifications (account_id, type, title, message, link) VALUES ("
        . (int) $accountId . ", " . bk_sql($type) . ", " . bk_sql($title) . ", " . bk_sql($message) . ", " . bk_sql($link) . ")");
}
function bk_mail($to, $name, $subject, $body) {
    require_once __DIR__ . '/mailer.php';
    $text = "Hi $name,\n\n$body\n\n— NextTrip Team";
    $sent = send_mail($to, $name, $subject, nl2br(htmlspecialchars($text)), $text);
    bk_q("INSERT INTO mail_log (to_email, subject, body) VALUES (" . bk_sql($to) . ", " . bk_sql($subject) . ", " . bk_sql($text) . ")");
    return $sent;
}

/* Creates a Razorpay order (amount in paise). Returns the order array or ['error' => ...]. */
function bk_razorpay_order($paise, $receipt, $notes = []) {
    $payload = json_encode(['amount' => (int) $paise, 'currency' => RAZORPAY_CURRENCY, 'receipt' => $receipt, 'notes' => $notes]);
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) { error_log('[razorpay] ' . $err); return ['error' => 'network']; }
    $data = json_decode($res, true);
    if ($code >= 300) { error_log('[razorpay] ' . $res); return ['error' => $data['error']['description'] ?? 'razorpay_error']; }
    return $data ?: ['error' => 'bad_response'];
}

/* Razorpay checkout signature: HMAC-SHA256 of "order_id|payment_id" with the key secret. */
function bk_verify_signature($orderId, $paymentId, $signature) {
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
    return hash_equals($expected, (string) $signature);
}
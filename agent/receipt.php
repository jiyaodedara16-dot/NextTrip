<?php
/* Agent downloads the PDF receipt for one of their own confirmed bookings. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/email-template.php';

$bid = (int) ($_GET['booking_id'] ?? 0);
$b = row("SELECT booking_status FROM bookings WHERE booking_id = $bid AND agent_id = $agent_id");
if (!$b || $b['booking_status'] !== 'Confirmed') {
    header('Location: bookings.php?msg=' . urlencode('A receipt is available only for confirmed bookings.') . '&type=error');
    exit;
}

$d = email_booking_data($bid);
$no = 'NT-' . str_pad((string) $bid, 6, '0', STR_PAD_LEFT);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="NextTrip-Receipt-' . $no . '.pdf"');
echo email_receipt_pdf($d);
exit;
<?php
/* Saves reviews for one confirmed, ended booking. Always returns JSON. */
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

function rv_json($data, $code = 200) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data);
    exit;
}

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error while saving your review.', 'detail' => $e['message']]);
    }
});

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/booking-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') rv_json(['success' => false, 'message' => 'Method not allowed.'], 405);
if (empty($_SESSION['user_id'])) rv_json(['success' => false, 'message' => 'Please log in to write a review.'], 401);

$uid = (int) $_SESSION['user_id'];
$bid = (int) ($_POST['booking_id'] ?? 0);
$crowdOptions = ['Very Low', 'Low', 'Medium', 'High', 'Very High'];

$b = bk_row("SELECT b.booking_id, b.booking_status, b.travel_date, b.package_id, p.duration_days
             FROM bookings b JOIN packages p ON p.package_id = b.package_id
             WHERE b.booking_id = $bid AND b.user_id = $uid");
if (!$b) rv_json(['success' => false, 'message' => 'Booking not found.'], 404);
if ($b['booking_status'] !== 'Confirmed') rv_json(['success' => false, 'message' => 'Reviews open only for confirmed trips.'], 409);
if (strtotime($b['travel_date'] . ' +' . (int) $b['duration_days'] . ' days') > time()) {
    rv_json(['success' => false, 'message' => 'Reviews open after your trip ends.'], 409);
}

$already = bk_row("SELECT (SELECT COUNT(*) FROM package_reviews WHERE booking_id = $bid) AS p,
                          (SELECT COUNT(*) FROM destination_reviews WHERE booking_id = $bid) AS d");
if (!$already) rv_json(['success' => false, 'message' => 'Could not check your review status. Please try again.'], 500);
if ((int) $already['p'] > 0 || (int) $already['d'] > 0) rv_json(['success' => false, 'message' => 'You have already reviewed this trip.'], 409);

$dests = bk_rows("SELECT pd.destination_id, d.destination_name FROM package_destinations pd
                  JOIN destinations d ON d.destination_id = pd.destination_id
                  WHERE pd.package_id = " . (int) $b['package_id'] . " ORDER BY pd.display_order");
$dInput = $_POST['dest'] ?? [];
$errors = [];
$rows = [];

foreach ($dests as $d) {
    $id = (int) $d['destination_id'];
    $r = $dInput[$id] ?? [];
    $rating = (int) ($r['rating'] ?? 0);
    $text = trim((string) ($r['text'] ?? ''));
    $visit = trim((string) ($r['visit_time'] ?? ''));
    $crowd = trim((string) ($r['crowd_level'] ?? ''));
    if ($rating < 1 || $rating > 5) $errors[] = $d['destination_name'] . ': choose a star rating.';
    if (mb_strlen($text) < 10) $errors[] = $d['destination_name'] . ': write at least 10 characters.';
    if (mb_strlen($text) > 1000) $errors[] = $d['destination_name'] . ': keep it under 1000 characters.';
    if ($visit !== '' && !preg_match('/^([01]\d|2[0-3]):00$/', $visit)) $errors[] = $d['destination_name'] . ': invalid visit time.';
    if ($crowd !== '' && !in_array($crowd, $crowdOptions, true)) $errors[] = $d['destination_name'] . ': invalid crowd level.';
    $rows[] = ['id' => $id, 'rating' => $rating, 'text' => $text,
               'visit' => $visit !== '' ? $visit . ':00' : null, 'crowd' => $crowd !== '' ? $crowd : null];
}

$pRating = (int) ($_POST['package_rating'] ?? 0);
$pTitle  = trim((string) ($_POST['package_title'] ?? ''));
$pText   = trim((string) ($_POST['package_text'] ?? ''));
if ($pRating < 1 || $pRating > 5) $errors[] = 'Choose a star rating for the whole package.';
if (mb_strlen($pText) < 10) $errors[] = 'Write at least 10 characters about the package.';
if (mb_strlen($pText) > 2000) $errors[] = 'The package review must be under 2000 characters.';
if (mb_strlen($pTitle) > 150) $errors[] = 'The package title must be under 150 characters.';
if ($errors) rv_json(['success' => false, 'message' => implode(' ', array_slice($errors, 0, 3)), 'details' => $errors], 422);

bk_q("START TRANSACTION");
$ok = true;
foreach ($rows as $r) {
    $ok = $ok && bk_q("INSERT INTO destination_reviews (destination_id, user_id, booking_id, rating, review_title, review_text, visit_time, crowd_level, status, review_date)
                       VALUES (" . $r['id'] . ", $uid, $bid, " . $r['rating'] . ", NULL, " . bk_sql($r['text']) . ", "
                       . bk_sql($r['visit']) . ", " . bk_sql($r['crowd']) . ", 'Approved', NOW())");
}
if ($ok) {
    $ok = bk_q("INSERT INTO package_reviews (package_id, user_id, booking_id, rating, review_title, review_text, review_date, status)
                VALUES (" . (int) $b['package_id'] . ", $uid, $bid, $pRating, " . bk_sql($pTitle) . ", " . bk_sql($pText) . ", NOW(), 'Approved')");
}
if (!$ok) {
    bk_q("ROLLBACK");
    global $conn;
    rv_json(['success' => false, 'message' => 'Could not save your review.', 'detail' => mysqli_error($conn)], 500);
}
bk_q("COMMIT");
rv_json(['success' => true, 'message' => 'Thank you! Your reviews are now published.']);
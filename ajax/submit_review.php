<?php
/**
 * ajax/submit_review.php
 * ------------------------------------------------------------
 * POST { destination_id, rating, review_text, visit_time, crowd_level }
 * Inserts a new row into `destination_reviews` (extended with
 * visit_time / crowd_level by destination_reviews_crowd_updates.sql).
 * Returns JSON with the new review (already joined to the user's
 * name) so the page can render it immediately without a reload.
 * ------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$userId     = (int) $_SESSION['user_id'];
$destId     = isset($_POST['destination_id']) ? (int) $_POST['destination_id'] : 0;
$rating     = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$reviewText = trim($_POST['review_text'] ?? '');
$visitTime  = trim($_POST['visit_time'] ?? '');
$crowdLevel = trim($_POST['crowd_level'] ?? '');

$allowedCrowd = ['Very Low', 'Low', 'Medium', 'High', 'Very High'];

// ---- validation --------------------------------------------------------
$errors = [];
if ($destId <= 0) $errors[] = 'invalid_destination';
if ($rating < 1 || $rating > 5) $errors[] = 'invalid_rating';
if ($reviewText === '') $errors[] = 'empty_review';
if (mb_strlen($reviewText) > 1000) $errors[] = 'review_too_long';
if ($visitTime !== '' && !preg_match('/^([01]\d|2[0-3]):00$/', $visitTime)) $errors[] = 'invalid_time';
if ($crowdLevel !== '' && !in_array($crowdLevel, $allowedCrowd, true)) $errors[] = 'invalid_crowd_level';

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => 'validation_failed', 'details' => $errors]);
    exit;
}

$check = mysqli_prepare($conn, "SELECT destination_id FROM destinations WHERE destination_id = ? AND status = 'Active' LIMIT 1");
mysqli_stmt_bind_param($check, 'i', $destId);
mysqli_stmt_execute($check);
$exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
mysqli_stmt_close($check);

if (!$exists) {
    http_response_code(404);
    echo json_encode(['error' => 'destination_not_found']);
    exit;
}

$visitTimeParam  = $visitTime !== '' ? $visitTime . ':00' : null;
$crowdLevelParam = $crowdLevel !== '' ? $crowdLevel : null;

$sql = "INSERT INTO destination_reviews
            (destination_id, user_id, rating, review_text, visit_time, crowd_level, status)
        VALUES (?, ?, ?, ?, ?, ?, 'Approved')";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'iiisss', $destId, $userId, $rating, $reviewText, $visitTimeParam, $crowdLevelParam);

if (!mysqli_stmt_execute($stmt)) {
    error_log('submit_review insert failed: ' . mysqli_stmt_error($stmt));
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
    mysqli_stmt_close($stmt);
    exit;
}
mysqli_stmt_close($stmt);

// Pull back the fresh row (with reviewer name) plus the destination's
// updated average rating / review count, so the page can update both
// the review list and the summary without a full reload.
$nameStmt = mysqli_prepare($conn, "SELECT CONCAT(first_name, ' ', last_name) AS reviewer_name FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($nameStmt, 'i', $userId);
mysqli_stmt_execute($nameStmt);
$nameRow = mysqli_fetch_assoc(mysqli_stmt_get_result($nameStmt));
mysqli_stmt_close($nameStmt);

$statsStmt = mysqli_prepare($conn, "SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS review_count
                                     FROM destination_reviews
                                     WHERE destination_id = ? AND status = 'Approved'");
mysqli_stmt_bind_param($statsStmt, 'i', $destId);
mysqli_stmt_execute($statsStmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($statsStmt));
mysqli_stmt_close($statsStmt);

echo json_encode([
    'success' => true,
    'review' => [
        'reviewer_name' => $nameRow['reviewer_name'] ?? 'You',
        'rating'        => $rating,
        'review_text'   => $reviewText,
        'visit_time'    => $visitTimeParam,
        'crowd_level'   => $crowdLevelParam,
        'created_at'    => date('c'),
    ],
    'stats' => [
        'avg_rating'   => (float) ($stats['avg_rating'] ?? 0),
        'review_count' => (int) ($stats['review_count'] ?? 0),
    ],
]);

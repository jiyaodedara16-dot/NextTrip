<?php
/**
 * ajax/favorite_toggle.php
 * ------------------------------------------------------------
 * POST { destination_id }
 * Toggles a row in the existing `favorites` table for the
 * logged-in traveller. Returns JSON: { liked: bool } or
 * { error: "..." } with a non-200 status.
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

$userId = (int) $_SESSION['user_id'];
$destId = isset($_POST['destination_id']) ? (int) $_POST['destination_id'] : 0;

if ($destId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_destination']);
    exit;
}

// Confirm the destination actually exists & is active before touching favorites.
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

$findStmt = mysqli_prepare($conn, "SELECT favorite_id FROM favorites WHERE user_id = ? AND destination_id = ? LIMIT 1");
mysqli_stmt_bind_param($findStmt, 'ii', $userId, $destId);
mysqli_stmt_execute($findStmt);
$existingFav = mysqli_fetch_assoc(mysqli_stmt_get_result($findStmt));
mysqli_stmt_close($findStmt);

if ($existingFav) {
    // Already favourited — remove it.
    $del = mysqli_prepare($conn, "DELETE FROM favorites WHERE favorite_id = ?");
    mysqli_stmt_bind_param($del, 'i', $existingFav['favorite_id']);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
    echo json_encode(['liked' => false]);
} else {
    // Not favourited yet — add it.
    $ins = mysqli_prepare($conn, "INSERT INTO favorites (user_id, destination_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($ins, 'ii', $userId, $destId);
    mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);
    echo json_encode(['liked' => true]);
}

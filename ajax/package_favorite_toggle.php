<?php
/**
 * ajax/package_favorite_toggle.php
 * ------------------------------------------------------------
 * POST { package_id }
 * Toggles a row in `package_favorites` for the logged-in traveller.
 * Mirrors ajax/favorite_toggle.php (destinations), but for packages.
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
$pkgId  = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;

if ($pkgId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_package']);
    exit;
}

$check = mysqli_prepare($conn, "SELECT package_id FROM packages WHERE package_id = ? AND status = 'Active' LIMIT 1");
mysqli_stmt_bind_param($check, 'i', $pkgId);
mysqli_stmt_execute($check);
$exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
mysqli_stmt_close($check);

if (!$exists) {
    http_response_code(404);
    echo json_encode(['error' => 'package_not_found']);
    exit;
}

$findStmt = mysqli_prepare($conn, "SELECT favorite_id FROM package_favorites WHERE user_id = ? AND package_id = ? LIMIT 1");
mysqli_stmt_bind_param($findStmt, 'ii', $userId, $pkgId);
mysqli_stmt_execute($findStmt);
$existingFav = mysqli_fetch_assoc(mysqli_stmt_get_result($findStmt));
mysqli_stmt_close($findStmt);

if ($existingFav) {
    $del = mysqli_prepare($conn, "DELETE FROM package_favorites WHERE favorite_id = ?");
    mysqli_stmt_bind_param($del, 'i', $existingFav['favorite_id']);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
    echo json_encode(['liked' => false]);
} else {
    $ins = mysqli_prepare($conn, "INSERT INTO package_favorites (user_id, package_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($ins, 'ii', $userId, $pkgId);
    mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);
    echo json_encode(['liked' => true]);
}

<?php
/* POST image_id: saves or removes a liked gallery photo for the logged-in traveller.
   Every query is checked, so a failure shows the real database error instead of a false success. */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';

function gal_fail($msg, $code = 400, $detail = null) {
    http_response_code($code);
    $out = ['message' => $msg];
    if ($detail) $out['detail'] = $detail;
    echo json_encode($out);
    exit;
}

if (empty($_SESSION['user_id'])) gal_fail('Please log in to save photos.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') gal_fail('Method not allowed.', 405);

$uid     = (int) $_SESSION['user_id'];
$imageId = (int) ($_POST['image_id'] ?? 0);
if ($imageId <= 0) gal_fail('This photo could not be identified.');

/* 1. The photo must exist and be active */
$res = mysqli_query($conn, "SELECT di.image_path, di.image_title, di.destination_id
                            FROM destination_images di
                            JOIN destinations d ON d.destination_id = di.destination_id
                            WHERE di.image_id = $imageId AND di.status = 'Active' AND d.status = 'Active' LIMIT 1");
if ($res === false) gal_fail('Database error while reading the photo.', 500, mysqli_error($conn));
$img = mysqli_fetch_assoc($res);
if (!$img) gal_fail('This photo is no longer available.', 404);

$path = mysqli_real_escape_string($conn, $img['image_path']);

/* 2. Find or create its gallery_photos record */
$res = mysqli_query($conn, "SELECT gallery_photo_id FROM gallery_photos WHERE image_path = '$path' LIMIT 1");
if ($res === false) gal_fail('Database error while checking the gallery.', 500, mysqli_error($conn));
$gp = mysqli_fetch_assoc($res);
if ($gp) {
    $gpId = (int) $gp['gallery_photo_id'];
} else {
    $caption = mysqli_real_escape_string($conn, (string) ($img['image_title'] ?? ''));
    $dest = (int) $img['destination_id'];
    $ok = mysqli_query($conn, "INSERT INTO gallery_photos (destination_id, image_path, caption, status)
                               VALUES ($dest, '$path', '$caption', 'Active')");
    if (!$ok) gal_fail('Could not add this photo to the gallery.', 500, mysqli_error($conn));
    $gpId = (int) mysqli_insert_id($conn);
    if ($gpId <= 0) gal_fail('Could not add this photo to the gallery (no ID created). Check that gallery_photos.gallery_photo_id is AUTO_INCREMENT.', 500);
}

/* 3. Toggle the favourite */
$res = mysqli_query($conn, "SELECT gallery_favorite_id FROM gallery_favorites WHERE user_id = $uid AND gallery_photo_id = $gpId LIMIT 1");
if ($res === false) gal_fail('Database error while checking your likes.', 500, mysqli_error($conn));
if (mysqli_fetch_assoc($res)) {
    if (!mysqli_query($conn, "DELETE FROM gallery_favorites WHERE user_id = $uid AND gallery_photo_id = $gpId"))
        gal_fail('Could not remove your like.', 500, mysqli_error($conn));
    echo json_encode(['liked' => false]);
} else {
    if (!mysqli_query($conn, "INSERT INTO gallery_favorites (user_id, gallery_photo_id) VALUES ($uid, $gpId)"))
        gal_fail('Could not save your like.', 500, mysqli_error($conn));
    echo json_encode(['liked' => true]);
}

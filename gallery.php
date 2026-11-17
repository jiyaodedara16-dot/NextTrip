<?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery</title>
    <!-- CSS FILES LINK -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/gallery.css">
     <!-- BOOTSTRAP ICONS LINK -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
</head>
<body>
    
    <!-- navbar -->
    <?php include 'includes/navbar.php'; ?>


    <?php
        // ---- All active categories, for the filter pills ----
        $categories = [];
        $catQuery = "SELECT category_id, category_name FROM categories WHERE status = 'Active' ORDER BY category_name ASC";
        $catResult = mysqli_query($conn, $catQuery);
        if ($catResult) {
            while ($row = mysqli_fetch_assoc($catResult)) {
                $categories[] = $row;
            }
        }

        // ---- All active gallery images, joined with their destination + categories ----
        // GROUP_CONCAT collects every category_id/category_name a destination has,
        // so multi-category destinations still filter correctly on every matching pill.
        $images = [];
        $imgQuery = "
            SELECT di.image_id, di.image_path, di.image_title, di.alt_text,
                d.destination_id, d.destination_name, d.city, d.state,
                GROUP_CONCAT(DISTINCT c.category_id) AS category_ids,
                GROUP_CONCAT(DISTINCT c.category_name SEPARATOR ', ') AS category_names
            FROM destination_images di
            JOIN destinations d ON d.destination_id = di.destination_id
            LEFT JOIN destination_categories dc ON dc.destination_id = d.destination_id
            LEFT JOIN categories c ON c.category_id = dc.category_id AND c.status = 'Active'
            WHERE di.status = 'Active' AND d.status = 'Active'
            GROUP BY di.image_id
            ORDER BY di.created_at DESC
        ";
        // Photos this traveller already liked, matched by image path, so each heart shows the right state
        $likedPaths = [];
        if (!empty($_SESSION['user_id'])) {
            $lk = mysqli_query($conn, "SELECT gp.image_path FROM gallery_favorites gf
                                       JOIN gallery_photos gp ON gp.gallery_photo_id = gf.gallery_photo_id
                                       WHERE gf.user_id = " . (int) $_SESSION['user_id']);
            if ($lk) while ($lr = mysqli_fetch_assoc($lk)) $likedPaths[$lr['image_path']] = true;
        }
        $imgResult = mysqli_query($conn, $imgQuery);
        if ($imgResult) {
            while ($row = mysqli_fetch_assoc($imgResult)) {
                $images[] = $row;
            }
        }
    ?>

    <main class="main-content">

    <!-- ===================== GALLERY HERO ===================== -->
    <section class="gallery-hero">
        <p class="gallery-hero__eyebrow">PHOTO GALLERY</p>
        <h1 class="gallery-hero-title">Incredible India Gallery</h1>
        <p class="gallery-hero-subtitle">Captured moments from across the land of diverse cultures, landscapes, and legends.</p>
    </section>

    <!-- ===================== CATEGORY FILTER PILLS ===================== -->
    <section class="gallery-filters-section">
        <div class="gallery-filters" id="galleryFilters">
        <span class="gallery-filter-indicator" id="filterIndicator"></span>

        <button type="button" class="gallery-filter-pill active" data-filter="all">All</button>

        <?php foreach ($categories as $category): ?>
            <button type="button" class="gallery-filter-pill" data-filter="<?= (int) $category['category_id'] ?>">
            <?= htmlspecialchars($category['category_name']) ?>
            </button>
        <?php endforeach; ?>
        </div>
    </section>

    <!-- ===================== IMAGE GRID ===================== -->
    <section class="gallery-grid-section">
        <div class="gallery-grid" id="galleryGrid">

        <?php foreach ($images as $img): ?>
            <?php
            $altText = !empty($img['alt_text']) ? $img['alt_text'] : $img['destination_name'];
            $locationText = $img['city'] . ($img['state'] ? ', ' . $img['state'] : '');
            $categoryIds = $img['category_ids'] ?? '';           // e.g. "1,6"
            $categoryLabel = $img['category_names'] ?? 'Uncategorised'; // e.g. "Nature, Adventure"
            ?>
            <div class="gallery-item"
                data-category="<?= htmlspecialchars($categoryIds) ?>"
                data-image-id="<?= (int) $img['image_id'] ?>"
                data-image="<?= htmlspecialchars($img['image_path']) ?>"
                data-title="<?= htmlspecialchars($img['destination_name']) ?>"
                data-location="<?= htmlspecialchars($locationText) ?>"
                data-category-label="<?= htmlspecialchars($categoryLabel) ?>">

            <img src="<?= htmlspecialchars($img['image_path']) ?>"
                alt="<?= htmlspecialchars($altText) ?>"
                loading="lazy">

            <?php $isLiked = isset($likedPaths[$img['image_path']]); ?>
            <button type="button" class="gallery-like-btn<?= $isLiked ? ' liked' : '' ?>" aria-label="<?= $isLiked ? 'Remove from favourites' : 'Add to favourites' ?>" aria-pressed="<?= $isLiked ? 'true' : 'false' ?>">
                <i class="bi <?= $isLiked ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
            </button>

            <div class="gallery-item-overlay">
                <span class="gallery-item-badge"><?= htmlspecialchars($categoryLabel) ?></span>
                <span class="gallery-item-location">&#128205; <?= htmlspecialchars($img['destination_name']) ?></span>
            </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($images)): ?>
            <p class="gallery-empty-message">No gallery images available yet.</p>
        <?php endif; ?>

        </div>
    </section>

    <!-- ===================== LIGHTBOX MODAL ===================== -->
    <div class="gallery-modal-backdrop" id="galleryModalBackdrop">
        <div class="gallery-modal" id="galleryModal">
        <button type="button" class="gallery-modal-close" id="galleryModalClose" aria-label="Close">&times;</button>
        <img src="" alt="" class="gallery-modal-image" id="galleryModalImage">
        <div class="gallery-modal-info">
            <span class="gallery-modal-badge" id="galleryModalBadge"></span>
            <span class="gallery-modal-location" id="galleryModalLocation">&#128205; <span id="galleryModalLocationText"></span></span>
        </div>
        <button type="button" class="gallery-modal-like" id="galleryModalLike" aria-label="Add to favourites" aria-pressed="false">
            <i class="bi bi-heart" aria-hidden="true"></i>
        </button>
        </div>
    </div>

    </main>

    <!-- footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/navbar.js"></script>
    <script src="assets/js/gallery.js"></script>
    <script src="assets/js/gallery-likes.js"></script>
</body>
</html>
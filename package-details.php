<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/config.php';
require_once 'package-details-db.php';

$id      = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$package = $id > 0 ? get_package($id) : null;

if (!$package) {
    http_response_code(404);
    ?>
    <!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <title>Package Not Found · NextTrip</title>
    <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="assets/css/booking.css">
</head><body style="text-align:center;padding:80px 20px;">
        <h1>Package not found</h1>
        <p><a href="packages.php">← Back to Packages</a></p>
    </body></html>
    <?php
    exit;
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$isLoggedIn    = $currentUserId > 0;

$images        = get_package_images($id);
if (empty($images)) {
    $images = [['image_path' => 'assets/images/destinations/placeholder.jpg', 'image_title' => $package['name'], 'alt_text' => $package['name']]];
}
$placesCovered = get_package_destinations($id);
$inclusions    = get_package_inclusions($id);
$exclusions    = get_package_exclusions($id);
$itinerary     = get_package_itinerary($id);
$reviews       = get_package_reviews($id);
$isFavorited   = is_package_favorited($currentUserId, $id);

$agentHasDetails = !empty($package['agent_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/place-details.css">
    <link rel="stylesheet" href="assets/css/package-details.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title><?php echo htmlspecialchars($package['name']); ?> · NextTrip</title>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main class="pd">
        <div class="pd-container">

            <a href="packages.php" class="pd-back"><i class="bi bi-arrow-left"></i> Back to Packages</a>

            <!-- ===================== HEADER (item #2: title, location, rating, favorite — no add-to-cart) ===================== -->
            <div class="pd-header">
                <div class="pd-header__badges">
                    <span class="pd-badge pd-badge--category"><?php echo htmlspecialchars($package['category'] ?? 'Package'); ?></span>
                    <span class="pd-badge pkgd-badge-duration"><i class="bi bi-calendar3"></i> <?php echo (int) $package['duration_days']; ?> Days / <?php echo (int) $package['duration_nights']; ?> Nights</span>
                </div>

                <div class="pd-header__row">
                    <h1 class="pd-title"><?php echo htmlspecialchars($package['name']); ?></h1>

                    <div class="pd-header__actions">
                        <button type="button"
                                class="pd-icon-btn pd-like-btn<?php echo $isFavorited ? ' liked' : ''; ?>"
                                id="pkgdLikeBtn"
                                data-id="<?php echo $id; ?>"
                                aria-label="Add to favourites"
                                aria-pressed="<?php echo $isFavorited ? 'true' : 'false'; ?>">
                            <i class="bi <?php echo $isFavorited ? 'bi-heart-fill' : 'bi-heart'; ?>" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="pd-header__meta">
                    <span><i class="bi bi-geo-alt-fill"></i>
                        <?php
                        $placeNames = array_map(fn($p) => $p['name'], $placesCovered);
                        echo htmlspecialchars(implode(' · ', $placeNames) ?: '—');
                        ?>
                    </span>
                    <span class="pd-header__rating">
                        <span class="pd-stars"><?php pkgd_stars((float) $package['rating']); ?></span>
                        <strong><?php echo number_format((float) $package['rating'], 1); ?></strong>
                        (<?php echo (int) $package['review_count']; ?> reviews)
                    </span>
                </div>
            </div>

            <!-- ===================== IMAGE CAROUSEL (item #2) ===================== -->
            <div class="pd-carousel" id="pkgdCarousel" aria-roledescription="carousel">
                <div class="pd-carousel__viewport">
                    <div class="pd-carousel__track" id="pkgdCarouselTrack">
                        <?php foreach ($images as $img): ?>
                        <div class="pd-carousel__slide">
                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>"
                                 alt="<?php echo htmlspecialchars($img['alt_text'] ?? $package['name']); ?>"
                                 loading="lazy">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (count($images) > 1): ?>
                <button type="button" class="pd-carousel__arrow pd-carousel__arrow--prev" id="pkgdCarouselPrev" aria-label="Previous photo">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="pd-carousel__arrow pd-carousel__arrow--next" id="pkgdCarouselNext" aria-label="Next photo">
                    <i class="bi bi-chevron-right"></i>
                </button>

                <div class="pd-carousel__dots" id="pkgdCarouselDots">
                    <?php foreach ($images as $i => $img): ?>
                    <button type="button" class="pd-carousel__dot<?php echo $i === 0 ? ' is-active' : ''; ?>" data-index="<?php echo $i; ?>" aria-label="Go to photo <?php echo $i + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===================== BODY LAYOUT ===================== -->
            <div class="pd-layout">
                <div class="pd-main">

                    <!-- About -->
                    <?php if (!empty($package['description'])): ?>
                    <div class="pd-card">
                        <h3 class="pd-card__title">About This Package</h3>
                        <p class="pkgd-description"><?php echo nl2br(htmlspecialchars($package['description'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- item #3: Duration + Best Time only (difficulty removed) -->
                    <div class="pd-facts pkgd-facts">
                        <div class="pd-fact">
                            <div class="pd-fact__icon"><i class="bi bi-calendar3"></i></div>
                            <div class="pd-fact__label">Duration</div>
                            <div class="pd-fact__value"><?php echo (int) $package['duration_days']; ?>D / <?php echo (int) $package['duration_nights']; ?>N</div>
                        </div>
                        <div class="pd-fact">
                            <div class="pd-fact__icon"><i class="bi bi-sun"></i></div>
                            <div class="pd-fact__label">Best Time</div>
                            <div class="pd-fact__value"><?php echo htmlspecialchars($package['best_time'] ?? '—'); ?></div>
                        </div>
                    </div>

                    <!-- item #5: Places Covered — full-width horizontal-scroll strip, tap to open destination page -->
                    <?php if (!empty($placesCovered)): ?>
                    <div class="pd-card pkgd-places-card">
                        <h3 class="pd-card__title">Places Covered</h3>
                        <div class="pkgd-places-strip" id="pkgdPlacesStrip">
                            <?php foreach ($placesCovered as $place): ?>
                            <a class="pkgd-place" href="place-details.php?id=<?php echo (int) $place['id']; ?>">
                                <div class="pkgd-place__media">
                                    <img src="<?php echo htmlspecialchars($place['image']); ?>" alt="<?php echo htmlspecialchars($place['name']); ?>" loading="lazy">
                                </div>
                                <div class="pkgd-place__name"><?php echo htmlspecialchars($place['name']); ?></div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Itinerary -->
                    <?php if (!empty($itinerary)): ?>
                    <div class="pd-card">
                        <h3 class="pd-card__title">Itinerary</h3>
                        <div class="pkgd-itinerary">
                            <?php foreach ($itinerary as $day): ?>
                            <div class="pkgd-itinerary__day">
                                <div class="pkgd-itinerary__day-num">Day <?php echo (int) $day['day_number']; ?></div>
                                <div class="pkgd-itinerary__day-body">
                                    <h4><?php echo htmlspecialchars($day['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($day['description']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- item #6: Includes / Excludes -->
                    <div class="pd-card">
                        <h3 class="pd-card__title">What's Included &amp; Excluded</h3>
                        <div class="pkgd-inc-exc">
                            <div class="pkgd-inc-exc__col">
                                <h4><i class="bi bi-check-circle-fill"></i> Included</h4>
                                <ul class="pkgd-inc-list">
                                    <?php foreach ($inclusions as $item): ?>
                                    <li><i class="bi bi-check2"></i> <?php echo htmlspecialchars($item); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (empty($inclusions)): ?><li class="pd-empty">Nothing listed yet.</li><?php endif; ?>
                                </ul>
                            </div>
                            <div class="pkgd-inc-exc__col">
                                <h4><i class="bi bi-x-circle-fill"></i> Excluded</h4>
                                <ul class="pkgd-exc-list">
                                    <?php foreach ($exclusions as $item): ?>
                                    <li><i class="bi bi-x"></i> <?php echo htmlspecialchars($item); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (empty($exclusions)): ?><li class="pd-empty">Nothing listed yet.</li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <div class="pd-card">
                        <div class="pd-reviews-head">
                            <h3 class="pd-card__title">Traveller Reviews</h3>
                            <div class="pd-reviews-summary">
                                <strong><?php echo number_format((float) $package['rating'], 1); ?></strong>
                                <span class="pd-stars"><?php pkgd_stars((float) $package['rating']); ?></span>
                                <div class="pd-reviews-summary__count"><?php echo (int) $package['review_count']; ?> reviews</div>
                            </div>
                        </div>
                        <div class="pd-review-list">
                            <?php foreach ($reviews as $rv): ?>
                            <div class="pd-review">
                                <div class="pd-review__top">
                                    <div class="pd-review__avatar"><?php echo htmlspecialchars(mb_substr($rv['reviewer_name'], 0, 1)); ?></div>
                                    <div>
                                        <div class="pd-review__name"><?php echo htmlspecialchars($rv['reviewer_name']); ?></div>
                                        <div class="pd-review__date"><?php echo date('M Y', strtotime($rv['review_date'])); ?></div>
                                    </div>
                                    <span class="pd-stars pd-review__stars"><?php pkgd_stars((float) $rv['rating']); ?></span>
                                </div>
                                <?php if (!empty($rv['review_title'])): ?><p class="pkgd-review-title"><strong><?php echo htmlspecialchars($rv['review_title']); ?></strong></p><?php endif; ?>
                                <p class="pd-review__text"><?php echo htmlspecialchars($rv['review_text']); ?></p>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($reviews)): ?>
                            <p class="pd-empty">No reviews yet for this package.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- ===================== SIDEBAR: Booking + Agent (items #7, #8) ===================== -->
                <aside class="pd-sidebar">

                    <!-- item #8: Book Package -->
                    <div class="pd-card pkgd-booking-card">
                        <div class="pkgd-booking-card__price">
                            ₹<?php echo number_format((float) $package['price_per_person'], 0); ?>
                            <small>/ person</small>
                        </div>

                        <?php if ($isLoggedIn): ?>
                        <a href="package-booking.php?id=<?= (int) $id ?>" class="pd-btn pd-btn--primary pkgd-book-btn"><i class="bi bi-bag-plus"></i> Add to cart / Book package</a>
                        <p class="pkgd-book-form__note">Send a request to the agent. You pay only after they accept it.</p>
                        <?php else: ?>
                        <a href="auth/login.php?redirect=<?php echo urlencode('package-details.php?id=' . $id); ?>" class="pd-btn pd-btn--primary pkgd-book-btn">Log in to Book</a>
                        <?php endif; ?>
                    </div>

                    <!-- item #7: Agent Details — real DB fields -->
                    <?php if ($agentHasDetails): ?>
                    <div class="pd-card pkgd-agent-card">
                        <h3 class="pd-card__title">Tour Operator</h3>
                        <div class="pkgd-agent__top">
                            <div class="pkgd-agent__avatar"><?php echo htmlspecialchars(mb_substr($package['agent_name'], 0, 1)); ?></div>
                            <div>
                                <div class="pkgd-agent__name"><?php echo htmlspecialchars($package['agent_name']); ?></div>
                            </div>
                        </div>
                        <div class="pkgd-agent__details">
                            <?php if (!empty($package['agent_phone'])): ?>
                            <div><i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($package['agent_phone']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($package['agent_email'])): ?>
                            <div><i class="bi bi-envelope-fill"></i> <?php echo htmlspecialchars($package['agent_email']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($package['agent_city'])): ?>
                            <div><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($package['agent_city']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </aside>
            </div>

        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
    window.PKGD_DATA = {
        packageId: <?php echo json_encode($id); ?>,
        isLoggedIn: <?php echo json_encode($isLoggedIn); ?>,
        loginUrl: <?php echo json_encode('auth/login.php?redirect=' . urlencode('package-details.php?id=' . $id)); ?>,
        pricePerPerson: <?php echo json_encode((float) $package['price_per_person']); ?>
    };
    </script>
    <script src="assets/js/navbar.js"></script>
    <script src="assets/js/package-details.js" defer></script>
</body>
</html>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/config.php';
require_once 'place-details-db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$place = $id > 0 ? get_destination($id) : null;

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$isLoggedIn    = $currentUserId > 0;

$images      = get_destination_images($id);
if (empty($images)) {
    $images = [['image_path' => 'assets/images/destinations/placeholder.jpg', 'image_title' => $place['name'], 'alt_text' => $place['name']]];
}
$sections        = get_destination_sections($id);
$activities      = get_destination_activities($id);
$tips            = get_destination_tips($id);
$hotels          = get_destination_hotels($id);
$restaurants     = get_destination_restaurants($id);
$reviews         = get_destination_reviews_with_crowd($id);
$hoursText       = get_destination_hours_text($id);
$isFavorited     = is_destination_favorited($currentUserId, $id);
$crowdAnalytics  = get_destination_crowd_analytics($id);

// Human-friendly labels for the 24-hour visit-time <select> in the review form.
function pd_time_options(): array {
    $opts = [];
    for ($h = 0; $h < 24; $h++) {
        $opts[sprintf('%02d:00', $h)] = date('g:i A', mktime($h, 0));
    }
    return $opts;
}
$pdTimeOptions = pd_time_options();
$pdCrowdLevels = ['Very Low', 'Low', 'Medium', 'High', 'Very High'];

/**
 * Crowd Level widget defaults to the visitor's current real-world hour.
 * get_crowd_result_for_hour() (place-details-db.php) does the actual
 * review-weighted calculation + nearest-hour fallback; this page only
 * decides which hour to ask it for on first load.
 */
$pdDefaultHour = (int) date('G');
$pdCrowdResult = get_crowd_result_for_hour($id, $pdDefaultHour, $crowdAnalytics);

// Optional "Hourly Forecast" strip — every-2-hours snapshot across the day,
// each one a real (possibly estimated-from-nearby) result, never invented.
$pdForecastHours = [6, 8, 10, 12, 14, 16, 18, 20];
$pdCrowdForecast = [];
$pdHasForecastData = false;
foreach ($pdForecastHours as $fh) {
    $fr = get_crowd_result_for_hour($id, $fh, $crowdAnalytics);
    if ($fr['status'] === 'ok') $pdHasForecastData = true;
    $pdCrowdForecast[] = ['hour' => $fh, 'result' => $fr];
}

/** Hex color for a crowd level name — matches the .pd-dot-- * legend colors. */
function pd_crowd_color(?string $level): string {
    $colors = [
        'Very Low' => '#1fa96a', 'Low' => '#7bc96f', 'Medium' => '#f4b23f',
        'High' => '#f08a3c', 'Very High' => '#e2513f',
    ];
    return $colors[$level] ?? '#d8dee3';
}

$ratingRounded = round((float) $place['rating']);
$crowdWeekday  = $place['crowd_weekday'] ?: 'Low';
$crowdWeekend  = $place['crowd_weekend'] ?: 'Low';

/** "50" -> "₹50", "0"/"" -> "Free", null -> "—". */
function pd_entry_fee_text($fee): string {
    if ($fee === null || $fee === '') return '—';
    $num = is_numeric($fee) ? (float) $fee : null;
    if ($num !== null) return $num <= 0 ? 'Free' : '₹' . number_format($num, 0);
    return (string) $fee;
}

function pd_stars(float $rating): string {
    $full = (int) round($rating);
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<i class="bi ' . ($i <= $full ? 'bi-star-fill' : 'bi-star') . '"></i>';
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextTrip</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/place-details.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="pd">
        <div class="pd-container">

            <a href="destinations.php" class="pd-back"><i class="bi bi-arrow-left"></i> Back to Destinations</a>

            <!-- ===================== HEADER ===================== -->
            <div class="pd-header">
                <div class="pd-header__badges">
                    <span class="pd-badge pd-badge--category"><?php echo htmlspecialchars($place['category'] ?? 'Destination') ?> · <?php echo htmlspecialchars($place['state']) ?></span>
                    <span class="pd-badge pd-badge--crowd pd-badge--crowd-<?php echo strtolower($crowdWeekday); ?>">
                        <span class="pd-badge__dot"></span><?php echo htmlspecialchars($crowdWeekday); ?> Crowd
                    </span>
                </div>

                <div class="pd-header__row">
                    <h1 class="pd-title"><?php echo htmlspecialchars($place['name']) ?></h1>

                    <div class="pd-header__actions">
                        <button type="button"
                                class="pd-icon-btn pd-like-btn<?php echo $isFavorited ? ' liked' : ''; ?>"
                                id="pdLikeBtn"
                                data-id="<?php echo $id; ?>"
                                aria-label="Add to favourites"
                                aria-pressed="<?php echo $isFavorited ? 'true' : 'false'; ?>">
                            <i class="bi <?php echo $isFavorited ? 'bi-heart-fill' : 'bi-heart'; ?>" aria-hidden="true"></i>
                        </button>
                        <a href="packages.php?destination=<?php echo htmlspecialchars($place['id']); ?>" class="pd-btn pd-btn--primary">Book Package</a>
                    </div>
                </div>

                <div class="pd-header__meta">
                    <span><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($place['location']); ?></span>
                    <span class="pd-header__rating">
                        <span class="pd-stars"><?php pd_stars((float) $place['rating']) ?></span>
                        <strong><?php echo number_format((float) $place['rating'], 1) ?></strong>
                        (<?php echo (int) $place['review_count'] ?> reviews)
                    </span>
                </div>
            </div>

            <!-- ===================== IMAGE CAROUSEL ===================== -->
            <div class="pd-carousel" id="pdCarousel" aria-roledescription="carousel">
                <div class="pd-carousel__viewport">
                    <div class="pd-carousel__track" id="pdCarouselTrack">
                        <?php foreach ($images as $img): ?>
                        <div class="pd-carousel__slide">
                            <img src="<?php echo htmlspecialchars($img['image_path']) ?>"
                                 alt="<?php echo htmlspecialchars($img['alt_text'] ?? $place['name']) ?>"
                                 loading="lazy">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (count($images) > 1): ?>
                <button type="button" class="pd-carousel__arrow pd-carousel__arrow--prev" id="pdCarouselPrev" aria-label="Previous photo">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="pd-carousel__arrow pd-carousel__arrow--next" id="pdCarouselNext" aria-label="Next photo">
                    <i class="bi bi-chevron-right"></i>
                </button>

                <div class="pd-carousel__dots" id="pdCarouselDots">
                    <?php foreach ($images as $i => $img): ?>
                    <button type="button" class="pd-carousel__dot<?php echo $i === 0 ? ' is-active' : '' ?>" data-index="<?php echo $i ?>" aria-label="Go to photo <?php echo $i + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===================== BODY LAYOUT ===================== -->
            <div class="pd-layout">

                <!-- ---------- MAIN COLUMN ---------- -->
                <div class="pd-main">

                    <!-- About / History / Importance -->
                    <div class="pd-card pd-tabs-card">
                        <div class="pd-tabs" role="tablist">
                            <?php
                            $tabOrder = ['About', 'History', 'Importance'];
                            foreach ($tabOrder as $i => $type):
                                if (!isset($sections[$type])) continue;
                            ?>
                            <button type="button" class="pd-tab<?php echo $i === 0 ? ' is-active' : '' ?>" role="tab"
                                aria-selected="<?php echo $i === 0 ? 'true' : 'false' ?>" data-tab="<?php echo strtolower($type) ?>">
                                <?php echo htmlspecialchars($type) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="pd-tab-content" id="pdTabContent">
                            <?php foreach ($tabOrder as $i => $type):
                                if (!isset($sections[$type])) continue;
                                $sec = $sections[$type];
                            ?>
                            <div class="pd-tab-panel<?php echo $i === 0 ? ' is-active' : '' ?>" data-panel="<?php echo strtolower($type) ?>">
                                <h3 class="pd-tab-panel__title"><?php echo htmlspecialchars($sec['title']) ?></h3>
                                <p class="pd-tab-panel__text"><?php echo htmlspecialchars($sec['content']) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quick facts row 1 -->
                    <div class="pd-facts">
                        <div class="pd-fact">
                            <div class="pd-fact__icon"><i class="bi bi-calendar-event"></i></div>
                            <div class="pd-fact__label">Best Time</div>
                            <div class="pd-fact__value"><?php echo htmlspecialchars($place['best_time'] ?? '—'); ?></div>
                        </div>
                        <div class="pd-fact">
                            <div class="pd-fact__icon"><i class="bi bi-alarm"></i></div>
                            <div class="pd-fact__label">Opening Hours</div>
                            <div class="pd-fact__value"><?php echo htmlspecialchars($hoursText); ?></div>
                        </div>
                        <div class="pd-fact">
                            <div class="pd-fact__icon"><i class="bi bi-ticket-perforated"></i></div>
                            <div class="pd-fact__label">Entry Fee</div>
                            <div class="pd-fact__value"><?php echo htmlspecialchars(pd_entry_fee_text($place['entry_fee'])); ?></div>
                        </div>
                    </div>

                    <!-- Activities + Travel Tips row 2 -->
                    <div class="pd-lists-row">
                        <div class="pd-card pd-list-card">
                            <h3 class="pd-card__title">Activities</h3>
                            <div class="pd-activities">
                                <?php foreach ($activities as $a): ?>
                                <span class="pd-activity-pill">
                                    <span class="pd-activity-pill__icon"><?php echo htmlspecialchars($a['icon'] ?? '📍'); ?></span>
                                    <?php echo htmlspecialchars($a['activity_name']); ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (empty($activities)): ?>
                                <p class="pd-empty">No activities listed yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pd-card pd-list-card">
                            <h3 class="pd-card__title">Travel Tips</h3>
                            <ul class="pd-tips">
                                <?php foreach ($tips as $tip): ?>
                                <li><i class="bi bi-lightbulb"></i><span><?php echo htmlspecialchars($tip); ?></span></li>
                                <?php endforeach; ?>
                                <?php if (empty($tips)): ?>
                                <li class="pd-empty">No tips added yet.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Nearby Hotels -->
                    <?php if (!empty($hotels)): ?>
                    <div class="pd-card">
                        <h3 class="pd-card__title">Nearby Hotels</h3>
                        <div class="pd-nearby-list">
                            <?php foreach ($hotels as $h): ?>
                            <div class="pd-nearby-item">
                                <img src="<?php echo htmlspecialchars($h['image_path']); ?>" alt="<?php echo htmlspecialchars($h['hotel_name']); ?>" class="pd-nearby-item__img" loading="lazy" onerror="this.onerror=null;this.src='assets/images/hotels/hotel-placeholder.jpg';">
                                <div class="pd-nearby-item__body">
                                    <div class="pd-nearby-item__name"><?php echo htmlspecialchars($h['hotel_name']); ?></div>
                                    <div class="pd-nearby-item__meta">
                                        <i class="bi bi-geo-alt-fill"></i> <?php echo number_format((float) $h['distance_km'], 1); ?> km
                                        <i class="bi bi-star-fill" style="margin-left:10px;"></i> <?php echo number_format((float) $h['rating'], 1); ?>
                                    </div>
                                </div>
                                <div class="pd-nearby-item__side">
                                    <div class="pd-nearby-item__price">₹<?php echo number_format((float) $h['price_per_night'], 0); ?>/night</div>
                                    <a href="#" class="pd-chip-btn">Book</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Nearby Restaurants -->
                    <?php if (!empty($restaurants)): ?>
                    <div class="pd-card">
                        <h3 class="pd-card__title">Nearby Restaurants</h3>
                        <div class="pd-nearby-list">
                            <?php foreach ($restaurants as $r): ?>
                            <div class="pd-nearby-item pd-nearby-item--compact">
                                <div class="pd-nearby-item__icon"><i class="bi bi-cup-hot-fill"></i></div>
                                <div class="pd-nearby-item__body">
                                    <div class="pd-nearby-item__name"><?php echo htmlspecialchars($r['restaurant_name']); ?></div>
                                    <div class="pd-nearby-item__meta">
                                        <?php echo htmlspecialchars($r['cuisine']); ?> · <?php echo number_format((float) $r['distance_km'], 1); ?> km
                                    </div>
                                </div>
                                <div class="pd-nearby-item__side">
                                    <i class="bi bi-star-fill"></i> <?php echo number_format((float) $r['rating'], 1); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Traveller Reviews -->
                    <div class="pd-card" id="pdReviews">
                        <div class="pd-reviews-head">
                            <h3 class="pd-card__title">Traveller Reviews</h3>
                            <div class="pd-reviews-summary">
                                <strong><?php echo number_format((float) $place['rating'], 1); ?></strong>
                                <span class="pd-stars"><?php echo pd_stars((float) $place['rating']); ?></span>
                                <div class="pd-reviews-summary__count"><?php echo (int) $place['review_count']; ?> reviews</div>
                            </div>
                        </div>

                        <div class="pd-review-list" id="pdReviewList">
                            <?php foreach ($reviews as $rv): ?>
                            <div class="pd-review">
                                <div class="pd-review__top">
                                    <div class="pd-review__avatar"><?php echo htmlspecialchars(mb_substr($rv['reviewer_name'], 0, 1)); ?></div>
                                    <div>
                                        <div class="pd-review__name"><?php echo htmlspecialchars($rv['reviewer_name']); ?></div>
                                        <div class="pd-review__date"><?php echo date('M Y', strtotime($rv['created_at'])); ?></div>
                                    </div>
                                    <span class="pd-stars pd-review__stars"><?php echo pd_stars((float) $rv['rating']); ?></span>
                                </div>
                                <p class="pd-review__text"><?php echo htmlspecialchars($rv['review_text']); ?></p>
                                <?php if (!empty($rv['visit_time']) || !empty($rv['crowd_level'])): ?>
                                <div class="pd-review__crowd-meta">
                                    <?php if (!empty($rv['visit_time'])): ?>
                                    <span><i class="bi bi-clock"></i> Visited around <?php echo date('g:i A', strtotime($rv['visit_time'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($rv['crowd_level'])): ?>
                                    <span class="pd-review__crowd-tag pd-review__crowd-tag--<?php echo strtolower(str_replace(' ', '-', $rv['crowd_level'])); ?>">
                                        <i class="bi bi-people-fill"></i> <?php echo htmlspecialchars($rv['crowd_level']); ?> crowd
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($reviews)): ?>
                            <p class="pd-empty" id="pdReviewsEmpty">No reviews yet — be the first to share your experience.</p>
                            <?php endif; ?>
                        </div>

                        <?php if ($isLoggedIn): ?>
                        <form class="pd-review-form" id="pdReviewForm" data-destination-id="<?php echo $id; ?>">
                            <h4>Add Your Review</h4>
                            <div class="pd-review-form__stars" id="pdReviewStars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button" class="pd-review-form__star" data-value="<?= $i ?>" aria-label="<?= $i ?> star"><i class="bi bi-star-fill"></i></button>
                                <?php endfor; ?>
                            </div>
                            <textarea class="pd-review-form__text" placeholder="Share your experience..." rows="3" maxlength="1000"></textarea>

                            <div class="pd-review-form__crowd-row">
                                <div class="pd-review-form__field">
                                    <label for="pdVisitTime">Time you visited</label>
                                    <select id="pdVisitTime" class="pd-review-form__select">
                                        <option value="">Select time (optional)</option>
                                        <?php foreach ($pdTimeOptions as $val => $label): ?>
                                        <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="pd-review-form__field">
                                    <label for="pdCrowdLevel">Crowd level you experienced</label>
                                    <select id="pdCrowdLevel" class="pd-review-form__select">
                                        <option value="">Select crowd level (optional)</option>
                                        <?php foreach ($pdCrowdLevels as $lvl): ?>
                                        <option value="<?php echo $lvl; ?>"><?php echo $lvl; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="pd-btn pd-btn--primary">Submit Review</button>
                        </form>
                        <?php else: ?>
                        <div class="pd-review-login-prompt">
                            <i class="bi bi-lock"></i>
                            <span><a href="auth/login.php?redirect=<?php echo urlencode('place-details.php?id=' . $id); ?>">Log in</a> to write a review and report crowd levels.</span>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- ---------- SIDEBAR ---------- -->
                <aside class="pd-sidebar">

                    <!-- Crowd Level (compact, review-driven) -->
                    <div class="pd-card pd-crowd-mini" id="pdCrowdMini" data-destination-id="<?php echo $id; ?>">
                        <h3 class="pd-card__title">Crowd Level</h3>

                        <div class="pd-crowd-mini__arc-wrap">
                            <svg viewBox="0 0 220 130" class="pd-crowd-mini__arc-svg" aria-hidden="true">
                                <path class="pd-crowd-mini__arc-track" d="M 25 110 A 85 85 0 0 1 195 110" />
                                <path class="pd-crowd-mini__arc-fill" id="pdCrowdMiniArc" pathLength="100"
                                      d="M 25 110 A 85 85 0 0 1 195 110"
                                      style="stroke-dasharray:100;stroke-dashoffset:<?php echo $pdCrowdResult['status'] === 'ok' ? (100 - $pdCrowdResult['percentage']) : 100; ?>;stroke:<?php echo $pdCrowdResult['status'] === 'ok' ? pd_crowd_color($pdCrowdResult['level']) : '#d8dee3'; ?>;" />
                            </svg>
                            <div class="pd-crowd-mini__center">
                                <div class="pd-crowd-mini__pct" id="pdCrowdMiniPct"><?php echo $pdCrowdResult['status'] === 'ok' ? (int) $pdCrowdResult['percentage'] . '%' : '—'; ?></div>
                                <div class="pd-crowd-mini__label" id="pdCrowdMiniLabel"><?php echo htmlspecialchars($pdCrowdResult['status'] === 'ok' ? $pdCrowdResult['level'] : 'No data'); ?></div>
                            </div>
                        </div>

                        <div class="pd-crowd-mini__legend">
                            <span><span class="pd-dot pd-dot--very-low"></span>Very Low</span>
                            <span><span class="pd-dot pd-dot--low"></span>Low</span>
                            <span><span class="pd-dot pd-dot--medium"></span>Medium</span>
                            <span><span class="pd-dot pd-dot--high"></span>High</span>
                            <span><span class="pd-dot pd-dot--very-high"></span>Very High</span>
                        </div>

                        <label class="pd-crowd-mini__time">
                            <span><i class="bi bi-clock"></i> Select Time</span>
                            <select id="pdCrowdMiniTimeSelect">
                                <?php foreach ($pdTimeOptions as $val => $label): $th = (int) substr($val, 0, 2); ?>
                                <option value="<?php echo $th; ?>"<?php echo $th === $pdDefaultHour ? ' selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <div class="pd-crowd-mini__status<?php echo $pdCrowdResult['status'] !== 'ok' ? ' is-empty' : ''; ?>" id="pdCrowdMiniStatus">
                            <?php if ($pdCrowdResult['status'] === 'ok'): ?>
                                <?php echo $pdCrowdResult['estimated']
                                    ? 'Estimated from nearby reports'
                                    : 'Based on ' . (int) $pdCrowdResult['count'] . ' visitor review' . ($pdCrowdResult['count'] === 1 ? '' : 's'); ?>
                            <?php else: ?>
                                No visitor reports available for this time
                            <?php endif; ?>
                        </div>

                        <?php if ($pdHasForecastData): ?>
                        <div class="pd-crowd-mini__forecast">
                            <div class="pd-crowd-mini__forecast-label"><i class="bi bi-bar-chart-fill"></i> Hourly Forecast</div>
                            <div class="pd-crowd-mini__forecast-bars" id="pdCrowdMiniForecastBars">
                                <?php
                                $pdForecastBarMaxPx = 52;
                                foreach ($pdCrowdForecast as $f):
                                    $fr = $f['result'];
                                    $fpct = $fr['status'] === 'ok' ? max(6, $fr['percentage']) : 6;
                                    $fpx  = max(4, (int) round($fpct / 100 * $pdForecastBarMaxPx));
                                    $fcls = $fr['status'] === 'ok' ? strtolower(str_replace(' ', '-', $fr['level'])) : 'none';
                                ?>
                                <div class="pd-crowd-mini__forecast-bar<?php echo $f['hour'] === $pdDefaultHour ? ' is-active' : ''; ?>" data-hour="<?php echo $f['hour']; ?>">
                                    <div class="pd-crowd-mini__forecast-fill pd-crowd-mini__forecast-fill--<?php echo $fcls; ?>" style="height:<?php echo $fpx; ?>px"></div>
                                    <div class="pd-crowd-mini__forecast-time"><?php echo htmlspecialchars(strtolower(date('ga', mktime($f['hour'], 0)))); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Interactive Map -->
                    <div class="pd-card pd-map-card">
                        <h3 class="pd-card__title">Interactive Map</h3>
                        <div id="pdMap" class="pd-map"
                             data-lat="<?php echo htmlspecialchars($place['latitude']); ?>"
                             data-lng="<?php echo htmlspecialchars($place['longitude']); ?>"
                             data-name="<?php echo htmlspecialchars($place['name']); ?>"></div>
                        <div class="pd-map__coords"><?php echo number_format((float) $place['latitude'], 4); ?>° N, <?php echo number_format((float) $place['longitude'], 4); ?>° E</div>

                        <div class="pd-map__actions">
                            <a class="pd-chip-btn" id="pdDirectionsBtn"
                               href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($place['latitude'] . ',' . $place['longitude']) ?>"
                               target="_blank" rel="noopener">Directions</a>
                            <button type="button" class="pd-chip-btn" id="pdMyLocationBtn">My Location</button>
                            <a class="pd-chip-btn" id="pdNearbyBtn"
                               href="https://www.google.com/maps/search/hotels+restaurants/@<?= htmlspecialchars($place['latitude']) ?>,<?= htmlspecialchars($place['longitude']) ?>,14z"
                               target="_blank" rel="noopener">Nearby</a>
                        </div>
                    </div>

                    <!-- Plan Your Visit CTA -->
                    <div class="pd-cta-card">
                        <div class="pd-cta-card__label">Plan Your Visit</div>
                        <?php if ($place['starting_price']): ?>
                        <div class="pd-cta-card__price">From ₹<?php echo number_format((float) $place['starting_price'], 0); ?> per person</div>
                        <?php endif; ?>
                        <a href="packages.php?destination=<?php echo $id; ?>" class="pd-cta-card__btn">Book a Package →</a>
                    </div>

                </aside>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

<script>
    // Server-computed data the client-side widgets need, without extra requests.
    window.PD_DATA = {
        destinationId: <?= json_encode($id) ?>,
        isLoggedIn: <?= json_encode($isLoggedIn) ?>,
        loginUrl: <?= json_encode('auth/login.php?redirect=' . urlencode('place-details.php?id=' . $id)) ?>,
        map: {
            lat: <?= json_encode((float) $place['latitude']) ?>,
            lng: <?= json_encode((float) $place['longitude']) ?>,
            name: <?= json_encode($place['name']) ?>
        }
    };
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/navbar.js"></script>
<script src="assets/js/place-details.js"></script>
</body>
</html>
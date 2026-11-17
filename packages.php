<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/config.php';
include 'packages-db.php';

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

// Arriving from a destination's "Book Package" link (place-details.php)
$destinationFilter = isset($_GET['destination']) ? (int) $_GET['destination'] : 0;

// First page load: show everything (or pre-filtered by destination), no filters applied
$initialResults = filter_packages(['destination_id' => $destinationFilter, 'user_id' => $currentUserId]);

/** One card's markup — used for the very first (PHP-rendered) load only;
 *  packages.js rebuilds this same markup client-side for every search/filter. */
function pkg_card_html(array $p): string {
    $category   = $p['category'] ?? 'Package';
    $isFav      = (bool) $p['is_favorited'];
    $destNames  = array_filter(array_map('trim', explode(',', $p['destinations_label'] ?? '')));
    $itinerary  = pkg_itinerary_preview($p['itinerary_raw'] ?? null);
    ob_start();
    ?>
    <a class="pkg-card" href="package-details.php?id=<?php echo (int) $p['id']; ?>" data-id="<?php echo (int) $p['id']; ?>">
      <div class="pkg-card__media">
        <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
        <span class="pkg-card__tag"><?php echo htmlspecialchars($category); ?></span>
        <span class="pkg-card__rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float) $p['rating'], 1); ?></span>
       
        </button>
        <div class="pkg-card__price">
          <span class="pkg-card__price-amount">₹<?php echo number_format((float) $p['price_per_person'], 0); ?></span><span class="pkg-card__price-unit">/person</span>
        </div>
      </div>
      <div class="pkg-card__body">
        <h3 class="pkg-card__title"><?php echo htmlspecialchars($p['name']); ?></h3>
        <div class="pkg-card__meta">
          <span><i class="bi bi-clock"></i> <?php echo (int) $p['duration_days']; ?>D/<?php echo (int) $p['duration_nights']; ?>N</span>
          <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($p['agent_name'] ?? 'NextTrip'); ?></span>
          <span><i class="bi bi-tag"></i> <?php echo htmlspecialchars($p['best_time'] ?? 'All Season'); ?></span>
        </div>
        <?php if (!empty($destNames)): ?>
        <div class="pkg-card__dest-pills">
          <?php foreach ($destNames as $dn): ?>
          <span class="pkg-card__dest-pill"><i class="bi bi-geo-alt-fill"></i><?php echo htmlspecialchars($dn); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($itinerary['days'])): ?>
        <div class="pkg-card__itinerary">
          <div class="pkg-card__itinerary-title">Itinerary Preview</div>
          <?php foreach ($itinerary['days'] as $day): ?>
          <div class="pkg-card__itinerary-row">
            <span class="pkg-card__itinerary-num"><?php echo (int) $day['day_number']; ?></span>
            <span>Day <?php echo (int) $day['day_number']; ?>: <?php echo htmlspecialchars($day['title']); ?></span>
          </div>
          <?php endforeach; ?>
          <?php if ($itinerary['more'] > 0): ?>
          <div class="pkg-card__itinerary-more">+<?php echo (int) $itinerary['more']; ?> more days</div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <span class="pkg-card__book-btn">Book Now <i class="bi bi-arrow-right"></i></span>
      </div>
    </a>
    <?php
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/destinations.css">
    <link rel="stylesheet" href="assets/css/packages.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Packages · NextTrip</title>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main>
        <section class="dest-hero">
            <h1 class="dest-hero__title">Curated Tour Packages</h1>
            <p class="dest-hero__subtext">
                Handpicked multi-day itineraries across India — stays, transport and experiences bundled together.
            </p>

            <div class="dest-search">
                <i class="bi bi-search dest-search__icon" aria-hidden="true"></i>
                <input
                    type="text"
                    id="pkgSearchInput"
                    class="dest-search__input"
                    placeholder="Search package name or destination..."
                    autocomplete="off"
                    aria-label="Search packages"
                />
                <div class="dest-search__spinner" id="pkgSearchSpinner" hidden></div>
            </div>
        </section>

        <section class="dest-explore">

            <div class="dest-toolbar">
                <div class="dest-tabs" id="pkgTabs" role="tablist" aria-label="Filter by category">
                    <span class="dest-tabs__indicator" id="pkgTabsIndicator"></span>
                    <button class="dest-tab is-active" type="button" role="tab" aria-selected="true" data-category="all">All</button>
                    <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="beach">Beaches</button>
                    <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="mountain">Mountains</button>
                    <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="heritage">Heritage</button>
                    <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="religious">Religious</button>
                    <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="nature">Nature</button>
                    <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="adventure">Adventure</button>
                </div>

                <button class="dest-filters-btn" id="pkgFiltersBtn" type="button" aria-expanded="false" aria-controls="pkgFilterPanel">
                    <i class="bi bi-sliders" aria-hidden="true"></i>
                    <span>Filters</span>
                    <i class="bi bi-chevron-down dest-filters-btn__chevron" aria-hidden="true"></i>
                </button>
            </div>

            <p class="dest-count">
                Showing <strong id="pkgCount"><?php echo count($initialResults); ?></strong> packages
            </p>

            <!-- Filter panel: Budget / Duration / Min Rating / Sort -->
            <div class="dest-filter-panel" id="pkgFilterPanel" hidden>

                <div class="dest-filter-group">
                    <p class="dest-filter-label">Budget (per person)</p>
                    <div class="dest-pills" id="pkgBudgetPills">
                        <button class="dest-pill is-active" type="button" data-budget="all">Any</button>
                        <button class="dest-pill" type="button" data-budget="0-10000">Under ₹10,000</button>
                        <button class="dest-pill" type="button" data-budget="10000-15000">₹10,000 – ₹15,000</button>
                        <button class="dest-pill" type="button" data-budget="15000-20000">₹15,000 – ₹20,000</button>
                        <button class="dest-pill" type="button" data-budget="20000-999999">₹20,000+</button>
                    </div>
                </div>

                <div class="dest-filter-divider"></div>

                <div class="dest-filter-group">
                    <p class="dest-filter-label">Duration</p>
                    <div class="dest-pills" id="pkgDurationPills">
                        <button class="dest-pill is-active" type="button" data-duration="all">Any</button>
                        <button class="dest-pill" type="button" data-duration="short">1 – 3 Days</button>
                        <button class="dest-pill" type="button" data-duration="medium">4 – 5 Days</button>
                        <button class="dest-pill" type="button" data-duration="long">6+ Days</button>
                    </div>
                </div>

                <div class="dest-filter-divider"></div>

                <div class="dest-filter-group">
                    <div class="dest-filter-label-row">
                        <p class="dest-filter-label">Min Rating</p>
                        <span class="dest-rating-value" id="pkgRatingValue">1.0 ★</span>
                    </div>
                    <input
                        type="range"
                        id="pkgRatingSlider"
                        class="dest-slider"
                        min="1" max="5" step="0.1" value="1"
                        aria-label="Minimum rating"
                    />
                    <div class="dest-slider-scale">
                        <span>1.0</span><span>2.0</span><span>3.0</span><span>4.0</span><span>5.0</span>
                    </div>
                </div>

                <div class="dest-filter-divider"></div>

                <div class="dest-filter-group">
                    <p class="dest-filter-label">Sort By</p>
                    <div class="dest-select-wrap">
                        <select id="pkgSortSelect" class="dest-select" aria-label="Sort packages">
                            <option value="popularity">Popularity</option>
                            <option value="rating_desc">Rating (High to Low)</option>
                            <option value="price_asc">Price (Low to High)</option>
                            <option value="price_desc">Price (High to Low)</option>
                            <option value="az">A – Z</option>
                            <option value="za">Z – A</option>
                        </select>
                        <i class="bi bi-chevron-down dest-select-wrap__chevron" aria-hidden="true"></i>
                    </div>
                </div>

                <div class="dest-filter-actions">
                    <button class="dest-btn dest-btn--primary" id="pkgApplyBtn" type="button">Apply Filters</button>
                    <button class="dest-btn dest-btn--text" id="pkgResetBtn" type="button">Reset</button>
                </div>
            </div>

            <div class="dest-grid pkg-grid" id="pkgGrid" aria-live="polite">
                <?php if (empty($initialResults)): ?>
                <div class="dest-empty">
                    <i class="bi bi-suitcase-lg" aria-hidden="true"></i>
                    <h3>No packages match that</h3>
                    <p>Try a different search term, or reset the filters.</p>
                    <button class="dest-btn dest-btn--primary" type="button" id="pkgEmptyResetBtn">Reset Filters</button>
                </div>
                <?php else: foreach ($initialResults as $p): ?>
                <?php echo pkg_card_html($p); ?>
                <?php endforeach; endif; ?>
            </div>

        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>window.PKG_IS_LOGGED_IN = <?php echo $currentUserId > 0 ? 'true' : 'false'; ?>;
    window.PKG_LOGIN_URL = <?php echo json_encode('auth/login.php?redirect=' . urlencode('packages.php')); ?>;</script>
    <script src="assets/js/packages.js" defer></script>
    <script src="assets/js/navbar.js"></script>
</body>
</html>

<?php
require_once 'config/config.php';
include 'nexttrip-db.php';

// First page load: show everything, weekday view, no filters applied
$initialResults = filter_destinations([]);

// One little helper so the PHP loop below and nothing else needs to
// repeat this markup — used only on the very first load.
function dest_card_html(array $d): string {
    $category   = $d['category'] ?? '';
    $crowd      = strtolower($d['crowd_level'] ?? 'low');
    $crowdLabel = ucfirst($crowd);
    ob_start();
  
    ?>
    <a class="dest-card" href="place-details.php?id=<?php echo (int) $d['id']; ?>" data-id="<?php echo (int) $d['id']; ?>">
      <div class="dest-card__media">
        <img src="<?php echo htmlspecialchars($d['image']); ?>" alt="<?php echo htmlspecialchars($d['name']); ?>" loading="lazy">
        <span class="dest-card__tag"><?php echo htmlspecialchars($category); ?></span>
        <span class="dest-card__crowd dest-card__crowd--<?php echo $crowd; ?>">
          <span class="dest-card__crowd-dot"></span><?php echo $crowdLabel; ?>
        </span>
      </div>
      <div class="dest-card__body">
        <h3 class="dest-card__title"><?php echo htmlspecialchars($d['name']); ?></h3>
        <p class="dest-card__location"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i><?php echo htmlspecialchars($d['location']); ?></p>
        <div class="dest-card__stats">
          <span><i class="bi bi-star-fill" aria-hidden="true"></i><?php echo number_format((float) $d['rating'], 1); ?></span>
          <span class="dest-card__stat-sep"></span>
          <span><i class="bi bi-calendar-event" aria-hidden="true"></i><?php echo htmlspecialchars($d['season'] ?? '—'); ?></span>
        </div>
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
    <!-- CSS FILES LINK -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/destinations.css">

     <!-- BOOTSTRAP ICONS LINK -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
 
    <title>NextTrip</title>
</head>
<body>
    <!-- navbar -->
    <?php include 'includes/navbar.php'; ?>

    <main>

        <section class="dest-hero">
        <h1 class="dest-hero__title">Discover India</h1>
        <p class="dest-hero__subtext">
            12 handpicked destinations, with live crowd forecasts to help you pick the right day to go.
        </p>

        <div class="dest-search">
            <i class="bi bi-search dest-search__icon" aria-hidden="true"></i>
            <input
            type="text"
            id="destSearchInput"
            class="dest-search__input"
            placeholder="Search destination or location..."
            autocomplete="off"
            aria-label="Search destinations"
            />
            <div class="dest-search__spinner" id="destSearchSpinner" hidden></div>
        </div>
        </section>

        <section class="dest-explore">

        <div class="dest-toolbar">
            <div class="dest-tabs" id="destTabs" role="tablist" aria-label="Filter by category">
            <span class="dest-tabs__indicator" id="destTabsIndicator"></span>
            <button class="dest-tab is-active" type="button" role="tab" aria-selected="true" data-category="all">All</button>
            <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="beach">Beach</button>
            <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="mountain">Mountain</button>
            <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="heritage">Heritage</button>
            <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="religious">Religious</button>
            <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="nature">Nature</button>
            <button class="dest-tab" type="button" role="tab" aria-selected="false" data-category="adventure">Adventure</button>
            </div>

            <button class="dest-filters-btn" id="destFiltersBtn" type="button" aria-expanded="false" aria-controls="destFilterPanel">
            <i class="bi bi-sliders" aria-hidden="true"></i>
            <span>Filters</span>
            <i class="bi bi-chevron-down dest-filters-btn__chevron" aria-hidden="true"></i>
            </button>
        </div>

        <p class="dest-count">
            Showing <strong id="destCount"><?php echo count($initialResults); ?></strong> destinations
        </p>

        <!-- Filter panel: slides open below the tabs/Filters row, not a popup -->
        <div class="dest-filter-panel" id="destFilterPanel" hidden>

            <div class="dest-filter-group">
            <p class="dest-filter-label">Crowd Level</p>

            <div class="dest-daytoggle" id="destDayToggle" role="tablist" aria-label="Day type">
                <span class="dest-daytoggle__indicator" id="destDayToggleIndicator"></span>
                <button class="dest-daytoggle__opt is-active" type="button" data-daytype="weekday">Weekday</button>
                <button class="dest-daytoggle__opt" type="button" data-daytype="weekend">Weekend</button>
            </div>

            <div class="dest-pills" id="destCrowdPills">
                <button class="dest-pill is-active" type="button" data-crowd="all">All</button>
                <button class="dest-pill" type="button" data-crowd="low"><span class="dest-dot dest-dot--low"></span>Low</button>
                <button class="dest-pill" type="button" data-crowd="medium"><span class="dest-dot dest-dot--medium"></span>Medium</button>
                <button class="dest-pill" type="button" data-crowd="high"><span class="dest-dot dest-dot--high"></span>High</button>
            </div>
            </div>

            <div class="dest-filter-divider"></div>

            <div class="dest-filter-group">
            <div class="dest-filter-label-row">
                <p class="dest-filter-label">Min Rating</p>
                <span class="dest-rating-value" id="destRatingValue">1.0 ★</span>
            </div>
            <input
                type="range"
                id="destRatingSlider"
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
                <select id="destSortSelect" class="dest-select" aria-label="Sort destinations">
                <option value="popularity">Popularity</option>
                <option value="rating_desc">Rating (High to Low)</option>
                <option value="az">A – Z</option>
                <option value="za">Z – A</option>
                </select>
                <i class="bi bi-chevron-down dest-select-wrap__chevron" aria-hidden="true"></i>
            </div>
            </div>

            <div class="dest-filter-actions">
            <button class="dest-btn dest-btn--primary" id="destApplyBtn" type="button">Apply Filters</button>
            <button class="dest-btn dest-btn--text" id="destResetBtn" type="button">Reset</button>
            </div>
        </div>

        <!-- Card grid: PHP fills this in on first load; destinations.js
            replaces its contents every time you search or filter -->
        <div class="dest-grid" id="destGrid" aria-live="polite">
            <?php if (empty($initialResults)): ?>
            <div class="dest-empty">
                <i class="bi bi-map" aria-hidden="true"></i>
                <h3>No destinations match that</h3>
                <p>Try a different search term, or reset the filters.</p>
                <button class="dest-btn dest-btn--primary" type="button" id="destEmptyResetBtn">Reset Filters</button>
            </div>
            <?php else: foreach ($initialResults as $d): ?>
            <?php echo dest_card_html($d); ?>
            <?php endforeach; endif; ?>
        </div>

        </section>

    </main>


    <!-- footer -->
    <?php include 'includes/footer.php'; ?>

<script src="assets/js/destinations.js" defer></script>
<script src="assets/js/navbar.js"></script>

</body>
</html>
<?php
/**
 * packages-db.php
 * ------------------------------------------------------------
 * Same two-job pattern as nexttrip-db.php, but for tour Packages:
 *   1. Connects to the database (mysqli)
 *   2. Provides filter_packages($params) which runs the
 *      search/filter/sort query and returns matching rows
 * ------------------------------------------------------------
 * Used by BOTH:
 *   - package-search.php (the AJAX endpoint the page calls while typing)
 *   - packages.php        (the page itself, for the first normal load)
 * ------------------------------------------------------------
 */

require_once 'config/config.php';

const PKG_SORT_MAP = [
    'popularity'   => 'rating DESC, p.package_name ASC',
    'rating_desc'  => 'rating DESC, p.package_name ASC',
    'price_asc'    => 'p.price_per_person ASC',
    'price_desc'   => 'p.price_per_person DESC',
    'az'           => 'p.package_name ASC',
    'za'           => 'p.package_name DESC',
];

/**
 * Applies search + filter + sort params against the database.
 * $params keys: q, category, min_budget, max_budget, duration, min_rating, sort, destination_id
 *
 * `category` matches categories.category_name with a leading-substring
 * LIKE (e.g. 'heritage' matches 'Heritage'), same convention as
 * filter_destinations() in nexttrip-db.php.
 *
 * `duration` is a bucket key: 'all' | 'short' (1-3 days) | 'medium' (4-5
 * days) | 'long' (6+ days) — matches the Duration select in the filter panel.
 *
 * `destination_id`, when > 0, restricts results to packages that include
 * that destination (used when arriving from a "Book Package" link on a
 * destination's own page).
 */
function filter_packages(array $params): array {
    global $conn;

    $q            = trim($params['q'] ?? '');
    $category     = strtolower($params['category'] ?? 'all');
    $minBudget    = isset($params['min_budget']) ? (float) $params['min_budget'] : 0;
    $maxBudget    = isset($params['max_budget']) ? (float) $params['max_budget'] : 999999;
    $duration     = strtolower($params['duration'] ?? 'all');
    $minRating    = isset($params['min_rating']) ? (float) $params['min_rating'] : 0.0;
    $destinationId = isset($params['destination_id']) ? (int) $params['destination_id'] : 0;
    $userId       = isset($params['user_id']) ? (int) $params['user_id'] : 0;
    $sortKey      = $params['sort'] ?? 'popularity';
    $orderBy      = PKG_SORT_MAP[$sortKey] ?? PKG_SORT_MAP['popularity'];

    // Duration bucket -> [min_days, max_days] (max_days = null means "and up")
    $durationRanges = [
        'short'  => [1, 3],
        'medium' => [4, 5],
        'long'   => [6, null],
    ];
    $durMin = $durationRanges[$duration][0] ?? 0;
    $durMax = $durationRanges[$duration][1] ?? 999;

    $sql = "
        SELECT
            p.package_id AS id,
            p.package_name AS name,
            p.slug,
            p.description,
            p.duration_days,
            p.duration_nights,
            p.price_per_person,
            p.max_adults,
            p.max_children,

            (SELECT c.category_name
             FROM package_categories pc2
             JOIN categories c ON c.category_id = pc2.category_id
             WHERE pc2.package_id = p.package_id
             ORDER BY pc2.category_id ASC
             LIMIT 1) AS category,

            (SELECT GROUP_CONCAT(d.destination_name ORDER BY pdst.display_order SEPARATOR ', ')
             FROM package_destinations pdst
             JOIN destinations d ON d.destination_id = pdst.destination_id
             WHERE pdst.package_id = p.package_id) AS destinations_label,

            a.full_name AS agent_name,

            (SELECT s.season_name
             FROM package_seasons ps
             JOIN seasons s ON s.season_id = ps.season_id
             WHERE ps.package_id = p.package_id
             ORDER BY ps.is_best_time DESC
             LIMIT 1) AS best_time,

            -- First few itinerary days for the card hover preview --
            -- day_number::title pairs, ||-separated; the card only shows
            -- the first 3 plus a remaining count, but we fetch them all
            -- here since GROUP_CONCAT can't LIMIT per group.
            (SELECT GROUP_CONCAT(CONCAT(pit.day_number, '::', pit.title) ORDER BY pit.display_order SEPARATOR '||')
             FROM package_itinerary pit
             WHERE pit.package_id = p.package_id) AS itinerary_raw,

            COALESCE((
                SELECT ROUND(AVG(pr.rating), 1)
                FROM package_reviews pr
                WHERE pr.package_id = p.package_id
                  AND pr.status = 'Approved'
            ), 0) AS rating,

            COALESCE((
                SELECT COUNT(*)
                FROM package_reviews pr2
                WHERE pr2.package_id = p.package_id
                  AND pr2.status = 'Approved'
            ), 0) AS review_count,

            COALESCE((
                SELECT pi.image_path
                FROM package_images pi
                WHERE pi.package_id = p.package_id
                  AND pi.status = 'Active'
                ORDER BY pi.image_type = 'Cover' DESC, pi.display_order ASC
                LIMIT 1
            ), 'assets/images/destinations/placeholder.jpg') AS image,

            -- Some package_images rows were seeded with filenames that
            -- don't actually exist on disk (case/typo mismatches). This is
            -- a real, working fallback: the cover photo of the package's
            -- first covered destination, which destinations.php already
            -- proves works. pkg_resolve_image() below picks whichever of
            -- the two actually exists as a file.
            (SELECT di.image_path
             FROM package_destinations pdimg
             JOIN destination_images di ON di.destination_id = pdimg.destination_id AND di.status = 'Active'
             WHERE pdimg.package_id = p.package_id
             ORDER BY pdimg.display_order ASC, di.is_primary DESC, di.display_order ASC
             LIMIT 1) AS fallback_image,

            EXISTS (
                SELECT 1 FROM package_favorites pf
                WHERE pf.package_id = p.package_id AND pf.user_id = ?
            ) AS is_favorited

        FROM packages p
        LEFT JOIN agents a ON a.agent_id = p.agent_id

        WHERE p.status = 'Active'
          AND (? = 'all' OR EXISTS (
                SELECT 1
                FROM package_categories pc
                JOIN categories c ON c.category_id = pc.category_id
                WHERE pc.package_id = p.package_id
                  AND c.category_name LIKE ?
              ))
          AND (? = '' OR p.package_name LIKE ?
                       OR EXISTS (
                            SELECT 1
                            FROM package_destinations pdst2
                            JOIN destinations d2 ON d2.destination_id = pdst2.destination_id
                            WHERE pdst2.package_id = p.package_id
                              AND (d2.destination_name LIKE ? OR d2.city LIKE ?)
                       ))
          AND p.price_per_person BETWEEN ? AND ?
          AND p.duration_days BETWEEN ? AND ?
          AND (? = 0 OR EXISTS (
                SELECT 1 FROM package_destinations pdst3
                WHERE pdst3.package_id = p.package_id
                  AND pdst3.destination_id = ?
              ))

        HAVING rating >= ?
        ORDER BY {$orderBy}
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('Package query prepare failed: ' . mysqli_error($conn));
        return [];
    }

    $categoryLike = $category . '%';
    $qLike        = '%' . $q . '%';

    // 1 int (favorited user) + 6 strings + 2 doubles (budget) + 2 ints (duration)
    // + 2 ints (destination) + 1 double (rating) = 14 params
    mysqli_stmt_bind_param(
        $stmt,
        'issssssddiiiid',
        $userId,         // EXISTS (... pf.user_id = ?)
        $category,       // (? = 'all' ...
        $categoryLike,   // c.category_name LIKE ?
        $q,              // (? = '' ...
        $qLike,          // p.package_name LIKE ?
        $qLike,          // d2.destination_name LIKE ?
        $qLike,          // d2.city LIKE ?
        $minBudget,      // BETWEEN ? (price)
        $maxBudget,      //         AND ?
        $durMin,         // BETWEEN ? (duration)
        $durMax,         //         AND ?
        $destinationId,  // (? = 0 ...
        $destinationId,  // pdst3.destination_id = ?
        $minRating       // HAVING rating >= ?
    );

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['image'] = pkg_resolve_image($row['image'] ?? null, $row['fallback_image'] ?? null);
        unset($row['fallback_image']); // internal-only, not needed by callers
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

/** Picks whichever candidate image path actually exists as a file on
 *  disk — package_images' own path first, then the destination-photo
 *  fallback, then the shared placeholder. Fixes cards with a broken
 *  image because the seeded package_images filename didn't match what
 *  was actually saved to disk (wrong case, typo, wrong extension). */
function pkg_resolve_image(?string $primary, ?string $fallback): string {
    $placeholder = 'assets/images/destinations/placeholder.jpg';
    foreach ([$primary, $fallback, $placeholder] as $candidate) {
        if (!empty($candidate) && is_file(__DIR__ . '/' . $candidate)) {
            return $candidate;
        }
    }
    return $placeholder;
}

/** Parses the 'itinerary_raw' field (see filter_packages()) into
 *  ['days' => up to 3 ['day_number', 'title'], 'more' => count of any
 *  remaining days] for the card's hover "Itinerary Preview". */
function pkg_itinerary_preview(?string $raw): array {
    if (empty($raw)) return ['days' => [], 'more' => 0];
    $pairs = explode('||', $raw);
    $days = [];
    foreach ($pairs as $pair) {
        [$num, $title] = array_pad(explode('::', $pair, 2), 2, '');
        $days[] = ['day_number' => (int) $num, 'title' => $title];
    }
    $preview = array_slice($days, 0, 3);
    $more = max(0, count($days) - 3);
    return ['days' => $preview, 'more' => $more];
}

<?php
/**
 * nexttrip-db.php
 * ------------------------------------------------------------
 * ONE file that does two jobs:
 *   1. Connects to the smart_tourism database (mysqli)
 *   2. Provides filter_destinations($params) which runs the
 *      search/filter/sort query and returns matching rows
 * ------------------------------------------------------------
 * Used by BOTH:
 *   - search.php   (the AJAX endpoint the page calls while typing)
 *   - destinations.php (the page itself, for the first normal load)
 * ------------------------------------------------------------
 */

require_once 'config/config.php';

/* ---------- 2. SEARCH / FILTER FUNCTION ---------- */

const DEST_SORT_MAP = [
    'popularity'  => 'rating DESC, d.destination_name ASC',
    'rating_desc' => 'rating DESC, d.destination_name ASC',
    'az'          => 'd.destination_name ASC',
    'za'          => 'd.destination_name DESC',
];

/**
 * Applies search + filter + sort params against the database.
 * $params keys: q, category, day_type, crowd, min_rating, sort
 *
 * `category` is matched against categories.category_name with a
 * leading-substring LIKE (e.g. 'beach' matches 'Beaches') so the
 * UI's singular lowercase slugs work without a schema change.
 */
function filter_destinations(array $params): array {
    global $conn;

    $q          = trim($params['q'] ?? '');
    $category   = strtolower($params['category'] ?? 'all');
    $dayType    = ($params['day_type'] ?? 'weekday') === 'weekend' ? 'Weekend' : 'Weekday';
    $crowd      = strtolower($params['crowd'] ?? 'all');
    $minRating  = isset($params['min_rating']) ? (float) $params['min_rating'] : 0.0;
    $sortKey    = $params['sort'] ?? 'popularity';
    $orderBy    = DEST_SORT_MAP[$sortKey] ?? DEST_SORT_MAP['popularity'];

    $sql = "
        SELECT
            d.destination_id AS id,
            d.destination_name AS name,
            CONCAT(d.city, ', ', d.state) AS location,

            (SELECT c.category_name
             FROM destination_categories dc2
             JOIN categories c ON c.category_id = dc2.category_id
             WHERE dc2.destination_id = d.destination_id
             ORDER BY dc2.category_id ASC
             LIMIT 1) AS category,

            COALESCE((
                SELECT ROUND(AVG(dr.rating), 1)
                FROM destination_reviews dr
                WHERE dr.destination_id = d.destination_id
                  AND dr.status = 'Approved'
            ), 0) AS rating,

            (SELECT s.season_name
             FROM destination_seasons ds
             JOIN seasons s ON s.season_id = ds.season_id
             WHERE ds.destination_id = d.destination_id
             ORDER BY ds.is_best_time DESC
             LIMIT 1) AS season,

            COALESCE((
                SELECT di.image_path
                FROM destination_images di
                WHERE di.destination_id = d.destination_id
                  AND di.status = 'Active'
                ORDER BY di.is_primary DESC, di.display_order ASC
                LIMIT 1
            ), 'assets/images/destinations/placeholder.jpg') AS image,

            COALESCE((
                SELECT CASE
                    WHEN ROUND(AVG(CASE dr2.crowd_level
                        WHEN 'Very Low' THEN 1 WHEN 'Low' THEN 2 WHEN 'Medium' THEN 3
                        WHEN 'High' THEN 4 WHEN 'Very High' THEN 5 END)) <= 2 THEN 'Low'
                    WHEN ROUND(AVG(CASE dr2.crowd_level
                        WHEN 'Very Low' THEN 1 WHEN 'Low' THEN 2 WHEN 'Medium' THEN 3
                        WHEN 'High' THEN 4 WHEN 'Very High' THEN 5 END)) = 3 THEN 'Medium'
                    ELSE 'High'
                END
                FROM destination_reviews dr2
                WHERE dr2.destination_id = d.destination_id
                  AND dr2.status = 'Approved'
                  AND dr2.crowd_level IS NOT NULL
                  AND ( (? = 'Weekend' AND DAYOFWEEK(dr2.review_date) IN (1, 7))
                     OR (? = 'Weekday' AND DAYOFWEEK(dr2.review_date) NOT IN (1, 7)) )
            ), 'Low') AS crowd_level

        FROM destinations d

        WHERE d.status = 'Active'
          AND (? = 'all' OR EXISTS (
                SELECT 1
                FROM destination_categories dc
                JOIN categories c ON c.category_id = dc.category_id
                WHERE dc.destination_id = d.destination_id
                  AND c.category_name LIKE ?
              ))
          AND (? = '' OR d.destination_name LIKE ?
                       OR d.city LIKE ?
                       OR d.state LIKE ?)

        HAVING rating >= ?
          AND (? = 'all' OR crowd_level = ?)
        ORDER BY {$orderBy}
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('Destination query prepare failed: ' . mysqli_error($conn));
        return [];
    }

    $categoryLike = $category . '%';
    $qLike        = '%' . $q . '%';

    // 10 strings + 1 double, in the exact order the ? marks appear above
    mysqli_stmt_bind_param(
        $stmt,
        'ssssssssdss',
        $dayType,       // crowd_level subquery: ? = 'Weekend'
        $dayType,       // crowd_level subquery: ? = 'Weekday'
        $category,      // (? = 'all' ...
        $categoryLike,  // c.category_name LIKE ?
        $q,             // (? = '' ...
        $qLike,         // destination_name LIKE ?
        $qLike,         // city LIKE ?
        $qLike,         // state LIKE ?
        $minRating,     // HAVING rating >= ?
        $crowd,         // HAVING (? = 'all' ...
        $crowd          // HAVING crowd_level = ?
    );

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $rows;
}
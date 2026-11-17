<?php
/**
 * place-details-db.php
 * ------------------------------------------------------------
 * Data layer for place-details.php — one function per section of
 * the page, all plain procedural mysqli (matches nexttrip-db.php's
 * convention: mysqli_prepare + bind_param, no PDO, no OOP).
 * ------------------------------------------------------------
 */

require 'config/config.php';

/**
 * Core destination record: name, location, category badge, rating +
 * review count, primary image, entry fee / price, coordinates, and
 * both weekday & weekend crowd levels in one row.
 * Returns null if the id doesn't exist / isn't Active.
 */
function get_destination(int $id): ?array {
    global $conn;

    $sql = "
        SELECT
            d.destination_id   AS id,
            d.destination_name AS name,
            d.city, d.state, d.district,
            CONCAT(d.city, ', ', d.state) AS location,
            d.latitude, d.longitude,
            -- `destinations.entry_fee` now exists in the schema; keep
            -- starting_price NULL since there's still no matching column
            -- for it (packages carry price_per_person instead).
            d.entry_fee AS entry_fee, NULL AS starting_price,

            (SELECT c.category_name
             FROM destination_categories dc2
             JOIN categories c ON c.category_id = dc2.category_id
             WHERE dc2.destination_id = d.destination_id
             ORDER BY dc2.category_id ASC LIMIT 1) AS category,

            COALESCE((SELECT ROUND(AVG(dr.rating), 1)
                      FROM destination_reviews dr
                      WHERE dr.destination_id = d.destination_id
                        AND dr.status = 'Approved'), 0) AS rating,

            (SELECT COUNT(*) FROM destination_reviews dr2
             WHERE dr2.destination_id = d.destination_id
               AND dr2.status = 'Approved') AS review_count,

            (SELECT s.season_name
             FROM destination_seasons ds
             JOIN seasons s ON s.season_id = ds.season_id
             WHERE ds.destination_id = d.destination_id
             ORDER BY ds.is_best_time DESC LIMIT 1) AS best_time,

            (SELECT crowd_level FROM destination_crowd
             WHERE destination_id = d.destination_id AND day_type = 'Weekday') AS crowd_weekday,
            (SELECT crowd_level FROM destination_crowd
             WHERE destination_id = d.destination_id AND day_type = 'Weekend') AS crowd_weekend

        FROM destinations d
        WHERE d.destination_id = ? AND d.status = 'Active'
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { error_log('get_destination prepare failed: ' . mysqli_error($conn)); return null; }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/** All active photos for the carousel, primary first. */
function get_destination_images(int $id): array {
    global $conn;
    $sql = "SELECT image_path, image_title, alt_text
            FROM destination_images
            WHERE destination_id = ? AND status = 'Active'
            ORDER BY is_primary DESC, display_order ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

/** About / History / Importance blocks, keyed by section_type. */
function get_destination_sections(int $id): array {
    global $conn;
    $sql = "SELECT section_type, title, content
            FROM destination_sections
            WHERE destination_id = ? AND status = 'Active'
            ORDER BY display_order ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $sections[$row['section_type']] = $row;
    }
    mysqli_stmt_close($stmt);
    return $sections;
}

/** Activity tags (icon + name), in display order. */
function get_destination_activities(int $id): array {
    global $conn;
    // NOTE: `activities` has no `icon` column in the current schema — the
    // page already falls back to a generic pin emoji per-row, so we only
    // select what actually exists instead of crashing the query.
    $sql = "SELECT a.activity_name
            FROM destination_activities da
            JOIN activities a ON a.activity_id = da.activity_id
            WHERE da.destination_id = ? AND a.status = 'Active'
            ORDER BY da.display_order ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Travel tips list, in display order. */
function get_destination_tips(int $id): array {
    global $conn;
    $sql = "SELECT tip_text FROM destination_tips
            WHERE destination_id = ? AND status = 'Active'
            ORDER BY display_order ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row['tip_text'];
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Nearby hotels linked to this destination, with real distance (km)
 *  computed via the Haversine formula against the destination's coords. */
function get_destination_hotels(int $id): array {
    global $conn;
    $sql = "SELECT h.hotel_name, h.price_per_night, h.rating, h.image_path,
                   (6371 * ACOS(
                       COS(RADIANS(d.latitude))  * COS(RADIANS(h.latitude)) *
                       COS(RADIANS(h.longitude) - RADIANS(d.longitude)) +
                       SIN(RADIANS(d.latitude))  * SIN(RADIANS(h.latitude))
                   )) AS distance_km
            FROM destination_hotels dh
            JOIN hotels h ON h.hotel_id = dh.hotel_id
            JOIN destinations d ON d.destination_id = dh.destination_id
            WHERE dh.destination_id = ? AND h.status = 'Active'
            ORDER BY distance_km ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Nearby restaurants linked to this destination, with real distance (km). */
function get_destination_restaurants(int $id): array {
    global $conn;
    $sql = "SELECT r.restaurant_name, r.cuisine, r.rating, r.image_path,
                   (6371 * ACOS(
                       COS(RADIANS(d.latitude))  * COS(RADIANS(r.latitude)) *
                       COS(RADIANS(r.longitude) - RADIANS(d.longitude)) +
                       SIN(RADIANS(d.latitude))  * SIN(RADIANS(r.latitude))
                   )) AS distance_km
            FROM destination_restaurants dr
            JOIN restaurants r ON r.restaurant_id = dr.restaurant_id
            JOIN destinations d ON d.destination_id = dr.destination_id
            WHERE dr.destination_id = ? AND r.status = 'Active'
            ORDER BY distance_km ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Approved reviews, most recent first, with the reviewer's name. */
function get_destination_reviews(int $id): array {
    global $conn;
    $sql = "SELECT dr.rating, dr.review_title, dr.review_text, dr.review_date AS created_at,
                   CONCAT(u.first_name, ' ', u.last_name) AS reviewer_name
            FROM destination_reviews dr
            JOIN users u ON u.user_id = dr.user_id
            WHERE dr.destination_id = ? AND dr.status = 'Approved'
            ORDER BY dr.review_date DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * A friendly "6:00 AM – 6:30 PM" (or "Open 24 hours") string built
 * from the seeded generic (day_of_week = 0) opening-hours row.
 */
function get_destination_hours_text(int $id): string {
    global $conn;
    $sql = "SELECT open_time, close_time, is_closed
            FROM destination_opening_hours
            WHERE destination_id = ? ORDER BY day_of_week ASC LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) return 'Not available';
    if ((int) $row['is_closed'] === 1) return 'Closed';

    $open  = $row['open_time'];
    $close = $row['close_time'];
    if ($open === '00:00:00' && $close >= '23:59:00') return 'Open 24 hours';

    return date('g:i A', strtotime($open)) . ' – ' . date('g:i A', strtotime($close));
}
/** Approved reviews, most recent first — includes reviewer's name plus
 *  the crowd-report fields (visit_time / crowd_level) added by
 *  destination_reviews_crowd_updates.sql. */
function get_destination_reviews_with_crowd(int $id): array {
    global $conn;
    $sql = "SELECT dr.rating, dr.review_text, dr.visit_time, dr.crowd_level,
                   dr.review_date AS created_at,
                   CONCAT(u.first_name, ' ', u.last_name) AS reviewer_name
            FROM destination_reviews dr
            JOIN users u ON u.user_id = dr.user_id
            WHERE dr.destination_id = ? AND dr.status = 'Approved'
            ORDER BY dr.review_date DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Whether the given logged-in user has already favourited this destination. */
function is_destination_favorited(int $userId, int $destId): bool {
    global $conn;
    if ($userId <= 0) return false;
    $sql = "SELECT favorite_id FROM favorites WHERE user_id = ? AND destination_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $destId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool) $row;
}

/**
 * Crowd analytics built from user-submitted reviews (visit_time +
 * crowd_level), grouped by hour. Returns:
 *   hours          -> [0 => avg_level_or_null, ..., 23 => ...] (1-5 scale)
 *   sample_count   -> total crowd reports used
 *   peak_hour      -> "HH:00" with the highest average level, or null
 *   quietest_hour  -> "HH:00" with the lowest average level, or null
 *   average_label  -> 'Low' / 'Medium' / 'High' etc. across all reports
 * Returns sample_count = 0 (and everything else null/empty) when there
 * isn't enough data yet, so the page can show a friendly message
 * instead of an empty/broken chart.
 */
function get_destination_crowd_analytics(int $id): array {
    global $conn;

    $levelToNum = ['Very Low' => 1, 'Low' => 2, 'Medium' => 3, 'High' => 4, 'Very High' => 5];
    $numToLevel = array_flip($levelToNum);

    $sql = "SELECT HOUR(visit_time) AS hr, crowd_level, rating
            FROM destination_reviews
            WHERE destination_id = ?
              AND status = 'Approved'
              AND visit_time IS NOT NULL
              AND crowd_level IS NOT NULL";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $byHour = []; // hr => [sum, count]
    $hourCounts = array_fill(0, 24, 0);
    $levelCounts = []; // 'Very Low' => n, ...
    $allValues = [];
    $ratingSum = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $hr = (int) $row['hr'];
        $val = $levelToNum[$row['crowd_level']] ?? null;
        if ($val === null) continue;
        if (!isset($byHour[$hr])) $byHour[$hr] = [0, 0];
        $byHour[$hr][0] += $val;
        $byHour[$hr][1] += 1;
        $hourCounts[$hr]++;
        $levelCounts[$row['crowd_level']] = ($levelCounts[$row['crowd_level']] ?? 0) + 1;
        $allValues[] = $val;
        $ratingSum += (float) $row['rating'];
    }
    mysqli_stmt_close($stmt);

    $sampleCount = count($allValues);
    if ($sampleCount === 0) {
        return [
            'hours' => array_fill(0, 24, null),
            'hour_counts' => array_fill(0, 24, 0),
            'sample_count' => 0,
            'peak_hour' => null,
            'quietest_hour' => null,
            'average_label' => null,
            'most_reported' => null,
            'avg_rating' => null,
        ];
    }

    $hours = [];
    $bestHour = null; $bestVal = -1;
    $worstHour = null; $worstVal = 6;
    for ($h = 0; $h < 24; $h++) {
        if (isset($byHour[$h])) {
            $avg = $byHour[$h][0] / $byHour[$h][1];
            $hours[$h] = round($avg, 2);
            if ($avg > $bestVal) { $bestVal = $avg; $bestHour = $h; }
            if ($avg < $worstVal) { $worstVal = $avg; $worstHour = $h; }
        } else {
            $hours[$h] = null;
        }
    }

    $overallAvg = array_sum($allValues) / $sampleCount;
    $roundedAvg = max(1, min(5, (int) round($overallAvg)));

    // "Most Reported" — the single crowd level travellers reported most often.
    arsort($levelCounts);
    $mostReported = array_key_first($levelCounts);

    return [
        'hours' => $hours,
        'hour_counts' => $hourCounts,
        'sample_count' => $sampleCount,
        'peak_hour' => $bestHour !== null ? sprintf('%02d:00', $bestHour) : null,
        'quietest_hour' => $worstHour !== null ? sprintf('%02d:00', $worstHour) : null,
        'average_label' => $numToLevel[$roundedAvg] ?? null,
        'most_reported' => $mostReported,
        'avg_rating' => round($ratingSum / $sampleCount, 1),
    ];
}

/**
 * Review-weighted crowd result for ONE specific hour of ONE destination.
 * This is the single calculation used both by the initial page render and
 * by ajax/crowd_level.php, so the page and the AJAX update can never
 * disagree.
 *
 * Weighting (documented, not hardcoded per-category):
 *   Very Low = 20, Low = 40, Medium = 60, High = 80, Very High = 100
 * `get_destination_crowd_analytics()` already stores, per hour, the
 * average of these reports on a 1-5 scale (Very Low=1 ... Very High=5).
 * Multiplying that average by 20 gives the exact same result as
 * averaging the 20/40/60/80/100 weights directly — so the percentage
 * reflects the real distribution of that hour's reports (e.g. 2 Very Low
 * + 3 Low + 4 Medium + 1 High -> a genuine weighted average, not a fixed
 * "Medium = 60%" constant).
 *
 * Matching: destination_reviews.visit_time is bucketed by HOUR(), so
 * 9:45 / 10:00 / 10:15 / 10:30 all count toward the "10:00" bucket
 * regardless of minute — no exact-minute match is required.
 *
 * Fallback: if the exact hour has no reports yet, walks outward to the
 * nearest hour that does (marked 'estimated' => true) instead of
 * inventing a number. Only returns status 'no_data' when this
 * destination has no crowd reports at all, at any hour.
 */
function get_crowd_result_for_hour(int $destId, int $hour, ?array $crowdAnalytics = null): array {
    $crowdAnalytics = $crowdAnalytics ?? get_destination_crowd_analytics($destId);
    $numToLevel = [1 => 'Very Low', 2 => 'Low', 3 => 'Medium', 4 => 'High', 5 => 'Very High'];

    if ((int) $crowdAnalytics['sample_count'] === 0) {
        return ['status' => 'no_data'];
    }

    for ($offset = 0; $offset <= 12; $offset++) {
        $candidates = $offset === 0 ? [$hour] : [$hour - $offset, $hour + $offset];
        foreach ($candidates as $h) {
            $h = (($h % 24) + 24) % 24;
            if ($crowdAnalytics['hours'][$h] !== null) {
                $rawAvg     = (float) $crowdAnalytics['hours'][$h]; // 1.0 - 5.0
                $percentage = (int) round($rawAvg * 20);            // weighted 20-100 scale
                $levelNum   = max(1, min(5, (int) round($rawAvg)));
                return [
                    'status' => 'ok',
                    'hour' => $h,
                    'percentage' => $percentage,
                    'level' => $numToLevel[$levelNum],
                    'count' => $crowdAnalytics['hour_counts'][$h],
                    'estimated' => $offset > 0,
                ];
            }
            if ($offset === 0) break;
        }
    }
    return ['status' => 'no_data'];
}

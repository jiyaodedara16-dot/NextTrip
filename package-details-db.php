<?php
/**
 * package-details-db.php
 * ------------------------------------------------------------
 * Data layer for package-details.php. Mirrors the naming and
 * mysqli-procedural style of place-details-db.php, one function
 * per section of the page.
 * ------------------------------------------------------------
 */

require_once 'config/config.php';

/** Core package record: name, description, duration, price, category,
 *  best-time season, computed rating/review_count, and agent + agency
 *  contact details (item #7 — real DB fields, not placeholders). */
function get_package(int $id): ?array {
    global $conn;

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
            p.agent_id,

            (SELECT c.category_name
             FROM package_categories pc
             JOIN categories c ON c.category_id = pc.category_id
             WHERE pc.package_id = p.package_id
             ORDER BY pc.category_id ASC
             LIMIT 1) AS category,

            (SELECT s.season_name
             FROM package_seasons ps
             JOIN seasons s ON s.season_id = ps.season_id
             WHERE ps.package_id = p.package_id
             ORDER BY ps.is_best_time DESC
             LIMIT 1) AS best_time,

            COALESCE((
                SELECT ROUND(AVG(pr.rating), 1)
                FROM package_reviews pr
                WHERE pr.package_id = p.package_id AND pr.status = 'Approved'
            ), 0) AS rating,

            COALESCE((
                SELECT COUNT(*)
                FROM package_reviews pr2
                WHERE pr2.package_id = p.package_id AND pr2.status = 'Approved'
            ), 0) AS review_count,

            a.full_name AS agent_name,
            a.phone AS agent_phone,
            a.city AS agent_city,
            a.profile_image AS agent_photo,
            acc.email AS agent_email

        FROM packages p
        LEFT JOIN agents a ON a.agent_id = p.agent_id
        LEFT JOIN accounts acc ON acc.account_id = a.account_id

        WHERE p.package_id = ? AND p.status = 'Active'
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/** Cover image first, then gallery, in display_order. Drops any row
 *  whose image_path doesn't actually exist on disk (seeded filenames
 *  that don't match what was really saved — wrong case, typo, wrong
 *  extension). If that leaves nothing, falls back to the covered
 *  destinations' own real photos so the carousel is never empty. */
function get_package_images(int $id): array {
    global $conn;
    $sql = "SELECT image_path, image_title, alt_text
            FROM package_images
            WHERE package_id = ? AND status = 'Active'
            ORDER BY image_type = 'Cover' DESC, display_order ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if (is_file(__DIR__ . '/' . $row['image_path'])) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);

    if (!empty($rows)) return $rows;

    // Fallback: one real photo per covered destination.
    $sql2 = "SELECT di.image_path,
                    d.destination_name AS image_title,
                    d.destination_name AS alt_text
             FROM package_destinations pd
             JOIN destinations d ON d.destination_id = pd.destination_id
             JOIN destination_images di ON di.destination_id = pd.destination_id AND di.status = 'Active'
             WHERE pd.package_id = ?
             ORDER BY pd.display_order ASC, di.is_primary DESC, di.display_order ASC";
    $stmt2 = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($stmt2, 'i', $id);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    $seen = [];
    while ($row = mysqli_fetch_assoc($result2)) {
        // One photo per destination is plenty for a fallback carousel —
        // skip extra gallery shots of a destination we've already used.
        if (isset($seen[$row['image_title']])) continue;
        $seen[$row['image_title']] = true;
        if (is_file(__DIR__ . '/' . $row['image_path'])) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt2);

    return $rows;
}

/** Places covered — item #5: each with its own destination_id, name and
 *  cover image, ordered for the horizontal tappable strip. */
function get_package_destinations(int $id): array {
    global $conn;
    $sql = "
        SELECT
            d.destination_id AS id,
            d.destination_name AS name,
            CONCAT(d.city, ', ', d.state) AS location,
            COALESCE((
                SELECT di.image_path
                FROM destination_images di
                WHERE di.destination_id = d.destination_id AND di.status = 'Active'
                ORDER BY di.is_primary DESC, di.display_order ASC
                LIMIT 1
            ), 'assets/images/destinations/placeholder.jpg') AS image
        FROM package_destinations pd
        JOIN destinations d ON d.destination_id = pd.destination_id
        WHERE pd.package_id = ?
        ORDER BY pd.display_order ASC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function get_package_inclusions(int $id): array {
    global $conn;
    $sql = "SELECT item_name FROM package_inclusions WHERE package_id = ? AND status = 'Active' ORDER BY display_order ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row['item_name'];
    mysqli_stmt_close($stmt);
    return $rows;
}

function get_package_exclusions(int $id): array {
    global $conn;
    $sql = "SELECT item_name FROM package_exclusions WHERE package_id = ? AND status = 'Active' ORDER BY display_order ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row['item_name'];
    mysqli_stmt_close($stmt);
    return $rows;
}

function get_package_itinerary(int $id): array {
    global $conn;
    $sql = "SELECT day_number, title, description FROM package_itinerary WHERE package_id = ? ORDER BY display_order ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function get_package_reviews(int $id): array {
    global $conn;
    $sql = "SELECT pr.rating, pr.review_title, pr.review_text, pr.review_date,
                   CONCAT(u.first_name, ' ', u.last_name) AS reviewer_name
            FROM package_reviews pr
            JOIN users u ON u.user_id = pr.user_id
            WHERE pr.package_id = ? AND pr.status = 'Approved'
            ORDER BY pr.review_date DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function is_package_favorited(int $userId, int $pkgId): bool {
    global $conn;
    if ($userId <= 0) return false;
    $sql = "SELECT favorite_id FROM package_favorites WHERE user_id = ? AND package_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $pkgId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool) $row;
}

/** Renders n solid/empty stars as HTML — same helper pattern as
 *  place-details-db.php's pd_stars(), namespaced for packages. */
function pkgd_stars(float $rating): void {
    $full = (int) round($rating);
    for ($i = 1; $i <= 5; $i++) {
        echo '<i class="bi ' . ($i <= $full ? 'bi-star-fill' : 'bi-star') . '"></i>';
    }
}

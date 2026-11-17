<?php
/**
 * contact-db.php
 * ------------------------------------------------------------
 * Small helper used by contact.php and ajax/submit_enquiry.php:
 * looks up a logged-in visitor's name / email / phone (users +
 * accounts) so the contact form and the feedback mini-forms can
 * skip asking someone who's already signed in to retype them.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';

/** Returns ['name','email','phone'] for a user_id, or null if not found. */
function get_contact_profile(int $userId): ?array {
    global $conn;
    if ($userId <= 0) return null;

    $sql = "SELECT u.first_name, u.last_name, u.phone, a.email
            FROM users u
            JOIN accounts a ON a.account_id = u.account_id
            WHERE u.user_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) return null;
    return [
        'name'  => trim($row['first_name'] . ' ' . $row['last_name']),
        'email' => $row['email'],
        'phone' => $row['phone'],
    ];
}

/** "Rahul Sharma" -> "Rahul S." — reviews on the public board never show a full surname. */
function format_reviewer_name(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    if (!$parts || $parts[0] === '') return 'Traveller';
    if (count($parts) === 1) return $parts[0];
    return $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
}

/** Approved site reviews for the Contact page board, newest first. */
function get_site_reviews(int $limit = 12): array {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT review_id, reviewer_name, rating, review_text, created_at
                                   FROM site_reviews
                                   WHERE status = 'Approved'
                                   ORDER BY created_at DESC, review_id DESC
                                   LIMIT ?");
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) {
        $r['display_name'] = format_reviewer_name($r['reviewer_name']);
        $r['date_label']   = date('M Y', strtotime($r['created_at']));
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/** ['avg' => 4.7, 'count' => 6] across ALL approved site reviews. */
function get_site_review_stats(): array {
    global $conn;
    $res = mysqli_query($conn, "SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS review_count
                                FROM site_reviews WHERE status = 'Approved'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return [
        'avg'   => (float) ($row['avg_rating'] ?? 0),
        'count' => (int) ($row['review_count'] ?? 0),
    ];
}

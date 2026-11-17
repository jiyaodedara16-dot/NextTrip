<?php
/**
 * ajax/submit_enquiry.php
 * ------------------------------------------------------------
 * POST handler for every form on contact.php:
 *   - main contact form                 -> `enquiries`
 *   - suggest a feature                 -> `enquiries`
 *   - suggest a destination             -> `enquiries`
 *   - report a problem                  -> `enquiries`
 *   - share feedback (rating + text)    -> `site_reviews`
 *     (shown on the Contact page's reviews board; see
 *      site_reviews_migration.sql)
 *
 * Guests are welcome (user_id is nullable in both tables); a
 * logged-in visitor's name/email/phone come from their account
 * instead of the form (contact.php hides those fields for them).
 *
 * Validation failures return HTTP 422 with `details` = a list of
 * error codes; contact.js maps each code to the field it belongs
 * to and shows the message right under that field.
 * ------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../contact-db.php';

$type = trim($_POST['type'] ?? '');
$allowedTypes = ['main', 'feature', 'destination', 'feedback', 'problem'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'invalid_type']);
    exit;
}

$userId  = (int) ($_SESSION['user_id'] ?? 0);
$profile = $userId > 0 ? get_contact_profile($userId) : null;

$name  = $profile['name']  ?? trim($_POST['name']  ?? '');
$email = $profile['email'] ?? trim($_POST['email'] ?? '');
$phone = trim($profile['phone'] ?? ($_POST['phone'] ?? ''));

$errors  = [];
$subject = '';
$message = '';

if ($name === '') $errors[] = 'name_required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'invalid_email';
if ($phone !== '' && !preg_match('/^[+\d][\d\s\-()]{6,19}$/', $phone)) $errors[] = 'invalid_phone';

switch ($type) {
    case 'main':
        $subjectMap = [
            'plan_trip'    => 'Plan My Trip',
            'booking_help' => 'Booking Help',
            'general'      => 'General Enquiry',
            'report_issue' => 'Report an Issue',
        ];
        $enquiryType = $_POST['enquiry_type'] ?? 'general';
        $subject = $subjectMap[$enquiryType] ?? 'General Enquiry';
        $message = trim($_POST['message'] ?? '');
        if ($message === '') $errors[] = 'message_required';
        break;

    case 'feature':
        $subject = 'Feature Suggestion';
        $message = trim($_POST['message'] ?? '');
        if ($message === '') $errors[] = 'message_required';
        break;

    case 'destination':
        $destName  = trim($_POST['destination_name'] ?? '');
        $destState = trim($_POST['destination_state'] ?? '');
        $why       = trim($_POST['message'] ?? '');
        if ($destName === '') $errors[] = 'destination_name_required';
        if ($destState === '') $errors[] = 'destination_state_required';
        if ($why === '') $errors[] = 'message_required';
        $subject = 'Destination Suggestion: ' . $destName . ', ' . $destState;
        $message = "Destination: {$destName}\nState: {$destState}\n\nWhy travellers should visit:\n{$why}";
        break;

    case 'feedback':
        $rating  = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
        $message = trim($_POST['message'] ?? '');
        if ($rating < 1 || $rating > 5) $errors[] = 'invalid_rating';
        if ($message === '') $errors[] = 'message_required';
        if (mb_strlen($message) > 500) $errors[] = 'message_too_long';
        break;

    case 'problem':
        $subject = 'Problem Report';
        $message = trim($_POST['message'] ?? '');
        if ($message === '') $errors[] = 'message_required';
        break;
}

if ($type !== 'feedback' && mb_strlen($message) > 2000) $errors[] = 'message_too_long';

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'validation_failed', 'details' => array_values(array_unique($errors))]);
    exit;
}

$userIdParam = $userId > 0 ? $userId : null;

/* ---- Share Feedback -> site_reviews ---------------------------------- */
if ($type === 'feedback') {
    $stmt = mysqli_prepare($conn, "INSERT INTO site_reviews (user_id, reviewer_name, email, rating, review_text, status)
                                   VALUES (?, ?, ?, ?, ?, 'Approved')");
    mysqli_stmt_bind_param($stmt, 'issis', $userIdParam, $name, $email, $rating, $message);

    if (!mysqli_stmt_execute($stmt)) {
        error_log('submit_enquiry (review) insert failed: ' . mysqli_stmt_error($stmt));
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'db_error']);
        mysqli_stmt_close($stmt);
        exit;
    }
    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'review'  => [
            'display_name' => format_reviewer_name($name),
            'rating'       => $rating,
            'review_text'  => $message,
            'date_label'   => date('M Y'),
        ],
        'stats'   => get_site_review_stats(),
    ]);
    exit;
}

/* ---- Everything else -> enquiries ------------------------------------ */
$subject = mb_substr($subject, 0, 150); // matches enquiries.subject varchar(150)

$stmt = mysqli_prepare($conn, "INSERT INTO enquiries (user_id, full_name, phone, email, subject, message, status)
                                VALUES (?, ?, ?, ?, ?, ?, 'New')");
$phoneParam = $phone !== '' ? $phone : null;
mysqli_stmt_bind_param($stmt, 'isssss', $userIdParam, $name, $phoneParam, $email, $subject, $message);

if (!mysqli_stmt_execute($stmt)) {
    error_log('submit_enquiry insert failed: ' . mysqli_stmt_error($stmt));
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_error']);
    mysqli_stmt_close($stmt);
    exit;
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true]);

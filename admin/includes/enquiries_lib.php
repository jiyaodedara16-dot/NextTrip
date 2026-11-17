<?php
/* admin/includes/enquiries_lib.php — helpers for the Contact Enquiries page. */

/* Statuses the admin sees. "New" -> "Seen" happens automatically when an enquiry is opened.
   The older values are kept so historical records still display correctly. */
const ENQ_STATUSES = ['New', 'Seen', 'In Progress', 'Resolved', 'Closed'];
/* Statuses offered in the filter. Older values stay in ENQ_STATUSES so old records still display. */
const ENQ_FILTER_STATUSES = ['New', 'Seen'];

/* Enquiry types submitted by the Contact page (stored in enquiries.enquiry_type). */
const ENQ_MAIN_TYPES = [
    'plan_trip'    => 'Plan My Trip',
    'booking_help' => 'Booking Help',
    'general'      => 'General Enquiry',
    'report_issue' => 'Report an Issue',
];
const ENQ_FILTER_TYPES = ENQ_MAIN_TYPES + [
    'destination' => 'Destination Suggestion',
    'feature'     => 'Feature Suggestion',
];

function enq_type_label($subject) {
    $s = trim((string) $subject);
    return $s !== '' ? $s : 'General Enquiry';
}

function enq_status_class($status) {
    return ['New' => 'enq-new', 'Seen' => 'enq-seen', 'In Progress' => 'enq-progress',
            'Resolved' => 'enq-resolved', 'Closed' => 'enq-closed'][$status] ?? 'enq-new';
}

/* Prepared SELECT. $types is a mysqli type string such as "ssi". */
function enq_fetch($sql, $types = '', array $params = []) {
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('[enquiries] prepare failed: ' . mysqli_error($conn));
        return [];
    }
    if ($types !== '') {
        $refs = [];
        foreach ($params as $k => $v) $refs[$k] = &$params[$k];
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt, $types], $refs));
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

/* Prepared INSERT/UPDATE. Returns affected rows, or -1 on error. */
function enq_exec($sql, $types = '', array $params = []) {
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('[enquiries] prepare failed: ' . mysqli_error($conn));
        return -1;
    }
    if ($types !== '') {
        $refs = [];
        foreach ($params as $k => $v) $refs[$k] = &$params[$k];
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt, $types], $refs));
    }
    $ok = mysqli_stmt_execute($stmt);
    $affected = $ok ? mysqli_stmt_affected_rows($stmt) : -1;
    if (!$ok) error_log('[enquiries] execute failed: ' . mysqli_error($conn));
    mysqli_stmt_close($stmt);
    return $affected;
}
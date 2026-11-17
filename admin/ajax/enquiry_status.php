<?php
/* POST id, status, csrf — save a new status for one enquiry. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/enquiries_lib.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id     = require_post_id();
$status = (string) ($_POST['status'] ?? '');
if (!in_array($status, ENQ_STATUSES, true)) json_fail('Please choose a valid status.', 422);

$exists = enq_fetch("SELECT enquiry_id FROM enquiries WHERE enquiry_id = ? LIMIT 1", 'i', [$id]);
if (!$exists) json_fail('This enquiry could not be found.', 404);

if (enq_exec("UPDATE enquiries SET status = ? WHERE enquiry_id = ?", 'si', [$status, $id]) < 0) {
    json_fail('The status could not be saved. Please try again.', 500);
}

json_out(['success' => true, 'status' => $status, 'message' => 'Status updated to ' . $status . '.']);
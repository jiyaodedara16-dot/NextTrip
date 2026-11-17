<?php
/* POST id — marks a New enquiry as Seen. Called only when the admin explicitly opens the enquiry. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/enquiries_lib.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id = require_post_id();
$n = enq_exec("UPDATE enquiries SET status = 'Seen', is_read = 1, updated_at = NOW()
               WHERE enquiry_id = ? AND status = 'New'", 'i', [$id]);
if ($n < 0) json_fail('The enquiry could not be updated. Please try again.', 500);

$row = enq_fetch("SELECT status FROM enquiries WHERE enquiry_id = ? LIMIT 1", 'i', [$id]);
if (!$row) json_fail('This enquiry could not be found.', 404);

json_out(['success' => true, 'status' => $row[0]['status'], 'changed' => $n > 0]);
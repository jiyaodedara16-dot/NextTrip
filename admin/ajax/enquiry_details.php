<?php
/* GET id — read-only. Returns the enquiry and its reply history. Never changes data. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/enquiries_lib.php';
ajax_guard();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) json_fail('Invalid enquiry.', 400);

$rows = enq_fetch("SELECT enquiry_id, full_name, email, phone, subject, message, status, created_at
                   FROM enquiries WHERE enquiry_id = ? LIMIT 1", 'i', [$id]);
if (!$rows) json_fail('This enquiry could not be found. It may have been deleted.', 404);
$e = $rows[0];

$replies = enq_fetch("SELECT reply_id, subject, message, to_email, send_status, created_at
                      FROM enquiry_replies WHERE enquiry_id = ? ORDER BY reply_id DESC", 'i', [$id]);

json_out(['success' => true, 'enquiry' => [
    'enquiry_id'    => (int) $e['enquiry_id'],
    'full_name'     => (string) $e['full_name'],
    'email'         => (string) $e['email'],
    'phone'         => (string) ($e['phone'] ?? ''),
    'type_label'    => enq_type_label($e['subject']),
    'subject'       => (string) ($e['subject'] ?? ''),
    'message'       => (string) $e['message'],
    'status'        => (string) $e['status'],
    'created_label' => fmt_datetime($e['created_at']),
], 'replies' => array_map(function ($r) {
    return ['reply_id' => (int) $r['reply_id'], 'subject' => $r['subject'], 'message' => $r['message'],
            'to_email' => $r['to_email'], 'send_status' => $r['send_status'], 'created_label' => fmt_datetime($r['created_at'])];
}, $replies)]);
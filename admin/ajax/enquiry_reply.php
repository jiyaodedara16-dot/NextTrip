<?php
/* POST id, subject, message, idem, csrf — sends a reply to the enquiry's own email address through SMTP.
   The recipient comes from the database, never from the browser. Duplicate sends are blocked by idem. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/enquiries_lib.php';
require_once __DIR__ . '/../../config/mail_config.php';
require_once __DIR__ . '/../../includes/email-template.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id      = require_post_id();
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = str_replace("\r\n", "\n", trim((string) ($_POST['message'] ?? '')));
$idem    = trim((string) ($_POST['idem'] ?? ''));

if (!preg_match('/^[a-f0-9-]{20,64}$/i', $idem)) json_fail('Your reply form has expired. Close it and open Reply again.', 422);
if ($subject === '' || mb_strlen($subject) > 150) json_fail('Please enter a subject (up to 150 characters).', 422);
if (mb_strlen($message) < 10) json_fail('Please write a reply of at least 10 characters.', 422);
if (mb_strlen($message) > 5000) json_fail('The reply is too long (maximum 5000 characters).', 422);

$rows = enq_fetch("SELECT enquiry_id, full_name, email FROM enquiries WHERE enquiry_id = ? LIMIT 1", 'i', [$id]);
if (!$rows) json_fail('This enquiry could not be found.', 404);
$e = $rows[0];

$to = trim((string) $e['email']);
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) json_fail('The customer email on this enquiry is not valid.', 422);
$from = MAIL_FROM_EMAIL;
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

/* Duplicate protection: a reply with this key already sent or still in progress is refused */
$dup = enq_fetch("SELECT send_status FROM enquiry_replies WHERE idem_key = ? LIMIT 1", 's', [$idem]);
if ($dup) {
    if ($dup[0]['send_status'] === 'Sent') json_fail('This reply was already sent.', 409);
    json_fail('This reply is still being sent. Please wait a moment.', 409);
}

/* Record the attempt first, so a second click cannot send the same email twice */
if (enq_exec("INSERT INTO enquiry_replies (enquiry_id, admin_id, from_email, to_email, subject, message, idem_key, send_status)
              VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')", 'iisssss', [$id, $adminId ?: null, $from, $to, $subject, $message, $idem]) < 0) {
    json_fail('The reply could not be prepared. Please try again.', 500);
}
global $conn;
$replyId = (int) mysqli_insert_id($conn);

$name = (string) $e['full_name'];
$body = '<p style="color:#33443b;font-size:14.5px;line-height:1.7;margin:0 0 12px;">Hi ' . ne($name) . ',</p>'
      . '<div style="color:#33443b;font-size:14.5px;line-height:1.7;">' . nl2br(ne($message)) . '</div>';
$html = email_layout('Reply from NextTrip', $subject, $body);
$text = "Hi $name,\n\n$message\n\n— NextTrip Team";

$sent = send_mail($to, $name, $subject, $html, $text);

/* Keep a copy in the mail log, the same as other system emails */
enq_exec("INSERT INTO mail_log (to_email, subject, body) VALUES (?, ?, ?)", 'sss',
    [$to, $sent ? $subject : $subject . ' [FAILED]', $message]);

if ($sent) {
    enq_exec("UPDATE enquiry_replies SET send_status = 'Sent' WHERE reply_id = ?", 'i', [$replyId]);
    json_out(['success' => true, 'message' => 'Reply sent to ' . $to . '.']);
}

/* Failed: keep the row for history, release the key so the admin can retry with the same message */
enq_exec("UPDATE enquiry_replies SET send_status = 'Failed', idem_key = NULL, error_note = 'SMTP send failed'
          WHERE reply_id = ?", 'i', [$replyId]);
json_fail('The email could not be sent. Your message has been kept, so you can try again.', 502);
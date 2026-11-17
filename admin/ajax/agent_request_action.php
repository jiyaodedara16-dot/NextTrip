<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id     = require_post_id();
$action = $_POST['action'] ?? '';
$ag = row("SELECT ag.agent_id, ag.full_name, ag.status, a.email
           FROM agents ag JOIN accounts a ON a.account_id = ag.account_id WHERE ag.agent_id = $id");
if (!$ag) json_fail('Application not found.', 404);
if ($ag['status'] !== 'Pending') json_fail('This application has already been reviewed.');

if ($action === 'approve') {
    /* The login account is created when the applicant verifies their email at signup,
       so approval only flips the status, stamps the review time and notifies them. */
    if (!q("UPDATE agents SET status = 'Approved', reviewed_at = NOW(), rejection_reason = NULL WHERE agent_id = $id")) {
        json_fail('Could not approve this application.', 500);
    }
    $sent = notify_email($ag['email'], $ag['full_name'], 'Your NextTrip agent application is approved',
        "Congratulations! Your agent application has been approved. You can now sign in with your registered email and password to use the agent portal.");
    json_out(['success' => true, 'message' => 'Agent approved' . ($sent ? ' and notified by email.' : '. The email could not be sent; check the mail settings.')]);
}

if ($action === 'reject') {
    $reason = trim($_POST['reason'] ?? '');
    if (mb_strlen($reason) < 5) json_fail('Please enter a rejection reason of at least 5 characters.');
    $reason = mb_substr($reason, 0, 255);
    if (!q("UPDATE agents SET status = 'Rejected', rejection_reason = " . sql_val($reason) . ", reviewed_at = NOW() WHERE agent_id = $id")) {
        json_fail('Could not reject this application.', 500);
    }
    $sent = notify_email($ag['email'], $ag['full_name'], 'Update on your NextTrip agent application',
        "We're sorry to inform you that your agent application was not approved.\n\nReason: $reason");
    json_out(['success' => true, 'message' => 'Application rejected' . ($sent ? ' and applicant notified by email.' : '. The email could not be sent.')]);
}

json_fail('Unknown action.');

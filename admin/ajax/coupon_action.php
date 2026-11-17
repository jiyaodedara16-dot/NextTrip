<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id     = require_post_id();
$action = $_POST['action'] ?? '';
$c = row("SELECT c.coupon_id, c.code, c.approval_status, c.agent_id, p.package_name, ag.account_id AS agent_acc, ag.full_name, a.email
          FROM coupons c JOIN packages p ON p.package_id = c.package_id
          JOIN agents ag ON ag.agent_id = c.agent_id JOIN accounts a ON a.account_id = ag.account_id
          WHERE c.coupon_id = $id");
if (!$c) json_fail('Coupon not found.', 404);
if ($c['approval_status'] !== 'Pending') json_fail('This coupon has already been decided.');

if ($action === 'approve') {
    if (!q("UPDATE coupons SET approval_status = 'Approved', reviewed_at = NOW(), rejection_reason = NULL WHERE coupon_id = $id")) {
        json_fail('Could not approve this coupon.', 500);
    }
    notify_account($c['agent_acc'], 'coupon_approved', 'Coupon approved',
        "Coupon {$c['code']} for \"{$c['package_name']}\" is approved and live for the selected users.", 'coupons.php');
    $sent = notify_email($c['email'], $c['full_name'], 'Coupon ' . $c['code'] . ' approved',
        "Your coupon {$c['code']} for \"{$c['package_name']}\" has been approved. The selected users can now use it during booking.");
    json_out(['success' => true, 'message' => 'Coupon approved and activated' . ($sent ? '. The agent was notified.' : '.')]);
}

if ($action === 'reject') {
    $reason = trim($_POST['reason'] ?? '');
    if (mb_strlen($reason) < 5) json_fail('Please enter a reason of at least 5 characters.');
    $reason = mb_substr($reason, 0, 255);
    if (!q("UPDATE coupons SET approval_status = 'Rejected', rejection_reason = " . sql_val($reason) . ", reviewed_at = NOW() WHERE coupon_id = $id")) {
        json_fail('Could not reject this coupon.', 500);
    }
    notify_account($c['agent_acc'], 'coupon_rejected', 'Coupon not approved',
        "Coupon {$c['code']} was not approved. Reason: $reason", 'coupons.php');
    json_out(['success' => true, 'message' => 'Coupon rejected. The agent was notified in the panel.']);
}

json_fail('Unknown action.');

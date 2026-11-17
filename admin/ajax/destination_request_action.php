<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id     = require_post_id();
$action = $_POST['action'] ?? '';
$r = row("SELECT dr.*, ag.full_name AS agent_name, a.email AS agent_email, a.account_id
          FROM destination_requests dr
          JOIN agents ag ON ag.agent_id = dr.agent_id
          JOIN accounts a ON a.account_id = ag.account_id
          WHERE dr.request_id = $id");
if (!$r) json_fail('Request not found.', 404);
if ($r['status'] !== 'Pending') json_fail('This request has already been reviewed.');
$linked = (int) ($r['linked_destination_id'] ?? 0);

if ($action === 'approve') {
    begin_tx();
    if ($linked) {
        /* Agent-submitted destination: images, sections, hotels etc. are already saved. Make it live. */
        $ok = q("UPDATE destinations SET status = 'Active', updated_at = NOW() WHERE destination_id = $linked")
           && q("UPDATE destination_requests SET status = 'Approved', reviewed_at = NOW() WHERE request_id = $id");
        $newId = $linked;
    } else {
        /* Older request without a linked destination: create it from the request fields */
        $slug = unique_slug('destinations', slugify($r['destination_name']));
        $ok = q("INSERT INTO destinations (destination_name, slug, city, district, state, country, entry_fee, status, created_by, created_at)
                 VALUES (" . sql_val($r['destination_name']) . ", " . sql_val($slug) . ", " . sql_val($r['city']) . ", " . sql_val($r['district'])
               . ", " . sql_val($r['state']) . ", " . sql_val($r['country'] ?: 'India') . ", " . sql_val($r['entry_fee'])
               . ", 'Active', " . (int) $r['account_id'] . ", NOW())");
        $newId = last_id();
        if ($ok && $r['image_path']) {
            $ok = q("INSERT INTO destination_images (destination_id, image_path, image_title, alt_text, is_primary, display_order, status)
                     VALUES ($newId, " . sql_val($r['image_path']) . ", " . sql_val($r['destination_name']) . ", " . sql_val($r['destination_name']) . ", 1, 1, 'Active')");
        }
        if ($ok && trim((string) $r['description']) !== '') {
            $ok = q("INSERT INTO destination_sections (destination_id, section_type, title, content, display_order, status)
                     VALUES ($newId, 'About', " . sql_val('About ' . $r['destination_name']) . ", " . sql_val($r['description']) . ", 1, 'Active')");
        }
        if ($ok && $r['category_id']) {
            $ok = q("INSERT IGNORE INTO destination_categories (destination_id, category_id) VALUES ($newId, " . (int) $r['category_id'] . ")");
        }
        if ($ok) $ok = q("UPDATE destination_requests SET status = 'Approved', reviewed_at = NOW(), linked_destination_id = $newId WHERE request_id = $id");
    }
    if (!$ok) { rollback_tx(); json_fail('Could not publish this destination. Please try again.', 500); }
    commit_tx();

    notify_account($r['account_id'], 'destination_approved', 'Destination approved',
        "\"{$r['destination_name']}\" has been approved and is now live on NextTrip.", 'destinations.php');
    $sent = notify_email($r['agent_email'], $r['agent_name'], 'Your destination "' . $r['destination_name'] . '" is approved',
        "Good news! \"{$r['destination_name']}\" has been approved and is now live on NextTrip. You can now build packages for it.");
    json_out(['success' => true, 'message' => 'Destination approved and published' . ($sent ? '. The agent was notified.' : '. The agent email could not be sent.')]);
}

if ($action === 'reject') {
    $reason = trim($_POST['reason'] ?? '');
    if (mb_strlen($reason) < 5) json_fail('Please enter a rejection reason of at least 5 characters.');
    $reason = mb_substr($reason, 0, 255);
    begin_tx();
    $ok = q("UPDATE destination_requests SET status = 'Rejected', rejection_reason = " . sql_val($reason) . ", reviewed_at = NOW() WHERE request_id = $id");
    if ($ok && $linked) $ok = q("UPDATE destinations SET status = 'Inactive' WHERE destination_id = $linked");
    if (!$ok) { rollback_tx(); json_fail('Could not reject this request.', 500); }
    commit_tx();

    notify_account($r['account_id'], 'destination_rejected', 'Destination not approved',
        "\"{$r['destination_name']}\" was not approved. Reason: $reason", 'destinations.php');
    $sent = notify_email($r['agent_email'], $r['agent_name'], 'Update on your destination request',
        "Thank you for submitting \"{$r['destination_name']}\". Unfortunately we are unable to publish it right now.\n\nReason: $reason");
    json_out(['success' => true, 'message' => 'Request rejected' . ($sent ? ' and the agent was notified.' : '.')]);
}

json_fail('Unknown action.');
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id     = require_post_id();
$reason = require_reason();

/* Owner = the agent linked to the account that created the destination (admin-made ones have no agent) */
$d = row("SELECT d.destination_name, ag.full_name, a.email
          FROM destinations d
          JOIN agents ag ON ag.account_id = d.created_by
          JOIN accounts a ON a.account_id = ag.account_id
          WHERE d.destination_id = $id");
if (!row("SELECT destination_id FROM destinations WHERE destination_id = $id")) json_fail('Destination not found.', 404);

begin_tx();
$ok = q("UPDATE destination_requests SET linked_destination_id = NULL WHERE linked_destination_id = $id")
   && q("DELETE FROM destination_activities WHERE destination_id = $id")
   && q("DELETE FROM destination_hotels WHERE destination_id = $id")
   && q("DELETE FROM destination_restaurants WHERE destination_id = $id")
   && q("DELETE FROM destination_categories WHERE destination_id = $id")
   && q("DELETE FROM destination_seasons WHERE destination_id = $id")
   && q("DELETE FROM destination_crowd WHERE destination_id = $id")
   && q("DELETE FROM destinations WHERE destination_id = $id");
if (!$ok) { rollback_tx(); json_fail('Could not delete this destination. Please try again.', 500); }
commit_tx();

$sent = null;
if ($d) {
    $sent = notify_email($d['email'], $d['full_name'], 'Your destination "' . $d['destination_name'] . '" was removed',
        "Your destination \"{$d['destination_name']}\" has been removed from NextTrip by our admin team.\n\nReason: $reason\n\nIf you believe this was a mistake, please contact support.");
}
$msg = 'Destination deleted.';
if ($d) $msg .= $sent ? ' The agent has been notified by email.' : ' The agent could not be emailed; check mail settings.';
json_out(['success' => true, 'message' => $msg]);
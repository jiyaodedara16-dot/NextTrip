<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id     = require_post_id();
$reason = require_reason();

$pk = row("SELECT p.package_name, ag.full_name, a.email
           FROM packages p
           JOIN agents ag ON ag.agent_id = p.agent_id
           JOIN accounts a ON a.account_id = ag.account_id
           WHERE p.package_id = $id");
if (!$pk && !row("SELECT package_id FROM packages WHERE package_id = $id")) json_fail('Package not found.', 404);

begin_tx();
$ok = q("DELETE FROM package_destinations WHERE package_id = $id")
   && q("DELETE FROM package_categories WHERE package_id = $id")
   && q("DELETE FROM package_images WHERE package_id = $id")
   && q("DELETE FROM package_inclusions WHERE package_id = $id")
   && q("DELETE FROM package_exclusions WHERE package_id = $id")
   && q("DELETE FROM package_itinerary WHERE package_id = $id")
   && q("DELETE FROM package_seasons WHERE package_id = $id")
   && q("DELETE FROM package_favorites WHERE package_id = $id")
   && q("DELETE FROM package_reviews WHERE package_id = $id")
   && q("DELETE FROM packages WHERE package_id = $id");
if (!$ok) { rollback_tx(); json_fail('Could not delete this package. Please try again.', 500); }
commit_tx();

$msg = 'Package deleted.';
if ($pk) {
    $sent = notify_email($pk['email'], $pk['full_name'], 'Your package "' . $pk['package_name'] . '" was removed',
        "Your package \"{$pk['package_name']}\" has been removed from NextTrip by our admin team.\n\nReason: $reason\n\nIf you believe this was a mistake, please contact support.");
    $msg .= $sent ? ' The agent has been notified by email.' : ' The agent could not be emailed; check mail settings.';
}
json_out(['success' => true, 'message' => $msg]);
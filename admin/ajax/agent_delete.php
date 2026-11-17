<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('Method not allowed.', 405);

$id = require_post_id();
$ag = row("SELECT account_id FROM agents WHERE agent_id = $id");
if (!$ag) json_fail('Agent not found.', 404);
$acc = (int) $ag['account_id'];

/* Destinations are owned by the agent's account (no cascade), so remove them first.
   Deleting the agent then cascades packages, bookings and reviews. */
begin_tx();
$ok = q("UPDATE destination_requests SET linked_destination_id = NULL WHERE agent_id = $id")
   && q("DELETE FROM destination_requests WHERE agent_id = $id")
   && q("DELETE FROM destinations WHERE created_by = $acc")
   && q("DELETE FROM agents WHERE agent_id = $id")
   && q("UPDATE website_settings SET updated_by = NULL WHERE updated_by = $acc")
   && q("DELETE FROM accounts WHERE account_id = $acc");
if (!$ok) { rollback_tx(); json_fail('Could not delete this agent. Please try again.', 500); }
commit_tx();
remove_dir(__DIR__ . '/../../uploads/agents/' . $acc);
json_out(['success' => true, 'message' => 'Agent removed from the platform.']);

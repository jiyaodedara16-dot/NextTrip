<?php
/**
 * ajax/crowd_level.php
 * ------------------------------------------------------------
 * GET { destination_id, hour } -> JSON crowd result for that hour.
 *
 * All calculation happens in get_crowd_result_for_hour() (see
 * place-details-db.php) — this endpoint just validates input, calls it,
 * and returns the result as-is:
 *
 *   { status: "ok", hour, percentage, level, count, estimated }
 *   { status: "no_data" }   -- destination has no crowd reports at all
 *   { status: "error", message }
 * ------------------------------------------------------------
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../place-details-db.php';

$destId = isset($_GET['destination_id']) ? (int) $_GET['destination_id'] : 0;
$hour   = isset($_GET['hour']) ? (int) $_GET['hour'] : -1;

if ($destId <= 0 || $hour < 0 || $hour > 23) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'invalid_parameters']);
    exit;
}

echo json_encode(get_crowd_result_for_hour($destId, $hour));

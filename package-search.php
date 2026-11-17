<?php
/**
 * package-search.php
 * ------------------------------------------------------------
 * The AJAX endpoint. packages.php calls this file in the
 * background every time you type in the search box, click a
 * category, or change a filter — WITHOUT reloading the page.
 * Returns matching packages as JSON. Mirrors search.php.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
include 'packages-db.php';

$params = [
    'q'              => $_GET['q']              ?? '',
    'category'       => $_GET['category']       ?? 'all',
    'min_budget'     => $_GET['min_budget']     ?? '0',
    'max_budget'     => $_GET['max_budget']     ?? '999999',
    'duration'       => $_GET['duration']       ?? 'all',
    'min_rating'     => $_GET['min_rating']     ?? '0.0',
    'sort'           => $_GET['sort']           ?? 'popularity',
    'destination_id' => $_GET['destination_id'] ?? '0',
    'user_id'        => $_SESSION['user_id']    ?? 0,
];

$results = filter_packages($params);

echo json_encode([
    'count'   => count($results),
    'results' => $results,
], JSON_UNESCAPED_SLASHES);

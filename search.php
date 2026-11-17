<?php
/**
 * search.php
 * ------------------------------------------------------------
 * The AJAX endpoint. destinations.php calls this file in the
 * background (via JavaScript fetch) every time you type in the
 * search box, click a category, or change a filter — WITHOUT
 * reloading the page. It returns the matching destinations as JSON.
 * ------------------------------------------------------------
 */

header('Content-Type: application/json; charset=utf-8');
include 'nexttrip-db.php';

$params = [
    'q'          => $_GET['q']          ?? '',
    'category'   => $_GET['category']   ?? 'all',
    'day_type'   => $_GET['day_type']   ?? 'weekday',
    'crowd'      => $_GET['crowd']      ?? 'all',
    'min_rating' => $_GET['min_rating'] ?? '1.0',
    'sort'       => $_GET['sort']       ?? 'popularity',
];

$results = filter_destinations($params);

echo json_encode([
    'count'   => count($results),
    'results' => $results,
], JSON_UNESCAPED_SLASHES);
<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$all   = load_requests();

// One request: api.php?id=1
if (isset($_GET['id'])) {
    $id = (int)input_str($_GET, 'id', '0');
    foreach ($all as $r) {
        if ((int)$r['id'] === $id) {
            echo json_encode(['meta' => api_meta(), 'request' => public_request($r)], $flags);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['meta' => api_meta(), 'error' => 'Request not found'], $flags);
    exit;
}

// List: api.php?q=...&category=...&sort=top|newest
$items = filter_requests(
    $all,
    input_str($_GET, 'q'),
    input_str($_GET, 'category'),
    input_str($_GET, 'sort')
);

echo json_encode([
    'meta'     => api_meta(),
    'total'    => count($all),
    'count'    => count($items),
    'requests' => array_map('public_request', $items),
], $flags);
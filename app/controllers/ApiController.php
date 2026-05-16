<?php

function handle_api(string $route): void
{
    header('Content-Type: application/json');
    if ($route === 'search') {
        echo json_encode(['products' => product_query('trending', ['q' => trim($_GET['q'] ?? ''), 'limit' => 8])]);
        return;
    }
    if ($route === 'quick-view') {
        $product = find_product_by_slug($_GET['slug'] ?? '');
        echo json_encode(['product' => $product]);
        return;
    }
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found']);
}

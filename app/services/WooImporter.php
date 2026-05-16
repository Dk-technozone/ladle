<?php

function normalize_store_url(string $url): string
{
    $url = trim($url);
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return rtrim($url, '/');
}

function import_woocommerce_products(string $storeUrl, string $consumerKey, string $consumerSecret, int $limit, ?int $vendorId, ?int $categoryId): array
{
    if (!filter_var($storeUrl, FILTER_VALIDATE_URL)) {
        return ['status' => 'error', 'message' => 'Valid WooCommerce store URL required.', 'count' => 0];
    }
    if ($consumerKey === '' || $consumerSecret === '') {
        return ['status' => 'error', 'message' => 'Consumer key and secret are required.', 'count' => 0];
    }
    $endpoint = $storeUrl . '/wp-json/wc/v3/products?' . http_build_query([
        'consumer_key' => $consumerKey,
        'consumer_secret' => $consumerSecret,
        'per_page' => max(1, min(50, $limit)),
        'status' => 'publish',
    ]);
    $response = @file_get_contents($endpoint, false, stream_context_create(['http' => ['timeout' => 15, 'header' => "Accept: application/json\r\nUser-Agent: PSP-Marketplace-Importer/3.0\r\n"]]));
    if ($response === false) {
        return ['status' => 'error', 'message' => 'Unable to connect to WooCommerce REST API.', 'count' => 0];
    }
    $products = json_decode($response, true);
    if (!is_array($products)) {
        return ['status' => 'error', 'message' => 'WooCommerce returned invalid JSON.', 'count' => 0];
    }
    $vendorId = $vendorId ?: (int) db()->query('SELECT id FROM vendors ORDER BY id LIMIT 1')->fetchColumn();
    $categoryId = $categoryId ?: (int) db()->query('SELECT id FROM categories ORDER BY sort_order LIMIT 1')->fetchColumn();
    $imported = 0;
    foreach ($products as $product) {
        if (empty($product['name'])) {
            continue;
        }
        $slug = unique_slug('products', $product['slug'] ?? $product['name']);
        $description = trim(strip_tags($product['short_description'] ?: $product['description'] ?: 'Imported WooCommerce digital product.'));
        $stmt = db()->prepare('INSERT OR IGNORE INTO products (vendor_id, category_id, woo_id, name, slug, short_description, full_description, product_type, thumbnail, regular_price, sale_price, live_preview_url, documentation_url, tags, compatible_cms, meta_title, meta_description, total_sales, rating_average, badge, approval_status, status, last_updated, release_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, date("now"), date("now"))');
        $stmt->execute([
            $vendorId,
            $categoryId,
            (int) $product['id'],
            $product['name'],
            $slug,
            excerpt($description, 220),
            trim(strip_tags($product['description'] ?: $description)),
            'Theme',
            $product['images'][0]['src'] ?? 'https://images.unsplash.com/photo-1556742502-ec7c0e9f34b1?auto=format&fit=crop&w=1200&q=80',
            (float) ($product['regular_price'] ?: $product['price'] ?: 0),
            $product['sale_price'] !== '' ? (float) $product['sale_price'] : null,
            $product['permalink'] ?? $storeUrl,
            $product['permalink'] ?? $storeUrl,
            implode(', ', array_filter(array_column($product['categories'] ?? [], 'name'))),
            'WooCommerce, WordPress',
            $product['name'] . ' - Imported Product',
            excerpt($description, 155),
            (int) ($product['total_sales'] ?? 0),
            (float) ($product['average_rating'] ?: 4.8),
            'Woo Import',
            'approved',
            'published',
        ]);
        if ($stmt->rowCount() > 0) {
            $imported++;
        }
    }
    return ['status' => 'success', 'message' => "Imported {$imported} WooCommerce products into marketplace catalog.", 'count' => $imported];
}

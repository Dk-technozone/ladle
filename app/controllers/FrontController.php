<?php

function handle_front(string $route): void
{
    $site = settings();
    if ($route === 'catalog') {
        render('front/catalog', [
            'title' => 'Marketplace Catalog',
            'site' => $site,
            'products' => product_query('trending', ['q' => trim($_GET['q'] ?? ''), 'category' => $_GET['category'] ?? null, 'limit' => 60]),
            'categories' => categories_with_counts(),
            'search' => trim($_GET['q'] ?? ''),
        ]);
        return;
    }
    if ($route === 'product') {
        $product = find_product_by_slug($_GET['slug'] ?? '');
        if (!$product) {
            http_response_code(404);
            render('front/404', ['title' => 'Product not found', 'site' => $site]);
            return;
        }
        render('front/product', [
            'title' => $product['meta_title'] ?: $product['name'],
            'site' => $site,
            'product' => $product,
            'features' => product_features((int) $product['id']),
            'faqs' => product_faqs((int) $product['id']),
            'changelog' => product_changelog((int) $product['id']),
            'related' => product_query('trending', ['category' => $product['category_slug'], 'limit' => 4]),
        ]);
        return;
    }
    render('front/home', [
        'title' => $site['site_name'] ?? APP_NAME,
        'site' => $site,
        'featured' => product_query('featured', ['limit' => 8]),
        'trending' => product_query('trending', ['limit' => 6]),
        'categories' => categories_with_counts(),
        'reviews' => db()->query('SELECT reviews.*, products.name AS product_name FROM reviews LEFT JOIN products ON products.id = reviews.product_id WHERE reviews.status = "approved" ORDER BY reviews.id DESC LIMIT 6')->fetchAll(),
        'stats' => [
            'products' => (int) db()->query('SELECT COUNT(*) FROM products WHERE status = "published"')->fetchColumn(),
            'vendors' => (int) db()->query('SELECT COUNT(*) FROM vendors WHERE status = "approved"')->fetchColumn(),
            'sales' => (int) db()->query('SELECT COALESCE(SUM(total_sales),0) FROM products')->fetchColumn(),
            'downloads' => (int) db()->query('SELECT COUNT(*) FROM downloads')->fetchColumn(),
        ],
    ]);
}

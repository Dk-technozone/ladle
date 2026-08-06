<?php

function handle_front(string $route): void
{
    $site = settings();
    if ($route === 'sitemap') {
        header('Content-Type: application/xml; charset=utf-8');
        $base = strtok((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? ''), '?');
        $root = str_replace('index.php', '', $base);
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach (['home' => 'index.php', 'catalog' => 'index.php?route=catalog'] as $loc) {
            echo '<url><loc>' . e($root . $loc) . "</loc><changefreq>daily</changefreq></url>\n";
        }
        foreach (product_query('latest', ['limit' => 500]) as $product) {
            echo '<url><loc>' . e($root . app_url('product', ['slug' => $product['slug']])) . '</loc><lastmod>' . e(substr($product['updated_at'] ?: $product['created_at'], 0, 10)) . "</lastmod></url>\n";
        }
        echo '</urlset>';
        return;
    }
    if ($route === 'cart') {
        render('front/cart', ['title' => 'Cart', 'site' => $site, 'totals' => cart_totals()]);
        return;
    }
    if ($route === 'checkout') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            try {
                $orderId = create_order_from_cart($_POST['payment_method'] ?? 'manual', trim($_POST['email'] ?? 'guest@example.com'));
                flash('success', 'Order #' . $orderId . ' created. Payment integration is ready for Stripe/Razorpay keys.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect_to(app_url('checkout'));
        }
        render('front/checkout', ['title' => 'Checkout', 'site' => $site, 'totals' => cart_totals()]);
        return;
    }
    if ($route === 'catalog') {
        render('front/catalog', [
            'title' => 'Shop Marketplace',
            'site' => $site,
            'products' => product_query($_GET['sort'] ?? 'trending', ['q' => trim($_GET['q'] ?? ''), 'category' => $_GET['category'] ?? null, 'limit' => 60]),
            'categories' => categories_with_counts(),
            'search' => trim($_GET['q'] ?? ''),
            'coupon' => active_coupon('LAUNCH20'),
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

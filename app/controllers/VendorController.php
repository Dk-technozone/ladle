<?php

function handle_vendor(string $route): void
{
    if ($route === 'login') {
        $current = current_user();
        if ($current && $current['role'] === 'vendor') {
            redirect_to(app_url('dashboard', [], 'vendor'));
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            if (login_user(trim($_POST['email'] ?? ''), $_POST['password'] ?? '') && ($logged = current_user()) && $logged['role'] === 'vendor') {
                flash('success', 'Welcome to your vendor dashboard.');
                redirect_to(app_url('dashboard', [], 'vendor'));
            }
            logout_user();
            flash('error', 'Invalid vendor credentials.');
            redirect_to(app_url('login', [], 'vendor'));
        }
        render('vendor/login', ['title' => 'Vendor Login'], 'auth');
        return;
    }
    if ($route === 'logout') {
        logout_user();
        redirect_to(app_url('login', [], 'vendor'));
    }
    $user = require_role('vendor');
    $vendorId = (int) $user['id'];
    $vendor = db()->prepare('SELECT * FROM vendors WHERE user_id = ?');
    $vendor->execute([$vendorId]);
    $vendor = $vendor->fetch();
    $vendorDbId = (int) $vendor['id'];

    if ($route === 'products') {
        render('vendor/products', ['title' => 'My Products', 'vendor' => $vendor, 'products' => product_query('latest', ['vendor_id' => $vendorDbId, 'limit' => 200])], 'vendor');
        return;
    }
    if ($route === 'product-create' || $route === 'product-edit') {
        $product = null;
        if ($route === 'product-edit') {
            $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND vendor_id = ?');
            $stmt->execute([(int) ($_GET['id'] ?? 0), $vendorDbId]);
            $product = $stmt->fetch();
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $_POST['approval_status'] = 'pending';
            $_POST['status'] = $_POST['status'] ?? 'draft';
            try {
                save_product_from_post($product, $vendorDbId);
                flash('success', 'Product submitted to approval queue.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect_to(app_url('products', [], 'vendor'));
        }
        render('admin/product-form', ['title' => $product ? 'Edit Product' : 'Upload Product', 'product' => $product, 'categories' => categories_with_counts(), 'vendors' => [$vendor], 'vendorMode' => true], 'vendor');
        return;
    }
    if ($route === 'support') {
        render('vendor/support', ['title' => 'Support Tickets', 'tickets' => db()->query('SELECT * FROM support_tickets ORDER BY id DESC LIMIT 30')->fetchAll()], 'vendor');
        return;
    }
    render('vendor/dashboard', [
        'title' => 'Vendor Dashboard',
        'vendor' => $vendor,
        'stats' => [
            'products' => (int) db()->query('SELECT COUNT(*) FROM products WHERE vendor_id = ' . $vendorDbId)->fetchColumn(),
            'sales' => (int) db()->query('SELECT COALESCE(SUM(total_sales),0) FROM products WHERE vendor_id = ' . $vendorDbId)->fetchColumn(),
            'earnings' => (float) $vendor['balance'],
            'pending' => (int) db()->query('SELECT COUNT(*) FROM products WHERE vendor_id = ' . $vendorDbId . ' AND approval_status = "pending"')->fetchColumn(),
        ],
        'products' => product_query('latest', ['vendor_id' => $vendorDbId, 'limit' => 5]),
    ], 'vendor');
}

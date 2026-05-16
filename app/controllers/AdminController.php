<?php

function handle_admin(string $route): void
{
    if ($route === 'login') {
        $current = current_user();
        if ($current && $current['role'] === 'admin') {
            redirect_to(app_url('dashboard', [], 'admin'));
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            if (login_user(trim($_POST['email'] ?? ''), $_POST['password'] ?? '') && ($logged = current_user()) && $logged['role'] === 'admin') {
                flash('success', 'Welcome to the PSP admin panel.');
                redirect_to(app_url('dashboard', [], 'admin'));
            }
            logout_user();
            flash('error', 'Invalid admin credentials.');
            redirect_to(app_url('login', [], 'admin'));
        }
        render('admin/login', ['title' => 'Admin Login'], 'auth');
        return;
    }
    if ($route === 'logout') {
        logout_user();
        redirect_to(app_url('login', [], 'admin'));
    }

    $admin = require_role('admin');
    if ($route === 'products') {
        render('admin/products', ['title' => 'Product Management', 'products' => product_query('latest', ['limit' => 200]), 'admin' => $admin], 'admin');
        return;
    }
    if ($route === 'product-create' || $route === 'product-edit') {
        $product = null;
        if ($route === 'product-edit') {
            $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([(int) ($_GET['id'] ?? 0)]);
            $product = $stmt->fetch();
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            try {
                save_product_from_post($product);
                flash('success', 'Product saved with full marketplace metadata.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect_to(app_url('products', [], 'admin'));
        }
        render('admin/product-form', ['title' => $product ? 'Edit Product' : 'Add Product', 'product' => $product, 'categories' => categories_with_counts(), 'vendors' => db()->query('SELECT * FROM vendors ORDER BY store_name')->fetchAll()], 'admin');
        return;
    }
    if ($route === 'approve-product') {
        verify_csrf();
        db()->prepare('UPDATE products SET approval_status = ?, status = ? WHERE id = ?')->execute([$_POST['approval_status'] ?? 'approved', $_POST['status'] ?? 'published', (int) $_POST['id']]);
        flash('success', 'Product moderation status updated.');
        redirect_to(app_url('products', [], 'admin'));
    }
    if ($route === 'vendors') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            db()->prepare('UPDATE vendors SET status = ?, verified = ? WHERE id = ?')->execute([$_POST['status'], isset($_POST['verified']) ? 1 : 0, (int) $_POST['id']]);
            flash('success', 'Vendor verification updated.');
            redirect_to(app_url('vendors', [], 'admin'));
        }
        render('admin/vendors', ['title' => 'Vendor Management', 'vendors' => db()->query('SELECT vendors.*, users.email FROM vendors LEFT JOIN users ON users.id = vendors.user_id ORDER BY vendors.id DESC')->fetchAll()], 'admin');
        return;
    }
    if ($route === 'orders') {
        render('admin/orders', ['title' => 'Orders & Licenses', 'orders' => db()->query('SELECT orders.*, products.name AS product_name, vendors.store_name FROM orders LEFT JOIN products ON products.id = orders.product_id LEFT JOIN vendors ON vendors.id = orders.vendor_id ORDER BY orders.id DESC')->fetchAll()], 'admin');
        return;
    }
    if ($route === 'marketing') {
        render('admin/marketing', ['title' => 'Coupons & Homepage', 'coupons' => db()->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll()], 'admin');
        return;
    }
    if ($route === 'seo') {
        render('admin/seo', ['title' => 'SEO Manager', 'settings' => settings()], 'admin');
        return;
    }
    if ($route === 'system') {
        render('admin/system', ['title' => 'System Tools', 'logs' => db()->query('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 20')->fetchAll()], 'admin');
        return;
    }
    if ($route === 'import') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $storeUrl = normalize_store_url($_POST['store_url'] ?? '');
            $result = import_woocommerce_products($storeUrl, trim($_POST['consumer_key'] ?? ''), trim($_POST['consumer_secret'] ?? ''), (int) ($_POST['limit'] ?? 12), (int) ($_POST['vendor_id'] ?? 0), (int) ($_POST['category_id'] ?? 0));
            db()->prepare('INSERT INTO imports (store_url, imported_count, status, message) VALUES (?, ?, ?, ?)')->execute([$storeUrl, $result['count'], $result['status'], $result['message']]);
            flash($result['status'] === 'success' ? 'success' : 'error', $result['message']);
            redirect_to(app_url('import', [], 'admin'));
        }
        render('admin/import', ['title' => 'WooCommerce Extractor', 'imports' => db()->query('SELECT * FROM imports ORDER BY id DESC LIMIT 20')->fetchAll(), 'vendors' => db()->query('SELECT * FROM vendors ORDER BY store_name')->fetchAll(), 'categories' => categories_with_counts()], 'admin');
        return;
    }
    render('admin/dashboard', [
        'title' => 'Admin Dashboard',
        'admin' => $admin,
        'stats' => [
            'products' => (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            'pending' => (int) db()->query('SELECT COUNT(*) FROM products WHERE approval_status = "pending"')->fetchColumn(),
            'vendors' => (int) db()->query('SELECT COUNT(*) FROM vendors')->fetchColumn(),
            'orders' => (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        ],
        'recent' => product_query('latest', ['limit' => 6]),
    ], 'admin');
}

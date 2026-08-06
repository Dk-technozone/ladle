<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0775, true);
    }
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    migrate($pdo);
    seed($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT "customer",
        avatar TEXT,
        status TEXT NOT NULL DEFAULT "active",
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS vendors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        store_name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        headline TEXT,
        verified INTEGER NOT NULL DEFAULT 0,
        commission_rate REAL NOT NULL DEFAULT 20,
        balance REAL NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT "pending",
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        parent_id INTEGER,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        icon TEXT NOT NULL DEFAULT "bx-category",
        gradient TEXT DEFAULT "from-cyan-400 to-fuchsia-500",
        description TEXT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(parent_id) REFERENCES categories(id) ON DELETE SET NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        vendor_id INTEGER,
        category_id INTEGER,
        woo_id INTEGER UNIQUE,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        short_description TEXT NOT NULL,
        full_description TEXT,
        product_type TEXT NOT NULL DEFAULT "Theme",
        tags TEXT,
        version TEXT DEFAULT "1.0.0",
        release_date TEXT,
        last_updated TEXT,
        status TEXT NOT NULL DEFAULT "published",
        approval_status TEXT NOT NULL DEFAULT "approved",
        badge TEXT DEFAULT "Featured",
        regular_price REAL NOT NULL DEFAULT 0,
        sale_price REAL,
        extended_license_price REAL,
        subscription_price REAL,
        offer_expiry TEXT,
        is_free INTEGER NOT NULL DEFAULT 0,
        live_preview_url TEXT,
        admin_demo_url TEXT,
        documentation_url TEXT,
        video_preview_url TEXT,
        download_preview_url TEXT,
        thumbnail TEXT NOT NULL,
        featured_banner TEXT,
        product_logo TEXT,
        screenshot_gallery TEXT,
        mobile_screenshots TEXT,
        gif_preview TEXT,
        video_showcase TEXT,
        compatible_browsers TEXT,
        compatible_cms TEXT,
        php_version TEXT,
        framework TEXT,
        responsive INTEGER NOT NULL DEFAULT 1,
        retina_ready INTEGER NOT NULL DEFAULT 1,
        dark_mode INTEGER NOT NULL DEFAULT 1,
        rtl_support INTEGER NOT NULL DEFAULT 0,
        multi_language INTEGER NOT NULL DEFAULT 0,
        meta_title TEXT,
        meta_description TEXT,
        focus_keywords TEXT,
        og_image TEXT,
        structured_data TEXT,
        canonical_url TEXT,
        robots_control TEXT DEFAULT "index,follow",
        main_download_file TEXT,
        additional_files TEXT,
        file_size TEXT,
        download_limit INTEGER DEFAULT 10,
        license_type TEXT DEFAULT "single-domain",
        support_expiry TEXT,
        auto_update_token TEXT,
        total_sales INTEGER NOT NULL DEFAULT 0,
        total_views INTEGER NOT NULL DEFAULT 0,
        wishlist_count INTEGER NOT NULL DEFAULT 0,
        rating_average REAL NOT NULL DEFAULT 5,
        reviews_count INTEGER NOT NULL DEFAULT 0,
        trending_score REAL NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
        FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS product_features (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, feature TEXT NOT NULL, sort_order INTEGER DEFAULT 0, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS product_changelog (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, version TEXT, notes TEXT NOT NULL, released_at TEXT, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS product_faqs (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, question TEXT NOT NULL, answer TEXT NOT NULL, sort_order INTEGER DEFAULT 0, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY AUTOINCREMENT, order_number TEXT NOT NULL UNIQUE, user_id INTEGER, product_id INTEGER, vendor_id INTEGER, amount REAL NOT NULL, payment_method TEXT DEFAULT "manual", payment_status TEXT DEFAULT "paid", coupon_code TEXT, tax_amount REAL DEFAULT 0, invoice_path TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS downloads (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, product_id INTEGER, user_id INTEGER, download_token TEXT NOT NULL, downloaded_count INTEGER DEFAULT 0, expires_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS licenses (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, product_id INTEGER, user_id INTEGER, license_key TEXT NOT NULL UNIQUE, license_type TEXT, domain_limit INTEGER DEFAULT 1, status TEXT DEFAULT "active", expires_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, user_id INTEGER, rating INTEGER NOT NULL, comment TEXT, verified INTEGER DEFAULT 1, status TEXT DEFAULT "approved", created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS wishlists (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, product_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, product_id))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS carts (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT NOT NULL, product_id INTEGER NOT NULL, quantity INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS payment_integrations (id INTEGER PRIMARY KEY AUTOINCREMENT, provider TEXT NOT NULL, public_key TEXT, secret_key TEXT, webhook_secret TEXT, status TEXT DEFAULT "disabled", created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS ai_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, prompt TEXT NOT NULL, response TEXT, provider TEXT DEFAULT "openai", status TEXT DEFAULT "logged", created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS telegram_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER, message TEXT NOT NULL, status TEXT DEFAULT "queued", created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS coupons (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, discount_type TEXT DEFAULT "percent", discount_value REAL NOT NULL, expires_at TEXT, usage_limit INTEGER, used_count INTEGER DEFAULT 0, status TEXT DEFAULT "active")');
    $pdo->exec('CREATE TABLE IF NOT EXISTS withdrawals (id INTEGER PRIMARY KEY AUTOINCREMENT, vendor_id INTEGER NOT NULL, amount REAL NOT NULL, method TEXT, status TEXT DEFAULT "pending", requested_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS support_tickets (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, vendor_id INTEGER, product_id INTEGER, subject TEXT NOT NULL, message TEXT NOT NULL, status TEXT DEFAULT "open", created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT NOT NULL, context TEXT, ip_address TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS imports (id INTEGER PRIMARY KEY AUTOINCREMENT, store_url TEXT NOT NULL, imported_count INTEGER DEFAULT 0, status TEXT NOT NULL, message TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
}

function seed(PDO $pdo): void
{
    $defaults = [
        'site_name' => APP_NAME,
        'site_tagline' => 'ThemeForest + PSP UI marketplace for themes, scripts, UI systems, and creator economy assets.',
        'hero_title' => 'Build next-generation websites with futuristic digital products.',
        'hero_subtitle' => 'Explore colorful themes, premium PHP scripts, UI kits, SaaS templates, plugins, and website services crafted for creators, developers, and modern businesses.',
        'primary_color' => '#6C4DFF',
        'accent_color' => '#00D4FF',
        'support_email' => 'support@psp.local',
        'notice_text' => 'Limited launch offer: use coupon LAUNCH20 for 20% off premium themes.',
        'stripe_public_key' => '',
        'stripe_secret_key' => '',
        'razorpay_key_id' => '',
        'razorpay_key_secret' => '',
        'openai_api_key' => '',
        'telegram_bot_token' => '',
        'telegram_chat_id' => '',
        'social_telegram' => 'https://t.me/example',
        'social_youtube' => '#',
    ];
    $setting = $pdo->prepare('INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($defaults as $key => $value) {
        $setting->execute([$key, $value]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $user = $pdo->prepare('INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)');
        $user->execute(['Admin Pro', ADMIN_EMAIL, password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT), 'admin', 'active']);
        $user->execute(['Neon Vendor', VENDOR_EMAIL, password_hash(VENDOR_PASSWORD, PASSWORD_DEFAULT), 'vendor', 'active']);
        $vendorUserId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO vendors (user_id, store_name, slug, headline, verified, status, balance) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$vendorUserId, 'NeonLab Studio', 'neonlab-studio', 'Premium PSP-style themes, scripts, and UI kits.', 1, 'approved', 2840]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        $categories = [
            ['WordPress Themes', 'wordpress-themes', 'bxl-wordpress', 'from-blue-400 to-purple-500', 'Movie, OTT, magazine, SEO, affiliate, and WooCommerce themes', 1],
            ['Blogger Templates', 'blogger-templates', 'bxl-blogger', 'from-orange-400 to-pink-500', 'Movie, anime, news, minimal, and Adsense-ready Blogger templates', 2],
            ['PHP Scripts', 'php-scripts', 'bx-code-curly', 'from-cyan-400 to-blue-600', 'Auto scraper, OTT clone, movie database, SaaS tools, and download systems', 3],
            ['Plugins', 'plugins', 'bx-plug', 'from-lime-400 to-cyan-500', 'Auto post generator, IMDb fetcher, player, and download button plugins', 4],
            ['UI Kits', 'ui-kits', 'bx-palette', 'from-fuchsia-400 to-purple-600', 'Colorful UI systems, components, and dashboards', 5],
            ['SaaS Apps', 'saas-apps', 'bx-cloud', 'from-sky-400 to-indigo-600', 'App templates, dashboards, and subscription-ready systems', 6],
            ['Elementor Kits', 'elementor-kits', 'bx-layout', 'from-violet-400 to-pink-500', 'Landing pages, news portals, streaming layouts, and product-selling UI', 7],
            ['Website Services', 'website-services', 'bx-wrench', 'from-amber-400 to-rose-500', 'Cloning, speed optimization, SEO fixing, malware cleanup, and redesign', 8],
        ];
        $stmt = $pdo->prepare('INSERT INTO categories (name, slug, icon, gradient, description, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($categories as $category) {
            $stmt->execute($category);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $vendorId = (int) $pdo->query('SELECT id FROM vendors LIMIT 1')->fetchColumn();
        $categoryIds = [];
        foreach ($pdo->query('SELECT id, slug FROM categories')->fetchAll() as $row) {
            $categoryIds[$row['slug']] = (int) $row['id'];
        }
        $products = [
            ['NovaFlix PSP Movie Pro', 'novaflix-psp-movie-pro', 'Theme', 'wordpress-themes', 'A cinematic OTT WordPress theme with PSP neon cards, episode grids, watchlist UI, and schema-ready movie pages.', 'https://images.unsplash.com/photo-1485846234645-a62644f84728?auto=format&fit=crop&w=1200&q=80', 59, 39, 4.9, 1840, 224, 'Best Seller'],
            ['AutoStream PHP Scraper', 'autostream-php-scraper', 'Script', 'php-scripts', 'A fast PHP scraper and movie database script with protected downloads, SEO pages, and cron-ready imports.', 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=1200&q=80', 79, 59, 4.8, 1210, 188, 'Hot Script'],
            ['Blogger Anime Neon', 'blogger-anime-neon', 'Template', 'blogger-templates', 'A colorful anime Blogger template with clean ad slots, fast mobile UX, and premium typography.', 'https://images.unsplash.com/photo-1612036782180-6f0b6cd846fe?auto=format&fit=crop&w=1200&q=80', 29, 19, 4.7, 812, 76, 'New'],
            ['Elementor SaaS Orbit Kit', 'elementor-saas-orbit-kit', 'UI Kit', 'elementor-kits', 'A high-converting Elementor kit for SaaS landing pages, pricing, dashboards, testimonials, and app CTAs.', 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80', 69, 49, 5.0, 2205, 340, 'Premium'],
            ['RankPilot SEO Plugin', 'rankpilot-seo-plugin', 'Plugin', 'plugins', 'An SEO helper plugin concept with metadata, schema snippets, internal links, and AI keyword suggestions.', 'https://images.unsplash.com/photo-1562577309-4932fdd64cd1?auto=format&fit=crop&w=1200&q=80', 45, 35, 4.9, 1504, 129, 'SEO Ready'],
            ['Creator Dashboard UI Kit', 'creator-dashboard-ui-kit', 'UI Kit', 'ui-kits', 'A PSP gaming-inspired admin dashboard UI kit with charts, glass sidebars, neon widgets, and mobile panels.', 'https://images.unsplash.com/photo-1558655146-9f40138edfeb?auto=format&fit=crop&w=1200&q=80', 39, 29, 4.8, 968, 154, '3D UI'],
        ];
        $insert = $pdo->prepare('INSERT INTO products (vendor_id, category_id, name, slug, product_type, short_description, full_description, thumbnail, featured_banner, regular_price, sale_price, extended_license_price, subscription_price, live_preview_url, admin_demo_url, documentation_url, video_preview_url, screenshot_gallery, compatible_browsers, compatible_cms, php_version, framework, meta_title, meta_description, focus_keywords, main_download_file, file_size, total_sales, total_views, wishlist_count, rating_average, reviews_count, trending_score, badge, last_updated, release_date, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, date("now"), date("now", "-45 days"), ?)');
        foreach ($products as $product) {
            [$name, $slug, $type, $categorySlug, $short, $image, $regular, $sale, $rating, $sales, $wishlist, $badge] = $product;
            $insert->execute([$vendorId, $categoryIds[$categorySlug] ?? null, $name, $slug, $type, $short, $short . "\n\nIncludes installation guide, SEO fields, license support, responsive layouts, and conversion-focused sections.", $image, $image, $regular, $sale, $regular * 3, 9, '#preview', '#admin-demo', '#docs', '#video', $image . ',' . $image, 'Chrome, Firefox, Safari, Edge', 'WordPress, Blogger, PHP', '8.1+', 'Tailwind / Vanilla PHP', $name . ' - PSP Marketplace', excerpt($short, 155), strtolower(str_replace(' ', ', ', $name)), '/downloads/' . $slug . '.zip', '24 MB', $sales, $sales * 12, $wishlist, $rating, max(8, (int) ($sales / 80)), ($sales * 1.4) + ($wishlist * 2), $badge, strtolower($type . ', premium, psp, neon')]);
            $productId = (int) $pdo->lastInsertId();
            foreach (['Fast loading', 'SEO ready', 'Mobile optimized', 'Ads ready', 'Dark mode', 'One click install', 'AI optimized', 'Accessibility ready'] as $i => $feature) {
                $pdo->prepare('INSERT INTO product_features (product_id, feature, sort_order) VALUES (?, ?, ?)')->execute([$productId, $feature, $i]);
            }
            $pdo->prepare('INSERT INTO product_changelog (product_id, version, notes, released_at) VALUES (?, ?, ?, date("now"))')->execute([$productId, '1.0.0', 'Initial premium marketplace release with documentation and demo assets.']);
            $pdo->prepare('INSERT INTO product_faqs (product_id, question, answer, sort_order) VALUES (?, ?, ?, ?)')->execute([$productId, 'Is documentation included?', 'Yes, every product includes setup documentation and support notes.', 1]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM coupons')->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO coupons (code, discount_type, discount_value, expires_at, usage_limit, status) VALUES (?, ?, ?, date("now", "+60 days"), ?, ?)')->execute(['LAUNCH20', 'percent', 20, 500, 'active']);
        $pdo->prepare('INSERT INTO coupons (code, discount_type, discount_value, expires_at, usage_limit, status) VALUES (?, ?, ?, date("now", "+30 days"), ?, ?)')->execute(['SAVE10', 'fixed', 10, 200, 'active']);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn() === 0) {
        $productId = (int) $pdo->query('SELECT id FROM products LIMIT 1')->fetchColumn();
        $pdo->prepare('INSERT INTO reviews (product_id, rating, comment, verified) VALUES (?, ?, ?, ?)')->execute([$productId, 5, 'Premium look, fast loading, and exactly the futuristic marketplace style I wanted.', 1]);
    }
}

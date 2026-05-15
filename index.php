<?php
session_start();

const DB_PATH = __DIR__ . '/data/marketplace.sqlite';
const SITE_NAME = 'Ladle Themes';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = dirname(DB_PATH);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    migrate($pdo);
    seed($pdo);

    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS themes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            woo_id INTEGER UNIQUE,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            category TEXT NOT NULL,
            description TEXT NOT NULL,
            image_url TEXT NOT NULL,
            demo_url TEXT,
            price REAL NOT NULL DEFAULT 0,
            rating REAL NOT NULL DEFAULT 5,
            sales INTEGER NOT NULL DEFAULT 0,
            badge TEXT DEFAULT "Featured",
            source_url TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS imports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_url TEXT NOT NULL,
            imported_count INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
}

function seed(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM themes')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $themes = [
        ['NovaFlix Movie Pro', 'novaflix-movie-pro', 'Movie Themes', 'A cinematic streaming layout with episode grids, schema-ready movie pages, and blazing performance.', 'https://images.unsplash.com/photo-1485846234645-a62644f84728?auto=format&fit=crop&w=1200&q=80', '#preview', 49, 4.9, 1840, 'Best Seller'],
        ['PulseNews Agency', 'pulsenews-agency', 'News Themes', 'Editorial WordPress magazine design with sticky monetization blocks and instant article discovery.', 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1200&q=80', '#preview', 39, 4.8, 1312, 'Trending'],
        ['Blogger Spark', 'blogger-spark', 'Blogger Templates', 'Minimal blogger template crafted for creators who need speed, typography, and ad-ready layouts.', 'https://images.unsplash.com/photo-1499750310107-5fef28a66643?auto=format&fit=crop&w=1200&q=80', '#preview', 29, 4.7, 968, 'New'],
        ['Elementor SaaS Kit', 'elementor-saas-kit', 'Elementor Kits', 'A premium SaaS landing kit with pricing sections, CTA funnels, dashboards, and conversion blocks.', 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80', '#preview', 59, 5.0, 2205, 'Premium'],
        ['Rankify SEO Suite', 'rankify-seo-suite', 'SEO Themes', 'SEO-first affiliate theme with product comparisons, FAQ schema, and Core Web Vitals-friendly markup.', 'https://images.unsplash.com/photo-1562577309-4932fdd64cd1?auto=format&fit=crop&w=1200&q=80', '#preview', 45, 4.9, 1504, 'SEO Ready'],
        ['ShopWave Digital', 'shopwave-digital', 'WooCommerce Themes', 'High-converting product storefront for digital assets, plugins, themes, and creative downloads.', 'https://images.unsplash.com/photo-1556742502-ec7c0e9f34b1?auto=format&fit=crop&w=1200&q=80', '#preview', 69, 5.0, 2840, 'Featured'],
    ];

    $stmt = $pdo->prepare('INSERT INTO themes (title, slug, category, description, image_url, demo_url, price, rating, sales, badge) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($themes as $theme) {
        $stmt->execute($theme);
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function slugify(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    return $slug !== '' ? $slug : 'imported-theme-' . time();
}

function normalize_store_url(string $url): string
{
    $url = trim($url);
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    return rtrim($url, '/');
}

function import_woocommerce_products(PDO $pdo, string $storeUrl, string $consumerKey, string $consumerSecret, int $limit): array
{
    if (!filter_var($storeUrl, FILTER_VALIDATE_URL)) {
        return ['status' => 'error', 'message' => 'Please enter a valid WooCommerce store URL.', 'count' => 0];
    }

    if ($consumerKey === '' || $consumerSecret === '') {
        return ['status' => 'error', 'message' => 'Consumer key and secret are required for the extractor.', 'count' => 0];
    }

    $endpoint = $storeUrl . '/wp-json/wc/v3/products?' . http_build_query([
        'consumer_key' => $consumerKey,
        'consumer_secret' => $consumerSecret,
        'per_page' => max(1, min(50, $limit)),
        'status' => 'publish',
    ]);

    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => "Accept: application/json\r\nUser-Agent: Ladle-WooCommerce-Importer/1.0\r\n",
        ],
    ]);

    $response = file_get_contents($endpoint, false, $context);
    if ($response === false) {
        return ['status' => 'error', 'message' => 'Unable to connect to the WooCommerce API. Check credentials, SSL, and REST API access.', 'count' => 0];
    }

    $products = json_decode($response, true);
    if (!is_array($products)) {
        return ['status' => 'error', 'message' => 'WooCommerce returned an invalid JSON response.', 'count' => 0];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO themes (woo_id, title, slug, category, description, image_url, demo_url, price, rating, sales, badge, source_url, updated_at)
         VALUES (:woo_id, :title, :slug, :category, :description, :image_url, :demo_url, :price, :rating, :sales, :badge, :source_url, CURRENT_TIMESTAMP)
         ON CONFLICT(woo_id) DO UPDATE SET
            title = excluded.title,
            slug = excluded.slug,
            category = excluded.category,
            description = excluded.description,
            image_url = excluded.image_url,
            demo_url = excluded.demo_url,
            price = excluded.price,
            rating = excluded.rating,
            sales = excluded.sales,
            badge = excluded.badge,
            source_url = excluded.source_url,
            updated_at = CURRENT_TIMESTAMP'
    );

    $imported = 0;
    foreach ($products as $product) {
        if (!is_array($product) || empty($product['name'])) {
            continue;
        }

        $category = $product['categories'][0]['name'] ?? 'WooCommerce Themes';
        $image = $product['images'][0]['src'] ?? 'https://images.unsplash.com/photo-1556742502-ec7c0e9f34b1?auto=format&fit=crop&w=1200&q=80';
        $price = (float) ($product['price'] ?: $product['regular_price'] ?: 0);
        $rating = (float) ($product['average_rating'] ?: 4.8);
        $sales = (int) ($product['total_sales'] ?? 0);
        $description = trim(strip_tags($product['short_description'] ?: $product['description'] ?: 'Premium imported WooCommerce product ready for marketplace display.'));

        $stmt->execute([
            ':woo_id' => (int) $product['id'],
            ':title' => $product['name'],
            ':slug' => slugify($product['slug'] ?? $product['name']),
            ':category' => $category,
            ':description' => substr($description, 0, 220),
            ':image_url' => $image,
            ':demo_url' => $product['permalink'] ?? null,
            ':price' => $price,
            ':rating' => max(0, min(5, $rating)),
            ':sales' => $sales,
            ':badge' => 'Woo Import',
            ':source_url' => $product['permalink'] ?? $storeUrl,
        ]);
        $imported++;
    }

    return ['status' => 'success', 'message' => "Imported {$imported} WooCommerce products into the SQLite marketplace.", 'count' => $imported];
}

$pdo = db();
$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'import') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $notice = ['status' => 'error', 'message' => 'Security token expired. Refresh and try again.'];
    } else {
        $storeUrl = normalize_store_url($_POST['store_url'] ?? '');
        $result = import_woocommerce_products(
            $pdo,
            $storeUrl,
            trim($_POST['consumer_key'] ?? ''),
            trim($_POST['consumer_secret'] ?? ''),
            (int) ($_POST['limit'] ?? 12)
        );
        $log = $pdo->prepare('INSERT INTO imports (store_url, imported_count, status, message) VALUES (?, ?, ?, ?)');
        $log->execute([$storeUrl, $result['count'], $result['status'], $result['message']]);
        $notice = $result;
    }
}

$themes = $pdo->query('SELECT * FROM themes ORDER BY sales DESC, rating DESC LIMIT 8')->fetchAll();
$categories = $pdo->query('SELECT category, COUNT(*) AS total FROM themes GROUP BY category ORDER BY total DESC, category ASC')->fetchAll();
$themeCount = (int) $pdo->query('SELECT COUNT(*) FROM themes')->fetchColumn();
$salesCount = (int) $pdo->query('SELECT COALESCE(SUM(sales), 0) FROM themes')->fetchColumn();
$lastImport = $pdo->query('SELECT * FROM imports ORDER BY id DESC LIMIT 1')->fetch();
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A premium PHP SQLite marketplace for WordPress themes, Elementor kits, SEO templates, Blogger layouts, and WooCommerce product imports.">
    <meta name="keywords" content="PHP marketplace script, WooCommerce importer, WordPress themes, ThemeForest alternative, SQLite ecommerce">
    <meta name="robots" content="index,follow">
    <meta property="og:title" content="<?= e(SITE_NAME) ?> - Premium Theme Marketplace Script">
    <meta property="og:description" content="Modern dark marketplace UI with WooCommerce product extractor, SQLite storage, Tailwind CSS, and premium conversion sections.">
    <meta property="og:type" content="website">
    <title><?= e(SITE_NAME) ?> - Premium PHP Theme Marketplace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#6C4DFF',
                        secondary: '#9B7BFF',
                        accent: '#00D4FF',
                        ink: '#0F1117',
                        card: '#171A22',
                        muted: '#B9C0D0'
                    },
                    fontFamily: {
                        heading: ['Poppins', 'sans-serif'],
                        body: ['Inter', 'sans-serif']
                    },
                    boxShadow: {
                        glow: '0 0 40px rgba(108,77,255,.35)',
                        card: '0 24px 80px rgba(0,0,0,.35)'
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: Inter, sans-serif; background: #0F1117; color: #fff; }
        .glass { background: rgba(23,26,34,.74); border: 1px solid rgba(255,255,255,.08); backdrop-filter: blur(18px); }
        .gradient-text { background: linear-gradient(135deg,#fff 0%,#9B7BFF 55%,#00D4FF 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .blob { filter: blur(80px); opacity: .58; animation: float 9s ease-in-out infinite; }
        .reveal { opacity: 0; transform: translateY(28px); transition: .8s ease; }
        .reveal.show { opacity: 1; transform: translateY(0); }
        @keyframes float { 0%,100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(24px,-28px,0) scale(1.08); } }
        .light body, body.light { background: #f7f8ff; color: #111827; }
        body.light .dark-card { background: rgba(255,255,255,.86); color: #111827; border-color: rgba(15,17,23,.08); }
        body.light .muted-text { color: #4b5563; }
    </style>
</head>
<body class="overflow-x-hidden selection:bg-primary/40">
    <div class="pointer-events-none fixed inset-0 z-0">
        <div class="blob absolute -top-28 left-4 h-80 w-80 rounded-full bg-primary/50"></div>
        <div class="blob absolute right-0 top-40 h-96 w-96 rounded-full bg-accent/30" style="animation-delay: -3s"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(108,77,255,.16),transparent_34%),linear-gradient(180deg,rgba(15,17,23,.15),#0F1117_62%)]"></div>
    </div>

    <header class="sticky top-0 z-50 border-b border-white/10 bg-ink/75 backdrop-blur-2xl">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 lg:px-8" aria-label="Main navigation">
            <a href="#home" class="flex items-center gap-3" aria-label="Ladle Themes home">
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-primary to-accent shadow-glow"><i class='bx bxs-layer text-2xl'></i></span>
                <span class="font-heading text-xl font-black tracking-tight">Ladle<span class="text-accent">Themes</span></span>
            </a>
            <div class="hidden items-center gap-8 text-sm font-semibold text-muted lg:flex">
                <a class="hover:text-white" href="#themes">Themes</a>
                <a class="hover:text-white" href="#categories">Categories</a>
                <a class="hover:text-white" href="#importer">Woo Importer</a>
                <a class="hover:text-white" href="#pricing">Why Us</a>
            </div>
            <div class="hidden items-center gap-3 md:flex">
                <label class="relative" aria-label="Search marketplace">
                    <i class='bx bx-search absolute left-4 top-1/2 -translate-y-1/2 text-muted'></i>
                    <input class="w-56 rounded-full border border-white/10 bg-white/5 py-3 pl-11 pr-4 text-sm outline-none transition focus:border-accent" placeholder="Search themes...">
                </label>
                <button id="modeToggle" class="grid h-11 w-11 place-items-center rounded-full border border-white/10 bg-white/5" aria-label="Toggle dark and light mode"><i class='bx bx-sun text-xl'></i></button>
                <a href="#login" class="rounded-full px-5 py-3 text-sm font-bold text-white/85 hover:text-white">Login</a>
                <a href="#importer" class="rounded-full bg-gradient-to-r from-primary to-accent px-5 py-3 text-sm font-black text-white shadow-glow transition hover:-translate-y-0.5">Start Selling</a>
            </div>
            <a href="#importer" class="rounded-full bg-gradient-to-r from-primary to-accent px-4 py-3 text-sm font-bold md:hidden">Import</a>
        </nav>
    </header>

    <main class="relative z-10" id="home">
        <section class="mx-auto grid max-w-7xl gap-10 px-4 pb-20 pt-16 lg:grid-cols-[1.05fr_.95fr] lg:px-8 lg:pb-28 lg:pt-24">
            <div class="reveal">
                <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-bold text-muted">
                    <i class='bx bxs-bolt-circle text-accent'></i> Premium PHP + SQLite marketplace script
                </div>
                <h1 class="font-heading text-5xl font-black leading-[1.02] tracking-tight md:text-7xl">
                    Build a <span class="gradient-text">ThemeForest-style</span> marketplace with WooCommerce imports.
                </h1>
                <p class="mt-7 max-w-2xl text-lg leading-8 text-muted muted-text">A modern WBThemes-inspired selling experience for WordPress themes, movie scripts, Blogger templates, Elementor kits, and SEO products. Fast, responsive, SEO-friendly, and ready to extract WooCommerce product data into SQLite.</p>
                <div class="mt-9 flex flex-col gap-4 sm:flex-row">
                    <a href="#themes" class="group rounded-2xl bg-gradient-to-r from-primary via-secondary to-accent px-7 py-4 text-center font-black text-white shadow-glow transition hover:-translate-y-1">Explore Themes <i class='bx bx-right-arrow-alt align-middle text-xl transition group-hover:translate-x-1'></i></a>
                    <a href="#importer" class="rounded-2xl border border-white/10 bg-white/5 px-7 py-4 text-center font-black text-white transition hover:border-accent hover:bg-accent/10">WooCommerce Extractor</a>
                </div>
                <dl class="mt-10 grid max-w-xl grid-cols-3 gap-4">
                    <div class="glass dark-card rounded-2xl p-5"><dt class="text-sm text-muted muted-text">Products</dt><dd class="counter mt-1 text-3xl font-black" data-target="<?= $themeCount ?>">0</dd></div>
                    <div class="glass dark-card rounded-2xl p-5"><dt class="text-sm text-muted muted-text">Sales</dt><dd class="counter mt-1 text-3xl font-black" data-target="<?= $salesCount ?>">0</dd></div>
                    <div class="glass dark-card rounded-2xl p-5"><dt class="text-sm text-muted muted-text">Rating</dt><dd class="mt-1 text-3xl font-black">4.9</dd></div>
                </dl>
            </div>
            <div class="reveal relative min-h-[560px]">
                <div class="glass dark-card absolute right-0 top-4 w-full max-w-xl rounded-[2rem] p-4 shadow-card">
                    <div class="overflow-hidden rounded-[1.5rem] border border-white/10 bg-card">
                        <img src="<?= e($themes[0]['image_url'] ?? '') ?>" alt="Featured marketplace theme preview" class="h-72 w-full object-cover opacity-90">
                        <div class="p-6">
                            <div class="flex items-center justify-between"><span class="rounded-full bg-primary/20 px-3 py-1 text-xs font-black text-secondary">Marketplace Showcase</span><span class="text-sm text-accent">★★★★★</span></div>
                            <h2 class="mt-4 font-heading text-3xl font-black">Premium digital storefront</h2>
                            <p class="mt-3 text-muted muted-text">Floating cards, neon CTAs, clean pricing, product ratings, and conversion-first theme previews.</p>
                        </div>
                    </div>
                </div>
                <div class="glass dark-card absolute -left-2 bottom-24 w-72 rounded-3xl p-5 shadow-glow">
                    <div class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/15 text-accent"><i class='bx bx-trending-up text-2xl'></i></span><div><p class="text-sm text-muted muted-text">Conversion lift</p><p class="text-2xl font-black">+38%</p></div></div>
                </div>
                <div class="glass dark-card absolute bottom-4 right-8 w-80 rounded-3xl p-5">
                    <p class="text-sm font-bold text-muted muted-text">Latest Woo Import</p>
                    <p class="mt-2 text-lg font-black"><?= $lastImport ? e($lastImport['message']) : 'Ready to extract products from WooCommerce.' ?></p>
                </div>
            </div>
        </section>

        <?php if ($notice): ?>
            <section class="mx-auto max-w-7xl px-4 lg:px-8">
                <div class="rounded-3xl border <?= $notice['status'] === 'success' ? 'border-emerald-400/30 bg-emerald-400/10' : 'border-rose-400/30 bg-rose-400/10' ?> p-5 font-bold">
                    <?= e($notice['message']) ?>
                </div>
            </section>
        <?php endif; ?>

        <section id="themes" class="mx-auto max-w-7xl px-4 py-20 lg:px-8">
            <div class="reveal mb-10 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div><p class="font-bold text-accent">Featured Themes</p><h2 class="font-heading text-4xl font-black md:text-5xl">Handpicked premium products</h2></div>
                <a href="#importer" class="font-bold text-secondary hover:text-accent">Add more with WooCommerce importer →</a>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                <?php foreach ($themes as $theme): ?>
                    <article class="reveal glass dark-card group overflow-hidden rounded-[18px] shadow-card transition duration-300 hover:-translate-y-2 hover:border-accent/50" itemscope itemtype="https://schema.org/Product">
                        <div class="relative overflow-hidden">
                            <img src="<?= e($theme['image_url']) ?>" alt="<?= e($theme['title']) ?> preview" class="h-52 w-full object-cover transition duration-500 group-hover:scale-110" itemprop="image">
                            <span class="absolute left-4 top-4 rounded-full bg-ink/80 px-3 py-1 text-xs font-black text-accent"><?= e($theme['badge']) ?></span>
                            <span class="absolute right-4 top-4 rounded-full bg-white px-3 py-1 text-xs font-black text-ink">$<?= number_format((float) $theme['price'], 0) ?></span>
                        </div>
                        <div class="p-5">
                            <p class="text-xs font-black uppercase tracking-widest text-secondary"><?= e($theme['category']) ?></p>
                            <h3 class="mt-2 text-xl font-black" itemprop="name"><?= e($theme['title']) ?></h3>
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-muted muted-text" itemprop="description"><?= e($theme['description']) ?></p>
                            <div class="mt-4 flex items-center justify-between text-sm">
                                <span class="text-amber-300">★★★★★ <b class="text-white"><?= number_format((float) $theme['rating'], 1) ?></b></span>
                                <span class="text-muted muted-text"><?= number_format((int) $theme['sales']) ?> sales</span>
                            </div>
                            <div class="mt-5 grid grid-cols-2 gap-3">
                                <a href="<?= e($theme['demo_url'] ?: '#preview') ?>" class="rounded-xl border border-white/10 px-4 py-3 text-center text-sm font-black transition hover:border-accent hover:text-accent">Live Preview</a>
                                <a href="#checkout" class="rounded-xl bg-gradient-to-r from-primary to-secondary px-4 py-3 text-center text-sm font-black text-white">Buy Now</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="categories" class="mx-auto max-w-7xl px-4 py-16 lg:px-8">
            <div class="reveal text-center"><p class="font-bold text-accent">Categories</p><h2 class="font-heading text-4xl font-black md:text-5xl">Everything creators need</h2></div>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                <?php $icons = ['Movie Themes' => 'bx-movie-play', 'News Themes' => 'bx-news', 'Blogger Templates' => 'bxl-blogger', 'Elementor Kits' => 'bx-layout', 'SEO Themes' => 'bx-search-alt-2', 'WooCommerce Themes' => 'bx-cart']; ?>
                <?php foreach ($categories as $category): ?>
                    <a href="#themes" class="reveal glass dark-card rounded-[18px] p-6 text-center transition hover:-translate-y-2 hover:border-accent/50">
                        <i class='bx <?= e($icons[$category['category']] ?? 'bx-category') ?> text-4xl text-accent'></i>
                        <h3 class="mt-4 font-black"><?= e($category['category']) ?></h3>
                        <p class="mt-2 text-sm text-muted muted-text"><?= (int) $category['total'] ?> products</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="pricing" class="mx-auto max-w-7xl px-4 py-20 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                <div class="reveal"><p class="font-bold text-accent">Why Choose Us</p><h2 class="font-heading text-4xl font-black md:text-5xl">Built for trust, speed, SEO, and conversions.</h2><p class="mt-5 text-lg leading-8 text-muted muted-text">Every section is intentionally crafted for high CTR: sticky navigation, clean product cards, structured content, fast SQLite reads, and a direct WooCommerce REST product pipeline.</p></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <?php foreach ([['bx-rocket','Fast loading'],['bx-line-chart','SEO optimized'],['bx-mobile-alt','Mobile responsive'],['bx-slider-alt','Easy customization'],['bx-support','Premium support'],['bx-data','SQLite powered']] as $feature): ?>
                        <div class="reveal glass dark-card rounded-[18px] p-6"><i class='bx <?= e($feature[0]) ?> text-3xl text-accent'></i><h3 class="mt-4 text-xl font-black"><?= e($feature[1]) ?></h3><p class="mt-2 text-sm text-muted muted-text">Professional UX details with minimal dependencies and clean PHP structure.</p></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="importer" class="mx-auto max-w-7xl px-4 py-20 lg:px-8">
            <div class="glass dark-card grid gap-8 rounded-[2rem] p-6 shadow-card md:p-10 lg:grid-cols-[.9fr_1.1fr]">
                <div class="reveal"><p class="font-bold text-accent">WooCommerce Product Extractor</p><h2 class="mt-2 font-heading text-4xl font-black">Import WordPress store products into SQLite.</h2><p class="mt-5 leading-8 text-muted muted-text">Paste your WooCommerce REST API credentials to extract published product titles, prices, categories, images, ratings, sales, and preview links. Imported records instantly appear in the featured marketplace grid.</p><div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-muted muted-text"><b class="text-white">Endpoint used:</b> /wp-json/wc/v3/products with consumer key and secret.</div></div>
                <form method="post" class="reveal grid gap-4" aria-label="WooCommerce importer form">
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label class="grid gap-2 text-sm font-bold">Store URL<input name="store_url" required placeholder="https://example.com" class="rounded-2xl border border-white/10 bg-ink/70 px-4 py-4 outline-none focus:border-accent"></label>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="grid gap-2 text-sm font-bold">Consumer Key<input name="consumer_key" required placeholder="ck_..." class="rounded-2xl border border-white/10 bg-ink/70 px-4 py-4 outline-none focus:border-accent"></label>
                        <label class="grid gap-2 text-sm font-bold">Consumer Secret<input name="consumer_secret" required placeholder="cs_..." class="rounded-2xl border border-white/10 bg-ink/70 px-4 py-4 outline-none focus:border-accent"></label>
                    </div>
                    <label class="grid gap-2 text-sm font-bold">Import Limit<input name="limit" type="number" min="1" max="50" value="12" class="rounded-2xl border border-white/10 bg-ink/70 px-4 py-4 outline-none focus:border-accent"></label>
                    <button class="rounded-2xl bg-gradient-to-r from-primary to-accent px-7 py-4 font-black text-white shadow-glow transition hover:-translate-y-1" type="submit">Extract Products Now</button>
                </form>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-16 lg:px-8">
            <div class="reveal text-center"><p class="font-bold text-accent">Testimonials</p><h2 class="font-heading text-4xl font-black md:text-5xl">Trusted by digital sellers</h2></div>
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                <?php foreach ([['Aarav Mehta','Theme Founder','The UI feels premium and the Woo importer saved hours of manual product entry.'],['Sara Khan','WP Seller','Clean layout, strong CTA rhythm, and mobile cards look like a top marketplace.'],['Dev Patel','Agency Owner','SQLite keeps the script simple while the landing page still feels enterprise-grade.']] as $review): ?>
                    <figure class="reveal glass dark-card rounded-[18px] p-6"><div class="mb-5 flex text-amber-300">★★★★★</div><blockquote class="text-lg font-semibold leading-8">“<?= e($review[2]) ?>”</blockquote><figcaption class="mt-6 flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-full bg-gradient-to-br from-primary to-accent font-black"><?= e(substr($review[0], 0, 1)) ?></span><span><b><?= e($review[0]) ?></b><small class="block text-muted muted-text"><?= e($review[1]) ?></small></span></figcaption></figure>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-20 lg:px-8">
            <div class="reveal overflow-hidden rounded-[2rem] bg-gradient-to-r from-primary via-secondary to-accent p-10 text-center shadow-glow md:p-16">
                <h2 class="font-heading text-4xl font-black md:text-6xl">Start Your Website Today</h2>
                <p class="mx-auto mt-5 max-w-2xl text-lg text-white/90">Launch a professional PHP script shop with premium visuals, SEO-ready content blocks, and product imports from your existing WooCommerce catalog.</p>
                <a href="#importer" class="mt-8 inline-flex rounded-2xl bg-white px-8 py-4 font-black text-ink transition hover:-translate-y-1">Import & Launch <i class='bx bx-right-arrow-alt ml-2 text-2xl'></i></a>
            </div>
        </section>
    </main>

    <footer class="relative z-10 border-t border-white/10 px-4 pb-24 pt-14 lg:px-8 lg:pb-10">
        <div class="mx-auto grid max-w-7xl gap-8 md:grid-cols-4">
            <div><h2 class="font-heading text-2xl font-black">Ladle<span class="text-accent">Themes</span></h2><p class="mt-4 text-muted muted-text">Premium PHP marketplace script for digital products, WordPress themes, and WooCommerce-powered catalogs.</p></div>
            <div><h3 class="font-black">Quick Links</h3><ul class="mt-4 space-y-3 text-muted muted-text"><li><a href="#themes">Featured Themes</a></li><li><a href="#categories">Categories</a></li><li><a href="#importer">Woo Importer</a></li></ul></div>
            <div><h3 class="font-black">Products</h3><ul class="mt-4 space-y-3 text-muted muted-text"><li>Movie Themes</li><li>News Themes</li><li>Elementor Kits</li></ul></div>
            <div><h3 class="font-black">Social</h3><div class="mt-4 flex gap-3"><a class="grid h-11 w-11 place-items-center rounded-full bg-white/5" href="#"><i class='bx bxl-facebook'></i></a><a class="grid h-11 w-11 place-items-center rounded-full bg-white/5" href="#"><i class='bx bxl-twitter'></i></a><a class="grid h-11 w-11 place-items-center rounded-full bg-white/5" href="#"><i class='bx bxl-linkedin'></i></a></div></div>
        </div>
        <p class="mx-auto mt-10 max-w-7xl border-t border-white/10 pt-6 text-sm text-muted muted-text">© <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</p>
    </footer>

    <nav class="fixed bottom-3 left-1/2 z-50 grid w-[92%] max-w-md -translate-x-1/2 grid-cols-4 rounded-3xl border border-white/10 bg-ink/90 p-2 text-center text-xs font-bold text-muted shadow-card backdrop-blur-2xl md:hidden" aria-label="Mobile bottom menu">
        <a class="rounded-2xl p-3 hover:bg-white/10" href="#home"><i class='bx bx-home block text-xl'></i>Home</a>
        <a class="rounded-2xl p-3 hover:bg-white/10" href="#themes"><i class='bx bx-store block text-xl'></i>Themes</a>
        <a class="rounded-2xl p-3 hover:bg-white/10" href="#categories"><i class='bx bx-category block text-xl'></i>Cats</a>
        <a class="rounded-2xl p-3 hover:bg-white/10" href="#importer"><i class='bx bx-download block text-xl'></i>Import</a>
    </nav>

    <script>
        const revealItems = document.querySelectorAll('.reveal');
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('show');
        }), { threshold: 0.12 });
        revealItems.forEach(item => observer.observe(item));

        document.querySelectorAll('.counter').forEach(counter => {
            const target = Number(counter.dataset.target || 0);
            let value = 0;
            const step = Math.max(1, Math.ceil(target / 80));
            const tick = () => {
                value = Math.min(target, value + step);
                counter.textContent = value.toLocaleString();
                if (value < target) requestAnimationFrame(tick);
            };
            tick();
        });

        document.getElementById('modeToggle')?.addEventListener('click', () => {
            document.body.classList.toggle('light');
        });
    </script>
</body>
</html>

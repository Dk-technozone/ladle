<?php

function setting_value(string $key, string $default = ''): string
{
    $settings = settings();
    return (string) ($settings[$key] ?? $default);
}

function save_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value')->execute([$key, $value]);
}

function active_coupon(?string $code): ?array
{
    $code = strtoupper(trim((string) $code));
    if ($code === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM coupons WHERE UPPER(code) = ? AND status = "active" AND (expires_at IS NULL OR expires_at = "" OR date(expires_at) >= date("now")) LIMIT 1');
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    return $coupon ?: null;
}

function calculate_discount(float $subtotal, ?array $coupon): float
{
    if (!$coupon) {
        return 0;
    }
    $value = (float) $coupon['discount_value'];
    return $coupon['discount_type'] === 'fixed' ? min($subtotal, $value) : round($subtotal * ($value / 100), 2);
}

function cart_items(): array
{
    $ids = array_values(array_unique(array_map('intval', $_SESSION['cart'] ?? [])));
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT products.*, vendors.store_name, categories.name AS category_name FROM products LEFT JOIN vendors ON vendors.id = products.vendor_id LEFT JOIN categories ON categories.id = products.category_id WHERE products.id IN ({$placeholders})");
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

function cart_totals(?string $couponCode = null): array
{
    $items = cart_items();
    $subtotal = array_reduce($items, fn(float $sum, array $item): float => $sum + (float) ($item['sale_price'] ?: $item['regular_price']), 0.0);
    $coupon = active_coupon($couponCode ?? ($_SESSION['coupon_code'] ?? ''));
    $discount = calculate_discount($subtotal, $coupon);
    return ['items' => $items, 'subtotal' => $subtotal, 'coupon' => $coupon, 'discount' => $discount, 'total' => max(0, $subtotal - $discount)];
}

function create_order_from_cart(string $paymentMethod, string $customerEmail): int
{
    $totals = cart_totals();
    if (!$totals['items']) {
        throw new RuntimeException('Cart is empty.');
    }
    $orderNumber = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
    $first = $totals['items'][0];
    db()->prepare('INSERT INTO orders (order_number, product_id, vendor_id, amount, payment_method, payment_status, coupon_code) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$orderNumber, (int) $first['id'], (int) ($first['vendor_id'] ?? 0), $totals['total'], $paymentMethod, 'pending', $_SESSION['coupon_code'] ?? '']);
    $orderId = (int) db()->lastInsertId();
    foreach ($totals['items'] as $item) {
        db()->prepare('INSERT INTO downloads (order_id, product_id, download_token, expires_at) VALUES (?, ?, ?, date("now", "+30 days"))')->execute([$orderId, (int) $item['id'], bin2hex(random_bytes(16))]);
    }
    $_SESSION['cart'] = [];
    unset($_SESSION['coupon_code']);
    notify_telegram('New order ' . $orderNumber . ' from ' . $customerEmail . ' total ' . money($totals['total']));
    return $orderId;
}

function notify_telegram(string $message): bool
{
    $token = setting_value('telegram_bot_token');
    $chatId = setting_value('telegram_chat_id');
    if ($token === '' || $chatId === '') {
        return false;
    }
    $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';
    $payload = http_build_query(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML']);
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload, 'timeout' => 8]]);
    return @file_get_contents($url, false, $context) !== false;
}

function auto_post_product_to_telegram(int $productId): void
{
    $stmt = db()->prepare('SELECT name, slug, short_description FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        return;
    }
    $message = "🆕 New product published: <b>" . $product['name'] . "</b>\n" . excerpt($product['short_description'], 120) . "\n" . app_url('product', ['slug' => $product['slug']]);
    $sent = notify_telegram($message);
    db()->prepare('INSERT INTO telegram_posts (product_id, message, status) VALUES (?, ?, ?)')->execute([$productId, $message, $sent ? 'sent' : 'queued']);
}

function ai_chat_reply(string $prompt): string
{
    $apiKey = setting_value('openai_api_key');
    $fallback = 'AI assistant ready: ask about products, compatibility, discounts, setup, or licenses.';
    if ($apiKey === '') {
        db()->prepare('INSERT INTO ai_logs (prompt, response, provider, status) VALUES (?, ?, ?, ?)')->execute([$prompt, $fallback, 'local-fallback', 'configured-fallback']);
        return $fallback;
    }
    $response = 'AI API key configured. Connect model endpoint from settings for production responses.';
    db()->prepare('INSERT INTO ai_logs (prompt, response, provider, status) VALUES (?, ?, ?, ?)')->execute([$prompt, $response, 'openai', 'stubbed']);
    return $response;
}

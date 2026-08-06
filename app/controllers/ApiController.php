<?php

function handle_api(string $route): void
{
    header('Content-Type: application/json');
    if ($route === 'search' || $route === 'autocomplete') {
        echo json_encode(['products' => product_query('trending', ['q' => trim($_GET['q'] ?? ''), 'limit' => 8])]);
        return;
    }
    if ($route === 'quick-view') {
        echo json_encode(['product' => find_product_by_slug($_GET['slug'] ?? '')]);
        return;
    }
    if ($route === 'cart-add') {
        $id = (int) ($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
        if ($id > 0) {
            $_SESSION['cart'][] = $id;
            db()->prepare('INSERT INTO carts (session_id, product_id, quantity) VALUES (?, ?, 1)')->execute([session_id(), $id]);
        }
        echo json_encode(['ok' => true, 'count' => count($_SESSION['cart'] ?? [])]);
        return;
    }
    if ($route === 'coupon') {
        $coupon = active_coupon($_POST['code'] ?? $_GET['code'] ?? '');
        if ($coupon) {
            $_SESSION['coupon_code'] = $coupon['code'];
        }
        echo json_encode(['ok' => (bool) $coupon, 'coupon' => $coupon, 'totals' => cart_totals()]);
        return;
    }
    if ($route === 'ai-chat') {
        echo json_encode(['reply' => ai_chat_reply(trim($_POST['message'] ?? $_GET['message'] ?? ''))]);
        return;
    }
    if ($route === 'telegram-test') {
        echo json_encode(['sent' => notify_telegram('Telegram integration test from ' . APP_NAME)]);
        return;
    }
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found']);
}

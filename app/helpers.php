<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(string $route = 'home', array $params = [], string $area = 'front'): string
{
    $query = array_merge($area === 'front' ? [] : ['area' => $area], ['route' => $route], $params);
    if ($area === 'front' && $route === 'home' && empty($params)) {
        return 'index.php';
    }
    return 'index.php?' . http_build_query($query);
}

function asset_url(string $path): string
{
    return ltrim($path, '/');
}

function redirect_to(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Security token expired. Please refresh and try again.');
    }
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type && $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function slugify(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    return $slug ?: 'item-' . time();
}

function money(mixed $amount): string
{
    return '$' . number_format((float) $amount, 0);
}

function excerpt(string $text, int $limit = 160): string
{
    $clean = trim(strip_tags($text));
    return strlen($clean) > $limit ? rtrim(substr($clean, 0, $limit), ' .,') . '...' : $clean;
}

function lines_to_array(?string $value): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $value))));
}

function csv_to_array(?string $value): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
}

function render(string $view, array $data = [], string $layout = 'front'): void
{
    extract($data, EXTR_SKIP);
    $viewFile = APP_ROOT . '/views/' . $view . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        exit('View missing: ' . e($view));
    }
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require APP_ROOT . '/views/layouts/' . $layout . '.php';
}

function settings(): array
{
    $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

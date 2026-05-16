<?php
require __DIR__ . '/app/bootstrap.php';

$area = $_GET['area'] ?? 'front';
$route = $_GET['route'] ?? 'home';

match ($area) {
    'admin' => handle_admin($route),
    'vendor' => handle_vendor($route),
    'api' => handle_api($route),
    default => handle_front($route),
};

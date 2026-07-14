<?php

require_once "core/AuthMiddleware.php";

$route = $_GET['route'] ?? '';

$publicRoutes = [
    'auth/v1/login',
    'auth/v1/logout'
];

// Apply auth for all protected routes
if (!in_array($route, $publicRoutes)) {
    AuthMiddleware::check();
}

// Load API
$file = "api/" . $route . ".php";

if (file_exists($file)) {
    require_once $file;
} else {
    echo json_encode([
        "status" => false,
        "message" => "Invalid route"
    ]);
}
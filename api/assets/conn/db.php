<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Database Connection
 * db.php
 * Version 1.0.0 | Updated 2026-07-10
 * ==========================================================
 */

if (!class_exists('Env')) {
    require_once dirname(__DIR__, 2) . '/core/Env.php';
}

Env::load();

$dbHost = Env::get('DB_HOST');
$dbPort = Env::get('DB_PORT', '3306');
$dbName = Env::get('DB_DATABASE');
$dbUser = Env::get('DB_USERNAME');
$dbPass = Env::get('DB_PASSWORD');
$dbTimeout = max(1, (int) Env::get('DB_CONNECT_TIMEOUT', '5'));

if (!$dbHost || !$dbName || !$dbUser || $dbPass === null) {
    if (class_exists('ErrorHandler')) {
        ErrorHandler::log('Database environment configuration missing', [
            'required' => ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']
        ]);
        ErrorHandler::sendFriendly(503);
    }

    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Service configuration is missing. Please contact support.',
        'data' => null,
        'errors' => null,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$con = mysqli_init();

if ($con) {
    mysqli_options($con, MYSQLI_OPT_CONNECT_TIMEOUT, $dbTimeout);
}

$connected = $con && @mysqli_real_connect(
    $con,
    $dbHost,
    $dbUser,
    $dbPass,
    $dbName,
    (int)$dbPort
);

if (!$connected) {
    $error = mysqli_connect_error();

    if (class_exists('ErrorHandler')) {
        ErrorHandler::log('Database connection failed', ['db_error' => $error]);
        ErrorHandler::sendFriendly(503);
    }

    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Service is temporarily unavailable. Please try again after some time.',
        'data' => null,
        'errors' => null,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

mysqli_set_charset($con, 'utf8mb4');

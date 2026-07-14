<?php

/**
 * auth_api.php
 * -------------------------------------------------------
 * Base file for APIs requiring authenticated session.
 * -------------------------------------------------------
 */

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/core/SessionManager.php';
require_once __DIR__ . '/core/Csrf.php';

/*
|--------------------------------------------------------------------------
| Start Secure Session
|--------------------------------------------------------------------------
*/

SessionManager::start();

/*
|--------------------------------------------------------------------------
| Verify Login
|--------------------------------------------------------------------------
*/

SessionManager::requireLogin();

/*
|--------------------------------------------------------------------------
| Verify CSRF For State Changing Requests
|--------------------------------------------------------------------------
*/

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    Csrf::validate();
}
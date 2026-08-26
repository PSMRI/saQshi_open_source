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
| Mandatory first-login password change
|--------------------------------------------------------------------------
| A user with a temporary/reset password may only use their profile endpoint
| to complete their details and change that password. This complements the
| client-side router guard and prevents direct API calls from bypassing it.
*/

$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$allowedDuringPasswordChange = (bool)preg_match('#/(?:api/)?(?:auth/v1/|admin/v1/users\.php$|config/v1/deployment\.php$)#', $requestPath);
if (!empty($_SESSION['password_must_change']) && !$allowedDuringPasswordChange) {
    Response::forbidden('Password change is required before continuing.');
}

/*
|--------------------------------------------------------------------------
| Verify CSRF For State Changing Requests
|--------------------------------------------------------------------------
*/

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    Csrf::validate();
}

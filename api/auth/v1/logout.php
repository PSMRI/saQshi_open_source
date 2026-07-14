<?php

/**
 * logout.php
 * -------------------------------------------------------
 * Logout authenticated user.
 *
 * Method:
 * POST
 *
 * URL:
 * /api/auth/v1/logout.php
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('POST');

try {

    $auth = new Auth($con);

    /*
     * Destroy CSRF token
     */
    Csrf::destroy();

    /*
     * Destroy session
     */
    $result = $auth->logout();

    Response::success(
        $result['message'] ?? 'Logout successful'
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
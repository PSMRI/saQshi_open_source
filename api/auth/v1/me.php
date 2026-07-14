<?php

/**
 * me.php
 * -------------------------------------------------------
 * Returns current logged-in user session details.
 *
 * Method:
 * GET
 *
 * URL:
 * /api/auth/v1/me.php
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

try {

    $auth = new Auth($con);

    $result = $auth->me();

    if ($result['status'] !== 'success') {
        Response::error($result['message']);
    }

    Response::success(
        'User fetched successfully',
        $result['data']
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
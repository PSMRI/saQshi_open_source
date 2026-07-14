<?php

/**
 * login.php
 * -------------------------------------------------------
 * Secure login API for SaQshi.
 *
 * Method:
 * POST
 *
 * URL:
 * /api/auth/v1/login.php
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../public_api.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/LoginCrypto.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('POST');

try {

    $request = Security::jsonInput();
    Event::dispatch('auth.login.started', [
        'has_username' => isset($request['username']) && trim((string)$request['username']) !== '',
        'has_password' => isset($request['password']) && (string)$request['password'] !== '',
        'has_captcha' => isset($request['captcha']) && trim((string)$request['captcha']) !== ''
    ]);

    Security::requireFields($request, [
        'username',
        'password_enc',
        'captcha'
    ]);

    $username = Security::cleanString($request['username']);
    $password = LoginCrypto::decryptPassword((string)$request['password_enc']);
    $captcha = trim((string)$request['captcha']);

    $expectedCaptcha = (string)($_SESSION['login_captcha_answer'] ?? '');
    $captchaExpires = (int)($_SESSION['login_captcha_expires'] ?? 0);

    unset($_SESSION['login_captcha_answer'], $_SESSION['login_captcha_expires']);

    if (
        $expectedCaptcha === '' ||
        $captchaExpires < time() ||
        !hash_equals($expectedCaptcha, $captcha)
    ) {
        Event::dispatch('auth.login.failed', [
            'reason' => 'captcha'
        ]);

        Response::validation([
            'captcha' => 'Invalid captcha. Please try again.'
        ]);
    }

    $auth = new Auth($con);
    $authStarted = microtime(true);

    $result = $auth->login(
        $username,
        $password
    );

    Event::dispatch('auth.login.auth_checked', [
        'duration_ms' => round((microtime(true) - $authStarted) * 1000, 2),
        'status' => $result['status'] ?? 'unknown'
    ]);

    if (
        !isset($result['status']) ||
        $result['status'] !== 'success'
    ) {
        Event::dispatch('auth.login.failed', [
            'reason' => 'invalid_credentials'
        ]);

        Response::error(
            $result['message'] ?? 'Invalid username or password'
        );
    }

    /*
     * IMPORTANT:
     * CSRF token is regenerated only after successful login.
     * Frontend must store this token and should not call csrf.php
     * again immediately after login.
     */
    $csrfToken = Csrf::regenerate();

    Event::dispatch('auth.login.succeeded', [
        'user_id' => $result['data']['user']['u_id'] ?? null,
        'facility_id' => $result['data']['user']['facility_id'] ?? null
    ]);

    Response::success(
        'Login successful',
        [
            'user' => $result['data']['user'] ?? null,
            'csrf_token' => $csrfToken,
            'csrf' => [
                'token' => $csrfToken,
                'header_name' => 'X-CSRF-TOKEN'
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

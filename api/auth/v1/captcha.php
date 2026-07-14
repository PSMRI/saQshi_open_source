<?php

/**
 * captcha.php
 * -------------------------------------------------------
 * Generates a small server-side math captcha for login.
 *
 * Method:
 * GET
 *
 * URL:
 * /api/auth/v1/captcha.php
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../public_api.php';

Security::requireMethod('GET');

try {
    $left = random_int(2, 9);
    $right = random_int(1, 9);
    $answer = (string)($left + $right);

    $_SESSION['login_captcha_answer'] = $answer;
    $_SESSION['login_captcha_expires'] = time() + 300;

    Response::success('Captcha generated', [
        'question' => $left . ' + ' . $right . ' = ?',
        'expires_in' => 300
    ]);

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

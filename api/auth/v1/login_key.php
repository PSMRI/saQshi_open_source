<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Login Public Key API
 * login_key.php
 * Version 1.0.0 | Updated 2026-07-10
 * ==========================================================
 */

require_once __DIR__ . '/../../public_api.php';
require_once __DIR__ . '/../../core/LoginCrypto.php';

Security::requireMethod('GET');

try {
    Response::success('Login public key generated', [
        'algorithm' => 'RSA-OAEP-SHA1',
        'public_key' => LoginCrypto::publicKey()
    ]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

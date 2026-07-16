<?php

/**
 * SaQshi API
 * auth/v1/csrf.php
 * Purpose: csrf endpoint/support workflow.
 */


require_once __DIR__ . '/../../public_api.php';

Response::success(
    'CSRF token generated',
    Csrf::getTokenInfo()
);

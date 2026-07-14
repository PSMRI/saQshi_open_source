<?php

require_once __DIR__ . '/../../public_api.php';

Response::success(
    'CSRF token generated',
    Csrf::getTokenInfo()
);

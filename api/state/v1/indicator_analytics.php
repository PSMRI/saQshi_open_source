<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * State Indicator Analytics API
 * indicator_analytics.php
 * Version 1.1.0 | Updated 2026-07-13
 * ==========================================================
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../service/StateIndicatorAnalyticsService.php';

Security::requireMethod('GET');

try {
    if (($_GET['download'] ?? '') === 'zero_facilities') {
        StateIndicatorAnalyticsService::streamZeroFacilityList($con, (int)($_GET['checkpoint_id'] ?? 0), $_GET);
    }

    Response::success('State indicator analytics loaded', StateIndicatorAnalyticsService::analytics($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

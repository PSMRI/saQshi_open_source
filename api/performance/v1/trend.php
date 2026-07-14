<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Trend API
 * trend.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/PerformanceService.php';

Security::requireMethod('GET');

try {
    $facId = SessionManager::facilityId();
    Response::success('Performance trend loaded', PerformanceService::dashboard($con, $facId, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

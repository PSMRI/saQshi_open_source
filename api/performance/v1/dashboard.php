<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Dashboard API
 * dashboard.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/DashboardService.php';

Security::requireMethod('GET');

try {
    Response::success('Performance dashboard loaded', DashboardService::dashboard($con, SessionManager::facilityId(), $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

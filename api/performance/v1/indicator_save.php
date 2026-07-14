<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Indicator Save API
 * indicator_save.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/IndicatorService.php';

Security::requireMethod('POST');

try {
    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    $payload = is_array($payload) ? $payload : [];

    Response::success(
        'Indicator saved successfully',
        IndicatorService::save($con, $payload, SessionManager::userId(), SessionManager::facilityId())
    );
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

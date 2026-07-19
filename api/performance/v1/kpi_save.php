<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance KPI Save API
 * kpi_save.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/KPIService.php';

Security::requireMethod('POST');

try {
    $user = SessionManager::user();
    $roleName = strtolower((string)($user['role_name'] ?? $user['user_type'] ?? ''));
    if ((int)($user['role_id'] ?? 0) === 10 || str_contains($roleName, 'assessor')) {
        Response::forbidden('Assessors can view KPI data but cannot save KPI entries.');
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    $payload = is_array($payload) ? $payload : [];

    $result = KPIService::save($con, $payload, SessionManager::userId(), SessionManager::facilityId());

    Event::dispatch('performance.kpi.saved', [
        'fac_id' => SessionManager::facilityId(),
        'user_id' => SessionManager::userId(),
        'payload' => $payload,
        'result' => $result
    ]);

    Response::success(
        'KPI saved successfully',
        $result
    );
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

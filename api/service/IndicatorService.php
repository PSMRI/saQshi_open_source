<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Indicator Service
 * IndicatorService.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

/**
 * IndicatorService.php
 * -------------------------------------------------------
 * Generic KPI / Outcome indicator service per v3 design.
 * -------------------------------------------------------
 */

require_once __DIR__ . '/KPIService.php';
require_once __DIR__ . '/OutcomeService.php';
require_once __DIR__ . '/ValidationService.php';

class IndicatorService
{
    public static function configPath(): string
    {
        return __DIR__ . '/../config/performance/indicator.json';
    }

    public static function list(int $facilityTypeId = 0, int $departmentId = 0, string $indicatorType = ''): array
    {
        $type = strtoupper(trim($indicatorType));

        if ($type === 'KPI') {
            return KPIService::list($facilityTypeId, $departmentId);
        }

        if ($type === 'OUTCOME') {
            return OutcomeService::list($facilityTypeId, $departmentId);
        }

        return array_merge(
            KPIService::list($facilityTypeId, $departmentId),
            OutcomeService::list($facilityTypeId, $departmentId)
        );
    }

    public static function save(mysqli $con, array $payload, int $userId, int $facilityId): array
    {
        $errors = ValidationService::validateEntry($payload);

        if ($errors) {
            Response::validation($errors);
        }

        $type = strtoupper((string)($payload['indicator_type'] ?? ''));

        if ($type === 'KPI') {
            return KPIService::save($con, $payload, $userId, $facilityId);
        }

        return OutcomeService::save($con, $payload, $userId, $facilityId);
    }

    public static function history(mysqli $con, int $facilityId, array $filters = []): array
    {
        $type = strtoupper((string)($filters['indicator_type'] ?? ''));

        if ($type === 'KPI') {
            return KPIService::history($con, $facilityId, $filters);
        }

        if ($type === 'OUTCOME') {
            return OutcomeService::history($con, $facilityId, $filters);
        }

        $kpi = KPIService::history($con, $facilityId, $filters)['items'] ?? [];
        $outcome = OutcomeService::history($con, $facilityId, $filters)['items'] ?? [];

        return [
            'filters' => $filters,
            'items' => array_merge($kpi, $outcome)
        ];
    }
}

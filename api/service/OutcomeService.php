<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Outcome Service
 * OutcomeService.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

/**
 * OutcomeService.php
 * -------------------------------------------------------
 * Outcome list, save and history service.
 * -------------------------------------------------------
 */

require_once __DIR__ . '/KPIService.php';

class OutcomeService
{
    public static function configPath(): string
    {
        $correct = __DIR__ . '/../config/performance/outcome.json';
        $legacy = __DIR__ . '/../config/performance/outcom.json';

        $data = PerformanceService::readJson($correct, []);

        if (!empty($data['outcomes']) || !empty($data['facility_types'])) {
            return $correct;
        }

        return file_exists($legacy) ? $legacy : $correct;
    }

    public static function list(int $facilityTypeId = 0, int $departmentId = 0): array
    {
        $config = PerformanceService::readJson(self::configPath(), ['outcomes' => []]);
        return PerformanceService::flattenIndicators($config, 'OUTCOME', $facilityTypeId, $departmentId);
    }

    public static function save(mysqli $con, array $payload, int $userId, int $facilityId): array
    {
        $payload['indicator_type'] = 'OUTCOME';
        return KPIService::saveEntry($con, $payload, $userId, $facilityId, 'OUTCOME');
    }

    public static function history(mysqli $con, int $facilityId, array $filters = []): array
    {
        return KPIService::entryHistory($con, $facilityId, 'OUTCOME', $filters);
    }
}

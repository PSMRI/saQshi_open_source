<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance KPI Service
 * KPIService.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

/**
 * KPIService.php
 * -------------------------------------------------------
 * KPI list, save and history service.
 * -------------------------------------------------------
 */

require_once __DIR__ . '/PerformanceService.php';

/**
 * Provides kpiservice behavior for SaQshi API workflows.
 */
class KPIService
{
    /**
     * Handles config path processing for this API workflow.
     */
    public static function configPath(): string
    {
        return __DIR__ . '/../config/performance/kpi.json';
    }

    /**
     * Handles list processing for this API workflow.
     */
    public static function list(int $facilityTypeId = 0, int $departmentId = 0): array
    {
        $config = PerformanceService::readJson(self::configPath(), ['kpis' => []]);
        return PerformanceService::flattenIndicators($config, 'KPI', $facilityTypeId, $departmentId);
    }

    /**
     * Handles save processing for this API workflow.
     */
    public static function save(mysqli $con, array $payload, int $userId, int $facilityId): array
    {
        return self::saveEntry($con, $payload, $userId, $facilityId, 'KPI');
    }

    /**
     * Handles history processing for this API workflow.
     */
    public static function history(mysqli $con, int $facilityId, array $filters = []): array
    {
        return self::entryHistory($con, $facilityId, 'KPI', $filters);
    }

    /**
     * Handles save entry processing for this API workflow.
     */
    public static function saveEntry(mysqli $con, array $payload, int $userId, int $facilityId, string $type): array
    {
        PerformanceService::ensureTable($con);

        $deptId = (int)($payload['department_id'] ?? $payload['dept_id'] ?? 0);
        $facilityTypeId = (int)($payload['facility_type_id'] ?? $payload['fac_type_id'] ?? 0);
        if ($facilityTypeId <= 0) {
            $facility = PerformanceService::facilityMeta($facilityId);
            $facilityTypeId = (int)($facility['fac_type_id'] ?? 0);
        }
        PerformanceService::assertIndicatorAllowed($facilityTypeId, $type);

        $indicatorId = (int)($payload['indicator_id'] ?? 0);
        $month = (int)($payload['month'] ?? date('n'));
        $year = (int)($payload['year'] ?? date('Y'));
        $numerator = (float)($payload['numerator'] ?? $payload['numerator_value'] ?? 0);
        $denominator = (float)($payload['denominator'] ?? $payload['denominator_value'] ?? 0);
        $denominatorNA = !empty($payload['denominator_na']) || !empty($payload['denominator_not_applicable']);
        $formulaId = (int)($payload['formula_id'] ?? 0);
        $indicatorCode = (string)($payload['indicator_code'] ?? '');
        $indicatorName = (string)($payload['indicator_name'] ?? '');
        $remarks = (string)($payload['remarks'] ?? '');
        $result = $denominatorNA ? round($numerator, 2) : FormulaEngine::calculate($numerator, $denominator, $formulaId);

        $errors = [];

        if ($facilityId <= 0) {
            $errors['facility'] = 'Facility ID is required';
        }

        if ($indicatorId <= 0) {
            $errors['indicator_id'] = 'Indicator is required';
        }

        if ($month < 1 || $month > 12) {
            $errors['month'] = 'Valid month is required';
        }

        if ($year < 2000 || $year > 2100) {
            $errors['year'] = 'Valid year is required';
        }

        if ($result === null) {
            $errors['result'] = 'Unable to calculate result';
        }

        if ($errors) {
            Response::validation($errors);
        }

        $sql = "
            INSERT INTO performance_entries (
                fac_id, dept_id, indicator_type, indicator_id, indicator_code, indicator_name,
                entry_month, entry_year, numerator_value, denominator_value, result_value,
                formula_id, remarks, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                indicator_code = VALUES(indicator_code),
                indicator_name = VALUES(indicator_name),
                numerator_value = VALUES(numerator_value),
                denominator_value = VALUES(denominator_value),
                result_value = VALUES(result_value),
                formula_id = VALUES(formula_id),
                remarks = VALUES(remarks),
                updated_by = VALUES(updated_by)
        ";

        $stmt = $con->prepare($sql);

        if (!$stmt) {
            Response::serverError('Performance save prepare failed: ' . $con->error);
        }

        $stmt->bind_param(
            'iisissiidddisii',
            $facilityId,
            $deptId,
            $type,
            $indicatorId,
            $indicatorCode,
            $indicatorName,
            $month,
            $year,
            $numerator,
            $denominator,
            $result,
            $formulaId,
            $remarks,
            $userId,
            $userId
        );

        if (!$stmt->execute()) {
            Response::serverError('Performance save failed: ' . $stmt->error);
        }

        return [
            'entry_id' => $stmt->insert_id,
            'facility_id' => $facilityId,
            'department_id' => $deptId,
            'indicator_type' => $type,
            'indicator_id' => $indicatorId,
            'month' => $month,
            'year' => $year,
            'numerator' => $numerator,
            'denominator' => $denominator,
            'result' => $result
        ];
    }

    /**
     * Handles entry history processing for this API workflow.
     */
    public static function entryHistory(mysqli $con, int $facilityId, string $type, array $filters): array
    {
        PerformanceService::ensureTable($con);

        $params = [$facilityId, $type];
        $types = 'is';
        $where = 'fac_id = ? AND indicator_type = ?';

        foreach (['indicator_id' => 'i', 'dept_id' => 'i', 'entry_year' => 'i', 'entry_month' => 'i'] as $key => $bindType) {
            $inputKey = $key === 'dept_id' ? 'department_id' : str_replace('entry_', '', $key);

            if (isset($filters[$inputKey]) && $filters[$inputKey] !== '') {
                $where .= " AND {$key} = ?";
                $params[] = (int)$filters[$inputKey];
                $types .= $bindType;
            }
        }

        $stmt = $con->prepare("
            SELECT *
            FROM performance_entries
            WHERE {$where}
            ORDER BY entry_year DESC, entry_month DESC, indicator_name ASC
        ");

        if (!$stmt) {
            Response::serverError('Performance history prepare failed: ' . $con->error);
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $rows = [];
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return [
            'filters' => $filters,
            'items' => $rows
        ];
    }
}

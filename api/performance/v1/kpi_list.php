<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance KPI List API
 * kpi_list.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/KPIService.php';
require_once __DIR__ . '/../../service/PerformanceService.php';

Security::requireMethod('GET');

try {
    $facId = SessionManager::facilityId();
    $facility = PerformanceService::facilityMeta($facId);
    $facilityTypeId = (int)($_GET['facility_type_id'] ?? $facility['fac_type_id'] ?? 0);
    $departmentId = (int)($_GET['department_id'] ?? $_GET['dept_id'] ?? 0);
    $rule = PerformanceService::facilityTypeRule($facilityTypeId);
    $activeAssessment = PerformanceService::activeAssessment($con, $facId);
    $activeDepartments = PerformanceService::activeDepartmentIds($con, $facId);
    $items = [];

    if ($rule['kpi_applicable'] && !$rule['block_kpi_entry']) {
        $items = PerformanceService::filterByDepartmentIds(
            KPIService::list($facilityTypeId, $departmentId),
            $activeDepartments
        );
    }

    if ($departmentId > 0) {
        $items = array_values(array_filter($items, fn($item) => (int)($item['department_id'] ?? 0) === $departmentId));
    }

    Response::success('KPI list loaded', [
        'facility' => $facility,
        'rule' => $rule,
        'effective_indicator_type' => ($rule['kpi_applicable'] && !$rule['block_kpi_entry']) ? 'KPI' : 'OUTCOME',
        'active_assessment' => $activeAssessment,
        'active_department_ids' => $activeDepartments,
        'items' => $items
    ]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

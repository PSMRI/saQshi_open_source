<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * State Reports API
 * reports.php
 * Version 1.1.0 | Updated 2026-07-13
 * ==========================================================
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../service/StateReportService.php';

Security::requireMethod('GET');

try {
    if (isset($_GET['download'])) {
        StateReportService::streamCsv($con, (string)$_GET['download'], $_GET);
    }

    Response::success('State report data loaded', [
        'facility_category' => StateDashboardService::facilityCategory($con, $_GET),
        'assessment_progress' => StateDashboardService::assessmentProgress($con, $_GET),
        'cqi_summary' => StateDashboardService::cqiSummary($con, $_GET),
        'performance_summary' => StateDashboardService::performanceSummary($con, $_GET),
        'certification_summary' => StateDashboardService::certificationSummary($con, $_GET),
        'exports' => StateReportService::exportCatalog()
    ]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

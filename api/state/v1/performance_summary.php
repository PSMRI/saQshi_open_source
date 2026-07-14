<?php

/*! SaQshi Open Source | State Performance Summary API | performance_summary.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    Response::success('Performance summary loaded', StateDashboardService::performanceSummary($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

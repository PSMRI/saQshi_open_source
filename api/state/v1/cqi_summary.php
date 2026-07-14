<?php

/*! SaQshi Open Source | State CQI Summary API | cqi_summary.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    Response::success('CQI summary loaded', StateDashboardService::cqiSummary($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

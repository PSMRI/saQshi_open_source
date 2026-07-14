<?php

/*! SaQshi Open Source | State Certification Summary API | certification_summary.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

try {
    Response::success('Certification summary loaded', StateDashboardService::certificationSummary($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

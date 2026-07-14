<?php

/*! SaQshi Open Source | State Facility Progress API | facility_progress.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    Response::success('Facility progress loaded', StateDashboardService::assessmentProgress($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

<?php

/*! SaQshi Open Source | State Assessment Progress API | assessment_progress.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    Response::success('Assessment progress loaded', StateDashboardService::assessmentProgress($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

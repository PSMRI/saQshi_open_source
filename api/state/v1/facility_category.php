<?php

/*! SaQshi Open Source | State Facility Category API | facility_category.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    Response::success('Facility category loaded', StateDashboardService::facilityCategory($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

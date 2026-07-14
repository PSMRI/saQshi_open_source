<?php

/*! SaQshi Open Source | State User Administration API | users.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    Response::success('State users loaded', StateDashboardService::users($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

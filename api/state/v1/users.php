<?php

/*! SaQshi Open Source | State User Administration API | users.php | Version 1.0.0 */

require_once __DIR__ . '/_management_bootstrap.php';
require_once __DIR__ . '/../../service/StateDashboardService.php';

Security::requireMethod('GET');

try {
    if (SessionManager::roleId() === 11) {
        $_GET['management_roles_only'] = 1;
    }
    Response::success('State users loaded', StateDashboardService::users($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

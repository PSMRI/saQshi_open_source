<?php

/*! SaQshi Open Source | State Certification Map API | map.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    Response::success('Certification map loaded', StateDashboardService::certificationMap($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

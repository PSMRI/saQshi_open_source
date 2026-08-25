<?php

/*! SaQshi Open Source | State Certification Map API | map.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../service/StateIndicatorAnalyticsService.php';

Security::requireMethod('GET');

try {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    $mode = strtolower(trim((string)($_GET['map_mode'] ?? 'presence')));
    if ($mode === 'area_of_concern') {
        $base = StateDashboardService::certificationMap($con, $_GET);
        $data = StateIndicatorAnalyticsService::areaOfConcernMap($con, $_GET);
        $data['map_config'] = $base['map_config'] ?? [];
        $data['map_mode'] = $mode;
        Response::success('Area of Concern map loaded', $data);
    }

    Response::success('Facility map loaded', StateDashboardService::certificationMap($con, $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

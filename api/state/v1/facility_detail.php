<?php

/*! SaQshi Open Source | State Facility Detail API | facility_detail.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    if (strtolower((string)($_GET['mode'] ?? '')) === 'hierarchy') {
        Response::success('Facility hierarchy loaded', StateDashboardService::facilityHierarchy($_GET));
    }

    $facilityId = Security::int($_GET['fac_id'] ?? 0);
    if ($facilityId <= 0 && trim((string)($_GET['search'] ?? '')) !== '') {
        $facilityId = StateDashboardService::resolveFacilityId($con, (string)$_GET['search']);
    }
    Response::success('Facility detail loaded', StateDashboardService::facilityDetail($con, $facilityId));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

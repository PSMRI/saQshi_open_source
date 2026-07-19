<?php

/*! SaQshi Open Source | Assessor Facility Summary API | facility_summary.php | Version 1.0.0 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessorService.php';

Security::requireMethod('GET');

try {
    $facId = (int)($_GET['fac_id'] ?? 0);

    if ($facId <= 0) {
        Response::validation(['fac_id' => 'Facility is required.']);
    }

    Response::success(
        'Assessor facility summary loaded',
        (new AssessorService($con))->facilitySummary(SessionManager::userId(), SessionManager::username(), $facId)
    );
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

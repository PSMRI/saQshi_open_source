<?php

/*! SaQshi Open Source | Assessor My Facilities API | my_facilities.php | Version 1.0.0 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessorService.php';

Security::requireMethod('GET');

try {
    Response::success(
        'Mapped facilities loaded',
        (new AssessorService($con))->mappedFacilitiesForUser(SessionManager::userId(), SessionManager::username())
    );
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

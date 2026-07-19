<?php

/*! SaQshi Open Source | Assessor Master List API | list.php | Version 1.0.0 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessorService.php';

Security::requireMethod('GET');

try {
    Response::success('Assessors loaded', (new AssessorService($con))->listAssessors($_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

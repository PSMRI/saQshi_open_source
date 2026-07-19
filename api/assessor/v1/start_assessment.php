<?php

/*! SaQshi Open Source | Assessor Start Assessment API | start_assessment.php | Version 1.0.0 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessorService.php';

Security::requireMethod('POST');

try {
    $payload = Security::jsonInput();
    $data = (new AssessorService($con))->startAssessment(
        $payload,
        SessionManager::userId(),
        SessionManager::username()
    );

    Event::dispatch('assessor.assessment_started', [
        'assessment_id' => $data['assessment']['assessment_id'] ?? null,
        'fac_id' => $data['facility']['fac_id'] ?? null,
        'assessor_id' => $_SESSION['assessor_id'] ?? null
    ]);

    Response::success('Assessment ready for selected facility', $data);
} catch (InvalidArgumentException $e) {
    Response::validation(['facility' => $e->getMessage()]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

<?php

/*! SaQshi Open Source | Assessor Master Save API | save.php | Version 1.0.0 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessorService.php';

Security::requireAnyMethod(['POST', 'PATCH']);

try {
    $payload = Security::jsonInput();
    $data = (new AssessorService($con))->saveAssessor($payload, SessionManager::userId());

    Event::dispatch('assessor.saved', [
        'assessor_id' => $data['assessor_id'] ?? null,
        'saved_by' => SessionManager::userId()
    ]);

    Response::success('Assessor saved', $data);
} catch (InvalidArgumentException $e) {
    Response::validation(['assessor' => $e->getMessage()]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

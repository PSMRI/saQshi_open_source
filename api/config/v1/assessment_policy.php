<?php

/*! SaQshi Open Source | Education Assessment Period Configuration API */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../service/DeploymentConfigService.php';

try {
    $roleId = SessionManager::roleId();
    if (!in_array($roleId, [1, 9], true)) {
        Response::forbidden('Only system/state administrators can change the assessment period.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Security::requireMethod('GET');
        Response::success('Assessment period configuration loaded', DeploymentConfigService::current()['domain']['assessment_policy'] ?? []);
    }

    Security::requireMethod('POST');
    Response::success(
        'Assessment period configuration saved',
        DeploymentConfigService::updateAssessmentPolicy(Security::jsonInput(), SessionManager::userId())
    );
} catch (InvalidArgumentException $e) {
    Response::validation(['reassessment_interval_days' => $e->getMessage()]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

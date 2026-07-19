<?php

/*! SaQshi Open Source | Apply Deployment Profile API | profile_apply.php | Version 1.0.0 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../service/DeploymentConfigService.php';

Security::requireMethod('POST');

try {
    $roleId = SessionManager::roleId();

    if (!in_array($roleId, [1, 9], true)) {
        Response::forbidden('Only system/state administrators can apply deployment profiles.');
    }

    $payload = Security::jsonInput();
    $profileCode = (string)($payload['profile_code'] ?? '');

    Response::success(
        'Deployment profile applied successfully',
        DeploymentConfigService::applyProfile($profileCode, SessionManager::userId())
    );
} catch (InvalidArgumentException $e) {
    Response::validation(['profile_code' => $e->getMessage()]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

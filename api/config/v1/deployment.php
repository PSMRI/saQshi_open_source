<?php

/*! SaQshi Open Source | Deployment Configuration API | deployment.php | Version 1.0.0 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../service/DeploymentConfigService.php';

Security::requireMethod('GET');

try {
    Response::success('Deployment configuration loaded', DeploymentConfigService::current());
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

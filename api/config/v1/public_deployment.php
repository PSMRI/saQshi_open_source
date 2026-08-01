<?php

/*! SaQshi Open Source | Public Deployment Branding API | public_deployment.php | Version 1.0.0 */

require_once __DIR__ . '/../../public_api.php';
require_once __DIR__ . '/../../service/DeploymentConfigService.php';

Security::requireMethod('GET');

try {
    $domain = DeploymentConfigService::current()['domain'] ?? [];

    Response::success('Public deployment branding loaded', [
        'domain' => [
            'profile_code' => (string)($domain['profile_code'] ?? ''),
            'profile_name' => (string)($domain['profile_name'] ?? ''),
            'labels' => is_array($domain['labels'] ?? null) ? $domain['labels'] : [],
            'branding' => is_array($domain['branding'] ?? null) ? $domain['branding'] : [],
            'content' => is_array($domain['content'] ?? null) ? $domain['content'] : []
        ]
    ]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

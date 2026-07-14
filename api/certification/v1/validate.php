<?php

require_once __DIR__ . '/_common.php';

certificationHandle(function () use ($con) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['status' => 'error', 'message' => 'POST required'], 405);
    }

    $payload = certificationPayload();
    $config = CertificationService::config();
    $errors = CertificationValidator::validatePayload($payload, $config);

    if (!$errors && strtoupper((string)($payload['certification_type'] ?? $payload['cert_type'] ?? '')) === 'NATIONAL') {
        $state = CertificationService::current($con, [
            'fac_nin' => $payload['fac_nin'] ?? null,
            'fac_id' => $payload['fac_id'] ?? null,
            'certification_type' => 'STATE'
        ]);

        if (!$state && !empty($config['national_requires_state'])) {
            $errors['certification_type'] = 'National certification requires an existing State certification.';
        }
    }

    respond([
        'status' => $errors ? 'error' : 'success',
        'message' => $errors ? 'Validation failed' : 'Validation passed',
        'errors' => $errors
    ], $errors ? 422 : 200);
});

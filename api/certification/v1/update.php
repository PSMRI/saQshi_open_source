<?php

/**
 * SaQshi API
 * certification/v1/update.php
 * Purpose: update endpoint/support workflow.
 */


require_once __DIR__ . '/_common.php';

certificationHandle(function () use ($con) {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'], true)) {
        respond(['status' => 'error', 'message' => 'POST or PUT required'], 405);
    }

    $payload = certificationPayload();
    $id = (int)($_GET['certification_id'] ?? $payload['certification_id'] ?? $payload['id'] ?? 0);

    if ($id <= 0) {
        respond(['status' => 'error', 'message' => 'certification_id is required'], 400);
    }

    $row = CertificationService::update($con, $id, $payload);

    respond([
        'status' => 'success',
        'message' => 'Certification updated successfully',
        'data' => $row
    ]);
});

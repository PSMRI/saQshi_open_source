<?php

/**
 * SaQshi API
 * certification/v1/dashboard.php
 * Purpose: dashboard endpoint/support workflow.
 */


require_once __DIR__ . '/_common.php';

certificationHandle(function () use ($con) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['status' => 'error', 'message' => 'GET required'], 405);
    }

    respond([
        'status' => 'success',
        'message' => 'Certification dashboard fetched',
        'data' => CertificationService::dashboard($con, certificationFilters())
    ]);
});

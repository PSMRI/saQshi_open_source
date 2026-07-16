<?php

/**
 * SaQshi API
 * certification/v1/renewal_status.php
 * Purpose: renewal status endpoint/support workflow.
 */


require_once __DIR__ . '/_common.php';

certificationHandle(function () use ($con) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['status' => 'error', 'message' => 'GET required'], 405);
    }

    $rows = CertificationService::list($con, certificationFilters());
    $data = array_map(fn($row) => [
        'certification_id' => $row['certification_id'],
        'certification_type' => $row['certification_type'],
        'valid_to' => $row['valid_to'],
        'renewal_status' => $row['renewal_status']
    ], $rows);

    respond([
        'status' => 'success',
        'message' => 'Renewal status fetched',
        'count' => count($data),
        'data' => $data
    ]);
});

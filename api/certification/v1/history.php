<?php

require_once __DIR__ . '/_common.php';

certificationHandle(function () use ($con) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['status' => 'error', 'message' => 'GET required'], 405);
    }

    $rows = CertificationService::history($con, certificationFilters());

    respond([
        'status' => 'success',
        'message' => 'Certification history fetched',
        'count' => count($rows),
        'data' => $rows
    ]);
});

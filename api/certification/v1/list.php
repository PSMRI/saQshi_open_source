<?php

require_once __DIR__ . '/_common.php';

certificationHandle(function () use ($con) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['status' => 'error', 'message' => 'GET required'], 405);
    }

    $rows = CertificationService::list($con, certificationFilters());

    respond([
        'status' => 'success',
        'message' => 'Certification records fetched',
        'count' => count($rows),
        'data' => $rows
    ]);
});

<?php

require_once __DIR__ . '/_common.php';

certificationHandle(function () use ($con) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['status' => 'error', 'message' => 'GET required'], 405);
    }

    $data = CertificationService::current($con, certificationFilters());

    respond([
        'status' => 'success',
        'message' => $data ? 'Current certification fetched' : 'No certification found',
        'data' => $data
    ], $data ? 200 : 404);
});

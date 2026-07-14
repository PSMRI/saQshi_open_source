<?php

require_once __DIR__ . '/_common.php';

certificationHandle(function () use ($con) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['status' => 'error', 'message' => 'POST required'], 405);
    }

    $row = CertificationService::save($con, certificationPayload());

    Event::dispatch('certification.updated', [
        'fac_id' => SessionManager::facilityId(),
        'user_id' => SessionManager::userId(),
        'certification' => $row
    ]);

    respond([
        'status' => 'success',
        'message' => 'Certification saved successfully',
        'data' => $row
    ], 201);
});

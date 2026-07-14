<?php

/**
 * get-cycle.php
 * -------------------------------------------------------
 * Fetch assessment cycle details with departments.
 *
 * URL:
 * /api/assessment/v1/get-cycle.php?cycle_id=1
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../service/DynamicAssessmentService.php';
require_once __DIR__ . '/../../assets/conn/db.php';

try {

    $cycleId = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : 0;

    if ($cycleId <= 0) {
        Response::validation([
            'cycle_id' => 'cycle_id is required'
        ]);
    }

    $service = new DynamicAssessmentService($con);

    $result = $service->getCycle($cycleId);

    if ($result['status'] === 'success') {
        Response::success(
            $result['message'],
            $result['data']
        );
    }

    Response::error(
        $result['message']
    );

} catch (Throwable $e) {

    Response::serverError(
        $e->getMessage()
    );
}
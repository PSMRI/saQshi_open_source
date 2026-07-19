<?php

/**
 * list.php
 * -------------------------------------------------------
 * Get department activation status list
 * for facility + assessment period.
 *
 * URL:
 * /api/assessment/v1/department-status/list.php?fac_id=1&assessment_id=1
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../../core/Response.php';
require_once __DIR__ . '/../../../service/DepartmentStatusService.php';
require_once __DIR__ . '/../../../assets/conn/db.php';

try {

    $facId = isset($_GET['fac_id']) ? (int)$_GET['fac_id'] : 0;
    $assessmentId = isset($_GET['assessment_id'])
        ? (int)$_GET['assessment_id']
        : (int)($_GET['ass_period'] ?? 0);

    if ($facId <= 0) {
        Response::validation([
            'fac_id' => 'Facility ID is required'
        ]);
    }

    if ($assessmentId <= 0) {
        Response::validation([
            'assessment_id' => 'Assessment ID is required'
        ]);
    }

    $service = new DepartmentStatusService($con);

    $result = $service->getStatusList(
        $facId,
        $assessmentId
    );

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

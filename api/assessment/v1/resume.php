<?php

/**
 * resume.php
 * -------------------------------------------------------
 * Resume an assessment cycle.
 *
 * URL:
 * /api/assessment/v1/resume.php?cycle_id=1
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../service/DynamicAssessmentService.php';
require_once __DIR__ . '/../../assets/conn/db.php';

try {

    $cycleId = isset($_GET['cycle_id'])
        ? (int)$_GET['cycle_id']
        : 0;

    if ($cycleId <= 0) {
        Response::validation([
            'cycle_id' => 'cycle_id is required'
        ]);
    }

    $service = new DynamicAssessmentService($con);

    /* Get Cycle */
    $cycleResult = $service->getCycle($cycleId);

    if ($cycleResult['status'] !== 'success') {
        Response::error($cycleResult['message']);
    }

    /* Get Responses */
    $responseResult = $service->getResponses($cycleId);

    $responses = [];

    if ($responseResult['status'] === 'success') {
        $responses = $responseResult['data'];
    }

    $departments = $cycleResult['data']['departments'] ?? [];

    $deptSummary = [];

    foreach ($departments as $department) {

        if ((int)$department['is_active'] !== 1) {
            continue;
        }

        $deptId = (int)$department['dept_id'];

        $savedResponses = array_filter(
            $responses,
            function ($r) use ($deptId) {
                return (int)$r['dept_id'] === $deptId;
            }
        );

        $deptSummary[] = [
            'dept_id'         => $deptId,
            'status'          => $department['status'],
            'saved_responses' => count($savedResponses),
            'started_on'      => $department['started_on'],
            'completed_on'    => $department['completed_on']
        ];
    }

    Response::success(
        'Assessment resumed successfully',
        [
            'cycle' => $cycleResult['data']['cycle'],
            'departments' => $deptSummary,
            'total_departments' => count($deptSummary),
            'total_responses' => count($responses)
        ]
    );

} catch (Throwable $e) {

    Response::serverError(
        $e->getMessage()
    );
}
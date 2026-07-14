<?php

/**
 * start_cycle.php
 * -------------------------------------------------------
 * Create or return existing assessment cycle for
 * logged-in user's assigned facility.
 *
 * Uses:
 * $_SESSION['fac_id']
 * $_SESSION['u_id']
 *
 * URL:
 * /api/assessment/v1/start_cycle.php
 *
 * Method:
 * POST
 *
 * Body:
 * {
 *   "framework_code": "saqshi-nqas",
 *   "ass_period": 1,
 *   "instance_no": 1,
 *   "departments": [
 *      {"dept_id": 1, "is_active": 1},
 *      {"dept_id": 2, "is_active": 0}
 *   ]
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';

require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../service/DynamicAssessmentService.php';
require_once __DIR__ . '/../../assets/conn/db.php';

try {

    if (!isset($_SESSION['fac_id']) || (int)$_SESSION['fac_id'] <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if (!isset($_SESSION['u_id']) || (int)$_SESSION['u_id'] <= 0) {
        Response::error('User session not found');
    }

    $request = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($request)) {
        Response::validation([
            'request' => 'Invalid JSON request body'
        ]);
    }

    $frameworkCode = trim($request['framework_code'] ?? 'saqshi-nqas');
    $assPeriod = isset($request['ass_period']) ? (int)$request['ass_period'] : 0;
    $instanceNo = isset($request['instance_no']) ? (int)$request['instance_no'] : 1;

    if ($frameworkCode === '') {
        Response::validation([
            'framework_code' => 'Framework code is required'
        ]);
    }

    if ($assPeriod <= 0) {
        Response::validation([
            'ass_period' => 'Assessment period is required'
        ]);
    }

    if ($instanceNo <= 0) {
        Response::validation([
            'instance_no' => 'Instance number is required'
        ]);
    }

    $facId = (int)$_SESSION['fac_id'];
    $userId = (int)$_SESSION['u_id'];

    $service = new DynamicAssessmentService($con);

    /*
     * 1. Create or fetch existing cycle
     */
    $cycleResult = $service->createCycle([
        'fac_id'         => $facId,
        'ass_period'     => $assPeriod,
        'framework_code' => $frameworkCode,
        'instance_no'    => $instanceNo,
        'user_id'        => $userId
    ]);

    if ($cycleResult['status'] !== 'success') {
        Response::error($cycleResult['message']);
    }

    $cycleId = (int)$cycleResult['data']['cycle_id'];

    /*
     * 2. Optional: attach departments to cycle
     */
    $departmentResult = null;

    if (
        isset($request['departments'])
        && is_array($request['departments'])
        && count($request['departments']) > 0
    ) {
        $departmentResult = $service->addDepartments(
            $cycleId,
            $request['departments']
        );

        if ($departmentResult['status'] !== 'success') {
            Response::error($departmentResult['message']);
        }
    }

    Response::success(
        'Assessment cycle started successfully',
        [
            'cycle' => $cycleResult['data'],
            'departments' => $departmentResult['data'] ?? null
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
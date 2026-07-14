<?php

/**
 * start.php
 * -------------------------------------------------------
 * Start / Create Dynamic Assessment Cycle
 *
 * URL:
 * /api/assessment/v1/start.php
 *
 * Method:
 * POST
 *
 * Body:
 * {
 *   "fac_id": 1,
 *   "ass_period": 1,
 *   "framework_code": "sample-framework",
 *   "instance_no": 1,
 *   "user_id": 1
 * }
 * -------------------------------------------------------
 */
require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../service/DynamicAssessmentService.php';
require_once __DIR__ . '/../../assets/conn/db.php';

try {

    $request = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($request)) {
        Response::validation([
            'request' => 'Invalid JSON request body'
        ]);
    }

    $required = [
        'fac_id',
        'ass_period',
        'framework_code',
        'user_id'
    ];

    foreach ($required as $field) {
        if (!isset($request[$field]) || $request[$field] === '') {
            Response::validation([
                $field => $field . ' is required'
            ]);
        }
    }

    $service = new DynamicAssessmentService($con);

    $result = $service->createCycle([
        'fac_id'         => (int)$request['fac_id'],
        'ass_period'     => (int)$request['ass_period'],
        'framework_code' => trim($request['framework_code']),
        'instance_no'    => isset($request['instance_no'])
            ? (int)$request['instance_no']
            : 1,
        'user_id'        => (int)$request['user_id']
    ]);

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
<?php

/**
 * complete-cycle.php
 * -------------------------------------------------------
 * Mark full assessment cycle as completed.
 *
 * Rule:
 * Cycle can be completed only when all active departments
 * inside the cycle are completed.
 *
 * URL:
 * /api/assessment/v1/complete-cycle.php
 *
 * Method:
 * POST
 *
 * Body:
 * {
 *   "cycle_id": 1
 * }
 * -------------------------------------------------------
 */

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

    if (!isset($request['cycle_id']) || (int)$request['cycle_id'] <= 0) {
        Response::validation([
            'cycle_id' => 'cycle_id is required'
        ]);
    }

    $service = new DynamicAssessmentService($con);

    $result = $service->completeCycle(
        (int)$request['cycle_id']
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
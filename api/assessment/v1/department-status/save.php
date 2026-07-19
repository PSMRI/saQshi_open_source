<?php

/**
 * save.php
 * -------------------------------------------------------
 * Save department activation/deactivation status
 * for logged-in user's facility + assessment period.
 *
 * Uses session:
 * - fac_id
 * - u_id
 *
 * URL:
 * /api/assessment/v1/department-status/save.php
 *
 * Method:
 * POST
 *
 * Body bulk:
 * {
 *   "assessment_id": 1,
 *   "departments": [
 *     {"dept_id": 1, "is_active": 1},
 *     {"dept_id": 2, "is_active": 0}
 *   ]
 * }
 *
 * Body single:
 * {
 *   "assessment_id": 1,
 *   "dept_id": 1,
 *   "is_active": 1
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../../auth_api.php';
require_once __DIR__ . '/../../../service/DepartmentStatusService.php';
require_once __DIR__ . '/../../../assets/conn/db.php';

Security::requireMethod('POST');

try {

    $request = Security::jsonInput();

    $facId = SessionManager::facilityId();
    $userId = SessionManager::userId();

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if ($userId <= 0) {
        Response::error('User session not found');
    }

    if (
        (!isset($request['assessment_id']) || $request['assessment_id'] === '') &&
        (!isset($request['ass_period']) || $request['ass_period'] === '')
    ) {
        Response::validation([
            'assessment_id' => 'assessment_id is required'
        ]);
    }

    $assPeriod = isset($request['assessment_id']) && $request['assessment_id'] !== ''
        ? (int)$request['assessment_id']
        : (int)$request['ass_period'];

    if ($assPeriod <= 0) {
        Response::validation([
            'assessment_id' => 'Invalid assessment ID'
        ]);
    }

    $service = new DepartmentStatusService($con);

    /*
     * BULK SAVE
     */
    if (
        isset($request['departments']) &&
        is_array($request['departments'])
    ) {

        if (count($request['departments']) === 0) {
            Response::validation([
                'departments' => 'Departments list cannot be empty'
            ]);
        }

        $cleanDepartments = [];

        foreach ($request['departments'] as $index => $department) {

            if (!isset($department['dept_id']) || $department['dept_id'] === '') {
                Response::validation([
                    "departments.$index.dept_id" => 'dept_id is required'
                ]);
            }

            if (!isset($department['is_active']) || $department['is_active'] === '') {
                Response::validation([
                    "departments.$index.is_active" => 'is_active is required'
                ]);
            }

            $cleanDepartments[] = [
                'dept_id'   => (int)$department['dept_id'],
                'is_active' => (int)$department['is_active']
            ];
        }

        $result = $service->saveBulkStatus([
            'fac_id'      => $facId,
            'ass_period'  => $assPeriod,
            'user_id'     => $userId,
            'departments' => $cleanDepartments
        ]);

    } else {

        /*
         * SINGLE SAVE
         */
        if (!isset($request['dept_id']) || $request['dept_id'] === '') {
            Response::validation([
                'dept_id' => 'dept_id is required'
            ]);
        }

        if (!isset($request['is_active']) || $request['is_active'] === '') {
            Response::validation([
                'is_active' => 'is_active is required'
            ]);
        }

        $result = $service->saveStatus([
            'fac_id'     => $facId,
            'ass_period' => $assPeriod,
            'dept_id'    => (int)$request['dept_id'],
            'is_active'  => (int)$request['is_active'],
            'user_id'    => $userId
        ]);
    }

    if (($result['status'] ?? '') === 'success') {
        Event::dispatch('department.activation.saved', [
            'assessment_id' => $assPeriod,
            'fac_id' => $facId,
            'saved_by' => $userId,
            'data' => $result['data'] ?? []
        ]);

        Response::success(
            $result['message'],
            $result['data'] ?? []
        );
    }

    Response::error(
        $result['message'] ?? 'Department status save failed'
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

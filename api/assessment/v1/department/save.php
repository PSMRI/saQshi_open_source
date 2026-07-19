<?php

/**
 * save.php
 * -------------------------------------------------------
 * Activate departments for active assessment.
 *
 * Rule:
 * - Department can be activated once.
 * - Once activated, it cannot be deactivated.
 *
 * Method: POST
 *
 * Body:
 * {
 *   "assessment_id": 1,
 *   "departments": [
 *     {"dept_id": 25},
 *     {"dept_id": 26}
 *   ]
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../../auth_api.php';
require_once __DIR__ . '/../../../assets/conn/db.php';

Security::requireMethod('POST');

try {

    $request = Security::jsonInput();

    $facId  = SessionManager::facilityId();
    $userId = SessionManager::userId();

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if ($userId <= 0) {
        Response::error('User session not found');
    }

    $assessmentId = isset($request['assessment_id'])
        ? (int)$request['assessment_id']
        : 0;

    if ($assessmentId <= 0) {
        Response::validation([
            'assessment_id' => 'assessment_id is required'
        ]);
    }

    if (
        !isset($request['departments']) ||
        !is_array($request['departments']) ||
        count($request['departments']) === 0
    ) {
        Response::validation([
            'departments' => 'Departments list is required'
        ]);
    }

    /*
     * Check assessment belongs to logged-in facility and is active
     */
    $sqlCheck = "
        SELECT assessment_id
        FROM assessment_master
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND status = 'ACTIVE'
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlCheck);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $assessment = $stmt->get_result()->fetch_assoc();

    if (!$assessment) {
        Response::error('Active assessment not found for this facility');
    }

    $activated = [];
    $alreadyActive = [];

    foreach ($request['departments'] as $department) {

        $deptId = isset($department['dept_id'])
            ? (int)$department['dept_id']
            : 0;

        if ($deptId <= 0) {
            continue;
        }

        /*
         * If already exists, do not deactivate/update to 0.
         */
        $sqlExists = "
            SELECT assessment_dept_id AS id, is_active
            FROM assessment_department
            WHERE assessment_id = ?
              AND dept_id = ?
            LIMIT 1
        ";

        $stmt = $con->prepare($sqlExists);

        if (!$stmt) {
            Response::serverError('Prepare failed: ' . $con->error);
        }

        $stmt->bind_param('ii', $assessmentId, $deptId);
        $stmt->execute();

        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {

            if ((int)$existing['is_active'] === 1) {
                $alreadyActive[] = $deptId;
                continue;
            }

            $sqlUpdate = "
                UPDATE assessment_department
                SET is_active = 1,
                    activated_by = ?
                WHERE assessment_dept_id = ?
            ";

            $stmt = $con->prepare($sqlUpdate);

            if (!$stmt) {
                Response::serverError('Prepare failed: ' . $con->error);
            }

            $id = (int)$existing['id'];

            $stmt->bind_param('ii', $userId, $id);

            if (!$stmt->execute()) {
                Response::serverError('Update failed: ' . $stmt->error);
            }

            $activated[] = $deptId;
            continue;
        }

        /*
         * Insert active department
         */
        $sqlInsert = "
            INSERT INTO assessment_department
                (
                    assessment_id,
                    fac_id_fk,
                    dept_id,
                    is_active,
                    activated_by
                )
            VALUES
                (?, ?, ?, 1, ?)
        ";

        $stmt = $con->prepare($sqlInsert);

        if (!$stmt) {
            Response::serverError('Prepare failed: ' . $con->error);
        }

        $stmt->bind_param(
            'iiii',
            $assessmentId,
            $facId,
            $deptId,
            $userId
        );

        if (!$stmt->execute()) {
            Response::serverError('Insert failed: ' . $stmt->error);
        }

        $activated[] = $deptId;
    }

    Response::success(
        'Department activation saved successfully',
        [
            'assessment_id' => $assessmentId,
            'activated_departments' => $activated,
            'already_active_departments' => $alreadyActive
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

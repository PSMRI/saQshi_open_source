<?php

/**
 * complete_department.php
 * -------------------------------------------------------
 * Complete department assessment.
 *
 * Rule:
 * - Assessment must be ACTIVE.
 * - Department must be activated.
 * - Department must be IN_PROGRESS.
 * - Assessor information must be saved.
 * - At least one response must be saved.
 *
 * Method:
 * POST
 *
 * Body:
 * {
 *   "assessment_id": 1,
 *   "dept_id": 25,
 *   "force_complete": false
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

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

    $deptId = isset($request['dept_id'])
        ? (int)$request['dept_id']
        : 0;

    $forceComplete = isset($request['force_complete'])
        ? (bool)$request['force_complete']
        : false;

    if ($assessmentId <= 0) {
        Response::validation([
            'assessment_id' => 'assessment_id is required'
        ]);
    }

    if ($deptId <= 0) {
        Response::validation([
            'dept_id' => 'dept_id is required'
        ]);
    }

    /*
     * 1. Validate active assessment
     */
    $sqlAssessment = "
        SELECT
            assessment_id,
            assessment_name,
            framework_code,
            status
        FROM assessment_master
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND status = 'ACTIVE'
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlAssessment);

    if (!$stmt) {
        Response::serverError('Assessment prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $assessment = $stmt->get_result()->fetch_assoc();

    if (!$assessment) {
        Response::error('Active assessment not found for this facility');
    }

    /*
     * 2. Validate department
     */
    $sqlDepartment = "
        SELECT
            id,
            assessment_id,
            fac_id_fk,
            dept_id,
            is_active,
            status,
            started_on,
            completed_on,
            current_checkpoint_id
        FROM assessment_department
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
          AND is_active = 1
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlDepartment);

    if (!$stmt) {
        Response::serverError('Department prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iii',
        $assessmentId,
        $facId,
        $deptId
    );

    $stmt->execute();

    $department = $stmt->get_result()->fetch_assoc();

    if (!$department) {
        Response::error('Department is not activated for this assessment');
    }

    if (($department['status'] ?? '') === 'COMPLETED') {
        Response::success(
            'Department assessment already completed',
            [
                'completed' => true,
                'assessment_id' => $assessmentId,
                'dept_id' => $deptId,
                'status' => 'COMPLETED',
                'completed_on' => $department['completed_on']
            ]
        );
    }

    if (($department['status'] ?? '') !== 'IN_PROGRESS') {
        Response::error('Department assessment has not been started');
    }

    /*
     * 3. Validate assessor info exists
     */
    $sqlAssessor = "
        SELECT id
        FROM assessment_assessor_info
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlAssessor);

    if (!$stmt) {
        Response::serverError('Assessor info prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iii',
        $assessmentId,
        $facId,
        $deptId
    );

    $stmt->execute();

    $assessorInfo = $stmt->get_result()->fetch_assoc();

    if (!$assessorInfo) {
        Response::error('Please save assessor and assessee information before completing department');
    }

    /*
     * 4. Check saved responses
     *
     * Simplified design:
     * response assessment_id is the current assessment_id
     */
    $cycleId = $assessmentId;

    $sqlResponseCount = "
        SELECT COUNT(*) AS saved_count
        FROM assessment_response
        WHERE assessment_id = ?
          AND dept_id = ?
    ";

    $stmt = $con->prepare($sqlResponseCount);

    if (!$stmt) {
        Response::serverError('Response count prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'ii',
        $cycleId,
        $deptId
    );

    $stmt->execute();

    $countRow = $stmt->get_result()->fetch_assoc();

    $savedCount = (int)($countRow['saved_count'] ?? 0);

    if ($savedCount <= 0 && !$forceComplete) {
        Response::error('No responses saved. Department cannot be completed');
    }

    /*
     * 5. Complete department
     */
    $sqlComplete = "
        UPDATE assessment_department
        SET
            status = 'COMPLETED',
            completed_on = CURRENT_TIMESTAMP
        WHERE id = ?
    ";

    $stmt = $con->prepare($sqlComplete);

    if (!$stmt) {
        Response::serverError('Department complete prepare failed: ' . $con->error);
    }

    $departmentRowId = (int)$department['id'];

    $stmt->bind_param('i', $departmentRowId);

    if (!$stmt->execute()) {
        Response::serverError('Department completion failed: ' . $stmt->error);
    }

    Response::success(
        'Department assessment completed successfully',
        [
            'completed' => true,
            'assessment_id' => $assessmentId,
            'assessment_id' => $assessmentId,
            'dept_id' => $deptId,
            'saved_responses' => $savedCount,
            'status' => 'COMPLETED',
            'completed_on' => date('Y-m-d H:i:s')
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
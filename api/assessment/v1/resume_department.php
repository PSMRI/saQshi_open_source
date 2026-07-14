<?php

/**
 * resume_department.php
 * -------------------------------------------------------
 * Resume department assessment.
 *
 * It returns the checkpoint_id from where user should continue.
 *
 * Logic:
 * - If current_checkpoint_id exists, resume from it.
 * - If not, return checkpoint_id = 0 so UI loads first checkpoint.
 *
 * Method:
 * GET
 *
 * URL:
 * /api/assessment/v1/resume_department.php?assessment_id=1&dept_id=25
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

try {

    $facId  = SessionManager::facilityId();
    $userId = SessionManager::userId();

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if ($userId <= 0) {
        Response::error('User session not found');
    }

    $assessmentId = isset($_GET['assessment_id'])
        ? (int)$_GET['assessment_id']
        : 0;

    $deptId = isset($_GET['dept_id'])
        ? (int)$_GET['dept_id']
        : 0;

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
            start_date,
            end_date,
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
                'can_resume' => false,
                'is_completed' => true,
                'assessment' => [
                    'assessment_id' => (int)$assessment['assessment_id'],
                    'assessment_name' => $assessment['assessment_name'],
                    'framework_code' => $assessment['framework_code'],
                    'start_date' => $assessment['start_date'],
                    'end_date' => $assessment['end_date'],
                    'status' => $assessment['status']
                ],
                'department' => [
                    'dept_id' => (int)$department['dept_id'],
                    'status' => $department['status'],
                    'started_on' => $department['started_on'],
                    'completed_on' => $department['completed_on'],
                    'current_checkpoint_id' =>
                        $department['current_checkpoint_id'] !== null
                            ? (int)$department['current_checkpoint_id']
                            : null
                ],
                'resume_checkpoint_id' => null
            ]
        );
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
        Response::error('Please save assessor and assessee information before resuming assessment');
    }

    /*
     * 4. If department not started, mark as IN_PROGRESS
     */
    if (($department['status'] ?? '') === 'NOT_STARTED') {

        $sqlStart = "
            UPDATE assessment_department
            SET
                status = 'IN_PROGRESS',
                started_on = CURRENT_TIMESTAMP
            WHERE id = ?
        ";

        $stmt = $con->prepare($sqlStart);

        if (!$stmt) {
            Response::serverError('Department start prepare failed: ' . $con->error);
        }

        $departmentRowId = (int)$department['id'];

        $stmt->bind_param('i', $departmentRowId);

        if (!$stmt->execute()) {
            Response::serverError('Department start failed: ' . $stmt->error);
        }

        $department['status'] = 'IN_PROGRESS';
        $department['started_on'] = date('Y-m-d H:i:s');
    }

    /*
     * 5. Count saved responses
     */
    $cycleId = $assessmentId;

    $sqlCount = "
        SELECT COUNT(*) AS saved_count
        FROM assessment_response
        WHERE assessment_id = ?
          AND dept_id = ?
    ";

    $stmt = $con->prepare($sqlCount);

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

    /*
     * If no current checkpoint, UI should call get_checkpoint.php with checkpoint_id=0
     */
    $resumeCheckpointId =
        !empty($department['current_checkpoint_id'])
            ? (int)$department['current_checkpoint_id']
            : 0;

    Response::success(
        'Department assessment resume information fetched successfully',
        [
            'can_resume' => true,
            'is_completed' => false,
            'assessment_id' => $assessmentId,
            'resume_checkpoint_id' => $resumeCheckpointId,

            'assessment' => [
                'assessment_id' => (int)$assessment['assessment_id'],
                'assessment_name' => $assessment['assessment_name'],
                'framework_code' => $assessment['framework_code'],
                'start_date' => $assessment['start_date'],
                'end_date' => $assessment['end_date'],
                'status' => $assessment['status']
            ],

            'department' => [
                'dept_id' => (int)$department['dept_id'],
                'status' => $department['status'],
                'started_on' => $department['started_on'],
                'completed_on' => $department['completed_on'],
                'current_checkpoint_id' =>
                    $department['current_checkpoint_id'] !== null
                        ? (int)$department['current_checkpoint_id']
                        : null
            ],

            'progress' => [
                'saved_responses' => (int)($countRow['saved_count'] ?? 0),
                'current_checkpoint_id' =>
                    $department['current_checkpoint_id'] !== null
                        ? (int)$department['current_checkpoint_id']
                        : null
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
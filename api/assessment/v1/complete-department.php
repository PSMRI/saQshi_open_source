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
 * - Every configured checkpoint must have a saved response.
 *
 * Method:
 * POST
 *
 * Body:
 * {
 *   "assessment_id": 1,
 *   "dept_id": 25
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../core/AssessmentAccess.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../service/AssessmentSectionAssignmentService.php';

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

    AssessmentAccess::requireEditableByCurrentUser($con, $assessmentId, $facId);
    AssessmentSectionAssignmentService::requireOwner($con, $assessmentId, $deptId);

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
            assessment_dept_id AS id,
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
        SELECT info_id AS id
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

    $facilityStmt = $con->prepare('SELECT Health_facilty_type FROM facilities WHERE fac_id = ? LIMIT 1');
    if (!$facilityStmt) {
        Response::serverError('Facility type prepare failed: ' . $con->error);
    }
    $facilityStmt->bind_param('i', $facId);
    $facilityStmt->execute();
    $facility = $facilityStmt->get_result()->fetch_assoc() ?: [];
    $facilityTypeId = (int)($facility['Health_facilty_type'] ?? 0);

    try {
        $engine = FrameworkEngine::load((string)($assessment['framework_code'] ?? ''));
        $expectedCheckpointIds = array_values(array_unique(array_filter(array_map(
            static fn(array $checkpoint): int => (int)($checkpoint['csqa_id'] ?? 0),
            $engine->getCheckpoints($facilityTypeId, $deptId)
        ))));
    } catch (Throwable $e) {
        Response::serverError('Checklist configuration could not be loaded: ' . $e->getMessage());
    }

    if (!$expectedCheckpointIds) {
        Response::serverError('No checklist configuration is available for this class');
    }

    $savedCheckpointStmt = $con->prepare('SELECT DISTINCT checkpoint_id FROM assessment_response WHERE assessment_id = ? AND dept_id = ?');
    if (!$savedCheckpointStmt) {
        Response::serverError('Saved checkpoint prepare failed: ' . $con->error);
    }
    $savedCheckpointStmt->bind_param('ii', $assessmentId, $deptId);
    $savedCheckpointStmt->execute();
    $savedCheckpointIds = [];
    $savedCheckpointResult = $savedCheckpointStmt->get_result();
    while ($savedCheckpoint = $savedCheckpointResult->fetch_assoc()) {
        $savedCheckpointIds[] = (int)($savedCheckpoint['checkpoint_id'] ?? 0);
    }

    $completedCheckpointCount = count(array_intersect($expectedCheckpointIds, $savedCheckpointIds));
    if ($completedCheckpointCount < count($expectedCheckpointIds)) {
        Response::error('All checklist checkpoints must be completed before this class can be completed.', [
            'saved_checkpoints' => $completedCheckpointCount,
            'total_checkpoints' => count($expectedCheckpointIds),
            'pending_checkpoints' => count($expectedCheckpointIds) - $completedCheckpointCount
        ]);
    }

    /*
     * 5. Complete department
     */
    $sqlComplete = "
        UPDATE assessment_department
        SET
            status = 'COMPLETED',
            completed_on = CURRENT_TIMESTAMP
        WHERE assessment_dept_id = ?
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

    AssessmentSectionAssignmentService::complete($con, $assessmentId, $deptId);

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

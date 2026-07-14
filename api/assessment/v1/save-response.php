<?php

/**
 * save_response.php
 * -------------------------------------------------------
 * Save or update one checkpoint response.
 *
 * New simplified flow:
 * - responses are stored by assessment_id in assessment_response
 * - response saved against assessment_id + dept_id + checkpoint_id
 * - current checkpoint is updated in assessment_department
 *
 * Method:
 * POST
 *
 * Body:
 * {
 *   "assessment_id": 1,
 *   "dept_id": 25,
 *   "checkpoint_id": 21070,
 *   "response_value": 2,
 *   "score": 2,
 *   "remarks": "",
 *   "evidence_url": ""
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

    $checkpointId = isset($request['checkpoint_id'])
        ? (int)$request['checkpoint_id']
        : 0;

    $responseValue = isset($request['response_value'])
        ? trim((string)$request['response_value'])
        : '';

    $score = isset($request['score'])
        ? (float)$request['score']
        : (is_numeric($responseValue) ? (float)$responseValue : 0);

    $remarks = trim((string)($request['remarks'] ?? ''));
    $evidenceUrl = trim((string)($request['evidence_url'] ?? ''));

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

    if ($checkpointId <= 0) {
        Response::validation([
            'checkpoint_id' => 'checkpoint_id is required'
        ]);
    }

    if ($responseValue === '') {
        Response::validation([
            'response_value' => 'response_value is required'
        ]);
    }

    /*
     * 1. Validate active assessment
     */
    $sqlAssessment = "
        SELECT
            assessment_id,
            assessment_name,
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
     * 2. Validate department is active and in progress
     */
    $sqlDepartment = "
        SELECT
            id,
            status,
            is_active
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

    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();

    $department = $stmt->get_result()->fetch_assoc();

    if (!$department) {
        Response::error('Department is not activated for this assessment');
    }

    if (($department['status'] ?? '') === 'COMPLETED') {
        Response::error('Department already completed. Response cannot be changed');
    }

    if (($department['status'] ?? '') !== 'IN_PROGRESS') {
        Response::error('Please start department assessment before saving response');
    }

    /*
     * 3. Validate assessor info exists
     */
    $sqlInfo = "
        SELECT id
        FROM assessment_assessor_info
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlInfo);

    if (!$stmt) {
        Response::serverError('Assessor info prepare failed: ' . $con->error);
    }

    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();

    $assessorInfo = $stmt->get_result()->fetch_assoc();

    if (!$assessorInfo) {
        Response::error('Please save assessor information before saving response');
    }

    /*
     * 4. Save / update response
     *
     * Existing table:
     * assessment_response
     * unique key: assessment_id, dept_id, checkpoint_id
     *
     */
    $sqlSave = "
        INSERT INTO assessment_response
            (
                assessment_id,
                dept_id,
                checkpoint_id,
                response_value,
                score,
                remarks,
                evidence_url,
                updated_by
            )
        VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?
            )
        ON DUPLICATE KEY UPDATE
            response_value = VALUES(response_value),
            score = VALUES(score),
            remarks = VALUES(remarks),
            evidence_url = VALUES(evidence_url),
            updated_by = VALUES(updated_by),
            updated_on = CURRENT_TIMESTAMP
    ";

    $stmt = $con->prepare($sqlSave);

    if (!$stmt) {
        Response::serverError('Response save prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iiisdssi',
        $assessmentId,
        $deptId,
        $checkpointId,
        $responseValue,
        $score,
        $remarks,
        $evidenceUrl,
        $userId
    );

    if (!$stmt->execute()) {
        Response::serverError('Response save failed: ' . $stmt->error);
    }

    /*
     * 5. Update current checkpoint in assessment_department
     */
    $sqlUpdateDept = "
        UPDATE assessment_department
        SET current_checkpoint_id = ?
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
    ";

    $stmt = $con->prepare($sqlUpdateDept);

    if (!$stmt) {
        Response::serverError('Department progress prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iiii',
        $checkpointId,
        $assessmentId,
        $facId,
        $deptId
    );

    if (!$stmt->execute()) {
        Response::serverError('Department progress update failed: ' . $stmt->error);
    }

    /*
     * 6. Count saved responses
     */
    $sqlCount = "
        SELECT COUNT(*) AS saved_count
        FROM assessment_response
        WHERE assessment_id = ?
          AND dept_id = ?
    ";

    $stmt = $con->prepare($sqlCount);

    if (!$stmt) {
        Response::serverError('Progress count prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $deptId);
    $stmt->execute();

    $countRow = $stmt->get_result()->fetch_assoc();

    Event::dispatch('checklist.response.saved', [
        'assessment_id' => $assessmentId,
        'dept_id' => $deptId,
        'checkpoint_id' => $checkpointId,
        'response_value' => $responseValue,
        'score' => $score,
        'fac_id' => $facId,
        'updated_by' => $userId
    ]);

    Response::success(
        'Response saved successfully',
        [
            'assessment_id' => $assessmentId,
            'dept_id' => $deptId,
            'checkpoint_id' => $checkpointId,
            'response_value' => $responseValue,
            'score' => $score,
            'remarks' => $remarks,
            'evidence_url' => $evidenceUrl,
            'updated_by' => $userId,
            'progress' => [
                'saved_responses' => (int)($countRow['saved_count'] ?? 0),
                'current_checkpoint_id' => $checkpointId
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

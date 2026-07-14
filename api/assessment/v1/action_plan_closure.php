<?php

/**
 * action_plan_closure.php
 * -------------------------------------------------------
 * Update action plan closure status.
 *
 * Rule:
 * - If gap is closed:
 *      status = COMPLETED
 *      revised_score can be updated
 *      evidence is optional
 *
 * - If gap is not closed:
 *      status = IN_PROGRESS or OPEN
 *      original score remains unchanged
 *
 * Method:
 * POST
 *
 * Body:
 * {
 *   "assessment_id": 1,
 *   "dept_id": 25,
 *   "checkpoint_id": 21070,
 *   "is_gap_closed": true,
 *   "revised_score": 2,
 *   "closure_remarks": "Training completed and equipment arranged",
 *   "closure_evidence_url": ""
 * }
 * POST /api/assessment/v1/action_plan_closure.php
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

    $isGapClosed = isset($request['is_gap_closed'])
        ? (bool)$request['is_gap_closed']
        : false;

    $revisedScore = isset($request['revised_score'])
        ? (float)$request['revised_score']
        : null;

    $closureRemarks = trim((string)($request['closure_remarks'] ?? ''));

    $closureEvidenceUrl = trim(
        (string)($request['closure_evidence_url'] ?? '')
    );

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

    /*
     * 1. Validate assessment belongs to facility.
     */
    $sqlAssessment = "
        SELECT
            assessment_id,
            status
        FROM assessment_master
        WHERE assessment_id = ?
          AND fac_id_fk = ?
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
        Response::error('Assessment not found for this facility');
    }

    /*
     * 2. Validate department belongs to assessment.
     */
    $sqlDept = "
        SELECT id
        FROM assessment_department
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
          AND is_active = 1
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlDept);

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

    /*
     * 3. Validate original gap response exists.
     * Only score 0 or 1 can have closure update.
     */
    $cycleId = $assessmentId;

    $sqlGap = "
        SELECT
            response_id,
            score,
            response_value
        FROM assessment_response
        WHERE assessment_id = ?
          AND dept_id = ?
          AND checkpoint_id = ?
          AND score < 2
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlGap);

    if (!$stmt) {
        Response::serverError('Gap response prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iii',
        $cycleId,
        $deptId,
        $checkpointId
    );

    $stmt->execute();

    $gap = $stmt->get_result()->fetch_assoc();

    if (!$gap) {
        Response::error(
            'Gap closure can be updated only for score 0 or 1 checkpoints'
        );
    }

    /*
     * 4. Validate action plan exists.
     */
    $sqlPlan = "
        SELECT
            id,
            status,
            achievability
        FROM assessment_action_plan
        WHERE assessment_id = ?
          AND dept_id = ?
          AND checkpoint_id = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlPlan);

    if (!$stmt) {
        Response::serverError('Action plan prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iii',
        $assessmentId,
        $deptId,
        $checkpointId
    );

    $stmt->execute();

    $plan = $stmt->get_result()->fetch_assoc();

    if (!$plan) {
        Response::error('Action plan not found for this gap');
    }

    /*
     * 5. Decide closure values.
     */
    if ($isGapClosed) {

        if ($revisedScore === null) {
            Response::validation([
                'revised_score' => 'revised_score is required when gap is closed'
            ]);
        }

        if ($revisedScore < 0 || $revisedScore > 2) {
            Response::validation([
                'revised_score' => 'revised_score must be between 0 and 2'
            ]);
        }

        $status = 'COMPLETED';
        $closedBy = $userId;
        $closedOn = date('Y-m-d H:i:s');

    } else {

        $status = 'IN_PROGRESS';
        $revisedScore = null;
        $closedBy = null;
        $closedOn = null;
    }

    /*
     * 6. Update action plan closure.
     */
    $sqlUpdate = "
        UPDATE assessment_action_plan
        SET
            status = ?,
            closure_remarks = ?,
            closure_evidence_url = ?,
            revised_score = ?,
            closed_by = ?,
            closed_on = ?,
            updated_by = ?,
            updated_on = CURRENT_TIMESTAMP
        WHERE assessment_id = ?
          AND dept_id = ?
          AND checkpoint_id = ?
    ";

    $stmt = $con->prepare($sqlUpdate);

    if (!$stmt) {
        Response::serverError('Closure update prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'sssdisiiii',
        $status,
        $closureRemarks,
        $closureEvidenceUrl,
        $revisedScore,
        $closedBy,
        $closedOn,
        $userId,
        $assessmentId,
        $deptId,
        $checkpointId
    );

    if (!$stmt->execute()) {
        Response::serverError('Closure update failed: ' . $stmt->error);
    }

    /*
     * Important:
     * We are not changing original assessment score here.
     * Original score remains in assessment_response.
     * Revised score is stored separately in assessment_action_plan.
     */

    Response::success(
        $isGapClosed
            ? 'Gap closed successfully'
            : 'Gap closure updated as in progress',
        [
            'assessment_id' => $assessmentId,
            'dept_id' => $deptId,
            'checkpoint_id' => $checkpointId,

            'original_score' => (float)$gap['score'],
            'revised_score' => $revisedScore,

            'is_gap_closed' => $isGapClosed,
            'status' => $status,

            'closure_remarks' => $closureRemarks,
            'closure_evidence_url' => $closureEvidenceUrl,

            'closed_by' => $closedBy,
            'closed_on' => $closedOn
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

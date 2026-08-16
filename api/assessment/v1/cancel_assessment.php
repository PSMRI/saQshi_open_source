<?php

/**
 * cancel_assessment.php
 * -------------------------------------------------------
 * Cancels an ACTIVE assessment or an unclaimed PENDING assessor draft.
 *
 * Method:
 * POST
 *
 * URL:
 * /api/assessment/v1/cancel_assessment.php
 *
 * Body:
 * {
 *   "assessment_id": 1
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../core/AssessmentAccess.php';
require_once __DIR__ . '/../../core/ApiCache.php';

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

    AssessmentAccess::requireEditableByCurrentUser($con, $assessmentId, $facId);

    // The assessment status and its class claims are one unit of work.  A
    // partial cancellation leaves an IN_PROGRESS claim behind, which blocks
    // another mapped assessor from claiming that class.
    $con->begin_transaction();

    $sqlAssessment = "
        SELECT
            assessment_id,
            assessment_name,
            framework_code,
            fac_id_fk,
            start_date,
            end_date,
            status,
            assigned_assessor_id,
            assessment_source
        FROM assessment_master
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND status IN ('ACTIVE', 'PENDING')
        LIMIT 1
        FOR UPDATE
    ";

    $stmt = $con->prepare($sqlAssessment);

    if (!$stmt) {
        throw new RuntimeException('Assessment prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $assessment = $stmt->get_result()->fetch_assoc();

    if (!$assessment) {
        throw new RuntimeException('Active assessment or pending draft not found for this facility');
    }

    $isAssessorCycle = (int)($assessment['assigned_assessor_id'] ?? 0) > 0
        || strtoupper((string)($assessment['assessment_source'] ?? '')) === 'STATE_ASSESSOR';
    $sessionAssessorId = (int)($_SESSION['assessor_id'] ?? 0);
    if ($isAssessorCycle && ((int)($assessment['assigned_assessor_id'] ?? 0) <= 0 || $sessionAssessorId !== (int)$assessment['assigned_assessor_id'])) {
        throw new RuntimeException('Only the assigned assessor can cancel this assessment or draft.');
    }

    $sqlCancel = "
        UPDATE assessment_master
        SET
            status = 'CANCELLED',
            cancelled_on = CURRENT_TIMESTAMP
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND status IN ('ACTIVE', 'PENDING')
    ";

    $stmt = $con->prepare($sqlCancel);

    if (!$stmt) {
        throw new RuntimeException('Assessment cancellation prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);

    if (!$stmt->execute()) {
        throw new RuntimeException('Assessment cancellation failed: ' . $stmt->error);
    }

    // Cancellation ends all in-progress class/department ownership for this
    // assessment, so other mapped assessors can select those units.
    $release = $con->prepare("UPDATE assessment_section_assignee SET status = 'RELEASED', released_on = COALESCE(released_on, CURRENT_TIMESTAMP) WHERE assessment_id = ? AND fac_id_fk = ? AND status = 'IN_PROGRESS'");
    if (!$release) {
        throw new RuntimeException('Class claim release prepare failed: ' . $con->error);
    }
    $release->bind_param('ii', $assessmentId, $facId);
    if (!$release->execute()) {
        throw new RuntimeException('Class claim release failed: ' . $release->error);
    }
    $releasedClaims = $release->affected_rows;

    // Cancelled classes must not remain active in the assessment status list.
    $deactivate = $con->prepare("UPDATE assessment_department_status SET is_active = 0, status = 'INACTIVE', updated_on = CURRENT_TIMESTAMP WHERE fac_id_fk = ? AND assessment_id = ? AND is_active = 1");
    if (!$deactivate) {
        throw new RuntimeException('Class status cleanup prepare failed: ' . $con->error);
    }
    $deactivate->bind_param('ii', $facId, $assessmentId);
    if (!$deactivate->execute()) {
        throw new RuntimeException('Class status cleanup failed: ' . $deactivate->error);
    }

    $con->commit();

    ApiCache::forget('assessment:list:facility:' . $facId);

    Response::success(
        $assessment['status'] === 'PENDING'
            ? 'Pending assessment draft cancelled successfully.'
            : 'Assessment cancelled successfully. You can now create a new assessment.',
        [
            'cancelled' => true,
            'assessment_id' => $assessmentId,
            'assessment_name' => $assessment['assessment_name'],
            'framework_code' => $assessment['framework_code'],
            'fac_id' => $facId,
            'status' => 'CANCELLED',
            'cancelled_on' => date('Y-m-d H:i:s'),
            'cancelled_by' => $userId,
            'released_class_claims' => $releasedClaims
        ]
    );

} catch (Throwable $e) {
    if (isset($con) && $con instanceof mysqli) {
        $con->rollback();
    }
    Response::serverError($e->getMessage());
}

<?php

/**
 * cancel_assessment.php
 * -------------------------------------------------------
 * Cancels the ACTIVE assessment for the logged-in facility.
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

    $sqlAssessment = "
        SELECT
            assessment_id,
            assessment_name,
            framework_code,
            fac_id_fk,
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

    $sqlCancel = "
        UPDATE assessment_master
        SET
            status = 'CANCELLED',
            cancelled_on = CURRENT_TIMESTAMP
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND status = 'ACTIVE'
    ";

    $stmt = $con->prepare($sqlCancel);

    if (!$stmt) {
        Response::serverError('Assessment cancellation prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);

    if (!$stmt->execute()) {
        Response::serverError('Assessment cancellation failed: ' . $stmt->error);
    }

    Response::success(
        'Assessment cancelled successfully. You can now create a new assessment.',
        [
            'cancelled' => true,
            'assessment_id' => $assessmentId,
            'assessment_name' => $assessment['assessment_name'],
            'framework_code' => $assessment['framework_code'],
            'fac_id' => $facId,
            'status' => 'CANCELLED',
            'cancelled_on' => date('Y-m-d H:i:s'),
            'cancelled_by' => $userId
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

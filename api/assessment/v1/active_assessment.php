<?php

/**
 * active_assessment.php
 * -------------------------------------------------------
 * Returns active assessment for logged-in user's facility.
 *
 * Facility users receive their own active assessment. Assessor-led cycles
 * are kept separate so they do not block the facility self-assessment flow.
 *
 * Method:
 * GET
 *
 * URL:
 * /api/assessment/v1/active_assessment.php
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

try {

    $facId  = SessionManager::facilityId();
    $userId = SessionManager::userId();
    $requestedAssessmentId = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if ($userId <= 0) {
        Response::error('User session not found');
    }

    $isAssessorSession = (int)($_SESSION['assessor_id'] ?? 0) > 0
        || str_contains(strtolower((string)($_SESSION['role_name'] ?? $_SESSION['user_type'] ?? '')), 'assessor');
    $assessorId = (int)($_SESSION['assessor_id'] ?? 0);

    $scopeSql = $isAssessorSession
        ? "AND (assigned_assessor_id IS NOT NULL OR UPPER(COALESCE(assessment_source, '')) = 'STATE_ASSESSOR')"
        : "AND (assigned_assessor_id IS NULL OR assigned_assessor_id = 0)
           AND (assessment_source IS NULL OR UPPER(assessment_source) <> 'STATE_ASSESSOR')";
    $assessorSql = $isAssessorSession && $assessorId > 0 ? ' AND assigned_assessor_id = ?' : '';
    $assessmentIdSql = $requestedAssessmentId > 0 ? ' AND assessment_id = ?' : '';
    // PENDING is an assessor-only draft used while choosing a class.  It is
    // intentionally not shown as active work on the assessor dashboard.
    $statusSql = $isAssessorSession ? "AND status IN ('ACTIVE', 'PENDING')" : "AND status = 'ACTIVE'";

    $sql = "
        SELECT
            assessment_id,
            assessment_name,
            framework_code,
            fac_id_fk,
            start_date,
            end_date,
            status,
            created_by,
            assigned_assessor_id,
            assessment_source,
            created_on,
            updated_on,
            completed_on,
            cancelled_on
        FROM assessment_master
        WHERE fac_id_fk = ?
          {$statusSql}
          {$scopeSql}
          {$assessorSql}
          {$assessmentIdSql}
        ORDER BY assessment_id DESC
        LIMIT 1
    ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    if ($assessorSql !== '' && $assessmentIdSql !== '') {
        $stmt->bind_param('iii', $facId, $assessorId, $requestedAssessmentId);
    } elseif ($assessorSql !== '') {
        $stmt->bind_param('ii', $facId, $assessorId);
    } elseif ($assessmentIdSql !== '') {
        $stmt->bind_param('ii', $facId, $requestedAssessmentId);
    } else {
        $stmt->bind_param('i', $facId);
    }
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if (!$row) {
        Response::success(
            'No active assessment found',
            [
                'has_active' => false,
                'assessment' => null
            ]
        );
    }

    Response::success(
        'Active assessment fetched successfully',
        [
            'has_active' => true,
            'assessment' => [
                'assessment_id'   => (int)$row['assessment_id'],
                'assessment_name' => $row['assessment_name'],
                'framework_code'  => $row['framework_code'],
                'fac_id'          => (int)$row['fac_id_fk'],
                'start_date'      => $row['start_date'],
                'end_date'        => $row['end_date'],
                'status'          => $row['status'],
                'created_by'      => (int)$row['created_by'],
                'is_assessor_led' => (int)($row['assigned_assessor_id'] ?? 0) > 0 || strtoupper((string)($row['assessment_source'] ?? '')) === 'STATE_ASSESSOR',
                'is_assessor_session' => $isAssessorSession,
                'created_on'      => $row['created_on'],
                'updated_on'      => $row['updated_on'],
                'completed_on'    => $row['completed_on'],
                'cancelled_on'    => $row['cancelled_on']
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

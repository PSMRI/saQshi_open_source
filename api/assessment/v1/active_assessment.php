<?php

/**
 * active_assessment.php
 * -------------------------------------------------------
 * Returns active assessment for logged-in user's facility.
 *
 * Rule:
 * - One facility can have only one ACTIVE assessment.
 * - If no active assessment exists, return has_active = false.
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

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if ($userId <= 0) {
        Response::error('User session not found');
    }

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
            created_on,
            updated_on,
            completed_on,
            cancelled_on
        FROM assessment_master
        WHERE fac_id_fk = ?
          AND status = 'ACTIVE'
        ORDER BY assessment_id DESC
        LIMIT 1
    ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('i', $facId);
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
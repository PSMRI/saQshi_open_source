<?php

/**
 * complete_assessment.php
 * -------------------------------------------------------
 * Complete full assessment.
 *
 * Rule:
 * - Assessment must be ACTIVE.
 * - At least one department must be activated.
 * - All activated departments must be COMPLETED.
 *
 * Method:
 * POST
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

    /*
     * 1. Validate active assessment
     */
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

    /*
     * 2. Count activated departments
     */
    $sqlCount = "
        SELECT
            COUNT(*) AS total_active,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN status <> 'COMPLETED' THEN 1 ELSE 0 END) AS pending_count
        FROM assessment_department
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND is_active = 1
    ";

    $stmt = $con->prepare($sqlCount);

    if (!$stmt) {
        Response::serverError('Department count prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $count = $stmt->get_result()->fetch_assoc();

    $totalActive = (int)($count['total_active'] ?? 0);
    $completedCount = (int)($count['completed_count'] ?? 0);
    $pendingCount = (int)($count['pending_count'] ?? 0);

    if ($totalActive <= 0) {
        Response::error('No department activated for this assessment');
    }

    if ($pendingCount > 0) {

        /*
         * Return pending department list
         */
        $sqlPending = "
            SELECT
                dept_id,
                status,
                started_on,
                completed_on
            FROM assessment_department
            WHERE assessment_id = ?
              AND fac_id_fk = ?
              AND is_active = 1
              AND status <> 'COMPLETED'
            ORDER BY dept_id
        ";

        $stmt = $con->prepare($sqlPending);

        if (!$stmt) {
            Response::serverError('Pending department prepare failed: ' . $con->error);
        }

        $stmt->bind_param('ii', $assessmentId, $facId);
        $stmt->execute();

        $result = $stmt->get_result();

        $pendingDepartments = [];

        while ($row = $result->fetch_assoc()) {
            $pendingDepartments[] = [
                'dept_id' => (int)$row['dept_id'],
                'status' => $row['status'],
                'started_on' => $row['started_on'],
                'completed_on' => $row['completed_on']
            ];
        }

        Response::error(
            'Assessment cannot be completed. Some activated departments are still pending.',
            [
                'total_active_departments' => $totalActive,
                'completed_departments' => $completedCount,
                'pending_departments' => $pendingCount,
                'pending_department_list' => $pendingDepartments
            ]
        );
    }

    /*
     * 3. Complete assessment
     */
    $sqlComplete = "
        UPDATE assessment_master
        SET
            status = 'COMPLETED',
            completed_on = CURRENT_TIMESTAMP
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND status = 'ACTIVE'
    ";

    $stmt = $con->prepare($sqlComplete);

    if (!$stmt) {
        Response::serverError('Assessment completion prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);

    if (!$stmt->execute()) {
        Response::serverError('Assessment completion failed: ' . $stmt->error);
    }

    Event::dispatch('assessment.completed', [
        'assessment_id' => $assessmentId,
        'assessment_name' => $assessment['assessment_name'],
        'framework_code' => $assessment['framework_code'],
        'fac_id' => $facId,
        'total_active_departments' => $totalActive,
        'completed_departments' => $completedCount,
        'completed_by' => $userId
    ]);

    Response::success(
        'Assessment completed successfully',
        [
            'completed' => true,
            'assessment_id' => $assessmentId,
            'assessment_name' => $assessment['assessment_name'],
            'framework_code' => $assessment['framework_code'],
            'fac_id' => $facId,
            'status' => 'COMPLETED',
            'total_active_departments' => $totalActive,
            'completed_departments' => $completedCount,
            'completed_on' => date('Y-m-d H:i:s'),
            'completed_by' => $userId
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

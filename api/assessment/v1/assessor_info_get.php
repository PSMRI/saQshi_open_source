<?php

/**
 * assessor_info_get.php
 * -------------------------------------------------------
 * Get assessor / assessee information department-wise.
 *
 * Method: GET
 *
 * URL:
 * /api/assessment/v1/assessor_info_get.php?assessment_id=1&dept_id=25
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
     * Check assessment belongs to logged-in facility and is ACTIVE
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
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $assessment = $stmt->get_result()->fetch_assoc();

    if (!$assessment) {
        Response::error('Active assessment not found for this facility');
    }

    /*
     * Check department is active for assessment
     */
    $sqlDept = "
        SELECT
            dept_id,
            is_active,
            activated_by,
            activated_on
        FROM assessment_department_status
        WHERE ass_period_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
          AND is_active = 1
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlDept);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();

    $department = $stmt->get_result()->fetch_assoc();

    if (!$department) {
        Response::error('Department is not activated for this assessment');
    }

    /*
     * Get assessor info
     */
    $sqlInfo = "
        SELECT
            id,
            assessment_id,
            fac_id_fk,
            dept_id,
            assessment_date,
            assessment_type,

            assessor_name,
            assessor_designation,
            assessor_mobile,
            assessor_email,

            assessee_name,
            assessee_designation,
            assessee_mobile,
            assessee_email,

            remarks,
            saved_by,
            saved_on,
            updated_on
        FROM assessment_assessor_info
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlInfo);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();

    $info = $stmt->get_result()->fetch_assoc();

    if (!$info) {
        Response::success(
            'Assessor information not saved yet',
            [
                'has_info' => false,
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
                    'is_active' => (int)$department['is_active'],
                    'activated_by' => (int)$department['activated_by'],
                    'activated_on' => $department['activated_on']
                ],
                'info' => null
            ]
        );
    }

    Response::success(
        'Assessor information fetched successfully',
        [
            'has_info' => true,
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
                'is_active' => (int)$department['is_active'],
                'activated_by' => (int)$department['activated_by'],
                'activated_on' => $department['activated_on']
            ],
            'info' => [
                'id' => (int)$info['id'],
                'assessment_id' => (int)$info['assessment_id'],
                'fac_id' => (int)$info['fac_id_fk'],
                'dept_id' => (int)$info['dept_id'],
                'assessment_date' => $info['assessment_date'],
                'assessment_type' => $info['assessment_type'],

                'assessor_name' => $info['assessor_name'],
                'assessor_designation' => $info['assessor_designation'],
                'assessor_mobile' => $info['assessor_mobile'],
                'assessor_email' => $info['assessor_email'],

                'assessee_name' => $info['assessee_name'],
                'assessee_designation' => $info['assessee_designation'],
                'assessee_mobile' => $info['assessee_mobile'],
                'assessee_email' => $info['assessee_email'],

                'remarks' => $info['remarks'],
                'saved_by' => (int)$info['saved_by'],
                'saved_on' => $info['saved_on'],
                'updated_on' => $info['updated_on']
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

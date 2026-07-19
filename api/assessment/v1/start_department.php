<?php

/**
 * start_department.php
 * -------------------------------------------------------
 * Start or resume department assessment.
 *
 * New simplified flow:
 *
 * assessment_master
 *      ↓
 * assessment_department
 *      ↓
 * assessment_assessor_info
 *      ↓
 * assessment_response
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

Security::requireMethod('POST');

function startDepartmentStatusAssessmentColumn(mysqli $con): string
{
    $result = $con->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'assessment_department_status'
          AND COLUMN_NAME = 'assessment_id'
        LIMIT 1
    ");

    return ($result && $result->fetch_assoc()) ? 'assessment_id' : 'ass_period_id';
}

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
     * 2. Validate department is activated
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
        $departmentStatusColumn = startDepartmentStatusAssessmentColumn($con);

        $sqlStatus = "
            SELECT dept_id, is_active
            FROM assessment_department_status
            WHERE {$departmentStatusColumn} = ?
              AND fac_id_fk = ?
              AND dept_id = ?
              AND is_active = 1
            LIMIT 1
        ";

        $stmt = $con->prepare($sqlStatus);

        if (!$stmt) {
            Response::serverError('Department status prepare failed: ' . $con->error);
        }

        $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
        $stmt->execute();

        $activeStatus = $stmt->get_result()->fetch_assoc();

        if (!$activeStatus) {
            Response::error('Department is not activated for this assessment');
        }

        $sqlExistingDepartment = "
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
            LIMIT 1
        ";

        $stmt = $con->prepare($sqlExistingDepartment);

        if (!$stmt) {
            Response::serverError('Existing department prepare failed: ' . $con->error);
        }

        $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
        $stmt->execute();

        $existingDepartment = $stmt->get_result()->fetch_assoc();

        if ($existingDepartment) {
            $sqlReactivateDepartment = "
                UPDATE assessment_department
                SET is_active = 1,
                    activated_by = ?
                WHERE assessment_dept_id = ?
            ";

            $stmt = $con->prepare($sqlReactivateDepartment);

            if (!$stmt) {
                Response::serverError('Department reactivate prepare failed: ' . $con->error);
            }

            $existingDepartmentId = (int)$existingDepartment['id'];
            $stmt->bind_param('ii', $userId, $existingDepartmentId);

            if (!$stmt->execute()) {
                Response::serverError('Department reactivate failed: ' . $stmt->error);
            }

            $department = $existingDepartment;
            $department['is_active'] = 1;
        } else {
        $sqlInsertDepartment = "
            INSERT INTO assessment_department
                (
                    assessment_id,
                    fac_id_fk,
                    dept_id,
                    is_active,
                    status,
                    activated_by
                )
            VALUES
                (?, ?, ?, 1, 'NOT_STARTED', ?)
        ";

        $stmt = $con->prepare($sqlInsertDepartment);

        if (!$stmt) {
            Response::serverError('Department insert prepare failed: ' . $con->error);
        }

        $stmt->bind_param('iiii', $assessmentId, $facId, $deptId, $userId);

        if (!$stmt->execute()) {
            Response::serverError('Department insert failed: ' . $stmt->error);
        }

        $department = [
            'id' => (int)$stmt->insert_id,
            'assessment_id' => $assessmentId,
            'fac_id_fk' => $facId,
            'dept_id' => $deptId,
            'is_active' => 1,
            'status' => 'NOT_STARTED',
            'started_on' => null,
            'completed_on' => null,
            'current_checkpoint_id' => null
        ];
        }
    }

    if (($department['status'] ?? '') === 'COMPLETED') {
        Response::success(
            'Department assessment already completed',
            [
                'can_start' => false,
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
                    'current_checkpoint_id' => $department['current_checkpoint_id']
                ]
            ]
        );
    }

    /*
     * 3. Validate assessor / assessee information is saved
     */
    $sqlAssessor = "
        SELECT
            info_id AS id,
            assessment_date,
            assessment_type,
            assessor_name,
            assessor_designation,
            assessor_mobile,
            assessor_email,
            assessee_name,
            assessee_designation,
            assessee_mobile,
            assessee_email
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
        Response::error('Please save assessor and assessee information before starting assessment');
    }

    /*
     * 4. Start department if not already started
     */
    if (($department['status'] ?? 'NOT_STARTED') === 'NOT_STARTED') {

        $sqlStart = "
            UPDATE assessment_department
            SET
                status = 'IN_PROGRESS',
                started_on = CURRENT_TIMESTAMP
            WHERE assessment_dept_id = ?
        ";

        $stmt = $con->prepare($sqlStart);

        if (!$stmt) {
            Response::serverError('Start department prepare failed: ' . $con->error);
        }

        $departmentRowId = (int)$department['id'];

        $stmt->bind_param('i', $departmentRowId);

        if (!$stmt->execute()) {
            Response::serverError('Start department failed: ' . $stmt->error);
        }

        $department['status'] = 'IN_PROGRESS';
        $department['started_on'] = date('Y-m-d H:i:s');
    }

    /*
     * 5. Count saved responses
     */
    $sqlCount = "
        SELECT COUNT(*) AS saved_count
        FROM assessment_response
        WHERE assessment_id = ?
          AND dept_id = ?
    ";

    /*
     * Since we removed assessment_cycle,
     * responses are keyed by assessment_id
     * for response mapping.
     */
    $cycleId = $assessmentId;

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

    Response::success(
        'Department assessment started successfully',
        [
            'can_start' => true,
            'assessment_id' => $assessmentId,
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
                'status' => 'IN_PROGRESS',
                'started_on' => $department['started_on'],
                'completed_on' => $department['completed_on'],
                'current_checkpoint_id' => $department['current_checkpoint_id']
            ],
            'assessor_info' => [
                'id' => (int)$assessorInfo['id'],
                'assessment_date' => $assessorInfo['assessment_date'],
                'assessment_type' => $assessorInfo['assessment_type'],
                'assessor_name' => $assessorInfo['assessor_name'],
                'assessor_designation' => $assessorInfo['assessor_designation'],
                'assessor_mobile' => $assessorInfo['assessor_mobile'],
                'assessor_email' => $assessorInfo['assessor_email'],
                'assessee_name' => $assessorInfo['assessee_name'],
                'assessee_designation' => $assessorInfo['assessee_designation'],
                'assessee_mobile' => $assessorInfo['assessee_mobile'],
                'assessee_email' => $assessorInfo['assessee_email']
            ],
            'progress' => [
                'saved_responses' => (int)($countRow['saved_count'] ?? 0),
                'current_checkpoint_id' => $department['current_checkpoint_id']
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

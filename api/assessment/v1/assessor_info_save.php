<?php

/**
 * assessor_info_save.php
 * -------------------------------------------------------
 * Save assessor / assessee information department-wise.
 *
 * Rule:
 * - One assessor info per assessment + department.
 * - If already saved, return existing data and do not overwrite.
 *
 * Method: POST
 *
 * Body:
 * {
 *   "assessment_id": 1,
 *   "dept_id": 25,
 *   "assessment_date": "2026-06-25",
 *   "assessment_type": "INTERNAL",
 *
 *   "assessor_name": "Dr. Manish",
 *   "assessor_designation": "Medical Officer",
 *   "assessor_mobile": "8294386969",
 *   "assessor_email": "manish@example.com",
 *
 *   "assessee_name": "Dr. Manish Kumar",
 *   "assessee_designation": "Department In-charge",
 *   "assessee_mobile": "9876543211",
 *   "assessee_email": "rManish_kumar@example.com",
 *
 *   "remarks": "Optional remarks"
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../core/Crypto.php';

/**
 * Encrypts structured assessor/assessee personal fields before storing.
 * Designations remain plain because they are role/post values, not personal identity.
 */
function assessorInfoEncryptedPayload(
    string $assessorName,
    string $assessorMobile,
    string $assessorEmail,
    string $assesseeName,
    string $assesseeMobile,
    string $assesseeEmail
): array {
    return [
        'assessor_name' => Crypto::encrypt($assessorName),
        'assessor_mobile' => Crypto::encrypt($assessorMobile),
        'assessor_email' => Crypto::encrypt($assessorEmail),
        'assessee_name' => Crypto::encrypt($assesseeName),
        'assessee_mobile' => Crypto::encrypt($assesseeMobile),
        'assessee_email' => Crypto::encrypt($assesseeEmail)
    ];
}

function currentAssessorContext(mysqli $con): ?array
{
    $userId = SessionManager::userId();
    $username = strtoupper(trim(SessionManager::username()));

    $sql = "
        SELECT assessor_id, assessor_code, assessor_name, designation, mobile_no, mail_id
        FROM assessor_master
        WHERE is_active = 1
          AND (user_id = ? OR assessor_code = ?)
        ORDER BY user_id = ? DESC
        LIMIT 1
    ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('isi', $userId, $username, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        return null;
    }

    $row = Crypto::decryptFields($row, ['assessor_name', 'mobile_no', 'mail_id']);

    return [
        'assessor_id' => (int)$row['assessor_id'],
        'assessor_code' => $row['assessor_code'],
        'assessor_name' => $row['assessor_name'],
        'assessor_designation' => $row['designation'],
        'assessor_mobile' => $row['mobile_no'],
        'assessor_email' => $row['mail_id']
    ];
}

function assessorInfoDepartmentStatusColumn(mysqli $con): string
{
    $sql = "
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'assessment_department_status'
          AND COLUMN_NAME = 'assessment_id'
        LIMIT 1
    ";

    $result = $con->query($sql);

    if ($result && $result->fetch_assoc()) {
        return 'assessment_id';
    }

    return 'ass_period_id';
}

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

    $assessmentDate = trim($request['assessment_date'] ?? '');
    $assessmentType = strtoupper(trim($request['assessment_type'] ?? 'INTERNAL'));

    $assessorName = trim($request['assessor_name'] ?? '');
    $assessorDesignation = trim($request['assessor_designation'] ?? '');
    $assessorMobile = trim($request['assessor_mobile'] ?? '');
    $assessorEmail = trim($request['assessor_email'] ?? '');

    $currentAssessor = currentAssessorContext($con);

    if ($currentAssessor) {
        $assessorName = trim((string)$currentAssessor['assessor_name']);
        $assessorDesignation = trim((string)$currentAssessor['assessor_designation']);
        $assessorMobile = trim((string)$currentAssessor['assessor_mobile']);
        $assessorEmail = trim((string)$currentAssessor['assessor_email']);
    }

    $assesseeName = trim($request['assessee_name'] ?? '');
    $assesseeDesignation = trim($request['assessee_designation'] ?? '');
    $assesseeMobile = trim($request['assessee_mobile'] ?? '');
    $assesseeEmail = trim($request['assessee_email'] ?? '');

    $remarks = trim($request['remarks'] ?? '');

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

    if ($assessmentDate === '') {
        Response::validation([
            'assessment_date' => 'assessment_date is required'
        ]);
    }

    if (!strtotime($assessmentDate)) {
        Response::validation([
            'assessment_date' => 'Invalid assessment_date'
        ]);
    }

    $allowedTypes = [
        'INTERNAL',
        'EXTERNAL',
        'MOCK',
        'REASSESSMENT'
    ];

    if (!in_array($assessmentType, $allowedTypes, true)) {
        Response::validation([
            'assessment_type' => 'Invalid assessment_type'
        ]);
    }

    if ($assessorName === '') {
        Response::validation([
            'assessor_name' => 'assessor_name is required'
        ]);
    }

    if ($assesseeName === '') {
        Response::validation([
            'assessee_name' => 'assessee_name is required'
        ]);
    }

    if ($assessorEmail !== '' && !filter_var($assessorEmail, FILTER_VALIDATE_EMAIL)) {
        Response::validation([
            'assessor_email' => 'Invalid assessor_email'
        ]);
    }

    if ($assesseeEmail !== '' && !filter_var($assesseeEmail, FILTER_VALIDATE_EMAIL)) {
        Response::validation([
            'assessee_email' => 'Invalid assessee_email'
        ]);
    }

    /*
     * Check assessment belongs to logged-in facility and is ACTIVE
     */
    $sqlAssessment = "
        SELECT assessment_id, assessment_name, status
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
     * Check department is activated for this assessment.
     */
    $departmentStatusColumn = assessorInfoDepartmentStatusColumn($con);

    $sqlDept = "
        SELECT dept_id
        FROM assessment_department_status
        WHERE {$departmentStatusColumn} = ?
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

    $activeDept = $stmt->get_result()->fetch_assoc();

    if (!$activeDept) {
        Response::error('Department is not activated for this assessment');
    }

    /*
     * If already saved, update it. The UI opens the form only when the user
     * intentionally edits the saved assessor/assessee information.
     */
    $sqlExisting = "
        SELECT *
        FROM assessment_assessor_info
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlExisting);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();

    $existing = $stmt->get_result()->fetch_assoc();

    $encryptedPersonalFields = assessorInfoEncryptedPayload(
        $assessorName,
        $assessorMobile,
        $assessorEmail,
        $assesseeName,
        $assesseeMobile,
        $assesseeEmail
    );

    if ($existing) {
        $existingId = (int)($existing['info_id'] ?? $existing['id'] ?? 0);

        $sqlUpdate = "
            UPDATE assessment_assessor_info
            SET assessment_date = ?,
                assessment_type = ?,
                assessor_name = ?,
                assessor_designation = ?,
                assessor_mobile = ?,
                assessor_email = ?,
                assessee_name = ?,
                assessee_designation = ?,
                assessee_mobile = ?,
                assessee_email = ?,
                remarks = ?,
                saved_by = ?
            WHERE info_id = ?
        ";

        $stmt = $con->prepare($sqlUpdate);

        if (!$stmt) {
            Response::serverError('Prepare failed: ' . $con->error);
        }

        $stmt->bind_param(
            'sssssssssssii',
            $assessmentDate,
            $assessmentType,
            $encryptedPersonalFields['assessor_name'],
            $assessorDesignation,
            $encryptedPersonalFields['assessor_mobile'],
            $encryptedPersonalFields['assessor_email'],
            $encryptedPersonalFields['assessee_name'],
            $assesseeDesignation,
            $encryptedPersonalFields['assessee_mobile'],
            $encryptedPersonalFields['assessee_email'],
            $remarks,
            $userId,
            $existingId
        );

        if (!$stmt->execute()) {
            Response::serverError('Update failed: ' . $stmt->error);
        }

        Response::success(
            'Assessor information updated successfully',
            [
                'saved' => true,
                'updated' => true,
                'already_exists' => true,
                'info' => [
                    'id' => $existingId,
                    'assessment_id' => $assessmentId,
                    'fac_id' => $facId,
                    'dept_id' => $deptId,
                    'assessment_date' => $assessmentDate,
                    'assessment_type' => $assessmentType,
                    'assessor_name' => $assessorName,
                    'assessor_designation' => $assessorDesignation,
                    'assessor_mobile' => $assessorMobile,
                    'assessor_email' => $assessorEmail,
                    'assessee_name' => $assesseeName,
                    'assessee_designation' => $assesseeDesignation,
                    'assessee_mobile' => $assesseeMobile,
                    'assessee_email' => $assesseeEmail,
                    'remarks' => $remarks,
                    'saved_by' => $userId
                ]
            ]
        );
    }

    /*
     * Save new assessor / assessee info.
     */

    $sqlInsert = "
        INSERT INTO assessment_assessor_info
            (
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
                saved_by
            )
        VALUES
            (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?
            )
    ";

    $stmt = $con->prepare($sqlInsert);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iiisssssssssssi',
        $assessmentId,
        $facId,
        $deptId,
        $assessmentDate,
        $assessmentType,

        $encryptedPersonalFields['assessor_name'],
        $assessorDesignation,
        $encryptedPersonalFields['assessor_mobile'],
        $encryptedPersonalFields['assessor_email'],

        $encryptedPersonalFields['assessee_name'],
        $assesseeDesignation,
        $encryptedPersonalFields['assessee_mobile'],
        $encryptedPersonalFields['assessee_email'],

        $remarks,
        $userId
    );

    if (!$stmt->execute()) {
        Response::serverError('Save failed: ' . $stmt->error);
    }

    $id = (int)$stmt->insert_id;

    Response::success(
        'Assessor information saved successfully',
        [
            'saved' => true,
            'already_exists' => false,
            'info' => [
                'id' => $id,
                'assessment_id' => $assessmentId,
                'fac_id' => $facId,
                'dept_id' => $deptId,
                'assessment_date' => $assessmentDate,
                'assessment_type' => $assessmentType,
                'assessor_name' => $assessorName,
                'assessor_designation' => $assessorDesignation,
                'assessor_mobile' => $assessorMobile,
                'assessor_email' => $assessorEmail,
                'assessee_name' => $assesseeName,
                'assessee_designation' => $assesseeDesignation,
                'assessee_mobile' => $assesseeMobile,
                'assessee_email' => $assesseeEmail,
                'remarks' => $remarks,
                'saved_by' => $userId
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

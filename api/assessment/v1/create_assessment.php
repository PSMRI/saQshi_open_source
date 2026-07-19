<?php

/**
 * create_assessment.php
 * -------------------------------------------------------
 * Creates a new assessment for logged-in user's facility.
 *
 * Rule:
 * - One facility can have only one ACTIVE assessment.
 * - New assessment can be created only after previous one is
 *   COMPLETED or CANCELLED.
 *
 * Method:
 * POST
 *
 * URL:
 * /api/assessment/v1/create_assessment.php
 *
 * Body:
 * {
 *   "assessment_name": "Internal Assessment",
 *   "framework_code": "saqshi-nqas",
 *   "start_date": "2026-06-25",
 *   "end_date": "2026-07-25"
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('POST');

function createAssessmentFrameworkLabel(string $frameworkCode): string
{
    $code = strtolower($frameworkCode);

    if (strpos($code, 'musqan') !== false) {
        return 'MusQan';
    }

    if (strpos($code, 'laqshya') !== false) {
        return 'LaQshya';
    }

    return 'NQAS';
}

function createAssessmentFacilityName(mysqli $con, int $facId): string
{
    $stmt = $con->prepare("
        SELECT fac_name
        FROM facilities
        WHERE fac_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return 'Facility';
    }

    $stmt->bind_param('i', $facId);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    $name = trim((string)($row['fac_name'] ?? ''));

    return $name !== '' ? $name : 'Facility';
}

function createAssessmentAutoName(mysqli $con, int $facId, string $frameworkCode, string $startDate): string
{
    $facilityName = createAssessmentFacilityName($con, $facId);
    $frameworkName = createAssessmentFrameworkLabel($frameworkCode);
    $timestamp = strtotime($startDate) ?: time();
    $period = date('F Y', $timestamp);

    return $facilityName . ' - ' . $frameworkName . ' - ' . $period;
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

    $assessmentName = trim(
        (string)($request['assessment_name'] ?? '')
    );

    $frameworkCode = trim(
        $request['framework_code'] ?? 'saqshi-nqas'
    );

    $startDate = trim(
        $request['start_date'] ?? date('Y-m-d')
    );

    $endDate = trim(
        $request['end_date'] ?? date('Y-m-d', strtotime('+1 month'))
    );

    if ($frameworkCode === '') {
        Response::validation([
            'framework_code' => 'Framework code is required'
        ]);
    }

    if (!strtotime($startDate)) {
        Response::validation([
            'start_date' => 'Invalid start date'
        ]);
    }

    if (!strtotime($endDate)) {
        Response::validation([
            'end_date' => 'Invalid end date'
        ]);
    }

    if (strtotime($endDate) < strtotime($startDate)) {
        Response::validation([
            'end_date' => 'End date cannot be before start date'
        ]);
    }

    if ($assessmentName === '') {
        $assessmentName = createAssessmentAutoName($con, $facId, $frameworkCode, $startDate);
    }

    /*
     * Check active assessment
     */
    $sqlCheck = "
        SELECT
            assessment_id,
            assessment_name,
            framework_code,
            fac_id_fk,
            start_date,
            end_date,
            status,
            created_by,
            created_on
        FROM assessment_master
        WHERE fac_id_fk = ?
          AND status = 'ACTIVE'
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlCheck);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('i', $facId);
    $stmt->execute();

    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        Response::success(
            'Active assessment already exists for this facility',
            [
                'created' => false,
                'assessment' => [
                    'assessment_id' => (int)$existing['assessment_id'],
                    'assessment_name' => $existing['assessment_name'],
                    'framework_code' => $existing['framework_code'],
                    'fac_id' => (int)$existing['fac_id_fk'],
                    'start_date' => $existing['start_date'],
                    'end_date' => $existing['end_date'],
                    'status' => $existing['status'],
                    'created_by' => (int)$existing['created_by'],
                    'created_on' => $existing['created_on']
                ]
            ]
        );
    }

    /*
     * Create new active assessment
     */
    $sqlInsert = "
        INSERT INTO assessment_master
            (
                assessment_name,
                framework_code,
                fac_id_fk,
                start_date,
                end_date,
                status,
                created_by
            )
        VALUES
            (
                ?, ?, ?, ?, ?, 'ACTIVE', ?
            )
    ";

    $stmt = $con->prepare($sqlInsert);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'ssissi',
        $assessmentName,
        $frameworkCode,
        $facId,
        $startDate,
        $endDate,
        $userId
    );

    if (!$stmt->execute()) {
        Response::serverError('Assessment creation failed: ' . $stmt->error);
    }

    $assessmentId = (int)$stmt->insert_id;

    Event::dispatch('assessment.created', [
        'assessment_id' => $assessmentId,
        'assessment_name' => $assessmentName,
        'framework_code' => $frameworkCode,
        'fac_id' => $facId,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'status' => 'ACTIVE',
        'created_by' => $userId
    ]);

    Response::success(
        'Assessment created successfully',
        [
            'created' => true,
            'assessment' => [
                'assessment_id' => $assessmentId,
                'assessment_name' => $assessmentName,
                'framework_code' => $frameworkCode,
                'fac_id' => $facId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'ACTIVE',
                'created_by' => $userId
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

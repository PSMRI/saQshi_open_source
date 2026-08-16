<?php

/**
 * save_response.php
 * -------------------------------------------------------
 * Save or update one checkpoint response.
 *
 * New simplified flow:
 * - responses are stored by assessment_id in assessment_response
 * - response saved against assessment_id + dept_id + checkpoint_id
 * - current checkpoint is updated in assessment_department
 *
 * Method:
 * POST
 *
 * Body:
 * {
 *   "assessment_id": 1,
 *   "dept_id": 25,
 *   "checkpoint_id": 21070,
 *   "response_value": 2,
 *   "score": 2,
 *   "remarks": "",
 *   "evidence_url": ""
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../service/ResponseTypeService.php';
require_once __DIR__ . '/../../core/AssessmentAccess.php';
require_once __DIR__ . '/../../core/ApiCache.php';
require_once __DIR__ . '/../../service/AssessmentSectionAssignmentService.php';

Security::requireMethod('POST');

/** Finds the assessment column used by the installed department-status table. */
function responseDepartmentStatusAssessmentColumn(mysqli $con): string
{
    $result = $con->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_department_status' AND COLUMN_NAME = 'assessment_id' LIMIT 1");
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

    $checkpointId = isset($request['checkpoint_id'])
        ? (int)$request['checkpoint_id']
        : 0;

    $remarks = trim((string)($request['remarks'] ?? ''));
    $evidenceUrl = trim((string)($request['evidence_url'] ?? ''));

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

    AssessmentAccess::requireEditableByCurrentUser($con, $assessmentId, $facId);
    AssessmentSectionAssignmentService::requireOwner($con, $assessmentId, $deptId);

    ResponseTypeService::ensureSchema($con);

    /*
     * 1. Validate active assessment
     */
    $sqlAssessment = "
        SELECT
            assessment_id,
            assessment_name,
            framework_code,
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
     * 2. Validate department is active and in progress
     */
    $sqlDepartment = "
        SELECT
            assessment_dept_id AS id,
            status,
            is_active
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

    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();

    $department = $stmt->get_result()->fetch_assoc();

    if (!$department) {
        Response::error('Department is not activated for this assessment');
    }

    if (($department['status'] ?? '') === 'COMPLETED') {
        Response::error('Department already completed. Response cannot be changed');
    }

    if (($department['status'] ?? '') !== 'IN_PROGRESS') {
        Response::error('Please start department assessment before saving response');
    }

    /*
     * 3. Validate assessor info exists
     */
    $sqlInfo = "
        SELECT info_id AS id
        FROM assessment_assessor_info
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlInfo);

    if (!$stmt) {
        Response::serverError('Assessor info prepare failed: ' . $con->error);
    }

    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();

    $assessorInfo = $stmt->get_result()->fetch_assoc();

    if (!$assessorInfo) {
        Response::error('Please save assessor information before saving response');
    }

    /*
     * 4. Calculate response from framework JSON.
     * The server owns the scoring rule; the browser only sends the selected
     * or entered response value.
     */
    $frameworkCode = trim((string)($assessment['framework_code'] ?? 'saqshi-nqas'));
    $engine = FrameworkEngine::load($frameworkCode !== '' ? $frameworkCode : 'saqshi-nqas');
    $checkpoint = $engine->getCheckpointById($checkpointId) ?? [];
    $calculated = ResponseTypeService::evaluate($checkpoint, $request);

    $responseValue = $calculated['response_value'];
    $responseType = $calculated['response_type'];
    $responseJson = $calculated['response_json'];
    $score = $calculated['score'];
    $maxScore = $calculated['max_score'];
    $scoreStatus = $calculated['score_status'];

    /*
     * 5. Save / update response
     *
     * Existing table:
     * assessment_response
     * unique key: assessment_id, dept_id, checkpoint_id
     *
     */
    $sqlSave = "
        INSERT INTO assessment_response
            (
                assessment_id,
                dept_id,
                checkpoint_id,
                response_value,
                response_type,
                response_json,
                score,
                max_score,
                score_status,
                remarks,
                evidence_url,
                updated_by
            )
        VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ON DUPLICATE KEY UPDATE
            response_value = VALUES(response_value),
            response_type = VALUES(response_type),
            response_json = VALUES(response_json),
            score = VALUES(score),
            max_score = VALUES(max_score),
            score_status = VALUES(score_status),
            remarks = VALUES(remarks),
            evidence_url = VALUES(evidence_url),
            updated_by = VALUES(updated_by),
            updated_on = CURRENT_TIMESTAMP
    ";

    $stmt = $con->prepare($sqlSave);

    if (!$stmt) {
        Response::serverError('Response save prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iiisssddsssi',
        $assessmentId,
        $deptId,
        $checkpointId,
        $responseValue,
        $responseType,
        $responseJson,
        $score,
        $maxScore,
        $scoreStatus,
        $remarks,
        $evidenceUrl,
        $userId
    );

    if (!$stmt->execute()) {
        Response::serverError('Response save failed: ' . $stmt->error);
    }

    /*
     * Store indexed response fields for future domain-neutral analytics.
     */
    ResponseTypeService::replaceFieldIndex(
        $con,
        $assessmentId,
        $deptId,
        $checkpointId,
        $userId,
        $calculated['fields'] ?? []
    );

    /*
     * 6. Update current checkpoint in assessment_department
     */
    $sqlUpdateDept = "
        UPDATE assessment_department
        SET current_checkpoint_id = ?
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
    ";

    $stmt = $con->prepare($sqlUpdateDept);

    if (!$stmt) {
        Response::serverError('Department progress prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iiii',
        $checkpointId,
        $assessmentId,
        $facId,
        $deptId
    );

    if (!$stmt->execute()) {
        Response::serverError('Department progress update failed: ' . $stmt->error);
    }

    /*
     * A scope is not the whole department.  Compare saved responses with every
     * checkpoint configured for this department before closing it, then close
     * the assessment only when every active department is complete.
     */
    $facilityTypeId = (int)($checkpoint['_fac_type_id'] ?? 0);
    $departmentCheckpoints = $facilityTypeId > 0 ? $engine->getCheckpoints($facilityTypeId, $deptId) : [];
    $expectedCheckpointIds = array_values(array_unique(array_filter(array_map(
        static fn(array $item): int => (int)($item['csqa_id'] ?? 0),
        $departmentCheckpoints
    ))));

    $savedCheckpointIds = [];
    $stmt = $con->prepare('SELECT DISTINCT checkpoint_id FROM assessment_response WHERE assessment_id = ? AND dept_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $assessmentId, $deptId);
        $stmt->execute();
        $savedResult = $stmt->get_result();
        while ($savedRow = $savedResult->fetch_assoc()) {
            $savedCheckpointIds[] = (int)$savedRow['checkpoint_id'];
        }
    }

    $completedCheckpointCount = count(array_intersect($expectedCheckpointIds, $savedCheckpointIds));
    $departmentCompleted = count($expectedCheckpointIds) > 0
        && $completedCheckpointCount >= count($expectedCheckpointIds);
    $assessmentCompleted = false;

    if ($departmentCompleted) {
        $stmt = $con->prepare("UPDATE assessment_department SET status = 'COMPLETED', completed_on = CURRENT_TIMESTAMP WHERE assessment_id = ? AND fac_id_fk = ? AND dept_id = ? AND is_active = 1");
        if ($stmt) {
            $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
            $stmt->execute();
        }

        // Release the assessor's class lock when all checkpoints are saved.
        // Without this, a completed class remains IN_PROGRESS and cannot be
        // selected by another mapped assessor.
        $assignment = $con->prepare("UPDATE assessment_section_assignee SET status = 'COMPLETED', completed_on = COALESCE(completed_on, CURRENT_TIMESTAMP) WHERE assessment_id = ? AND fac_id_fk = ? AND dept_id = ? AND status = 'IN_PROGRESS'");
        if ($assignment) {
            $assignment->bind_param('iii', $assessmentId, $facId, $deptId);
            $assignment->execute();
        }

        $statusColumn = responseDepartmentStatusAssessmentColumn($con);
        $activeDepartments = $con->prepare("SELECT ads.dept_id, COALESCE(ad.status, 'NOT_STARTED') AS status FROM assessment_department_status ads LEFT JOIN assessment_department ad ON ad.assessment_id = ads.{$statusColumn} AND ad.fac_id_fk = ads.fac_id_fk AND ad.dept_id = ads.dept_id AND ad.is_active = 1 WHERE ads.{$statusColumn} = ? AND ads.fac_id_fk = ? AND ads.is_active = 1");
        $allDepartmentsCompleted = true;
        $activeDepartmentCount = 0;
        if ($activeDepartments) {
            $activeDepartments->bind_param('ii', $assessmentId, $facId);
            $activeDepartments->execute();
            $activeResult = $activeDepartments->get_result();
            while ($activeRow = $activeResult->fetch_assoc()) {
                $activeDepartmentCount++;
                if (($activeRow['status'] ?? '') !== 'COMPLETED') $allDepartmentsCompleted = false;
            }
        } else {
            $allDepartmentsCompleted = false;
        }

        // An assessment can contain multiple active classes/departments.
        // Completing one class must never close the parent assessment while
        // another class is still pending.
        if ($activeDepartmentCount > 0 && $allDepartmentsCompleted) {
            $stmt = $con->prepare("UPDATE assessment_master SET status = 'COMPLETED', completed_on = CURRENT_TIMESTAMP WHERE assessment_id = ? AND fac_id_fk = ? AND status = 'ACTIVE'");
            if ($stmt) {
                $stmt->bind_param('ii', $assessmentId, $facId);
                $stmt->execute();
                $assessmentCompleted = $stmt->affected_rows > 0;
            }
        }
    }

    /*
     * 7. Count saved responses
     */
    $sqlCount = "
        SELECT COUNT(*) AS saved_count
        FROM assessment_response
        WHERE assessment_id = ?
          AND dept_id = ?
    ";

    $stmt = $con->prepare($sqlCount);

    if (!$stmt) {
        Response::serverError('Progress count prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $deptId);
    $stmt->execute();

    $countRow = $stmt->get_result()->fetch_assoc();

    Event::dispatch('checklist.response.saved', [
        'assessment_id' => $assessmentId,
        'dept_id' => $deptId,
        'checkpoint_id' => $checkpointId,
        'response_type' => $responseType,
        'response_value' => $responseValue,
        'score' => $score,
        'max_score' => $maxScore,
        'score_status' => $scoreStatus,
        'fac_id' => $facId,
        'updated_by' => $userId
    ]);

    ApiCache::forget('assessment:list:facility:' . $facId);

    Response::success(
        'Response saved successfully',
        [
            'assessment_id' => $assessmentId,
            'dept_id' => $deptId,
            'checkpoint_id' => $checkpointId,
            'response_type' => $responseType,
            'response_value' => $responseValue,
            'response_json' => json_decode($responseJson, true),
            'score' => $score,
            'max_score' => $maxScore,
            'score_status' => $scoreStatus,
            'remarks' => $remarks,
            'evidence_url' => $evidenceUrl,
            'updated_by' => $userId,
            'progress' => [
                'saved_responses' => (int)($countRow['saved_count'] ?? 0),
                'current_checkpoint_id' => $checkpointId,
                'expected_checkpoints' => count($expectedCheckpointIds),
                'completed_checkpoints' => $completedCheckpointCount
            ],
            'lifecycle' => [
                'department_completed' => $departmentCompleted,
                'assessment_completed' => $assessmentCompleted
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

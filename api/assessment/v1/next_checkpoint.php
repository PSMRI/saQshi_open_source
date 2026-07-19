<?php

/**
 * next_checkpoint.php
 * -------------------------------------------------------
 * Find next checkpoint for selected assessment department.
 *
 * Method:
 * GET
 *
 * URL:
 * /api/assessment/v1/next_checkpoint.php
 *   ?assessment_id=1
 *   &dept_id=25
 *   &concern_id=4
 *   &subtype_id=96
 *   &checkpoint_id=21070
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
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

    $assessmentId = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;
    $deptId       = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
    $concernId    = isset($_GET['concern_id']) ? (int)$_GET['concern_id'] : 0;
    $subtypeId    = isset($_GET['subtype_id']) ? (int)$_GET['subtype_id'] : 0;
    $checkpointId = isset($_GET['checkpoint_id']) ? (int)$_GET['checkpoint_id'] : 0;

    if ($assessmentId <= 0) {
        Response::validation(['assessment_id' => 'assessment_id is required']);
    }

    if ($deptId <= 0) {
        Response::validation(['dept_id' => 'dept_id is required']);
    }

    if ($concernId <= 0) {
        Response::validation(['concern_id' => 'concern_id is required']);
    }

    if ($subtypeId <= 0) {
        Response::validation(['subtype_id' => 'subtype_id is required']);
    }

    if ($checkpointId <= 0) {
        Response::validation(['checkpoint_id' => 'checkpoint_id is required']);
    }

    /*
     * 1. Validate active assessment and get framework
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

    $frameworkCode = $assessment['framework_code'] ?: 'saqshi-nqas';

    /*
     * 2. Validate department is activated and not completed
     */
    $sqlDept = "
        SELECT
            assessment_dept_id AS id,
            dept_id,
            is_active,
            status
        FROM assessment_department
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
          AND is_active = 1
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlDept);

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
        Response::error('Department assessment is already completed');
    }

    if (($department['status'] ?? '') !== 'IN_PROGRESS') {
        Response::error('Please start department assessment first');
    }

    /*
     * 3. Get facility type from facilities.json
     */
    $facilityJsonPath = __DIR__ . '/../../config/masters/facilities.json';

    if (!file_exists($facilityJsonPath)) {
        Response::serverError('facilities.json not found');
    }

    $states = json_decode(
        file_get_contents($facilityJsonPath),
        true
    );

    if (!is_array($states)) {
        Response::serverError('Invalid facilities.json format');
    }

    $facTypeId = 0;

    foreach ($states as $state) {
        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {
                        if ((int)($facility['fac_id'] ?? 0) === $facId) {
                            $facTypeId = (int)($facility['fac_type_id'] ?? 0);
                            break 5;
                        }
                    }
                }
            }
        }
    }

    if ($facTypeId <= 0) {
        Response::error('Facility type not found for assigned facility');
    }

    /*
     * 4. Load checkpoints
     */
    $engine = FrameworkEngine::load($frameworkCode);

    $checkpoints = $engine->getCheckpoints(
        $facTypeId,
        $deptId,
        $concernId,
        $subtypeId
    );

    $cleanCheckpoints = [];

    foreach ($checkpoints as $checkpoint) {
        $cpId = (int)($checkpoint['csqa_id'] ?? 0);

        if ($cpId > 0) {
            $cleanCheckpoints[] = $checkpoint;
        }
    }

    usort($cleanCheckpoints, function ($a, $b) {
        return (int)($a['csqa_id'] ?? 0) <=> (int)($b['csqa_id'] ?? 0);
    });

    if (empty($cleanCheckpoints)) {
        Response::success(
            'No checkpoints found',
            [
                'has_next' => false,
                'next_checkpoint_id' => null,
                'total_checkpoints' => 0
            ]
        );
    }

    /*
     * 5. Find current and next
     */
    $currentIndex = null;

    foreach ($cleanCheckpoints as $index => $checkpoint) {
        if ((int)($checkpoint['csqa_id'] ?? 0) === $checkpointId) {
            $currentIndex = $index;
            break;
        }
    }

    if ($currentIndex === null) {
        Response::error('Current checkpoint not found in selected scope');
    }

    $nextIndex = $currentIndex + 1;

    if (!isset($cleanCheckpoints[$nextIndex])) {
        Response::success(
            'No next checkpoint. This is the last checkpoint',
            [
                'has_next' => false,
                'next_checkpoint_id' => null,
                'current_checkpoint_id' => $checkpointId,
                'position' => [
                    'current' => $currentIndex + 1,
                    'total' => count($cleanCheckpoints),
                    'is_last' => true
                ]
            ]
        );
    }

    $nextCheckpointId = (int)$cleanCheckpoints[$nextIndex]['csqa_id'];

    Response::success(
        'Next checkpoint fetched successfully',
        [
            'has_next' => true,
            'current_checkpoint_id' => $checkpointId,
            'next_checkpoint_id' => $nextCheckpointId,
            'position' => [
                'current' => $currentIndex + 1,
                'next' => $nextIndex + 1,
                'total' => count($cleanCheckpoints),
                'is_last' => false
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

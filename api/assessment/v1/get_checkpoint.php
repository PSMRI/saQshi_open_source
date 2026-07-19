<?php

/**
 * get_checkpoint.php
 * -------------------------------------------------------
 * Load one checkpoint at a time for department assessment.
 *
 * Method:
 * GET
 *
 * URL:
 * /api/assessment/v1/get_checkpoint.php
 *   ?assessment_id=1
 *   &dept_id=25
 *   &concern_id=4
 *   &subtype_id=96
 *   &checkpoint_id=0
 *
 * checkpoint_id = 0 means first checkpoint.
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

    $frameworkCode = $assessment['framework_code'] ?: 'saqshi-nqas';

    /*
     * 2. Validate department is activated and in progress
     */
    $sqlDept = "
        SELECT
            assessment_dept_id AS id,
            dept_id,
            is_active,
            status,
            current_checkpoint_id,
            started_on,
            completed_on
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
     * 3. Check assessor information exists
     */
    $sqlAssessor = "
        SELECT info_id AS id
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

    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();

    $assessorInfo = $stmt->get_result()->fetch_assoc();

    if (!$assessorInfo) {
        Response::error('Please save assessor and assessee information before loading checkpoint');
    }

    /*
     * 4. Get facility type from facilities.json
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

    $facilityData = null;

    foreach ($states as $state) {
        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {

                        if ((int)($facility['fac_id'] ?? 0) === $facId) {

                            $facilityData = [
                                'fac_id'          => (int)$facility['fac_id'],
                                'fac_name'        => $facility['fac_name'] ?? '',
                                'fac_type_id'     => (int)($facility['fac_type_id'] ?? 0),
                                'facilities_type' => $facility['facilities_type'] ?? ''
                            ];

                            break 5;
                        }
                    }
                }
            }
        }
    }

    if (!$facilityData) {
        Response::error('Assigned facility not found in facilities.json');
    }

    $facTypeId = (int)$facilityData['fac_type_id'];

    if ($facTypeId <= 0) {
        Response::error('Facility type not found for assigned facility');
    }

    /*
     * 5. Load checkpoints from framework JSON
     */
    $engine = FrameworkEngine::load($frameworkCode);

    $departmentConfig = $engine->getDepartmentById(
        $facTypeId,
        $deptId
    );

    if (!$departmentConfig) {
        Response::error('Department not found in framework');
    }

    $concernConfig = $engine->getConcernById(
        $facTypeId,
        $deptId,
        $concernId
    );

    if (!$concernConfig) {
        Response::error('Concern not found in framework');
    }

    $subtypeConfig = $engine->getSubtypeById(
        $facTypeId,
        $deptId,
        $concernId,
        $subtypeId
    );

    if (!$subtypeConfig) {
        Response::error('Subtype not found in framework');
    }

    $checkpoints = $engine->getCheckpoints(
        $facTypeId,
        $deptId,
        $concernId,
        $subtypeId
    );

    if (empty($checkpoints)) {
        Response::success(
            'No checkpoints found for selected scope',
            [
                'has_checkpoint' => false,
                'checkpoint' => null,
                'total_checkpoints' => 0
            ]
        );
    }

    /*
     * 6. Clean and sort checkpoints
     */
    $cleanCheckpoints = [];

    foreach ($checkpoints as $checkpoint) {

        $cpId = (int)($checkpoint['csqa_id'] ?? 0);

        if ($cpId <= 0) {
            continue;
        }

        $cleanCheckpoints[] = $checkpoint;
    }

    usort($cleanCheckpoints, function ($a, $b) {
        return (int)($a['csqa_id'] ?? 0) <=> (int)($b['csqa_id'] ?? 0);
    });

    if (empty($cleanCheckpoints)) {
        Response::success(
            'No valid checkpoints found',
            [
                'has_checkpoint' => false,
                'checkpoint' => null,
                'total_checkpoints' => 0
            ]
        );
    }

    /*
     * 7. Decide which checkpoint to load
     */
    $selectedIndex = 0;

    if ($checkpointId > 0) {

        foreach ($cleanCheckpoints as $index => $checkpoint) {
            if ((int)($checkpoint['csqa_id'] ?? 0) === $checkpointId) {
                $selectedIndex = $index;
                break;
            }
        }

    } elseif (!empty($department['current_checkpoint_id'])) {

        $currentCheckpointId = (int)$department['current_checkpoint_id'];

        foreach ($cleanCheckpoints as $index => $checkpoint) {
            if ((int)($checkpoint['csqa_id'] ?? 0) === $currentCheckpointId) {
                $selectedIndex = $index;
                break;
            }
        }
    }

    $selectedCheckpoint = $cleanCheckpoints[$selectedIndex];

    $selectedCheckpointId = (int)$selectedCheckpoint['csqa_id'];

    /*
     * 8. Fetch already saved response for this checkpoint
     */
    $sqlResponse = "
        SELECT
            response_id,
            response_value,
            score,
            remarks,
            evidence_url,
            updated_by,
            updated_on
        FROM assessment_response
        WHERE assessment_id = ?
          AND dept_id = ?
          AND checkpoint_id = ?
        LIMIT 1
    ";

    /*
     * responses are keyed by assessment_id
     */
    $cycleId = $assessmentId;

    $stmt = $con->prepare($sqlResponse);

    if (!$stmt) {
        Response::serverError('Response prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iii',
        $cycleId,
        $deptId,
        $selectedCheckpointId
    );

    $stmt->execute();

    $savedResponse = $stmt->get_result()->fetch_assoc();

    /*
     * 9. Navigation IDs
     */
    $previousCheckpointId = null;
    $nextCheckpointId = null;

    if (isset($cleanCheckpoints[$selectedIndex - 1])) {
        $previousCheckpointId = (int)$cleanCheckpoints[$selectedIndex - 1]['csqa_id'];
    }

    if (isset($cleanCheckpoints[$selectedIndex + 1])) {
        $nextCheckpointId = (int)$cleanCheckpoints[$selectedIndex + 1]['csqa_id'];
    }

    /*
     * 10. Response options fallback
     */
    $responseConfig = $selectedCheckpoint['response'] ?? [
        'type' => 'radio',
        'mandatory' => true,
        'remarks_required' => false,
        'evidence_required' => false,
        'options' => [
            ['label' => 'Fully Compliant', 'value' => 2, 'score' => 2],
            ['label' => 'Partially Compliant', 'value' => 1, 'score' => 1],
            ['label' => 'Non Compliant', 'value' => 0, 'score' => 0]
        ]
    ];

    Response::success(
        'Checkpoint fetched successfully',
        [
            'has_checkpoint' => true,

            'assessment' => [
                'assessment_id' => (int)$assessment['assessment_id'],
                'assessment_name' => $assessment['assessment_name'],
                'framework_code' => $frameworkCode,
                'start_date' => $assessment['start_date'],
                'end_date' => $assessment['end_date'],
                'status' => $assessment['status']
            ],

            'facility' => $facilityData,

            'department' => [
                'dept_id' => $deptId,
                'dept_name' => $departmentConfig['dept_name'] ?? '',
                'status' => $department['status'],
                'current_checkpoint_id' => $department['current_checkpoint_id']
            ],

            'concern' => [
                'concern_id' => $concernId,
                'concern_name' => $concernConfig['concern_name'] ?? '',
                'concern_des' => $concernConfig['concern_des'] ?? ''
            ],

            'subtype' => [
                'c_subtype_id' => $subtypeId,
                'Reference_No' => $subtypeConfig['Reference_No'] ?? '',
                'area_of_con_subtypedeatils' =>
                    $subtypeConfig['area_of_con_subtypedeatils'] ?? ''
            ],

            'position' => [
                'current' => $selectedIndex + 1,
                'total' => count($cleanCheckpoints),
                'previous_checkpoint_id' => $previousCheckpointId,
                'next_checkpoint_id' => $nextCheckpointId,
                'is_first' => $previousCheckpointId === null,
                'is_last' => $nextCheckpointId === null
            ],

            'checkpoint' => [
                'checkpoint_id' => $selectedCheckpointId,
                'csqa_id' => $selectedCheckpointId,
                'csqa_reference_id' =>
                    $selectedCheckpoint['csqa_reference_id'] ?? '',
                'Measurable_Element' =>
                    $selectedCheckpoint['Measurable_Element'] ?? '',
                'Checkpoint' =>
                    $selectedCheckpoint['Checkpoint'] ?? '',
                'Assessment_Method' =>
                    $selectedCheckpoint['Assessment_Method'] ?? '',
                'Means_of_Verification' =>
                    $selectedCheckpoint['Means_of_Verification'] ?? '',
                'action_plan' =>
                    $selectedCheckpoint['action_plan'] ?? '',
                'program_tag' =>
                    $selectedCheckpoint['program_tag'] ?? '',
                'response' => $responseConfig
            ],

            'saved_response' => $savedResponse ? [
                'response_id' => (int)$savedResponse['response_id'],
                'response_value' => $savedResponse['response_value'],
                'score' => (float)$savedResponse['score'],
                'remarks' => $savedResponse['remarks'],
                'evidence_url' => $savedResponse['evidence_url'],
                'updated_by' => (int)$savedResponse['updated_by'],
                'updated_on' => $savedResponse['updated_on']
            ] : null
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

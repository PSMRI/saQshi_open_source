<?php

/**
 * checkpoints.php
 * -------------------------------------------------------
 * Load checklist dynamically from nested SaQshi framework JSON.
 *
 * Flow:
 * Logged-in user facility
 * â†’ facility type
 * â†’ department
 * â†’ concern
 * â†’ subtype
 * â†’ assessment method
 * â†’ checkpoints
 *
 * URL:
 * /api/framework/v1/checkpoints.php
 *   ?framework=saqshi-nqas
 *   &assessment_id=1
 *   &dept_id=1
 *   &concern_id=1
 *   &subtype_id=1
 *   &assessment_method=SI
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';

require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../service/DepartmentStatusService.php';
require_once __DIR__ . '/../../service/ResponseTypeService.php';
require_once __DIR__ . '/../../assets/conn/db.php';

try {

    ResponseTypeService::ensureSchema($con);

    if (!isset($_SESSION['fac_id']) || (int)$_SESSION['fac_id'] <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    $facId = (int)$_SESSION['fac_id'];

    $frameworkCode = trim($_GET['framework'] ?? 'saqshi-nqas');
    $assPeriod = isset($_GET['assessment_id'])
        ? (int)$_GET['assessment_id']
        : (int)($_GET['ass_period'] ?? 0);
    $deptId = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
    $concernId = isset($_GET['concern_id']) ? (int)$_GET['concern_id'] : 0;
    $subtypeId = isset($_GET['subtype_id']) ? (int)$_GET['subtype_id'] : 0;
    $assessmentMethod = strtoupper(trim($_GET['assessment_method'] ?? ''));

    if ($frameworkCode === '') {
        Response::validation([
            'framework' => 'Framework code is required'
        ]);
    }

    if ($assPeriod <= 0) {
        Response::validation([
            'assessment_id' => 'Assessment ID is required'
        ]);
    }

    if ($deptId <= 0) {
        Response::validation([
            'dept_id' => 'Department ID is required'
        ]);
    }

    if ($concernId <= 0) {
        Response::validation([
            'concern_id' => 'Concern ID is required'
        ]);
    }

    if ($subtypeId <= 0) {
        Response::validation([
            'subtype_id' => 'Subtype ID is required'
        ]);
    }

    /*
     * 1. Load logged-in user's facility from facilities.json
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

        $stateId = $state['state_id'] ?? $state['stateid'] ?? null;
        $stateName = $state['state_name'] ?? $state['statename'] ?? '';

        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {

                        if ((int)($facility['fac_id'] ?? 0) === $facId) {

                            $facilityData = [
                                'fac_id'          => (int)$facility['fac_id'],
                                'fac_name'        => $facility['fac_name'] ?? '',
                                'nin_no'          => $facility['nin_no'] ?? null,
                                'fac_type_id'     => (int)($facility['fac_type_id'] ?? 0),
                                'facilities_type' => $facility['facilities_type'] ?? '',

                                'block_id'        => (int)($block['block_id'] ?? 0),
                                'block_name'      => $block['block_name'] ?? '',

                                'dist_id'         => (int)($district['dist_id'] ?? 0),
                                'dist_name'       => $district['dist_name'] ?? '',

                                'division_id'     => (int)($division['division_id'] ?? 0),
                                'division_name'   => $division['division_name'] ?? '',

                                'state_id'        => $stateId !== null ? (int)$stateId : null,
                                'state_name'      => $stateName
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
     * 2. Check department active status for facility + period
     */
    $departmentService = new DepartmentStatusService($con);

    $isActive = $departmentService->isDepartmentActive(
        $facId,
        $assPeriod,
        $deptId
    );

    if (!$isActive) {
        Response::success(
            'Department is inactive for this facility and assessment period',
            [
                'facility' => $facilityData,
                'ass_period' => $assPeriod,
                'assessment_id' => $assPeriod,
                'dept_id' => $deptId,
                'is_active' => false,
                'checkpoints' => []
            ]
        );
    }

    /*
     * 3. Load framework
     */
    $engine = FrameworkEngine::load($frameworkCode);

    $department = $engine->getDepartmentById(
        $facTypeId,
        $deptId
    );

    if (!$department) {
        Response::notFound('Department not found for this facility type');
    }

    $concern = $engine->getConcernById(
        $facTypeId,
        $deptId,
        $concernId
    );

    if (!$concern) {
        Response::notFound('Concern not found for this department');
    }

    $subtype = $engine->getSubtypeById(
        $facTypeId,
        $deptId,
        $concernId,
        $subtypeId
    );

    if (!$subtype) {
        Response::notFound('Subtype not found for this concern');
    }

    /*
     * 4. Load checkpoints by selected scope
     */
    $allCheckpoints = $engine->getCheckpoints(
        $facTypeId,
        $deptId,
        $concernId,
        $subtypeId
    );

/*
 * 5. Filter checkpoints by assessment method.
 * Blank assessment_method means load all methods for this scope.
 */
$filteredCheckpoints = [];

foreach ($allCheckpoints as $checkpoint) {

    $rawMethod =
        $checkpoint['Assessment_Method']
        ?? $checkpoint['assessment_method']
        ?? '';

    $rawMethod = strtoupper((string)$rawMethod);

    // Normalize multiple spaces and spaces around slash
    $rawMethod = preg_replace('/\s+/', ' ', $rawMethod);
    $rawMethod = preg_replace('/\s*\/\s*/', '/', $rawMethod);
    $rawMethod = trim($rawMethod);

    $selectedMethod = strtoupper(trim($assessmentMethod));
    $selectedMethod = preg_replace('/\s+/', ' ', $selectedMethod);
    $selectedMethod = preg_replace('/\s*\/\s*/', '/', $selectedMethod);

    /*
     * If checkpoint has no assessment method,
     * include it so checklist does not disappear.
     */
    if ($rawMethod === '') {
        $normalizedMethods = [];
    } else {
        $normalizedMethods = [$rawMethod];

        /*
         * Also add individual method parts:
         * SI/OB => SI, OB
         */
        foreach (explode('/', $rawMethod) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $normalizedMethods[] = $part;
            }
        }

        $normalizedMethods = array_values(array_unique($normalizedMethods));
    }

    /*
     * Match either:
     * selected SI/OB equals raw SI/OB
     * selected SI exists inside SI/OB
     * selected OB exists inside SI/OB
     */
    $methodMatched = false;

    if ($selectedMethod === '') {
        $methodMatched = true;
    } elseif ($rawMethod === '') {
        $methodMatched = true;
    } elseif ($selectedMethod === $rawMethod) {
        $methodMatched = true;
    } elseif (in_array($selectedMethod, $normalizedMethods, true)) {
        $methodMatched = true;
    }

    if (!$methodMatched) {
        continue;
    }

    $filteredCheckpoints[] = [
        'csqa_id' => $checkpoint['csqa_id'] ?? '',
        'csqa_reference_id' => $checkpoint['csqa_reference_id'] ?? '',
        'Measurable_Element' => $checkpoint['Measurable_Element'] ?? '',
        'Checkpoint' => $checkpoint['Checkpoint'] ?? '',
        'Assessment_Method' => $normalizedMethods,
        'Means_of_Verification' => $checkpoint['Means_of_Verification'] ?? '',
        'action_plan' => $checkpoint['action_plan'] ?? '',
        'program_tag' => $checkpoint['program_tag'] ?? '',
        'response' => $checkpoint['response'] ?? [
            'type' => 'radio',
            'mandatory' => true,
            'remarks_required' => false,
            'evidence_required' => false,
            'options' => [
                ['label' => 'Fully Compliant', 'value' => 2, 'score' => 2],
                ['label' => 'Partially Compliant', 'value' => 1, 'score' => 1],
                ['label' => 'Non Compliant', 'value' => 0, 'score' => 0]
            ]
        ]
    ];
}

/*
 * 6. Attach already saved responses for this assessment/cycle.
 * ass_period is the assessment_id in the simplified flow.
 */
$checkpointIds = array_values(array_filter(array_map(
    fn($checkpoint) => (int)($checkpoint['csqa_id'] ?? 0),
    $filteredCheckpoints
)));

$savedMap = [];

if (!empty($checkpointIds)) {
    $placeholders = implode(',', array_fill(0, count($checkpointIds), '?'));

    $sqlSaved = "
        SELECT
            response_id,
            checkpoint_id,
            response_value,
            response_type,
            response_json,
            score,
            max_score,
            score_status,
            remarks,
            evidence_url,
            updated_by,
            updated_on
        FROM assessment_response
        WHERE assessment_id = ?
          AND dept_id = ?
          AND checkpoint_id IN ($placeholders)
    ";

    $stmtSaved = $con->prepare($sqlSaved);

    if (!$stmtSaved) {
        Response::serverError('Saved response prepare failed: ' . $con->error);
    }

    $types = str_repeat('i', count($checkpointIds) + 2);
    $params = array_merge([$assPeriod, $deptId], $checkpointIds);
    $stmtSaved->bind_param($types, ...$params);
    $stmtSaved->execute();

    $savedResult = $stmtSaved->get_result();

    while ($row = $savedResult->fetch_assoc()) {
        $savedMap[(int)$row['checkpoint_id']] = [
            'response_id' => (int)$row['response_id'],
            'response_value' => $row['response_value'],
            'response_type' => $row['response_type'],
            'response_json' => json_decode((string)($row['response_json'] ?? ''), true),
            'score' => $row['score'] !== null ? (float)$row['score'] : null,
            'max_score' => $row['max_score'] !== null ? (float)$row['max_score'] : 0,
            'score_status' => $row['score_status'] ?? 'SCORED',
            'remarks' => $row['remarks'],
            'evidence_url' => $row['evidence_url'],
            'updated_by' => (int)$row['updated_by'],
            'updated_on' => $row['updated_on']
        ];
    }
}

foreach ($filteredCheckpoints as &$checkpoint) {
    $checkpointId = (int)($checkpoint['csqa_id'] ?? 0);
    $checkpoint['saved_response'] = $savedMap[$checkpointId] ?? null;
}
unset($checkpoint);

    Response::success(
        'Checkpoints fetched successfully',
        [

        'debug' => [
            'total_before_method_filter' => count($allCheckpoints),
            'total_after_method_filter' => count($filteredCheckpoints),
            'selected_method' => $assessmentMethod,
            'dept_id' => $deptId,
            'concern_id' => $concernId,
            'subtype_id' => $subtypeId
        ],

            'framework' => $frameworkCode,
            'facility' => $facilityData,
            'ass_period' => $assPeriod,
            'assessment_id' => $assPeriod,
            'department' => [
                'fac_dept_id' => (int)($department['fac_dept_id'] ?? 0),
                'dept_name' => $department['dept_name'] ?? ''
            ],
            'concern' => [
                'concern_id' => (int)($concern['concern_id'] ?? 0),
                'concern_name' => $concern['concern_name'] ?? '',
                'concern_des' => $concern['concern_des'] ?? ''
            ],
            'subtype' => [
                'c_subtype_id' => (int)($subtype['c_subtype_id'] ?? 0),
                'Reference_No' => $subtype['Reference_No'] ?? '',
                'area_of_con_subtypedeatils' => $subtype['area_of_con_subtypedeatils'] ?? ''
            ],
            'assessment_method' => $assessmentMethod,
            'is_active' => true,
            'total' => count($filteredCheckpoints),
            'checkpoints' => $filteredCheckpoints
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

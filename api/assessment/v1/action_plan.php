<?php

/**
 * action_plan.php
 * -------------------------------------------------------
 * Generate action plan from gap analysis.
 *
 * Gap condition:
 * - score = 0
 * - score = 1
 *
 * It returns:
 * - system suggested action_plan from framework JSON
 * - existing saved user action plan, if available
 * - achievability
 * - responsible person
 * - priority
 * - target date
 * - tracking status
 *
 * Method:
 * GET
 *
 * URL:
 * /api/assessment/v1/action_plan.php?assessment_id=1
 *
 * Department-wise:
 * /api/assessment/v1/action_plan.php?assessment_id=1&dept_id=25
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

    $assessmentId = isset($_GET['assessment_id'])
        ? (int)$_GET['assessment_id']
        : 0;

    $deptFilter = isset($_GET['dept_id'])
        ? (int)$_GET['dept_id']
        : 0;

    if ($assessmentId <= 0) {
        Response::validation([
            'assessment_id' => 'assessment_id is required'
        ]);
    }

    /*
     * 1. Validate assessment
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
        Response::error('Assessment not found for this facility');
    }

    $frameworkCode = $assessment['framework_code'] ?: 'saqshi-nqas';

    /*
     * 2. Get facility type from facilities.json
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
     * 3. Load activated departments
     */
    if ($deptFilter > 0) {
        $sqlDepartments = "
            SELECT dept_id, status
            FROM assessment_department
            WHERE assessment_id = ?
              AND fac_id_fk = ?
              AND dept_id = ?
              AND is_active = 1
        ";
    } else {
        $sqlDepartments = "
            SELECT dept_id, status
            FROM assessment_department
            WHERE assessment_id = ?
              AND fac_id_fk = ?
              AND is_active = 1
        ";
    }

    $stmt = $con->prepare($sqlDepartments);

    if (!$stmt) {
        Response::serverError('Department prepare failed: ' . $con->error);
    }

    if ($deptFilter > 0) {
        $stmt->bind_param('iii', $assessmentId, $facId, $deptFilter);
    } else {
        $stmt->bind_param('ii', $assessmentId, $facId);
    }

    $stmt->execute();

    $deptResult = $stmt->get_result();

    $departments = [];

    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = [
            'dept_id' => (int)$row['dept_id'],
            'status' => $row['status']
        ];
    }

    if (empty($departments)) {
        Response::success(
            'No activated departments found',
            [
                'assessment_id' => $assessmentId,
                'summary' => [
                    'total_action_plans' => 0,
                    'non_compliant' => 0,
                    'partially_compliant' => 0,
                    'achievable' => 0,
                    'non_achievable' => 0
                ],
                'action_plans' => []
            ]
        );
    }

    /*
     * 4. Load framework
     */
    $engine = FrameworkEngine::load($frameworkCode);

    /*
     * 5. Build checkpoint metadata lookup from JSON
     */
    $checkpointLookup = [];

    foreach ($departments as $dept) {

        $deptId = (int)$dept['dept_id'];

        $departmentConfig = $engine->getDepartmentById(
            $facTypeId,
            $deptId
        );

        if (!$departmentConfig) {
            continue;
        }

        foreach (($departmentConfig['concerns'] ?? []) as $concern) {
            foreach (($concern['subtypes'] ?? []) as $subtype) {
                foreach (($subtype['checkpoints'] ?? []) as $checkpoint) {

                    $cpId = (int)($checkpoint['csqa_id'] ?? 0);

                    if ($cpId <= 0) {
                        continue;
                    }

                    $checkpointLookup[$deptId . '_' . $cpId] = [
                        'department' => [
                            'dept_id' => $deptId,
                            'dept_name' => $departmentConfig['dept_name'] ?? ''
                        ],
                        'concern' => [
                            'concern_id' => (int)($concern['concern_id'] ?? 0),
                            'concern_name' => $concern['concern_name'] ?? '',
                            'concern_des' => $concern['concern_des'] ?? ''
                        ],
                        'subtype' => [
                            'c_subtype_id' => (int)($subtype['c_subtype_id'] ?? 0),
                            'Reference_No' => $subtype['Reference_No'] ?? '',
                            'area_of_con_subtypedeatils' =>
                                $subtype['area_of_con_subtypedeatils'] ?? ''
                        ],
                        'checkpoint' => [
                            'checkpoint_id' => $cpId,
                            'csqa_reference_id' =>
                                $checkpoint['csqa_reference_id'] ?? '',
                            'Measurable_Element' =>
                                $checkpoint['Measurable_Element'] ?? '',
                            'Checkpoint' =>
                                $checkpoint['Checkpoint'] ?? '',
                            'Assessment_Method' =>
                                $checkpoint['Assessment_Method'] ?? '',
                            'Means_of_Verification' =>
                                $checkpoint['Means_of_Verification'] ?? '',
                            'system_action_plan' =>
                                $checkpoint['action_plan'] ?? '',
                            'program_tag' =>
                                $checkpoint['program_tag'] ?? ''
                        ]
                    ];
                }
            }
        }
    }

    /*
     * 6. Fetch responses having score 0 or 1
     */
    $cycleId = $assessmentId;

    if ($deptFilter > 0) {
        $sqlGaps = "
            SELECT
                dept_id,
                checkpoint_id,
                response_value,
                score,
                remarks,
                evidence_url,
                updated_by,
                updated_on
            FROM assessment_response
            WHERE assessment_id = ?
              AND dept_id = ?
              AND score < 2
            ORDER BY dept_id, checkpoint_id
        ";
    } else {
        $sqlGaps = "
            SELECT
                dept_id,
                checkpoint_id,
                response_value,
                score,
                remarks,
                evidence_url,
                updated_by,
                updated_on
            FROM assessment_response
            WHERE assessment_id = ?
              AND score < 2
            ORDER BY dept_id, checkpoint_id
        ";
    }

    $stmt = $con->prepare($sqlGaps);

    if (!$stmt) {
        Response::serverError('Gap response prepare failed: ' . $con->error);
    }

    if ($deptFilter > 0) {
        $stmt->bind_param('ii', $cycleId, $deptFilter);
    } else {
        $stmt->bind_param('i', $cycleId);
    }

    $stmt->execute();

    $gapResult = $stmt->get_result();

    /*
     * 7. Load saved user action plans
     */
    if ($deptFilter > 0) {
        $sqlSavedPlans = "
            SELECT *
            FROM assessment_action_plan
            WHERE assessment_id = ?
              AND dept_id = ?
        ";
    } else {
        $sqlSavedPlans = "
            SELECT *
            FROM assessment_action_plan
            WHERE assessment_id = ?
        ";
    }

    $stmtPlan = $con->prepare($sqlSavedPlans);

    if (!$stmtPlan) {
        Response::serverError('Saved action plan prepare failed: ' . $con->error);
    }

    if ($deptFilter > 0) {
        $stmtPlan->bind_param('ii', $assessmentId, $deptFilter);
    } else {
        $stmtPlan->bind_param('i', $assessmentId);
    }

    $stmtPlan->execute();

    $planResult = $stmtPlan->get_result();

    $savedPlanMap = [];

    while ($plan = $planResult->fetch_assoc()) {
        $savedPlanMap[
            (int)$plan['dept_id'] . '_' . (int)$plan['checkpoint_id']
        ] = $plan;
    }

    /*
     * 7b. Load reusable facility action plan suggestions by checkpoint.
     */
    $sqlLibrary = "
        CREATE TABLE IF NOT EXISTS assessment_action_plan_library (
            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            checkpoint_id INT NOT NULL,
            framework_code VARCHAR(100) NULL,
            fac_id INT NOT NULL,
            fac_name VARCHAR(255) NULL,
            source_assessment_id BIGINT NOT NULL,
            source_dept_id INT NOT NULL,
            user_action_plan TEXT NOT NULL,
            created_by INT NULL,
            created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_checkpoint (checkpoint_id),
            INDEX idx_fac_checkpoint (fac_id, checkpoint_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$con->query($sqlLibrary)) {
        Response::serverError('Action plan library prepare failed: ' . $con->error);
    }

    $checkpointIds = [];

    foreach ($checkpointLookup as $lookup) {
        $checkpointId = (int)($lookup['checkpoint']['checkpoint_id'] ?? 0);

        if ($checkpointId > 0) {
            $checkpointIds[$checkpointId] = true;
        }
    }

    $suggestionMap = [];

    if (!empty($checkpointIds)) {
        $suggestionCheckpointIds = array_map('intval', array_keys($checkpointIds));
        $suggestionPlaceholders = implode(',', array_fill(0, count($suggestionCheckpointIds), '?'));

        $sqlSuggestions = "
            SELECT
                id,
                checkpoint_id,
                framework_code,
                fac_id,
                fac_name,
                source_assessment_id,
                source_dept_id,
                user_action_plan,
                created_by,
                created_on
            FROM assessment_action_plan_library
            WHERE checkpoint_id IN ($suggestionPlaceholders)
              AND (
                    framework_code IS NULL
                    OR framework_code = ''
                    OR framework_code = ?
                  )
            ORDER BY checkpoint_id, created_on DESC
            LIMIT 500
        ";

        $stmtSuggestions = $con->prepare($sqlSuggestions);

        if (!$stmtSuggestions) {
            Response::serverError('Action plan suggestion prepare failed: ' . $con->error);
        }

        $suggestionTypes = str_repeat('i', count($suggestionCheckpointIds)) . 's';
        $suggestionParams = $suggestionCheckpointIds;
        $suggestionParams[] = $frameworkCode;

        $stmtSuggestions->bind_param($suggestionTypes, ...$suggestionParams);
        $stmtSuggestions->execute();

        $suggestionResult = $stmtSuggestions->get_result();

        while ($suggestion = $suggestionResult->fetch_assoc()) {
            $checkpointId = (int)$suggestion['checkpoint_id'];

            if (!isset($suggestionMap[$checkpointId])) {
                $suggestionMap[$checkpointId] = [];
            }

            $suggestionMap[$checkpointId][] = [
                'id' => (int)$suggestion['id'],
                'checkpoint_id' => $checkpointId,
                'framework_code' => $suggestion['framework_code'],
                'fac_id' => (int)$suggestion['fac_id'],
                'fac_name' => $suggestion['fac_name'],
                'source_assessment_id' => (int)$suggestion['source_assessment_id'],
                'source_dept_id' => (int)$suggestion['source_dept_id'],
                'user_action_plan' => $suggestion['user_action_plan'],
                'created_by' => $suggestion['created_by'] !== null
                    ? (int)$suggestion['created_by']
                    : null,
                'created_on' => $suggestion['created_on']
            ];
        }
    }

    /*
     * 8. Build action plan output
     */
    $actionPlans = [];

    $nonCompliant = 0;
    $partiallyCompliant = 0;
    $achievable = 0;
    $nonAchievable = 0;
    $planValue = static function ($plan, $key, $default = null) {
        if (!is_array($plan) || !array_key_exists($key, $plan)) {
            return $default;
        }

        return $plan[$key];
    };

    while ($row = $gapResult->fetch_assoc()) {

        $deptId = (int)$row['dept_id'];
        $checkpointId = (int)$row['checkpoint_id'];
        $score = (float)$row['score'];

        $key = $deptId . '_' . $checkpointId;
        $meta = $checkpointLookup[$key] ?? null;
        $savedPlan = $savedPlanMap[$key] ?? null;
        $savedPlanData = is_array($savedPlan) ? $savedPlan : [];

        if ($score <= 0) {
            $gapType = 'NON_COMPLIANT';
            $nonCompliant++;
        } else {
            $gapType = 'PARTIALLY_COMPLIANT';
            $partiallyCompliant++;
        }

        $achievability = $planValue($savedPlanData, 'achievability', 'ACHIEVABLE');

        if ($achievability === 'NON_ACHIEVABLE') {
            $nonAchievable++;
        } else {
            $achievable++;
        }

        $actionPlans[] = [
            'gap_type' => $gapType,

            'assessment_id' => $assessmentId,
            'dept_id' => $deptId,
            'checkpoint_id' => $checkpointId,

            'response' => [
                'response_value' => $row['response_value'],
                'score' => $score,
                'remarks' => $row['remarks'],
                'evidence_url' => $row['evidence_url'],
                'updated_by' => (int)$row['updated_by'],
                'updated_on' => $row['updated_on']
            ],

            'department' => $meta['department'] ?? [
                'dept_id' => $deptId,
                'dept_name' => ''
            ],

            'concern' => $meta['concern'] ?? null,

            'subtype' => $meta['subtype'] ?? null,

            'checkpoint' => $meta['checkpoint'] ?? [
                'checkpoint_id' => $checkpointId,
                'system_action_plan' => ''
            ],

            'action_plan' => [
                'has_saved_plan' => $savedPlan ? true : false,

                'id' => $planValue($savedPlanData, 'id') !== null
                    ? (int)$planValue($savedPlanData, 'id')
                    : null,

                'system_action_plan' =>
                    $planValue($savedPlanData, 'system_action_plan')
                    ?? ($meta['checkpoint']['system_action_plan'] ?? ''),

                'user_action_plan' =>
                    $planValue($savedPlanData, 'user_action_plan', ''),

                'achievability' =>
                    $planValue($savedPlanData, 'achievability', 'ACHIEVABLE'),

                'responsible_person' =>
                    $planValue($savedPlanData, 'responsible_person'),

                'priority' =>
                    $planValue($savedPlanData, 'priority', 'MEDIUM'),

                'target_date' =>
                    $planValue($savedPlanData, 'target_date'),

                'status' =>
                    $planValue($savedPlanData, 'status', 'OPEN'),

                'revised_score' =>
                    $planValue($savedPlanData, 'revised_score') !== null
                        ? (float)$planValue($savedPlanData, 'revised_score')
                        : null,

                'closure_remarks' =>
                    $planValue($savedPlanData, 'closure_remarks', ''),

                'closure_evidence_url' =>
                    $planValue($savedPlanData, 'closure_evidence_url', ''),

                'closed_by' =>
                    $planValue($savedPlanData, 'closed_by') !== null
                        ? (int)$planValue($savedPlanData, 'closed_by')
                        : null,

                'closed_on' =>
                    $planValue($savedPlanData, 'closed_on'),

                'created_by' =>
                    $planValue($savedPlanData, 'created_by') !== null
                        ? (int)$planValue($savedPlanData, 'created_by')
                        : null,

                'created_on' =>
                    $planValue($savedPlanData, 'created_on'),

                'updated_by' =>
                    $planValue($savedPlanData, 'updated_by') !== null
                        ? (int)$planValue($savedPlanData, 'updated_by')
                        : null,

                'updated_on' =>
                    $planValue($savedPlanData, 'updated_on'),

                'facility_suggestions' =>
                    $suggestionMap[$checkpointId] ?? []
            ]
        ];
    }

    Response::success(
        'Action plan generated successfully',
        [
            'assessment' => [
                'assessment_id' => (int)$assessment['assessment_id'],
                'assessment_name' => $assessment['assessment_name'],
                'framework_code' => $frameworkCode,
                'status' => $assessment['status']
            ],

            'facility' => $facilityData,

            'scope' => $deptFilter > 0 ? 'DEPARTMENT' : 'ASSESSMENT',

            'dept_id' => $deptFilter > 0 ? $deptFilter : null,

            'summary' => [
                'total_action_plans' => count($actionPlans),
                'non_compliant' => $nonCompliant,
                'partially_compliant' => $partiallyCompliant,
                'achievable' => $achievable,
                'non_achievable' => $nonAchievable
            ],

            'action_plans' => $actionPlans
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

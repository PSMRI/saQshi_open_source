<?php
/**
 * gap_analysis.php
 * -------------------------------------------------------
 * Generate gap analysis for assessment / department.
 *
 * Gap means:
 * - Non Compliant score = 0
 * - Partially Compliant score = 1
 *
 * Method:
 * GET
 *
 * URL:
 * Full assessment:
 * /api/assessment/v1/gap_analysis.php?assessment_id=1
 *
 * Department:
 * /api/assessment/v1/gap_analysis.php?assessment_id=1&dept_id=25
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
    $deptFilter   = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;

    if ($assessmentId <= 0) {
        Response::validation([
            'assessment_id' => 'assessment_id is required'
        ]);
    }

    $sqlAssessment = "
        SELECT assessment_id, assessment_name, framework_code, fac_id_fk,
               start_date, end_date, status
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

    $facilityJsonPath = __DIR__ . '/../../config/masters/facilities.json';

    if (!file_exists($facilityJsonPath)) {
        Response::serverError('facilities.json not found');
    }

    $states = json_decode(file_get_contents($facilityJsonPath), true);

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
        Response::success('No activated departments found', [
            'assessment_id' => $assessmentId,
            'gaps' => [],
            'summary' => [
                'total_original_gaps' => 0,
                'open_gaps' => 0,
                'closed_gaps' => 0,
                'non_compliant' => 0,
                'partially_compliant' => 0
            ]
        ]);
    }

    $engine = FrameworkEngine::load($frameworkCode);

    $checkpointLookup = [];

    foreach ($departments as $dept) {
        $deptId = (int)$dept['dept_id'];

        $departmentConfig = $engine->getDepartmentById($facTypeId, $deptId);

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
                            'csqa_reference_id' => $checkpoint['csqa_reference_id'] ?? '',
                            'Measurable_Element' => $checkpoint['Measurable_Element'] ?? '',
                            'Checkpoint' => $checkpoint['Checkpoint'] ?? '',
                            'Assessment_Method' => $checkpoint['Assessment_Method'] ?? '',
                            'Means_of_Verification' => $checkpoint['Means_of_Verification'] ?? '',
                            'action_plan' => $checkpoint['action_plan'] ?? '',
                            'program_tag' => $checkpoint['program_tag'] ?? ''
                        ]
                    ];
                }
            }
        }
    }

    $cycleId = $assessmentId;

    if ($deptFilter > 0) {
        $sqlResponses = "
            SELECT
                r.dept_id,
                r.checkpoint_id,
                r.response_value,
                r.score AS original_score,
                r.remarks,
                r.evidence_url,
                r.updated_by,
                r.updated_on,

                ap.id AS action_plan_id,
                ap.revised_score,
                ap.status AS action_plan_status,
                ap.closure_remarks,
                ap.closure_evidence_url,
                ap.closed_by,
                ap.closed_on

            FROM assessment_response r

            LEFT JOIN assessment_action_plan ap
                ON ap.assessment_id = r.assessment_id
               AND ap.dept_id = r.dept_id
               AND ap.checkpoint_id = r.checkpoint_id

            WHERE r.assessment_id = ?
              AND r.dept_id = ?
              AND r.score < 2

            ORDER BY r.dept_id, r.checkpoint_id
        ";
    } else {
        $sqlResponses = "
            SELECT
                r.dept_id,
                r.checkpoint_id,
                r.response_value,
                r.score AS original_score,
                r.remarks,
                r.evidence_url,
                r.updated_by,
                r.updated_on,

                ap.id AS action_plan_id,
                ap.revised_score,
                ap.status AS action_plan_status,
                ap.closure_remarks,
                ap.closure_evidence_url,
                ap.closed_by,
                ap.closed_on

            FROM assessment_response r

            LEFT JOIN assessment_action_plan ap
                ON ap.assessment_id = r.assessment_id
               AND ap.dept_id = r.dept_id
               AND ap.checkpoint_id = r.checkpoint_id

            WHERE r.assessment_id = ?
              AND r.score < 2

            ORDER BY r.dept_id, r.checkpoint_id
        ";
    }

    $stmt = $con->prepare($sqlResponses);
    if (!$stmt) {
        Response::serverError('Gap query prepare failed: ' . $con->error);
    }

    if ($deptFilter > 0) {
        $stmt->bind_param('ii', $cycleId, $deptFilter);
    } else {
        $stmt->bind_param('i', $cycleId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $allGaps = [];
    $openGaps = [];
    $closedGaps = [];

    $nonCompliant = 0;
    $partiallyCompliant = 0;

    while ($row = $result->fetch_assoc()) {

        $deptId = (int)$row['dept_id'];
        $checkpointId = (int)$row['checkpoint_id'];

        $originalScore = (float)$row['original_score'];
        $revisedScore = $row['revised_score'] !== null
            ? (float)$row['revised_score']
            : null;

        $effectiveScore = $revisedScore !== null
            ? $revisedScore
            : $originalScore;

        $isClosed = $revisedScore !== null && $revisedScore >= 2;

        if ($originalScore <= 0) {
            $gapType = 'NON_COMPLIANT';
            $nonCompliant++;
        } else {
            $gapType = 'PARTIALLY_COMPLIANT';
            $partiallyCompliant++;
        }

        $lookupKey = $deptId . '_' . $checkpointId;
        $meta = $checkpointLookup[$lookupKey] ?? null;

        $gap = [
            'gap_type' => $gapType,
            'gap_status' => $isClosed ? 'CLOSED' : 'OPEN',

            'dept_id' => $deptId,
            'checkpoint_id' => $checkpointId,

            'score' => [
                'original_score' => $originalScore,
                'revised_score' => $revisedScore,
                'effective_score' => $effectiveScore,
                'improved' => $revisedScore !== null
            ],

            'response' => [
                'response_value' => $row['response_value'],
                'remarks' => $row['remarks'],
                'evidence_url' => $row['evidence_url'],
                'updated_by' => (int)$row['updated_by'],
                'updated_on' => $row['updated_on']
            ],

            'action_plan_closure' => [
                'action_plan_id' => $row['action_plan_id'] !== null
                    ? (int)$row['action_plan_id']
                    : null,
                'status' => $row['action_plan_status'],
                'closure_remarks' => $row['closure_remarks'],
                'closure_evidence_url' => $row['closure_evidence_url'],
                'closed_by' => $row['closed_by'] !== null
                    ? (int)$row['closed_by']
                    : null,
                'closed_on' => $row['closed_on']
            ],

            'department' => $meta['department'] ?? [
                'dept_id' => $deptId,
                'dept_name' => ''
            ],

            'concern' => $meta['concern'] ?? null,
            'subtype' => $meta['subtype'] ?? null,
            'checkpoint' => $meta['checkpoint'] ?? [
                'checkpoint_id' => $checkpointId
            ]
        ];

        $allGaps[] = $gap;

        if ($isClosed) {
            $closedGaps[] = $gap;
        } else {
            $openGaps[] = $gap;
        }
    }

    Response::success(
        'Gap analysis generated successfully',
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
                'total_original_gaps' => count($allGaps),
                'open_gaps' => count($openGaps),
                'closed_gaps' => count($closedGaps),
                'non_compliant' => $nonCompliant,
                'partially_compliant' => $partiallyCompliant,
                'closure_percent' => count($allGaps) > 0
                    ? round((count($closedGaps) / count($allGaps)) * 100, 2)
                    : 0
            ],

            'open_gaps' => $openGaps,
            'closed_gaps' => $closedGaps,
            'all_gaps' => $allGaps
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
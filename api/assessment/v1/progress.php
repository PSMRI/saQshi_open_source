<?php

/**
 * progress.php
 * -------------------------------------------------------
 * Assessment progress summary.
 *
 * Includes:
 * - Department progress
 * - Assessor info
 * - Original score
 * - Improved/revised score
 * - Gap closure summary
 *
 * response assessment_id is the current assessment_id
 *
 * Method:
 * GET
 *
 * URL:
 * /api/assessment/v1/progress.php?assessment_id=1
 * -------------------------------------------------------
 */

/**
 * progress.php
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../core/Crypto.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

/**
 * Handles progress facility type id processing for this API workflow.
 */
function progressFacilityTypeId(int $facId): int
{
    $facilityJsonPath = __DIR__ . '/../../config/masters/facilities.json';

    if (!file_exists($facilityJsonPath)) {
        return 0;
    }

    $states = json_decode(file_get_contents($facilityJsonPath), true);

    if (!is_array($states)) {
        return 0;
    }

    foreach ($states as $state) {
        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {
                        if ((int)($facility['fac_id'] ?? 0) === $facId) {
                            return (int)($facility['fac_type_id'] ?? 0);
                        }
                    }
                }
            }
        }
    }

    return 0;
}

/**
 * Handles progress checkpoint max score processing for this API workflow.
 */
function progressCheckpointMaxScore(array $checkpoint): float
{
    $options = $checkpoint['response']['options'] ?? [];

    if (!is_array($options) || empty($options)) {
        return 2;
    }

    $scores = array_map(
        fn($option) => (float)($option['score'] ?? 0),
        $options
    );

    $max = max($scores);

    return $max > 0 ? $max : 2;
}

/**
 * Handles progress department base processing for this API workflow.
 */
function progressDepartmentBase(FrameworkEngine $engine, int $facTypeId, int $deptId): array
{
    if ($facTypeId <= 0 || $deptId <= 0) {
        return [
            'total_checkpoints' => 0,
            'total_score' => 0
        ];
    }

    $seen = [];
    $totalCheckpoints = 0;
    $totalScore = 0;

    foreach ($engine->getCheckpoints($facTypeId, $deptId) as $checkpoint) {
        $checkpointId = (string)($checkpoint['csqa_id'] ?? '');

        if ($checkpointId === '' || isset($seen[$checkpointId])) {
            continue;
        }

        $seen[$checkpointId] = true;
        $totalCheckpoints++;
        $totalScore += progressCheckpointMaxScore($checkpoint);
    }

    return [
        'total_checkpoints' => $totalCheckpoints,
        'total_score' => $totalScore
    ];
}

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
            status,
            created_by,
            created_on,
            updated_on,
            completed_on,
            cancelled_on
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
    $facTypeId = progressFacilityTypeId($facId);
    $engine = FrameworkEngine::load($frameworkCode);

    /*
     * 2. Department summary
     */
    $sqlDeptSummary = "
        SELECT
            COUNT(*) AS total_departments,

            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_departments,

            SUM(CASE WHEN is_active = 1 AND status = 'NOT_STARTED' THEN 1 ELSE 0 END) AS not_started,

            SUM(CASE WHEN is_active = 1 AND status = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS in_progress,

            SUM(CASE WHEN is_active = 1 AND status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed

        FROM assessment_department
        WHERE assessment_id = ?
          AND fac_id_fk = ?
    ";

    $stmt = $con->prepare($sqlDeptSummary);

    if (!$stmt) {
        Response::serverError('Department summary prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();

    $totalDepartments = (int)($summary['total_departments'] ?? 0);
    $activeDepartments = (int)($summary['active_departments'] ?? 0);
    $notStarted = (int)($summary['not_started'] ?? 0);
    $inProgress = (int)($summary['in_progress'] ?? 0);
    $completed = (int)($summary['completed'] ?? 0);

    $departmentCompletionPercent = $activeDepartments > 0
        ? round(($completed / $activeDepartments) * 100, 2)
        : 0;

    /*
     * 3. Gap closure summary
     */
    $sqlGapSummary = "
        SELECT
            COUNT(*) AS total_original_gaps,

            SUM(
                CASE
                    WHEN ap.revised_score IS NOT NULL
                     AND ap.revised_score >= 2
                    THEN 1 ELSE 0
                END
            ) AS closed_gaps,

            SUM(
                CASE
                    WHEN ap.revised_score IS NULL
                      OR ap.revised_score < 2
                    THEN 1 ELSE 0
                END
            ) AS open_gaps

        FROM assessment_response r

        LEFT JOIN assessment_action_plan ap
            ON ap.assessment_id = r.assessment_id
           AND ap.dept_id = r.dept_id
           AND ap.checkpoint_id = r.checkpoint_id

        WHERE r.assessment_id = ?
          AND r.score < 2
    ";

    $stmt = $con->prepare($sqlGapSummary);

    if (!$stmt) {
        Response::serverError('Gap summary prepare failed: ' . $con->error);
    }

    $stmt->bind_param('i', $assessmentId);
    $stmt->execute();

    $gapSummary = $stmt->get_result()->fetch_assoc();

    $totalOriginalGaps = (int)($gapSummary['total_original_gaps'] ?? 0);
    $closedGaps = (int)($gapSummary['closed_gaps'] ?? 0);
    $openGaps = (int)($gapSummary['open_gaps'] ?? 0);

    $gapClosurePercent = $totalOriginalGaps > 0
        ? round(($closedGaps / $totalOriginalGaps) * 100, 2)
        : 0;

    /*
     * 4. Department-wise details
     */
    $sqlDepartments = "
        SELECT
            d.assessment_dept_id AS id,
            d.assessment_id,
            d.dept_id,
            d.is_active,
            d.status,
            d.started_on,
            d.completed_on,
            d.current_checkpoint_id,
            d.activated_by,

            ai.info_id AS assessor_info_id,
            ai.assessment_date,
            ai.assessment_type,
            ai.assessor_name,
            ai.assessee_name,

            COUNT(r.response_id) AS saved_responses,

            COALESCE(SUM(r.score), 0) AS original_obtained_score,

            COALESCE(
                SUM(
                    CASE
                        WHEN ap.revised_score IS NOT NULL
                        THEN ap.revised_score
                        ELSE r.score
                    END
                ),
                0
            ) AS improved_obtained_score,

            SUM(
                CASE
                    WHEN ap.revised_score IS NOT NULL THEN 1
                    ELSE 0
                END
            ) AS revised_checkpoints,

            SUM(
                CASE
                    WHEN r.score < 2 THEN 1
                    ELSE 0
                END
            ) AS original_gaps,

            SUM(
                CASE
                    WHEN r.score < 2
                     AND ap.revised_score IS NOT NULL
                     AND ap.revised_score >= 2
                    THEN 1
                    ELSE 0
                END
            ) AS closed_gaps

        FROM assessment_department d

        LEFT JOIN assessment_assessor_info ai
            ON ai.assessment_id = d.assessment_id
           AND ai.fac_id_fk = d.fac_id_fk
           AND ai.dept_id = d.dept_id

        LEFT JOIN assessment_response r
            ON r.assessment_id = d.assessment_id
           AND r.dept_id = d.dept_id

        LEFT JOIN assessment_action_plan ap
            ON ap.assessment_id = d.assessment_id
           AND ap.dept_id = d.dept_id
           AND ap.checkpoint_id = r.checkpoint_id

        WHERE d.assessment_id = ?
          AND d.fac_id_fk = ?

        GROUP BY
            d.assessment_dept_id,
            d.assessment_id,
            d.dept_id,
            d.is_active,
            d.status,
            d.started_on,
            d.completed_on,
            d.current_checkpoint_id,
            d.activated_by,
            ai.info_id,
            ai.assessment_date,
            ai.assessment_type,
            ai.assessor_name,
            ai.assessee_name

        ORDER BY d.dept_id
    ";

    $stmt = $con->prepare($sqlDepartments);

    if (!$stmt) {
        Response::serverError('Department detail prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $res = $stmt->get_result();

    $departments = [];

    $totalSavedResponses = 0;
    $totalRevisedCheckpoints = 0;

    $totalOriginalObtained = 0;
    $totalImprovedObtained = 0;
    $totalPossibleScore = 0;

    while ($row = $res->fetch_assoc()) {
        $row = Crypto::decryptFields($row, [
            'assessor_name',
            'assessee_name'
        ]);

        $savedResponses = (int)($row['saved_responses'] ?? 0);
        $revisedCheckpoints = (int)($row['revised_checkpoints'] ?? 0);
        $isActive = (int)($row['is_active'] ?? 0);

        $originalObtained = (float)($row['original_obtained_score'] ?? 0);
        $improvedObtained = (float)($row['improved_obtained_score'] ?? 0);
        $scoreBase = $isActive === 1
            ? progressDepartmentBase($engine, $facTypeId, (int)$row['dept_id'])
            : [
                'total_checkpoints' => 0,
                'total_score' => 0
            ];
        $possibleScore = (float)$scoreBase['total_score'];

        $originalPercentage = $possibleScore > 0
            ? round(($originalObtained / $possibleScore) * 100, 2)
            : 0;
        $improvedPercentage = $possibleScore > 0
            ? round(($improvedObtained / $possibleScore) * 100, 2)
            : 0;

        $deptOriginalGaps = (int)($row['original_gaps'] ?? 0);
        $deptClosedGaps = (int)($row['closed_gaps'] ?? 0);

        $deptOpenGaps = max(0, $deptOriginalGaps - $deptClosedGaps);

        $deptClosurePercent = $deptOriginalGaps > 0
            ? round(($deptClosedGaps / $deptOriginalGaps) * 100, 2)
            : 0;

        $totalSavedResponses += $savedResponses;
        $totalRevisedCheckpoints += $revisedCheckpoints;

        $totalOriginalObtained += $originalObtained;
        $totalImprovedObtained += $improvedObtained;
        $totalPossibleScore += $possibleScore;

        $departments[] = [
            'assessment_department_id' => (int)$row['id'],
            'assessment_id' => (int)$row['assessment_id'],
            'dept_id' => (int)$row['dept_id'],

            'is_active' => (int)$row['is_active'],
            'status' => $row['status'],

            'started_on' => $row['started_on'],
            'completed_on' => $row['completed_on'],

            'current_checkpoint_id' =>
                $row['current_checkpoint_id'] !== null
                    ? (int)$row['current_checkpoint_id']
                    : null,

            'activated_by' => (int)$row['activated_by'],

            'assessor_info' => [
                'has_info' => $row['assessor_info_id'] !== null,
                'assessor_info_id' =>
                    $row['assessor_info_id'] !== null
                        ? (int)$row['assessor_info_id']
                        : null,
                'assessment_date' => $row['assessment_date'],
                'assessment_type' => $row['assessment_type'],
                'assessor_name' => $row['assessor_name'],
                'assessee_name' => $row['assessee_name']
            ],

            'responses' => [
                'saved_responses' => $savedResponses,
                'revised_checkpoints' => $revisedCheckpoints
            ],

            'score' => [
                'original' => [
                    'obtained_score' => $originalObtained,
                    'total_score' => $possibleScore,
                    'percentage' => $originalPercentage
                ],
                'improved' => [
                    'obtained_score' => $improvedObtained,
                    'total_score' => $possibleScore,
                    'percentage' => $improvedPercentage
                ],
                'improvement' => [
                    'score_gain' => round($improvedObtained - $originalObtained, 2),
                    'percentage_gain' => round($improvedPercentage - $originalPercentage, 2)
                ]
            ],

            'gaps' => [
                'original_gaps' => $deptOriginalGaps,
                'closed_gaps' => $deptClosedGaps,
                'open_gaps' => $deptOpenGaps,
                'closure_percent' => $deptClosurePercent
            ]
        ];
    }

    $overallOriginalScorePercent = $totalPossibleScore > 0
        ? round(($totalOriginalObtained / $totalPossibleScore) * 100, 2)
        : 0;

    $overallImprovedScorePercent = $totalPossibleScore > 0
        ? round(($totalImprovedObtained / $totalPossibleScore) * 100, 2)
        : 0;

    Response::success(
        'Assessment progress fetched successfully',
        [
            'assessment' => [
                'assessment_id' => (int)$assessment['assessment_id'],
                'assessment_name' => $assessment['assessment_name'],
                'framework_code' => $assessment['framework_code'],
                'fac_id' => (int)$assessment['fac_id_fk'],
                'start_date' => $assessment['start_date'],
                'end_date' => $assessment['end_date'],
                'status' => $assessment['status'],
                'created_by' => (int)$assessment['created_by'],
                'created_on' => $assessment['created_on'],
                'updated_on' => $assessment['updated_on'],
                'completed_on' => $assessment['completed_on'],
                'cancelled_on' => $assessment['cancelled_on']
            ],

            'summary' => [
                'departments' => [
                    'total_departments' => $totalDepartments,
                    'active_departments' => $activeDepartments,
                    'not_started' => $notStarted,
                    'in_progress' => $inProgress,
                    'completed' => $completed,
                    'completion_percent' => $departmentCompletionPercent
                ],

                'responses' => [
                    'total_saved_responses' => $totalSavedResponses,
                    'revised_checkpoints' => $totalRevisedCheckpoints
                ],

                'score' => [
                    'original' => [
                        'obtained_score' => $totalOriginalObtained,
                        'total_score' => $totalPossibleScore,
                        'percentage' => $overallOriginalScorePercent
                    ],
                    'improved' => [
                        'obtained_score' => $totalImprovedObtained,
                        'total_score' => $totalPossibleScore,
                        'percentage' => $overallImprovedScorePercent
                    ],
                    'improvement' => [
                        'score_gain' => round($totalImprovedObtained - $totalOriginalObtained, 2),
                        'percentage_gain' => round($overallImprovedScorePercent - $overallOriginalScorePercent, 2)
                    ]
                ],

                'gaps' => [
                    'total_original_gaps' => $totalOriginalGaps,
                    'closed_gaps' => $closedGaps,
                    'open_gaps' => $openGaps,
                    'closure_percent' => $gapClosurePercent
                ]
            ],

            'departments' => $departments
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

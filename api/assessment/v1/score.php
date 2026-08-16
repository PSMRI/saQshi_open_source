<?php

/**
 * score.php
 * -------------------------------------------------------
 * Calculate assessment score.
 *
 * Supports:
 * - Full assessment score
 * - Department-wise score
 *
 * Original score:
 * - assessment_response.score
 *
 * Improved score:
 * - assessment_action_plan.revised_score if available
 * - otherwise original score
 *
 * Simplified design:
 * - responses are stored by assessment_id in assessment_response
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

/**
 * Handles score facility type id processing for this API workflow.
 */
function scoreFacilityTypeId(int $facId): int
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
 * Handles score checkpoint max score processing for this API workflow.
 */
function scoreCheckpointMaxScore(array $checkpoint): float
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
 * Handles score department base processing for this API workflow.
 */
function scoreDepartmentBase(FrameworkEngine $engine, int $facTypeId, int $deptId): array
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
        $totalScore += scoreCheckpointMaxScore($checkpoint);
    }

    return [
        'total_checkpoints' => $totalCheckpoints,
        'total_score' => $totalScore
    ];
}

/** Normalise framework labels so harmless whitespace in master JSON does not affect scoring. */
function scoreNormaliseLabel(string $value): string
{
    return preg_replace('/\s+/', ' ', trim($value)) ?? '';
}

/** Loads optional, framework-specific model scoring rules. */
function scorePolicy(string $frameworkCode): array
{
    $path = __DIR__ . '/../../config/scoring/' . basename($frameworkCode) . '.json';
    if (!is_file($path)) {
        return [];
    }

    $policy = json_decode((string)file_get_contents($path), true);
    return is_array($policy) ? $policy : [];
}

function scorePerformanceLevel(float $percentage, array $policy): ?array
{
    foreach (($policy['performance_levels'] ?? []) as $level) {
        $min = isset($level['min_percent']) ? (float)$level['min_percent'] : null;
        $max = isset($level['max_percent']) ? (float)$level['max_percent'] : null;
        $minOk = $min === null || (($level['min_inclusive'] ?? true) ? $percentage >= $min : $percentage > $min);
        $maxOk = $max === null || (($level['max_inclusive'] ?? true) ? $percentage <= $max : $percentage < $max);
        if ($minOk && $maxOk) {
            return [
                'name' => $level['name'] ?? '',
                'name_hi' => $level['name_hi'] ?? '',
                'points' => (int)($level['points'] ?? 0)
            ];
        }
    }
    return null;
}

/** Returns configurable model-wise and weighted score for one class/department. */
function scoreModels(mysqli $con, FrameworkEngine $engine, int $facTypeId, int $deptId, int $assessmentId, array $policy): ?array
{
    $configuredModels = $policy['domains'] ?? $policy['areas_of_concern'] ?? $policy['models'] ?? [];
    if (empty($configuredModels)) {
        return null;
    }

    $models = [];
    foreach ($configuredModels as $configured) {
        $name = scoreNormaliseLabel((string)($configured['domain_name'] ?? $configured['area_of_concern_name'] ?? $configured['domains_name'] ?? $configured['model_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $models[$name] = [
            'model_name' => $name,
            'weight_percent' => (float)($configured['weight_percent'] ?? 0),
            'total_checkpoints' => 0,
            'answered_checkpoints' => 0,
            'original_obtained_score' => 0.0,
            'improved_obtained_score' => 0.0,
            'total_score' => 0.0
        ];
    }

    if (empty($models)) {
        return null;
    }

    $responses = [];
    $sql = "SELECT r.checkpoint_id, r.score, ap.revised_score
            FROM assessment_response r
            LEFT JOIN assessment_action_plan ap
              ON ap.assessment_id = r.assessment_id
             AND ap.dept_id = r.dept_id
             AND ap.checkpoint_id = r.checkpoint_id
            WHERE r.assessment_id = ? AND r.dept_id = ?";
    $stmt = $con->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $assessmentId, $deptId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($response = $result->fetch_assoc()) {
            $responses[(string)$response['checkpoint_id']] = $response;
        }
    }

    $seen = [];
    foreach ($engine->getCheckpoints($facTypeId, $deptId) as $checkpoint) {
        $checkpointId = (string)($checkpoint['csqa_id'] ?? '');
        $modelName = scoreNormaliseLabel((string)($checkpoint['_concern_name'] ?? ''));
        if ($checkpointId === '' || isset($seen[$checkpointId]) || !isset($models[$modelName])) {
            continue;
        }
        $seen[$checkpointId] = true;
        $models[$modelName]['total_checkpoints']++;
        $models[$modelName]['total_score'] += scoreCheckpointMaxScore($checkpoint);
        if (isset($responses[$checkpointId])) {
            $response = $responses[$checkpointId];
            $models[$modelName]['answered_checkpoints']++;
            $models[$modelName]['original_obtained_score'] += (float)$response['score'];
            $models[$modelName]['improved_obtained_score'] += $response['revised_score'] !== null
                ? (float)$response['revised_score'] : (float)$response['score'];
        }
    }

    $domainOriginalObtained = 0.0;
    $domainImprovedObtained = 0.0;
    $domainPossible = 0.0;
    foreach ($models as &$model) {
        $total = (float)$model['total_score'];
        $model['original_percentage'] = $total > 0 ? round(($model['original_obtained_score'] / $total) * 100, 2) : 0;
        $model['improved_percentage'] = $total > 0 ? round(($model['improved_obtained_score'] / $total) * 100, 2) : 0;
        // Every model is independently assessed out of 100%. The class score is their average.
        $model['weighted_original_score'] = $model['original_percentage'];
        $model['weighted_improved_score'] = $model['improved_percentage'];
        $domainOriginalObtained += $model['original_obtained_score'];
        $domainImprovedObtained += $model['improved_obtained_score'];
        $domainPossible += $model['total_score'];
    }
    unset($model);

    $weightedOriginal = $domainPossible > 0 ? round(($domainOriginalObtained / $domainPossible) * 100, 2) : 0;
    $weightedImproved = $domainPossible > 0 ? round(($domainImprovedObtained / $domainPossible) * 100, 2) : 0;
    return [
        'domain_source' => $policy['domain_source'] ?? $policy['domains_source'] ?? $policy['model_source'] ?? 'concern_name',
        'area_of_concern_source' => $policy['area_of_concern_source'] ?? $policy['model_source'] ?? 'concern_name',
        // model_source is retained temporarily for existing report consumers.
        'model_source' => $policy['domain_source'] ?? $policy['domains_source'] ?? $policy['model_source'] ?? 'concern_name',
        'models' => array_values($models),
        'weighted_score' => [
            'aggregation' => $policy['scope']['model_aggregation'] ?? 'total_obtained_over_total_possible',
            'original_percentage' => $weightedOriginal,
            'improved_percentage' => $weightedImproved,
            'original_level' => scorePerformanceLevel($weightedOriginal, $policy),
            'improved_level' => scorePerformanceLevel($weightedImproved, $policy),
            'original_category' => scorePerformanceLevel($weightedOriginal, $policy),
            'improved_category' => scorePerformanceLevel($weightedImproved, $policy)
        ]
    ];
}

/** Builds the school/facility score for every round from completed class/department scores. */
function scoreRoundHistory(mysqli $con, FrameworkEngine $engine, int $facTypeId, int $facId, string $frameworkCode, array $policy): array
{
    $sql = "SELECT fr.round_id, fr.round_no, fr.status, fr.started_on, fr.completed_on,
                   a.assessment_id, d.dept_id
            FROM facility_assessment_round fr
            LEFT JOIN assessment_master a
              ON a.round_id = fr.round_id
             AND a.fac_id_fk = fr.fac_id
             AND a.framework_code = ?
            LEFT JOIN assessment_department d
              ON d.assessment_id = a.assessment_id
             AND d.fac_id_fk = fr.fac_id
             AND d.is_active = 1
             AND UPPER(d.status) = 'COMPLETED'
            WHERE fr.fac_id = ?
            ORDER BY fr.round_no, fr.round_id, a.assessment_id";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('si', $frameworkCode, $facId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rounds = [];
    while ($row = $result->fetch_assoc()) {
        $roundId = (int)$row['round_id'];
        if (!isset($rounds[$roundId])) {
            $rounds[$roundId] = [
                'round_id' => $roundId,
                'round_no' => (int)($row['round_no'] ?? 0),
                'status' => $row['status'] ?? '',
                'started_on' => $row['started_on'] ?? null,
                'completed_on' => $row['completed_on'] ?? null,
                'completed_classes' => []
            ];
        }
        if ((int)($row['assessment_id'] ?? 0) > 0 && (int)($row['dept_id'] ?? 0) > 0) {
            $modelScore = scoreModels($con, $engine, $facTypeId, (int)$row['dept_id'], (int)$row['assessment_id'], $policy);
            if ($modelScore !== null) {
                $rounds[$roundId]['completed_classes'][] = [
                    'assessment_id' => (int)$row['assessment_id'],
                    'dept_id' => (int)$row['dept_id'],
                    'score' => $modelScore['weighted_score']
                ];
            }
        }
    }

    foreach ($rounds as &$round) {
        $original = array_column(array_column($round['completed_classes'], 'score'), 'original_percentage');
        $improved = array_column(array_column($round['completed_classes'], 'score'), 'improved_percentage');
        $round['school_score'] = count($original) === 0 ? null : [
            'aggregation' => $policy['scope']['overall_aggregation'] ?? 'average_of_completed_class_scores_in_round',
            'original_percentage' => round(array_sum($original) / count($original), 2),
            'improved_percentage' => round(array_sum($improved) / count($improved), 2)
        ];
        if ($round['school_score'] !== null) {
            $round['school_score']['original_level'] = scorePerformanceLevel($round['school_score']['original_percentage'], $policy);
            $round['school_score']['improved_level'] = scorePerformanceLevel($round['school_score']['improved_percentage'], $policy);
            $round['school_score']['original_category'] = $round['school_score']['original_level'];
            $round['school_score']['improved_category'] = $round['school_score']['improved_level'];
        }
    }
    unset($round);
    return array_values($rounds);
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

    $deptId = isset($_GET['dept_id'])
        ? (int)$_GET['dept_id']
        : 0;

    if ($assessmentId <= 0) {
        Response::validation([
            'assessment_id' => 'assessment_id is required'
        ]);
    }

    /*
     * 1. Validate assessment belongs to facility
     */
    $sqlAssessment = "
        SELECT
            assessment_id,
            assessment_name,
            framework_code,
            fac_id_fk,
            round_id,
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

    $cycleId = $assessmentId;
    $frameworkCode = $assessment['framework_code'] ?: 'saqshi-nqas';
    $facTypeId = scoreFacilityTypeId($facId);
    $engine = FrameworkEngine::load($frameworkCode);
    // Education's master type is 10 while its framework intentionally contains one type (1).
    // Use that sole framework type when the master identifier is not present in the framework.
    if ($engine->getFacilityTypeById($facTypeId) === null) {
        $frameworkTypes = $engine->getFacilityTypes();
        if (count($frameworkTypes) === 1) {
            $facTypeId = (int)($frameworkTypes[0]['fac_type_id'] ?? 0);
        }
    }
    $policy = scorePolicy($frameworkCode);

    /*
     * 2. Department-wise score
     */
    if ($deptId > 0) {

        $sqlDept = "
            SELECT
                id,
                dept_id,
                status,
                is_active
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

        $dept = $stmt->get_result()->fetch_assoc();

        if (!$dept) {
            Response::error('Department not activated for this assessment');
        }

        /*
         * Revised score comes from completed action plan.
         * If revised_score is null, original score is used.
         */
        $sqlScore = "
            SELECT
                COUNT(r.response_id) AS answered_checkpoints,

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
                ) AS revised_checkpoints

            FROM assessment_response r

            LEFT JOIN assessment_action_plan ap
                ON ap.assessment_id = r.assessment_id
               AND ap.dept_id = r.dept_id
               AND ap.checkpoint_id = r.checkpoint_id

            WHERE r.assessment_id = ?
              AND r.dept_id = ?
        ";

        $stmt = $con->prepare($sqlScore);

        if (!$stmt) {
            Response::serverError('Score prepare failed: ' . $con->error);
        }

        $stmt->bind_param('ii', $cycleId, $deptId);
        $stmt->execute();

        $score = $stmt->get_result()->fetch_assoc();
        $scoreBase = scoreDepartmentBase($engine, $facTypeId, $deptId);
        $totalScore = (float)$scoreBase['total_score'];

        $originalObtained = (float)($score['original_obtained_score'] ?? 0);
        $improvedObtained = (float)($score['improved_obtained_score'] ?? 0);
        $originalPercentage = $totalScore > 0
            ? round(($originalObtained / $totalScore) * 100, 2)
            : 0;
        $improvedPercentage = $totalScore > 0
            ? round(($improvedObtained / $totalScore) * 100, 2)
            : 0;
        $modelScore = scoreModels($con, $engine, $facTypeId, $deptId, $assessmentId, $policy);

        Response::success(
            'Department score calculated successfully',
            [
                'assessment' => [
                    'assessment_id' => (int)$assessment['assessment_id'],
                    'assessment_name' => $assessment['assessment_name'],
                    'framework_code' => $assessment['framework_code'],
                    'status' => $assessment['status']
                ],
                'scope' => 'DEPARTMENT',
                'dept_id' => $deptId,
                'department_status' => $dept['status'],

                'score' => [
                    'answered_checkpoints' => (int)($score['answered_checkpoints'] ?? 0),
                    'revised_checkpoints' => (int)($score['revised_checkpoints'] ?? 0),

                    'original' => [
                        'obtained_score' => $originalObtained,
                        'total_score' => $totalScore,
                        'percentage' => $originalPercentage
                    ],

                    'improved' => [
                        'obtained_score' => $improvedObtained,
                        'total_score' => $totalScore,
                        'percentage' => $improvedPercentage
                    ],

                    'improvement' => [
                        'score_gain' =>
                            $improvedObtained - $originalObtained,

                        'percentage_gain' =>
                            round($improvedPercentage - $originalPercentage, 2)
                    ],
                    'model_score' => $modelScore
                ]
            ]
        );
    }

    /*
     * 3. Full assessment score
     */
    $sqlSummary = "
        SELECT
            d.dept_id,
            d.status AS department_status,

            COUNT(r.response_id) AS answered_checkpoints,

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
            ) AS revised_checkpoints

        FROM assessment_department d

        LEFT JOIN assessment_response r
            ON r.assessment_id = d.assessment_id
           AND r.dept_id = d.dept_id

        LEFT JOIN assessment_action_plan ap
            ON ap.assessment_id = d.assessment_id
           AND ap.dept_id = d.dept_id
           AND ap.checkpoint_id = r.checkpoint_id

        WHERE d.assessment_id = ?
          AND d.fac_id_fk = ?
          AND d.is_active = 1

        GROUP BY
            d.dept_id,
            d.status

        ORDER BY d.dept_id
    ";

    $stmt = $con->prepare($sqlSummary);

    if (!$stmt) {
        Response::serverError('Assessment score prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $result = $stmt->get_result();

    $departments = [];

    $totalAnswered = 0;
    $totalRevised = 0;

    $totalOriginalObtained = 0;
    $totalImprovedObtained = 0;
    $totalScore = 0;
    $classWeightedOriginal = [];
    $classWeightedImproved = [];

    while ($row = $result->fetch_assoc()) {

        $answered = (int)$row['answered_checkpoints'];
        $revised = (int)$row['revised_checkpoints'];

        $originalObtained = (float)$row['original_obtained_score'];
        $improvedObtained = (float)$row['improved_obtained_score'];
        $scoreBase = scoreDepartmentBase($engine, $facTypeId, (int)$row['dept_id']);
        $possible = (float)$scoreBase['total_score'];

        $originalPercentage = $possible > 0
            ? round(($originalObtained / $possible) * 100, 2)
            : 0;
        $improvedPercentage = $possible > 0
            ? round(($improvedObtained / $possible) * 100, 2)
            : 0;
        $modelScore = scoreModels($con, $engine, $facTypeId, (int)$row['dept_id'], $assessmentId, $policy);
        if ($modelScore !== null && strtoupper((string)$row['department_status']) === 'COMPLETED') {
            $classWeightedOriginal[] = $modelScore['weighted_score']['original_percentage'];
            $classWeightedImproved[] = $modelScore['weighted_score']['improved_percentage'];
        }

        $departments[] = [
            'dept_id' => (int)$row['dept_id'],
            'department_status' => $row['department_status'],

            'answered_checkpoints' => $answered,
            'revised_checkpoints' => $revised,

            'original' => [
                'obtained_score' => $originalObtained,
                'total_score' => $possible,
                'percentage' => $originalPercentage
            ],

            'improved' => [
                'obtained_score' => $improvedObtained,
                'total_score' => $possible,
                'percentage' => $improvedPercentage
            ],

            'improvement' => [
                'score_gain' => round($improvedObtained - $originalObtained, 2),
                'percentage_gain' => round($improvedPercentage - $originalPercentage, 2)
            ],
            'model_score' => $modelScore
        ];

        $totalAnswered += $answered;
        $totalRevised += $revised;

        $totalOriginalObtained += $originalObtained;
        $totalImprovedObtained += $improvedObtained;
        $totalScore += $possible;
    }

    $overallOriginalPercentage = $totalScore > 0
        ? round(($totalOriginalObtained / $totalScore) * 100, 2)
        : 0;

    $overallImprovedPercentage = $totalScore > 0
        ? round(($totalImprovedObtained / $totalScore) * 100, 2)
        : 0;

    $schoolWeightedOriginal = count($classWeightedOriginal) > 0
        ? round(array_sum($classWeightedOriginal) / count($classWeightedOriginal), 2) : null;
    $schoolWeightedImproved = count($classWeightedImproved) > 0
        ? round(array_sum($classWeightedImproved) / count($classWeightedImproved), 2) : null;
    $roundHistory = scoreRoundHistory($con, $engine, $facTypeId, $facId, $frameworkCode, $policy);
    $currentRoundScore = null;
    foreach ($roundHistory as $round) {
        if ((int)$round['round_id'] === (int)($assessment['round_id'] ?? 0)) {
            $currentRoundScore = $round;
            break;
        }
    }

    Response::success(
        'Assessment score calculated successfully',
        [
            'assessment' => [
                'assessment_id' => (int)$assessment['assessment_id'],
                'assessment_name' => $assessment['assessment_name'],
                'framework_code' => $assessment['framework_code'],
                'status' => $assessment['status']
            ],

            'scope' => 'ASSESSMENT',

            'overall_score' => [
                'answered_checkpoints' => $totalAnswered,
                'revised_checkpoints' => $totalRevised,

                'original' => [
                    'obtained_score' => $totalOriginalObtained,
                    'total_score' => $totalScore,
                    'percentage' => $overallOriginalPercentage
                ],

                'improved' => [
                    'obtained_score' => $totalImprovedObtained,
                    'total_score' => $totalScore,
                    'percentage' => $overallImprovedPercentage
                ],

                'improvement' => [
                    'score_gain' => round($totalImprovedObtained - $totalOriginalObtained, 2),
                    'percentage_gain' => round($overallImprovedPercentage - $overallOriginalPercentage, 2)
                ],
                'model_weighted_score' => $schoolWeightedOriginal === null ? null : [
                    'aggregation' => $policy['scope']['overall_aggregation'] ?? 'average_of_completed_class_scores',
                    'original_percentage' => $schoolWeightedOriginal,
                    'improved_percentage' => $schoolWeightedImproved,
                    'original_level' => scorePerformanceLevel($schoolWeightedOriginal, $policy),
                    'improved_level' => scorePerformanceLevel($schoolWeightedImproved, $policy)
                ]
            ],

            'departments' => $departments,
            'round_score' => $currentRoundScore,
            'round_history' => $roundHistory
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

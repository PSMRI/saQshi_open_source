<?php

/**
 * list.php
 * -------------------------------------------------------
 * Lists all assessments for the logged-in user's facility.
 *
 * Method:
 * GET
 *
 * URL:
 * /api/assessment/v1/list.php
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

/**
 * Handles list facility type id processing for this API workflow.
 */
function listFacilityTypeId(int $facId): int
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
 * Handles list checkpoint max score processing for this API workflow.
 */
function listCheckpointMaxScore(array $checkpoint): float
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
 * Handles list framework total score processing for this API workflow.
 */
function listFrameworkTotalScore(
    string $frameworkCode,
    int $facTypeId,
    array $deptIds,
    array &$engineCache
): array {
    if ($facTypeId <= 0 || empty($deptIds)) {
        return [
            'total_checkpoints' => 0,
            'total_score' => 0
        ];
    }

    if (!isset($engineCache[$frameworkCode])) {
        $engineCache[$frameworkCode] = FrameworkEngine::load($frameworkCode);
    }

    $engine = $engineCache[$frameworkCode];
    $totalCheckpoints = 0;
    $totalScore = 0;

    foreach (array_unique(array_map('intval', $deptIds)) as $deptId) {
        if ($deptId <= 0) {
            continue;
        }

        $seen = [];
        $checkpoints = $engine->getCheckpoints($facTypeId, $deptId);

        foreach ($checkpoints as $checkpoint) {
            $checkpointId = (string)($checkpoint['csqa_id'] ?? '');

            if ($checkpointId === '' || isset($seen[$checkpointId])) {
                continue;
            }

            $seen[$checkpointId] = true;
            $totalCheckpoints++;
            $totalScore += listCheckpointMaxScore($checkpoint);
        }
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

    $sql = "
        SELECT
            a.assessment_id,
            a.assessment_name,
            a.framework_code,
            a.fac_id_fk,
            a.start_date,
            a.end_date,
            a.status,
            a.created_by,
            a.created_on,
            a.updated_on,
            a.completed_on,
            a.cancelled_on,
            COALESCE(ds.active_departments, 0) AS active_departments,
            COALESCE(dd.started_departments, 0) AS started_departments,
            COALESCE(dd.completed_departments, 0) AS completed_departments,
            COALESCE(rs.answered_checkpoints, 0) AS answered_checkpoints,
            COALESCE(rs.obtained_score, 0) AS obtained_score
        FROM assessment_master a
        LEFT JOIN (
            SELECT
                ass_period_id,
                COUNT(DISTINCT dept_id) AS active_departments
            FROM assessment_department_status
            WHERE fac_id_fk = ?
              AND is_active = 1
            GROUP BY ass_period_id
        ) ds
            ON ds.ass_period_id = a.assessment_id
        LEFT JOIN (
            SELECT
                assessment_id,
                COUNT(DISTINCT dept_id) AS started_departments,
                COUNT(DISTINCT CASE WHEN status = 'COMPLETED' THEN dept_id END) AS completed_departments
            FROM assessment_department
            WHERE fac_id_fk = ?
              AND is_active = 1
            GROUP BY assessment_id
        ) dd
            ON dd.assessment_id = a.assessment_id
        LEFT JOIN (
            SELECT
                assessment_id,
                COUNT(response_id) AS answered_checkpoints,
                ROUND(COALESCE(SUM(score), 0), 2) AS obtained_score
            FROM assessment_response
            GROUP BY assessment_id
        ) rs
            ON rs.assessment_id = a.assessment_id
        WHERE a.fac_id_fk = ?
        ORDER BY a.assessment_id DESC
    ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('iii', $facId, $facId, $facId);
    $stmt->execute();

    $result = $stmt->get_result();
    $rawAssessments = [];
    $assessmentIds = [];

    while ($row = $result->fetch_assoc()) {
        $rawAssessments[] = $row;
        $assessmentIds[] = (int)$row['assessment_id'];
    }

    $activeDeptMap = [];

    if (!empty($assessmentIds)) {
        $assessmentIds = array_values(array_unique($assessmentIds));
        $placeholders = implode(',', array_fill(0, count($assessmentIds), '?'));

        $sqlActiveDept = "
            SELECT ass_period_id, dept_id
            FROM assessment_department_status
            WHERE fac_id_fk = ?
              AND is_active = 1
              AND ass_period_id IN ($placeholders)
        ";

        $stmtDept = $con->prepare($sqlActiveDept);

        if (!$stmtDept) {
            Response::serverError('Active department prepare failed: ' . $con->error);
        }

        $types = str_repeat('i', count($assessmentIds) + 1);
        $params = array_merge([$facId], $assessmentIds);
        $stmtDept->bind_param($types, ...$params);
        $stmtDept->execute();

        $deptResult = $stmtDept->get_result();

        while ($deptRow = $deptResult->fetch_assoc()) {
            $assessmentId = (int)$deptRow['ass_period_id'];

            if (!isset($activeDeptMap[$assessmentId])) {
                $activeDeptMap[$assessmentId] = [];
            }

            $activeDeptMap[$assessmentId][] = (int)$deptRow['dept_id'];
        }
    }

    $facTypeId = listFacilityTypeId($facId);
    $engineCache = [];
    $assessments = [];

    $summary = [
        'total' => 0,
        'active' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'average_score' => 0
    ];

    $scoreTotal = 0;
    $scoreCount = 0;

    foreach ($rawAssessments as $row) {
        $assessmentId = (int)$row['assessment_id'];
        $obtainedScore = (float)($row['obtained_score'] ?? 0);
        $scoreBase = listFrameworkTotalScore(
            $row['framework_code'] ?: 'saqshi-nqas',
            $facTypeId,
            $activeDeptMap[$assessmentId] ?? [],
            $engineCache
        );
        $totalScore = (float)$scoreBase['total_score'];
        $score = $totalScore > 0
            ? round(($obtainedScore / $totalScore) * 100, 2)
            : 0;
        $status = strtoupper((string)($row['status'] ?? ''));

        $summary['total']++;

        if ($status === 'ACTIVE') {
            $summary['active']++;
        } elseif ($status === 'COMPLETED') {
            $summary['completed']++;
        } elseif ($status === 'CANCELLED') {
            $summary['cancelled']++;
        }

        if ((int)$row['answered_checkpoints'] > 0) {
            $scoreTotal += $score;
            $scoreCount++;
        }

        $assessments[] = [
            'assessment_id' => $assessmentId,
            'assessment_name' => $row['assessment_name'],
            'framework_code' => $row['framework_code'],
            'fac_id' => (int)$row['fac_id_fk'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'status' => $status,
            'created_by' => (int)$row['created_by'],
            'created_on' => $row['created_on'],
            'updated_on' => $row['updated_on'],
            'completed_on' => $row['completed_on'],
            'cancelled_on' => $row['cancelled_on'],
            'active_departments' => (int)$row['active_departments'],
            'started_departments' => (int)$row['started_departments'],
            'completed_departments' => (int)$row['completed_departments'],
            'answered_checkpoints' => (int)$row['answered_checkpoints'],
            'total_checkpoints' => (int)$scoreBase['total_checkpoints'],
            'obtained_score' => $obtainedScore,
            'total_score' => $totalScore,
            'score_percent' => $score
        ];
    }

    $summary['average_score'] = $scoreCount > 0
        ? round($scoreTotal / $scoreCount, 2)
        : 0;

    Response::success(
        'Assessment list fetched successfully',
        [
            'summary' => $summary,
            'assessments' => $assessments
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}

<?php
require __DIR__ . '/../api/assets/conn/db.php';

$ids = json_decode(file_get_contents(__DIR__ . '/inprogress_assessment_ids.json'), true);
$ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));
if (!$ids) {
    throw new RuntimeException('No assessment IDs found.');
}
$idList = implode(',', $ids);

$sql = "
    SELECT
      a.assessment_id,
      a.assessment_name,
      a.status AS assessment_status,
      a.start_date,
      a.end_date,
      a.completed_on,
      COUNT(DISTINCT CASE WHEN d.is_active = 1 THEN d.dept_id END) AS active_departments,
      COUNT(DISTINCT CASE WHEN d.is_active = 1 AND UPPER(d.status) = 'COMPLETED' THEN d.dept_id END) AS completed_departments,
      COUNT(DISTINCT CASE WHEN d.is_active = 1 AND UPPER(d.status) = 'IN_PROGRESS' THEN d.dept_id END) AS in_progress_departments,
      COUNT(DISTINCT CASE WHEN d.is_active = 1 AND UPPER(d.status) NOT IN ('COMPLETED','IN_PROGRESS') THEN d.dept_id END) AS other_pending_departments,
      COUNT(DISTINCT r.checkpoint_id) AS responses,
      COUNT(DISTINCT ap.action_plan_id) AS action_plans,
      COUNT(DISTINCT CASE WHEN UPPER(ap.status) = 'COMPLETED' THEN ap.action_plan_id END) AS completed_action_plans,
      GROUP_CONCAT(DISTINCT CASE WHEN d.is_active = 1 AND UPPER(d.status) <> 'COMPLETED' THEN CONCAT(d.dept_id, ':', d.status) END ORDER BY d.dept_id SEPARATOR ', ') AS pending_department_details
    FROM assessment_master a
    LEFT JOIN assessment_department d ON d.assessment_id = a.assessment_id AND d.fac_id_fk = a.fac_id_fk
    LEFT JOIN assessment_response r ON r.assessment_id = a.assessment_id
    LEFT JOIN assessment_action_plan ap ON ap.assessment_id = a.assessment_id
    WHERE a.assessment_id IN ($idList)
    GROUP BY a.assessment_id, a.assessment_name, a.status, a.start_date, a.end_date, a.completed_on
    ORDER BY a.assessment_id";
$result = $con->query($sql);
$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['classification'] = ((int)$row['active_departments'] === 0)
        ? 'NO_ACTIVE_DEPARTMENT'
        : (((int)$row['active_departments'] === (int)$row['completed_departments'])
            ? 'READY_TO_COMPLETE'
            : 'PENDING_DEPARTMENT_WORK');
    $rows[] = $row;
}

$summary = [];
$departmentStatusSummary = ['IN_PROGRESS' => 0, 'NOT_STARTED' => 0, 'OTHER_PENDING' => 0];
$responseSummary = ['ZERO_RESPONSES' => 0, 'HAS_RESPONSES' => 0];
$readyToComplete = [];
$noActiveDeptBreakdown = ['ACTIVE_SELECTION_EXISTS' => 0, 'NO_ACTIVE_SELECTION' => 0];
foreach ($rows as $row) {
    $key = $row['classification'];
    $summary[$key] = ($summary[$key] ?? 0) + 1;
    if ($row['classification'] === 'PENDING_DEPARTMENT_WORK') {
        if ((int)$row['in_progress_departments'] > 0) {
            $departmentStatusSummary['IN_PROGRESS']++;
        } elseif ((int)$row['other_pending_departments'] > 0) {
            $details = (string)($row['pending_department_details'] ?? '');
            $departmentStatusSummary[str_contains($details, 'NOT_STARTED') ? 'NOT_STARTED' : 'OTHER_PENDING']++;
        }
    }
    $responseSummary[(int)$row['responses'] === 0 ? 'ZERO_RESPONSES' : 'HAS_RESPONSES']++;
    if ($row['classification'] === 'READY_TO_COMPLETE') {
        $readyToComplete[] = [
            'assessment_id' => $row['assessment_id'],
            'assessment_name' => $row['assessment_name'],
            'responses' => $row['responses'],
            'action_plans' => $row['action_plans']
        ];
    }
}
$noActiveIds = array_map(fn($row) => (int)$row['assessment_id'], array_filter($rows, fn($row) => $row['classification'] === 'NO_ACTIVE_DEPARTMENT'));
if ($noActiveIds) {
    $noActiveIdList = implode(',', $noActiveIds);
    $selectionResult = $con->query("SELECT assessment_id, COUNT(*) AS active_selections FROM assessment_department_status WHERE assessment_id IN ($noActiveIdList) AND is_active = 1 GROUP BY assessment_id");
    $selectionMap = [];
    while ($selection = $selectionResult->fetch_assoc()) {
        $selectionMap[(int)$selection['assessment_id']] = (int)$selection['active_selections'];
    }
    foreach ($noActiveIds as $assessmentId) {
        $noActiveDeptBreakdown[($selectionMap[$assessmentId] ?? 0) > 0 ? 'ACTIVE_SELECTION_EXISTS' : 'NO_ACTIVE_SELECTION']++;
    }
}
$claimResult = $con->query("SELECT COUNT(*) AS active_claims, COUNT(DISTINCT assessor_id) AS assessors FROM assessment_section_assignee WHERE assessment_id IN ($idList) AND status = 'IN_PROGRESS'");
$claimSummary = $claimResult->fetch_assoc() ?: ['active_claims' => 0, 'assessors' => 0];
$sharedFacilityResult = $con->query("SELECT COUNT(DISTINCT target.assessment_id) AS target_assessments_with_other_active, COUNT(*) AS other_active_assessments FROM assessment_master target JOIN assessment_master other_assessment ON other_assessment.fac_id_fk = target.fac_id_fk AND other_assessment.assessment_id <> target.assessment_id AND other_assessment.status = 'ACTIVE' WHERE target.assessment_id IN ($idList)");
$sharedFacilitySummary = $sharedFacilityResult->fetch_assoc() ?: ['target_assessments_with_other_active' => 0, 'other_active_assessments' => 0];
echo json_encode([
    'total' => count($rows),
    'summary' => $summary,
    'pending_status_summary' => $departmentStatusSummary,
    'response_summary' => $responseSummary,
    'no_active_department_breakdown' => $noActiveDeptBreakdown,
    'active_section_claims_to_be_released' => $claimSummary,
    'target_assessments_sharing_a_school_with_another_active_assessment' => $sharedFacilitySummary,
    'ready_to_complete' => $readyToComplete,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

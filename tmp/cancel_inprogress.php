<?php
require __DIR__ . '/../api/assets/conn/db.php';

$ids = json_decode(file_get_contents(__DIR__ . '/inprogress_assessment_ids.json'), true);
$ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
if (count($ids) !== 184) {
    throw new RuntimeException('Expected exactly 184 unique assessment IDs; found ' . count($ids) . '.');
}

$idList = implode(',', $ids);
$countResult = $con->query("SELECT COUNT(*) AS active_count FROM assessment_master WHERE assessment_id IN ($idList) AND status = 'ACTIVE'");
$activeCount = (int)(($countResult->fetch_assoc() ?: [])['active_count'] ?? 0);
if ($activeCount !== 184) {
    throw new RuntimeException("Cancellation stopped: expected 184 ACTIVE assessments, found $activeCount.");
}

$con->begin_transaction();
try {
    $release = $con->query("UPDATE assessment_section_assignee SET status = 'RELEASED', released_on = COALESCE(released_on, CURRENT_TIMESTAMP) WHERE assessment_id IN ($idList) AND status = 'IN_PROGRESS'");
    if ($release === false) throw new RuntimeException('Claim release failed: ' . $con->error);
    $releasedClaims = $con->affected_rows;

    $deactivate = $con->query("UPDATE assessment_department_status SET is_active = 0, status = 'INACTIVE', updated_on = CURRENT_TIMESTAMP WHERE assessment_id IN ($idList) AND is_active = 1");
    if ($deactivate === false) throw new RuntimeException('Department-status cleanup failed: ' . $con->error);
    $deactivatedStatuses = $con->affected_rows;

    $cancel = $con->query("UPDATE assessment_master SET status = 'CANCELLED', cancelled_on = CURRENT_TIMESTAMP WHERE assessment_id IN ($idList) AND status = 'ACTIVE'");
    if ($cancel === false) throw new RuntimeException('Assessment cancellation failed: ' . $con->error);
    $cancelledAssessments = $con->affected_rows;
    if ($cancelledAssessments !== 184) throw new RuntimeException("Cancellation stopped: changed $cancelledAssessments assessments, expected 184.");

    $con->commit();
    echo json_encode([
        'cancelled_assessments' => $cancelledAssessments,
        'released_class_claims' => $releasedClaims,
        'deactivated_department_statuses' => $deactivatedStatuses,
    ], JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $e) {
    $con->rollback();
    throw $e;
}

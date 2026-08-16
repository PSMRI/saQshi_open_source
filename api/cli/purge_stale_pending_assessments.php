<?php
/**
 * Safely cancels stale assessor drafts without deleting assessment history.
 * Usage: php api/cli/purge_stale_pending_assessments.php [--days=7] [--apply]
 * Default: preview only. Add --apply to perform the cancellation.
 */

require_once __DIR__ . '/../assets/conn/db.php';

$days = 7;
$apply = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') $apply = true;
    if (str_starts_with($argument, '--days=')) $days = max(1, (int)substr($argument, 7));
}

$sql = "
    SELECT assessment_id
    FROM assessment_master
    WHERE status = 'PENDING'
      AND assigned_assessor_id IS NOT NULL
      AND created_on < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? DAY)
      AND NOT EXISTS (
          SELECT 1 FROM assessment_section_assignee asa
          WHERE asa.assessment_id = assessment_master.assessment_id
            AND asa.status = 'IN_PROGRESS'
      )
      AND NOT EXISTS (
          SELECT 1 FROM assessment_response ar
          WHERE ar.assessment_id = assessment_master.assessment_id
      )";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $days);
$stmt->execute();
$ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'assessment_id'));

if (!$apply || !$ids) {
    echo json_encode(['mode' => $apply ? 'apply' : 'preview', 'days' => $days, 'stale_pending_drafts' => count($ids), 'assessment_ids' => $ids], JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

$idList = implode(',', $ids);
$con->begin_transaction();
try {
    $con->query("UPDATE assessment_department_status SET is_active = 0, status = 'INACTIVE', updated_on = CURRENT_TIMESTAMP WHERE assessment_id IN ($idList) AND is_active = 1");
    $con->query("UPDATE assessment_master SET status = 'CANCELLED', cancelled_on = CURRENT_TIMESTAMP WHERE assessment_id IN ($idList) AND status = 'PENDING'");
    $con->commit();
    echo json_encode(['mode' => 'apply', 'days' => $days, 'cancelled_stale_pending_drafts' => $con->affected_rows], JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $e) {
    $con->rollback();
    throw $e;
}

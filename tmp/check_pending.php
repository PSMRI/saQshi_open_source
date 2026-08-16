<?php
require __DIR__ . '/../api/assets/conn/db.php';

$ids = json_decode(file_get_contents(__DIR__ . '/inprogress_assessment_ids.json'), true);
$ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
$idList = implode(',', $ids);

$statusResult = $con->query("SELECT status, COUNT(*) AS total FROM assessment_master WHERE assessment_id IN ($idList) GROUP BY status ORDER BY status");
$statuses = [];
while ($row = $statusResult->fetch_assoc()) $statuses[] = $row;

$pendingResult = $con->query("SELECT assessment_id, assessment_name, status, start_date, end_date FROM assessment_master WHERE assessment_id IN ($idList) AND status IN ('ACTIVE','PENDING') ORDER BY assessment_id");
$pending = [];
while ($row = $pendingResult->fetch_assoc()) $pending[] = $row;

echo json_encode(['status_summary' => $statuses, 'pending_or_active' => $pending], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

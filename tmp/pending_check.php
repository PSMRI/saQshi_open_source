<?php
require __DIR__ . '/../api/assets/conn/db.php';

$ids = json_decode(file_get_contents(__DIR__ . '/pending_assessment_ids.json'), true);
$ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
$idList = implode(',', $ids);

$statusResult = $con->query("SELECT status, COUNT(*) AS total FROM assessment_master WHERE assessment_id IN ($idList) GROUP BY status ORDER BY status");
$summary = [];
while ($row = $statusResult->fetch_assoc()) $summary[] = $row;

$pendingResult = $con->query("SELECT assessment_id, assessment_name, status, start_date, end_date FROM assessment_master WHERE assessment_id IN ($idList) AND status IN ('PENDING', 'ACTIVE') ORDER BY status, assessment_id");
$pending = [];
while ($row = $pendingResult->fetch_assoc()) $pending[] = $row;

echo json_encode(['total_ids' => count($ids), 'status_summary' => $summary, 'pending_or_active_count' => count($pending), 'pending_or_active' => $pending], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

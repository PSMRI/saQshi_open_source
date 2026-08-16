<?php
require __DIR__ . '/../api/assets/conn/db.php';

$summary = [];
$status = $con->query("SELECT UPPER(COALESCE(status, 'UNKNOWN')) AS status, COUNT(*) AS assessments FROM assessment_master GROUP BY UPPER(COALESCE(status, 'UNKNOWN')) ORDER BY status");
while ($row = $status->fetch_assoc()) $summary[] = $row;

$completed = $con->query("SELECT
    (SELECT COUNT(*) FROM assessment_master WHERE status = 'COMPLETED') AS completed_assessments,
    (SELECT COUNT(DISTINCT ar.assessment_id) FROM assessment_response ar JOIN assessment_master am ON am.assessment_id = ar.assessment_id WHERE am.status = 'COMPLETED') AS completed_with_responses,
    (SELECT COUNT(*) FROM assessment_response ar JOIN assessment_master am ON am.assessment_id = ar.assessment_id WHERE am.status = 'COMPLETED') AS responses_in_completed_assessments
")->fetch_assoc();

echo json_encode(['assessment_status_summary' => $summary, 'completed_assessment_summary' => $completed], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

<?php
/** Read-only data export used to build the school/class/assessor Excel report. */
require __DIR__ . '/../api/assets/conn/db.php';
require_once __DIR__ . '/../api/core/Crypto.php';

function rows(mysqli $con, string $sql): array
{
    $result = $con->query($sql);
    if (!$result) {
        throw new RuntimeException($con->error);
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

$schoolClass = rows($con, "SELECT
    f.fac_name AS school_name, CAST(f.NIN_no AS CHAR) AS udise_code,
    f.Dist_Name AS district, f.Block_Name AS block, asa.dept_id,
    COUNT(*) AS assessment_count,
    SUM(asa.status = 'COMPLETED') AS completed_count,
    SUM(asa.status = 'IN_PROGRESS') AS in_progress_count,
    SUM(asa.status = 'RELEASED') AS released_count,
    MIN(asa.assigned_on) AS first_assessment_on,
    MAX(COALESCE(asa.completed_on, asa.released_on, asa.assigned_on)) AS last_activity_on
FROM assessment_section_assignee asa
JOIN facilities f ON f.fac_id = asa.fac_id_fk
GROUP BY f.fac_id, f.fac_name, f.NIN_no, f.Dist_Name, f.Block_Name, asa.dept_id
ORDER BY f.Dist_Name, f.Block_Name, f.fac_name, asa.dept_id");

$noAssessment = rows($con, "SELECT
    f.fac_name AS school_name, CAST(f.NIN_no AS CHAR) AS udise_code,
    f.Dist_Name AS district, f.Block_Name AS block, f.state_name AS state, f.division
FROM facilities f
WHERE NOT EXISTS (
    SELECT 1 FROM assessment_section_assignee asa WHERE asa.fac_id_fk = f.fac_id
)
ORDER BY f.Dist_Name, f.Block_Name, f.fac_name");

$assessorClass = rows($con, "SELECT
    am.assessor_code, am.assessor_name, am.mobile_no,
    f.Dist_Name AS district, f.Block_Name AS block,
    f.fac_name AS school_name, CAST(f.NIN_no AS CHAR) AS udise_code,
    asa.dept_id,
    COUNT(*) AS class_assessment_count,
    SUM(asa.status = 'COMPLETED') AS completed_count,
    SUM(asa.status = 'IN_PROGRESS') AS in_progress_count,
    SUM(asa.status = 'RELEASED') AS released_count,
    MAX(COALESCE(asa.completed_on, asa.released_on, asa.assigned_on)) AS last_activity_on
FROM assessment_section_assignee asa
JOIN assessor_master am ON am.assessor_id = asa.assessor_id
JOIN facilities f ON f.fac_id = asa.fac_id_fk
GROUP BY am.assessor_id, am.assessor_code, am.assessor_name, am.mobile_no,
    f.fac_id, f.fac_name, f.NIN_no, f.Dist_Name, f.Block_Name, asa.dept_id
ORDER BY f.Dist_Name, f.Block_Name, am.assessor_code, f.fac_name, asa.dept_id");

$assessorSummary = rows($con, "SELECT
    am.assessor_code, am.assessor_name, am.mobile_no,
    COALESCE(MAX(f.Dist_Name), '') AS district,
    COALESCE(MAX(f.Block_Name), '') AS block,
    COUNT(DISTINCT asa.fac_id_fk) AS schools_assessed,
    COUNT(*) AS total_class_assessments,
    SUM(asa.status = 'COMPLETED') AS completed_classes,
    SUM(asa.status = 'IN_PROGRESS') AS in_progress_classes,
    SUM(asa.status = 'RELEASED') AS released_classes
FROM assessor_master am
LEFT JOIN assessment_section_assignee asa ON asa.assessor_id = am.assessor_id
LEFT JOIN facilities f ON f.fac_id = asa.fac_id_fk
WHERE am.is_active = 1
GROUP BY am.assessor_id, am.assessor_code, am.assessor_name, am.mobile_no
ORDER BY district, block, am.assessor_code");

foreach ([$assessorClass, $assessorSummary] as &$set) {
    foreach ($set as &$row) {
        $row['assessor_name'] = Crypto::decrypt($row['assessor_name'] ?? '');
        $row['mobile_no'] = Crypto::decrypt($row['mobile_no'] ?? '');
    }
}
unset($set, $row);

$classNames = [1 => 'Class 9', 2 => 'Class 10', 3 => 'Class 11', 4 => 'Class 12'];
foreach ([$schoolClass, $assessorClass] as &$set) {
    foreach ($set as &$row) {
        $row['class_name'] = $classNames[(int)$row['dept_id']] ?? ('Class ' . $row['dept_id']);
    }
}
unset($set, $row);

echo json_encode([
    'generated_at' => date('Y-m-d H:i:s'),
    'school_class' => $schoolClass,
    'no_assessment' => $noAssessment,
    'assessor_class' => $assessorClass,
    'assessor_summary' => $assessorSummary
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

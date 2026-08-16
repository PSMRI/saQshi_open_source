<?php
require_once __DIR__ . '/../assets/conn/db.php';

$rows = $con->query("SELECT a.assessment_id, a.fac_id_fk, a.assessment_name, s.dept_id
    FROM assessment_master a
    INNER JOIN assessment_section_assignee s ON s.assessment_id = a.assessment_id
    WHERE a.assigned_assessor_id IS NOT NULL")->fetch_all(MYSQLI_ASSOC);

foreach ($rows as $row) {
    $prior = $con->prepare("SELECT 1 FROM assessment_section_assignee
        WHERE fac_id_fk = ? AND dept_id = ? AND assessment_id < ? AND status IN ('COMPLETED','RELEASED') LIMIT 1");
    $prior->bind_param('iii', $row['fac_id_fk'], $row['dept_id'], $row['assessment_id']);
    $prior->execute();
    $prefix = $prior->get_result()->fetch_assoc() ? 'Reassessment' : 'New Assessment';
    $name = $prefix . ' - Class/Department ' . (int)$row['dept_id'];
    $update = $con->prepare('UPDATE assessment_master SET assessment_name = ? WHERE assessment_id = ?');
    $update->bind_param('si', $name, $row['assessment_id']);
    $update->execute();
    echo $row['assessment_id'] . ': ' . $name . PHP_EOL;
}

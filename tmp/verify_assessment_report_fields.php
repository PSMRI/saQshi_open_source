<?php
require __DIR__ . '/../api/assets/conn/db.php';

$result = $con->query("SELECT a.assessment_id, ai.dept_id AS class_id, ai.assessor_name, ai.assessee_name, ai.teacher_code, ai.subject_name, ai.class_section
    FROM assessment_master a
    LEFT JOIN assessment_assessor_info ai ON ai.assessment_id = a.assessment_id
    ORDER BY a.assessment_id DESC, ai.dept_id
    LIMIT 3");
if ($result === false) throw new RuntimeException($con->error);
echo json_encode($result->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_SLASHES), PHP_EOL;

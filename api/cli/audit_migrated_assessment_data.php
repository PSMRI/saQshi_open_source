<?php

declare(strict_types=1);

require_once __DIR__ . '/../assets/conn/db.php';

$checks = [
    'database' => 'SELECT DATABASE() AS database_name',
    'completed_parent_with_pending_class' => "
        SELECT a.assessment_id, a.assessment_name, a.fac_id_fk, a.status,
               COUNT(DISTINCT ad.dept_id) AS active_classes,
               COUNT(DISTINCT CASE WHEN UPPER(COALESCE(ad.status, '')) = 'COMPLETED' THEN ad.dept_id END) AS completed_classes,
               COUNT(DISTINCT ar.dept_id, ar.checkpoint_id) AS saved_responses
        FROM assessment_master a
        LEFT JOIN assessment_department ad
          ON ad.assessment_id = a.assessment_id AND ad.is_active = 1
        LEFT JOIN assessment_response ar ON ar.assessment_id = a.assessment_id
        WHERE UPPER(a.status) = 'COMPLETED'
        GROUP BY a.assessment_id, a.assessment_name, a.fac_id_fk, a.status
        HAVING active_classes > completed_classes
            OR (active_classes > 0 AND saved_responses < active_classes * 38)
        ORDER BY a.assessment_id DESC
        LIMIT 100
    ",
    'completed_class_with_fewer_than_38_responses' => "
        SELECT a.assessment_id, a.assessment_name, a.fac_id_fk,
               ad.dept_id, ad.status AS department_status,
               COUNT(DISTINCT ar.checkpoint_id) AS saved_responses
        FROM assessment_master a
        JOIN assessment_department ad
          ON ad.assessment_id = a.assessment_id
         AND ad.is_active = 1
         AND UPPER(ad.status) = 'COMPLETED'
        LEFT JOIN assessment_response ar
          ON ar.assessment_id = a.assessment_id
         AND ar.dept_id = ad.dept_id
        WHERE a.framework_code = 'saqshi-education'
        GROUP BY a.assessment_id, a.assessment_name, a.fac_id_fk, ad.dept_id, ad.status
        HAVING saved_responses < 38
        ORDER BY a.assessment_id DESC
        LIMIT 100
    ",
    'responses_without_an_active_class' => '
        SELECT ar.assessment_id, ar.dept_id, COUNT(*) AS response_rows
        FROM assessment_response ar
        LEFT JOIN assessment_department ad
          ON ad.assessment_id = ar.assessment_id
         AND ad.dept_id = ar.dept_id
         AND ad.is_active = 1
        LEFT JOIN assessment_master a ON a.assessment_id = ar.assessment_id
        WHERE ad.assessment_id IS NULL
          AND UPPER(COALESCE(a.status, CHAR(0))) <> CHAR(67,65,78,67,69,76,76,69,68)
        GROUP BY ar.assessment_id, ar.dept_id
        ORDER BY ar.assessment_id DESC
        LIMIT 100
    ',
    'workflow_status_without_active_class' => '
        SELECT ads.assessment_id, ads.fac_id_fk, ads.dept_id, ads.status AS workflow_status
        FROM assessment_department_status ads
        LEFT JOIN assessment_department ad
          ON ad.assessment_id = ads.assessment_id
         AND ad.fac_id_fk = ads.fac_id_fk
         AND ad.dept_id = ads.dept_id
         AND ad.is_active = 1
        WHERE ads.is_active = 1
          AND ad.assessment_id IS NULL
        ORDER BY ads.assessment_id DESC
        LIMIT 100
    ',
    'workflow_status_without_active_class_summary' => '
        SELECT UPPER(COALESCE(a.status, CHAR(77,73,83,83,73,78,71))) AS assessment_status,
               COUNT(*) AS status_rows,
               COUNT(DISTINCT ads.assessment_id) AS assessments
        FROM assessment_department_status ads
        LEFT JOIN assessment_department ad
          ON ad.assessment_id = ads.assessment_id
         AND ad.fac_id_fk = ads.fac_id_fk
         AND ad.dept_id = ads.dept_id
         AND ad.is_active = 1
        LEFT JOIN assessment_master a ON a.assessment_id = ads.assessment_id
        WHERE ads.is_active = 1
          AND ad.assessment_id IS NULL
        GROUP BY UPPER(COALESCE(a.status, CHAR(77,73,83,83,73,78,71)))
        ORDER BY status_rows DESC
    ',
    'duplicate_active_classes' => '
        SELECT assessment_id, dept_id, COUNT(*) AS duplicate_rows
        FROM assessment_department
        WHERE is_active = 1
        GROUP BY assessment_id, dept_id
        HAVING COUNT(*) > 1
        LIMIT 100
    ',
    'completed_missing_round' => "
        SELECT assessment_id, assessment_name, fac_id_fk, status, round_id
        FROM assessment_master
        WHERE UPPER(status) = 'COMPLETED'
          AND (round_id IS NULL OR round_id = 0)
        ORDER BY assessment_id DESC
        LIMIT 100
    ",
    'completed_round_without_completed_assessment' => "
        SELECT fr.round_id, fr.fac_id, fr.round_no, fr.status,
               COUNT(a.assessment_id) AS completed_assessments
        FROM facility_assessment_round fr
        LEFT JOIN assessment_master a
          ON a.round_id = fr.round_id
         AND UPPER(a.status) = 'COMPLETED'
        WHERE UPPER(fr.status) = 'COMPLETED'
        GROUP BY fr.round_id, fr.fac_id, fr.round_no, fr.status
        HAVING completed_assessments = 0
        ORDER BY fr.round_id DESC
        LIMIT 100
    ",
    'same_class_active_in_multiple_assessments' => "
        SELECT asa.fac_id_fk, asa.dept_id,
               COUNT(DISTINCT asa.assessment_id) AS active_assessments,
               GROUP_CONCAT(DISTINCT asa.assessment_id ORDER BY asa.assessment_id) AS assessment_ids
        FROM assessment_section_assignee asa
        JOIN assessment_master a ON a.assessment_id = asa.assessment_id
        WHERE asa.status = 'IN_PROGRESS'
          AND UPPER(a.status) = 'ACTIVE'
        GROUP BY asa.fac_id_fk, asa.dept_id
        HAVING active_assessments > 1
        ORDER BY active_assessments DESC, asa.fac_id_fk
        LIMIT 100
    ",
    'active_assessment_without_active_class' => "
        SELECT a.assessment_id, a.assessment_name, a.fac_id_fk, a.status
        FROM assessment_master a
        LEFT JOIN assessment_department ad
          ON ad.assessment_id = a.assessment_id
         AND ad.is_active = 1
        WHERE UPPER(a.status) = 'ACTIVE'
        GROUP BY a.assessment_id, a.assessment_name, a.fac_id_fk, a.status
        HAVING COUNT(ad.assessment_id) = 0
        ORDER BY a.assessment_id DESC
        LIMIT 100
    "
];

foreach ($checks as $name => $sql) {
    echo "-- {$name} --\n";
    $result = $con->query($sql);
    if (!$result) {
        echo "ERROR: {$con->error}\n";
        continue;
    }

    $count = 0;
    while ($row = $result->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
        $count++;
    }
    echo "rows={$count}\n";
}

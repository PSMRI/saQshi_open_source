<?php
/** Read-only local data audit for assessor, class-claim and district-user scope. */
require __DIR__ . '/../api/assets/conn/db.php';

function report(mysqli $con, string $name, string $sql): void
{
    $result = $con->query($sql);
    if (!$result) {
        echo $name . ': ERROR ' . $con->error . PHP_EOL;
        return;
    }
    echo $name . ': ' . json_encode($result->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

report($con, 'summary', "SELECT
    (SELECT COUNT(*) FROM s_user WHERE role_id_fk = 10 AND is_active = 1) AS active_assessor_users,
    (SELECT COUNT(*) FROM assessor_master WHERE is_active = 1) AS active_assessor_profiles,
    (SELECT COUNT(*) FROM assessor_facility_mapping WHERE assignment_status = 'ACTIVE') AS active_assessor_mappings,
    (SELECT COUNT(*) FROM s_user WHERE role_id_fk = 4 AND is_active = 1) AS active_district_users,
    (SELECT COUNT(*) FROM assessment_section_assignee WHERE status = 'IN_PROGRESS') AS active_class_claims");

report($con, 'assessor_login_mismatches', "SELECT am.assessor_id, am.assessor_code, am.user_id, u.u_name, u.role_id_fk, u.is_active
    FROM assessor_master am
    LEFT JOIN s_user u ON u.u_id = am.user_id
    WHERE am.is_active = 1
      AND (u.u_id IS NULL OR u.role_id_fk <> 10 OR u.is_active <> 1 OR u.u_name <> am.assessor_code)");

report($con, 'duplicate_active_mappings', "SELECT assessor_id, fac_id, COUNT(*) AS mapping_count
    FROM assessor_facility_mapping
    WHERE assignment_status = 'ACTIVE'
    GROUP BY assessor_id, fac_id
    HAVING COUNT(*) > 1");

report($con, 'assessor_multiple_active_classes', "SELECT fac_id_fk, assessor_id, COUNT(DISTINCT dept_id) AS active_classes,
    GROUP_CONCAT(DISTINCT dept_id ORDER BY dept_id) AS dept_ids
    FROM assessment_section_assignee
    WHERE status = 'IN_PROGRESS'
    GROUP BY fac_id_fk, assessor_id
    HAVING COUNT(DISTINCT dept_id) > 1");

report($con, 'class_claimed_by_multiple_assessors', "SELECT fac_id_fk, dept_id, COUNT(DISTINCT assessor_id) AS assessor_count,
    GROUP_CONCAT(DISTINCT assessor_id ORDER BY assessor_id) AS assessor_ids
    FROM assessment_section_assignee
    WHERE status = 'IN_PROGRESS'
    GROUP BY fac_id_fk, dept_id
    HAVING COUNT(DISTINCT assessor_id) > 1");

report($con, 'completed_assessments_with_multiple_classes', "SELECT assessment.assessment_id, assessment.fac_id_fk, COUNT(DISTINCT claim.dept_id) AS completed_classes
    FROM assessment_master assessment
    JOIN assessment_section_assignee claim ON claim.assessment_id = assessment.assessment_id
    WHERE assessment.status = 'COMPLETED' AND claim.status = 'COMPLETED'
    GROUP BY assessment.assessment_id, assessment.fac_id_fk
    HAVING COUNT(DISTINCT claim.dept_id) > 1");

report($con, 'released_claims_still_active_in_cancelled_assessments', "SELECT claim.assessment_id, claim.fac_id_fk, claim.dept_id
    FROM assessment_section_assignee claim
    JOIN assessment_master assessment ON assessment.assessment_id = claim.assessment_id
    JOIN assessment_department_status status_row
      ON status_row.assessment_id = claim.assessment_id
     AND status_row.fac_id_fk = claim.fac_id_fk
     AND status_row.dept_id = claim.dept_id
    WHERE claim.status = 'RELEASED'
      AND assessment.status = 'CANCELLED'
      AND status_row.is_active = 1");

report($con, 'district_users_without_valid_scope', "SELECT u.u_id, u.u_name, u.dist_id
    FROM s_user u
    LEFT JOIN facilities f ON f.dist_id = u.dist_id
    WHERE u.role_id_fk = 4 AND u.is_active = 1
    GROUP BY u.u_id, u.u_name, u.dist_id
    HAVING u.dist_id IS NULL OR u.dist_id = 0 OR COUNT(f.fac_id) = 0");

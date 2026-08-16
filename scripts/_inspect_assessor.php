<?php
require __DIR__ . '/../api/assets/conn/db.php';
require_once __DIR__ . '/../api/core/Crypto.php';

echo "ASSESSORS\n" . json_encode(
    $con->query("SELECT assessor_id, user_id, assessor_code, is_active FROM assessor_master WHERE assessor_code LIKE 'RAD%'")->fetch_all(MYSQLI_ASSOC),
    JSON_UNESCAPED_SLASHES
) . PHP_EOL;

echo "USERS\n" . json_encode(
    $con->query("SELECT u_id, u_name, role_id_fk, is_active, user_type FROM s_user WHERE u_name LIKE 'RAD%'")->fetch_all(MYSQLI_ASSOC),
    JSON_UNESCAPED_SLASHES
) . PHP_EOL;

echo "ACTIVE-ASSESSMENTS\n" . json_encode(
    $con->query("SELECT assessment_id, fac_id_fk, assessment_name, status, assigned_assessor_id FROM assessment_master WHERE fac_id_fk = 15959 AND status IN ('ACTIVE', 'PENDING') ORDER BY assessment_id DESC")->fetch_all(MYSQLI_ASSOC),
    JSON_UNESCAPED_SLASHES
) . PHP_EOL;

echo "RECENT-ASSESSMENTS\n" . json_encode(
    $con->query("SELECT assessment_id, assessment_name, status, start_date, completed_on, cancelled_on, assigned_assessor_id FROM assessment_master WHERE fac_id_fk = 15959 AND assigned_assessor_id = 1051 ORDER BY assessment_id DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC),
    JSON_UNESCAPED_SLASHES
) . PHP_EOL;

echo "RESPONSE-TABLES\n" . json_encode(
    $con->query("SHOW TABLES LIKE '%response%'")->fetch_all(MYSQLI_ASSOC),
    JSON_UNESCAPED_SLASHES
) . PHP_EOL;

echo "RESPONSE-COLUMNS\n" . json_encode(
    $con->query('SHOW COLUMNS FROM assessment_response')->fetch_all(MYSQLI_ASSOC),
    JSON_UNESCAPED_SLASHES
) . PHP_EOL;

$claims = $con->query("SELECT f.fac_id, f.NIN_no, am.assessment_id, am.status AS assessment_status, asa.dept_id, asa.status AS claim_status, asa.assessor_id, assessor.user_id, user.u_name AS login_user_id, assessor.assessor_code, assessor.assessor_name, asa.assigned_on, asa.released_on
        FROM facilities f
        JOIN assessment_section_assignee asa ON asa.fac_id_fk = f.fac_id
        JOIN assessment_master am ON am.assessment_id = asa.assessment_id
        LEFT JOIN assessor_master assessor ON assessor.assessor_id = asa.assessor_id
        LEFT JOIN s_user user ON user.u_id = assessor.user_id
        WHERE f.NIN_no = '10190506505'
        ORDER BY asa.dept_id, asa.assigned_on DESC")->fetch_all(MYSQLI_ASSOC);
foreach ($claims as &$claim) {
    $claim['assessor_name'] = Crypto::decrypt($claim['assessor_name'] ?? '');
}
unset($claim);
echo "UDISE-CLAIMS\n" . json_encode($claims, JSON_UNESCAPED_SLASHES) . PHP_EOL;

$mapped = $con->query("SELECT mapping.assessor_id, assessor.user_id, user.u_name AS login_user_id, assessor.assessor_code, assessor.assessor_name, mapping.assignment_status
    FROM assessor_facility_mapping mapping
    JOIN assessor_master assessor ON assessor.assessor_id = mapping.assessor_id
    LEFT JOIN s_user user ON user.u_id = assessor.user_id
    WHERE mapping.fac_id = 19639
    ORDER BY mapping.assignment_status, assessor.assessor_code")->fetch_all(MYSQLI_ASSOC);
foreach ($mapped as &$assessor) {
    $assessor['assessor_name'] = Crypto::decrypt($assessor['assessor_name'] ?? '');
}
unset($assessor);
echo "UDISE-MAPPED-ASSESSORS\n" . json_encode($mapped, JSON_UNESCAPED_SLASHES) . PHP_EOL;

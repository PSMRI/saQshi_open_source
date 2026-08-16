-- Release class claims whose parent assessment is already cancelled.
-- Take a database backup first. Review the first SELECT before COMMIT.
-- Compatible with MySQL 5.7+.

START TRANSACTION;

SELECT
    claim.assignment_id,
    claim.assessment_id,
    claim.fac_id_fk,
    claim.dept_id,
    claim.assessor_id,
    claim.status AS claim_status,
    assessment.status AS assessment_status
FROM assessment_section_assignee AS claim
INNER JOIN assessment_master AS assessment
    ON assessment.assessment_id = claim.assessment_id
WHERE claim.status = 'IN_PROGRESS'
  AND UPPER(assessment.status) = 'CANCELLED'
ORDER BY claim.fac_id_fk, claim.assessment_id, claim.dept_id;

UPDATE assessment_section_assignee AS claim
INNER JOIN assessment_master AS assessment
    ON assessment.assessment_id = claim.assessment_id
SET claim.status = 'RELEASED',
    claim.released_on = COALESCE(claim.released_on, CURRENT_TIMESTAMP)
WHERE claim.status = 'IN_PROGRESS'
  AND UPPER(assessment.status) = 'CANCELLED';

UPDATE assessment_department_status AS department_status
INNER JOIN assessment_master AS assessment
    ON assessment.assessment_id = department_status.assessment_id
SET department_status.is_active = 0,
    department_status.status = 'INACTIVE',
    department_status.updated_on = CURRENT_TIMESTAMP
WHERE department_status.is_active = 1
  AND UPPER(assessment.status) = 'CANCELLED';

-- Must return zero rows after the repair.
SELECT claim.assignment_id, claim.assessment_id, claim.fac_id_fk, claim.dept_id
FROM assessment_section_assignee AS claim
INNER JOIN assessment_master AS assessment
    ON assessment.assessment_id = claim.assessment_id
WHERE claim.status = 'IN_PROGRESS'
  AND UPPER(assessment.status) = 'CANCELLED';

COMMIT;

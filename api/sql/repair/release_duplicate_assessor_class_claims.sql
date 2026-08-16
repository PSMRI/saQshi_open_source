-- SaQshi Open Source
-- Keep one active class per assessor per school.
--
-- Run this once after taking a database backup.  It never changes rows that
-- are already COMPLETED.  For each assessor-school pair with more than one
-- IN_PROGRESS class, it keeps the earliest claim and releases later claims.
--
-- Compatible with MySQL 5.7+ (does not require window functions).

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS sq_duplicate_class_claims;
CREATE TEMPORARY TABLE sq_duplicate_class_claims AS
SELECT
    current_claim.assignment_id,
    current_claim.assessment_id,
    current_claim.fac_id_fk,
    current_claim.dept_id,
    current_claim.assessor_id
FROM assessment_section_assignee AS current_claim
WHERE current_claim.status = 'IN_PROGRESS'
  AND EXISTS (
      SELECT 1
      FROM assessment_section_assignee AS earlier_claim
      WHERE earlier_claim.fac_id_fk = current_claim.fac_id_fk
        AND earlier_claim.assessor_id = current_claim.assessor_id
        AND earlier_claim.status = 'IN_PROGRESS'
        AND (
            earlier_claim.assigned_on < current_claim.assigned_on
            OR (
                earlier_claim.assigned_on = current_claim.assigned_on
                AND earlier_claim.assignment_id < current_claim.assignment_id
            )
        )
  );

-- Review this result before committing.  Each row is a class that will be released.
SELECT * FROM sq_duplicate_class_claims
ORDER BY fac_id_fk, assessor_id, assessment_id, dept_id;

-- Release the later duplicate class claim.  Completed claims are not selected.
UPDATE assessment_section_assignee AS claim_row
INNER JOIN sq_duplicate_class_claims AS duplicate_claim
    ON duplicate_claim.assignment_id = claim_row.assignment_id
SET claim_row.status = 'RELEASED',
    claim_row.released_on = COALESCE(claim_row.released_on, CURRENT_TIMESTAMP);

-- Remove released classes from the active-class count and allow them to be
-- claimed again by another mapped assessor.
UPDATE assessment_department_status AS department_status
INNER JOIN sq_duplicate_class_claims AS duplicate_claim
    ON duplicate_claim.assessment_id = department_status.assessment_id
   AND duplicate_claim.fac_id_fk = department_status.fac_id_fk
   AND duplicate_claim.dept_id = department_status.dept_id
SET department_status.is_active = 0,
    department_status.status = 'INACTIVE',
    department_status.updated_on = CURRENT_TIMESTAMP
WHERE department_status.is_active = 1;

-- Older cancelled assessments can already contain RELEASED claims from a
-- prior cancellation. They are no longer claimable work and must not remain
-- in the displayed class/checklist total.
UPDATE assessment_department_status AS department_status
INNER JOIN assessment_section_assignee AS released_claim
    ON released_claim.assessment_id = department_status.assessment_id
   AND released_claim.fac_id_fk = department_status.fac_id_fk
   AND released_claim.dept_id = department_status.dept_id
INNER JOIN assessment_master AS assessment
    ON assessment.assessment_id = released_claim.assessment_id
SET department_status.is_active = 0,
    department_status.status = 'INACTIVE',
    department_status.updated_on = CURRENT_TIMESTAMP
WHERE released_claim.status = 'RELEASED'
  AND assessment.status = 'CANCELLED'
  AND department_status.is_active = 1;

-- Final check: this query must return no rows.
SELECT
    fac_id_fk,
    assessor_id,
    COUNT(DISTINCT dept_id) AS active_classes,
    GROUP_CONCAT(DISTINCT dept_id ORDER BY dept_id) AS dept_ids
FROM assessment_section_assignee
WHERE status = 'IN_PROGRESS'
GROUP BY fac_id_fk, assessor_id
HAVING COUNT(DISTINCT dept_id) > 1;

COMMIT;

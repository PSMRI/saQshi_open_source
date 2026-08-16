-- SaQshi production-data repair (MySQL 8+)
-- Scope:
--   1) Reopen parents marked COMPLETED while an active class is still pending.
--   2) Deactivate stale active workflow/class-assignment rows on ACTIVE parents
--      where there is no matching active assessment_department row.
--   3) Keep assessment 4074 and cancel its conflicting assessment 4189.
--   4) Cancel the resulting empty ACTIVE parent assessments. They have no
--      active class and no saved response, so they cannot be continued.
-- This script does not delete responses, assessments, departments, or rounds.
-- Run the preview SELECT statements and verify their counts before COMMIT.

START TRANSACTION;

-- Release legacy claims that still say IN_PROGRESS even though their parent
-- assessment is no longer ACTIVE. They are stale locks, not active work.
UPDATE assessment_section_assignee AS asa
LEFT JOIN assessment_master AS assessment
  ON assessment.assessment_id = asa.assessment_id
SET asa.status = 'RELEASED',
    asa.released_on = COALESCE(asa.released_on, CURRENT_TIMESTAMP)
WHERE asa.status = 'IN_PROGRESS'
  AND (assessment.assessment_id IS NULL OR UPPER(assessment.status) <> 'ACTIVE');

DROP TEMPORARY TABLE IF EXISTS repair_prematurely_completed_assessments;
CREATE TEMPORARY TABLE repair_prematurely_completed_assessments AS
SELECT a.assessment_id
FROM assessment_master AS a
JOIN assessment_department AS ad
  ON ad.assessment_id = a.assessment_id
 AND ad.is_active = 1
WHERE UPPER(a.status) = 'COMPLETED'
GROUP BY a.assessment_id
HAVING SUM(UPPER(COALESCE(ad.status, '')) = 'COMPLETED') < COUNT(*);

-- Do not automatically reopen a record if one of its pending classes is
-- already claimed by another ACTIVE assessment. That conflict needs a human
-- decision about which assessment should continue.
DROP TEMPORARY TABLE IF EXISTS repair_conflicting_active_claims;
CREATE TEMPORARY TABLE repair_conflicting_active_claims AS
SELECT DISTINCT repair.assessment_id,
       claim.fac_id_fk,
       claim.dept_id,
       other_claim.assessment_id AS conflicting_assessment_id
FROM repair_prematurely_completed_assessments AS repair
JOIN assessment_section_assignee AS claim
  ON claim.assessment_id = repair.assessment_id
 AND claim.status = 'IN_PROGRESS'
JOIN assessment_section_assignee AS other_claim
  ON other_claim.fac_id_fk = claim.fac_id_fk
 AND other_claim.dept_id = claim.dept_id
 AND other_claim.status = 'IN_PROGRESS'
 AND other_claim.assessment_id <> claim.assessment_id
JOIN assessment_master AS other_assessment
  ON other_assessment.assessment_id = other_claim.assessment_id
 AND UPPER(other_assessment.status) = 'ACTIVE';

DROP TEMPORARY TABLE IF EXISTS repair_stale_active_workflow_rows;
CREATE TEMPORARY TABLE repair_stale_active_workflow_rows AS
SELECT ads.status_id, ads.assessment_id, ads.fac_id_fk, ads.dept_id
FROM assessment_department_status AS ads
JOIN assessment_master AS a
  ON a.assessment_id = ads.assessment_id
LEFT JOIN assessment_department AS ad
  ON ad.assessment_id = ads.assessment_id
 AND ad.fac_id_fk = ads.fac_id_fk
 AND ad.dept_id = ads.dept_id
 AND ad.is_active = 1
WHERE ads.is_active = 1
  AND UPPER(a.status) = 'ACTIVE'
  AND ad.assessment_id IS NULL;

DROP TEMPORARY TABLE IF EXISTS repair_empty_active_assessments;
CREATE TEMPORARY TABLE repair_empty_active_assessments AS
SELECT a.assessment_id
FROM assessment_master AS a
LEFT JOIN assessment_department AS ad
  ON ad.assessment_id = a.assessment_id
 AND ad.is_active = 1
LEFT JOIN assessment_response AS response
  ON response.assessment_id = a.assessment_id
WHERE UPPER(a.status) = 'ACTIVE'
GROUP BY a.assessment_id
HAVING COUNT(ad.assessment_id) = 0
   AND COUNT(response.response_id) = 0;

-- Resolution chosen by the data owner:
--   Keep assessment 4074 (facility 19225); cancel assessment 4189.
-- Assessment 4189 response history is retained, but its active class rows
-- and claims are released so it cannot block or distort current workflows.

UPDATE assessment_section_assignee
SET status = 'RELEASED',
    released_on = CURRENT_TIMESTAMP
WHERE assessment_id = 4189
  AND fac_id_fk = 19225
  AND status = 'IN_PROGRESS';

UPDATE assessment_department_status
SET is_active = 0,
    updated_on = CURRENT_TIMESTAMP
WHERE assessment_id = 4189
  AND fac_id_fk = 19225
  AND is_active = 1;

UPDATE assessment_department
SET is_active = 0,
    status = 'CANCELLED'
WHERE assessment_id = 4189
  AND fac_id_fk = 19225
  AND is_active = 1;

UPDATE assessment_master
SET status = 'CANCELLED',
    cancelled_on = COALESCE(cancelled_on, CURRENT_TIMESTAMP)
WHERE assessment_id = 4189
  AND fac_id_fk = 19225
  AND status <> 'COMPLETED';

-- Resolution chosen by the data owner:
--   Keep reassessment 3701 for facility 21686, Class 9 (dept_id 1).
--   Assessment 3656 has a valid completed Class 11 record, so retain it and
--   release only its empty, competing Class 9 claim.
UPDATE assessment_section_assignee
SET status = 'RELEASED',
    released_on = CURRENT_TIMESTAMP
WHERE assessment_id = 3656
  AND fac_id_fk = 21686
  AND dept_id = 1
  AND status = 'IN_PROGRESS';

UPDATE assessment_department_status
SET is_active = 0,
    updated_on = CURRENT_TIMESTAMP
WHERE assessment_id = 3656
  AND fac_id_fk = 21686
  AND dept_id = 1
  AND is_active = 1;

UPDATE assessment_department
SET is_active = 0,
    status = 'CANCELLED'
WHERE assessment_id = 3656
  AND fac_id_fk = 21686
  AND dept_id = 1
  AND is_active = 1;

-- The remaining active class in 3656 is already complete, so retain its
-- valid result by completing the parent rather than cancelling it.
UPDATE assessment_master AS a
SET a.status = 'COMPLETED',
    a.completed_on = COALESCE(a.completed_on, CURRENT_TIMESTAMP)
WHERE a.assessment_id = 3656
  AND a.fac_id_fk = 21686
  AND a.status = 'ACTIVE'
  AND EXISTS (
      SELECT 1
      FROM assessment_department ad
      WHERE ad.assessment_id = a.assessment_id
        AND ad.is_active = 1
  )
  AND NOT EXISTS (
      SELECT 1
      FROM assessment_department ad
      WHERE ad.assessment_id = a.assessment_id
        AND ad.is_active = 1
        AND UPPER(COALESCE(ad.status, '')) <> 'COMPLETED'
  );

-- Recheck the preview counts before COMMIT. After the first repair pass, the
-- current migrated database audit found 0 premature completions, 3 stale
-- active workflow rows, and 48 empty active assessments.
SELECT
    (SELECT COUNT(*) FROM repair_prematurely_completed_assessments) AS prematurely_completed_assessments,
    (SELECT COUNT(*) FROM repair_conflicting_active_claims) AS conflicting_active_claims,
    (SELECT COUNT(*) FROM repair_stale_active_workflow_rows) AS stale_active_workflow_rows,
    (SELECT COUNT(*) FROM repair_empty_active_assessments) AS empty_active_assessments;

SELECT a.assessment_id, a.assessment_name, a.fac_id_fk, a.status
FROM assessment_master AS a
JOIN repair_prematurely_completed_assessments AS repair
  ON repair.assessment_id = a.assessment_id
ORDER BY a.assessment_id DESC;

SELECT assessment_id, fac_id_fk, dept_id, conflicting_assessment_id
FROM repair_conflicting_active_claims
ORDER BY assessment_id, dept_id;

-- Reopen only parents with a genuinely pending active class.
UPDATE assessment_master AS a
JOIN repair_prematurely_completed_assessments AS repair
  ON repair.assessment_id = a.assessment_id
LEFT JOIN repair_conflicting_active_claims AS conflict
  ON conflict.assessment_id = a.assessment_id
LEFT JOIN assessment_master AS conflicting_assessment
  ON conflicting_assessment.assessment_id = conflict.conflicting_assessment_id
SET a.status = 'ACTIVE',
    a.completed_on = NULL
WHERE a.assessment_id <> 3656
  AND (
      conflict.assessment_id IS NULL
      OR UPPER(COALESCE(conflicting_assessment.status, '')) <> 'ACTIVE'
  );

-- Prevent stale workflow records from presenting unavailable classes as active.
UPDATE assessment_department_status AS ads
JOIN repair_stale_active_workflow_rows AS repair
  ON repair.status_id = ads.status_id
SET ads.is_active = 0,
    ads.updated_on = CURRENT_TIMESTAMP;

-- Release matching stale assessor class claims, without deleting history.
UPDATE assessment_section_assignee AS asa
JOIN repair_stale_active_workflow_rows AS repair
  ON repair.assessment_id = asa.assessment_id
 AND repair.fac_id_fk = asa.fac_id_fk
 AND repair.dept_id = asa.dept_id
SET asa.status = 'RELEASED',
    asa.released_on = CURRENT_TIMESTAMP
WHERE asa.status = 'IN_PROGRESS';

-- These active parents contain no class record or response. Cancel them so
-- they do not remain in state-monitoring counts as unusable active work.
UPDATE assessment_master AS a
JOIN repair_empty_active_assessments AS repair
  ON repair.assessment_id = a.assessment_id
SET a.status = 'CANCELLED',
    a.cancelled_on = COALESCE(a.cancelled_on, CURRENT_TIMESTAMP)
WHERE a.status = 'ACTIVE';

DROP TEMPORARY TABLE repair_empty_active_assessments;
DROP TEMPORARY TABLE repair_stale_active_workflow_rows;
DROP TEMPORARY TABLE repair_conflicting_active_claims;
DROP TEMPORARY TABLE repair_prematurely_completed_assessments;

COMMIT;

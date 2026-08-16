-- SaQshi production-data repair
-- Fix completed assessment history that shows extra classes/checklist rows
-- because assessment_department_status retained a stale active class.
--
-- This does not remove assessments, responses, or real active classes. It
-- deactivates only an active status row that has no matching active workflow
-- row in assessment_department on a COMPLETED parent assessment.

START TRANSACTION;

-- Review these rows before committing in production.
SELECT ads.status_id, ads.assessment_id, ads.fac_id_fk, ads.dept_id
FROM assessment_department_status AS ads
JOIN assessment_master AS assessment
  ON assessment.assessment_id = ads.assessment_id
LEFT JOIN assessment_department AS department
  ON department.assessment_id = ads.assessment_id
 AND department.fac_id_fk = ads.fac_id_fk
 AND department.dept_id = ads.dept_id
 AND department.is_active = 1
WHERE ads.is_active = 1
  AND UPPER(assessment.status) = 'COMPLETED'
  AND department.assessment_id IS NULL
ORDER BY ads.assessment_id, ads.dept_id;

UPDATE assessment_department_status AS ads
JOIN assessment_master AS assessment
  ON assessment.assessment_id = ads.assessment_id
LEFT JOIN assessment_department AS department
  ON department.assessment_id = ads.assessment_id
 AND department.fac_id_fk = ads.fac_id_fk
 AND department.dept_id = ads.dept_id
 AND department.is_active = 1
SET ads.is_active = 0,
    ads.updated_on = CURRENT_TIMESTAMP
WHERE ads.is_active = 1
  AND UPPER(assessment.status) = 'COMPLETED'
  AND department.assessment_id IS NULL;

-- Verify the completed history is now based only on its actual active class.
SELECT assessment.assessment_id,
       assessment.status,
       COUNT(DISTINCT department.dept_id) AS active_classes,
       COUNT(DISTINCT response.response_id) AS saved_checkpoints
FROM assessment_master AS assessment
LEFT JOIN assessment_department AS department
  ON department.assessment_id = assessment.assessment_id
 AND department.is_active = 1
LEFT JOIN assessment_response AS response
  ON response.assessment_id = assessment.assessment_id
WHERE UPPER(assessment.status) = 'COMPLETED'
GROUP BY assessment.assessment_id, assessment.status
ORDER BY assessment.assessment_id DESC;

COMMIT;

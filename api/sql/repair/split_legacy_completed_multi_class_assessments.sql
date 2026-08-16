-- SaQshi Open Source
-- Repairs legacy COMPLETED assessments that contain more than one completed
-- class. Each earlier class is moved to its own COMPLETED assessment ID; the
-- most recently claimed class remains on the original assessment ID.
--
-- Take a database backup and run this once. Review the first SELECT result
-- before executing the CALL statement.

DROP TEMPORARY TABLE IF EXISTS sq_split_completed_class_targets;
CREATE TEMPORARY TABLE sq_split_completed_class_targets AS
SELECT current_claim.assessment_id AS source_assessment_id,
       current_claim.dept_id
FROM assessment_section_assignee AS current_claim
JOIN assessment_master AS assessment
  ON assessment.assessment_id = current_claim.assessment_id
WHERE assessment.status = 'COMPLETED'
  AND current_claim.status = 'COMPLETED'
  AND EXISTS (
      SELECT 1
      FROM assessment_section_assignee AS later_claim
      WHERE later_claim.assessment_id = current_claim.assessment_id
        AND later_claim.status = 'COMPLETED'
        AND (
            later_claim.assigned_on > current_claim.assigned_on
            OR (later_claim.assigned_on = current_claim.assigned_on
                AND later_claim.assignment_id > current_claim.assignment_id)
        )
  );

-- Preview: every row below will become a separate historical assessment.
SELECT * FROM sq_split_completed_class_targets
ORDER BY source_assessment_id, dept_id;

DELIMITER $$
DROP PROCEDURE IF EXISTS sq_split_completed_multi_class_assessments$$
CREATE PROCEDURE sq_split_completed_multi_class_assessments()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE source_id BIGINT;
    DECLARE source_dept_id INT;
    DECLARE new_id BIGINT;
    DECLARE split_cursor CURSOR FOR
        SELECT source_assessment_id, dept_id
        FROM sq_split_completed_class_targets
        ORDER BY source_assessment_id, dept_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN split_cursor;
    split_loop: LOOP
        FETCH split_cursor INTO source_id, source_dept_id;
        IF done = 1 THEN LEAVE split_loop; END IF;

        INSERT INTO assessment_master (
            assessment_name, framework_code, fac_id_fk, start_date, end_date,
            completed_on, cancelled_on, status, remarks, assigned_assessor_id,
            assessment_source, created_by, updated_by, created_on, updated_on, round_id
        )
        SELECT
            CONCAT(LEFT(assessment_name, 220), ' (legacy class ', source_dept_id, ')'),
            framework_code, fac_id_fk, start_date, end_date,
            completed_on, NULL, 'COMPLETED', remarks, assigned_assessor_id,
            assessment_source, created_by, updated_by, created_on, updated_on, round_id
        FROM assessment_master
        WHERE assessment_id = source_id;

        SET new_id = LAST_INSERT_ID();

        UPDATE assessment_section_assignee
        SET assessment_id = new_id
        WHERE assessment_id = source_id AND dept_id = source_dept_id;

        UPDATE assessment_department_status
        SET assessment_id = new_id
        WHERE assessment_id = source_id AND dept_id = source_dept_id;

        UPDATE assessment_department
        SET assessment_id = new_id
        WHERE assessment_id = source_id AND dept_id = source_dept_id;

        UPDATE assessment_assessor_info
        SET assessment_id = new_id
        WHERE assessment_id = source_id AND dept_id = source_dept_id;

        UPDATE assessment_response
        SET assessment_id = new_id
        WHERE assessment_id = source_id AND dept_id = source_dept_id;

        UPDATE assessment_response_field_index
        SET assessment_id = new_id
        WHERE assessment_id = source_id AND dept_id = source_dept_id;

        UPDATE assessment_response_evidence
        SET assessment_id = new_id
        WHERE assessment_id = source_id AND dept_id = source_dept_id;

        UPDATE assessment_action_plan
        SET assessment_id = new_id
        WHERE assessment_id = source_id AND dept_id = source_dept_id;
    END LOOP;
    CLOSE split_cursor;
END$$
DELIMITER ;

START TRANSACTION;
CALL sq_split_completed_multi_class_assessments();

-- Validation: no COMPLETED assessment should still contain multiple classes.
SELECT asa.assessment_id, COUNT(DISTINCT asa.dept_id) AS completed_classes
FROM assessment_section_assignee AS asa
JOIN assessment_master AS assessment ON assessment.assessment_id = asa.assessment_id
WHERE assessment.status = 'COMPLETED' AND asa.status = 'COMPLETED'
GROUP BY asa.assessment_id
HAVING COUNT(DISTINCT asa.dept_id) > 1;

COMMIT;
DROP PROCEDURE sq_split_completed_multi_class_assessments;

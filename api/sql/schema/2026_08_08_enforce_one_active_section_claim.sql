-- Enforce one IN_PROGRESS assessment claim per facility/class (MySQL 8+).
-- Run only AFTER the data-repair script has completed and the 4074/4189
-- duplicate claim has been manually resolved; otherwise the unique index
-- intentionally refuses to be created.

-- Legacy data can leave an IN_PROGRESS claim on a parent that is already
-- COMPLETED or CANCELLED. Such a claim is stale and must not block a valid
-- active assessment. This is safe: it changes no assessment or response data.
UPDATE assessment_section_assignee AS asa
LEFT JOIN assessment_master AS assessment
  ON assessment.assessment_id = asa.assessment_id
SET asa.status = 'RELEASED',
    asa.released_on = COALESCE(asa.released_on, CURRENT_TIMESTAMP)
WHERE asa.status = 'IN_PROGRESS'
  AND (assessment.assessment_id IS NULL OR UPPER(assessment.status) <> 'ACTIVE');

DROP PROCEDURE IF EXISTS enforce_one_active_section_claim;
DELIMITER $$
CREATE PROCEDURE enforce_one_active_section_claim()
BEGIN
    DECLARE has_active_claim_key INT DEFAULT 0;
    DECLARE has_unique_index INT DEFAULT 0;

    SELECT COUNT(*) INTO has_active_claim_key
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'assessment_section_assignee'
      AND COLUMN_NAME = 'active_claim_key';

    IF has_active_claim_key = 0 THEN
        ALTER TABLE assessment_section_assignee
            ADD COLUMN active_claim_key TINYINT
            GENERATED ALWAYS AS (
                CASE WHEN UPPER(status) = 'IN_PROGRESS' THEN 1 ELSE NULL END
            ) STORED;
    END IF;

    SELECT COUNT(*) INTO has_unique_index
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'assessment_section_assignee'
      AND INDEX_NAME = 'uq_active_facility_section_claim';

    IF has_unique_index = 0 THEN
        ALTER TABLE assessment_section_assignee
            ADD UNIQUE KEY uq_active_facility_section_claim
                (fac_id_fk, dept_id, active_claim_key);
    END IF;
END$$
DELIMITER ;

CALL enforce_one_active_section_claim();
DROP PROCEDURE enforce_one_active_section_claim;

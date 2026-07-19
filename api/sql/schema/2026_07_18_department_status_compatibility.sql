-- ==========================================================
-- SaQshi department status compatibility migration
-- File: api/sql/schema/2026_07_18_department_status_compatibility.sql
-- Updated: 2026-07-18
--
-- Purpose:
--   Keep assessment_department_status compatible with both legacy/current
--   service code using ass_period_id and newer code paths using assessment_id.
-- ==========================================================

SET @has_ass_period_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'assessment_department_status'
      AND COLUMN_NAME = 'ass_period_id'
);

SET @sql := IF(
    @has_ass_period_id = 0,
    'ALTER TABLE assessment_department_status ADD COLUMN ass_period_id BIGINT NULL AFTER status_id',
    'SELECT ''ass_period_id already exists'' AS migration_note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_assessment_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'assessment_department_status'
      AND COLUMN_NAME = 'assessment_id'
);

SET @sql := IF(
    @has_assessment_id = 0,
    'ALTER TABLE assessment_department_status ADD COLUMN assessment_id BIGINT NULL AFTER ass_period_id',
    'ALTER TABLE assessment_department_status MODIFY assessment_id BIGINT NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE assessment_department_status
SET ass_period_id = assessment_id
WHERE ass_period_id IS NULL
  AND assessment_id IS NOT NULL;

UPDATE assessment_department_status
SET assessment_id = ass_period_id
WHERE assessment_id IS NULL
  AND ass_period_id IS NOT NULL;

ALTER TABLE assessment_department_status
    MODIFY ass_period_id BIGINT NULL;

SET @has_ass_period_index := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'assessment_department_status'
      AND INDEX_NAME = 'idx_dept_status_ass_period'
);

SET @sql := IF(
    @has_ass_period_index = 0,
    'ALTER TABLE assessment_department_status ADD INDEX idx_dept_status_ass_period (ass_period_id)',
    'SELECT ''idx_dept_status_ass_period already exists'' AS migration_note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TRIGGER IF EXISTS trg_dept_status_bi_sync_ids;
DROP TRIGGER IF EXISTS trg_dept_status_bu_sync_ids;

DELIMITER $$

CREATE TRIGGER trg_dept_status_bi_sync_ids
BEFORE INSERT ON assessment_department_status
FOR EACH ROW
BEGIN
    IF NEW.ass_period_id IS NULL AND NEW.assessment_id IS NOT NULL THEN
        SET NEW.ass_period_id = NEW.assessment_id;
    END IF;

    IF NEW.assessment_id IS NULL AND NEW.ass_period_id IS NOT NULL THEN
        SET NEW.assessment_id = NEW.ass_period_id;
    END IF;
END$$

CREATE TRIGGER trg_dept_status_bu_sync_ids
BEFORE UPDATE ON assessment_department_status
FOR EACH ROW
BEGIN
    IF NEW.ass_period_id IS NULL AND NEW.assessment_id IS NOT NULL THEN
        SET NEW.ass_period_id = NEW.assessment_id;
    END IF;

    IF NEW.assessment_id IS NULL AND NEW.ass_period_id IS NOT NULL THEN
        SET NEW.assessment_id = NEW.ass_period_id;
    END IF;
END$$

DELIMITER ;

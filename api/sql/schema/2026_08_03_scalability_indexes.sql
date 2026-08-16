-- SaQshi scalability indexes
-- Run once on existing deployments. Each index creation is idempotent.

-- Fast active/latest assessment lookup for facility and assessor workflows.
SET @index_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_master' AND INDEX_NAME = 'idx_assessment_facility_status_recent');
SET @sql := IF(@index_exists = 0, 'ALTER TABLE assessment_master ADD INDEX idx_assessment_facility_status_recent (fac_id_fk, status, assessment_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Department progress, completion and report queries use this scope repeatedly.
SET @index_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_department' AND INDEX_NAME = 'idx_assessment_department_scope_status');
SET @sql := IF(@index_exists = 0, 'ALTER TABLE assessment_department ADD INDEX idx_assessment_department_scope_status (assessment_id, fac_id_fk, is_active, status, dept_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Supports active-department joins for the current assessment.
SET @index_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_department_status' AND INDEX_NAME = 'idx_dept_status_scope_active');
SET @sql := IF(@index_exists = 0, 'ALTER TABLE assessment_department_status ADD INDEX idx_dept_status_scope_active (fac_id_fk, assessment_id, is_active, dept_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Used by analytics/reporting joins that group response rows by assessment.
SET @index_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_response' AND INDEX_NAME = 'idx_response_assessment_checkpoint');
SET @sql := IF(@index_exists = 0, 'ALTER TABLE assessment_response ADD INDEX idx_response_assessment_checkpoint (assessment_id, checkpoint_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

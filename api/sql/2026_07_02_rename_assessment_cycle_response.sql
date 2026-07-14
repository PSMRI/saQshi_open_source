-- SaQshi assessment response naming cleanup
-- Purpose:
--   The active assessment flow now uses assessment_master.assessment_id as the
--   only assessment unit. There is no separate cycle entity in the new flow.
--
-- Before running:
--   Take a database backup.
--
-- Main rename:
--   assessment_cycle_response.cycle_id -> assessment_response.assessment_id

RENAME TABLE assessment_cycle_response TO assessment_response;

ALTER TABLE assessment_response
    CHANGE COLUMN cycle_id assessment_id BIGINT NOT NULL;

-- Recreate the uniqueness/indexing intent with assessment naming.
-- Drop legacy index names manually first if your database uses different names.
ALTER TABLE assessment_response
    ADD INDEX idx_assessment_response_assessment (assessment_id),
    ADD INDEX idx_assessment_response_dept (dept_id),
    ADD INDEX idx_assessment_response_checkpoint (checkpoint_id),
    ADD UNIQUE KEY uq_assessment_response_scope (assessment_id, dept_id, checkpoint_id);

-- Optional temporary compatibility view for old/test endpoints still using
-- assessment_cycle_response + cycle_id naming during the transition.
CREATE OR REPLACE VIEW assessment_cycle_response AS
SELECT
    response_id,
    assessment_id AS cycle_id,
    dept_id,
    checkpoint_id,
    response_value,
    score,
    remarks,
    evidence_url,
    updated_by,
    updated_on
FROM assessment_response;

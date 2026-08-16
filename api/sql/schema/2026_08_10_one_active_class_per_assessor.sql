-- SaQshi Open Source
-- Enforce one IN_PROGRESS class per assessor per school.
--
-- Prerequisite: run ../repair/release_duplicate_assessor_class_claims.sql
-- first. This migration will fail while duplicate active claims exist.

ALTER TABLE assessment_section_assignee
    ADD COLUMN active_assessor_claim_key TINYINT
        GENERATED ALWAYS AS (
            CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE NULL END
        ) STORED,
    ADD UNIQUE KEY uq_active_facility_assessor_claim
        (fac_id_fk, assessor_id, active_assessor_claim_key);

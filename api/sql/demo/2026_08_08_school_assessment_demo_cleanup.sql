-- SaQshi demo school-assessment cleanup (MySQL 8+)
-- Removes only records created by 2026_08_06_school_assessment_demo_seed.sql.
-- Run in a development/test database. Review the SELECT output before COMMIT.

START TRANSACTION;

-- Preserve the exact demo assessment and round IDs before deleting the assessments.
DROP TEMPORARY TABLE IF EXISTS demo_cleanup_assessments;
CREATE TEMPORARY TABLE demo_cleanup_assessments (
    assessment_id BIGINT NOT NULL PRIMARY KEY,
    round_id BIGINT NULL
) AS
SELECT assessment_id, round_id
FROM assessment_master
WHERE assessment_name LIKE 'DEMO %'
  AND framework_code = 'saqshi-education';

DROP TEMPORARY TABLE IF EXISTS demo_cleanup_rounds;
CREATE TEMPORARY TABLE demo_cleanup_rounds (
    round_id BIGINT NOT NULL PRIMARY KEY
) AS
SELECT DISTINCT round_id
FROM demo_cleanup_assessments
WHERE round_id IS NOT NULL;

-- Review exactly what will be removed. Use ROLLBACK instead of COMMIT if incorrect.
SELECT
    (SELECT COUNT(*) FROM demo_cleanup_assessments) AS demo_assessments,
    (SELECT COUNT(*) FROM demo_cleanup_rounds) AS demo_rounds,
    (SELECT COUNT(*) FROM assessor_master WHERE assessor_code LIKE 'DEMO_%') AS demo_assessors,
    (SELECT COUNT(*) FROM s_user WHERE u_name LIKE 'demo_assessor_%') AS demo_users;

-- assessment_master foreign-key cascades remove assessment_response,
-- assessment_department, assessment_department_status, evidence, and action plans.
-- The library has no foreign key, so remove its demo-derived rows explicitly.
DELETE library
FROM assessment_action_plan_library AS library
JOIN demo_cleanup_assessments AS demo
  ON demo.assessment_id = library.source_assessment_id;

DELETE assessment
FROM assessment_master AS assessment
JOIN demo_cleanup_assessments AS demo
  ON demo.assessment_id = assessment.assessment_id;

-- These rounds were created by the demo seed and are not automatically cascaded.
DELETE assessment_round
FROM facility_assessment_round AS assessment_round
JOIN demo_cleanup_rounds AS demo
  ON demo.round_id = assessment_round.round_id;

-- Remove mappings before assessor records; mapping has an assessor foreign key.
DELETE mapping
FROM assessor_facility_mapping AS mapping
JOIN assessor_master AS assessor
  ON assessor.assessor_id = mapping.assessor_id
WHERE assessor.assessor_code LIKE 'DEMO_%';

DELETE FROM assessor_master
WHERE assessor_code LIKE 'DEMO_%';

DELETE FROM s_user
WHERE u_name LIKE 'demo_assessor_%';

DROP TEMPORARY TABLE demo_cleanup_rounds;
DROP TEMPORARY TABLE demo_cleanup_assessments;

-- Replace COMMIT with ROLLBACK for a dry run.
COMMIT;

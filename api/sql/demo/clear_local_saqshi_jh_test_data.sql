-- SaQshi local test-data reset (MySQL 8+)
--
-- Target database: saqshi_jh
-- Clears application test records, including users and facility master rows.
-- Preserves the schema, schema_migrations, and the built-in u_role records.
--
-- IMPORTANT: Run only against the local development database. This is destructive.
-- Uploaded files on disk are not removed by SQL; remove them separately if required.

USE `saqshi_jh`;

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Certification and operational history
DELETE FROM `certification_history`;
DELETE FROM `cert_details`;
DELETE FROM `background_jobs`;
DELETE FROM `login_attempts`;
DELETE FROM `ai_chat_messages`;
DELETE FROM `uploaded_files`;
DELETE FROM `resources`;

-- Assessment workflow data
DELETE FROM `assessment_action_plan_library`;
DELETE FROM `assessment_action_plan`;
DELETE FROM `assessment_section_assignee`;
DELETE FROM `assessment_response_evidence`;
DELETE FROM `assessment_response_field_index`;
DELETE FROM `assessment_response`;
DELETE FROM `assessment_assessor_info`;
DELETE FROM `assessment_department_status`;
DELETE FROM `assessment_department`;
DELETE FROM `assessment_cycle_department`;
DELETE FROM `assessment_cycle`;
DELETE FROM `assessment_master`;
DELETE FROM `facility_assessment_round`;

-- Assessor, performance, user, and facility data
DELETE FROM `assessor_facility_profile_update`;
DELETE FROM `assessor_facility_mapping`;
DELETE FROM `assessor_master`;
DELETE FROM `performance_entries`;
DELETE FROM `s_user`;
DELETE FROM `facilities`;
DELETE FROM `facilities_type`;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- Reset generated identifiers for a clean local test run.
ALTER TABLE `certification_history` AUTO_INCREMENT = 1;
ALTER TABLE `cert_details` AUTO_INCREMENT = 1;
ALTER TABLE `background_jobs` AUTO_INCREMENT = 1;
ALTER TABLE `login_attempts` AUTO_INCREMENT = 1;
ALTER TABLE `ai_chat_messages` AUTO_INCREMENT = 1;
ALTER TABLE `uploaded_files` AUTO_INCREMENT = 1;
ALTER TABLE `resources` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_action_plan_library` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_action_plan` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_section_assignee` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_response_evidence` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_response_field_index` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_response` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_assessor_info` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_department_status` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_department` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_cycle_department` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_cycle` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_master` AUTO_INCREMENT = 1;
ALTER TABLE `facility_assessment_round` AUTO_INCREMENT = 1;
ALTER TABLE `assessor_facility_mapping` AUTO_INCREMENT = 1;
ALTER TABLE `assessor_master` AUTO_INCREMENT = 1;
ALTER TABLE `performance_entries` AUTO_INCREMENT = 1;
ALTER TABLE `s_user` AUTO_INCREMENT = 1;
ALTER TABLE `facilities` AUTO_INCREMENT = 1;
ALTER TABLE `facilities_type` AUTO_INCREMENT = 1;

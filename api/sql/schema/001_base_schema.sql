-- ==========================================================
-- SaQshi sanitized base database schema
-- File: api/sql/schema/001_base_schema.sql
-- Version: 1.0.0
-- Updated: 2026-07-18
--
-- Purpose:
--   Create the database and core tables required for a fresh SaQshi
--   installation. This file contains schema and safe role seed values only.
--
-- Important:
--   - Do not add production data to this file.
--   - Replace database name/user/password in deployment scripts as needed.
--   - Master data such as facilities/framework JSON should be imported from
--     approved sanitized/configuration sources separately.
-- ==========================================================

CREATE DATABASE IF NOT EXISTS saqshi
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE saqshi;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- Schema migration tracking
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_id VARCHAR(150) NOT NULL,
    applied_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    applied_by VARCHAR(100) NULL,
    PRIMARY KEY (migration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Roles and users
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS u_role (
    role_id INT NOT NULL,
    role_name VARCHAR(80) NOT NULL,
    role_status TINYINT NOT NULL DEFAULT 1,
    role_description VARCHAR(255) NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id),
    UNIQUE KEY uq_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO u_role (role_id, role_name, role_status, role_description)
VALUES
    (1, 'Facility User', 1, 'Facility-level user'),
    (4, 'District User', 1, 'District monitoring user'),
    (5, 'Division User', 1, 'Division/regional monitoring user'),
    (8, 'Block User', 1, 'Block monitoring user'),
    (9, 'State Admin', 1, 'State monitoring and administration user'),
    (10, 'Assessor', 1, 'External/state assigned assessor')
ON DUPLICATE KEY UPDATE
    role_name = VALUES(role_name),
    role_status = VALUES(role_status),
    role_description = VALUES(role_description);

CREATE TABLE IF NOT EXISTS s_user (
    u_id INT NOT NULL AUTO_INCREMENT,
    u_name VARCHAR(120) NOT NULL,
    u_password VARCHAR(255) NOT NULL,
    fac_id_fk INT NULL,
    role_id_fk INT NOT NULL DEFAULT 1,
    is_active TINYINT NOT NULL DEFAULT 1,
    dept_id INT NULL,
    f_name TEXT NULL,
    m_name TEXT NULL,
    l_name TEXT NULL,
    mob_no TEXT NULL,
    mail_id TEXT NULL,
    user_type VARCHAR(30) NULL,
    assessment_id BIGINT NULL,
    state_id INT NULL,
    division_id INT NULL,
    dist_id INT NULL,
    block_id INT NULL,
    password_must_change TINYINT NOT NULL DEFAULT 0,
    password_changed_on TIMESTAMP NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (u_id),
    UNIQUE KEY uq_s_user_name (u_name),
    KEY idx_s_user_facility (fac_id_fk),
    KEY idx_s_user_role (role_id_fk),
    KEY idx_s_user_scope (state_id, division_id, dist_id, block_id),
    KEY idx_s_user_active (is_active),
    CONSTRAINT fk_s_user_role
        FOREIGN KEY (role_id_fk) REFERENCES u_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id BIGINT NOT NULL AUTO_INCREMENT,
    username VARCHAR(120) NOT NULL,
    ip_address VARCHAR(80) NULL,
    attempt_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL,
    PRIMARY KEY (attempt_id),
    KEY idx_login_attempt_user_time (username, attempt_time),
    KEY idx_login_attempt_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Facility master data
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS facilities_type (
    fac_type_id INT NOT NULL AUTO_INCREMENT,
    fac_type_name VARCHAR(120) NOT NULL,
    fac_type_code VARCHAR(50) NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (fac_type_id),
    UNIQUE KEY uq_fac_type_name (fac_type_name),
    KEY idx_fac_type_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facilities (
    fac_id INT NOT NULL AUTO_INCREMENT,
    state_name VARCHAR(80) NULL,
    Dist_Name VARCHAR(80) NULL,
    Block_Name VARCHAR(80) NULL,
    fac_name VARCHAR(120) NULL,
    Health_facilty_type INT NULL,
    block_id INT NOT NULL DEFAULT 0,
    dist_id INT NULL,
    state_code INT NULL,
    division_id INT NULL,
    division VARCHAR(80) NULL,
    NIN_no BIGINT NULL,
    lat DECIMAL(11,8) NULL,
    longit DECIMAL(11,8) NULL,
    is_active TINYINT NULL DEFAULT 1,
    state_id INT NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (fac_id),
    UNIQUE KEY uq_facilities_nin (NIN_no),
    KEY idx_facilities_scope (state_id, division_id, dist_id, block_id),
    KEY idx_facilities_type (Health_facilty_type),
    KEY idx_facilities_active (is_active),
    KEY idx_facilities_name (fac_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Assessment core
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS assessment_master (
    assessment_id BIGINT NOT NULL AUTO_INCREMENT,
    assessment_name VARCHAR(255) NOT NULL,
    framework_code VARCHAR(100) NOT NULL,
    fac_id_fk INT NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    completed_on DATETIME NULL,
    cancelled_on DATETIME NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    remarks TEXT NULL,
    assigned_assessor_id BIGINT NULL,
    assessment_source VARCHAR(30) NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (assessment_id),
    KEY idx_assessment_facility (fac_id_fk),
    KEY idx_assessment_status (status),
    KEY idx_assessment_framework (framework_code),
    KEY idx_assessment_dates (start_date, end_date),
    KEY idx_assessment_assessor (assigned_assessor_id),
    CONSTRAINT fk_assessment_facility
        FOREIGN KEY (fac_id_fk) REFERENCES facilities (fac_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_department_status (
    status_id BIGINT NOT NULL AUTO_INCREMENT,
    ass_period_id BIGINT NULL,
    assessment_id BIGINT NULL,
    fac_id_fk INT NOT NULL,
    dept_id INT NOT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    activated_by INT NULL,
    activated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (status_id),
    UNIQUE KEY uq_assessment_dept_status (ass_period_id, dept_id),
    KEY idx_dept_status_facility (fac_id_fk),
    KEY idx_dept_status_active (is_active, status),
    KEY idx_dept_status_assessment_id (assessment_id),
    CONSTRAINT fk_dept_status_assessment
        FOREIGN KEY (ass_period_id) REFERENCES assessment_master (assessment_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS assessment_department (
    assessment_dept_id BIGINT NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT NOT NULL,
    fac_id_fk INT NOT NULL,
    dept_id INT NOT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'IN_PROGRESS',
    started_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    completed_on TIMESTAMP NULL,
    current_checkpoint_id INT NULL,
    total_checkpoints INT NOT NULL DEFAULT 0,
    completed_checkpoints INT NOT NULL DEFAULT 0,
    activated_by INT NULL,
    updated_by INT NULL,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (assessment_dept_id),
    UNIQUE KEY uq_assessment_department (assessment_id, dept_id),
    KEY idx_assessment_department_facility (fac_id_fk),
    KEY idx_assessment_department_status (status, is_active),
    CONSTRAINT fk_assessment_department_master
        FOREIGN KEY (assessment_id) REFERENCES assessment_master (assessment_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_assessor_info (
    info_id BIGINT NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT NOT NULL,
    fac_id_fk INT NOT NULL,
    dept_id INT NOT NULL,
    assessment_date DATE NOT NULL,
    assessment_type VARCHAR(50) NOT NULL,
    assessor_name TEXT NULL,
    assessor_designation VARCHAR(120) NULL,
    assessor_mobile TEXT NULL,
    assessor_email TEXT NULL,
    assessee_name TEXT NULL,
    assessee_designation VARCHAR(120) NULL,
    assessee_mobile TEXT NULL,
    assessee_email TEXT NULL,
    remarks TEXT NULL,
    saved_by INT NULL,
    saved_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (info_id),
    UNIQUE KEY uq_assessor_info_scope (assessment_id, dept_id),
    KEY idx_assessor_info_facility (fac_id_fk),
    CONSTRAINT fk_assessor_info_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessment_master (assessment_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_response (
    response_id BIGINT NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT NOT NULL,
    dept_id INT NOT NULL,
    checkpoint_id INT NOT NULL,
    response_value TEXT NULL,
    response_type VARCHAR(50) NULL,
    response_json LONGTEXT NULL,
    score DECIMAL(10,2) NULL DEFAULT 0.00,
    max_score DECIMAL(10,2) NULL DEFAULT 2.00,
    score_status VARCHAR(30) NULL DEFAULT 'SCORED',
    remarks TEXT NULL,
    evidence_url VARCHAR(500) NULL,
    updated_by INT NULL,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (response_id),
    UNIQUE KEY uq_assessment_response (assessment_id, dept_id, checkpoint_id),
    KEY idx_response_assessment_dept (assessment_id, dept_id),
    KEY idx_response_checkpoint (checkpoint_id),
    KEY idx_response_score (score, max_score, score_status),
    CONSTRAINT fk_response_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessment_master (assessment_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_response_field_index (
    id BIGINT NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT NOT NULL,
    dept_id INT NOT NULL,
    checkpoint_id INT NOT NULL,
    field_key VARCHAR(120) NOT NULL,
    field_label VARCHAR(255) NULL,
    field_type VARCHAR(50) NULL,
    value_text LONGTEXT NULL,
    value_number DECIMAL(18,4) NULL,
    value_date DATE NULL,
    value_bool TINYINT NULL,
    updated_by INT NULL,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assessment_response_field (assessment_id, dept_id, checkpoint_id, field_key),
    KEY idx_response_field_key (field_key),
    KEY idx_response_field_number (field_key, value_number),
    KEY idx_response_field_bool (field_key, value_bool),
    CONSTRAINT fk_response_field_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessment_master (assessment_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_response_evidence (
    evidence_id BIGINT NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT NOT NULL,
    dept_id INT NOT NULL,
    checkpoint_id INT NOT NULL,
    field_key VARCHAR(120) NULL,
    file_url VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NULL,
    file_type VARCHAR(120) NULL,
    uploaded_by INT NULL,
    uploaded_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (evidence_id),
    KEY idx_response_evidence (assessment_id, dept_id, checkpoint_id),
    CONSTRAINT fk_response_evidence_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessment_master (assessment_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Legacy compatibility view for older report code paths.
DROP VIEW IF EXISTS assessment_cycle_response;
CREATE VIEW assessment_cycle_response AS
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

-- Legacy cycle tables retained for compatibility with older/dynamic service code.
CREATE TABLE IF NOT EXISTS assessment_cycle (
    cycle_id BIGINT NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT NULL,
    fac_id INT NOT NULL,
    framework_code VARCHAR(100) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    created_by INT NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cycle_id),
    KEY idx_cycle_assessment (assessment_id),
    KEY idx_cycle_facility (fac_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_cycle_department (
    cycle_dept_id BIGINT NOT NULL AUTO_INCREMENT,
    cycle_id BIGINT NOT NULL,
    dept_id INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    current_checkpoint_id INT NULL,
    total_checkpoints INT NOT NULL DEFAULT 0,
    completed_checkpoints INT NOT NULL DEFAULT 0,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cycle_dept_id),
    UNIQUE KEY uq_cycle_department (cycle_id, dept_id),
    KEY idx_cycle_department_status (status),
    CONSTRAINT fk_cycle_department_cycle
        FOREIGN KEY (cycle_id) REFERENCES assessment_cycle (cycle_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- CQI action plan and closure
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS assessment_action_plan (
    action_plan_id BIGINT NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT NOT NULL,
    dept_id INT NOT NULL,
    checkpoint_id INT NOT NULL,
    system_action_plan TEXT NULL,
    user_action_plan TEXT NULL,
    achievability VARCHAR(80) NULL,
    responsible_person VARCHAR(255) NULL,
    priority VARCHAR(30) NULL,
    target_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    closure_remarks TEXT NULL,
    revised_score DECIMAL(10,2) NULL,
    closure_evidence_url VARCHAR(500) NULL,
    closed_by INT NULL,
    closed_on TIMESTAMP NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (action_plan_id),
    UNIQUE KEY uq_action_plan_scope (assessment_id, dept_id, checkpoint_id),
    KEY idx_action_plan_status (status),
    KEY idx_action_plan_target (target_date),
    KEY idx_action_plan_checkpoint (checkpoint_id),
    CONSTRAINT fk_action_plan_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessment_master (assessment_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_action_plan_library (
    id BIGINT NOT NULL AUTO_INCREMENT,
    checkpoint_id INT NOT NULL,
    framework_code VARCHAR(100) NULL,
    fac_id INT NOT NULL,
    fac_name VARCHAR(255) NULL,
    source_assessment_id BIGINT NOT NULL,
    source_dept_id INT NOT NULL,
    user_action_plan TEXT NOT NULL,
    created_by INT NULL,
    created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_checkpoint (checkpoint_id),
    KEY idx_fac_checkpoint (fac_id, checkpoint_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Performance monitoring
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS performance_entries (
    entry_id BIGINT NOT NULL AUTO_INCREMENT,
    fac_id INT NOT NULL,
    dept_id INT NOT NULL DEFAULT 0,
    indicator_type VARCHAR(20) NOT NULL,
    indicator_id INT NOT NULL,
    indicator_code VARCHAR(80) NULL,
    indicator_name VARCHAR(500) NULL,
    entry_month TINYINT NOT NULL,
    entry_year SMALLINT NOT NULL,
    numerator_value DECIMAL(14,4) NOT NULL DEFAULT 0,
    denominator_value DECIMAL(14,4) NOT NULL DEFAULT 0,
    result_value DECIMAL(14,4) NULL,
    formula_id INT NULL,
    remarks TEXT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (entry_id),
    UNIQUE KEY uq_performance_entry (fac_id, dept_id, indicator_type, indicator_id, entry_month, entry_year),
    KEY idx_performance_fac_period (fac_id, entry_year, entry_month),
    KEY idx_performance_indicator (indicator_type, indicator_id),
    CONSTRAINT fk_performance_facility
        FOREIGN KEY (fac_id) REFERENCES facilities (fac_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Certification
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS cert_details (
    id BIGINT NOT NULL AUTO_INCREMENT,
    dist VARCHAR(150) NULL,
    block VARCHAR(150) NULL,
    fac_name VARCHAR(255) NULL,
    fac_type VARCHAR(100) NULL,
    cert_type VARCHAR(30) NOT NULL,
    cert_detailscol TEXT NULL,
    applied_date DATE NULL,
    cert_issue DATE NULL,
    validity DATE NULL,
    score DECIMAL(6,2) NULL,
    lat DECIMAL(11,8) NULL,
    longi DECIMAL(11,8) NULL,
    dist_id INT NULL,
    block_id INT NULL,
    fac_id INT NULL,
    fac_nin BIGINT NULL,
    Cert_status VARCHAR(60) NOT NULL,
    ass_mod VARCHAR(30) NOT NULL,
    date_of_ass DATE NOT NULL,
    state_id INT NULL,
    created_by INT NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cert_fac_nin (fac_nin),
    KEY idx_cert_fac_id (fac_id),
    KEY idx_cert_type (cert_type),
    KEY idx_cert_validity (validity),
    KEY idx_cert_scope (state_id, dist_id, block_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certification_history (
    history_id BIGINT NOT NULL AUTO_INCREMENT,
    certification_id BIGINT NULL,
    fac_id_fk INT NULL,
    fac_nin BIGINT NULL,
    old_data_json LONGTEXT NULL,
    new_data_json LONGTEXT NULL,
    action_type VARCHAR(30) NOT NULL,
    action_by INT NULL,
    action_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_cert_history_record (certification_id),
    KEY idx_cert_history_fac (fac_id_fk, fac_nin),
    KEY idx_cert_history_action (action_type, action_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- External/state assessor assignment workflow
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS assessor_master (
    assessor_id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    assessor_code VARCHAR(80) NOT NULL,
    assessor_name VARCHAR(500) NOT NULL,
    designation VARCHAR(120) NULL,
    mobile_no VARCHAR(500) NULL,
    mail_id VARCHAR(500) NULL,
    state_id INT NULL,
    division_id INT NULL,
    dist_id INT NULL,
    block_id INT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (assessor_id),
    UNIQUE KEY uq_assessor_code (assessor_code),
    KEY idx_assessor_user (user_id),
    KEY idx_assessor_scope (state_id, division_id, dist_id, block_id),
    KEY idx_assessor_status (is_active),
    CONSTRAINT fk_assessor_user
        FOREIGN KEY (user_id) REFERENCES s_user (u_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessor_facility_mapping (
    mapping_id BIGINT NOT NULL AUTO_INCREMENT,
    assessor_id BIGINT NOT NULL,
    fac_id INT NOT NULL,
    fac_nin BIGINT NULL,
    assignment_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    assigned_from DATE NULL,
    assigned_to DATE NULL,
    last_assessment_id BIGINT NULL,
    assigned_by INT NULL,
    remarks TEXT NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (mapping_id),
    UNIQUE KEY uq_assessor_facility (assessor_id, fac_id),
    KEY idx_mapping_assessor_status (assessor_id, assignment_status),
    KEY idx_mapping_facility (fac_id, fac_nin),
    KEY idx_mapping_assessment (last_assessment_id),
    CONSTRAINT fk_assessor_mapping_master
        FOREIGN KEY (assessor_id) REFERENCES assessor_master (assessor_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_assessor_mapping_facility
        FOREIGN KEY (fac_id) REFERENCES facilities (fac_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- AI chat assistant
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS ai_chat_messages (
    message_id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    fac_id INT NULL,
    role VARCHAR(20) NOT NULL,
    message_text TEXT NOT NULL,
    context_page VARCHAR(120) NULL,
    intent_key VARCHAR(80) NULL,
    source_type VARCHAR(30) NULL,
    created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id),
    KEY idx_ai_chat_user (user_id, created_on),
    KEY idx_ai_chat_facility (fac_id, created_on),
    KEY idx_ai_chat_intent (intent_key, created_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Optional operational metadata
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS uploaded_files (
    file_id BIGINT NOT NULL AUTO_INCREMENT,
    fac_id INT NULL,
    user_id INT NULL,
    category VARCHAR(80) NULL,
    original_name VARCHAR(255) NULL,
    stored_name VARCHAR(255) NULL,
    file_url VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT NULL,
    is_deleted TINYINT NOT NULL DEFAULT 0,
    deleted_by INT NULL,
    deleted_on TIMESTAMP NULL,
    uploaded_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (file_id),
    KEY idx_uploaded_facility (fac_id, category),
    KEY idx_uploaded_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_id, applied_by)
VALUES ('001_base_schema', 'base_schema')
ON DUPLICATE KEY UPDATE applied_on = applied_on;

SET FOREIGN_KEY_CHECKS = 1;

-- SaQshi configurable checklist response support
-- Date: 2026-07-18
-- Purpose: Add structured response metadata and field index tables for
-- healthcare and non-healthcare checklist deployments.

ALTER TABLE assessment_response
    ADD COLUMN response_type VARCHAR(50) NULL AFTER response_value,
    ADD COLUMN response_json LONGTEXT NULL AFTER response_type,
    ADD COLUMN max_score DECIMAL(10,2) NULL DEFAULT 2.00 AFTER score,
    ADD COLUMN score_status VARCHAR(30) NULL DEFAULT 'SCORED' AFTER max_score;

CREATE TABLE IF NOT EXISTS assessment_response_field_index (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
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
    UNIQUE KEY uq_assessment_response_field (
        assessment_id,
        dept_id,
        checkpoint_id,
        field_key
    ),
    KEY idx_response_field_key (field_key),
    KEY idx_response_field_number (field_key, value_number),
    KEY idx_response_field_bool (field_key, value_bool)
);

CREATE TABLE IF NOT EXISTS assessment_response_evidence (
    evidence_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assessment_id BIGINT NOT NULL,
    dept_id INT NOT NULL,
    checkpoint_id INT NOT NULL,
    field_key VARCHAR(120) NULL,
    file_url VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NULL,
    file_type VARCHAR(120) NULL,
    uploaded_by INT NULL,
    uploaded_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_response_evidence (
        assessment_id,
        dept_id,
        checkpoint_id
    )
);

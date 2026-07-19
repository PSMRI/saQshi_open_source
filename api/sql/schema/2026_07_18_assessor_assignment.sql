-- SaQshi assessor assignment workflow
-- Adds assessor master data and facility mapping for state-led assessments.

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
    KEY idx_assessor_status (is_active)
);

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
        FOREIGN KEY (assessor_id)
        REFERENCES assessor_master (assessor_id)
        ON DELETE CASCADE
);

ALTER TABLE assessment_master
    ADD COLUMN assigned_assessor_id BIGINT NULL,
    ADD COLUMN assessment_source VARCHAR(30) NULL,
    ADD INDEX idx_assessment_assessor (assigned_assessor_id);

ALTER TABLE s_user
    ADD COLUMN password_must_change TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN password_changed_on TIMESTAMP NULL;

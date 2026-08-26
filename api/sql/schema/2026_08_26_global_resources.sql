-- Global shared resources uploaded by State Admin users.
CREATE TABLE IF NOT EXISTS resources (
    resource_id BIGINT NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    resource_type VARCHAR(40) NOT NULL,
    description TEXT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size BIGINT NOT NULL,
    download_count BIGINT NOT NULL DEFAULT 0,
    applicable_facility_type_id INT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PUBLISHED',
    uploaded_by INT NOT NULL,
    created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (resource_id),
    UNIQUE KEY uq_resources_stored_name (stored_name),
    KEY idx_resources_visibility (status, resource_type, created_on),
    KEY idx_resources_applicable_type (applicable_facility_type_id, status, created_on),
    KEY idx_resources_uploader (uploaded_by, created_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supports MySQL versions where the Resources table was created before these
-- columns were introduced.
SET @resources_has_download_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resources' AND COLUMN_NAME = 'download_count');
SET @resources_sql = IF(@resources_has_download_count = 0, 'ALTER TABLE resources ADD COLUMN download_count BIGINT NOT NULL DEFAULT 0 AFTER file_size', 'SELECT 1');
PREPARE resources_stmt FROM @resources_sql;
EXECUTE resources_stmt;
DEALLOCATE PREPARE resources_stmt;

SET @resources_has_applicable_type = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resources' AND COLUMN_NAME = 'applicable_facility_type_id');
SET @resources_sql = IF(@resources_has_applicable_type = 0, 'ALTER TABLE resources ADD COLUMN applicable_facility_type_id INT NULL AFTER download_count', 'SELECT 1');
PREPARE resources_stmt FROM @resources_sql;
EXECUTE resources_stmt;
DEALLOCATE PREPARE resources_stmt;

INSERT INTO facilities_type (fac_type_id, fac_type_name, fac_type_code, is_active)
VALUES
    (1, 'CHC', 'CHC', 1),
    (2, 'DH', 'DH', 1),
    (3, 'PHC', 'PHC', 1),
    (5, 'UPHC', 'UPHC', 1),
    (8, 'AAM-SC', 'HWC', 1),
    (9, 'APHC (PHC without bed)', 'PHC without bed', 1),
    (10, 'SH', 'SDH', 1)
ON DUPLICATE KEY UPDATE fac_type_name = VALUES(fac_type_name), fac_type_code = VALUES(fac_type_code), is_active = VALUES(is_active);

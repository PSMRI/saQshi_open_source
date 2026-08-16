-- Durable background-job queue for reports, analytics and notifications.
CREATE TABLE IF NOT EXISTS background_jobs (
    job_id BIGINT NOT NULL AUTO_INCREMENT,
    job_type VARCHAR(100) NOT NULL,
    payload_json LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'QUEUED',
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    error_message TEXT NULL,
    created_by INT NULL,
    created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (job_id),
    KEY idx_background_jobs_next (status, available_at, job_id),
    KEY idx_background_jobs_type_status (job_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

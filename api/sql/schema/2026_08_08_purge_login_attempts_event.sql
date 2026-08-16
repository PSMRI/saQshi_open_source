-- Purge all login-attempt records every three seconds (MySQL 8+).
-- Requires the EVENT privilege. A DBA must enable the scheduler once per server:
--     SET GLOBAL event_scheduler = ON;

DROP EVENT IF EXISTS saqshi.purge_login_attempts_every_3_seconds;

CREATE EVENT saqshi.purge_login_attempts_every_3_seconds
    ON SCHEDULE EVERY 3 SECOND
    STARTS CURRENT_TIMESTAMP
    ON COMPLETION PRESERVE
    ENABLE
    DO
        DELETE FROM saqshi.login_attempts;

-- Verify it is enabled:
-- SHOW EVENTS FROM saqshi LIKE 'purge_login_attempts_every_3_seconds';
--
-- Disable without dropping it:
-- ALTER EVENT saqshi.purge_login_attempts_every_3_seconds DISABLE;

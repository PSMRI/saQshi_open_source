-- SaQshi user profile field encryption column sizing.
-- Run before enabling/updating encrypted user profile fields if existing columns are shorter.

ALTER TABLE s_user
    MODIFY f_name TEXT NULL,
    MODIFY m_name TEXT NULL,
    MODIFY l_name TEXT NULL,
    MODIFY mail_id TEXT NULL,
    MODIFY mob_no TEXT NULL,
    MODIFY user_type VARCHAR(30) NULL;

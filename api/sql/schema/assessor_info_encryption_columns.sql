-- SaQshi assessor/assessee personal field encryption column sizing.
-- Run before encrypting assessment_assessor_info personal fields if existing columns are shorter.

ALTER TABLE assessment_assessor_info
    MODIFY assessor_name TEXT NULL,
    MODIFY assessor_mobile TEXT NULL,
    MODIFY assessor_email TEXT NULL,
    MODIFY assessee_name TEXT NULL,
    MODIFY assessee_mobile TEXT NULL,
    MODIFY assessee_email TEXT NULL;

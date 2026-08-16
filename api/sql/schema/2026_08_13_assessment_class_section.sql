-- Store the optional section (for example, Class 9-A) captured during
-- assessor information entry. The class itself remains assessment dept_id.

ALTER TABLE assessment_assessor_info
    ADD COLUMN class_section VARCHAR(100) NULL AFTER subject_name;

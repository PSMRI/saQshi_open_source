-- SaQshi demo school-assessment data seed (MySQL 8+)
-- Creates 30 DEMO schools, 25 demo assessors, active mappings and completed
-- Class 9/10/11 assessment data across Rounds 1 and 2.
-- Safe to re-run: only rows carrying the DEMO_ / DEMO School prefix are removed.
-- Demo assessor login password: Demo@123
-- Run only in a development/test database.

START TRANSACTION;

-- Remove prior data created by this script. Existing non-demo data is untouched.
DELETE a FROM assessment_master a
JOIN facilities f ON f.fac_id = a.fac_id_fk
WHERE f.fac_name LIKE 'DEMO School %';

DELETE afm FROM assessor_facility_mapping afm
JOIN assessor_master am ON am.assessor_id = afm.assessor_id
WHERE am.assessor_code LIKE 'DEMO_%';

DELETE FROM assessor_master WHERE assessor_code LIKE 'DEMO_%';
DELETE FROM s_user WHERE u_name LIKE 'demo_assessor_%';
DELETE FROM facilities WHERE fac_name LIKE 'DEMO School %';

-- The current assessor workflow needs this round table and assessment_master.round_id.
CREATE TABLE IF NOT EXISTS facility_assessment_round (
    round_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fac_id INT NOT NULL,
    round_no INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    started_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    completed_on TIMESTAMP NULL,
    UNIQUE KEY uq_round_facility_number (fac_id, round_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If this column is absent, first run api/cli/backfill_assessment_rounds.php.

DROP PROCEDURE IF EXISTS seed_demo_school_assessments;
DELIMITER $$
CREATE PROCEDURE seed_demo_school_assessments()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE classNo INT;
    DECLARE checkpointNo INT;
    DECLARE schoolId INT;
    DECLARE assessorId BIGINT;
    DECLARE assessmentId BIGINT;
    DECLARE roundOneId BIGINT;
    DECLARE roundTwoId BIGINT;
    DECLARE checkpointId INT;
    DECLARE scoreValue DECIMAL(10,2);

    -- 30 schools spread across three blocks and districts.
    WHILE i <= 30 DO
        INSERT INTO facilities
            (state_name, division, Dist_Name, Block_Name, fac_name, Health_facilty_type,
             state_id, division_id, dist_id, block_id, NIN_no, lat, longit, is_active)
        VALUES
            ('Bihar', CONCAT('Demo Division ', ((i - 1) DIV 10) + 1),
             CONCAT('Demo District ', ((i - 1) DIV 5) + 1), CONCAT('Demo Block ', ((i - 1) DIV 3) + 1),
             CONCAT('DEMO School ', LPAD(i, 2, '0')), 10,
             1, ((i - 1) DIV 10) + 1, ((i - 1) DIV 5) + 1, ((i - 1) DIV 3) + 1,
             99000000000 + i, 25.5 + (i / 100), 85.5 + (i / 100), 1);
        SET i = i + 1;
    END WHILE;

    -- 25 login users and assessor master records.
    SET i = 1;
    WHILE i <= 25 DO
        INSERT INTO s_user
            (u_name, u_password, role_id_fk, is_active, f_name, l_name, mob_no, mail_id, user_type)
        VALUES
            (CONCAT('demo_assessor_', LPAD(i, 2, '0')),
             '$2y$12$Ybzg8/ISpVxJNLvTCwkaTOyCmtDHHSEJj3oXKilzkO.AfmF2TxF0.',
             10, 1, 'Demo', CONCAT('Assessor ', LPAD(i, 2, '0')),
             CONCAT('900000', LPAD(i, 4, '0')), CONCAT('demo.assessor', i, '@example.test'), 'ASSESSOR');

        INSERT INTO assessor_master
            (user_id, assessor_code, assessor_name, designation, mobile_no, mail_id,
             state_id, division_id, dist_id, block_id, is_active)
        VALUES
            (LAST_INSERT_ID(), CONCAT('DEMO_', LPAD(i, 3, '0')), CONCAT('Demo Assessor ', LPAD(i, 2, '0')),
             'Demo School Assessor', CONCAT('900000', LPAD(i, 4, '0')),
             CONCAT('demo.assessor', i, '@example.test'), 1, NULL, NULL, NULL, 1);
        SET i = i + 1;
    END WHILE;

    -- Map each school to three assessors. This allows simultaneous-assessor UI testing.
    SET i = 1;
    WHILE i <= 30 DO
        SELECT fac_id INTO schoolId FROM facilities WHERE fac_name = CONCAT('DEMO School ', LPAD(i, 2, '0')) LIMIT 1;
        INSERT INTO assessor_facility_mapping (assessor_id, fac_id, fac_nin, assignment_status, assigned_from, remarks)
        SELECT assessor_id, schoolId, 99000000000 + i, 'ACTIVE', CURRENT_DATE, 'Demo dashboard mapping'
        FROM assessor_master
        WHERE assessor_code IN (
            CONCAT('DEMO_', LPAD(((i - 1) MOD 25) + 1, 3, '0')),
            CONCAT('DEMO_', LPAD((i MOD 25) + 1, 3, '0')),
            CONCAT('DEMO_', LPAD(((i + 1) MOD 25) + 1, 3, '0'))
        );
        SET i = i + 1;
    END WHILE;

    -- Every school gets a completed Round 1: Classes 9, 10 and 11.
    -- First 15 schools also get a completed Round 2 reassessment for Class 9.
    SET i = 1;
    WHILE i <= 30 DO
        SELECT fac_id INTO schoolId FROM facilities WHERE fac_name = CONCAT('DEMO School ', LPAD(i, 2, '0')) LIMIT 1;
        SELECT assessor_id INTO assessorId FROM assessor_master WHERE assessor_code = CONCAT('DEMO_', LPAD(((i - 1) MOD 25) + 1, 3, '0')) LIMIT 1;

        INSERT INTO facility_assessment_round (fac_id, round_no, status, started_on, completed_on)
        VALUES (schoolId, 1, 'COMPLETED', DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY), DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 20 DAY));
        SET roundOneId = LAST_INSERT_ID();

        SET classNo = 9;
        WHILE classNo <= 11 DO
            INSERT INTO assessment_master
                (assessment_name, framework_code, fac_id_fk, round_id, start_date, end_date,
                 completed_on, status, assigned_assessor_id, assessment_source, created_by)
            VALUES
                (CONCAT('DEMO Round 1 - Class ', classNo, ' - School ', LPAD(i, 2, '0')),
                 'saqshi-education', schoolId, roundOneId, DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY),
                 DATE_SUB(CURRENT_DATE, INTERVAL 20 DAY), DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 20 DAY),
                 'COMPLETED', assessorId, 'ASSESSOR', NULL);
            SET assessmentId = LAST_INSERT_ID();

            INSERT INTO assessment_department
                (assessment_id, fac_id_fk, dept_id, is_active, status, started_on, completed_on,
                 total_checkpoints, completed_checkpoints, activated_by)
            VALUES (assessmentId, schoolId, classNo - 8, 1, 'COMPLETED', DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY),
                    DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 20 DAY), 38, 38, NULL);

            -- The education configuration uses exactly 38 checkpoints per class.
            SET checkpointNo = 0;
            WHILE checkpointNo < 38 DO
                SET checkpointId = 10001 + ((classNo - 9) * 38) + checkpointNo;
                -- Deliberately varied 1-4 scoring produces all three category colours in dashboards.
                SET scoreValue = 1 + ((i + classNo + checkpointNo) MOD 4);
                INSERT INTO assessment_response
                    (assessment_id, dept_id, checkpoint_id, response_value, response_type, score, max_score, score_status, remarks)
                VALUES (assessmentId, classNo - 8, checkpointId, CAST(scoreValue AS CHAR), 'radio', scoreValue, 4, 'SCORED', 'Demo response');
                SET checkpointNo = checkpointNo + 1;
            END WHILE;
            SET classNo = classNo + 1;
        END WHILE;

        IF i <= 15 THEN
            INSERT INTO facility_assessment_round (fac_id, round_no, status, started_on, completed_on)
            VALUES (schoolId, 2, 'COMPLETED', DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 DAY), DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY));
            SET roundTwoId = LAST_INSERT_ID();
            INSERT INTO assessment_master
                (assessment_name, framework_code, fac_id_fk, round_id, start_date, end_date,
                 completed_on, status, assigned_assessor_id, assessment_source, created_by)
            VALUES
                (CONCAT('DEMO Round 2 - Class 9 - School ', LPAD(i, 2, '0')),
                 'saqshi-education', schoolId, roundTwoId, DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY),
                 DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY), DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY),
                 'COMPLETED', assessorId, 'ASSESSOR', NULL);
            SET assessmentId = LAST_INSERT_ID();
            INSERT INTO assessment_department
                (assessment_id, fac_id_fk, dept_id, is_active, status, started_on, completed_on, total_checkpoints, completed_checkpoints)
            VALUES (assessmentId, schoolId, 1, 1, 'COMPLETED', DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 DAY), DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY), 38, 38);
            SET checkpointNo = 0;
            WHILE checkpointNo < 38 DO
                SET checkpointId = 10001 + checkpointNo;
                SET scoreValue = 2 + ((i + checkpointNo) MOD 3);
                INSERT INTO assessment_response
                    (assessment_id, dept_id, checkpoint_id, response_value, response_type, score, max_score, score_status, remarks)
                VALUES (assessmentId, 1, checkpointId, CAST(scoreValue AS CHAR), 'radio', scoreValue, 4, 'SCORED', 'Demo reassessment response');
                SET checkpointNo = checkpointNo + 1;
            END WHILE;
        END IF;
        SET i = i + 1;
    END WHILE;
END$$
DELIMITER ;

CALL seed_demo_school_assessments();
DROP PROCEDURE seed_demo_school_assessments;

COMMIT;

-- Expected result: 30 schools, 25 assessors, 90 completed Round-1 class records,
-- 15 completed Round-2 Class-9 records and 3 active assessor mappings per school.
SELECT 'Demo seed complete' AS message,
       (SELECT COUNT(*) FROM facilities WHERE fac_name LIKE 'DEMO School %') AS demo_schools,
       (SELECT COUNT(*) FROM assessor_master WHERE assessor_code LIKE 'DEMO_%') AS demo_assessors,
       (SELECT COUNT(*) FROM assessment_master WHERE assessment_name LIKE 'DEMO %') AS demo_assessments;

-- SaQshi district/domain map category demo data (MySQL 8+)
-- Run after 2026_08_06_school_assessment_demo_seed.sql.
-- Idempotent: updates only responses belonging to DEMO assessments.
-- Each assessment domain displays Abhilasha, Pragati and Jagriti districts.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS demo_district_category_rank;
CREATE TEMPORARY TABLE demo_district_category_rank AS
SELECT district_name,
       ROW_NUMBER() OVER (ORDER BY district_name) - 1 AS category_rank
FROM (
    SELECT DISTINCT UPPER(TRIM(Dist_Name)) AS district_name
    FROM facilities
    WHERE TRIM(COALESCE(Dist_Name, '')) <> ''
) districts;

UPDATE assessment_response response
JOIN assessment_master assessment
  ON assessment.assessment_id = response.assessment_id
JOIN facilities facility
  ON facility.fac_id = assessment.fac_id_fk
JOIN demo_district_category_rank district_rank
  ON district_rank.district_name = UPPER(TRIM(facility.Dist_Name))
SET response.score = CASE CASE MOD(district_rank.category_rank, 3)
        WHEN 0 THEN CASE WHEN MOD(response.checkpoint_id - 10001, 38) BETWEEN 24 AND 31 THEN 1 ELSE 0 END
        WHEN 1 THEN CASE WHEN MOD(response.checkpoint_id - 10001, 38) BETWEEN 18 AND 23 THEN 2 WHEN MOD(response.checkpoint_id - 10001, 38) BETWEEN 24 AND 31 THEN 0 ELSE 1 END
        ELSE CASE WHEN MOD(response.checkpoint_id - 10001, 38) BETWEEN 18 AND 23 THEN 1 ELSE 2 END
    END
        WHEN 0 THEN 2.00  -- 50%: Abhilasha
        WHEN 1 THEN 3.00  -- 75%: Pragati
        ELSE 4.00          -- 100%: Jagriti
    END,
    response.response_value = CAST(CASE CASE MOD(district_rank.category_rank, 3)
        WHEN 0 THEN CASE WHEN MOD(response.checkpoint_id - 10001, 38) BETWEEN 24 AND 31 THEN 1 ELSE 0 END
        WHEN 1 THEN CASE WHEN MOD(response.checkpoint_id - 10001, 38) BETWEEN 18 AND 23 THEN 2 WHEN MOD(response.checkpoint_id - 10001, 38) BETWEEN 24 AND 31 THEN 0 ELSE 1 END
        ELSE CASE WHEN MOD(response.checkpoint_id - 10001, 38) BETWEEN 18 AND 23 THEN 1 ELSE 2 END
    END
        WHEN 0 THEN 2.00
        WHEN 1 THEN 3.00
        ELSE 4.00
    END AS CHAR),
    response.remarks = 'Demo district/domain category map response'
WHERE assessment.assessment_name LIKE 'DEMO %'
  AND assessment.framework_code = 'saqshi-education'
  AND response.checkpoint_id BETWEEN 10001 AND 10114;

DROP TEMPORARY TABLE demo_district_category_rank;
COMMIT;

SELECT 'District/domain category demo seed complete' AS message,
       ROW_COUNT() AS affected_responses;

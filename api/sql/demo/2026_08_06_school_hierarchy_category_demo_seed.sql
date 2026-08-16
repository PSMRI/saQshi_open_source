-- SaQshi mixed hierarchy-category demo data (MySQL 8+)
-- Run after 2026_08_06_school_assessment_demo_seed.sql.
-- Idempotent and limited to DEMO assessment responses.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS demo_division_categories;
CREATE TEMPORARY TABLE demo_division_categories AS
SELECT division_name,
       CASE
           WHEN division_name = 'MUNGER' THEN 0
           WHEN division_name IN ('DARBHANGA', 'MAGADH', 'SARAN') THEN 1
           ELSE 2
       END AS category_code
FROM (
    SELECT DISTINCT UPPER(TRIM(division)) AS division_name
    FROM facilities
    WHERE TRIM(COALESCE(division, '')) <> ''
) divisions;

DROP TEMPORARY TABLE IF EXISTS demo_district_ranks;
CREATE TEMPORARY TABLE demo_district_ranks AS
SELECT division_name, district_name, school_count,
       ROW_NUMBER() OVER (PARTITION BY division_name ORDER BY school_count, district_name) AS district_rank,
       COUNT(*) OVER (PARTITION BY division_name) AS district_count
FROM (
    SELECT UPPER(TRIM(division)) AS division_name,
           UPPER(TRIM(Dist_Name)) AS district_name,
           COUNT(*) AS school_count
    FROM facilities
    WHERE TRIM(COALESCE(division, '')) <> ''
      AND TRIM(COALESCE(Dist_Name, '')) <> ''
    GROUP BY UPPER(TRIM(division)), UPPER(TRIM(Dist_Name))
) districts;

DROP TEMPORARY TABLE IF EXISTS demo_district_categories;
CREATE TEMPORARY TABLE demo_district_categories AS
SELECT district.division_name, district.district_name,
       CASE division.category_code
           WHEN 0 THEN CASE
               WHEN district.district_count >= 6 AND district.district_rank <= 2 THEN 2
               WHEN district.district_count >= 6 AND district.district_rank <= 4 THEN 1
               ELSE 0 END
           WHEN 1 THEN CASE
               WHEN district.district_rank = 1 THEN 0
               WHEN district.district_rank = 2 THEN 2
               ELSE 1 END
           ELSE CASE
               WHEN district.district_rank = 1 THEN 0
               WHEN district.district_rank = 2 THEN 1
               ELSE 2 END
       END AS category_code
FROM demo_district_ranks district
JOIN demo_division_categories division
  ON division.division_name = district.division_name;

DROP TEMPORARY TABLE IF EXISTS demo_block_ranks;
CREATE TEMPORARY TABLE demo_block_ranks AS
SELECT division_name, district_name, block_name, school_count,
       ROW_NUMBER() OVER (PARTITION BY division_name, district_name ORDER BY school_count, block_name) AS block_rank,
       COUNT(*) OVER (PARTITION BY division_name, district_name) AS block_count
FROM (
    SELECT UPPER(TRIM(division)) AS division_name,
           UPPER(TRIM(Dist_Name)) AS district_name,
           UPPER(TRIM(Block_Name)) AS block_name,
           COUNT(*) AS school_count
    FROM facilities
    WHERE TRIM(COALESCE(Block_Name, '')) <> ''
    GROUP BY UPPER(TRIM(division)), UPPER(TRIM(Dist_Name)), UPPER(TRIM(Block_Name))
) blocks;

DROP TEMPORARY TABLE IF EXISTS demo_block_categories;
CREATE TEMPORARY TABLE demo_block_categories AS
SELECT block.division_name, block.district_name, block.block_name,
       CASE WHEN block.division_name = 'MUNGER' THEN district.category_code ELSE CASE district.category_code
           WHEN 0 THEN CASE WHEN block.block_count >= 3 AND block.block_rank = 1 THEN 2 WHEN block.block_count >= 3 AND block.block_rank = 2 THEN 1 ELSE 0 END
           WHEN 1 THEN CASE WHEN block.block_count >= 3 AND block.block_rank = 1 THEN 0 WHEN block.block_count >= 3 AND block.block_rank = 2 THEN 2 ELSE 1 END
           ELSE CASE WHEN block.block_count >= 3 AND block.block_rank = 1 THEN 0 WHEN block.block_count >= 3 AND block.block_rank = 2 THEN 1 ELSE 2 END
       END END AS category_code
FROM demo_block_ranks block
JOIN demo_district_categories district
  ON district.division_name = block.division_name
 AND district.district_name = block.district_name;

DROP TEMPORARY TABLE IF EXISTS demo_school_ranks;
CREATE TEMPORARY TABLE demo_school_ranks AS
SELECT facility.fac_id,
       UPPER(TRIM(facility.division)) AS division_name,
       UPPER(TRIM(facility.Dist_Name)) AS district_name,
       UPPER(TRIM(facility.Block_Name)) AS block_name,
       ROW_NUMBER() OVER (
           PARTITION BY UPPER(TRIM(facility.division)), UPPER(TRIM(facility.Dist_Name)), UPPER(TRIM(facility.Block_Name))
           ORDER BY facility.fac_id
       ) AS school_rank,
       COUNT(*) OVER (
           PARTITION BY UPPER(TRIM(facility.division)), UPPER(TRIM(facility.Dist_Name)), UPPER(TRIM(facility.Block_Name))
       ) AS school_count
FROM facilities facility;

DROP TEMPORARY TABLE IF EXISTS demo_school_categories;
CREATE TEMPORARY TABLE demo_school_categories AS
SELECT school.fac_id,
       CASE WHEN school.division_name = 'MUNGER' THEN block.category_code ELSE CASE block.category_code
           WHEN 0 THEN CASE
               WHEN school.school_count >= 5 AND school.school_rank = 1 THEN 2
               WHEN school.school_count >= 5 AND school.school_rank = 2 THEN 1
               ELSE 0 END
           WHEN 1 THEN CASE
               WHEN school.school_count >= 4 AND school.school_rank = 1 THEN 0
               WHEN school.school_count >= 4 AND school.school_rank = 2 THEN 2
               ELSE 1 END
           ELSE CASE
               WHEN school.school_count >= 6 AND school.school_rank = 1 THEN 0
               WHEN school.school_count >= 6 AND school.school_rank = 2 THEN 1
               ELSE 2 END
       END END AS category_code
FROM demo_school_ranks school
JOIN demo_block_categories block
  ON block.division_name = school.division_name
 AND block.district_name = school.district_name
 AND block.block_name = school.block_name;

UPDATE assessment_response response
JOIN assessment_master assessment
  ON assessment.assessment_id = response.assessment_id
JOIN demo_school_categories school
  ON school.fac_id = assessment.fac_id_fk
SET response.score = CASE school.category_code
        WHEN 0 THEN 1.00
        WHEN 1 THEN 3.00
        ELSE 4.00
    END,
    response.response_value = CAST(CASE school.category_code
        WHEN 0 THEN 1.00
        WHEN 1 THEN 3.00
        ELSE 4.00
    END AS CHAR),
    response.remarks = 'Demo mixed hierarchy/domain category response'
WHERE assessment.assessment_name LIKE 'DEMO %'
  AND assessment.framework_code = 'saqshi-education'
  AND response.checkpoint_id BETWEEN 10001 AND 10114;

DROP TEMPORARY TABLE demo_school_categories;
DROP TEMPORARY TABLE demo_school_ranks;
DROP TEMPORARY TABLE demo_block_categories;
DROP TEMPORARY TABLE demo_block_ranks;
DROP TEMPORARY TABLE demo_district_categories;
DROP TEMPORARY TABLE demo_district_ranks;
DROP TEMPORARY TABLE demo_division_categories;

COMMIT;
SELECT 'Mixed hierarchy category demo seed complete' AS message;

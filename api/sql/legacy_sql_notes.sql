CREATE TABLE assessment_department_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fac_id_fk INT NOT NULL,
    ass_period_id INT NOT NULL,
    dept_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    activated_by INT NULL,
    activated_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fac_period_dept (fac_id_fk, ass_period_id, dept_id);



    SELECT 
  csc.csqa_id,
    aoc.concern_id,
    aoc.concern_name,
    aoc.concern_des,
    aocs.c_subtype_id,
    aocs.area_of_con_subtypedeatils,
       aocs.Reference_No,
        csc.c_subtype_Reference_No_fk,
    csc.csqa_reference_id,
    ft.fac_type_id,
    ft.facilities_type, 
   
    csc.Measurable_Element,
    csc.Checkpoint,
    csc.Assessment_Method,
    	csc.action_plan,
    fd.fac_dept_id,
    fd.dept_name,
    fd.program_tag

FROM sarbsoft_nqa_up.area_of_concern aoc

INNER JOIN sarbsoft_nqa_up.area_of_concern_subtype aocs
    ON aocs.area_of_con_id = aoc.concern_id

INNER JOIN sarbsoft_nqa_up.concern_subtype_chklist csc
    ON csc.area_of_con_id_fk = aoc.concern_id
   AND csc.c_subtype_id_fk = aocs.c_subtype_id
   AND csc.fac_type_id_fk = aocs.fac_type_id

LEFT JOIN sarbsoft_nqa_up.facilities_type ft
    ON ft.fac_type_id = csc.fac_type_id_fk

LEFT JOIN sarbsoft_nqa_up.fac_department fd
    ON fd.fac_dept_id = csc.fac_dept_id_fk

ORDER BY
    ft.fac_type_id,
    aoc.concern_id,
    aocs.Reference_No,
    csc.csqa_id;

========================================================
    SELECT DISTINCT
    f.state_id,
    f.state_name,
    d.iddivision AS division_id,
    d.division_name,
    f.dist_id,
    f.Dist_Name AS dist_name,
    f.block_id,
    f.Block_Name AS block_name,
    f.NIN_no ,
    f.fac_id AS facility_id,
    f.fac_name AS facility_name,
    f.Health_facilty_type AS facility_type_id,
    ft.facilities_type AS facility_type_name
FROM sarbsoft_nqa_up.facilities f
LEFT JOIN sarbsoft_nqa_up.division d
    ON d.iddivision = f.division_id
LEFT JOIN sarbsoft_nqa_up.facilities_type ft
    ON ft.fac_type_id = f.Health_facilty_type
ORDER BY f.fac_id;

    
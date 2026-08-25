-- One identity per role and assigned hierarchy scope. The original compound
-- indexes also applied the district/block/division constraints to Facility
-- Users because their hierarchy columns are populated. Scope-specific virtual
-- columns keep each uniqueness rule limited to its matching role.

ALTER TABLE s_user
    DROP INDEX uq_s_user_facility_role_scope,
    DROP INDEX uq_s_user_block_role_scope,
    DROP INDEX uq_s_user_district_role_scope,
    DROP INDEX uq_s_user_division_role_scope,
    ADD COLUMN uq_facility_user_scope_id INT GENERATED ALWAYS AS (CASE WHEN role_id_fk = 1 THEN fac_id_fk ELSE NULL END) VIRTUAL,
    ADD COLUMN uq_block_user_scope_id INT GENERATED ALWAYS AS (CASE WHEN role_id_fk = 8 THEN block_id ELSE NULL END) VIRTUAL,
    ADD COLUMN uq_district_user_scope_id INT GENERATED ALWAYS AS (CASE WHEN role_id_fk = 4 THEN dist_id ELSE NULL END) VIRTUAL,
    ADD COLUMN uq_division_user_scope_id INT GENERATED ALWAYS AS (CASE WHEN role_id_fk = 5 THEN division_id ELSE NULL END) VIRTUAL,
    ADD UNIQUE KEY uq_s_user_facility_role_scope (uq_facility_user_scope_id),
    ADD UNIQUE KEY uq_s_user_block_role_scope (uq_block_user_scope_id),
    ADD UNIQUE KEY uq_s_user_district_role_scope (uq_district_user_scope_id),
    ADD UNIQUE KEY uq_s_user_division_role_scope (uq_division_user_scope_id);

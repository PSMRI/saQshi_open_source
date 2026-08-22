-- One active identity per role and assigned hierarchy scope.  These indexes
-- protect against concurrent user-creation requests as well as API checks.

ALTER TABLE s_user
    ADD UNIQUE KEY uq_s_user_facility_role_scope (role_id_fk, fac_id_fk),
    ADD UNIQUE KEY uq_s_user_block_role_scope (role_id_fk, block_id),
    ADD UNIQUE KEY uq_s_user_district_role_scope (role_id_fk, dist_id),
    ADD UNIQUE KEY uq_s_user_division_role_scope (role_id_fk, division_id);

-- Local/demo only: create one active District User for every district present
-- in the facilities table.
-- Default password: 12345 (bcrypt hash below). Users must change it at login.
-- Username format: DIST_<state_id>_<dist_id>

START TRANSACTION;

INSERT INTO u_role (role_id, role_name, role_status, role_description)
VALUES (4, 'District User', 1, 'District monitoring user')
ON DUPLICATE KEY UPDATE
    role_name = VALUES(role_name),
    role_status = 1,
    role_description = VALUES(role_description);

INSERT INTO s_user (
    u_name, u_password, fac_id_fk, role_id_fk, is_active, dept_id,
    f_name, m_name, l_name, mob_no, mail_id, user_type, assessment_id,
    state_id, division_id, dist_id, block_id, password_must_change
)
SELECT
    CONCAT('DIST_', district.state_id, '_', district.dist_id) AS u_name,
    '$2y$12$q6uqOc/pE1v0npv1tfRJ0uKdP0pq71/2QGPTowX5Qnvvvey93GPNK' AS u_password,
    NULL, 4, 1, NULL,
    CONCAT('District ', district.district_name), '', 'User', NULL, NULL,
    'DISTRICT', NULL,
    district.state_id, district.division_id, district.dist_id, NULL, 1
FROM (
    SELECT
        MIN(state_id) AS state_id,
        MIN(division_id) AS division_id,
        dist_id,
        COALESCE(NULLIF(MIN(Dist_Name), ''), CONCAT('ID ', dist_id)) AS district_name
    FROM facilities
    WHERE dist_id IS NOT NULL AND dist_id > 0
    GROUP BY dist_id
) AS district
WHERE NOT EXISTS (
    SELECT 1
    FROM s_user AS existing_user
    WHERE existing_user.role_id_fk = 4
      AND existing_user.dist_id = district.dist_id
);

COMMIT;

-- Verify created/existing district users.
SELECT
    u.u_name AS username,
    u.dist_id,
    f.Dist_Name AS district,
    u.is_active,
    u.password_must_change
FROM s_user AS u
LEFT JOIN facilities AS f
    ON f.dist_id = u.dist_id
WHERE u.role_id_fk = 4
GROUP BY u.u_id, u.u_name, u.dist_id, f.Dist_Name, u.is_active, u.password_must_change
ORDER BY u.dist_id, u.u_name;

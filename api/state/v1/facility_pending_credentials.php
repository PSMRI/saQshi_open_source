<?php

/**
 * Role-11-only export of initial Facility User credentials.
 *
 * A Facility User is eligible only while its initial NIN password has not
 * been changed or reset. The initial username and password are both the NIN.
 */
require_once __DIR__ . '/_management_bootstrap.php';

Security::requireMethod('GET');

if (SessionManager::roleId() !== 11) {
    Response::forbidden('Facility User credentials are available only to role 11.');
}

try {
    $result = $con->query("
        SELECT
            u.u_id,
            u.u_name AS username,
            CAST(f.NIN_no AS CHAR) AS facility_nin,
            f.fac_name,
            f.Dist_Name AS district,
            f.Block_Name AS block,
            u.created_on
        FROM s_user u
        INNER JOIN facilities f ON f.fac_id = u.fac_id_fk
        WHERE u.role_id_fk = 1
          AND u.is_active = 1
          AND u.password_must_change = 1
          AND u.password_changed_on IS NULL
          AND f.NIN_no IS NOT NULL
          AND u.u_name = CONVERT(f.NIN_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
        ORDER BY u.created_on DESC, u.u_id DESC
    ");

    if (!$result) {
        Response::serverError('Unable to load pending Facility User credentials.');
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        // Initial Facility User credentials are deterministic by design.
        $row['temporary_password'] = (string)$row['facility_nin'];
        $rows[] = $row;
    }

    Response::success('Pending Facility User credentials loaded.', [
        'rows' => $rows,
        'count' => count($rows)
    ]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

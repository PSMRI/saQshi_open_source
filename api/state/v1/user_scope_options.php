<?php

/*! SaQshi Open Source | User Scope Options API */

require_once __DIR__ . '/_management_bootstrap.php';

Security::requireMethod('GET');

if (!in_array(SessionManager::roleId(), [9, 11], true)) {
    Response::forbidden('Only State Administration can create users.');
}

try {
    $divisions = $con->query("SELECT DISTINCT division_id, division AS division_name FROM facilities WHERE division_id IS NOT NULL AND division_id > 0 ORDER BY division");
    $districts = $con->query("SELECT DISTINCT dist_id, division_id, Dist_Name AS district_name FROM facilities WHERE dist_id IS NOT NULL AND dist_id > 0 ORDER BY Dist_Name");
    $blocks = $con->query("SELECT DISTINCT block_id, dist_id, division_id, Block_Name AS block_name FROM facilities WHERE block_id IS NOT NULL AND block_id > 0 ORDER BY Block_Name");
    $facilities = $con->query("SELECT fac_id, NIN_no, fac_name, block_id, dist_id, Health_facilty_type AS fac_type_id FROM facilities WHERE NIN_no IS NOT NULL AND NIN_no <> '' ORDER BY fac_name");
    $facilityRows = $facilities ? $facilities->fetch_all(MYSQLI_ASSOC) : [];
    $facilityTypes = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/config/masters/facility_types.json'), true) ?: [];
    $facilityTypeNames = [];
    foreach ($facilityTypes as $facilityType) {
        $facilityTypeNames[(int)($facilityType['fac_type_id'] ?? 0)] = (string)($facilityType['facilities_type'] ?? '');
    }
    foreach ($facilityRows as &$facility) {
        $facility['facilities_type'] = $facilityTypeNames[(int)($facility['fac_type_id'] ?? 0)] ?? '';
    }
    unset($facility);
    Response::success('User scope options loaded', [
        'divisions' => $divisions ? $divisions->fetch_all(MYSQLI_ASSOC) : [],
        'districts' => $districts ? $districts->fetch_all(MYSQLI_ASSOC) : [],
        'blocks' => $blocks ? $blocks->fetch_all(MYSQLI_ASSOC) : [],
        'facilities' => $facilityRows
    ]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

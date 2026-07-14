<?php

/**
 * my_departments.php
 * -------------------------------------------------------
 * Returns departments applicable to logged-in user's facility.
 *
 * Uses:
 * $_SESSION['fac_id']
 *
 * Files:
 * api/config/masters/facilities.json
 * api/config/frameworks/saqshi-nqas.json
 *
 * URL:
 * /api/framework/v1/my_departments.php?framework=saqshi-nqas
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';

try {
 $facId = SessionManager::facilityId();
    if (!isset($_SESSION['fac_id']) || (int)$_SESSION['fac_id'] <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    $facId = (int)$_SESSION['fac_id'];

    $frameworkCode = $_GET['framework'] ?? 'saqshi-nqas';
    $frameworkCode = trim($frameworkCode);

    if ($frameworkCode === '') {
        Response::validation([
            'framework' => 'Framework code is required'
        ]);
    }

    /*
     * Load facility master JSON
     */
    $facilityJsonPath = __DIR__ . '/../../config/masters/facilities.json';

    if (!file_exists($facilityJsonPath)) {
        Response::serverError('facilities.json not found');
    }

    $states = json_decode(
        file_get_contents($facilityJsonPath),
        true
    );

    if (!is_array($states)) {
        Response::serverError('Invalid facilities.json format');
    }

    /*
     * Find logged-in user's facility
     */
    $facilityData = null;

    foreach ($states as $state) {

        $stateId = $state['state_id'] ?? $state['stateid'] ?? null;
        $stateName = $state['state_name'] ?? $state['statename'] ?? '';

        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {

                        if ((int)($facility['fac_id'] ?? 0) === $facId) {

                            $facilityData = [
                                'fac_id'          => (int)$facility['fac_id'],
                                'fac_name'        => $facility['fac_name'] ?? '',
                                'nin_no'          => $facility['nin_no'] ?? null,
                                'fac_type_id'     => (int)($facility['fac_type_id'] ?? 0),
                                'facilities_type' => $facility['facilities_type'] ?? '',

                                'block_id'        => (int)($block['block_id'] ?? 0),
                                'block_name'      => $block['block_name'] ?? '',

                                'dist_id'         => (int)($district['dist_id'] ?? 0),
                                'dist_name'       => $district['dist_name'] ?? '',

                                'division_id'     => (int)($division['division_id'] ?? 0),
                                'division_name'   => $division['division_name'] ?? '',

                                'state_id'        => $stateId !== null ? (int)$stateId : null,
                                'state_name'      => $stateName
                            ];

                            break 5;
                        }
                    }
                }
            }
        }
    }

    if (!$facilityData) {
        Response::error('Assigned facility not found in facilities.json');
    }

    $facTypeId = (int)$facilityData['fac_type_id'];

    if ($facTypeId <= 0) {
        Response::error('Facility type not found for assigned facility');
    }

    /*
     * Load framework JSON
     */
    $engine = FrameworkEngine::load($frameworkCode);

    $departments = $engine->getDepartments($facTypeId);

    $departmentList = [];

foreach ($departments as $department) {

    $deptId = (int)(
        $department['dept_id']
        ?? $department['fac_dept_id']
        ?? $department['id']
        ?? 0
    );

    $deptName =
        $department['dept_name']
        ?? $department['department_name']
        ?? $department['fac_dept_name']
        ?? $department['name']
        ?? 'Department';

    $departmentList[] = [
        'dept_id'       => $deptId,
        'fac_dept_id'   => $deptId,
        'dept_name'     => $deptName,
        'department_name' => $deptName,
        'concern_count' => count($department['concerns'] ?? [])
    ];
}
    

    Response::success(
        'Departments fetched successfully',
        [
            'framework' => $frameworkCode,
            'facility' => $facilityData,
            'total' => count($departmentList),
            'departments' => $departmentList
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
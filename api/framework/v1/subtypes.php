<?php

/**
 * subtypes.php
 * -------------------------------------------------------
 * Returns subtypes/standards for selected department + concern
 * based on logged-in user's assigned facility type.
 *
 * Uses:
 * $_SESSION['fac_id']
 *
 * URL:
 * /api/framework/v1/subtypes.php?framework=saqshi-nqas&dept_id=1&concern_id=1
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';

require_once __DIR__ . '/../../core/Response.php';


try {

    if (!isset($_SESSION['fac_id']) || (int)$_SESSION['fac_id'] <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    $facId = (int)$_SESSION['fac_id'];

    $frameworkCode = $_GET['framework'] ?? 'saqshi-nqas';
    $deptId = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
    $concernId = isset($_GET['concern_id']) ? (int)$_GET['concern_id'] : 0;

    if (trim($frameworkCode) === '') {
        Response::validation([
            'framework' => 'Framework code is required'
        ]);
    }

    if ($deptId <= 0) {
        Response::validation([
            'dept_id' => 'Department ID is required'
        ]);
    }

    if ($concernId <= 0) {
        Response::validation([
            'concern_id' => 'Concern ID is required'
        ]);
    }

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

    $engine = FrameworkEngine::load($frameworkCode);

    $department = $engine->getDepartmentById(
        $facTypeId,
        $deptId
    );

    if (!$department) {
        Response::error('Department not found for this facility type');
    }

    $concern = $engine->getConcernById(
        $facTypeId,
        $deptId,
        $concernId
    );

    if (!$concern) {
        Response::error('Concern not found for this department');
    }

    $subtypes = $engine->getSubtypes(
        $facTypeId,
        $deptId,
        $concernId
    );

    $subtypeList = [];

    foreach ($subtypes as $subtype) {
        $subtypeList[] = [
            'c_subtype_id' => (int)($subtype['c_subtype_id'] ?? 0),
            'Reference_No' => $subtype['Reference_No'] ?? '',
            'area_of_con_subtypedeatils' => $subtype['area_of_con_subtypedeatils'] ?? '',
            'checkpoint_count' => count($subtype['checkpoints'] ?? [])
        ];
    }

    Response::success(
        'Subtypes fetched successfully',
        [
            'framework' => $frameworkCode,
            'facility' => $facilityData,
            'department' => [
                'fac_dept_id' => (int)($department['fac_dept_id'] ?? 0),
                'dept_name'   => $department['dept_name'] ?? ''
            ],
            'concern' => [
                'concern_id'   => (int)($concern['concern_id'] ?? 0),
                'concern_name' => $concern['concern_name'] ?? '',
                'concern_des'  => $concern['concern_des'] ?? ''
            ],
            'total' => count($subtypeList),
            'subtypes' => $subtypeList
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
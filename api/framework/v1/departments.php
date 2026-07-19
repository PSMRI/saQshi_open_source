<?php

/**
 * departments.php
 * -------------------------------------------------------
 * Returns departments from framework JSON with runtime
 * active/inactive status for a facility + assessment period.
 *
 * URL:
 * /api/framework/v1/departments.php?framework=sample-framework&facility_type=DH&fac_id=1&assessment_id=1
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../service/DepartmentStatusService.php';
require_once __DIR__ . '/../../assets/conn/db.php';

try {

    $frameworkCode = $_GET['framework'] ?? 'sample-framework';
    $facilityType  = $_GET['facility_type'] ?? '';
    $facId         = isset($_GET['fac_id']) ? (int)$_GET['fac_id'] : 0;
    $assPeriod     = isset($_GET['assessment_id'])
        ? (int)$_GET['assessment_id']
        : (int)($_GET['ass_period'] ?? 0);

    if (trim($frameworkCode) === '') {
        Response::validation([
            'framework' => 'Framework code is required'
        ]);
    }

    if (trim($facilityType) === '') {
        Response::validation([
            'facility_type' => 'Facility type is required'
        ]);
    }

    if ($facId <= 0) {
        Response::validation([
            'fac_id' => 'Facility ID is required'
        ]);
    }

    if ($assPeriod <= 0) {
        Response::validation([
            'assessment_id' => 'Assessment ID is required'
        ]);
    }

    $engine = FrameworkEngine::load($frameworkCode);

    $frameworkDepartments = $engine->getDepartments($facilityType);

    $departmentService = new DepartmentStatusService($con);

    $statusResult = $departmentService->getStatusList(
        $facId,
        $assPeriod
    );

    $statusMap = [];

    if ($statusResult['status'] === 'success' && is_array($statusResult['data'])) {
        foreach ($statusResult['data'] as $status) {
            $statusMap[(int)$status['dept_id']] = [
                'is_active'    => (int)$status['is_active'],
                'activated_by' => $status['activated_by'] ?? null,
                'activated_on' => $status['activated_on'] ?? null,
                'updated_on'   => $status['updated_on'] ?? null
            ];
        }
    }

    $departments = [];

    foreach ($frameworkDepartments as $department) {

        $deptId = (int)($department['id'] ?? 0);

        $runtimeStatus = $statusMap[$deptId] ?? null;

       $departments[] = [
    'id'            => $deptId,
    'dept_id'       => $deptId,
    'fac_dept_id'   => $deptId,
    'code'          => $department['code'] ?? null,
    'name'          => $department['name'] ?? '',
    'department_name' => $department['name'] ?? '',
    'dept_name'     => $department['name'] ?? '',
    'facility_type' => $department['facility_type'] ?? $facilityType,
    'subtype_ids'   => $department['subtype_ids'] ?? [],
    'is_active'     => $runtimeStatus ? (int)$runtimeStatus['is_active'] : 0,
    'configured'    => $runtimeStatus ? true : false,
    'activated_by'  => $runtimeStatus['activated_by'] ?? null,
    'activated_on'  => $runtimeStatus['activated_on'] ?? null,
    'updated_on'    => $runtimeStatus['updated_on'] ?? null
];
    }

   Response::success(
    'Departments fetched successfully',
    [
        'framework' => method_exists($engine, 'getFrameworkInfo')
            ? $engine->getFrameworkInfo()
            : [
                'code' => $frameworkCode
            ],
        'facility_type' => strtoupper($facilityType),
        'fac_id'        => $facId,
        'assessment_id' => $assPeriod,
        'ass_period'    => $assPeriod,
        'total'         => count($departments),
        'departments'   => $departments
    ]
);

} catch (Throwable $e) {

    Response::serverError(
        $e->getMessage()
    );
}

<?php

/**
 * facility-types.php
 * -------------------------------------------------------
 * Load facility types from nested SaQshi JSON framework.
 *
 * URL:
 * /api/framework/v1/facility-types.php?framework=saqshi-nqas
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';

try {

    $frameworkCode = $_GET['framework'] ?? 'saqshi-nqas';
    $frameworkCode = trim($frameworkCode);

    if ($frameworkCode === '') {
        Response::validation([
            'framework' => 'Framework code is required'
        ]);
    }

    $engine = FrameworkEngine::load($frameworkCode);

    $facilityTypes = [];

    foreach ($engine->getFacilityTypes() as $facilityType) {
        $facilityTypes[] = [
            'fac_type_id'     => (int)($facilityType['fac_type_id'] ?? 0),
            'facilities_type' => $facilityType['facilities_type'] ?? '',
            'department_count'=> count($facilityType['departments'] ?? [])
        ];
    }

    Response::success(
        'Facility types fetched successfully',
        [
            'framework' => $frameworkCode,
            'total' => count($facilityTypes),
            'facility_types' => $facilityTypes
        ]
    );

} catch (Throwable $e) {

    Response::serverError(
        $e->getMessage()
    );
}
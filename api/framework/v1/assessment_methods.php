<?php

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';

//require_once __DIR__ . '/../../core/Response.php';


try {

    if (!isset($_SESSION['fac_id']) || (int)$_SESSION['fac_id'] <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    $frameworkCode = $_GET['framework'] ?? 'saqshi-nqas';
    $deptId        = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
    $concernId     = isset($_GET['concern_id']) ? (int)$_GET['concern_id'] : 0;
    $subtypeId     = isset($_GET['subtype_id']) ? (int)$_GET['subtype_id'] : 0;

    if ($deptId <= 0) {
        Response::validation(['dept_id' => 'Department ID is required']);
    }

    if ($concernId <= 0) {
        Response::validation(['concern_id' => 'Concern ID is required']);
    }

    if ($subtypeId <= 0) {
        Response::validation(['subtype_id' => 'Subtype ID is required']);
    }

    /*
     * Find logged-in user's facility type
     */
    $facilityJsonPath = __DIR__ . '/../../config/masters/facilities.json';

    if (!file_exists($facilityJsonPath)) {
        Response::serverError('facilities.json not found');
    }

    $states = json_decode(file_get_contents($facilityJsonPath), true);

    if (!is_array($states)) {
        Response::serverError('Invalid facilities.json format');
    }

    $facId = (int)$_SESSION['fac_id'];
    $facilityData = null;

    foreach ($states as $state) {
        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {

                        if ((int)($facility['fac_id'] ?? 0) === $facId) {
                            $facilityData = [
                                'fac_id'          => (int)$facility['fac_id'],
                                'fac_name'        => $facility['fac_name'] ?? '',
                                'fac_type_id'     => (int)($facility['fac_type_id'] ?? 0),
                                'facilities_type' => $facility['facilities_type'] ?? ''
                            ];

                            break 5;
                        }
                    }
                }
            }
        }
    }

    if (!$facilityData) {
        Response::error('Assigned facility not found');
    }

    $facTypeId = (int)$facilityData['fac_type_id'];

    /*
     * Load framework and checkpoints
     */
    $engine = FrameworkEngine::load($frameworkCode);

    $checkpoints = $engine->getCheckpoints(
        $facTypeId,
        $deptId,
        $concernId,
        $subtypeId
    );

    $methodMap = [];

    foreach ($checkpoints as $checkpoint) {

        $rawMethod =
            $checkpoint['Assessment_Method']
            ?? $checkpoint['assessment_method']
            ?? '';

        if (is_array($rawMethod)) {
            $methods = $rawMethod;
        } else {
            $methods = preg_split('/[\/,]/', $rawMethod);
        }

        foreach ($methods as $method) {
            $code = strtoupper(trim($method));

            if ($code === '') {
                continue;
            }

            $methodMap[$code] = [
                'code' => $code,
                'name' => match ($code) {
                    'SI' => 'Staff Interview',
                    'OB' => 'Observation',
                    'RR' => 'Record Review',
                    'PI' => 'Patient Interview',
                    default => $code
                }
            ];
        }
    }

    Response::success(
        'Assessment methods fetched successfully',
        [
            'framework' => $frameworkCode,
            'facility' => $facilityData,
            'dept_id' => $deptId,
            'concern_id' => $concernId,
            'subtype_id' => $subtypeId,
            'total' => count($methodMap),
            'assessment_methods' => array_values($methodMap)
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
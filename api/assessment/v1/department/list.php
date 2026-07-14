<?php

/**
 * list.php
 * -------------------------------------------------------
 * List all framework departments for logged-in facility type
 * and show activation status for current active assessment.
 *
 * Method: GET
 *
 * URL:
 * /api/assessment/v1/department/list.php?framework=saqshi-nqas&assessment_id=1
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../../auth_api.php';
require_once __DIR__ . '/../../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../../assets/conn/db.php';

Security::requireMethod('GET');

try {

    $facId  = SessionManager::facilityId();

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    $frameworkCode = trim($_GET['framework'] ?? 'saqshi-nqas');
    $assessmentId = isset($_GET['assessment_id'])
        ? (int)$_GET['assessment_id']
        : 0;

    if ($assessmentId <= 0) {
        Response::validation([
            'assessment_id' => 'assessment_id is required'
        ]);
    }

    /*
     * Validate active assessment
     */
    $sqlAssessment = "
        SELECT assessment_id, framework_code, fac_id_fk, status
        FROM assessment_master
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND status = 'ACTIVE'
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlAssessment);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $assessment = $stmt->get_result()->fetch_assoc();

    if (!$assessment) {
        Response::error('Active assessment not found for this facility');
    }

    /*
     * Load facility type from facilities.json
     */
    $facilityJsonPath = __DIR__ . '/../../../config/masters/facilities.json';

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
                                'dist_name'       => $district['dist_name'] ?? ''
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
     * Load all departments from framework JSON
     */
    $engine = FrameworkEngine::load($frameworkCode);
    $allDepartments = $engine->getDepartments($facTypeId);

    /*
     * Load activated departments from DB
     */
    $sqlActive = "
        SELECT dept_id, is_active, activated_by, activated_on
        FROM assessment_department
        WHERE assessment_id = ?
          AND fac_id_fk = ?
    ";

    $stmt = $con->prepare($sqlActive);

    if (!$stmt) {
        Response::serverError('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $res = $stmt->get_result();

    $activeMap = [];

    while ($row = $res->fetch_assoc()) {
        $activeMap[(int)$row['dept_id']] = [
            'is_active'    => (int)$row['is_active'],
            'activated_by' => (int)$row['activated_by'],
            'activated_on' => $row['activated_on']
        ];
    }

    /*
     * Merge framework departments with activation status
     */
    $departments = [];

    foreach ($allDepartments as $department) {

        $deptId = (int)($department['fac_dept_id'] ?? 0);

        if ($deptId <= 0) {
            continue;
        }

        $activeInfo = $activeMap[$deptId] ?? null;

        $departments[] = [
            'dept_id'       => $deptId,
            'dept_name'     => $department['dept_name'] ?? '',
            'program_tag'   => $department['program_tag'] ?? '',
            'concern_count' => count($department['concerns'] ?? []),

            'is_active'     => $activeInfo ? (int)$activeInfo['is_active'] : 0,
            'can_activate'  => $activeInfo ? false : true,
            'can_deactivate'=> false,

            'activated_by'  => $activeInfo['activated_by'] ?? null,
            'activated_on'  => $activeInfo['activated_on'] ?? null
        ];
    }

    Response::success(
        'Departments fetched successfully',
        [
            'assessment_id' => $assessmentId,
            'framework' => $frameworkCode,
            'facility' => $facilityData,
            'total' => count($departments),
            'departments' => $departments
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
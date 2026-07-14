<?php

/**
 * my_facility.php
 * -------------------------------------------------------
 * Returns logged-in user's assigned facility details
 * from JSON master file.
 */

require_once __DIR__ . '/../../auth_api.php';

try {

    $facId = SessionManager::facilityId();

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    $jsonPath = __DIR__ . '/../../config/masters/facilities.json';

    if (!file_exists($jsonPath)) {
        Response::serverError('facilities.json not found');
    }

    $states = json_decode(
        file_get_contents($jsonPath),
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

    Response::success(
        'Facility fetched successfully',
        $facilityData
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
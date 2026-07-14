<?php

/**
 * facilities.php
 * -------------------------------------------------------
 * Logged-in user's assigned facility profile.
 *
 * GET  /api/admin/v1/facilities.php
 * POST /api/admin/v1/facilities.php
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

function adminFacilityRequest(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function adminFacilityColumns(mysqli $con): array
{
    $result = $con->query("SHOW COLUMNS FROM facilities");

    if (!$result) {
        Response::serverError('Unable to read facilities table columns: ' . $con->error);
    }

    $columns = [];

    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = true;
    }

    return $columns;
}

function adminFacilityColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) {
            return $candidate;
        }
    }

    return null;
}

function adminFacilityJsonMaster(int $facId): ?array
{
    $path = __DIR__ . '/../../config/masters/facilities.json';

    if (!file_exists($path)) {
        return null;
    }

    $states = json_decode(file_get_contents($path), true);

    if (!is_array($states)) {
        return null;
    }

    foreach ($states as $state) {
        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {
                        if ((int)($facility['fac_id'] ?? 0) === $facId) {
                            return [
                                'fac_id' => (int)$facility['fac_id'],
                                'fac_name' => (string)($facility['fac_name'] ?? ''),
                                'nin_no' => (string)($facility['nin_no'] ?? ''),
                                'NIN_no' => (string)($facility['nin_no'] ?? ''),
                                'Health_facilty_type' => (int)($facility['fac_type_id'] ?? 0),
                                'fac_type_id' => (int)($facility['fac_type_id'] ?? 0),
                                'facilities_type' => (string)($facility['facilities_type'] ?? ''),
                                'state_id' => (int)($state['state_id'] ?? 0),
                                'state_name' => (string)($state['state_name'] ?? ''),
                                'state_code' => (int)($state['state_id'] ?? 0),
                                'division_id' => (int)($division['division_id'] ?? 0),
                                'division' => (string)($division['division_name'] ?? ''),
                                'division_name' => (string)($division['division_name'] ?? ''),
                                'dist_id' => (int)($district['dist_id'] ?? 0),
                                'Dist_Name' => (string)($district['dist_name'] ?? ''),
                                'dist_name' => (string)($district['dist_name'] ?? ''),
                                'block_id' => (int)($block['block_id'] ?? 0),
                                'Block_Name' => (string)($block['block_name'] ?? ''),
                                'block_name' => (string)($block['block_name'] ?? ''),
                                'lat' => '',
                                'longit' => '',
                                'latitude' => '',
                                'longitude' => '',
                                'is_active' => 1,
                                'source' => 'json'
                            ];
                        }
                    }
                }
            }
        }
    }

    return null;
}

function adminFacilityTypeName(int $typeId): string
{
    $path = __DIR__ . '/../../config/masters/facility_types.json';

    if (!file_exists($path)) {
        return '';
    }

    $types = json_decode(file_get_contents($path), true);

    if (!is_array($types)) {
        return '';
    }

    foreach ($types as $type) {
        if ((int)($type['fac_type_id'] ?? 0) === $typeId) {
            return (string)($type['facilities_type'] ?? '');
        }
    }

    return '';
}

function adminFacilityFind(mysqli $con, int $facId): ?array
{
    $base = adminFacilityJsonMaster($facId);

    if (!$base) {
        return null;
    }

    $columns = adminFacilityColumns($con);
    $latColumn = adminFacilityColumn($columns, ['lat', 'latitude']);
    $lngColumn = adminFacilityColumn($columns, ['longit', 'longitude', 'lng']);
    $sql = "
        SELECT f.*
        FROM facilities f
        WHERE f.fac_id = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        Response::serverError('Facility lookup prepare failed: ' . $con->error);
    }

    $stmt->bind_param('i', $facId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        return $base;
    }

    $row['nin_no'] = (string)($row['NIN_no'] ?? $row['nin_no'] ?? $base['nin_no'] ?? '');
    $row['latitude'] = $latColumn ? (string)($row[$latColumn] ?? '') : '';
    $row['longitude'] = $lngColumn ? (string)($row[$lngColumn] ?? '') : '';
    $row['facilities_type'] = adminFacilityTypeName((int)($row['Health_facilty_type'] ?? $base['Health_facilty_type'] ?? 0));
    $row['source'] = 'json+db';

    return array_merge($base, $row);
}

function adminFacilityDbExists(mysqli $con, int $facId): bool
{
    $stmt = $con->prepare("SELECT fac_id FROM facilities WHERE fac_id = ? LIMIT 1");

    if (!$stmt) {
        Response::serverError('Facility existence prepare failed: ' . $con->error);
    }

    $stmt->bind_param('i', $facId);
    $stmt->execute();
    $stmt->store_result();

    return $stmt->num_rows > 0;
}

function adminFacilityNinExistsForOtherFacility(mysqli $con, string $ninNo, int $facId, array $columns): bool
{
    $ninColumn = adminFacilityColumn($columns, ['NIN_no', 'nin_no']);

    if (!$ninColumn || $ninNo === '') {
        return false;
    }

    $stmt = $con->prepare("
        SELECT fac_id
        FROM facilities
        WHERE {$ninColumn} = ?
          AND fac_id <> ?
        LIMIT 1
    ");

    if (!$stmt) {
        Response::serverError('NIN duplicate check prepare failed: ' . $con->error);
    }

    $stmt->bind_param('si', $ninNo, $facId);
    $stmt->execute();
    $stmt->store_result();

    return $stmt->num_rows > 0;
}

function adminFacilityValidateCoordinate(mixed $value, float $min, float $max): bool
{
    if ($value === null || $value === '') {
        return true;
    }

    if (!is_numeric($value)) {
        return false;
    }

    $number = (float)$value;
    return $number >= $min && $number <= $max;
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $facId = SessionManager::facilityId();

    if ($facId <= 0) {
        Response::validation([
            'fac_id' => 'Facility is not assigned to logged-in user'
        ]);
    }

    if ($method === 'GET') {
        $facility = adminFacilityFind($con, $facId);

        if (!$facility) {
            Response::notFound('Facility not found');
        }

        Response::success('Facility fetched successfully', [
            'facility' => $facility
        ]);
    }

    if ($method !== 'POST') {
        Response::error('Method not allowed', null, 405);
    }

    $columns = adminFacilityColumns($con);
    $latColumn = adminFacilityColumn($columns, ['lat', 'latitude']);
    $lngColumn = adminFacilityColumn($columns, ['longit', 'longitude', 'lng']);

    if (!$latColumn || !$lngColumn) {
        Response::serverError('Facility coordinate columns not found');
    }

    $request = adminFacilityRequest();
    $baseFacility = adminFacilityJsonMaster($facId);

    if (!$baseFacility) {
        Response::notFound('Facility not found in facilities.json');
    }

    $facilityName = trim((string)($request['fac_name'] ?? $request['facility_name'] ?? ''));
    $ninNo = trim((string)($request['nin_no'] ?? ''));
    $facilityType = (int)($request['Health_facilty_type'] ?? $request['facility_type'] ?? 0);
    $stateId = (int)($request['state_id'] ?? 0);
    $divisionId = (int)($request['division_id'] ?? 0);
    $districtId = (int)($request['dist_id'] ?? $request['district_id'] ?? 0);
    $blockId = (int)($request['block_id'] ?? 0);
    $latitude = trim((string)($request['latitude'] ?? ''));
    $longitude = trim((string)($request['longitude'] ?? ''));
    $isActive = isset($request['is_active']) ? (int)$request['is_active'] : 1;

    if ($ninNo === '') {
        $ninNo = (string)($baseFacility['nin_no'] ?? '');
    }

    if (adminFacilityNinExistsForOtherFacility($con, $ninNo, $facId, $columns)) {
        Response::validation([
            'nin_no' => 'This NIN number is already assigned to another facility'
        ]);
    }

    $errors = [];

    if ($facilityName === '') {
        $errors['fac_name'] = 'Facility name is required';
    }

    if ($facilityType <= 0) {
        $errors['facility_type'] = 'Facility type is required';
    }

    if (!adminFacilityValidateCoordinate($latitude, -90, 90)) {
        $errors['latitude'] = 'Latitude must be between -90 and 90';
    }

    if (!adminFacilityValidateCoordinate($longitude, -180, 180)) {
        $errors['longitude'] = 'Longitude must be between -180 and 180';
    }

    if (!empty($errors)) {
        Response::validation($errors);
    }

    $columnValues = [
        'fac_id' => [$facId, 'i'],
        'state_name' => [$baseFacility['state_name'] ?? '', 's'],
        'Dist_Name' => [$baseFacility['Dist_Name'] ?? $baseFacility['dist_name'] ?? '', 's'],
        'Block_Name' => [$baseFacility['Block_Name'] ?? $baseFacility['block_name'] ?? '', 's'],
        'fac_name' => [$facilityName, 's'],
        'Health_facilty_type' => [$facilityType, 'i'],
        'block_id' => [$blockId, 'i'],
        'dist_id' => [$districtId, 'i'],
        'state_code' => [$baseFacility['state_code'] ?? $stateId, 'i'],
        'division_id' => [$divisionId, 'i'],
        'division' => [$baseFacility['division'] ?? $baseFacility['division_name'] ?? '', 's'],
        'NIN_no' => [$ninNo, 's'],
        'lat' => [$latitude, 's'],
        'longit' => [$longitude, 's'],
        'is_active' => [$isActive, 'i'],
        'state_id' => [$stateId, 'i']
    ];

    $exists = adminFacilityDbExists($con, $facId);

    if ($exists) {
        $set = [];
        $values = [];
        $types = '';

        foreach ($columnValues as $column => [$value, $type]) {
            if ($column === 'fac_id' || !isset($columns[$column])) {
                continue;
            }

            $set[] = "{$column} = ?";
            $values[] = $value;
            $types .= $type;
        }

        $values[] = $facId;
        $types .= 'i';
        $sql = "
            UPDATE facilities
            SET " . implode(', ', $set) . "
            WHERE fac_id = ?
            LIMIT 1
        ";
    } else {
        $insertColumns = [];
        $placeholders = [];
        $values = [];
        $types = '';

        foreach ($columnValues as $column => [$value, $type]) {
            if (!isset($columns[$column])) {
                continue;
            }

            $insertColumns[] = $column;
            $placeholders[] = '?';
            $values[] = $value;
            $types .= $type;
        }

        $sql = "
            INSERT INTO facilities
                (" . implode(', ', $insertColumns) . ")
            VALUES
                (" . implode(', ', $placeholders) . ")
        ";
    }

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        Response::serverError('Facility update prepare failed: ' . $con->error);
    }

    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        Response::serverError('Facility update failed: ' . $stmt->error);
    }

    Response::success('Facility updated successfully', [
        'facility' => adminFacilityFind($con, $facId)
    ]);

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

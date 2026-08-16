<?php

/*! Assessor facility profile: mapped facility coordinates only. */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

function assessorProfileMaster(int $facId): ?array
{
    $path = __DIR__ . '/../../config/masters/facilities.json';
    $states = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
    foreach (is_array($states) ? $states : [] as $state) foreach (($state['divisions'] ?? []) as $division) foreach (($division['districts'] ?? []) as $district) foreach (($district['blocks'] ?? []) as $block) foreach (($block['facilities'] ?? []) as $facility) {
        if ((int)($facility['fac_id'] ?? 0) === $facId) return [
            'fac_id' => $facId, 'fac_name' => (string)($facility['fac_name'] ?? ''),
            'nin_no' => (string)($facility['nin_no'] ?? ''), 'facilities_type' => (string)($facility['facilities_type'] ?? ''),
            'village' => (string)($facility['village'] ?? ''), 'state_name' => (string)($state['state_name'] ?? ''),
            'division' => (string)($division['division_name'] ?? ''), 'district' => (string)($district['dist_name'] ?? ''),
            'block' => (string)($block['block_name'] ?? ''), 'latitude' => '', 'longitude' => '',
            'fac_type_id' => (int)($facility['fac_type_id'] ?? 0), 'state_id' => (int)($state['state_id'] ?? 0),
            'division_id' => (int)($division['division_id'] ?? 0), 'dist_id' => (int)($district['dist_id'] ?? 0),
            'block_id' => (int)($block['block_id'] ?? 0)
        ];
    }
    return null;
}

function assessorProfileColumn(mysqli $con, array $names): ?string
{
    $columns = []; $result = $con->query('SHOW COLUMNS FROM facilities');
    while ($result && ($row = $result->fetch_assoc())) $columns[$row['Field']] = true;
    foreach ($names as $name) if (isset($columns[$name])) return $name;
    return null;
}

function assessorProfileColumns(mysqli $con): array
{
    $columns = []; $result = $con->query('SHOW COLUMNS FROM facilities');
    while ($result && ($row = $result->fetch_assoc())) $columns[$row['Field']] = true;
    return $columns;
}

function assessorProfileEnsureAudit(mysqli $con): void
{
    $con->query("CREATE TABLE IF NOT EXISTS assessor_facility_profile_update (
        fac_id INT NOT NULL PRIMARY KEY, assessor_id INT NOT NULL, updated_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST'], true)) Response::error('Method not allowed', null, 405);
    $input = $method === 'POST' ? Security::jsonInput() : $_GET;
    $facId = (int)($input['fac_id'] ?? 0);
    $assessorId = (int)($_SESSION['assessor_id'] ?? 0);
    if ($assessorId <= 0) {
        // The profile can be opened directly from Assigned Schools, before an
        // assessment has populated the assessor session context.
        $userId = SessionManager::userId();
        $username = strtoupper(trim((string)SessionManager::username()));
        $assessor = $con->prepare("SELECT assessor_id FROM assessor_master WHERE is_active = 1 AND (user_id = ? OR assessor_code = ?) ORDER BY user_id = ? DESC LIMIT 1");
        $assessor->bind_param('isi', $userId, $username, $userId); $assessor->execute();
        $assessorId = (int)(($assessor->get_result()->fetch_assoc() ?: [])['assessor_id'] ?? 0);
        if ($assessorId > 0) $_SESSION['assessor_id'] = $assessorId;
    }
    if ($facId <= 0 || $assessorId <= 0) Response::forbidden('An assessor profile and facility are required.');

    $mapped = $con->prepare("SELECT 1 FROM assessor_facility_mapping WHERE assessor_id = ? AND fac_id = ? AND assignment_status = 'ACTIVE' LIMIT 1");
    $mapped->bind_param('ii', $assessorId, $facId); $mapped->execute();
    if (!$mapped->get_result()->fetch_assoc()) Response::forbidden('This facility is not assigned to the logged-in assessor.');

    $facility = assessorProfileMaster($facId);
    if (!$facility) Response::notFound('Facility not found in facilities.json.');
    $columns = assessorProfileColumns($con);
    $latColumn = assessorProfileColumn($con, ['lat', 'latitude']);
    $lngColumn = assessorProfileColumn($con, ['longit', 'longitude', 'lng']);
    if (!$latColumn || !$lngColumn) Response::serverError('Facility coordinate columns not found.');
    assessorProfileEnsureAudit($con);

    $existing = $con->prepare("SELECT `{$latColumn}` AS latitude, `{$lngColumn}` AS longitude FROM facilities WHERE fac_id = ? LIMIT 1");
    $existing->bind_param('i', $facId); $existing->execute();
    $coordinates = $existing->get_result()->fetch_assoc() ?: [];
    $facility['latitude'] = (string)($coordinates['latitude'] ?? '');
    $facility['longitude'] = (string)($coordinates['longitude'] ?? '');

    $audit = $con->prepare("SELECT a.assessor_id, a.updated_on, m.assessor_code FROM assessor_facility_profile_update a LEFT JOIN assessor_master m ON m.assessor_id = a.assessor_id WHERE a.fac_id = ? LIMIT 1");
    $audit->bind_param('i', $facId); $audit->execute(); $lastUpdate = $audit->get_result()->fetch_assoc() ?: [];
    $profileUpdate = [
        'exists' => !empty($lastUpdate), 'updated_by_other' => !empty($lastUpdate) && (int)$lastUpdate['assessor_id'] !== $assessorId,
        'assessor_code' => (string)($lastUpdate['assessor_code'] ?? ''), 'updated_on' => $lastUpdate['updated_on'] ?? null
    ];

    if ($method === 'GET') Response::success('Facility profile loaded', ['facility' => $facility, 'profile_update' => $profileUpdate]);

    $latitude = trim((string)($input['latitude'] ?? ''));
    $longitude = trim((string)($input['longitude'] ?? ''));
    if (!is_numeric($latitude) || (float)$latitude < -90 || (float)$latitude > 90) Response::validation(['latitude' => 'Latitude must be between -90 and 90.']);
    if (!is_numeric($longitude) || (float)$longitude < -180 || (float)$longitude > 180) Response::validation(['longitude' => 'Longitude must be between -180 and 180.']);

    // Keep the database row complete even though assessors may edit only GPS.
    $valuesByColumn = [
        'fac_id' => [$facId, 'i'], 'fac_name' => [$facility['fac_name'], 's'], 'NIN_no' => [$facility['nin_no'], 's'],
        'state_name' => [$facility['state_name'], 's'], 'division' => [$facility['division'], 's'], 'Dist_Name' => [$facility['district'], 's'],
        'Block_Name' => [$facility['block'], 's'], 'Health_facilty_type' => [$facility['fac_type_id'], 'i'],
        'state_id' => [$facility['state_id'], 'i'], 'state_code' => [$facility['state_id'], 'i'], 'division_id' => [$facility['division_id'], 'i'],
        'dist_id' => [$facility['dist_id'], 'i'], 'block_id' => [$facility['block_id'], 'i'], $latColumn => [$latitude, 's'], $lngColumn => [$longitude, 's']
    ];
    $fields = []; $params = []; $types = '';
    foreach ($valuesByColumn as $column => [$value, $type]) if (isset($columns[$column])) { $fields[] = $column; $params[] = $value; $types .= $type; }
    $set = array_filter($fields, fn($column) => $column !== 'fac_id');
    $sql = 'INSERT INTO facilities (`' . implode('`, `', $fields) . '`) VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($column) => "`{$column}` = VALUES(`{$column}`)", $set));
    $save = $con->prepare($sql);
    $save->bind_param($types, ...$params);
    $save->execute();
    $auditSave = $con->prepare("INSERT INTO assessor_facility_profile_update (fac_id, assessor_id, updated_on) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE assessor_id = VALUES(assessor_id), updated_on = CURRENT_TIMESTAMP");
    $auditSave->bind_param('ii', $facId, $assessorId); $auditSave->execute();
    $facility['latitude'] = $latitude; $facility['longitude'] = $longitude;
    Response::success('Facility coordinates updated successfully.', ['facility' => $facility, 'profile_update' => ['exists' => true, 'updated_by_other' => false]]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

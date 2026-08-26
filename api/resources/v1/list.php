<?php

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

$search = trim((string)($_GET['search'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$hasFacilityTypeFilter = isset($_GET['facility_type_id']) && $_GET['facility_type_id'] !== '';
$facilityTypeId = (int)($_GET['facility_type_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(5, (int)($_GET['per_page'] ?? 10)));
$allowedTypes = ['LETTER', 'FORM', 'DOCUMENT', 'GUIDELINE', 'OTHER'];
$masterFacilityTypes = json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/config/masters/facility_types.json'), true);
$masterFacilityTypes = is_array($masterFacilityTypes) ? $masterFacilityTypes : [];
$masterOtherTypeId = 0;
foreach ($masterFacilityTypes as $masterType) {
    if (strcasecmp((string)($masterType['facilities_type'] ?? ''), 'Others') === 0 || strcasecmp((string)($masterType['fac'] ?? ''), 'Others') === 0) {
        $masterOtherTypeId = (int)($masterType['fac_type_id'] ?? 0);
        break;
    }
}
if ($type !== '' && !in_array($type, $allowedTypes, true)) {
    Response::validation(['type' => 'Invalid resource type.']);
}

$sql = 'SELECT r.resource_id, r.title, r.resource_type, r.description, r.original_name, r.mime_type, r.file_size, r.download_count, r.uploaded_by, r.created_on, r.applicable_facility_type_id
        FROM resources r WHERE r.status = \'PUBLISHED\'';
$params = [];
$types = '';
if ($search !== '') {
    $sql .= ' AND (title LIKE ? OR description LIKE ? OR original_name LIKE ?)';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
    $types = 'sss';
}
if ($type !== '') {
    $sql .= ' AND resource_type = ?';
    $params[] = $type;
    $types .= 's';
}
if ($hasFacilityTypeFilter) {
    $sql .= $facilityTypeId === $masterOtherTypeId
        ? ' AND (r.applicable_facility_type_id = ? OR r.applicable_facility_type_id IS NULL)'
        : ' AND r.applicable_facility_type_id = ?';
    $params[] = $facilityTypeId;
    $types .= 'i';
}
$countSql = 'SELECT COUNT(*) AS total FROM (' . $sql . ') AS resource_rows';
$countStmt = $con->prepare($countSql);
if ($types !== '') $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int)(($countStmt->get_result()->fetch_assoc() ?: [])['total'] ?? 0);
$pageCount = max(1, (int)ceil($total / $perPage));
$page = min($page, $pageCount);
$offset = ($page - 1) * $perPage;
$sql .= ' ORDER BY created_on DESC, resource_id DESC LIMIT ?, ?';
$params[] = $offset;
$params[] = $perPage;
$types .= 'ii';
$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($rows as &$row) {
    $row['download_url'] = '/api/resources/v1/download.php?id=' . (int)$row['resource_id'];
    $row['preview_url'] = in_array((string)($row['mime_type'] ?? ''), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
        ? '/api/resources/v1/preview.php?id=' . (int)$row['resource_id']
        : null;
}
unset($row);

$rawTypeRows = $masterFacilityTypes;
$typeRows = [];
foreach (is_array($rawTypeRows) ? $rawTypeRows : [] as $type) {
    $id = (int)($type['fac_type_id'] ?? 0);
    $name = trim((string)($type['facilities_type'] ?? ''));
    if ($id <= 0 || $name === '') continue;
    $typeRows[] = [
        'fac_type_id' => $id,
        'fac_type_name' => $name,
        'fac_type_code' => (string)($type['fac'] ?? $name)
    ];
}
$typeById = [];
foreach ($typeRows as $facilityType) $typeById[(int)$facilityType['fac_type_id']] = $facilityType;
$otherTypeId = $masterOtherTypeId;
if ($otherTypeId === 0) {
    $typeRows[] = ['fac_type_id' => 0, 'fac_type_name' => 'Others', 'fac_type_code' => 'Others'];
    $typeById[0] = end($typeRows);
}
foreach ($rows as &$row) {
    $facilityType = $typeById[(int)($row['applicable_facility_type_id'] ?? 0)] ?? ($typeById[$otherTypeId] ?? $typeById[0]);
    $row['fac_type_name'] = $facilityType['fac_type_name'];
    $row['fac_type_code'] = $facilityType['fac_type_code'];
}
unset($row);
$countResult = $con->query("SELECT applicable_facility_type_id, COUNT(*) AS total FROM resources WHERE status = 'PUBLISHED' GROUP BY applicable_facility_type_id");
$counts = ['ALL' => 0];
while ($count = $countResult->fetch_assoc()) { $key = (int)($count['applicable_facility_type_id'] ?? 0); if (!isset($typeById[$key])) $key = $otherTypeId; $counts[$key] = (int)($counts[$key] ?? 0) + (int)$count['total']; $counts['ALL'] += (int)$count['total']; }

Response::success('Resources loaded.', [
    'resources' => $rows,
    'can_manage' => SessionManager::roleId() === 9,
    'types' => $allowedTypes,
    'facility_types' => $typeRows,
    'counts' => $counts,
    'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'page_count' => $pageCount]
]);

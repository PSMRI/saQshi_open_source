<?php
require_once __DIR__ . '/_management_bootstrap.php';
Security::requireMethod('GET');
$rows = [];
// JSON is the authoritative School/Facility master for bulk mapping.
$path = __DIR__ . '/../../config/masters/facilities.json';
$master = is_file($path) ? (json_decode((string)file_get_contents($path), true) ?: []) : [];
foreach ($master as $state) foreach (($state['divisions'] ?? []) as $division) foreach (($division['districts'] ?? []) as $district) foreach (($district['blocks'] ?? []) as $block) foreach (($block['facilities'] ?? []) as $facility) {
    $rows[] = [
        'fac_id' => $facility['fac_id'] ?? '',
        'fac_name' => $facility['fac_name'] ?? '',
        'NIN_no' => $facility['NIN_no'] ?? $facility['nin_no'] ?? '',
        'Dist_Name' => $facility['Dist_Name'] ?? $district['dist_name'] ?? '',
        'Block_Name' => $facility['Block_Name'] ?? $block['block_name'] ?? ''
    ];
}
if (!$rows) {
    $result = $con->query('SELECT fac_id, fac_name, NIN_no, Dist_Name, Block_Name FROM facilities ORDER BY fac_name');
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
usort($rows, fn($a, $b) => strcasecmp((string)$a['fac_name'], (string)$b['fac_name']));
Response::success('School/Facility reference loaded', ['rows' => $rows]);

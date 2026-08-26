<?php

/** Synchronize the facility-type database master from the approved JSON master. */
require_once __DIR__ . '/../assets/conn/db.php';

$path = __DIR__ . '/../config/masters/facility_types.json';
$types = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
if (!is_array($types)) {
    fwrite(STDERR, "Unable to read facility_types.json\n");
    exit(1);
}

$con->begin_transaction();
try {
    $stmt = $con->prepare('INSERT INTO facilities_type (fac_type_id, fac_type_name, fac_type_code, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE fac_type_name = VALUES(fac_type_name), fac_type_code = VALUES(fac_type_code), is_active = 1');
    $count = 0;
    foreach ($types as $type) {
        $id = (int)($type['fac_type_id'] ?? 0);
        $name = trim((string)($type['facilities_type'] ?? ''));
        $code = trim((string)($type['fac'] ?? $name));
        if ($id <= 0 || $name === '') continue;
        $stmt->bind_param('iss', $id, $name, $code);
        $stmt->execute();
        $count++;
    }
    $con->commit();
    echo "Synchronized {$count} facility types from facility_types.json\n";
} catch (Throwable $error) {
    $con->rollback();
    fwrite(STDERR, "Facility-type sync failed: {$error->getMessage()}\n");
    exit(1);
}

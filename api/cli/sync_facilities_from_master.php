<?php
/** Sync the trusted facilities.json master into the database facilities table. */
require_once __DIR__ . '/../assets/conn/db.php';

$path = __DIR__ . '/../config/masters/facilities.json';
$master = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
if (!is_array($master)) {
    throw new RuntimeException('Unable to read facilities master JSON.');
}

$sql = "INSERT INTO facilities
    (fac_id, state_name, division, Dist_Name, Block_Name, fac_name, Health_facilty_type,
     state_id, division_id, dist_id, block_id, NIN_no, lat, longit, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
      state_name=VALUES(state_name), division=VALUES(division), Dist_Name=VALUES(Dist_Name),
      Block_Name=VALUES(Block_Name), fac_name=VALUES(fac_name), Health_facilty_type=VALUES(Health_facilty_type),
      state_id=VALUES(state_id), division_id=VALUES(division_id), dist_id=VALUES(dist_id), block_id=VALUES(block_id),
      lat=VALUES(lat), longit=VALUES(longit), is_active=1";
$stmt = $con->prepare($sql);
if (!$stmt) throw new RuntimeException($con->error);

$count = 0;
$con->begin_transaction();
try {
    foreach ($master as $state) {
        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {
                        $facId = (int)($facility['fac_id'] ?? 0);
                        $nin = (string)($facility['nin_no'] ?? '');
                        if ($facId <= 0 || $nin === '') continue;
                        $stateName = (string)($state['state_name'] ?? '');
                        $divisionName = (string)($division['division_name'] ?? '');
                        $districtName = (string)($district['dist_name'] ?? '');
                        $blockName = (string)($block['block_name'] ?? '');
                        $name = (string)($facility['fac_name'] ?? '');
                        $type = (int)($facility['fac_type_id'] ?? 10);
                        $stateId = (int)($state['state_id'] ?? 0);
                        $divisionId = (int)($division['division_id'] ?? 0);
                        $districtId = (int)($district['dist_id'] ?? 0);
                        $blockId = (int)($block['block_id'] ?? 0);
                        $latitude = isset($facility['latitude']) ? (float)$facility['latitude'] : null;
                        $longitude = isset($facility['longitude']) ? (float)$facility['longitude'] : null;
                        $stmt->bind_param('isssssiiiiiidd', $facId, $stateName, $divisionName, $districtName, $blockName, $name,
                            $type, $stateId, $divisionId, $districtId, $blockId, $nin, $latitude, $longitude);
                        if (!$stmt->execute()) throw new RuntimeException($stmt->error);
                        $count++;
                    }
                }
            }
        }
    }
    $con->commit();
    echo "Synced {$count} facilities from facilities.json\n";
} catch (Throwable $e) {
    $con->rollback();
    throw $e;
}

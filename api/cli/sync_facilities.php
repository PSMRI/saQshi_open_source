<?php

/** Synchronize facility master records from the approved nested facilities JSON. */
require_once __DIR__ . '/../assets/conn/db.php';

$path = __DIR__ . '/../config/masters/facilities.json';
$states = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
if (!is_array($states)) {
    fwrite(STDERR, "Unable to read facilities.json\n");
    exit(1);
}

$sql = 'INSERT INTO facilities (fac_id, state_name, Dist_Name, Block_Name, fac_name, Health_facilty_type, block_id, dist_id, state_code, division_id, division, NIN_no, state_id, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE state_name = VALUES(state_name), Dist_Name = VALUES(Dist_Name), Block_Name = VALUES(Block_Name), fac_name = VALUES(fac_name), Health_facilty_type = VALUES(Health_facilty_type), block_id = VALUES(block_id), dist_id = VALUES(dist_id), state_code = VALUES(state_code), division_id = VALUES(division_id), division = VALUES(division), state_id = VALUES(state_id), is_active = 1';
$stmt = $con->prepare($sql);
if (!$stmt) {
    fwrite(STDERR, "Unable to prepare facility sync: {$con->error}\n");
    exit(1);
}

$inserted = 0;
$updated = 0;
$unchanged = 0;
$skipped = 0;
$con->begin_transaction();

try {
    foreach ($states as $state) {
        $stateId = (int)($state['state_id'] ?? 0);
        $stateName = trim((string)($state['state_name'] ?? ''));
        foreach (($state['divisions'] ?? []) as $divisionRow) {
            $divisionId = (int)($divisionRow['division_id'] ?? 0);
            $divisionName = trim((string)($divisionRow['division_name'] ?? ''));
            foreach (($divisionRow['districts'] ?? []) as $district) {
                $districtId = (int)($district['dist_id'] ?? 0);
                $districtName = trim((string)($district['dist_name'] ?? ''));
                foreach (($district['blocks'] ?? []) as $block) {
                    $blockId = (int)($block['block_id'] ?? 0);
                    $blockName = trim((string)($block['block_name'] ?? ''));
                    foreach (($block['facilities'] ?? []) as $facility) {
                        $facilityId = (int)($facility['fac_id'] ?? 0);
                        $nin = (int)($facility['nin_no'] ?? 0);
                        $facilityName = trim((string)($facility['fac_name'] ?? ''));
                        $facilityTypeId = (int)($facility['fac_type_id'] ?? 0);
                        if ($facilityId <= 0 || $nin <= 0 || $facilityName === '') {
                            $skipped++;
                            continue;
                        }
                        $stateCode = $stateId;
                        $stmt->bind_param('issssiiiiisii', $facilityId, $stateName, $districtName, $blockName, $facilityName, $facilityTypeId, $blockId, $districtId, $stateCode, $divisionId, $divisionName, $nin, $stateId);
                        $stmt->execute();
                        if ($stmt->affected_rows === 1) $inserted++;
                        elseif ($stmt->affected_rows === 2) $updated++;
                        else $unchanged++;
                    }
                }
            }
        }
    }
    $con->commit();
    echo "Facilities sync complete: inserted={$inserted}, updated={$updated}, unchanged={$unchanged}, skipped={$skipped}\n";
} catch (Throwable $error) {
    $con->rollback();
    fwrite(STDERR, "Facility sync failed; no facility records were changed: {$error->getMessage()}\n");
    exit(1);
}

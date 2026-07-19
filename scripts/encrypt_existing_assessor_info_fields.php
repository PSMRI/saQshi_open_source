<?php

/**
 * Encrypt existing plaintext assessment_assessor_info personal fields.
 *
 * Usage:
 *   php scripts/encrypt_existing_assessor_info_fields.php
 *
 * This script is idempotent. Values already starting with enc:v1: are skipped.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../api/assets/conn/db.php';
require_once __DIR__ . '/../api/core/Crypto.php';

$fields = [
    'assessor_name',
    'assessor_mobile',
    'assessor_email',
    'assessee_name',
    'assessee_mobile',
    'assessee_email'
];

$selectSql = '
    SELECT id, assessor_name, assessor_mobile, assessor_email, assessee_name, assessee_mobile, assessee_email
    FROM assessment_assessor_info
';
$result = $con->query($selectSql);

if (!$result) {
    fwrite(STDERR, "Unable to read assessment_assessor_info rows.\n");
    exit(1);
}

$updateSql = '
    UPDATE assessment_assessor_info
    SET assessor_name = ?,
        assessor_mobile = ?,
        assessor_email = ?,
        assessee_name = ?,
        assessee_mobile = ?,
        assessee_email = ?
    WHERE id = ?
    LIMIT 1
';
$stmt = $con->prepare($updateSql);

if (!$stmt) {
    fwrite(STDERR, "Unable to prepare assessment_assessor_info update.\n");
    exit(1);
}

$checked = 0;
$updated = 0;

while ($row = $result->fetch_assoc()) {
    $checked++;
    $encrypted = [];
    $needsUpdate = false;

    foreach ($fields as $field) {
        $value = (string)($row[$field] ?? '');
        if (Crypto::needsEncryption($value)) {
            $needsUpdate = true;
            $encrypted[$field] = Crypto::encrypt($value);
        } else {
            $encrypted[$field] = $value;
        }
    }

    if (!$needsUpdate) {
        continue;
    }

    $id = (int)$row['id'];
    $stmt->bind_param(
        'ssssssi',
        $encrypted['assessor_name'],
        $encrypted['assessor_mobile'],
        $encrypted['assessor_email'],
        $encrypted['assessee_name'],
        $encrypted['assessee_mobile'],
        $encrypted['assessee_email'],
        $id
    );

    if (!$stmt->execute()) {
        fwrite(STDERR, "Unable to update assessor info ID {$id}.\n");
        exit(1);
    }

    $updated++;
}

echo "Checked assessor info rows: {$checked}\n";
echo "Encrypted/updated assessor info rows: {$updated}\n";
echo "Done.\n";

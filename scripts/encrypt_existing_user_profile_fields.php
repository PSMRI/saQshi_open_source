<?php

/**
 * Encrypt existing plaintext s_user profile fields.
 *
 * Usage:
 *   php scripts/encrypt_existing_user_profile_fields.php
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

$fields = ['f_name', 'm_name', 'l_name', 'mail_id', 'mob_no'];
$selectSql = 'SELECT u_id, f_name, m_name, l_name, mail_id, mob_no FROM s_user';
$result = $con->query($selectSql);

if (!$result) {
    fwrite(STDERR, "Unable to read s_user rows.\n");
    exit(1);
}

$updateSql = '
    UPDATE s_user
    SET f_name = ?, m_name = ?, l_name = ?, mail_id = ?, mob_no = ?
    WHERE u_id = ?
    LIMIT 1
';
$stmt = $con->prepare($updateSql);

if (!$stmt) {
    fwrite(STDERR, "Unable to prepare s_user update.\n");
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

    $userId = (int)$row['u_id'];
    $stmt->bind_param(
        'sssssi',
        $encrypted['f_name'],
        $encrypted['m_name'],
        $encrypted['l_name'],
        $encrypted['mail_id'],
        $encrypted['mob_no'],
        $userId
    );

    if (!$stmt->execute()) {
        fwrite(STDERR, "Unable to update user ID {$userId}.\n");
        exit(1);
    }

    $updated++;
}

echo "Checked users: {$checked}\n";
echo "Encrypted/updated users: {$updated}\n";
echo "Done.\n";

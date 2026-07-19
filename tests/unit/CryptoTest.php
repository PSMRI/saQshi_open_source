<?php

declare(strict_types=1);

require_once __DIR__ . '/../../api/core/Crypto.php';

sqTest('Crypto encrypts and decrypts profile values', function (): void {
    $plain = 'Facility User 98765';
    $encrypted = Crypto::encrypt($plain);

    sqAssertTrue($encrypted !== $plain, 'Encrypted value should differ from plain text.');
    sqAssertTrue(Crypto::isEncrypted($encrypted), 'Encrypted value should use SaQshi encryption prefix.');
    sqAssertSame($plain, Crypto::decrypt($encrypted), 'Encrypted value should decrypt to original text.');
});

sqTest('Crypto leaves already encrypted values unchanged', function (): void {
    $encrypted = Crypto::encrypt('sample@example.org');
    sqAssertSame($encrypted, Crypto::encrypt($encrypted), 'Encrypting an encrypted value should be idempotent.');
});

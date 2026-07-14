<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Login Password Transport Encryption
 * LoginCrypto.php
 * Version 1.0.0 | Updated 2026-07-10
 * ==========================================================
 */

class LoginCrypto
{
    private static function opensslConfigPath(): ?string
    {
        if (class_exists('Env')) {
            $configured = Env::get('OPENSSL_CONF', '');

            if ($configured !== '' && is_file($configured)) {
                return $configured;
            }
        }

        $candidates = [
            'C:/php/extras/ssl/openssl.cnf',
            'C:/cert/openssl.cnf',
            'C:/Program Files/Git/mingw64/etc/ssl/openssl.cnf',
            'C:/Program Files/Git/usr/ssl/openssl.cnf'
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function opensslErrors(): string
    {
        $errors = [];

        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return implode(' | ', $errors);
    }

    private static function keyDir(): string
    {
        return dirname(__DIR__) . '/storage/keys';
    }

    private static function privateKeyPath(): string
    {
        return self::keyDir() . '/login_private.pem';
    }

    private static function publicKeyPath(): string
    {
        return self::keyDir() . '/login_public.pem';
    }

    public static function publicKey(): string
    {
        self::ensureKeys();
        return (string)file_get_contents(self::publicKeyPath());
    }

    public static function decryptPassword(string $encryptedBase64): string
    {
        self::ensureKeys();

        $cipherText = base64_decode($encryptedBase64, true);

        if ($cipherText === false || $cipherText === '') {
            throw new InvalidArgumentException('Invalid encrypted password payload');
        }

        $privateKey = openssl_pkey_get_private((string)file_get_contents(self::privateKeyPath()));

        if (!$privateKey) {
            throw new RuntimeException('Login private key could not be loaded');
        }

        $plainText = '';
        $ok = openssl_private_decrypt($cipherText, $plainText, $privateKey, OPENSSL_PKCS1_OAEP_PADDING);

        if (!$ok) {
            throw new InvalidArgumentException('Encrypted password could not be decrypted');
        }

        return $plainText;
    }

    private static function ensureKeys(): void
    {
        $dir = self::keyDir();

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Login key directory could not be created');
        }

        if (is_file(self::privateKeyPath()) && is_file(self::publicKeyPath())) {
            return;
        }

        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048
        ];

        $configPath = self::opensslConfigPath();

        if ($configPath) {
            $options['config'] = $configPath;
        }

        $resource = openssl_pkey_new($options);

        if (!$resource) {
            $details = self::opensslErrors();
            throw new RuntimeException('Login key pair could not be generated' . ($details ? ': ' . $details : ''));
        }

        $privateKey = '';
        $exportOptions = $configPath ? ['config' => $configPath] : null;
        $exported = $exportOptions
            ? openssl_pkey_export($resource, $privateKey, null, $exportOptions)
            : openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        $publicKey = (string)($details['key'] ?? '');

        if (!$exported || $privateKey === '' || $publicKey === '') {
            $details = self::opensslErrors();
            throw new RuntimeException('Login key pair export failed' . ($details ? ': ' . $details : ''));
        }

        file_put_contents(self::privateKeyPath(), $privateKey, LOCK_EX);
        file_put_contents(self::publicKeyPath(), $publicKey, LOCK_EX);
    }
}

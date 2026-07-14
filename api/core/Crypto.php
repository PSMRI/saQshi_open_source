<?php

/**
 * Crypto.php
 * -------------------------------------------------------
 * Small field encryption helper for sensitive profile data.
 *
 * Format:
 * enc:v1:<base64(mode + nonce/iv + tag + ciphertext)>
 * -------------------------------------------------------
 */

class Crypto
{
    private const PREFIX = 'enc:v1:';
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const FALLBACK_NONCE_LENGTH = 16;
    private const FALLBACK_TAG_LENGTH = 32;

    public static function encrypt(?string $plainText): string
    {
        $plainText = (string)($plainText ?? '');

        if ($plainText === '' || self::isEncrypted($plainText)) {
            return $plainText;
        }

        if (!function_exists('openssl_encrypt')) {
            return self::encryptFallback($plainText);
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $cipherText = openssl_encrypt(
            $plainText,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($cipherText === false) {
            throw new RuntimeException('Unable to encrypt field value');
        }

        return self::PREFIX . base64_encode('G1' . $iv . $tag . $cipherText);
    }

    public static function decrypt(?string $encryptedText): string
    {
        $encryptedText = (string)($encryptedText ?? '');

        if ($encryptedText === '' || !self::isEncrypted($encryptedText)) {
            return $encryptedText;
        }

        $payload = base64_decode(substr($encryptedText, strlen(self::PREFIX)), true);

        if ($payload === false || strlen($payload) <= 2) {
            return $encryptedText;
        }

        $mode = substr($payload, 0, 2);

        if ($mode === 'H1') {
            return self::decryptFallbackPayload($payload, $encryptedText);
        }

        if ($mode === 'G1') {
            if (!function_exists('openssl_decrypt')) {
                return $encryptedText;
            }

            $payload = substr($payload, 2);
        }

        if (strlen($payload) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return $encryptedText;
        }

        $iv = substr($payload, 0, self::IV_LENGTH);
        $tag = substr($payload, self::IV_LENGTH, self::TAG_LENGTH);
        $cipherText = substr($payload, self::IV_LENGTH + self::TAG_LENGTH);
        $plainText = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plainText === false ? $encryptedText : $plainText;
    }

    public static function decryptFields(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = self::decrypt((string)$row[$field]);
            }
        }

        return $row;
    }

    public static function isEncrypted(?string $value): bool
    {
        return str_starts_with((string)$value, self::PREFIX);
    }

    public static function needsEncryption(?string $value): bool
    {
        $value = (string)($value ?? '');
        return $value !== '' && !self::isEncrypted($value);
    }

    private static function key(): string
    {
        $key = getenv('SAQSHI_FIELD_ENCRYPTION_KEY') ?: '';

        if ($key === '') {
            $key = 'SaQshi field encryption key v1 - change with SAQSHI_FIELD_ENCRYPTION_KEY';
        }

        return hash('sha256', $key, true);
    }

    private static function encryptFallback(string $plainText): string
    {
        $nonce = random_bytes(self::FALLBACK_NONCE_LENGTH);
        $encKey = hash_hmac('sha256', 'enc', self::key(), true);
        $macKey = hash_hmac('sha256', 'mac', self::key(), true);
        $cipherText = self::xorWithKeystream($plainText, $nonce, $encKey);
        $tag = hash_hmac('sha256', $nonce . $cipherText, $macKey, true);

        return self::PREFIX . base64_encode('H1' . $nonce . $tag . $cipherText);
    }

    private static function decryptFallbackPayload(string $payload, string $fallback): string
    {
        $payload = substr($payload, 2);

        if (strlen($payload) <= self::FALLBACK_NONCE_LENGTH + self::FALLBACK_TAG_LENGTH) {
            return $fallback;
        }

        $nonce = substr($payload, 0, self::FALLBACK_NONCE_LENGTH);
        $tag = substr($payload, self::FALLBACK_NONCE_LENGTH, self::FALLBACK_TAG_LENGTH);
        $cipherText = substr($payload, self::FALLBACK_NONCE_LENGTH + self::FALLBACK_TAG_LENGTH);
        $encKey = hash_hmac('sha256', 'enc', self::key(), true);
        $macKey = hash_hmac('sha256', 'mac', self::key(), true);
        $expectedTag = hash_hmac('sha256', $nonce . $cipherText, $macKey, true);

        if (!hash_equals($expectedTag, $tag)) {
            return $fallback;
        }

        return self::xorWithKeystream($cipherText, $nonce, $encKey);
    }

    private static function xorWithKeystream(string $input, string $nonce, string $encKey): string
    {
        $output = '';
        $length = strlen($input);
        $offset = 0;
        $counter = 0;

        while ($offset < $length) {
            $block = hash_hmac('sha256', $nonce . pack('N', $counter), $encKey, true);
            $slice = substr($input, $offset, strlen($block));
            $output .= $slice ^ substr($block, 0, strlen($slice));
            $offset += strlen($slice);
            $counter++;
        }

        return $output;
    }
}

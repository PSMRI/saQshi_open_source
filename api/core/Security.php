<?php

/**
 * Security.php
 * -------------------------------------------------------
 * Centralized security helper for SaQshi APIs.
 *
 * Handles:
 * - Security headers
 * - JSON input parsing
 * - HTTP method enforcement
 * - Basic input sanitization helpers
 * - Secure error-safe response helpers
 * -------------------------------------------------------
 */

class Security
{
    /**
     * Apply secure HTTP headers.
     */
    public static function headers(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0');

        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "base-uri 'self'; " .
            "frame-ancestors 'none'; " .
            "form-action 'self'; " .
            "object-src 'none';"
        );
    }

    /**
     * Allow only specific HTTP method.
     */
    public static function requireMethod(string $method): void
    {
        $currentMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $requiredMethod = strtoupper($method);

        if ($currentMethod !== $requiredMethod) {
            http_response_code(405);

            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed. Required method: ' . $requiredMethod,
                'data' => null,
                'errors' => null,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
    }

    /**
     * Allow one method from list.
     */
    public static function requireAnyMethod(array $methods): void
    {
        $currentMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $allowed = array_map(
            fn($m) => strtoupper((string)$m),
            $methods
        );

        if (!in_array($currentMethod, $allowed, true)) {
            http_response_code(405);

            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed',
                'allowed_methods' => $allowed,
                'data' => null,
                'errors' => null,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
    }

    /**
     * Parse JSON body safely.
     */
    public static function jsonInput(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid JSON request body',
                'data' => null,
                'errors' => [
                    'json' => json_last_error_msg()
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }

        return $data;
    }

    /**
     * Required field validator.
     */
    public static function requireFields(array $data, array $fields): void
    {
        $errors = [];

        foreach ($fields as $field) {
            if (
                !array_key_exists($field, $data) ||
                $data[$field] === null ||
                $data[$field] === ''
            ) {
                $errors[$field] = $field . ' is required';
            }
        }

        if (!empty($errors)) {
            http_response_code(422);

            echo json_encode([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => null,
                'errors' => $errors,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
    }

    /**
     * Sanitize string.
     */
    public static function cleanString(mixed $value): string
    {
        $value = trim((string)$value);
        $value = strip_tags($value);

        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * Convert to safe integer.
     */
    public static function int(mixed $value): int
    {
        return filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default' => 0
                ]
            ]
        );
    }

    /**
     * Safe boolean.
     */
    public static function bool(mixed $value): bool
    {
        return filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? false;
    }

    /**
     * Validate email.
     */
    public static function email(mixed $value): ?string
    {
        $email = trim((string)$value);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return strtolower($email);
    }

    /**
     * Generate secure random token.
     */
    public static function token(int $length = 32): string
    {
        return bin2hex(
            random_bytes($length)
        );
    }

    /**
     * Constant-time compare.
     */
    public static function hashEquals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }

    /**
     * Generic safe error response.
     */
    public static function fail(
        string $message,
        int $statusCode = 400,
        mixed $errors = null
    ): never {
        http_response_code($statusCode);

        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    /**
     * Generic success response.
     */
    public static function success(
        string $message,
        mixed $data = null,
        int $statusCode = 200
    ): never {
        http_response_code($statusCode);

        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}
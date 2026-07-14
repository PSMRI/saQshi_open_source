<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Friendly API Error Handler
 * ErrorHandler.php
 * Version 1.0.0 | Updated 2026-07-10
 * ==========================================================
 */

class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function requestId(): string
    {
        static $requestId = null;

        if ($requestId === null) {
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
        }

        return $requestId;
    }

    public static function friendlyMessage(): string
    {
        return 'Something went wrong while processing your request. Please try again. If the issue continues, contact support with Request ID: ' . self::requestId();
    }

    public static function log(Throwable|string $error, array $context = []): void
    {
        $message = $error instanceof Throwable
            ? $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine()
            : $error;

        error_log('[SaQshi API Error][' . self::requestId() . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES));
    }

    public static function handleException(Throwable $e): void
    {
        self::log($e);
        self::sendFriendly(500);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        self::log($message, [
            'severity' => $severity,
            'file' => $file,
            'line' => $line
        ]);

        self::sendFriendly(500);
        return true;
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if (!$error || !in_array((int)$error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        self::log($error['message'] ?? 'Fatal error', [
            'type' => $error['type'] ?? null,
            'file' => $error['file'] ?? '',
            'line' => $error['line'] ?? 0
        ]);

        self::sendFriendly(500);
    }

    public static function sendFriendly(int $httpCode = 500): void
    {
        if (headers_sent()) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);

        echo json_encode([
            'status' => 'error',
            'message' => self::friendlyMessage(),
            'data' => null,
            'errors' => [
                'request_id' => self::requestId()
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }
}

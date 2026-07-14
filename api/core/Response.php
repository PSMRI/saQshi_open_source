<?php

/**
 * Response.php
 * SaQshi Standard JSON Response Handler
 */

class Response
{
    private static function send(
        string $status,
        string $message,
        mixed $data = null,
        mixed $errors = null,
        int $httpCode = 200
    ): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($httpCode);
        }

        echo json_encode([
            'status'    => $status,
            'message'   => $message,
            'data'      => $data,
            'errors'    => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function success(
        string $message = 'Success',
        mixed $data = null,
        int $httpCode = 200
    ): void {
        self::send('success', $message, $data, null, $httpCode);
    }

    public static function created(
        string $message = 'Created successfully',
        mixed $data = null
    ): void {
        self::send('success', $message, $data, null, 201);
    }

    public static function error(
        string $message = 'Request failed',
        mixed $errors = null,
        int $httpCode = 400
    ): void {
        self::send('error', $message, null, $errors, $httpCode);
    }

    public static function validation(
        array $errors,
        string $message = 'Validation failed'
    ): void {
        self::send('error', $message, null, $errors, 422);
    }

    public static function unauthorized(
        string $message = 'Unauthorized'
    ): void {
        self::send('error', $message, null, null, 401);
    }

    public static function forbidden(
        string $message = 'Forbidden'
    ): void {
        self::send('error', $message, null, null, 403);
    }

    public static function notFound(
        string $message = 'Resource not found'
    ): void {
        self::send('error', $message, null, null, 404);
    }

    public static function serverError(
        string $message = 'Internal server error'
    ): void {
        if (class_exists('ErrorHandler')) {
            ErrorHandler::log($message);
            self::send('error', ErrorHandler::friendlyMessage(), null, [
                'request_id' => ErrorHandler::requestId()
            ], 500);
        }

        self::send('error', 'Something went wrong while processing your request. Please try again.', null, null, 500);
    }
}

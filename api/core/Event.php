<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Event Abstraction Layer
 * Event.php
 * Version 1.0.0 | Updated 2026-07-10
 * ==========================================================
 */

class Event
{
    /** @var array<string, array<int, callable>> */
    private static array $listeners = [];
    private static bool $requestTraceStarted = false;
    private static float $requestStartedAt = 0.0;

    /**
     * Register a local listener for an event name.
     *
     * This keeps today's deployment simple while preserving a future path
     * to publish the same events to Kafka or another broker.
     */
    public static function listen(string $eventName, callable $listener): void
    {
        $eventName = trim($eventName);

        if ($eventName === '') {
            return;
        }

        self::$listeners[$eventName] ??= [];
        self::$listeners[$eventName][] = $listener;
    }

    /**
     * Dispatch an application event.
     *
     * Current implementation:
     * - appends JSON lines to api/storage/events/events-YYYY-MM-DD.log
     * - executes local PHP listeners registered with Event::listen()
     *
     * Future Kafka migration:
     * - replace or extend this method to publish $event to Kafka
     * - callers keep using Event::dispatch("event.name", $payload)
     */
    public static function dispatch(string $eventName, array $payload = [], array $meta = []): void
    {
        $eventName = trim($eventName);

        if ($eventName === '') {
            return;
        }

        $event = [
            'event' => $eventName,
            'payload' => $payload,
            'meta' => array_merge(self::defaultMeta(), $meta),
            'occurred_at' => date('c')
        ];

        self::writeLog($event);

        foreach (self::$listeners[$eventName] ?? [] as $listener) {
            try {
                $listener($event);
            } catch (Throwable $e) {
                error_log('SaQshi event listener failed [' . $eventName . ']: ' . $e->getMessage());
            }
        }
    }

    /**
     * Enable automatic API lifecycle events for every endpoint that loads
     * bootstrap.php.
     *
     * Events:
     * - api.request.started
     * - api.request.finished
     */
    public static function traceRequest(): void
    {
        if (self::$requestTraceStarted) {
            return;
        }

        self::$requestTraceStarted = true;
        self::$requestStartedAt = microtime(true);

        self::dispatch('api.request.started', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'path' => $_SERVER['REQUEST_URI'] ?? null,
            'query' => $_SERVER['QUERY_STRING'] ?? null
        ]);

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            $durationMs = self::$requestStartedAt > 0
                ? round((microtime(true) - self::$requestStartedAt) * 1000, 2)
                : null;

            self::dispatch('api.request.finished', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'path' => $_SERVER['REQUEST_URI'] ?? null,
                'http_status' => http_response_code(),
                'duration_ms' => $durationMs,
                'fatal_error' => $error && in_array((int)$error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
                    ? [
                        'type' => (int)$error['type'],
                        'message' => $error['message'] ?? '',
                        'file' => $error['file'] ?? '',
                        'line' => $error['line'] ?? 0
                    ]
                    : null
            ]);
        });
    }

    /**
     * Handles default meta processing for this API workflow.
     */
    private static function defaultMeta(): array
    {
        return [
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8)),
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'path' => $_SERVER['REQUEST_URI'] ?? null,
            'user_id' => self::safeSessionValue('userId'),
            'facility_id' => self::safeSessionValue('facilityId')
        ];
    }

    /**
     * Handles safe session value processing for this API workflow.
     */
    private static function safeSessionValue(string $method): ?int
    {
        if (!class_exists('SessionManager') || !method_exists('SessionManager', $method)) {
            return null;
        }

        try {
            $value = SessionManager::$method();
            return is_numeric($value) ? (int)$value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Handles write log processing for this API workflow.
     */
    private static function writeLog(array $event): void
    {
        $dir = dirname(__DIR__) . '/storage/events';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('SaQshi event log directory could not be created: ' . $dir);
            return;
        }

        $file = $dir . '/events-' . date('Y-m-d') . '.log';
        $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('SaQshi event log write failed: ' . $file);
        }
    }
}

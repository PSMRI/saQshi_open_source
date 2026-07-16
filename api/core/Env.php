<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Environment Loader
 * Env.php
 * Version 1.0.0 | Updated 2026-07-10
 * ==========================================================
 */

class Env
{
    private static bool $loaded = false;

    /**
     * Handles load processing for this API workflow.
     */
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;
        $path ??= dirname(__DIR__, 2) . '/.env';

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * Handles get processing for this API workflow.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();

        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}

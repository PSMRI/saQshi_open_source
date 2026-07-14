<?php

/**
 * -------------------------------------------------------
 * ConfigLoader.php
 * -------------------------------------------------------
 * SaQshi Open Source Framework
 * Loads JSON-based configuration files for frameworks,
 * tenants, UI, dashboards, and reports.
 * -------------------------------------------------------
 */

class ConfigLoader
{
    private static string $basePath = __DIR__ . '/../config';

    /**
     * Load any JSON config file by path.
     *
     * Example:
     * ConfigLoader::loadJson('frameworks/sample-framework.json');
     */
    public static function loadJson(string $relativePath): array
    {
        $relativePath = trim($relativePath, '/');

        $filePath = realpath(self::$basePath . '/' . $relativePath);

        $baseRealPath = realpath(self::$basePath);

        if ($filePath === false || $baseRealPath === false) {
            throw new Exception("Configuration file not found: {$relativePath}");
        }

        if (strpos($filePath, $baseRealPath) !== 0) {
            throw new Exception("Invalid configuration path");
        }

        if (!is_readable($filePath)) {
            throw new Exception("Configuration file is not readable: {$relativePath}");
        }

        $content = file_get_contents($filePath);

        if ($content === false || trim($content) === '') {
            throw new Exception("Configuration file is empty: {$relativePath}");
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(
                "Invalid JSON in {$relativePath}: " . json_last_error_msg()
            );
        }

        return $data;
    }

    /**
     * Load framework config.
     *
     * Example:
     * ConfigLoader::loadFramework('sample-framework');
     * ConfigLoader::loadFramework('nqas');
     */
    public static function loadFramework(string $frameworkCode): array
    {
        $frameworkCode = self::sanitizeCode($frameworkCode);

        return self::loadJson(
            "frameworks/{$frameworkCode}.json"
        );
    }

    /**
     * Load tenant config.
     *
     * Example:
     * ConfigLoader::loadTenant('bihar');
     */
    public static function loadTenant(string $tenantCode): array
    {
        $tenantCode = self::sanitizeCode($tenantCode);

        return self::loadJson(
            "tenants/{$tenantCode}.json"
        );
    }

    /**
     * Load UI config.
     *
     * Example:
     * ConfigLoader::loadUi('assessment-form');
     */
    public static function loadUi(string $uiCode): array
    {
        $uiCode = self::sanitizeCode($uiCode);

        return self::loadJson(
            "ui/{$uiCode}.json"
        );
    }

    /**
     * Load dashboard config.
     *
     * Example:
     * ConfigLoader::loadDashboard('default');
     */
    public static function loadDashboard(string $dashboardCode): array
    {
        $dashboardCode = self::sanitizeCode($dashboardCode);

        return self::loadJson(
            "dashboards/{$dashboardCode}.json"
        );
    }

    /**
     * Load report config.
     *
     * Example:
     * ConfigLoader::loadReport('facility-score');
     */
    public static function loadReport(string $reportCode): array
    {
        $reportCode = self::sanitizeCode($reportCode);

        return self::loadJson(
            "reports/{$reportCode}.json"
        );
    }

    /**
     * Check whether config exists.
     */
    public static function exists(string $relativePath): bool
    {
        $relativePath = trim($relativePath, '/');

        $filePath = realpath(self::$basePath . '/' . $relativePath);
        $baseRealPath = realpath(self::$basePath);

        return (
            $filePath !== false &&
            $baseRealPath !== false &&
            strpos($filePath, $baseRealPath) === 0 &&
            is_readable($filePath)
        );
    }

    /**
     * List JSON config files from a folder.
     *
     * Example:
     * ConfigLoader::listConfigs('frameworks');
     */
    public static function listConfigs(string $folder): array
    {
        $folder = trim($folder, '/');

        $folderPath = realpath(self::$basePath . '/' . $folder);
        $baseRealPath = realpath(self::$basePath);

        if ($folderPath === false || $baseRealPath === false) {
            return [];
        }

        if (strpos($folderPath, $baseRealPath) !== 0) {
            return [];
        }

        $files = glob($folderPath . '/*.json');

        if (!$files) {
            return [];
        }

        $configs = [];

        foreach ($files as $file) {
            $configs[] = basename($file, '.json');
        }

        return $configs;
    }

    /**
     * Override base path if required.
     *
     * Useful for testing or custom deployment.
     */
    public static function setBasePath(string $path): void
    {
        $realPath = realpath($path);

        if ($realPath === false || !is_dir($realPath)) {
            throw new Exception("Invalid config base path");
        }

        self::$basePath = $realPath;
    }

    /**
     * Sanitize config code.
     *
     * Allows only:
     * letters, numbers, dash, underscore
     */
    private static function sanitizeCode(string $code): string
    {
        $code = strtolower(trim($code));

        if (!preg_match('/^[a-z0-9_-]+$/', $code)) {
            throw new Exception("Invalid configuration code: {$code}");
        }

        return $code;
    }
}
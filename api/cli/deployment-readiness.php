<?php

/** Read-only deployment health check. Run: php api/cli/deployment-readiness.php [--json] */
require_once dirname(__DIR__) . '/core/Env.php';

$jsonOutput = in_array('--json', $argv ?? [], true);
$checks = [];
$add = static function (string $name, bool $ok, string $detail) use (&$checks): void {
    $checks[] = compact('name', 'ok', 'detail');
};

Env::load();
foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    $add('Environment: ' . $key, Env::get($key) !== null && Env::get($key) !== '', 'Required database setting is ' . (Env::get($key) ? 'configured.' : 'missing.'));
}
foreach (['mysqli', 'json', 'openssl'] as $extension) {
    $add('PHP extension: ' . $extension, extension_loaded($extension), extension_loaded($extension) ? 'Loaded.' : 'Missing.');
}

$configDir = dirname(__DIR__) . '/config/';
$domain = json_decode((string)@file_get_contents($configDir . 'domain.json'), true);
$modules = json_decode((string)@file_get_contents($configDir . 'modules.json'), true);
$worker = json_decode((string)@file_get_contents($configDir . 'background_worker.json'), true);
$add('Deployment profile', is_array($domain) && !empty($domain['profile_code']), 'Profile: ' . (string)($domain['profile_code'] ?? 'not configured'));
$add('Module configuration', is_array($modules) && is_array($modules['modules'] ?? null), is_array($modules) ? 'Module configuration is readable.' : 'modules.json is invalid.');
$allowedWorkerModes = ['scheduled-task', 'windows-service', 'redis-coordinated'];
$workerMode = (string)($worker['execution_mode'] ?? '');
$add('Background worker configuration', in_array($workerMode, $allowedWorkerModes, true), 'Mode: ' . ($workerMode ?: 'not configured'));

if ($workerMode === 'redis-coordinated') {
    $add('PHP extension: redis', extension_loaded('redis'), extension_loaded('redis') ? 'Loaded for Redis-coordinated worker.' : 'Required by Redis-coordinated worker.');
}

try {
    require dirname(__DIR__) . '/assets/conn/db.php';
    $result = $con->query("SHOW TABLES LIKE 'background_jobs'");
    $add('Database connection', true, 'Connected successfully.');
    $add('Background jobs table', $result && $result->num_rows === 1, $result && $result->num_rows === 1 ? 'background_jobs exists.' : 'Run api/sql/schema/2026_08_03_background_jobs.sql.');
} catch (Throwable $e) {
    $add('Database connection', false, 'Could not connect. Check database environment settings.');
}

$ok = !in_array(false, array_column($checks, 'ok'), true);
if ($jsonOutput) {
    echo json_encode(['ready' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    foreach ($checks as $check) echo ($check['ok'] ? '[PASS] ' : '[FAIL] ') . $check['name'] . ' — ' . $check['detail'] . PHP_EOL;
    echo PHP_EOL . ($ok ? 'Deployment readiness: PASS' : 'Deployment readiness: ACTION REQUIRED') . PHP_EOL;
}
exit($ok ? 0 : 1);

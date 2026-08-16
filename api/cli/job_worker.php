<?php
/* Run with: php api/cli/job_worker.php --once | --daemon [--redis-lock] */
require_once dirname(__DIR__) . '/assets/conn/db.php';
require_once dirname(__DIR__) . '/service/JobQueueService.php';

$arguments = $argv ?? [];
$daemon = in_array('--daemon', $arguments, true);
$redisLock = in_array('--redis-lock', $arguments, true);
$configPath = dirname(__DIR__) . '/config/background_worker.json';
$config = is_file($configPath) ? (json_decode((string)file_get_contents($configPath), true) ?: []) : [];
$pollSeconds = max(1, (int)($config['poll_seconds'] ?? 5));

if ($redisLock && !class_exists('Redis')) {
    fwrite(STDERR, "Redis-coordinated worker requires the PHP Redis extension.\n");
    exit(2);
}

$queue = new JobQueueService($con);
$redis = null;
$lockKey = (string)($config['redis_coordinated_worker']['lock_key'] ?? 'saqshi:background-worker:lock');
$lockTtl = max(5, (int)($config['redis_coordinated_worker']['lock_ttl_seconds'] ?? 30));
$lockToken = bin2hex(random_bytes(16));
if ($redisLock) {
    $cachePath = dirname(__DIR__) . '/config/cache.json';
    $cache = is_file($cachePath) ? (json_decode((string)file_get_contents($cachePath), true) ?: []) : [];
    $redisConfig = $cache['redis'] ?? [];
    $redis = new Redis();
    $redis->connect((string)($redisConfig['host'] ?? '127.0.0.1'), (int)($redisConfig['port'] ?? 6379), (float)($redisConfig['timeout_seconds'] ?? 1));
    if (!empty($redisConfig['password_env']) && getenv((string)$redisConfig['password_env'])) $redis->auth(getenv((string)$redisConfig['password_env']));
    $redis->select((int)($redisConfig['database'] ?? 0));
}

do {
    if ($redis && !$redis->set($lockKey, $lockToken, ['nx', 'ex' => $lockTtl])) {
        if (!$daemon) exit(0);
        sleep($pollSeconds);
        continue;
    }
    $job = $queue->claim();
    if (!$job) {
        if ($redis && $redis->get($lockKey) === $lockToken) $redis->del($lockKey);
        if (!$daemon) { echo "No queued jobs.\n"; exit(0); }
        sleep($pollSeconds);
        continue;
    }
    try {
        // Job handlers are added alongside each asynchronous feature, for example
        // report.generate_checkpoint_scorecard. Unknown types are retained for retry.
        throw new RuntimeException('No handler registered for job type: ' . (string)$job['job_type']);
    } catch (Throwable $e) {
        $queue->fail($job, $e);
        fwrite(STDERR, "Job {$job['job_id']} failed: {$e->getMessage()}\n");
        if (!$daemon) exit(1);
    } finally {
        if ($redis && $redis->get($lockKey) === $lockToken) $redis->del($lockKey);
    }
} while ($daemon);

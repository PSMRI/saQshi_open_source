<?php

/** Short-lived cache for read-heavy API payloads. It fails open when Redis is unavailable. */
final class ApiCache
{
    private static ?Redis $client = null;
    private static bool $attempted = false;
    private static array $config = [];

    public static function get(string $key): mixed
    {
        $redis = self::client();
        if (!$redis) return null;
        try {
            $value = $redis->get(self::key($key));
            return is_string($value) ? json_decode($value, true) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function put(string $key, mixed $value, int $ttl): void
    {
        $redis = self::client();
        if (!$redis || $ttl <= 0) return;
        try {
            $redis->setex(self::key($key), $ttl, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (Throwable) {
            // Cache failures must not affect the API response.
        }
    }

    public static function forget(string $key): void
    {
        $redis = self::client();
        if (!$redis) return;
        try { $redis->del(self::key($key)); } catch (Throwable) { }
    }

    public static function assessmentListTtl(): int
    {
        return max(1, (int)(self::config()['assessment_list_ttl_seconds'] ?? 30));
    }

    private static function client(): ?Redis
    {
        if (self::$attempted) return self::$client;
        self::$attempted = true;
        $config = self::config();
        if (empty($config['enabled']) || strtolower((string)($config['driver'] ?? '')) !== 'redis' || !extension_loaded('redis')) return null;
        $redisConfig = is_array($config['redis'] ?? null) ? $config['redis'] : [];
        try {
            $redis = new Redis();
            $connected = $redis->connect((string)($redisConfig['host'] ?? '127.0.0.1'), max(1, (int)($redisConfig['port'] ?? 6379)), max(0.1, (float)($redisConfig['timeout_seconds'] ?? 1)));
            if (!$connected) return null;
            $passwordEnv = (string)($redisConfig['password_env'] ?? '');
            $password = $passwordEnv !== '' ? getenv($passwordEnv) : false;
            if (is_string($password) && $password !== '') $redis->auth($password);
            $redis->select(max(0, (int)($redisConfig['database'] ?? 3)));
            return self::$client = $redis;
        } catch (Throwable) {
            return null;
        }
    }

    private static function config(): array
    {
        if (self::$config) return self::$config;
        $path = dirname(__DIR__) . '/config/cache.json';
        $data = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        return self::$config = is_array($data) ? $data : [];
    }

    private static function key(string $key): string
    {
        $prefix = (string)(self::config()['redis']['prefix'] ?? 'saqshi_cache_');
        return $prefix . $key;
    }
}

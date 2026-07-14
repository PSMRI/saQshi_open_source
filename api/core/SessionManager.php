<?php

/**
 * SessionManager.php
 * -------------------------------------------------------
 * Centralized secure session management for SaQshi APIs.
 *
 * Handles:
 * - Secure session start
 * - Login session creation
 * - Session timeout
 * - Session regeneration
 * - Logged-in user checks
 * - Common session getters
 * -------------------------------------------------------
 */

class SessionManager
{
    private const SESSION_NAME = 'SAQSHI_SESSION';

    /**
     * Session lifetime in seconds.
     * 1800 = 30 minutes.
     */
    private const SESSION_TIMEOUT = 1800;

    /**
     * Regenerate session ID every 10 minutes.
     */
    private const REGENERATE_INTERVAL = 600;

    /**
     * Start secure session.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        );

        session_name(self::SESSION_NAME);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_start();

        self::checkTimeout();
        self::regeneratePeriodically();
    }

    /**
     * Create login session.
     */
    public static function login(array $user): void
    {
        self::start();

        session_regenerate_id(true);

        $_SESSION['is_logged_in'] = true;
        $_SESSION['u_id'] = (int)($user['u_id'] ?? 0);
        $_SESSION['u_name'] = (string)($user['u_name'] ?? '');
        $_SESSION['role_id'] = (int)($user['role_id_fk'] ?? $user['role_id'] ?? 0);
        $_SESSION['fac_id'] = (int)($user['fac_id_fk'] ?? $user['fac_id'] ?? 0);
        $_SESSION['dept_id'] = (int)($user['dept_id'] ?? 0);

        $_SESSION['full_name'] = trim(
            ($user['f_name'] ?? '') . ' ' .
            ($user['m_name'] ?? '') . ' ' .
            ($user['l_name'] ?? '')
        );

        $_SESSION['mail_id'] = (string)($user['mail_id'] ?? '');
        $_SESSION['mob_no'] = (string)($user['mob_no'] ?? '');
        $_SESSION['user_type'] = (string)($user['user_type'] ?? '');

        $_SESSION['dist_id'] = (int)($user['dist_id'] ?? 0);
        $_SESSION['block_id'] = (int)($user['block_id'] ?? 0);
        $_SESSION['division_id'] = (int)($user['division_id'] ?? 0);

        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['last_regenerated'] = time();

        $_SESSION['ip_hash'] = self::hashClientIp();
        $_SESSION['user_agent_hash'] = self::hashUserAgent();
    }

    /**
     * Logout and destroy session.
     */
    public static function logout(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Check if user is logged in.
     */
    public static function isLoggedIn(): bool
    {
        self::start();

        return isset($_SESSION['is_logged_in'])
            && $_SESSION['is_logged_in'] === true
            && isset($_SESSION['u_id'])
            && (int)$_SESSION['u_id'] > 0;
    }

    /**
     * Require login for protected APIs.
     */
    public static function requireLogin(): void
    {
        self::start();

        if (!self::isLoggedIn()) {
            self::jsonError('Unauthorized. Please login again.', 401);
        }

        if (!self::isSameClient()) {
            self::logout();
            self::jsonError('Session security validation failed. Please login again.', 401);
        }
    }

    /**
     * Get logged-in user ID.
     */
    public static function userId(): int
    {
        self::start();
        return (int)($_SESSION['u_id'] ?? 0);
    }

    /**
     * Get username.
     */
    public static function username(): string
    {
        self::start();
        return (string)($_SESSION['u_name'] ?? '');
    }

    /**
     * Get role ID.
     */
    public static function roleId(): int
    {
        self::start();
        return (int)($_SESSION['role_id'] ?? 0);
    }

    /**
     * Get assigned facility ID.
     */
    public static function facilityId(): int
    {
        self::start();
        return (int)($_SESSION['fac_id'] ?? 0);
    }

    /**
     * Get assigned department ID.
     */
    public static function departmentId(): int
    {
        self::start();
        return (int)($_SESSION['dept_id'] ?? 0);
    }

    /**
     * Get full session user object.
     */
    public static function user(): array
    {
        self::start();

        return [
            'u_id'        => self::userId(),
            'u_name'      => self::username(),
            'role_id'     => self::roleId(),
            'fac_id'      => self::facilityId(),
            'dept_id'     => self::departmentId(),
            'full_name'   => $_SESSION['full_name'] ?? '',
            'mail_id'     => $_SESSION['mail_id'] ?? '',
            'mob_no'      => $_SESSION['mob_no'] ?? '',
            'user_type'   => $_SESSION['user_type'] ?? '',
            'dist_id'     => (int)($_SESSION['dist_id'] ?? 0),
            'block_id'    => (int)($_SESSION['block_id'] ?? 0),
            'division_id' => (int)($_SESSION['division_id'] ?? 0)
        ];
    }

    public static function updateProfile(array $user): void
    {
        self::start();

        $_SESSION['full_name'] = trim(
            ($user['f_name'] ?? '') . ' ' .
            ($user['m_name'] ?? '') . ' ' .
            ($user['l_name'] ?? '')
        );

        $_SESSION['mail_id'] = (string)($user['mail_id'] ?? '');
        $_SESSION['mob_no'] = (string)($user['mob_no'] ?? '');
        $_SESSION['user_type'] = (string)($user['user_type'] ?? ($_SESSION['user_type'] ?? ''));
    }

    /**
     * Session timeout handling.
     */
    private static function checkTimeout(): void
    {
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
            return;
        }

        if ((time() - (int)$_SESSION['last_activity']) > self::SESSION_TIMEOUT) {
            self::logout();
            self::jsonError('Session expired. Please login again.', 401);
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Regenerate session periodically.
     */
    private static function regeneratePeriodically(): void
    {
        if (!isset($_SESSION['last_regenerated'])) {
            $_SESSION['last_regenerated'] = time();
            return;
        }

        if ((time() - (int)$_SESSION['last_regenerated']) > self::REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['last_regenerated'] = time();
        }
    }

    /**
     * Prevent session hijacking by validating IP + User Agent.
     */
    private static function isSameClient(): bool
    {
        if (!isset($_SESSION['ip_hash'], $_SESSION['user_agent_hash'])) {
            return true;
        }

        return hash_equals($_SESSION['ip_hash'], self::hashClientIp())
            && hash_equals($_SESSION['user_agent_hash'], self::hashUserAgent());
    }

    /**
     * Hash client IP.
     */
    private static function hashClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return hash('sha256', $ip);
    }

    /**
     * Hash User Agent.
     */
    private static function hashUserAgent(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', $ua);
    }

    /**
     * JSON error response.
     */
    private static function jsonError(string $message, int $code = 401): never
    {
        http_response_code($code);

        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'errors' => null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        exit;
    }
}

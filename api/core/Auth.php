<?php

/**
 * Auth.php
 * -------------------------------------------------------
 * Centralized authentication service for SaQshi.
 *
 * Uses:
 * - s_user table
 * - u_role table
 * - login_attempts table
 * - SessionManager
 *
 * login_attempts columns:
 * id, username, ip_address, attempt_time, status
 * -------------------------------------------------------
 */

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/Crypto.php';

/**
 * Provides auth behavior for SaQshi API workflows.
 */
class Auth
{
    private mysqli $db;

    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    /**
     * Handles construct processing for this API workflow.
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Handles login processing for this API workflow.
     */
    public function login(string $username, string $password): array
    {
        $username = trim($username);
        $password = trim($password);

        if ($username === '' || $password === '') {
            return $this->error('Username and password are required');
        }

        if ($this->isLocked($username)) {
            return $this->error('Too many failed login attempts. Please try again later.');
        }

        $user = $this->findUser($username);

        if (
            !$user ||
            (int)($user['is_active'] ?? 0) !== 1 ||
            (array_key_exists('role_status', $user) && (int)($user['role_status'] ?? 0) !== 1)
        ) {
            $this->recordAttempt($username, 'FAILED');
            return $this->error('Invalid username or password');
        }

        $storedPassword = (string)($user['u_password'] ?? '');
        $passwordStatus = $this->passwordStatus($password, $storedPassword);

        if (!$passwordStatus['valid']) {
            $this->recordAttempt($username, 'FAILED');
            return $this->error('Invalid username or password');
        }

        if ($passwordStatus['needs_hash_upgrade']) {
            $this->upgradePasswordHash((int)$user['u_id'], $password);
        }

        $user = $this->decryptUserProfileFields($user);

        $this->clearOldFailedAttempts($username);
        $this->recordAttempt($username, 'SUCCESS');

        unset($user['u_password']);

        SessionManager::login($user);

        return $this->success('Login successful', [
            'user' => SessionManager::user()
        ]);
    }

    /**
     * Handles logout processing for this API workflow.
     */
    public function logout(): array
    {
        SessionManager::logout();

        return $this->success('Logout successful');
    }

    /**
     * Handles me processing for this API workflow.
     */
    public function me(): array
    {
        if (!SessionManager::isLoggedIn()) {
            return $this->error('Unauthorized');
        }

        return $this->success('User fetched successfully', [
            'user' => SessionManager::user()
        ]);
    }

    /**
     * Handles find user processing for this API workflow.
     */
    private function findUser(string $username): ?array
    {
        $sql = "
            SELECT
                u.u_id,
                u.u_name,
                u.u_password,
                u.fac_id_fk,
                u.role_id_fk,
                u.is_active,
                u.dept_id,
                u.f_name,
                u.m_name,
                u.l_name,
                u.mob_no,
                u.mail_id,
                u.user_type,
                u.assessment_id,
                u.dist_id,
                u.block_id,
                u.division_id,
                r.role_name,
                r.role_status
            FROM s_user u
            LEFT JOIN u_role r
                ON r.role_id = u.role_id_fk
            WHERE
                u.u_name = ?                
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'User query prepare failed: ' . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $username
           
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        return $result->fetch_assoc();
    }

    /**
     * Handles find user by id processing for this API workflow.
     */
    private function findUserById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $sql = "
            SELECT
                u.u_id,
                u.u_name,
                u.u_password,
                u.fac_id_fk,
                u.role_id_fk,
                u.is_active,
                u.dept_id,
                u.f_name,
                u.m_name,
                u.l_name,
                u.mob_no,
                u.mail_id,
                u.user_type,
                u.assessment_id,
                u.dist_id,
                u.block_id,
                u.division_id,
                r.role_name,
                r.role_status
            FROM s_user u
            LEFT JOIN u_role r
                ON r.role_id = u.role_id_fk
            WHERE u.u_id = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new Exception('User lookup prepare failed: ' . $this->db->error);
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        return $result->fetch_assoc();
    }

    /**
     * Handles decrypt user profile fields processing for this API workflow.
     */
    private function decryptUserProfileFields(array $user): array
    {
        return Crypto::decryptFields($user, [
            'f_name',
            'm_name',
            'l_name',
            'mail_id',
            'mob_no'
        ]);
    }

    /**
     * Handles password status processing for this API workflow.
     */
    private function passwordStatus(string $plainPassword, string $storedPassword): array
    {
        $plainPassword = trim((string)$plainPassword);
        $storedPassword = trim((string)$storedPassword);

        $plainPassword = preg_replace('/[[:^print:]]/', '', $plainPassword);
        $storedPassword = preg_replace('/[[:^print:]]/', '', $storedPassword);

        if ($storedPassword === '') {
            return [
                'valid' => false,
                'needs_hash_upgrade' => false
            ];
        }

        $passwordInfo = password_get_info($storedPassword);
        $isHashedPassword = (
            !empty($passwordInfo['algo']) ||
            (($passwordInfo['algoName'] ?? 'unknown') !== 'unknown')
        );

        if ($isHashedPassword) {
            return [
                'valid' => password_verify($plainPassword, $storedPassword),
                'needs_hash_upgrade' => false
            ];
        }

        return [
            'valid' => hash_equals($storedPassword, $plainPassword),
            'needs_hash_upgrade' => hash_equals($storedPassword, $plainPassword)
        ];
    }

    /**
     * Handles upgrade password hash processing for this API workflow.
     */
    private function upgradePasswordHash(int $userId, string $plainPassword): void
    {
        if ($userId <= 0) {
            return;
        }

        $hash = self::hashPassword($plainPassword);

        $stmt = $this->db->prepare("
            UPDATE s_user
            SET u_password = ?
            WHERE u_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception('Password hash update prepare failed: ' . $this->db->error);
        }

        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
    }

    /**
     * Handles is locked processing for this API workflow.
     */
    private function isLocked(string $username): bool
    {
        if (!$this->loginAttemptTableExists()) {
            return false;
        }

        $sql = "
            SELECT COUNT(*) AS failed_count
            FROM login_attempts
            WHERE username = ?
              AND status = 'FAILED'
              AND attempt_time >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $lockMinutes = self::LOCK_MINUTES;

        $stmt->bind_param(
            'si',
            $username,
            $lockMinutes
        );

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        return ((int)($row['failed_count'] ?? 0)) >= self::MAX_FAILED_ATTEMPTS;
    }

    /**
     * Handles record attempt processing for this API workflow.
     */
    private function recordAttempt(string $username, string $status): void
    {
        if (!$this->loginAttemptTableExists()) {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $status = strtoupper($status);

        $sql = "
            INSERT INTO login_attempts
                (
                    username,
                    ip_address,
                    attempt_time,
                    status
                )
            VALUES
                (
                    ?,
                    ?,
                    NOW(),
                    ?
                )
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return;
        }

        $stmt->bind_param(
            'sss',
            $username,
            $ip,
            $status
        );

        $stmt->execute();
    }

    /**
     * Handles clear old failed attempts processing for this API workflow.
     */
    private function clearOldFailedAttempts(string $username): void
    {
        if (!$this->loginAttemptTableExists()) {
            return;
        }

        $sql = "
            DELETE FROM login_attempts
            WHERE username = ?
              AND status = 'FAILED'
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return;
        }

        $stmt->bind_param('s', $username);
        $stmt->execute();
    }

    /**
     * Handles login attempt table exists processing for this API workflow.
     */
    private function loginAttemptTableExists(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        $result = $this->db->query(
            "SHOW TABLES LIKE 'login_attempts'"
        );

        $exists = $result && $result->num_rows > 0;

        return $exists;
    }

    /**
     * Handles hash password processing for this API workflow.
     */
    public static function hashPassword(string $password): string
    {
        return password_hash(
            $password,
            PASSWORD_BCRYPT,
            [
                'cost' => 12
            ]
        );
    }

    /**
     * Handles success processing for this API workflow.
     */
    private function success(string $message, array $data = []): array
    {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Handles error processing for this API workflow.
     */
    private function error(string $message): array
    {
        return [
            'status' => 'error',
            'message' => $message,
            'data' => null
        ];
    }
}

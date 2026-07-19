<?php

/**
 * users.php
 * -------------------------------------------------------
 * Logged-in facility user profile/password update.
 *
 * GET  /api/admin/v1/users.php
 * POST /api/admin/v1/users.php
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Crypto.php';

/**
 * Handles admin users request processing for this API workflow.
 */
function adminUsersRequest(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

/**
 * Handles admin users password errors processing for this API workflow.
 */
function adminUsersPasswordErrors(string $password): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Minimum 8 characters';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'At least one capital letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'At least one lower-case letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'At least one digit';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'At least one special character';
    }

    return $errors;
}

/**
 * Handles admin users row processing for this API workflow.
 */
function adminUsersRow(array $row): array
{
    $row = Crypto::decryptFields($row, [
        'f_name',
        'm_name',
        'l_name',
        'mail_id',
        'mob_no'
    ]);

    return [
        'u_id' => (int)($row['u_id'] ?? 0),
        'u_name' => (string)($row['u_name'] ?? ''),
        'f_name' => (string)($row['f_name'] ?? ''),
        'm_name' => (string)($row['m_name'] ?? ''),
        'l_name' => (string)($row['l_name'] ?? ''),
        'mail_id' => (string)($row['mail_id'] ?? ''),
        'mob_no' => (string)($row['mob_no'] ?? ''),
        'user_type' => (string)($row['user_type'] ?? ''),
        'role_id_fk' => isset($row['role_id_fk']) ? (int)$row['role_id_fk'] : null,
        'role_name' => (string)($row['role_name'] ?? ''),
        'fac_id_fk' => isset($row['fac_id_fk']) ? (int)$row['fac_id_fk'] : null,
        'dept_id' => isset($row['dept_id']) ? (int)$row['dept_id'] : null,
        'is_active' => (int)($row['is_active'] ?? 0)
    ];
}

/**
 * Encrypts profile identity fields before any s_user profile write.
 */
function adminUsersEncryptedProfilePayload(
    string $firstName,
    string $middleName,
    string $lastName,
    string $email,
    string $mobile
): array {
    return [
        'f_name' => Crypto::encrypt($firstName),
        'm_name' => Crypto::encrypt($middleName),
        'l_name' => Crypto::encrypt($lastName),
        'mail_id' => Crypto::encrypt($email),
        'mob_no' => Crypto::encrypt($mobile)
    ];
}

/**
 * Checks whether the current schema has a requested table column.
 */
function adminUsersColumnExists(mysqli $con, string $table, string $column): bool
{
    $stmt = $con->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    return (bool)$stmt->get_result()->fetch_assoc();
}

/**
 * Handles admin users find processing for this API workflow.
 */
function adminUsersFind(mysqli $con, int $userId): ?array
{
    $sql = "
        SELECT
            u.u_id,
            u.u_name,
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
            r.role_name
        FROM s_user u
        LEFT JOIN u_role r
            ON r.role_id = u.role_id_fk
        WHERE u.u_id = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        Response::serverError('User lookup prepare failed: ' . $con->error);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        return null;
    }

    $user = adminUsersRow($row);

    if (adminUsersColumnExists($con, 's_user', 'password_must_change')) {
        $flagStmt = $con->prepare("SELECT password_must_change FROM s_user WHERE u_id = ? LIMIT 1");
        if ($flagStmt) {
            $flagStmt->bind_param('i', $userId);
            $flagStmt->execute();
            $flag = $flagStmt->get_result()->fetch_assoc();
            $user['password_must_change'] = (int)($flag['password_must_change'] ?? 0) === 1;
        }
    }

    return $user;
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    $sessionUserId = SessionManager::userId();

    if ($sessionUserId <= 0) {
        Response::unauthorized('User session not found');
    }

    if ($method === 'GET') {
        $user = adminUsersFind($con, $sessionUserId);

        if (!$user) {
            Response::notFound('User not found');
        }

        Response::success('Profile fetched successfully', [
            'user' => $user
        ]);
    }

    if ($method !== 'POST') {
        Response::error('Method not allowed', null, 405);
    }

    $request = adminUsersRequest();
    $userId = $sessionUserId;

    $existing = adminUsersFind($con, $userId);

    if (!$existing) {
        Response::notFound('User not found');
    }

    $firstName = trim((string)($request['f_name'] ?? ''));
    $middleName = trim((string)($request['m_name'] ?? ''));
    $lastName = trim((string)($request['l_name'] ?? ''));
    $email = trim((string)($request['mail_id'] ?? ''));
    $mobile = trim((string)($request['mob_no'] ?? ''));
    $password = (string)($request['password'] ?? '');
    $confirmPassword = (string)($request['confirm_password'] ?? '');

    $errors = [];

    if ($firstName === '') {
        $errors['f_name'] = 'First name is required';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['mail_id'] = 'Valid email is required';
    }

    if ($mobile !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $mobile)) {
        $errors['mob_no'] = 'Valid mobile number is required';
    }

    if ($password !== '' || $confirmPassword !== '') {
        if (!hash_equals($password, $confirmPassword)) {
            $errors['confirm_password'] = 'Confirm password does not match';
        }

        $passwordErrors = adminUsersPasswordErrors($password);

        if (!empty($passwordErrors)) {
            $errors['password'] = implode(', ', $passwordErrors);
        }
    }

    if (!empty($errors)) {
        Response::validation($errors);
    }

    $encryptedProfile = adminUsersEncryptedProfilePayload(
        $firstName,
        $middleName,
        $lastName,
        $email,
        $mobile
    );

    if ($password !== '') {
        $hash = Auth::hashPassword($password);
        $passwordResetSql = adminUsersColumnExists($con, 's_user', 'password_must_change')
            ? ",
                password_must_change = 0,
                password_changed_on = CURRENT_TIMESTAMP"
            : "";

        $sql = "
            UPDATE s_user
            SET
                f_name = ?,
                m_name = ?,
                l_name = ?,
                mail_id = ?,
                mob_no = ?,
                u_password = ?
                {$passwordResetSql}
            WHERE u_id = ?
            LIMIT 1
        ";

        $stmt = $con->prepare($sql);

        if (!$stmt) {
            Response::serverError('User update prepare failed: ' . $con->error);
        }

        $stmt->bind_param(
            'ssssssi',
            $encryptedProfile['f_name'],
            $encryptedProfile['m_name'],
            $encryptedProfile['l_name'],
            $encryptedProfile['mail_id'],
            $encryptedProfile['mob_no'],
            $hash,
            $userId
        );
    } else {
        $sql = "
            UPDATE s_user
            SET
                f_name = ?,
                m_name = ?,
                l_name = ?,
                mail_id = ?,
                mob_no = ?
            WHERE u_id = ?
            LIMIT 1
        ";

        $stmt = $con->prepare($sql);

        if (!$stmt) {
            Response::serverError('User update prepare failed: ' . $con->error);
        }

        $stmt->bind_param(
            'sssssi',
            $encryptedProfile['f_name'],
            $encryptedProfile['m_name'],
            $encryptedProfile['l_name'],
            $encryptedProfile['mail_id'],
            $encryptedProfile['mob_no'],
            $userId
        );
    }

    if (!$stmt->execute()) {
        Response::serverError('User update failed: ' . $stmt->error);
    }

    $_SESSION['full_name'] = trim($firstName . ' ' . $middleName . ' ' . $lastName);
    $_SESSION['mail_id'] = $email;
    $_SESSION['mob_no'] = $mobile;
    $_SESSION['password_must_change'] = false;

    Response::success('Profile updated successfully', [
        'user' => adminUsersFind($con, $userId),
        'password_updated' => $password !== ''
    ]);

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

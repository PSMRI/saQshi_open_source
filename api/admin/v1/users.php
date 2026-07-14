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

function adminUsersRequest(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

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

    return $row ? adminUsersRow($row) : null;
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

    $encryptedFirstName = Crypto::encrypt($firstName);
    $encryptedMiddleName = Crypto::encrypt($middleName);
    $encryptedLastName = Crypto::encrypt($lastName);
    $encryptedEmail = Crypto::encrypt($email);
    $encryptedMobile = Crypto::encrypt($mobile);

    if ($password !== '') {
        $hash = Auth::hashPassword($password);
        $sql = "
            UPDATE s_user
            SET
                f_name = ?,
                m_name = ?,
                l_name = ?,
                mail_id = ?,
                mob_no = ?,
                u_password = ?
            WHERE u_id = ?
            LIMIT 1
        ";

        $stmt = $con->prepare($sql);

        if (!$stmt) {
            Response::serverError('User update prepare failed: ' . $con->error);
        }

        $stmt->bind_param(
            'ssssssi',
            $encryptedFirstName,
            $encryptedMiddleName,
            $encryptedLastName,
            $encryptedEmail,
            $encryptedMobile,
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
            $encryptedFirstName,
            $encryptedMiddleName,
            $encryptedLastName,
            $encryptedEmail,
            $encryptedMobile,
            $userId
        );
    }

    if (!$stmt->execute()) {
        Response::serverError('User update failed: ' . $stmt->error);
    }

    $_SESSION['full_name'] = trim($firstName . ' ' . $middleName . ' ' . $lastName);
    $_SESSION['mail_id'] = $email;
    $_SESSION['mob_no'] = $mobile;

    Response::success('Profile updated successfully', [
        'user' => adminUsersFind($con, $userId),
        'password_updated' => $password !== ''
    ]);

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

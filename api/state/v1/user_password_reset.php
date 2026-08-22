<?php

/*! SaQshi Open Source | State User Password Reset API */

require_once __DIR__ . '/_management_bootstrap.php';
require_once __DIR__ . '/../../core/Auth.php';

Security::requireMethod('POST');

if (SessionManager::roleId() !== 9) {
    Response::forbidden('Only State Admin can reset user passwords.');
}

try {
    $input = Security::jsonInput();
    $userId = (int)($input['u_id'] ?? $input['user_id'] ?? 0);
    $password = (string)($input['password'] ?? '');
    if ($userId <= 0) Response::validation(['u_id' => 'User ID is required.']);
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        Response::validation(['password' => 'Password must have 8+ characters with upper-case, lower-case, number and special character.']);
    }
    $target = $con->prepare('SELECT u_id, u_name FROM s_user WHERE u_id = ? LIMIT 1');
    $target->bind_param('i', $userId); $target->execute();
    $user = $target->get_result()->fetch_assoc();
    if (!$user) Response::validation(['u_id' => 'User was not found.']);

    $hash = Auth::hashPassword($password);
    $hasFlag = (bool)$con->query("SHOW COLUMNS FROM s_user LIKE 'password_must_change'")->fetch_assoc();
    $hasChangedOn = (bool)$con->query("SHOW COLUMNS FROM s_user LIKE 'password_changed_on'")->fetch_assoc();
    $fields = 'u_password = ?';
    if ($hasFlag) $fields .= ', password_must_change = 1';
    if ($hasChangedOn) $fields .= ', password_changed_on = CURRENT_TIMESTAMP';
    $fields .= ', updated_by = ?, updated_on = CURRENT_TIMESTAMP';
    $stmt = $con->prepare("UPDATE s_user SET {$fields} WHERE u_id = ? LIMIT 1");
    $updatedBy = SessionManager::userId();
    $stmt->bind_param('sii', $hash, $updatedBy, $userId);
    if (!$stmt->execute()) Response::serverError('Unable to reset password.');
    Response::success('Password reset. The user must change it at next login.', ['u_id' => $userId, 'username' => $user['u_name']]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

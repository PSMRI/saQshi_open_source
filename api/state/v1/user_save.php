<?php

/*! SaQshi Open Source | State User Save API | user_save.php | Version 1.0.0 */

require_once __DIR__ . '/_management_bootstrap.php';
require_once __DIR__ . '/../../core/Auth.php';

Security::requireAnyMethod(['POST', 'PUT']);

if (SessionManager::roleId() !== 11) {
    Response::forbidden('Only Role 11 can edit managed user accounts.');
}

$input = Security::jsonInput();
$userId = (int)($input['u_id'] ?? $input['user_id'] ?? 0);
$username = trim((string)($input['u_name'] ?? ''));
$firstName = trim((string)($input['f_name'] ?? ''));
$middleName = trim((string)($input['m_name'] ?? ''));
$lastName = trim((string)($input['l_name'] ?? ''));
$email = trim((string)($input['mail_id'] ?? ''));
$mobile = trim((string)($input['mob_no'] ?? ''));
$password = (string)($input['password'] ?? '');
if ($userId <= 0 || $username === '' || $firstName === '') Response::validation(['profile' => 'User ID, username and first name are required.']);
if (!preg_match('/^[A-Za-z0-9_.@-]{3,100}$/', $username)) Response::validation(['u_name' => 'Use 3-100 letters, numbers, dot, underscore, @ or hyphen.']);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) Response::validation(['mail_id' => 'Enter a valid email address.']);
if ($mobile !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $mobile)) Response::validation(['mob_no' => 'Enter a valid mobile number.']);
if ($password !== '' && (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password))) {
    Response::validation(['password' => 'Password must have 8+ characters with upper-case, lower-case, number and special character.']);
}
$target = $con->prepare("SELECT r.role_name FROM s_user u LEFT JOIN u_role r ON r.role_id = u.role_id_fk WHERE u.u_id = ? LIMIT 1");
$target->bind_param('i', $userId); $target->execute();
$roleName = strtolower((string)(($target->get_result()->fetch_assoc() ?: [])['role_name'] ?? ''));
if (!str_contains($roleName, 'admin') && !str_contains($roleName, 'assessor') && !str_contains($roleName, 'mentor')) Response::forbidden('Role 11 can edit only Administrator and Mentor/Assessor accounts.');
$duplicate = $con->prepare('SELECT 1 FROM s_user WHERE u_name = ? AND u_id <> ? LIMIT 1');
$duplicate->bind_param('si', $username, $userId); $duplicate->execute();
if ($duplicate->get_result()->fetch_assoc()) Response::validation(['u_name' => 'This username already exists.']);
$encryptedProfile = [
    Crypto::encrypt($firstName),
    Crypto::encrypt($middleName),
    Crypto::encrypt($lastName),
    Crypto::encrypt($email),
    Crypto::encrypt($mobile)
];
if ($password !== '') {
    $hash = Auth::hashPassword($password);
    $hasPasswordFlag = (bool)$con->query("SHOW COLUMNS FROM s_user LIKE 'password_must_change'")->fetch_assoc();
    $hasPasswordDate = (bool)$con->query("SHOW COLUMNS FROM s_user LIKE 'password_changed_on'")->fetch_assoc();
    $resetFields = ($hasPasswordFlag ? ', password_must_change = 1' : '') . ($hasPasswordDate ? ', password_changed_on = CURRENT_TIMESTAMP' : '');
    $sql = 'UPDATE s_user SET u_name = ?, f_name = ?, m_name = ?, l_name = ?, mail_id = ?, mob_no = ?, u_password = ?' . $resetFields . ' WHERE u_id = ? LIMIT 1';
    $stmt = $con->prepare($sql); $stmt->bind_param('sssssssi', $username, $encryptedProfile[0], $encryptedProfile[1], $encryptedProfile[2], $encryptedProfile[3], $encryptedProfile[4], $hash, $userId);
} else {
    $stmt = $con->prepare('UPDATE s_user SET u_name = ?, f_name = ?, m_name = ?, l_name = ?, mail_id = ?, mob_no = ? WHERE u_id = ? LIMIT 1');
    $stmt->bind_param('ssssssi', $username, $encryptedProfile[0], $encryptedProfile[1], $encryptedProfile[2], $encryptedProfile[3], $encryptedProfile[4], $userId);
}
$stmt->execute();
Response::success($password !== '' ? 'User profile updated. The reset password must be changed on next login.' : 'User profile updated.', ['u_id' => $userId, 'password_reset' => $password !== '']);

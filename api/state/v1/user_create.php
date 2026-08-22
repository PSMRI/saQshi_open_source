<?php

/*! SaQshi Open Source | State User Creation API */

require_once __DIR__ . '/_management_bootstrap.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Crypto.php';

Security::requireMethod('POST');

if (!in_array(SessionManager::roleId(), [9, 11], true)) {
    Response::forbidden('Only State Administration can create users.');
}

try {
    $input = Security::jsonInput();
    $roleId = (int)($input['role_id'] ?? 0);
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $firstName = trim((string)($input['first_name'] ?? ''));
    $middleName = trim((string)($input['middle_name'] ?? ''));
    $lastName = trim((string)($input['last_name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $mobile = trim((string)($input['mobile'] ?? ''));
    $facilityNin = trim((string)($input['facility_nin'] ?? ''));
    $scopeId = (int)($input['scope_id'] ?? 0);

    $allowedRoles = [1, 4, 5, 8];
    if (!in_array($roleId, $allowedRoles, true)) Response::validation(['role_id' => 'Select a valid user role.']);
    if ($roleId !== 1) {
        if ($firstName === '' || !preg_match('/^[A-Za-z .\'-]{2,100}$/', $firstName)) Response::validation(['first_name' => 'Enter a valid first name.']);
        if (!preg_match('/^[A-Za-z0-9_.@-]{3,100}$/', $username)) Response::validation(['username' => 'Use 3-100 letters, numbers, dot, underscore, @ or hyphen.']);
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) Response::validation(['password' => 'Password must have 8+ characters with upper-case, lower-case, number and special character.']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) Response::validation(['email' => 'Enter a valid email address.']);
        if ($mobile !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $mobile)) Response::validation(['mobile' => 'Enter a valid mobile number.']);
    }

    $facilityId = 0; $stateId = 0; $divisionId = 0; $districtId = 0; $blockId = 0;
    if ($roleId === 1) {
        if ($facilityNin === '') Response::validation(['facility_nin' => 'Facility NIN is required for a Facility User.']);
        $scope = $con->prepare('SELECT fac_id, state_id, division_id, dist_id, block_id FROM facilities WHERE NIN_no = ? LIMIT 1');
        $scope->bind_param('s', $facilityNin); $scope->execute();
        $scopeRow = $scope->get_result()->fetch_assoc();
        if (!$scopeRow) Response::validation(['facility_nin' => 'No facility was found for this NIN.']);
        $facilityId = (int)$scopeRow['fac_id']; $stateId = (int)$scopeRow['state_id']; $divisionId = (int)$scopeRow['division_id']; $districtId = (int)$scopeRow['dist_id']; $blockId = (int)$scopeRow['block_id'];
        // Facility-user credentials are intentionally deterministic: the
        // Facility NIN is both the initial username and initial password.
        $username = $facilityNin;
        $password = $facilityNin;
    } elseif ($roleId === 5) {
        if ($scopeId <= 0) Response::validation(['scope_id' => 'Select a division.']);
        $scope = $con->prepare('SELECT state_id, division_id FROM facilities WHERE division_id = ? LIMIT 1');
        $scope->bind_param('i', $scopeId); $scope->execute(); $scopeRow = $scope->get_result()->fetch_assoc();
        if (!$scopeRow) Response::validation(['scope_id' => 'Selected division is not valid.']);
        $stateId = (int)$scopeRow['state_id']; $divisionId = (int)$scopeRow['division_id'];
    } elseif ($roleId === 4) {
        if ($scopeId <= 0) Response::validation(['scope_id' => 'Select a district.']);
        $scope = $con->prepare('SELECT state_id, division_id, dist_id FROM facilities WHERE dist_id = ? LIMIT 1');
        $scope->bind_param('i', $scopeId); $scope->execute(); $scopeRow = $scope->get_result()->fetch_assoc();
        if (!$scopeRow) Response::validation(['scope_id' => 'Selected district is not valid.']);
        $stateId = (int)$scopeRow['state_id']; $divisionId = (int)$scopeRow['division_id']; $districtId = (int)$scopeRow['dist_id'];
    } elseif ($roleId === 8) {
        if ($scopeId <= 0) Response::validation(['scope_id' => 'Select a block.']);
        $scope = $con->prepare('SELECT state_id, division_id, dist_id, block_id FROM facilities WHERE block_id = ? LIMIT 1');
        $scope->bind_param('i', $scopeId); $scope->execute(); $scopeRow = $scope->get_result()->fetch_assoc();
        if (!$scopeRow) Response::validation(['scope_id' => 'Selected block is not valid.']);
        $stateId = (int)$scopeRow['state_id']; $divisionId = (int)$scopeRow['division_id']; $districtId = (int)$scopeRow['dist_id']; $blockId = (int)$scopeRow['block_id'];
    } else {
        $scopeRow = $con->query('SELECT state_id FROM facilities WHERE state_id IS NOT NULL AND state_id > 0 LIMIT 1')->fetch_assoc();
        $stateId = (int)($scopeRow['state_id'] ?? 0);
    }

    $scopeColumns = [1 => 'fac_id_fk', 8 => 'block_id', 4 => 'dist_id', 5 => 'division_id'];
    $scopeNames = [1 => 'facility', 8 => 'block', 4 => 'district', 5 => 'division'];
    $scopeValues = [1 => $facilityId, 8 => $blockId, 4 => $districtId, 5 => $divisionId];
    $scopeColumn = $scopeColumns[$roleId];
    $scopeValue = $scopeValues[$roleId];
    $existingScope = $con->prepare("SELECT u_id FROM s_user WHERE role_id_fk = ? AND {$scopeColumn} = ? LIMIT 1");
    $existingScope->bind_param('ii', $roleId, $scopeValue);
    $existingScope->execute();
    if ($existingScope->get_result()->fetch_assoc()) {
        Response::validation(['scope_id' => 'A ' . $scopeNames[$roleId] . ' user already exists for this assigned scope.']);
    }

    $duplicate = $con->prepare('SELECT 1 FROM s_user WHERE u_name = ? LIMIT 1');
    $duplicate->bind_param('s', $username); $duplicate->execute();
    if ($duplicate->get_result()->fetch_assoc()) Response::validation(['username' => 'A user already exists for this username or Facility NIN.']);

    $role = $con->prepare('SELECT role_name FROM u_role WHERE role_id = ? AND role_status = 1 LIMIT 1');
    $role->bind_param('i', $roleId); $role->execute();
    $roleRow = $role->get_result()->fetch_assoc();
    if (!$roleRow) Response::validation(['role_id' => 'Selected role is inactive or unavailable.']);

    $hash = Auth::hashPassword($password);
    $profile = [Crypto::encrypt($firstName), Crypto::encrypt($middleName), Crypto::encrypt($lastName), Crypto::encrypt($mobile), Crypto::encrypt($email)];
    $createdBy = SessionManager::userId();
    $stmt = $con->prepare('INSERT INTO s_user (u_name, u_password, fac_id_fk, role_id_fk, is_active, f_name, m_name, l_name, mob_no, mail_id, user_type, state_id, division_id, dist_id, block_id, password_must_change, created_by, updated_by) VALUES (?, ?, NULLIF(?, 0), ?, 1, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), 1, ?, ?)');
    $userType = (string)$roleRow['role_name'];
    $stmt->bind_param('ssiissssssiiiiii', $username, $hash, $facilityId, $roleId, $profile[0], $profile[1], $profile[2], $profile[3], $profile[4], $userType, $stateId, $divisionId, $districtId, $blockId, $createdBy, $createdBy);
    if (!$stmt->execute()) Response::serverError('Unable to create user.');
    $message = $roleId === 1
        ? 'Facility User created. The Facility NIN is the initial user ID and password; personal details must be completed after first login.'
        : 'User created successfully. The user must change the password at first login.';
    Response::success($message, ['u_id' => $con->insert_id, 'username' => $username, 'role_name' => $userType]);
} catch (Throwable $e) {
    if ($e instanceof mysqli_sql_exception && (int)$e->getCode() === 1062) {
        Response::validation(['scope_id' => 'A user already exists for this assigned scope.']);
    }
    Response::serverError($e->getMessage());
}

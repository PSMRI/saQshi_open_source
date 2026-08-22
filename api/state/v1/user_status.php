<?php

/*! SaQshi Open Source | State User Status API | user_status.php | Version 1.0.0 */

require_once __DIR__ . '/_management_bootstrap.php';
require_once __DIR__ . '/../../service/StateDashboardService.php';

Security::requireAnyMethod(['POST', 'PATCH']);

try {
    $payload = Security::jsonInput();
    $userId = (int)($payload['u_id'] ?? $payload['user_id'] ?? 0);
    $isActive = (int)($payload['is_active'] ?? -1);

    if ($userId <= 0) {
        Response::validation(['u_id' => 'User ID is required.']);
    }

    if (!in_array($isActive, [0, 1], true)) {
        Response::validation(['is_active' => 'Status must be 0 or 1.']);
    }

    if (SessionManager::roleId() === 11) {
        $target = $con->prepare("SELECT r.role_name FROM s_user u LEFT JOIN u_role r ON r.role_id = u.role_id_fk WHERE u.u_id = ? LIMIT 1");
        $target->bind_param('i', $userId);
        $target->execute();
        $targetRole = strtolower((string)(($target->get_result()->fetch_assoc() ?: [])['role_name'] ?? ''));
        if (!str_contains($targetRole, 'admin') && !str_contains($targetRole, 'assessor') && !str_contains($targetRole, 'mentor')) {
            Response::forbidden('Role 11 can manage only Administrator and Mentor/Assessor accounts.');
        }
    }

    if ($isActive === 0) {
        $target = $con->prepare('SELECT role_id_fk FROM s_user WHERE u_id = ? LIMIT 1');
        $target->bind_param('i', $userId);
        $target->execute();
        $targetRoleId = (int)(($target->get_result()->fetch_assoc() ?: [])['role_id_fk'] ?? 0);
        if ($targetRoleId === 9) {
            Response::forbidden('State Admin accounts cannot be deactivated.');
        }
    }

    if ($userId === SessionManager::userId() && $isActive === 0) {
        Response::validation(['u_id' => 'You cannot deactivate your own logged-in account.']);
    }

    Response::success('User status updated', StateDashboardService::updateUserStatus($con, $userId, $isActive));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

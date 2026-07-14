<?php

/*! SaQshi Open Source | State User Status API | user_status.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

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

    if ($userId === SessionManager::userId() && $isActive === 0) {
        Response::validation(['u_id' => 'You cannot deactivate your own logged-in account.']);
    }

    Response::success('User status updated', StateDashboardService::updateUserStatus($con, $userId, $isActive));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}

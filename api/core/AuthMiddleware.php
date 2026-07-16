<?php

/**
 * Provides auth middleware behavior for SaQshi API workflows.
 */
class AuthMiddleware {

    /**
     * Handles check processing for this API workflow.
     */
    public static function check() {
        session_start();

        if (!isset($_SESSION['user'])) {
            echo json_encode([
                "status" => false,
                "message" => "Unauthorized"
            ]);
            exit;
        }

        return $_SESSION['user'];
    }

    /**
     * Handles role processing for this API workflow.
     */
    public static function role($allowed_roles = []) {
        $user = self::check();

        if (!in_array($user['role_name'], $allowed_roles)) {
            echo json_encode([
                "status" => false,
                "message" => "Forbidden - Role not allowed"
            ]);
            exit;
        }

        return $user;
    }
}
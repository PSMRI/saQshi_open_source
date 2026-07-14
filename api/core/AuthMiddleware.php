<?php

class AuthMiddleware {

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
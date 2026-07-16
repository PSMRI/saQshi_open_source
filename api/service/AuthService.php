<?php

require_once __DIR__ . '/../core/Crypto.php';

/**
 * Provides auth service behavior for SaQshi API workflows.
 */
class AuthService {

    /**
     * Handles login processing for this API workflow.
     */
    public static function login($conn, $username, $password) {

        $sql = "SELECT u.*, r.role_name 
                FROM s_user u
                LEFT JOIN u_role r ON u.role_id_fk = r.role_id
                WHERE u.u_name = ? AND u.is_active = 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) {
            return ["status" => false, "message" => "User not found or inactive"];
        }

        // If password is hashed
        if (!password_verify($password, $user['u_password'])) {
            return ["status" => false, "message" => "Invalid password"];
        }

        $user = Crypto::decryptFields($user, [
            'f_name',
            'm_name',
            'l_name',
            'mail_id',
            'mob_no'
        ]);

        // If plain password (temporary fallback)
        // if ($password !== $user['u_password']) { ... }

        // Generate token
        $token = bin2hex(random_bytes(32));

        $_SESSION['user'] = [
            "u_id" => $user['u_id'],
            "username" => $user['u_name'],
            "role_id" => $user['role_id_fk'],
            "role_name" => $user['role_name'],
            "facility_id" => $user['fac_id_fk'],
            "dept_id" => $user['dept_id'],
            "dist_id" => $user['dist_id'],
            "block_id" => $user['block_id'],
            "division_id" => $user['division_id'],
            "assessment_id" => $user['assessment_id'],
            "token" => $token
        ];

        return [
            "status" => true,
            "message" => "Login successful",
            "data" => $_SESSION['user']
        ];
    }

    /**
     * Handles validate processing for this API workflow.
     */
    public static function validate() {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Handles logout processing for this API workflow.
     */
    public static function logout() {
        session_destroy();
        return true;
    }
}

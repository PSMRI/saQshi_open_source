<?php

/**
 * SaQshi API
 * auth/v1/validate.php
 * Purpose: validate endpoint/support workflow.
 */

require_once "../../../service/AuthService.php";

session_start();

$user = AuthService::validate();

if ($user) {
    echo json_encode(["status" => true, "data" => $user]);
} else {
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
}
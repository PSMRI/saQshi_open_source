<?php
require_once "../../../service/AuthService.php";

session_start();

$user = AuthService::validate();

if ($user) {
    echo json_encode(["status" => true, "data" => $user]);
} else {
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
}
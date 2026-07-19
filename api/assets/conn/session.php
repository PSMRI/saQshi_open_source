<?php

/**
 * SaQshi API
 * assets/conn/session.php
 * Purpose: session endpoint/support workflow.
 */

include('db.php');
session_start();

// Check if the user is logged in by verifying the session.
if (!isset($_SESSION['u_name'])) {
    header("Location: 404.php");
    exit();
}

$user_check = $_SESSION['u_name'];
$stmt = $con->prepare("SELECT u_name FROM s_user WHERE u_name = ?");
$stmt->bind_param("s", $user_check);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $login_session = $row['u_name'];
} else {
    header("Location: 404.php");
    exit();
}

$stmt->close();

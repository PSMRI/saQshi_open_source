<?php

/**
 * SaQshi API
 * assets/conn/session.php
 * Purpose: session endpoint/support workflow.
 */

   include('db.php');
   session_start();
   // Check if the user is logged in by verifying the session
   if (!isset($_SESSION['u_name'])) {
       // User is not logged in, redirect to 404 page
       header("Location: 404.php");
       exit(); // Ensure that no further code is executed after the redirect
   }
   // Sanitize and fetch the username securely
   $user_check = $_SESSION['u_name'];   
   // Prepare SQL statement to prevent SQL injection
   $stmt = $con->prepare("SELECT u_name FROM s_user WHERE u_name = ?");
   $stmt->bind_param("s", $user_check); // "s" for string
   $stmt->execute();
   $result = $stmt->get_result();
   // Check if the user exists in the database
   if ($row = $result->fetch_assoc()) {
       // If user exists, store the session username
       $login_session = $row['u_name'];
   } else {
       // If user not found in the database, redirect to 404 page
       header("Location: 404.php");
       exit(); // Ensure that no further code is executed after the redirect
   }
   // Close the prepared statement
   $stmt->close();   
   // At this point, the user is logged in and session is valid
  ?>

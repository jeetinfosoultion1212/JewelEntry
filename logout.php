<?php
// Initialize the session.
session_start();

// Include database connection
require_once "config/config.php";

// Get user ID before destroying the session
$user_id = $_SESSION['id'] ?? null;

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_me'])) {
    setcookie("remember_me", "", time() - 3600, "/");
}

// Clear remember me token from database if user ID is available
if ($user_id) {
    $update_sql = "UPDATE Firm_Users SET remember_token = NULL, token_expiration = NULL WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $update_sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Unset all of the session variables.
$_SESSION = array();
 
// Destroy the session.
session_destroy();
 
// Redirect to login page
header("location: login.php");
exit;
?> 
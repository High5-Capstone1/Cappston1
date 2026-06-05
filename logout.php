<?php
require_once 'session.php';

// Get the role first (before destroying the session)
$role = $_SESSION['role'] ?? null;

// Destroy the session
session_unset();
session_destroy();

// Redirect based on role
if ($role === 'admin') {
    header("Location: ../roleLogin/adminLogin.php"); // Admin login page
} else {
    header("Location: ../roleLogin/login.php");      // Staff/Cashier login page
}
exit();
?>
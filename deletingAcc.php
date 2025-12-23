<?php
session_start();
include 'DBconnect.php';

// Only admin can delete users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

if (!isset($_GET['user_id'])) {
    die("Invalid request");
}

$user_id = $_GET['user_id'];


if ($user_id == $_SESSION['user_id']) {
    die("You cannot delete your own account");
}


$sql = "DELETE FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

// redirect
header("Location: redirectAdmin/users.php");
exit();
?>

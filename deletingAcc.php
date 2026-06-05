<?php
session_start();
include 'DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

if (!isset($_GET['user_id'])) {
    die("Invalid request");
}

$user_id = (int)$_GET['user_id'];

if ($user_id == $_SESSION['user_id']) {
    die("You cannot delete your own account");
}


$stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();


$stmt = $conn->prepare("DELETE FROM stock_requests WHERE requested_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();


$stmt = $conn->prepare("DELETE FROM attendance WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();


$stmt = $conn->prepare("DELETE FROM sales WHERE cashier_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();


$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

header("Location: redirectAdmin/users.php");
exit();
?>
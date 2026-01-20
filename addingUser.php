<?php
session_start();
include 'DBconnect.php';


if ($_SESSION['role'] !== 'admin') {
    die("Access denied");
}

$name = $_POST['name'];
$username = $_POST['username'];
$password = $_POST['password'];
$role = $_POST['role'];
$store_id = $_POST['store_id'];

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (name, username, password, store_id, role)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssis", $name, $username, $hashedPassword, $store_id, $role);


if ($stmt->execute()) {
        $_SESSION['success'] = "User account successfully created!";
    } else {
        $_SESSION['error'] = "Failed to create user.";
    }


header("Location: redirectAdmin/users.php");
exit();
?>

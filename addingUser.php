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
$stmt->execute();


header("Location: redirectAdmin/users.php");
exit();
?>

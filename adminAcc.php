<?php
include 'DBconnect.php';

$name = "Super Admin";
$username = "Admin@1";
$password = "IvanIta-as@2025"; 
$role = "admin";
$store_id = NULL;

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);


$sql = "INSERT INTO users (name, username, password, store_id, role) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssis", $name, $username, $hashedPassword, $store_id, $role);

if ($stmt->execute()) {
    echo "Admin account created successfully.<br>";
    echo "Username: $username<br>";
    echo "Password: $password";
} else {
    echo "Error: " . $stmt->error;
}
?>

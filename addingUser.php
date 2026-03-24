<?php
session_start();
include 'session.php';
include 'DBconnect.php';

define('ENCRYPTION_KEY', 'MrSoftyCapstone2025SecureKey!@#$');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

function encryptData($data) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
  
    return base64_encode($iv . '::' . $encrypted);
}

if ($_POST['password'] !== $_POST['confirm_password']) {
    $_SESSION['error'] = "Passwords do not match!";
    header("Location: redirectAdmin/users.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    die("Access denied");
}

$name     = encryptData($_POST['name']);
$username = encryptData($_POST['username']);
$email    = encryptData($_POST['email']);
$role     = encryptData($_POST['role']);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT); // password stays hashed
$store_id = $_POST['store_id'];

$sql = "INSERT INTO users (name, username, password, store_id, role, email)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssiss", $name, $username, $password, $store_id, $role, $email);

if ($stmt->execute()) {
    $_SESSION['add_user_success'] = "Account created successfully!";
} else {
    $_SESSION['error'] = "Failed to create user.";
}

header("Location: redirectAdmin/users.php");
exit();
?>
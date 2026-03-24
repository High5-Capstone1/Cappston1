<?php
include 'DBconnect.php';

define('ENCRYPTION_KEY', 'MrSoftyCapstone2025SecureKey!@#$');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

function encryptData($data) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

$conn->query("DELETE FROM users WHERE role LIKE '%admin%' OR username LIKE '%Admin%'");
echo "Deleted old admins. Rows affected: " . $conn->affected_rows . "<br>"; 

$name     = encryptData("Super Admin");
$username = encryptData("Admin@1");
$email    = encryptData("admin@mrsofty.com");
$password = password_hash("IvanIta-as@2025", PASSWORD_DEFAULT);
$role     = encryptData("admin");
$store_id = NULL;

$sql = "INSERT INTO users (name, username, email, password, store_id, role) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssis", $name, $username, $email, $password, $store_id, $role);

if ($stmt->execute()) {
    echo "Admin created successfully!";
} else {
    echo "Error: " . $stmt->error;
}
?>
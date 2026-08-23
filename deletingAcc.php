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

try {
    $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    header("Location: redirectAdmin/users.php");
    exit();

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    die("Failed to remove account. Check error logs for details.");
}
?>
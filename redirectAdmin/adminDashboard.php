<?php
session_start();
include "../DBconnect.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

//fetch user
$sql = "SELECT user_id, name, username, role, store_id FROM users ORDER BY role";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="../Design/forAdminDashboard.css">
<title>Admin Dashboard</title>
</head>

<body>

<div class="sidebar">
    <h2>ADMIN PANEL</h2>
    <a href="adminDashboard.php">🏠 Dashboard</a>
    <a href="inventory.php">📦 Inventory (All Stores)</a>
    <a href="sales.php">💰 Sales Overview</a>
    <a href="attendanceRecords.php">🕒 Attendance Logs</a>
    <a href="expenses.php">🧾 Expenses Summary</a>
    <a href="reports.php">📊 Daily Reports</a>
    <a href="variance.php">⚠ Variance Alerts</a>
    <a href="users.php">👥 Staff Accounts</a>

    <form action="../logout.php" method="POST">
        <button class="logout">Logout</button>
    </form>
</div>


<div class="content">

    <div class="box">
        <h2>Welcome, Admin</h2>
        <p>You have full access to all stores and reports.</p>
    </div>
    
</body>
</html>
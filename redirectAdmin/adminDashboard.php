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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../Design/forAdminDashboard.css">
<title>Admin Dashboard - Mr. Softy</title>
</head>

<body>

<nav class="navbar">
    <div class="navbar-brand">
        <span class="brand-icon"><img src="../img/mrsofty2.png" alt="Mr. Softy Logo" width="100px"></span>
        <span class="brand-name">Mr. Softy</span>
        <span class="brand-subtitle">Admin Panel</span>
    </div>
    <div class="navbar-user">
        <form action="../logout.php" method="POST">
            <button class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
        </form>
    </div>
</nav>

<div class="dashboard-container">
    
    <div class="dashboard-grid">
        
        <a href="adminDashboard.php" class="dashboard-card active">
            <div class="card-icon">🏠</div>
            <div class="card-title">Dashboard</div>
            <div class="card-desc">Overview</div>
        </a>

        <a href="inventory.php" class="dashboard-card">
            <div class="card-icon">📦</div>
            <div class="card-title">Inventory</div>
            <div class="card-desc">All Stores</div>
        </a>

        <a href="sales.php" class="dashboard-card">
            <div class="card-icon">💰</div>
            <div class="card-title">Sales</div>
            <div class="card-desc">Overview</div>
        </a>

        <a href="attendanceRecords.php" class="dashboard-card">
            <div class="card-icon">🕒</div>
            <div class="card-title">Attendance</div>
            <div class="card-desc">Records</div>
        </a>

        <a href="expenses.php" class="dashboard-card">
            <div class="card-icon">🧾</div>
            <div class="card-title">Expenses</div>
            <div class="card-desc">Summary</div>
        </a>

        <a href="reports.php" class="dashboard-card">
            <div class="card-icon">📊</div>
            <div class="card-title">Reports</div>
            <div class="card-desc">Daily</div>
        </a>

        <a href="variance.php" class="dashboard-card">
            <div class="card-icon">⚠️</div>
            <div class="card-title">Variance</div>
            <div class="card-desc">Alerts</div>
        </a>

        <a href="users.php" class="dashboard-card">
            <div class="card-icon">👥</div>
            <div class="card-title">Users</div>
            <div class="card-desc">Staff Accounts</div>
        </a>

    </div>

    <div class="welcome-section">
        <h2>Admin Dashboard</h2>
        <p>You have full access to all stores and reports. Select a section above to get started.</p>
    </div>

</div>

</body>
</html>
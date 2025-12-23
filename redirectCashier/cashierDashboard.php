<?php
session_start();
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

$store_id = $_SESSION['store_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cashier Dashboard</title>
    <link rel="stylesheet" href="../../Design/dashboard.css">
</head>
<body>

<div class="sidebar">
    <h2>Cashier Panel</h2>
    <a href="attendance.php">🕒 Attendance</a>
    <a href="addSales.php">💰 New Sale</a>
    <a href="salesHistory.php">📊 Sales History</a>

    <form action="../../logout.php" method="POST">
        <button class="logout">Logout</button>
    </form>
</div>

<div class="content">
    <p>Welcome: <?= $store_id ?></p>
    <p>You can record attendance and manage sales here</p>
</div>

</body>
</html>

<?php
session_start();
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$store_id = $_SESSION['store_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="../Design/forStaff.css">
</head>
<body>

<h2>Staff Dashboard</h2>
<p>Assigned Store: <?= $store_id ?></p>

<ul>
    <li><a href="attendance.php">🕒 Attendance</a></li>
    <li><a href="inventoryStaff.php">📦 Inventory</a></li>
    <li>
        <form action="../logout.php" method="POST">
            <button type="submit">Logout</button>
        </form>
    </li>
</ul>

</body>
</html>

<?php
session_start();
include '../DBconnect.php';

// check if admin acc 
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add User Account</title>
<link rel="stylesheet" href="../Design/forAdminDashboard.css">
</head>
<body>

<div class="sidebar">
    <h2>ADMIN PANEL</h2>
    <a href="adminDashboard.php">🏠 Dashboard</a>
    <a href="inventory.php">📦 Inventory (All Stores)</a>
    <a href="sales.php">💰 Sales Overview</a>
    <a href="attendance.php">🕒 Attendance Logs</a>
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
        <h2>Add Staff / Cashier Account</h2>
        <form action="../addingUser.php" method="POST">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="text" name="username" placeholder="Username" required>
            <div class="password-wrapper">
             <input type="password" name="password" id="password" placeholder="Password" required>
            <span class="toggle-password" onclick="togglePassword()">👁</span>
            </div>


<p id="passwordError" style="color:red; display:none;">
    Passwords do not match
</p>



            <select name="role" required>
                <option value="">-- Select Role --</option>
                <option value="cashier">Cashier</option>
                <option value="staff">Staff</option>
            </select>

            <input type="number" name="store_id" placeholder="Store ID" required>

            <button type="submit">Create Account</button>
        </form>
    </div>

    <div class="box">
    <h2>Existing Users</h2>
    <?php
    // fetch existing users
    $sql = "SELECT u.user_id, u.name, u.username, u.role, s.store_id, s.location
    FROM users u LEFT JOIN  store s
            ON u.store_id = s.store_id ORDER BY role";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo "<table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Store ID</th>
                    <th>Store Name</>
                    <th>Action</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['user_id']}</td>
                    <td>".htmlspecialchars($row['name'])."</td>
                    <td>".htmlspecialchars($row['username'])."</td>
                    <td>{$row['role']}</td>
                    <td>{$row['store_id']}</td>
                     <td>" . htmlspecialchars($row['location'] ?? 'N/A') . "</td>
                    <td>
                        <a href='../deletingAcc.php?user_id={$row['user_id']}' onclick='return confirm(\"Are you sure you want to delete this user?\")'>Delete</a>
                    </td>
                </tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found.</p>";
    }
    ?>
</div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById("password");

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
    } else {
        passwordInput.type = "password";
    }
}
</script>


</body>
</html>

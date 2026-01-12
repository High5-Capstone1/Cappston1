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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../Design/forUsers.css">
</head>
<body>
    <header class="header">
        <div class="header-container">
            <div class="header-content">
                <div class="header-left">
                    <a href="adminDashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="header-title">
                        <h1>
                            <i class="fas fa-clipboard-list"></i>
                            Staff Accounts  
                        </h1>
                        <p>Manage employee account</p>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <div class="content">

        <div class="page-container">
         <div class="box">
             <div class="logo">
                <img src="../img/mrsofty1.png" alt="Mr. Softy Logo" width="100px">
            </div>
             <div class="subtitle">Signature Creations</div>
              <h2>Add Staff / Cashier Account</h2>
        <form action="../addingUser.php" method="POST">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="text" name="username" placeholder="Username" required>
            <div class="password-wrapper">
             <input type="password" name="password" id="password" placeholder="Password" required>
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
    
    <div class="table">
    <?php
    //fetch existing users
    $sql = "SELECT u.user_id, u.name, u.username, u.role, s.store_id, s.location
    FROM users u LEFT JOIN  store s
            ON u.store_id = s.store_id ORDER BY role";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo "<table>
                <tr>
                    <th><i class='fas fa-hashtag'></i> ID</th>
                    <th><i class='fas fa-user'></i> Name</th>
                    <th><i class='fas fa-user-tag'></i> Username</th>
                    <th><i class='fas fa-user-shield'></i> Role</th>
                    <th><i class='fas fa-store'></i> Store ID</th>
                    <th><i class='fas fa-store-alt'></i> Store Name</>
                    <th><i class='fas fa-cog'></i> Action</th>
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
                        <a href='../deletingAcc.php?user_id={$row['user_id']}' onclick='return confirm(\"Are you sure you want to delete this user?\")'>
                        <i class='fas fa-trash-alt'></i>
                        </a>
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

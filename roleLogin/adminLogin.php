<?php
session_start();
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Design/forIndex.css">
    <title>Admin Login - Mr. Softy</title>

</head>
<body>

<div class="container">

    <div class="logo-section">
        <div class="logo-icon">
            <img src="../img/mrsofty2.png" alt="mrsofty" width="120px" height="120px">
        </div>
        <h1>Mr. Softy</h1>
        <div class="subtitle">Signature Creations</div>
    </div>

    <div class="welcome-section">
        <h2>Admin Login</h2>
        <p>Enter your credentials to continue</p>
    </div>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="../process.php">
         <input type="hidden" name="login_source" value="admin">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="off">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>

        <button type="submit" class="btn-login">Sign In</button>
    </form>

    <a href="../admin_index.php" class="back-link">← Back</a>

    <div class="footer">
        <p> Fresh • Delicious • Daily </p>
    </div>
</div>

</body>
</html> 
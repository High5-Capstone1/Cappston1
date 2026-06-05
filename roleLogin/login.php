<?php
require_once '../session.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login</title>

    <link rel="stylesheet" href="../../Design/login.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="decoration"></div>
    <div class="container">

        <div class="header">
            <img src="../../img/mrsofty2.png" alt="Mr. Softy Logo" class="brand-logo" width="50px">
            <h2>Mr. Softy</h2>
            <p class="sub">Signature Creations</p>
        </div>
        <form action="../process.php" method="POST">
            <div class="form">
                <label>Username or Email</label>
                <div class="input">
                    <input type="text"
                        name="username"
                        placeholder="Enter your Username or Email"
                        required>
                    <span class="icon">👤</span>
                </div>
            </div>

            <div class="form">
                <label>Password</label>
                <div class="input">
                    <input type="password"
                        name="password"
                        id="password"
                        placeholder="Enter your Password"
                        required>

                    <span class="icon" onclick="togglePassword()" style="cursor:pointer;">
                        🔒
                    </span>
                </div>
            </div>

                <div class="options">
                    <a href="forgot_password.php" style="color:#e91e25; font-size:13px; text-decoration:none;">
                        Forgot Password?
                        </a>
                </div>

            <button type="submit">Login</button>

        </form>
    </div>
    <?php if (isset($_SESSION['error'])): ?>
        <script>
            Swal.fire({
                icon: "error",
                title: "Wrong Credential!",
                text: "<?= $_SESSION['error']; ?>",
                confirmButtonColor: "#e91e25"
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>


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
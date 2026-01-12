<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="../../Design/login.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="decoration"></div>

    <div class="container">
        <div class="header">
            <img src="../../img/mrsofty2.png" alt="Mr. Softy Logo" class="brand-logo" width="50px">
            <h2>Mr. Softy</h2>
            <p class="sub">Signature Creations</p>
            </div>
            
            <form action="/process.php" method="POST">
                <div class="form">
                    <label>Username:</label>
                    <div class="input">
                        <input type="text" name="username" placeholder="Enter your Username" required>
                        <span class="icon">👤</span>
                    </div>
                </div>

                <div class="form">
                    <label>Password:</label>
                    <div class="input">
                        <input type="password" name="password" placeholder="Enter your Password" required>
                        <span class="icon">🔒</span>
                    </div>
                </div>

                <div class="options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                </div>

        <button type="submit">Login</button>
    </form>
</div>
    <script>
function togglePassword() {
    const password = document.getElementById("password");
    password.type = password.type === "password" ? "text" : "password";
}
</script>
</body>
</html>
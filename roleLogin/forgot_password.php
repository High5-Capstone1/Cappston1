<?php require_once '../session.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password – Mr. Softy</title>
    <link rel="stylesheet" href="../Design/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="decoration"></div>

<div class="container forgot-card">


    <div class="forgot-header header">
        <img src="../img/mrsofty2.png" alt="Mr. Softy Logo" class="brand-logo" width="50">
        <h2>Mr. Softy</h2>
        <p class="sub">Reset Password</p>
    </div>


    <p class="forgot-hint">
        Enter your registered email address and we'll send you a link to reset your password.
    </p>


    <div class="section-rule"><span>Email address</span></div>


    <form id="forgotForm">

        <div class="forgot-field">
            <label for="email">Email Address</label>
            <div class="input-row">
                <input
                    type="email"
                    id="email"
                    placeholder="Enter your registered email"
                    autocomplete="email"
                    required
                >
                <span class="icon">✉️</span>
            </div>
        </div>

        <button type="submit" id="submitBtn">Send Reset Link</button>

    </form>

    <div class="back-row">
        <a href="login.php">
            <i class="back-arrow">←</i> Back to Login
        </a>
    </div>

</div>

<script>
document.getElementById('forgotForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const email     = document.getElementById('email').value;
    const submitBtn = document.getElementById('submitBtn');

    submitBtn.disabled    = true;
    submitBtn.textContent = 'Sending...';

    await fetch('../process_forgot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=send_reset&email=${encodeURIComponent(email)}`
    });

    Swal.fire({
        icon: 'success',
        title: 'Check Your Email',
        text: 'If that email is registered, a reset link has been sent. Check your inbox and spam folder.',
        confirmButtonColor: '#e91e25'
    }).then(() => window.location.href = 'login.php');
});
</script>
</body>
</html>
<?php require_once '../session.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – Mr. Softy</title>
     <link rel="stylesheet" href="../../Design/forgotP.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   
</head>
<body>

<div class="container">
    <!-- Form Card -->
    <div class="form-card">
        <!-- Header Section Inside Card -->
        <div class="header-section">
            <div class="logo-wrapper">
                <img src="../img/mrsofty1.png" alt="Mr. Softy Logo" class="brand-logo">
            </div>
            <h1 class="brand-title">Mr. Softy</h1>
            <p class="card-subtitle">Reset Password</p>
            <p class="header-description">
                Enter your registered email address and we'll send you a link to reset your password.
            </p>
        </div>

        <form id="forgotForm">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input
                    type="email"
                    id="email"
                    class="form-input"
                    placeholder="Enter your registered email"
                    autocomplete="email"
                    required
                >
            </div>

            <button type="submit" id="submitBtn" class="submit-button">
                SEND RESET LINK
            </button>
        </form>
    </div>

    <!-- Back to Login -->
    <a href="login.php" class="back-link">
        <span>←</span>
        <span>Back to Login</span>
    </a>
</div>

<script>
document.getElementById('forgotForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const email     = document.getElementById('email').value;
    const submitBtn = document.getElementById('submitBtn');

    submitBtn.disabled    = true;
    submitBtn.textContent = 'Sending...';

    try {
        await fetch('../process_forgot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=send_reset&email=${encodeURIComponent(email)}`
        });

        Swal.fire({
            icon: 'success',
            title: 'Check Your Email',
            text: 'If that email is registered, a reset link has been sent. Check your inbox and spam folder.',
            confirmButtonColor: '#2196F3'
        }).then(() => {
            window.location.href = 'login.php';
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred. Please try again.',
            confirmButtonColor: '#2196F3'
        });
        submitBtn.disabled = false;
        submitBtn.textContent = 'SEND RESET LINK';
    }
});
</script>

</body>
</html>
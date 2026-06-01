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
    <title>Admin Login - Mr. Softy</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Design/login.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <!-- ===== PAGE LOADING SKELETON ===== -->
    <div id="page-loader" class="page-loader">
        <div class="loader-card">
            <div class="loader-logo-ring">
                <div class="loader-logo-circle shimmer"></div>
            </div>
            <div class="loader-text-group">
                <div class="loader-bar loader-bar-title shimmer"></div>
                <div class="loader-bar loader-bar-sub shimmer"></div>
                <div class="loader-bar loader-bar-line shimmer"></div>
            </div>
            <div class="loader-bar loader-bar-input shimmer"></div>
            <div class="loader-bar loader-bar-input shimmer"></div>
            <div class="loader-bar loader-bar-link shimmer"></div>
            <div class="loader-bar loader-bar-btn shimmer"></div>
            <div class="loader-bar loader-bar-divider shimmer"></div>
            <div class="loader-bar loader-bar-footer shimmer"></div>
        </div>
    </div>
    <!-- =============================== -->



    <!-- ===== BACKGROUND DECORATIONS ===== -->
    <!-- Morphing Blobs (Skyblue) -->
    <div class="bg-blob bg-blob-1"></div>
    <div class="bg-blob bg-blob-2"></div>
    <div class="bg-blob bg-blob-3"></div>
    <div class="bg-blob bg-blob-4"></div>

    <!-- Grain/Noise Overlay -->
    <div class="bg-grain"></div>

    <!-- Large transparent circles -->
    <div class="bg-circle bg-circle-1"></div>
    <div class="bg-circle bg-circle-2"></div>
    <div class="bg-circle bg-circle-3"></div>

    <!-- Thin diagonal lines -->
    <div class="bg-line bg-line-1"></div>
    <div class="bg-line bg-line-2"></div>
    <div class="bg-line bg-line-3"></div>

    <!-- Small dots pattern -->
    <div class="bg-dot bg-dot-1"></div>
    <div class="bg-dot bg-dot-2"></div>
    <div class="bg-dot bg-dot-3"></div>
    <div class="bg-dot bg-dot-4"></div>
    <div class="bg-dot bg-dot-5"></div>
    <div class="bg-dot bg-dot-6"></div>

    <!-- Floating particles -->
    <div class="bg-particle bg-particle-1"></div>
    <div class="bg-particle bg-particle-2"></div>
    <div class="bg-particle bg-particle-3"></div>
    <div class="bg-particle bg-particle-4"></div>
    <div class="bg-particle bg-particle-5"></div>
    <div class="bg-particle bg-particle-6"></div>
    <div class="bg-particle bg-particle-7"></div>
    <div class="bg-particle bg-particle-8"></div>
    <div class="bg-particle bg-particle-9"></div>
    <div class="bg-particle bg-particle-10"></div>
    <div class="bg-particle bg-particle-11"></div>
    <div class="bg-particle bg-particle-12"></div>

    <!-- Subtle glowing effects -->
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>
    <div class="bg-glow bg-glow-3"></div>

    <!-- Decorative rings -->
    <div class="bg-ring bg-ring-1"></div>
    <div class="bg-ring bg-ring-2"></div>

    <!-- Expanding ripple rings -->
    <div class="bg-ring-expand bg-ring-expand-1"></div>
    <div class="bg-ring-expand bg-ring-expand-2"></div>

    <!-- Floating nebula orbs -->
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>
    <div class="bg-orb bg-orb-3"></div>

    <!-- Warm sun glow -->
    <div class="bg-sun bg-sun-1"></div>
    <div class="bg-sun bg-sun-2"></div>

    <!-- Drifting clouds -->
    <div class="bg-cloud bg-cloud-1"></div>
    <div class="bg-cloud bg-cloud-2"></div>
    <div class="bg-cloud bg-cloud-3"></div>
    <div class="bg-cloud bg-cloud-4"></div>

    <!-- Twinkling sparkle stars -->
    <div class="bg-sparkle bg-sparkle-1"></div>
    <div class="bg-sparkle bg-sparkle-2"></div>
    <div class="bg-sparkle bg-sparkle-3"></div>
    <div class="bg-sparkle bg-sparkle-4"></div>
    <div class="bg-sparkle bg-sparkle-5"></div>
    <div class="bg-sparkle bg-sparkle-6"></div>
    <div class="bg-sparkle bg-sparkle-7"></div>
    <div class="bg-sparkle bg-sparkle-8"></div>

    <!-- Floating bubbles -->
    <div class="bg-bubble bg-bubble-1"></div>
    <div class="bg-bubble bg-bubble-2"></div>
    <div class="bg-bubble bg-bubble-3"></div>
    <div class="bg-bubble bg-bubble-4"></div>
    <div class="bg-bubble bg-bubble-5"></div>

    <!-- Floating Ice Cream Cones (Branding) -->
    <div class="bg-icecream bg-icecream-1"></div>
    <div class="bg-icecream bg-icecream-2"></div>
    <div class="bg-icecream bg-icecream-3"></div>
    <div class="bg-icecream bg-icecream-4"></div>
    <div class="bg-icecream bg-icecream-5"></div>
    <!-- ============================== -->

    <div class="login-container">

        <!-- Logo Area with Orbit Dots -->
        <div class="logo-wrapper">
            <div class="logo-ring"></div>
            <div class="logo-dot dot-1"></div>
            <div class="logo-dot dot-2"></div>
            <div class="logo-dot dot-3"></div>
            <div class="logo-circle">
                <img src="../img/mrsofty1.png" alt="Mr. Softy" class="logo-img">
            </div>
        </div>

        <!-- Title Area -->
        <h1 class="main-title">Mr. Softy</h1>
        <div class="subtitle-row">
            <span class="subtitle-text">Admin Panel</span>
            <div class="title-underline"></div>
        </div>

        <!-- Login Form -->
        <form action="../process.php" method="POST" class="login-form">

            <input type="hidden" name="login_source" value="admin">

            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <input type="text"
                        name="username"
                        id="username"
                        placeholder="Enter your username"
                        required
                        autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password"
                        name="password"
                        id="password"
                        placeholder="Enter your password"
                        required>
                    <button type="button" class="toggle-pass" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-off-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login btn-login-submit">
                <span class="btn-login-text">Sign In</span>
                <span class="btn-login-arrow">&rarr;</span>
            </button>

        </form>

        <!-- Footer -->
        <div class="footer-divider"></div>
        <div class="footer">
            <p>Inventory &bull; Sales &bull; Management</p>
        </div>

    </div>

    <?php if ($error): ?>
        <script>
            Swal.fire({
                icon: "error",
                title: "Login Failed",
                text: "<?= htmlspecialchars($error) ?>",
                confirmButtonColor: "#e91e25"
            });
        </script>
    <?php endif; ?>


    <script>
        function togglePassword() {
            const passwordInput = document.getElementById("password");
            const eyeIcon = document.querySelector(".eye-icon");
            const eyeOffIcon = document.querySelector(".eye-off-icon");

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                if (eyeIcon) eyeIcon.style.display = "none";
                if (eyeOffIcon) eyeOffIcon.style.display = "block";
            } else {
                passwordInput.type = "password";
                if (eyeIcon) eyeIcon.style.display = "block";
                if (eyeOffIcon) eyeOffIcon.style.display = "none";
            }
        }
    </script>

    <script>
        // ── Ripple Effect on Click ──
        function createRipple(e, el) {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var ripple = document.createElement('span');
            ripple.className = 'ripple';
            var rect = el.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var x = (e.clientX || (e.touches && e.touches[0].clientX) || rect.left + rect.width / 2) - rect.left - size / 2;
            var y = (e.clientY || (e.touches && e.touches[0].clientY) || rect.top + rect.height / 2) - rect.top - size / 2;
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            el.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 700);
        }

        var loginForm = document.querySelector('.login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = this.querySelector('.btn-login');
                if (btn) {
                    createRipple(e, btn);
                }
                var form = this;
                setTimeout(function() {
                    form.submit();
                }, 400);
            });
        }

        // Hide loading skeleton once fonts and assets are ready
        (function() {
            var loader = document.getElementById('page-loader');
            if (!loader) return;

            function hideLoader() {
                loader.classList.add('loader-hidden');
                setTimeout(function() {
                    loader.style.display = 'none';
                }, 500);
            }

            // Wait for fonts and window to load
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(hideLoader);
            }
            window.addEventListener('load', hideLoader);

            // Fallback: hide after 3s no matter what
            setTimeout(hideLoader, 3000);
        })();
    </script>

</body>

</html>

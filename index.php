<?php
require_once 'session.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="Design/forIndex.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <title>Mr. Softy - Sales Management System</title>
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
            <div class="loader-text-group">
                <div class="loader-bar loader-bar-heading shimmer"></div>
                <div class="loader-bar loader-bar-desc shimmer"></div>
            </div>
            <div class="loader-bar loader-bar-btn shimmer"></div>
            <div class="loader-bar loader-bar-divider shimmer"></div>
            <div class="loader-bar loader-bar-footer shimmer"></div>
        </div>
    </div>
    <!-- =============================== -->



    <!-- ===== BACKGROUND DECORATIONS ===== -->
    <!-- Morphing Blobs (Ice Cream Flavors) -->
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


    <div class="container">

        <!-- Logo Area with Orbit Dots -->
        <div class="logo-wrapper">
            <div class="logo-ring"></div>
            <div class="logo-dot dot-1"></div>
            <div class="logo-dot dot-2"></div>
            <div class="logo-dot dot-3"></div>
            <div class="logo-circle">
                <img src="img/mrsofty1.png" alt="Mr. Softy" class="logo-img">
            </div>
        </div>

        <!-- Title Area -->
        <h1 class="main-title">Mr. Softy</h1>
        <div class="subtitle-row">
            <span class="subtitle-text">Signature Creations</span>
            <div class="title-underline"></div>
        </div>

        <!-- Section Header -->
        <div class="section-header">
            <h2 class="section-title">Sales Management System</h2>
            <p class="section-desc">Select your role to continue</p>
        </div>

        <!-- Login Button -->
        <a href="roleLogin/login.php" class="role-card staff">
            <div class="btn-text-group">
                <span class="btn-label">Login</span>
                <span class="btn-sub">Manage Sales &amp; Inventory</span>
            </div>
            <div class="btn-arrow">&rarr;</div>
        </a>

        <!-- Footer -->
        <div class="footer-divider"></div>
        <div class="footer">
            <p>Inventory &bull; Sales &bull; Management</p>
        </div>

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

        var loginBtn = document.querySelector('.role-card');
        if (loginBtn) {
            loginBtn.addEventListener('click', function(e) {
                e.preventDefault();
                createRipple(e, this);
                var href = this.getAttribute('href');
                setTimeout(function() {
                    window.location.href = href;
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
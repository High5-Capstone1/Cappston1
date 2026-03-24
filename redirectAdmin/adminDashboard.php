<?php
require_once '../session.php';
include "../DBconnect.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

//fetch user
$sql = "SELECT user_id, name, username, role, store_id FROM users ORDER BY role";
$result = $conn->query($sql);

$adminName = $_SESSION['name'] ?? 'Admin';
$initial   = strtoupper(substr($adminName, 0, 1));
$hour      = (int)date('H');
$greeting  = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mr. Softy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../Design/forAdminDashboard.css">
</head>
<body>


<div class="bg-particles">
    <div class="particle p1"></div>
    <div class="particle p2"></div>
    <div class="particle p3"></div>
    <div class="particle p4"></div>
    <div class="particle p5"></div>
</div>

<nav class="navbar">
    <div class="navbar-brand">
        <div class="brand-logo-box">
            <img src="../img/mrsofty2.png" alt="Mr. Softy">
        </div>
        <div class="brand-info">
            <span class="brand-name">Mr. Softy</span>
            <span class="brand-sub">Admin Panel</span>
        </div>
    </div>

    <div class="navbar-center">
        <div class="nav-time-box">
            <i class="fas fa-clock"></i>
            <span id="live-time"></span>
        </div>
    </div>

    <div class="navbar-right">
        <div class="nav-user-chip">
            <div class="nav-avatar"><?= $initial ?></div>
            <div class="nav-info">
                <span class="nav-name"><?= htmlspecialchars($adminName) ?></span>
                <span class="nav-role">Administrator</span>
            </div>
        </div>
        <form action="../logout.php" method="POST">
            <button class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</nav>

<div class="page-wrapper">

    <div class="hero-card">
        <div class="hero-left">
            <span class="hero-greeting"><?= $greeting ?> 👋</span>
            <h1 class="hero-name"><?= htmlspecialchars($adminName) ?></h1>
            <p class="hero-sub">You have full access to all stores and reports.<br>Choose a module below to get started.</p>
            <div class="hero-meta">
                <div class="meta-pill"><i class="fas fa-calendar-day"></i><?= date('F d, Y') ?></div>
                <div class="meta-pill"><i class="fas fa-store"></i>All Stores</div>
            </div>
        </div>
        <div class="hero-right">
            <div class="hero-logo-wrap">
                <img src="../img/mrsofty2.png" alt="Mr. Softy">
            </div>
        </div>
  
        <div class="hero-blob b1"></div>
        <div class="hero-blob b2"></div>
    </div>


    <div class="modules-heading">
        <span class="modules-label"><i class="fas fa-th-large"></i> Modules</span>
        <span class="modules-count">8 sections</span>
    </div>


    <div class="dashboard-grid">

        <a href="adminDashboard.php" class="dashboard-card" data-color="blue">
            <div class="card-bg-icon">🏠</div>
            <div class="card-inner">
                <div class="card-emoji">🏠</div>
                <div class="card-text">
                    <div class="card-title">Dashboard</div>
                    <div class="card-desc">Overview & Summary</div>
                </div>
                <div class="card-chevron"><i class="fas fa-chevron-right"></i></div>
            </div>
            <div class="card-active-bar"></div>
        </a>

        <a href="inventory.php" class="dashboard-card" data-color="teal">
            <div class="card-bg-icon">📦</div>
            <div class="card-inner">
                <div class="card-emoji">📦</div>
                <div class="card-text">
                    <div class="card-title">Inventory</div>
                    <div class="card-desc">All Store Stocks Request</div>
                </div>
                <div class="card-chevron"><i class="fas fa-chevron-right"></i></div>
            </div>
            <div class="card-active-bar"></div>
        </a>

        <a href="sales.php" class="dashboard-card" data-color="green">
            <div class="card-bg-icon">💰</div>
            <div class="card-inner">
                <div class="card-emoji">💰</div>
                <div class="card-text">
                    <div class="card-title">Sales</div>
                    <div class="card-desc">Revenue Overview</div>
                </div>
                <div class="card-chevron"><i class="fas fa-chevron-right"></i></div>
            </div>
            <div class="card-active-bar"></div>
        </a>

        <a href="attendanceRecords.php" class="dashboard-card" data-color="orange">
            <div class="card-bg-icon">🕒</div>
            <div class="card-inner">
                <div class="card-emoji">🕒</div>
                <div class="card-text">
                    <div class="card-title">Attendance</div>
                    <div class="card-desc">Staff Records</div>
                </div>
                <div class="card-chevron"><i class="fas fa-chevron-right"></i></div>
            </div>
            <div class="card-active-bar"></div>
        </a>

        <a href="expenses.php" class="dashboard-card" data-color="red">
            <div class="card-bg-icon">🧾</div>
            <div class="card-inner">
                <div class="card-emoji">🧾</div>
                <div class="card-text">
                    <div class="card-title">Expenses</div>
                    <div class="card-desc">Cost Summary</div>
                </div>
                <div class="card-chevron"><i class="fas fa-chevron-right"></i></div>
            </div>
            <div class="card-active-bar"></div>
        </a>

        <a href="reports.php" class="dashboard-card" data-color="purple">
            <div class="card-bg-icon">📊</div>
            <div class="card-inner">
                <div class="card-emoji">📊</div>
                <div class="card-text">
                    <div class="card-title">Reports</div>
                    <div class="card-desc">Daily Reports</div>
                </div>
                <div class="card-chevron"><i class="fas fa-chevron-right"></i></div>
            </div>
            <div class="card-active-bar"></div>
        </a>

        <a href="variance.php" class="dashboard-card" data-color="yellow">
            <div class="card-bg-icon">⚠️</div>
            <div class="card-inner">
                <div class="card-emoji">⚠️</div>
                <div class="card-text">
                    <div class="card-title">Variance</div>
                    <div class="card-desc">Stock Alerts</div>
                </div>
                <div class="card-chevron"><i class="fas fa-chevron-right"></i></div>
            </div>
            <div class="card-active-bar"></div>
        </a>

        <a href="users.php" class="dashboard-card" data-color="indigo">
            <div class="card-bg-icon">👥</div>
            <div class="card-inner">
                <div class="card-emoji">👥</div>
                <div class="card-text">
                    <div class="card-title">Users</div>
                    <div class="card-desc">Staff Accounts</div>
                </div>
                <div class="card-chevron"><i class="fas fa-chevron-right"></i></div>
            </div>
            <div class="card-active-bar"></div>
        </a>
    </div>
</div>
<script>

function updateTime() {
    const now = new Date();
    let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    document.getElementById('live-time').textContent =
        `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')} ${ampm}`;
}
updateTime();
setInterval(updateTime, 1000);


const path = window.location.pathname.split('/').pop();
document.querySelectorAll('.dashboard-card').forEach(card => {
    if (card.getAttribute('href') === path) {
        card.classList.add('active');
    }
});
</script>

</body>
</html>
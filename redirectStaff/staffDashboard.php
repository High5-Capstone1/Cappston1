<?php
require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$store_id = $_SESSION['store_id'];
$staff_name = $_SESSION['username'] ?? 'Staff Member';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Store <?= htmlspecialchars($store_id) ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Design/staffDashboard.css">
</head>
<body>


    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>
                <img src="../img/mrsofty2.png" alt="Mr. Softy Logo">
                Mr.Softy Staff
            </h2>
            <div class="sub">
            <p>Signature Creation</p>
            </div>
            <p>Inventory Management</p>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($staff_name, 0, 1)) ?>
            </div>
            <div class="user-details">
                <h3><?= htmlspecialchars($staff_name) ?></h3>
                <p>Store #<?= htmlspecialchars($store_id) ?></p>
            </div>
        </div>

        <nav>
            <a href="dashboard.php" class="active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="attendance.php">
                <i class="fas fa-clock"></i>
                <span>Attendance</span>
            </a>
            
            <a href="inventoryStaff.php">
                <i class="fas fa-boxes"></i>
                <span>Inventory</span>
            </a>
        </nav>

        <div class="logout-section">
            <form action="../logout.php" method="POST">
                <button type="submit">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

 
    <main>
        
        <div class="welcome-card">
            <div>
                <h1>Welcome Back, <?= htmlspecialchars($staff_name) ?>! 🍦</h1>
                <p>Manage your inventory and keep our sweet treats stocked!</p>
            </div>
            <div class="time-info">
                <p class="date">
                    <i class="far fa-calendar"></i>
                    <?= date('l, F j, Y') ?>
                </p>
                <p class="time">
                    <i class="far fa-clock"></i>
                    <span id="currentTime"></span>
                </p>
            </div>
        </div>

   
        <div class="stats-grid">
            <div class="stat-card stat-blue">
                <div class="stat-header">
                    <div class="stat-icon icon-blue">
                        <i class="fas fa-ice-cream"></i>
                    </div>
                    <span class="stat-badge badge-blue">Active</span>
                </div>
                <h3>Total Products</h3>
                <p class="stat-value">0</p>
                <p class="stat-desc">In inventory</p>
            </div>

            <div class="stat-card stat-orange">
                <div class="stat-header">
                    <div class="stat-icon icon-orange">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <span class="stat-badge badge-orange">Alert</span>
                </div>
                <h3>Low Stock Items</h3>
                <p class="stat-value">0</p>
                <p class="stat-desc">Need restock</p>
            </div>

         
            <div class="stat-card stat-green">
                <div class="stat-header">
                    <div class="stat-icon icon-green">
                        <i class="fas fa-store"></i>
                    </div>
                    <span class="stat-badge badge-green">Open</span>
                </div>
                <h3>Store Status</h3>
                <p class="stat-value">Active</p>
                <p class="stat-desc">Operating normally</p>
            </div>
        </div>

        <div class="content-grid">
      
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon icon-blue">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h2>Quick Actions</h2>
                </div>
                
                <div class="actions-list">
                    <a href="attendance.php" class="action-item action-blue">
                        <div class="action-content">
                            <div class="action-icon icon-blue">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="action-info">
                                <h3>Time In/Out</h3>
                                <p>Record your attendance</p>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right"></i>
                    </a>

                    <a href="inventoryStaff.php" class="action-item action-purple">
                        <div class="action-content">
                            <div class="action-icon icon-purple">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="action-info">
                                <h3>Manage Inventory</h3>
                                <p>Update stock levels</p>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right"></i>
                    </a>

                    <a href="stockCheck.php" class="action-item action-orange">
                        <div class="action-content">
                            <div class="action-icon icon-orange">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="action-info">
                                <h3>Stock Check</h3>
                                <p>Verify inventory levels</p>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

           
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon icon-purple">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h2>Today's Tasks</h2>
                </div>

                <div class="tasks-list">
                    <div class="task-item task-blue">
                        <div class="task-check">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <h4>🍦 Morning Stock Check</h4>
                            <p>Verify ice cream and toppings inventory</p>
                        </div>
                    </div>

            
                </div>
            </div>
        </div>

     
    
    </main>

    <script>
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        updateTime();
        setInterval(updateTime, 1000);

        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('nav a').forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html>
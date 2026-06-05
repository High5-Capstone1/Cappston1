<?php
require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

$store_id     = $_SESSION['store_id'] ?? 0;
$store_name   = $_SESSION['store_name'] ?? '';
$cashier_name = $_SESSION['username'] ?? 'Cashier';
$user_id      = $_SESSION['user_id'] ?? 0;
$today        = date('Y-m-d');

// Today's sales stats
$sales_stmt = $conn->prepare("
    SELECT COUNT(*) as total_transactions, COALESCE(SUM(subtotal), 0) as total_sales
    FROM sales
    WHERE store_id = ? AND DATE(sale_date) = ? AND is_deleted = 0
");
$sales_stmt->bind_param("is", $store_id, $today);
$sales_stmt->execute();
$sales_data         = $sales_stmt->get_result()->fetch_assoc();
$total_transactions = $sales_data['total_transactions'] ?? 0;
$total_sales        = $sales_data['total_sales'] ?? 0;

// Recent sales
$recent_stmt = $conn->prepare("
    SELECT s.sale_id, s.subtotal, s.sale_date, s.sale_time
    FROM sales s
    WHERE s.store_id = ? AND s.is_deleted = 0
    ORDER BY s.sale_date DESC, s.sale_time DESC
    LIMIT 5
");
$recent_stmt->bind_param("i", $store_id);
$recent_stmt->execute();
$recent_sales = $recent_stmt->get_result();

// Low stock count
$low_stmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM inventory
    WHERE store_id = ? AND quantity <= low_stock_level
");
$low_stmt->bind_param("i", $store_id);
$low_stmt->execute();
$low_stock_count = $low_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;

// Attendance
$att_stmt = $conn->prepare("
    SELECT time_in, time_out FROM attendance
    WHERE user_id = ? AND DATE(time_in) = ?
    ORDER BY time_in DESC LIMIT 1
");
$att_stmt->bind_param("is", $user_id, $today);
$att_stmt->execute();
$att_row     = $att_stmt->get_result()->fetch_assoc();
$clocked_in  = !empty($att_row['time_in']);
$clocked_out = !empty($att_row['time_out']);

// Total inventory items
$inv_total_stmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM items WHERE status = 'active'
");
$inv_total_stmt->execute();
$total_items = $inv_total_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Store <?= htmlspecialchars($store_name) ?></title>
    <link rel="stylesheet" href="../Design/forCashierDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-cash-register"></i> Cashier Panel</h2>
            <div class="sub">Point of Sale System</div>
        </div>

        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($cashier_name, 0, 1)) ?></div>
            <div class="user-details">
                <h3><?= htmlspecialchars($cashier_name) ?></h3>
                <a href="updateProfile.php" style="font-size:.9rem;color:#15803d;text-decoration:none;">
                    <i class="fas fa-pen-to-square" style="margin-right:4px;"></i>Update Profile
                </a>
            </div>
        </div>

        <nav>
            <a href="cashierDashboard.php" class="active">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <a href="attendance.php">
                <i class="fas fa-clock"></i><span>Attendance</span>
            </a>
            <a href="addSales.php">
                <i class="fas fa-cart-plus"></i><span>New Sale</span>
            </a>
            <a href="salesHistory.php">
                <i class="fas fa-chart-line"></i><span>Sales History</span>
            </a>
            <a href="inventoryCashier.php">
                <i class="fas fa-boxes"></i><span>Inventory</span>
            </a>
            <a href="stockCheck.php">
                <i class="fas fa-clipboard-check"></i><span>Stock Check</span>
            </a>
        </nav>

        <div class="logout-section">
            <form action="../../logout.php" method="POST">
                <button type="submit">
                    <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <main>

        <div class="welcome-card">
            <div>
                <h1>Welcome Back, <?= htmlspecialchars($cashier_name) ?>! 👋</h1>
                <p>Start your day with a smile at Mr.Softy (:</p>
                <p class="store-label">
                    <i class="fas fa-store"></i>Store: <?= htmlspecialchars($store_name) ?>
                </p>
            </div>
            <div class="time-info">
                <p class="date"><i class="far fa-calendar"></i><?= date('l, F j, Y') ?></p>
                <p class="time"><i class="far fa-clock"></i><span id="currentTime"></span></p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon si-blue"><i class="fas fa-receipt"></i></div>
                    <span class="stat-badge sb-blue">Today</span>
                </div>
                <p class="stat-label">TODAY'S TRANSACTIONS</p>
                <h2 class="stat-value"><?= $total_transactions ?></h2>
                <p class="stat-sub">Sales recorded today</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon si-green"><i class="fas fa-peso-sign"></i></div>
                    <span class="stat-badge sb-green">Live</span>
                </div>
                <p class="stat-label">TODAY'S REVENUE</p>
                <h2 class="stat-value">₱<?= number_format($total_sales, 2) ?></h2>
                <p class="stat-sub">Total collected today</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon si-orange"><i class="fas fa-triangle-exclamation"></i></div>
                    <span class="stat-badge <?= $low_stock_count > 0 ? 'sb-orange' : 'sb-green' ?>">
                        <?= $low_stock_count > 0 ? 'Alert' : 'Good' ?>
                    </span>
                </div>
                <p class="stat-label">LOW STOCK ITEMS</p>
                <h2 class="stat-value"><?= $low_stock_count ?></h2>
                <p class="stat-sub">Need restock</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon si-teal"><i class="fas fa-user-check"></i></div>
                    <span class="stat-badge <?= $clocked_in && !$clocked_out ? 'sb-teal' : 'sb-gray' ?>">
                        <?= $clocked_in ? ($clocked_out ? 'Done' : 'Active') : 'Absent' ?>
                    </span>
                </div>
                <p class="stat-label">ATTENDANCE</p>
                <h2 class="stat-value av">
                    <?php if ($clocked_out): ?>Clocked Out
                    <?php elseif ($clocked_in): ?>Clocked In
                    <?php else: ?>Not Yet
                    <?php endif; ?>
                </h2>
                <p class="stat-sub">
                    <?php if ($clocked_in && !$clocked_out): ?>
                        Since <?= date('h:i A', strtotime($att_row['time_in'])) ?>
                    <?php elseif ($clocked_out): ?>
                        Out at <?= date('h:i A', strtotime($att_row['time_out'])) ?>
                    <?php else: ?>
                        Not clocked in today
                    <?php endif; ?>
                </p>
            </div>

        </div>

        <div class="bottom-grid">

   
            <div class="panel">
                <div class="panel-header">
                    <div class="ph-icon si-blue"><i class="fas fa-bolt"></i></div>
                    <h3>Quick Actions</h3>
                </div>
                <div class="qa-list">
                    <a href="attendance.php" class="qa-row">
                        <div class="qa-icon si-blue"><i class="fas fa-clock"></i></div>
                        <div class="qa-text">
                            <strong>Time In / Out</strong>
                            <span>Record your attendance</span>
                        </div>
                        <i class="fas fa-chevron-right qa-arrow"></i>
                    </a>
                    <a href="addSales.php" class="qa-row">
                        <div class="qa-icon si-purple"><i class="fas fa-cart-plus"></i></div>
                        <div class="qa-text">
                            <strong>New Sale</strong>
                            <span>Process a transaction</span>
                        </div>
                        <i class="fas fa-chevron-right qa-arrow"></i>
                    </a>
                    <a href="salesHistory.php" class="qa-row">
                        <div class="qa-icon si-orange"><i class="fas fa-chart-line"></i></div>
                        <div class="qa-text">
                            <strong>Sales History</strong>
                            <span>View past transactions</span>
                        </div>
                        <i class="fas fa-chevron-right qa-arrow"></i>
                    </a>
                    <a href="inventoryCashier.php" class="qa-row">
                        <div class="qa-icon si-green"><i class="fas fa-boxes"></i></div>
                        <div class="qa-text">
                            <strong>Manage Inventory</strong>
                            <span>Request stock replenishment</span>
                        </div>
                        <i class="fas fa-chevron-right qa-arrow"></i>
                    </a>
                    <a href="stockCheck.php" class="qa-row">
                        <div class="qa-icon si-teal"><i class="fas fa-clipboard-check"></i></div>
                        <div class="qa-text">
                            <strong>Stock Check</strong>
                            <span>Verify inventory levels</span>
                        </div>
                        <i class="fas fa-chevron-right qa-arrow"></i>
                    </a>
                </div>
            </div>


            <div class="panel">
                <div class="panel-header">
                    <div class="ph-icon si-green"><i class="fas fa-clock-rotate-left"></i></div>
                    <h3>Recent Transactions</h3>
                    <a href="salesHistory.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <?php if ($recent_sales && $recent_sales->num_rows > 0): ?>
                <div class="recent-list">
                    <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                    <div class="recent-row">
                        <div class="recent-icon"><i class="fas fa-receipt"></i></div>
                        <div class="recent-info">
                            <strong>Sale #<?= $sale['sale_id'] ?></strong>
                            <span><?= date('h:i A', strtotime($sale['sale_time'])) ?> &mdash; <?= date('M d', strtotime($sale['sale_date'])) ?></span>
                        </div>
                        <span class="recent-amount">₱<?= number_format($sale['subtotal'], 2) ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No transactions recorded yet today.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>


        <div class="panel">
            <div class="panel-header">
                <div class="ph-icon si-purple"><i class="fas fa-list-check"></i></div>
                <h3>Today's Tasks</h3>
            </div>
            <div class="tasks-grid">
                <div class="task-row task-done">
                    <div class="task-check checked"><i class="fas fa-check"></i></div>
                    <div class="task-text">
                        <strong>☀️ Morning Stock Check</strong>
                        <span>Verify ice cream and toppings inventory</span>
                    </div>
                    <span class="task-status done">Done</span>
                </div>
                <div class="task-row">
                    <div class="task-check"><i class="fas fa-check"></i></div>
                    <div class="task-text">
                        <strong>🧾 Process Sales</strong>
                        <span>Handle customer transactions accurately</span>
                    </div>
                    <span class="task-status pending">Pending</span>
                </div>
                <div class="task-row">
                    <div class="task-check"><i class="fas fa-check"></i></div>
                    <div class="task-text">
                        <strong>📋 End-of-Day Report</strong>
                        <span>Submit daily sales summary before closing</span>
                    </div>
                    <span class="task-status pending">Pending</span>
                </div>
                <div class="task-row">
                    <div class="task-check"><i class="fas fa-check"></i></div>
                    <div class="task-text">
                        <strong>🌡️ Freezer Temperature Check</strong>
                        <span>Ensure all storage units are at correct temperature</span>
                    </div>
                    <span class="task-status pending">Pending</span>
                </div>
            </div>
        </div>

    </main>

    <script>
        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
        }
        updateTime();
        setInterval(updateTime, 1000);

        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('nav a').forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === currentPage) link.classList.add('active');
        });
    </script>

</body>
</html>
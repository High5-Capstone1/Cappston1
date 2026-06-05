<?php
require_once '../session.php';
include '../DBconnect.php';

define('ENCRYPTION_KEY', 'MrSoftyCapstone2025SecureKey!@#$');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

function decryptData($data) {
    if (empty($data)) return $data;
    $data = base64_decode($data);
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return $data;
    list($iv, $encrypted) = $parts;
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../roleLogin/login.php");
    exit();
}

$filter_store = $_GET['store_id'] ?? '';
$filter_start = $_GET['start_date'] ?? '';
$filter_end   = $_GET['end_date'] ?? '';

//default date display
$today = date('Y-m-d');
if (empty($filter_start) && empty($filter_end)) {
    $filter_start = $today;
    $filter_end = $today;
}

$stores = $conn->query("SELECT store_id, location FROM store ORDER BY location");

$sql = "
    SELECT 
        o.order_id,
        o.order_date,
        o.order_time,
        o.total_amount,
        s.sale_id,
        p.product_name,
        p.size,
        s.quantity,
        s.subtotal,
        u.name AS cashier_name,
        st.location,
        IFNULL(GROUP_CONCAT(
            CONCAT(t.topping_name, ' x', st2.quantity)
            ORDER BY t.topping_name
            SEPARATOR ', '
        ), '') AS toppings
    FROM orders o
    JOIN sales s ON s.order_id = o.order_id
    JOIN products p ON s.product_id = p.product_id
    JOIN users u ON o.cashier_id = u.user_id
    JOIN store st ON o.store_id = st.store_id
    LEFT JOIN sale_toppings st2 ON s.sale_id = st2.sale_id
    LEFT JOIN toppings t ON st2.topping_id = t.topping_id
    WHERE 1=1
";

$params = [];
$types  = "";

if (!empty($filter_store)) {
    $sql .= " AND o.store_id = ?";
    $params[] = $filter_store;
    $types .= "i";
}
if (!empty($filter_start)) {
    $sql .= " AND o.order_date >= ?";
    $params[] = $filter_start;
    $types .= "s";
}
if (!empty($filter_end)) {
    $sql .= " AND o.order_date <= ?";
    $params[] = $filter_end;
    $types .= "s";
}

$sql .= " GROUP BY o.order_id, s.sale_id ORDER BY o.order_date DESC, o.order_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
$grand_total = 0;
while ($row = $result->fetch_assoc()) {
    $oid = $row['order_id'];
    if (!isset($orders[$oid])) {
        $orders[$oid] = [
            'order_id'     => $oid,
            'order_date'   => $row['order_date'],
            'order_time'   => $row['order_time'],
            'total_amount' => $row['total_amount'],
            'cashier_name' => $row['cashier_name'],
            'location'     => $row['location'],
            'items'        => []
        ];
        $grand_total += $row['total_amount'];
    }
    $orders[$oid]['items'][] = [
        'product_name' => $row['product_name'],
        'size'         => $row['size'],
        'quantity'     => $row['quantity'],
        'subtotal'     => $row['subtotal'],
        'toppings'     => $row['toppings'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Sales Inventory Summary</title>
    <link rel="stylesheet" href="../Design/adminSalesHistory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="header">
    <div class="header-container">
        <div class="header-content">
            <div class="header-left">
                <a href="adminDashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="header-title">
                    <h1><i class="fas fa-clipboard-list"></i> Sales Inventory Summary</h1>
                    <p>View all orders grouped by transaction</p>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="container">

    <!-- Filter -->
    <div class="filter-section">
        <div class="filter-header">
            <div class="filter-title">
                <i class="fas fa-sliders-h"></i>
                <h2>Filter Records</h2>
            </div>
        </div>
        <form method="GET" class="filter-form">
            <div class="filter-grid">
                <div class="filter-field">
                    <label>Store
                        <select name="store_id">
                            <option value="">All Stores</option>
                            <?php while ($s = $stores->fetch_assoc()): ?>
                                <option value="<?= $s['store_id'] ?>"
                                    <?= ($filter_store == $s['store_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['location']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </label>
                </div>
                <div class="filter-field">
                    <label>From
                        <input type="date" name="start_date" value="<?= $filter_start ?>">
                    </label>
                </div>
                <div class="filter-field">
                    <label>To
                        <input type="date" name="end_date" value="<?= $filter_end ?>">
                    </label>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-filter">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="sales.php" class="btn btn-reset">
                    <i class="fas fa-rotate-left"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Summary bar -->
    <?php if (count($orders) > 0): ?>
    <div class="summary-bar">
        <div class="count">
            Showing <span><?= count($orders) ?></span> order<?= count($orders) !== 1 ? 's' : '' ?>
        </div>
        <div class="grand-total-badge">
            <i class="fas fa-peso-sign"></i>
            Grand Total: ₱<?= number_format($grand_total, 2) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Orders -->
    <div class="orders-list">
        <?php if (count($orders) > 0): ?>
            <?php foreach ($orders as $order): ?>
            <div class="order-card">

                <!-- Blue header — matches cashier salesHistory -->
                <div class="order-header">
                    <div class="order-header-left">
                        <div class="order-meta-item">
                            <i class="fas fa-receipt"></i>
                            <span class="order-id-badge">Order #<?= $order['order_id'] ?></span>
                        </div>
                        <div class="order-meta-item">
                            <i class="fas fa-calendar-days"></i>
                            <?= date('M d, Y', strtotime($order['order_date'])) ?>
                        </div>
                        <div class="order-meta-item">
                            <i class="fas fa-clock"></i>
                            <?= date('h:i A', strtotime($order['order_time'])) ?>
                        </div>
                        <div class="order-meta-item">
                            <i class="fas fa-cash-register"></i>
                            <?= htmlspecialchars(decryptData($order['cashier_name']) ?? 'N/A') ?>
                        </div>
                        <div class="order-meta-item">
                            <i class="fas fa-location-dot"></i>
                            <?= htmlspecialchars($order['location']) ?>
                        </div>
                    </div>
                    <div class="order-total-badge">
                        ₱<?= number_format($order['total_amount'], 2) ?>
                    </div>
                </div>

                <!-- Column headers -->
                <div class="items-header">
                    <span><i class="fas fa-ice-cream"></i> Product</span>
                    <span><i class="fas fa-ruler"></i> Size</span>
                    <span><i class="fas fa-plus-circle"></i> Toppings</span>
                    <span><i class="fas fa-hashtag"></i> Qty</span>
                    <span style="justify-content:flex-end;"><i class="fas fa-peso-sign"></i> Subtotal</span>
                </div>

                <!-- Item rows -->
                <?php foreach ($order['items'] as $item): ?>
                <div class="item-row">
                    <div class="item-product"><?= htmlspecialchars($item['product_name']) ?></div>
                    <div class="item-size"><?= htmlspecialchars($item['size']) ?></div>
                    <div class="item-toppings <?= empty($item['toppings']) ? 'none' : '' ?>">
                        <?= !empty($item['toppings']) ? htmlspecialchars($item['toppings']) : '—' ?>
                    </div>
                    <div class="item-qty"><?= $item['quantity'] ?></div>
                    <div class="item-subtotal">₱<?= number_format($item['subtotal'], 2) ?></div>
                </div>
                <?php endforeach; ?>

            </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="no-data-card">
                <i class="fas fa-inbox"></i>
                <p>No sales data found</p>
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
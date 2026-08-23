<?php
require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../roleLogin/login.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];
$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d');

$stmt = $conn->prepare("
    SELECT 
        o.order_id,
        o.order_date,
        o.order_time,
        o.total_amount,
        o.discount_type,
        o.discount_amount,
        o.discount_id_number,
        s.sale_id,
        p.product_name,
        p.size,
        s.quantity,
        s.subtotal,
        IFNULL(GROUP_CONCAT(
            CONCAT(t.topping_name, ' x', st.quantity)
            ORDER BY t.topping_name
            SEPARATOR ', '
        ), '') AS toppings
    FROM orders o
    JOIN sales s ON s.order_id = o.order_id
    JOIN products p ON s.product_id = p.product_id
    LEFT JOIN sale_toppings st ON s.sale_id = st.sale_id
    LEFT JOIN toppings t ON st.topping_id = t.topping_id
    WHERE o.cashier_id = ?
      AND o.order_date BETWEEN ? AND ?
    GROUP BY o.order_id, s.sale_id
    ORDER BY o.order_date DESC, o.order_time DESC
");
$stmt->bind_param("iss", $cashier_id, $from, $to);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
$grand_total = 0;
while ($row = $result->fetch_assoc()) {
    $oid = $row['order_id'];
    if (!isset($orders[$oid])) {
        $orders[$oid] = [
            'order_id'           => $oid,
            'order_date'         => $row['order_date'],
            'order_time'         => $row['order_time'],
            'total_amount'       => $row['total_amount'],
            'discount_type'      => $row['discount_type'],
            'discount_amount'    => $row['discount_amount'],
            'discount_id_number' => $row['discount_id_number'],
            'items'              => []
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
<html>
<head>
    <title>Sales History</title>
    <link rel="stylesheet" href="../Design/forCashierSaleHistory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .order-group { margin-bottom: 18px; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .order-group-header { background: #0ea5e9; color: #fff; padding: 10px 18px; display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 0.95rem; flex-wrap: wrap; gap: 8px; }
        .order-group-header .order-meta { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
        .order-group-header .order-total-block { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
        .order-group-header .order-total { font-size: 1.05rem; }
        .order-group-header .order-subtotal-strike { font-size: 0.78rem; font-weight: 500; opacity: 0.85; text-decoration: line-through; }
        .order-group table { width: 100%; border-collapse: collapse; background: #fff; }
        .order-group table th { background: #f0f9ff; color: #0369a1; font-size: 0.82rem; padding: 8px 14px; text-align: left; }
        .order-group table td { padding: 9px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .order-group table tr:last-child td { border-bottom: none; }
        .topping-text { color: #e91e8c; font-style: italic; font-size: 0.82rem; }
        .no-orders { text-align: center; padding: 40px; color: #94a3b8; }
        .order-num { background: rgba(255,255,255,0.25); border-radius: 20px; padding: 2px 10px; font-size: 0.82rem; }
        .discount-badge { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 20px; padding: 3px 12px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .discount-row { background: #fef2f2; padding: 8px 18px; font-size: 0.82rem; color: #b91c1c; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; border-top: 1px solid #fee2e2; }
        .discount-row .discount-id { font-weight: 600; }
    </style>
</head>
<body>
<header class="header">
    <div class="header-container">
        <div class="header-content">
            <div class="header-left">
                <a href="cashierDashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="header-title">
                    <h1><i class="fas fa-clipboard-list"></i> Sales History</h1>
                    <p></p>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="container">
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
                    <label>From:</label>
                    <input type="date" name="from" value="<?= $from ?>">
                </div>
                <div class="filter-field">
                    <label>To:</label>
                    <input type="date" name="to" value="<?= $to ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-filter">
                    <i class="fa-solid fa-magnifying-glass"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <?php if (empty($orders)): ?>
        <div class="no-orders">
            <i class="fas fa-receipt" style="font-size:2.5rem;margin-bottom:10px;display:block;"></i>
            No orders found for this date range.
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <?php
                $has_discount = !empty($order['discount_type']) && $order['discount_amount'] > 0;
                $items_subtotal = array_sum(array_column($order['items'], 'subtotal'));
            ?>
            <div class="order-group">
                <div class="order-group-header">
                    <div class="order-meta">
                        <span><i class="fas fa-receipt" style="margin-right:6px;"></i>Order #<?= $order['order_id'] ?></span>
                        <span><i class="fas fa-calendar" style="margin-right:4px;"></i><?= date('M d, Y', strtotime($order['order_date'])) ?></span>
                        <span><i class="fas fa-clock" style="margin-right:4px;"></i><?= date('h:i A', strtotime($order['order_time'])) ?></span>
                        <?php if ($has_discount): ?>
                            <span class="discount-badge">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($order['discount_type']) ?> -12%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="order-total-block">
                        <?php if ($has_discount): ?>
                            <span class="order-subtotal-strike">₱<?= number_format($items_subtotal, 2) ?></span>
                        <?php endif; ?>
                        <span class="order-total">₱<?= number_format($order['total_amount'], 2) ?></span>
                    </div>
                </div>

                <?php if ($has_discount): ?>
                    <div class="discount-row">
                        <span>
                            <i class="fas fa-id-card" style="margin-right:5px;"></i>
                            <?= htmlspecialchars($order['discount_type']) ?> ID:
                            <span class="discount-id"><?= htmlspecialchars($order['discount_id_number'] ?? 'N/A') ?></span>
                        </span>
                        <span>Discount Applied: -₱<?= number_format($order['discount_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>

                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-ice-cream"></i> Product</th>
                            <th><i class="fas fa-ruler"></i> Size</th>
                            <th><i class="fas fa-bowl-food"></i> Toppings</th>
                            <th><i class="fas fa-hashtag"></i> Qty</th>
                            <th><i class="fas fa-money-bill-wave"></i> Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order['items'] as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= htmlspecialchars($item['size']) ?></td>
                                <td>
                                    <?php if ($item['toppings']): ?>
                                        <span class="topping-text"><?= htmlspecialchars($item['toppings']) ?></span>
                                    <?php else: ?>
                                        <span style="color:#cbd5e1;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $item['quantity'] ?></td>
                                <td>₱<?= number_format($item['subtotal'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="total">
        💰 Total Sales: ₱<?= number_format($grand_total, 2) ?>
    </div>
</div>
</body>
</html>
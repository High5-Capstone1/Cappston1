<?php
require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

$cashier_id = $_SESSION['user_id'];
$store_id   = $_SESSION['store_id'];


$last_sale_ids = $_SESSION['last_sale_ids'] ?? [];
unset($_SESSION['last_sale_ids']); 


$last_order_id = $_SESSION['last_order_id'] ?? null;
unset($_SESSION['last_order_id']); 

$sales = [];
if (!empty($last_sale_ids)) {
    $placeholders = implode(',', array_fill(0, count($last_sale_ids), '?'));
    $types = str_repeat('i', count($last_sale_ids));
    $stmt = $conn->prepare("
        SELECT s.sale_id, s.product_id, s.quantity, s.subtotal, s.sale_date, s.sale_time,
               p.product_name, p.price
        FROM sales s
        JOIN products p ON s.product_id = p.product_id
        WHERE s.sale_id IN ($placeholders)
        ORDER BY s.sale_id ASC
    ");
    $stmt->bind_param($types, ...$last_sale_ids);
    $stmt->execute();
    $sales_result = $stmt->get_result();
    while ($row = $sales_result->fetch_assoc()) {
        $sales[] = $row;
    }
}

$sale_toppings = [];
foreach ($sales as $sale) {
    $t_stmt = $conn->prepare("
        SELECT st.quantity, t.topping_name, t.price AS topping_price
        FROM sale_toppings st
        JOIN toppings t ON st.topping_id = t.topping_id
        WHERE st.sale_id = ?
    ");
    $t_stmt->bind_param("i", $sale['sale_id']);
    $t_stmt->execute();
    $t_result = $t_stmt->get_result();
    while ($t_row = $t_result->fetch_assoc()) {
        $sale_toppings[$sale['sale_id']][] = $t_row;
    }
}

$subtotal = array_sum(array_column($sales, 'subtotal'));


$discount_type   = null;
$discount_amount = 0.00;
$grand_total     = $subtotal;

if ($last_order_id) {
    $o_stmt = $conn->prepare("
        SELECT total_amount, discount_type, discount_amount
        FROM orders
        WHERE order_id = ?
    ");
    $o_stmt->bind_param("i", $last_order_id);
    $o_stmt->execute();
    $o_result = $o_stmt->get_result();
    if ($order_row = $o_result->fetch_assoc()) {
        $grand_total     = $order_row['total_amount'];
        $discount_type   = $order_row['discount_type'];
        $discount_amount = $order_row['discount_amount'];
    }
}

$has_discount = $discount_type && $discount_amount > 0;
$receipt_no   = strtoupper(substr(md5(uniqid()), 0, 8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Mr. Softy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../Design/forReceipt.css"
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php if ($success_message): ?>
    <div class="success-banner">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>

    <div class="receipt-wrapper">
        <div class="receipt-card">

            <div class="receipt-header">
                <div class="brand-logo">
                    <img src="../img/mrsofty1.png" alt="Mr. Softy">
                </div>
                <div class="brand-tagline">YOUR RECEIPT</div>
                <div class="receipt-meta">
                    <div class="meta-chip">
                        <div class="meta-label">Date</div>
                        <div class="meta-value"><?= date('m/d/Y') ?></div>
                    </div>
                    <div class="meta-chip">
                        <div class="meta-label">Time</div>
                        <div class="meta-value"><?= date('h:i A') ?></div>
                    </div>
                    <div class="meta-chip">
                        <div class="meta-label">Cashier</div>
                        <div class="meta-value"><?= htmlspecialchars($_SESSION['name'] ?? 'Cashier') ?></div>
                    </div>
                    <div class="meta-chip">
                        <div class="meta-label">Receipt #</div>
                        <div class="meta-value"><?= $receipt_no ?></div>
                    </div>
                </div>
            </div>

            <div class="receipt-body">

                <div class="section-label">Order Items</div>

                <div class="order-items">
                    <?php if (empty($sales)): ?>
                        <p style="color:var(--gray-500); font-size:0.85rem; text-align:center;">No items found.</p>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                        <div class="order-item">
                            <div class="item-main">
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($sale['product_name']) ?></div>

                                </div>
                               <?php
    $topping_total = 0;
    if (!empty($sale_toppings[$sale['sale_id']])) {
        foreach ($sale_toppings[$sale['sale_id']] as $t) {
            $topping_total += $t['topping_price'] * $t['quantity'];
        }
    }
    $product_only_price = $sale['subtotal'] - $topping_total;
?>
<div class="item-qty-price">
    <div class="item-price">₱<?= number_format($product_only_price, 2) ?></div>
    <div class="item-qty">x<?= $sale['quantity'] ?></div>
</div>
                            </div>

                            <?php if (!empty($sale_toppings[$sale['sale_id']])): ?>
                            <div class="toppings-list">
                                <?php foreach ($sale_toppings[$sale['sale_id']] as $t): ?>
                                <div class="topping-row">
                                    <span class="topping-name"><?= htmlspecialchars($t['topping_name']) ?> x<?= $t['quantity'] ?></span>
                                    <span>₱<?= number_format($t['topping_price'] * $t['quantity'], 2) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($has_discount): ?>
                <div class="total-row">
                    <span class="total-label">Subtotal</span>
                    <span class="total-value">₱<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="total-row">
                    <span class="total-label" style="color:#e74c3c;">
                        <?= htmlspecialchars($discount_type) ?> Discount (12%)
                    </span>
                    <span class="total-value" style="color:#e74c3c;">
                        -₱<?= number_format($discount_amount, 2) ?>
                    </span>
                </div>
                <?php endif; ?>

                <div class="total-row main">
                    <span class="total-label grand">Total Amount</span>
                    <span class="total-value grand">₱<?= number_format($grand_total, 2) ?></span>
                </div>

            </div>

            <div class="receipt-zigzag"></div>

            <div class="receipt-footer">
                <div class="thank-you">Thank you for your order!</div>
                <div class="footer-note">We hope to see you again soon 🍦</div>
                <div class="action-buttons">
                    <a href="addSales.php" class="btn btn-outline">
                        <i class="fas fa-plus"></i> New Order
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
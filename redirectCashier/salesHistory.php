<?php
session_start();
include '../DBconnect.php';


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../roleLogin/login.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];

//filter
$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d');

// sale
$sql = "
    SELECT 
        s.sale_id,
        p.product_name,
        p.size,
        s.quantity,
        s.subtotal,
        s.sale_date,
        s.sale_time,
        IFNULL(GROUP_CONCAT(t.topping_name SEPARATOR ', '), '') AS toppings
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    LEFT JOIN sale_toppings st ON s.sale_id = st.sale_id
    LEFT JOIN toppings t ON st.topping_id = t.topping_id
    WHERE s.cashier_id = ?
      AND s.sale_date BETWEEN ? AND ?
    GROUP BY s.sale_id, p.product_name, p.size, s.quantity, s.subtotal, s.sale_date, s.sale_time
    ORDER BY s.sale_date DESC, s.sale_time DESC
";


$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $cashier_id, $from, $to);
$stmt->execute();
$sales = $stmt->get_result();

//total sale
$total_sales = 0;
while ($row = $sales->fetch_assoc()) {
    $total_sales += $row['subtotal'];
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales History</title>
    <link rel="stylesheet" href="../Design/forCashierSaleHistory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <h1>
                            <i class="fas fa-clipboard-list"></i>
                            Sales History
                        </h1>
                        <p></p>
                    </div>
                </div>
                <div class="header-right">
                    <p></p>
                    <p class="admin-name"></p>
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
    <button type="submit" class="btn btn-filter"><i class="fa-solid fa-magnifying-glass"></i>Filter</button>
</div>
</form>
</div>


<table>
    <tr>
        <th><i class="fa-solid fa-calendar-days"></i>Date</th>
        <th><i class="fa-solid fa-clock"></i>Time</th>
        <th><i class="fa-solid fa-ice-cream"></i>Product</th>
        <th><i class="fa-solid fa-bowl-food"></i>Toppings</th>
        <th><i class="fa-solid fa-ruler"></i>Size</th>
        <th><i class="fa-solid fa-hashtag"></i>Qty</th>
        <th><i class="fa-solid fa-money-bill-wave"></i>Subtotal</th>
    </tr>

    <?php if (!empty($rows)): ?>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= $row['sale_date'] ?></td>
            <td><?= date("h:i A", strtotime($row['sale_time'])) ?></td>
            <td><?= $row['product_name'] ?></td>
            <td><?= $row['toppings'] ?? '-' ?></td>
            <td><?= $row['size'] ?></td>
            <td><?= $row['quantity'] ?></td>
            <td>₱<?= number_format($row['subtotal'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="6">No sales found</td>
        </tr>
    <?php endif; ?>
</table>

<div class="total">
    💰 Total Sales: ₱<?= number_format($total_sales, 2) ?>
</div>
    </div>

</body>
</html>

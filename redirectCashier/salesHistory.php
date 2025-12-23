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
</head>
<body>
<a href="cashierDashboard.php">Back</a>
<h1>📊 Sales History</h1>


<form method="GET">
    <label>From:</label>
    <input type="date" name="from" value="<?= $from ?>">

    <label>To:</label>
    <input type="date" name="to" value="<?= $to ?>">

    <button type="submit">Filter</button>
</form>


<table>
    <tr>
        <th>Date</th>
        <th>Time</th>
        <th>Product</th>
        <th>Toppings</th>
        <th>Size</th>
        <th>Qty</th>
        <th>Subtotal</th>
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

</body>
</html>

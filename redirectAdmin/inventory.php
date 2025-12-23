<?php
session_start();
include '../DBconnect.php';


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../roleLogin/login.php");
    exit();
}

// sale summary
$sql = "
    SELECT 
    p.product_name,
    p.size,
    SUM(s.quantity) AS total_qty,
    SUM(IFNULL(sts.total_topping,0)) AS toppings_total,
    SUM(s.subtotal + IFNULL(sts.total_topping, 0)) AS total_sales,
    u.name AS cashier_name,
    st.location
FROM sales s
JOIN products p ON s.product_id = p.product_id
JOIN users u ON s.cashier_id = u.user_id
JOIN store st ON s.store_id = st.store_id
LEFT JOIN (
    SELECT st.sale_id, SUM(t.price) AS total_topping
    FROM sale_toppings st
    JOIN toppings t ON st.topping_id = t.topping_id
    GROUP BY st.sale_id
) sts ON s.sale_id = sts.sale_id
GROUP BY p.product_name, p.size, s.cashier_id, s.store_id
ORDER BY total_sales DESC
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin | Sales Inventory Summary</title>
    <link rel="stylesheet" href="../Design/forInventory.css">
</head>
<body>
    <a href="adminDashboard.php">Back</a>

<h1>📦 Sales Inventory Summary (Admin)</h1>

<table>
    <tr>
        <th>Product</th>
        <th>Size</th>
        <th>Total Qty Sold</th>
        <th>Toppings Sales</th>
        <th>Total Sales</th>
        <th>Cashier</th>
        <th>Store Location</th>
    </tr>

    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['product_name'] ?></td>
            <td><?= $row['size'] ?></td>
            <td><?= $row['total_qty'] ?></td>
            <td class="money">₱<?= number_format($row['toppings_total'], 2) ?></td>
            <td class="money">₱<?= number_format($row['total_sales'], 2) ?></td>
            <td><?= $row['cashier_name'] ?></td>
            <td><?= $row['location'] ?></td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="6">No sales data available</td>
        </tr>
    <?php endif; ?>
</table>

</body>
</html>

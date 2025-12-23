<?php
session_start();
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../roleLogin/login.php");
    exit();
}

if (!isset($_GET['sale_id'])) {
    die("Sale ID not provided");
}

$sale_id = intval($_GET['sale_id']);

//sale info
$sale = $conn->query("
    SELECT s.sale_id, s.quantity, s.subtotal, s.sale_date, s.sale_time,
           p.product_name, p.size, p.price,
           u.name AS cashier_name
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN users u ON s.cashier_id = u.user_id
    WHERE s.sale_id = $sale_id
")->fetch_assoc();

if (!$sale) {
    die("Sale not found");
}
// sale topping
$toppings = $conn->query("
    SELECT t.topping_name, t.price
    FROM sale_toppings st
    JOIN toppings t ON st.topping_id = t.topping_id
    WHERE st.sale_id = $sale_id
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt - Sale #<?= $sale_id ?></title>
    <link rel="stylesheet" href="../Design/forReceipt.css">

</head>
<body>
<a href="addSales.php">Back</a>
<h2>🍦 Mr.Softy Ice Cream</h2>
<p>Cashier: <?= $sale['cashier_name'] ?><br>
Date: <?= $sale['sale_date'] ?> <?= date("h:i A", strtotime($sale['sale_time'])) ?></p>

<table>
    <tr>
        <th>Item</th>
        <th>Qty</th>
        <th>Price</th>
    </tr>
    <tr>
        <td><?= $sale['product_name'] ?> (<?= $sale['size'] ?>)</td>
        <td><?= $sale['quantity'] ?></td>
        <td>₱<?= number_format($sale['price'] * $sale['quantity'],2) ?></td>
    </tr>
    <?php
    $topping_total = 0;
    while($t = $toppings->fetch_assoc()) {
        $topping_total += $t['price'];
        echo "<tr>
                <td>+ {$t['topping_name']}</td>
                <td>1</td>
                <td>₱".number_format($t['price'],2)."</td>
              </tr>";
    }
    ?>
    <tr class="total">
        <td colspan="2">Total</td>
        <td>₱<?= number_format($sale['subtotal'],2) ?></td>
    </tr>
</table>

<p class="center">
    Thank you for your purchase!<br>
    <button onclick="window.print()">Print Receipt</button>
</p>
</body>
</html>

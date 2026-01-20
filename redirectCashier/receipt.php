<?php
session_start();
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../roleLogin/login.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];
$store_id   = $_SESSION['store_id'];


if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    die("Cart is empty");
}

//remove order
if (isset($_POST['remove_item'])) {
    $index = intval($_POST['remove_item']); 
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); 
    }
    header("Location: receipt.php"); 
    exit();
}


if (isset($_POST['confirm_order'])) {

    foreach ($_SESSION['cart'] as $item) {

        $conn->query("
            INSERT INTO sales
            (cashier_id, store_id, product_id, quantity, subtotal, sale_date, sale_time)
            VALUES
            ($cashier_id, $store_id, {$item['product_id']},
             {$item['quantity']}, {$item['subtotal']},
             CURDATE(), CURTIME())
        ");

        $sale_id = $conn->insert_id;

        foreach ($item['toppings'] as $t) {
            $conn->query("
                INSERT INTO sale_toppings (sale_id, topping_id, quantity)
                VALUES ($sale_id, {$t['topping_id']}, {$t['qty']})
            ");
        }
    }

    unset($_SESSION['cart']); //clear cart after saving
    $_SESSION['success_message'] = "Order confirmed and saved!";
    header("Location: addSales.php?success=1");
    exit();
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Receipt - Cart</title>
    <link rel="stylesheet" href="../Design/forReceipt.css">
</head>
<body>
    <a href="addSales.php">← Add More Items</a>
    <h2>🧾 Order Summary - Mr.Softy Ice Cream</h2>

    <table border="1" width="100%">
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Action</th>
        </tr>

        <?php
        $grand_total = 0;
        foreach ($_SESSION['cart'] as $index => $item):
            $grand_total += $item['subtotal'];
        ?>
        <tr>
            <td><?= htmlspecialchars($item['product_name']) ?> (<?= htmlspecialchars($item['size']) ?>)</td>
            <td><?= $item['quantity'] ?></td>
            <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
            <td>
                <form method="POST" style="margin:0;">
                    <button type="submit" name="remove_item" value="<?= $index ?>">Remove</button>
                </form>
            </td>
        </tr>

        <?php foreach ($item['toppings'] as $t): ?>
        <tr>
            <td>+ <?= htmlspecialchars($t['name']) ?></td>
            <td><?= $t['qty'] ?></td>
            <td>₱<?= number_format($t['subtotal'],2) ?></td>
            
            <td></td>
        </tr>
        <?php endforeach; ?>

        <?php endforeach; ?>

        <tr>
            <th colspan="2">TOTAL</th>
            <th>₱<?= number_format($grand_total,2) ?></th>
            <th></th>
        </tr>
    </table>

    <form method="POST">
        <button type="submit" name="confirm_order" class="btn-submit">
            Confirm Order
        </button>
    </form>
</body>
</html>

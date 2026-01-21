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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <a href="addSales.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Add More Items
    </a>

    <div class="receipt-container">
        <div class="receipt-paper">
            <div class="receipt-header">
                <div class="store-logo">
                    <img src="../img/mrsofty1.png" alt="" width="120px">
                </div>
                <div class="store-tagline">Signature Creations</div>
                <div class="receipt-info">
                    <div><span>Date:</span><span><?= date('m/d/Y') ?></span></div>
                    <div><span>Time:</span><span><?= date('h:i A') ?></span></div>
                    <div><span>Cashier:</span><span><?= $_SESSION['name'] ?? 'Cashier' ?></span></div>
                    <div><span>Receipt #:</span><span><?= rand(10000, 99999) ?></span></div>
                </div>
            </div>

            <div class="receipt-body">
                <div class="section-title">Order Items</div>

                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $grand_total = 0;
                        foreach ($_SESSION['cart'] as $index => $item):
                            $grand_total += $item['subtotal'];
                        ?>
                        <tr>
                            <td>
                                <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div class="item-size">(<?= htmlspecialchars($item['size']) ?>)</div>
                            </td>
                            <td><?= $item['quantity'] ?></td>
                            <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <button type="submit" name="remove_item" value="<?= $index ?>" class="remove-btn">Remove</button>
                                </form>
                            </td>
                        </tr>

                        <?php foreach ($item['toppings'] as $t): ?>
                        <tr class="topping-row">
                            <td class="item-name"><?= htmlspecialchars($t['name']) ?></td>
                            <td><?= $t['qty'] ?></td>
                            <td>₱<?= number_format($t['subtotal'],2) ?></td>
                            <td></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="receipt-total">
                    <div class="total-row">
                        <span class="total-label">Total</span>
                        <span class="total-amount">₱<?= number_format($grand_total,2) ?></span>
                    </div>
                </div>
            </div>

            <div class="receipt-footer">
                <form method="POST">
                    <button type="submit" name="confirm_order" class="confirm-btn">
                        <i class="fas fa-check-circle"></i> Confirm Order
                    </button>
                </form>
                <div class="thank-you">Thank you for your order!</div>
                <div class="thank-you">Please come again 😊</div>
                <div class="barcode">|||  ||  |||  |  ||  |||</div>
            </div>
        </div>
    </div>
</body>
</html>

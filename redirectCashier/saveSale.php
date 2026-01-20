<?php
session_start();
include '../DBconnect.php';

$cashier_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    die("Cart is empty");
}

if (isset($_POST['confirm_order'])) {

    foreach ($_SESSION['cart'] as $item) {
        $stmt = $conn->prepare("
            INSERT INTO sales (cashier_id, store_id, product_id, quantity, subtotal, sale_date, sale_time)
            VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME())
        ");
        $stmt->bind_param("iiiid", $cashier_id, $store_id, $item['product_id'], $item['quantity'], $item['subtotal']);
        $stmt->execute();
        $sale_id = $stmt->insert_id;
        $stmt->close();

        foreach ($item['toppings'] as $t) {
            $stmt = $conn->prepare("
                INSERT INTO sale_toppings (sale_id, topping_id, quantity)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iii", $sale_id, $t['topping_id'], $t['qty']);
            $stmt->execute();
            $stmt->close();
        }
    }

    unset($_SESSION['cart']);
    $_SESSION['success_message'] = "Order confirmed and saved!";
    header("Location: addSales.php");
    exit();
}
?>

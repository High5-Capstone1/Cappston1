<?php
include '../DBconnect.php';
session_start();

$cashier_id = $_SESSION['user_id'];
$store_id   = $_SESSION['store_id'];
$product_id = $_POST['product_id'];
$quantity   = $_POST['quantity'];
$toppings   = $_POST['toppings'] ?? [];

$product = $conn->query("SELECT price FROM products WHERE product_id = $product_id")->fetch_assoc();

$topping_price = count($toppings) * 5;
$subtotal = ($product['price'] + $topping_price) * $quantity;

$conn->query("
    INSERT INTO sales (cashier_id, store_id, product_id, quantity, subtotal, sale_date, sale_time)
    VALUES ($cashier_id, $store_id, $product_id, $quantity, $subtotal, CURDATE(), CURTIME())
");

$sale_id = $conn->insert_id;

foreach ($toppings as $topping_id) {
    $conn->query("
        INSERT INTO sale_toppings (sale_id, topping_id)
        VALUES ($sale_id, $topping_id)
    ");
}

header("Location: salesHistory.php");

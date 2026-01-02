<?php
session_start();
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../roleLogin/login.php");
    exit();
}

if (isset($_SESSION['success_message'])) {
    echo '<p class="success">' . $_SESSION['success_message'] . '</p>';
    unset($_SESSION['success_message']);
}

$cashier_id = $_SESSION['user_id'];
$store_id   = $_SESSION['store_id'];


$productQuery = $conn->query("
    SELECT DISTINCT product_name 
    FROM products 
    ORDER BY product_name
");

$allProducts = [];
$result = $conn->query("SELECT * FROM products WHERE status='active'");
while ($row = $result->fetch_assoc()) {
    $allProducts[] = $row;
}

//topping
$toppings = $conn->query("SELECT * FROM toppings WHERE status='active'");

//save sale
if (isset($_POST['save_sale'])) {

    $product_id  = $_POST['product_id'];
    $quantity    = $_POST['quantity'];
    $topping_ids = $_POST['toppings'] ?? [];


    $product = $conn->query("
        SELECT price FROM products WHERE product_id = $product_id
    ")->fetch_assoc();
    $product_price = $product['price'];


    $topping_total = 0;
    if (!empty($topping_ids)) {
        $ids = implode(',', $topping_ids);
        $res = $conn->query("
            SELECT SUM(price) AS total 
            FROM toppings 
            WHERE topping_id IN ($ids)
        ");
        $topping_total = $res->fetch_assoc()['total'];
    }

    
    $subtotal = ($product_price + $topping_total) * $quantity;

    
    $conn->query("
        INSERT INTO sales 
        (cashier_id, store_id, product_id, quantity, subtotal, sale_date, sale_time)
        VALUES 
        ($cashier_id, $store_id, $product_id, $quantity, $subtotal, CURDATE(), CURTIME())
    ");

    $sale_id = $conn->insert_id;

    
    foreach ($topping_ids as $topping_id) {
        $conn->query("
            INSERT INTO sale_toppings (sale_id, topping_id)
            VALUES ($sale_id, $topping_id)
        ");
    }

    $_SESSION['success_message'] = "✅ Sale successfully saved!";

    header("Location: receipt.php? sale_id=".$sale_id);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cashier - Add Sale</title>
    <link rel="stylesheet" href="../Design/forAddSales.css">
</head>
<body>
 <a href="cashierDashboard.php">Back</a>
<h2>Add Sale</h2>

<form method="POST">

    
    <label>Product</label>
    <select id="product_name" required>
        <option value="">Select Product</option>
        <?php while ($p = $productQuery->fetch_assoc()): ?>
            <option value="<?= $p['product_name'] ?>">
                <?= $p['product_name'] ?>
            </option>
        <?php endwhile; ?>
    </select>

  
    <label>Size</label>
    <select name="product_id" id="size" required>
        <option value="">Select Size</option>
    </select>

    
    <label>Price (₱)</label>
    <input type="text" id="price" readonly>

    
    <label>Quantity</label>
    <input type="number" name="quantity" min="1" value="1" required>

    
    <h3>Add Toppings (+₱5 each)</h3>
    <?php while ($t = $toppings->fetch_assoc()): ?>
        <label>
            <input type="checkbox" name="toppings[]" value="<?= $t['topping_id'] ?>">
            <?= $t['topping_name'] ?> (+₱<?= $t['price'] ?>) <br>
        </label>
    <?php endwhile; ?>

    <button type="submit" name="save_sale">Submit</button>
</form>

<script>
const products = <?= json_encode($allProducts); ?>;

document.getElementById('product_name').addEventListener('change', function () {
    const sizeSelect = document.getElementById('size');
    const priceInput = document.getElementById('price');
    sizeSelect.innerHTML = '<option value="">Select Size</option>';
    priceInput.value = '';

    products.forEach(p => {
        if (p.product_name === this.value) {
            const option = document.createElement('option');
            option.value = p.product_id;
            option.text  = `${p.size} - ₱${p.price}`;
            option.dataset.price = p.price;
            sizeSelect.appendChild(option);
        }
    });
});

document.getElementById('size').addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    document.getElementById('price').value = selected.dataset.price || '';
});
</script>

</body>
</html>

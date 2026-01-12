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

    $_SESSION['success_message'] = " Sale successfully saved!";

    header("Location: receipt.php? sale_id=".$sale_id);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cashier - Add Sale</title>
    <link rel="stylesheet" href="../Design/forAddSales.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
 <header class="header">
        <div class="header-container">
            <div class="header-content">
                <div class="header-left">
                    <a href="cashierDashboard.php" class="back">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="header-title">
                        <h1>
                            <i class="fa-solid fa-cart-shopping"></i>
                            Sales
                        </h1>
                        <p>Track your work hours</p>
                    </div>
                </div>
                <div class="header-right">
                    <p>Store #<?= htmlspecialchars($store_id) ?></p>
                    <p class="username"><?= htmlspecialchars($username) ?></p>
                </div>
            </div>
        </div>
    </header>

<div class="form-container">
     <div class="header-logo">
            <img src="../../img/mrsofty1.png" alt="Mr. Softy Logo" class="brand-logo" width="50px">
            </div>
<form method="POST">
    <div class="form-row">
     <div class="form-group">
      <label>Product</label>
      <select id="product_name" class="form-control" required>
        <option value="">Select Product</option>
        <?php while ($p = $productQuery->fetch_assoc()): ?>
            <option value="<?= $p['product_name'] ?>">
                <?= $p['product_name'] ?>
            </option>
        <?php endwhile; ?>
      </select>
     </div>

     <div class="form-group">
      <label>Size</label>
      <select name="product_id" id="size" class="form-control" required>
        <option value="">Select Size</option>
      </select>
     </div>
    </div>

    <div class="form-row">
     <div class="form-group">
      <label>Price (₱)</label>
      <input type="text" id="price" class="form-control" readonly>
     </div>

     <div class="form-group">
      <label>Quantity</label>
      <input type="number" name="quantity" class="form-control" min="1" value="1" required>
     </div>
    </div>

    <div class="toppings-section">
     <h3>🍰 Add Toppings</h3>
     <div class="toppings-grid">
      <?php while ($t = $toppings->fetch_assoc()): ?>
       <div class="topping-card">
        <label>
            <input type="checkbox" name="toppings[]" value="<?= $t['topping_id'] ?>">
            <span class="topping-name"><?= $t['topping_name'] ?></span>
            <span class="topping-price">+₱<?= $t['price'] ?></span>
        </label>
       </div>
      <?php endwhile; ?>
     </div>
    </div>

    <button type="submit" name="save_sale" class="btn-submit">💳 Complete Sale</button>
</form>
</div>

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
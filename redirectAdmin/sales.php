<?php
require_once '../session.php';
include '../DBconnect.php';

define('ENCRYPTION_KEY', 'MrSoftyCapstone2025SecureKey!@#$');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

function decryptData($data)
{
    if (empty($data)) return $data;
    $data = base64_decode($data);
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return $data; 
    list($iv, $encrypted) = $parts;
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}



if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../roleLogin/login.php");
    exit();
}


$filter_store = $_GET['store_id'] ?? '';
$filter_start = $_GET['start_date'] ?? '';
$filter_end   = $_GET['end_date'] ?? '';


$stores = $conn->query("SELECT store_id, location FROM store ORDER BY location");


$sql = "
SELECT 
    p.product_name,
    p.size,
    SUM(s.subtotal) AS total_sales,
    SUM(s.quantity) AS total_qty,
    SUM(IFNULL(sts.total_topping,0)) AS toppings_total,
    SUM(s.subtotal - IFNULL(sts.total_topping, 0)) AS product_total,
    u.name AS cashier_name,
    st.location,
    s.sale_date
FROM sales s
JOIN products p ON s.product_id = p.product_id
JOIN users u ON s.cashier_id = u.user_id
JOIN store st ON s.store_id = st.store_id
LEFT JOIN (
    SELECT st.sale_id, SUM(t.price * st.quantity) AS total_topping
    FROM sale_toppings st
    JOIN toppings t ON st.topping_id = t.topping_id
    GROUP BY st.sale_id     
) sts ON s.sale_id = sts.sale_id
WHERE 1=1
";

$params = [];
$types  = "";


// filter 
if (!empty($filter_store)) {
    $sql .= " AND s.store_id = ?";
    $params[] = $filter_store;
    $types .= "i";
}


if (!empty($filter_start)) {
    $sql .= " AND s.sale_date >= ?";
    $params[] = $filter_start;
    $types .= "s";
}


if (!empty($filter_end)) {
    $sql .= " AND s.sale_date <= ?";
    $params[] = $filter_end;
    $types .= "s";
}

$sql .= "
GROUP BY p.product_name, p.size, s.cashier_id, s.store_id, st.location, s.sale_date
ORDER BY s.sale_date DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$sales = $stmt->get_result();

$total_sales = 0;
$rows = [];
while ($row = $sales->fetch_assoc()) {
    $rows[] = $row;
}
$grand_total = 0;
foreach ($rows as $row) {
    $grand_total += $row['total_sales'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Sales Inventory Summary</title>
    <link rel="stylesheet" href="../Design/forInventory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<header class="header">
        <div class="header-container">
            <div class="header-content">
                <div class="header-left">
                    <a href="adminDashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="header-title">
                        <h1>
                            <i class="fas fa-clipboard-list"></i>
                            Sales Inventory Summary
                        </h1>
                        <p>Manage all employee attendance</p>
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
    <label>
        Store:
        <select name="store_id">
            <option value="">All Stores</option>
            <?php while ($s = $stores->fetch_assoc()): ?>
                <option value="<?= $s['store_id'] ?>"
                    <?= ($filter_store == $s['store_id']) ? 'selected' : '' ?>>
                    <?= $s['location'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </label>
    </div>
    <div class="filter-field">
    <label>
        From:
        <input type="date" name="start_date" value="<?= $filter_start ?>">
    </label>
    </div>

    <div class="filter-field">
    <label>
        To:
        <input type="date" name="end_date" value="<?= $filter_end ?>">
    </label>
    </div>
     </div>
    <div class="filter-actions">
    <button type="submit" class="btn btn-filter">🔍 Filter</button>
    <a href="sales.php" class="btn btn-reset">Reset</a>
    </div>
</form>
    </div>


<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th><i class="fa-solid fa-box-open"></i>Product</th>
                <th><i class="fa-solid fa-ruler"></i>Size</th>
                <th><i class="fa-solid fa-hashtag"></i>Total Qty Sold</th>
                <th><i class="fa-solid fa-receipt"></i>Total Product Price</th>
                <th><i class="fa-solid fa-circle-plus"></i>Toppings Sales</th>
                <th><i class="fa-solid fa-peso-sign"></i>Total Sales</th>
                <th><i class="fa-solid fa-cash-register"></i>Cashier</th>
                <th><i class="fa-solid fa-location-dot"></i>Store Location</th>
                <th><i class="fa-solid fa-calendar-days"></i>Sale Date</th>

            </tr>
        </thead>
        <tbody>
       <?php if (count($rows) > 0): ?>
    <?php foreach ($rows as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['product_name']) ?></td>
        <td><?= htmlspecialchars($row['size']) ?></td>
        <td><?= $row['total_qty'] ?></td>
        <td class="money">₱<?= number_format($row['product_total'], 2) ?></td>
        <td class="money">₱<?= number_format($row['toppings_total'], 2) ?></td>
        <td class="money">₱<?= number_format($row['total_sales'], 2) ?></td>
        <td><?= htmlspecialchars(decryptData($row['cashier_name']) ?? 'N/A')?> </td>
        <td><?= htmlspecialchars($row['location']) ?></td>
        <td><?= $row['sale_date'] ?></td>

    </tr>
    <?php endforeach; ?>

    
    <tr style="font-weight:bold;">
        <td colspan="5">Grand Total</td>
        <td>₱<?= number_format($grand_total, 2) ?></td>
        <td colspan="4"></td>
    </tr>
<?php else: ?>
    <tr>
        <td colspan="10">No sales data found</td>
    </tr>
<?php endif; ?>


        </tbody>
    </table>
</div>
</div>

</body>
</html>

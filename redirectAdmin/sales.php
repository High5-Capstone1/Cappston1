<?php
session_start();
include '../DBconnect.php';


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
    SUM(s.quantity) AS total_qty,
    SUM(IFNULL(sts.total_topping,0)) AS toppings_total,
    SUM(s.subtotal + IFNULL(sts.total_topping, 0)) AS total_sales,
    u.name AS cashier_name,
    st.location,
    s.sale_date
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
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Sales Inventory Summary</title>
    <link rel="stylesheet" href="../Design/forInventory.css">
</head>
<body>


    <a href="adminDashboard.php" class="back-link"> Back</a>
</div>

<h1> Sales Inventory Summary</h1>

<form method="GET" class="filter-form">
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

    <label>
        From:
        <input type="date" name="start_date" value="<?= $filter_start ?>">
    </label>

    <label>
        To:
        <input type="date" name="end_date" value="<?= $filter_end ?>">
    </label>

    <button type="submit">🔍 Filter</button>
    <a href="sales.php" class="reset-link">Reset</a>
</form>


<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Size</th>
                <th>Total Qty Sold</th>
                <th>Toppings Sales</th>
                <th>Total Sales</th>
                <th>Cashier</th>
                <th>Store Location</th>
                <th>Sale Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= htmlspecialchars($row['size']) ?></td>
                    <td><?= $row['total_qty'] ?></td>
                    <td class="money">₱<?= number_format($row['toppings_total'], 2) ?></td>
                    <td class="money">₱<?= number_format($row['total_sales'], 2) ?></td>
                    <td><?= htmlspecialchars($row['cashier_name']) ?></td>
                    <td><?= htmlspecialchars($row['location']) ?></td>
                    <td><?= $row['sale_date'] ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No sales data found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>

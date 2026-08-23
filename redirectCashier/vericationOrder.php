<?php
require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];
$store_id   = $_SESSION['store_id'];

if (isset($_POST['remove_item'])) {
    $index = intval($_POST['remove_item']);
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    header("Location: vericationOrder.php");
    exit();
}

if (isset($_POST['apply_discount_confirm'])) {
    $id_number = trim($_POST['id_number'] ?? '');
    $discount_type_input = $_POST['discount_type_input'] ?? '';

    $pwd_pattern = '/^\d{2}-\d{4}-\d{3}-\d{3,7}$/';

    if ($id_number === '' || !in_array($discount_type_input, ['Senior Citizen', 'PWD'], true)) {
        $_SESSION['discount_error'] = "Please enter the ID number and select a discount type.";
    } elseif ($discount_type_input === 'PWD' && !preg_match($pwd_pattern, $id_number)) {
        $_SESSION['discount_error'] = "Invalid PWD ID format. Expected format: RR-PPMM-BBB-NNN (e.g. 13-5416-000-001).";
    } else {
        $_SESSION['apply_discount']     = true;
        $_SESSION['discount_type']      = $discount_type_input;
        $_SESSION['discount_id_number'] = $id_number;
    }
    header("Location: vericationOrder.php");
    exit();
}

if (isset($_POST['remove_discount'])) {
    unset($_SESSION['apply_discount'], $_SESSION['discount_type'], $_SESSION['discount_id_number']);
    header("Location: vericationOrder.php");
    exit();
}

function deductInventory($conn, $store_id, $product_id, $product_qty)
{
    $stmt = $conn->prepare("
        SELECT item_id, quantity_needed
        FROM product_items
        WHERE product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $materials = $stmt->get_result();
    while ($row = $materials->fetch_assoc()) {
        $deduct_qty = $row['quantity_needed'] * $product_qty;
        $update = $conn->prepare("
            UPDATE inventory
            SET quantity = quantity - ?
            WHERE store_id = ? AND item_id = ?
        ");
        $update->bind_param("dii", $deduct_qty, $store_id, $row['item_id']);
        $update->execute();
    }
}


const SENIOR_PWD_DISCOUNT_RATE = 0.12;

if (isset($_POST['confirm_order'])) {
    $conn->begin_transaction();
    $_SESSION['last_sale_ids'] = [];
    try {

        $grand_total = array_sum(array_column($_SESSION['cart'], 'subtotal'));

        $apply_discount     = $_SESSION['apply_discount'] ?? false;
        $discount_amount    = $apply_discount ? round($grand_total * SENIOR_PWD_DISCOUNT_RATE, 2) : 0.00;
        $final_total        = $grand_total - $discount_amount;
        $discount_type      = $apply_discount ? ($_SESSION['discount_type'] ?? null) : null;
        $discount_id_number = $apply_discount ? ($_SESSION['discount_id_number'] ?? null) : null;

        $stmt = $conn->prepare("
            INSERT INTO orders (cashier_id, store_id, total_amount, discount_type, discount_amount, discount_id_number, order_date, order_time)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())
        ");
        $stmt->bind_param("iidsds", $cashier_id, $store_id, $final_total, $discount_type, $discount_amount, $discount_id_number);
        $stmt->execute();
        $order_id = $conn->insert_id;
        $_SESSION['last_order_id'] = $order_id;

        foreach ($_SESSION['cart'] as $item) {
            $stmt = $conn->prepare("
                INSERT INTO sales (cashier_id, store_id, product_id, quantity, subtotal, sale_date, sale_time, order_id)
                VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?)
            ");
            $stmt->bind_param("iiiddi", $cashier_id, $store_id, $item['product_id'], $item['quantity'], $item['subtotal'], $order_id);
            $stmt->execute();
            $sale_id = $conn->insert_id;
            $_SESSION['last_sale_ids'][] = $sale_id;

            foreach ($item['toppings'] as $t) {
                $stmt = $conn->prepare("INSERT INTO sale_toppings (sale_id, topping_id, quantity) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $sale_id, $t['topping_id'], $t['qty']);
                $stmt->execute();
            }
            deductInventory($conn, $store_id, $item['product_id'], $item['quantity']);
        }
        $conn->commit();
        unset($_SESSION['cart']);
        unset($_SESSION['apply_discount']);
        unset($_SESSION['discount_type'], $_SESSION['discount_id_number']);
        $_SESSION['success_message'] = "Order Successfully Confirmed";
        header("Location: vericationOrder.php?success=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("POS Transaction Failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Verification - Mr. Softy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../Design/forVerication.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>

    <div class="topbar">
        <div class="topbar-left">
            <a href="addSales.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="topbar-title">
                <img src="../img/mrsofty1.png" alt="Mr. Softy">
                <div>
                    <h1>Order Verification</h1>
                    <span>Review before confirming</span>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <?= htmlspecialchars($_SESSION['name'] ?? 'Cashier') ?>
            <small><?= date('F d, Y · h:i A') ?></small>
        </div>
    </div>

    <div class="page-wrapper">

        <div class="page-heading">
            <div class="page-heading-icon">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div>
                <h2>Verify Your Order</h2>
                <p>Review all items below before placing the order</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-shopping-bag"></i>
                <h3>Order Items</h3>
                <span class="item-count"><?= count($_SESSION['cart'] ?? []) ?> item(s)</span>
            </div>

            <?php
            $grand_total = 0;
            foreach ($_SESSION['cart'] ?? [] as $item) {
                $grand_total += $item['subtotal'];
            }

            $apply_discount  = $_SESSION['apply_discount'] ?? false;
            $discount_amount = $apply_discount ? round($grand_total * SENIOR_PWD_DISCOUNT_RATE, 2) : 0.00;
            $final_total     = $grand_total - $discount_amount;

            $discount_type_display = $_SESSION['discount_type'] ?? null;
            $discount_id_display   = $_SESSION['discount_id_number'] ?? null;
            ?>

            <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-shopping-cart"></i></div>
                    <h3>Your cart is empty</h3>
                    <p>Go back and add some items to get started.</p>
                </div>
            <?php else: ?>
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="th-center">Qty</th>
                            <th style="text-align:right">Subtotal</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($_SESSION['cart'] ?? [] as $index => $item):
                        ?>
                            <tr class="item-row">
                                <td>
                                    <div class="product-cell">
                                        <div class="product-icon"><i class="fas fa-ice-cream"></i></div>
                                        <div>
                                            <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <div class="product-size"><?= htmlspecialchars($item['size']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="th-center">
                                    <span class="qty-badge"><?= $item['quantity'] ?></span>
                                </td>
                                <td class="price-cell">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                <td class="action-cell">
                                    <form method="POST" style="margin:0;">
                                        <button type="submit" name="remove_item" value="<?= $index ?>" class="remove-btn">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <?php foreach ($item['toppings'] as $t): ?>
                                <tr class="topping-row">
                                    <td colspan="2">
                                        <span class="topping-pill">
                                            <i class="fas fa-circle"></i>
                                            <?= htmlspecialchars($t['name']) ?> &times; <?= $t['qty'] ?>
                                        </span>
                                    </td>
                                    <td class="topping-price">₱<?= number_format($t['subtotal'], 2) ?></td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($_SESSION['cart'])): ?>

        <div class="card" style="margin-top:16px;">
            <div class="card-header">
                <i class="fas fa-id-card"></i>
                <h3>Senior / PWD Discount</h3>
            </div>

            <?php if ($apply_discount): ?>
                <div style="padding:16px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <div>
                        <p style="margin:0; font-weight:600; color:#333;">
                            Discount Applied (12%) — <?= htmlspecialchars($discount_type_display) ?>
                        </p>
                        <p style="margin:4px 0 0; font-size:13px; color:#777;">
                            ID No: <?= htmlspecialchars($discount_id_display) ?>
                        </p>
                    </div>
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="remove_discount" value="1" class="remove-btn" style="white-space:nowrap;">
                            <i class="fas fa-times-circle"></i> Remove Discount
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div style="padding:16px;">
                    <button type="button" id="showDiscountFormBtn" class="btn btn-secondary" style="white-space:nowrap;">
                        <i class="fas fa-plus-circle"></i> Add Discount
                    </button>

                    <div id="discountFloatPanel" class="discount-float-panel">
                        <form method="POST" id="discountForm">
                            <div style="margin-bottom:12px;">
                                <label style="font-size:13px; color:#777; display:block; margin-bottom:4px;">Discount Type</label>
                                <select name="discount_type_input" id="discountTypeInput" required
                                    style="width:100%; padding:8px; border-radius:8px; border:1px solid #ddd;">
                                    <option value="" disabled selected>Select type</option>
                                    <option value="Senior Citizen">Senior Citizen</option>
                                    <option value="PWD">PWD</option>
                                </select>
                            </div>
                            <div style="margin-bottom:8px;">
                                <label id="idNumberLabel" style="font-size:13px; color:#777; display:block; margin-bottom:4px;">ID Number</label>
                                <input type="text" name="id_number" id="idNumberInput" required
                                    placeholder="Select discount type first"
                                    disabled
                                    style="width:100%; padding:8px; border-radius:8px; border:1px solid #ddd; background:#f2f2f2;">
                                <small id="idFormatHint" style="display:block; margin-top:4px; font-size:12px; color:#999;"></small>
                                <small id="idFormatError" style="display:none; margin-top:4px; font-size:12px; color:#e74c3c;"></small>
                            </div>
                            <div style="display:flex; gap:8px; justify-content:flex-end;">
                                <button type="button" id="cancelDiscountBtn" class="btn btn-secondary" style="white-space:nowrap;">
                                    Cancel
                                </button>
                                <button type="submit" name="apply_discount_confirm" value="1" class="btn btn-confirm" style="white-space:nowrap;">
                                    <i class="fas fa-check-circle"></i> Apply Discount (12%)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($_SESSION['discount_error'])): ?>
                    <p style="padding:0 16px 12px; color:#e74c3c; font-size:13px;">
                        <?= htmlspecialchars($_SESSION['discount_error']) ?>
                    </p>
                    <?php unset($_SESSION['discount_error']); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="summary-card">
            <div class="summary-body">
                <div class="summary-row">
                    <span class="label"><i class="fas fa-layer-group" style="color:var(--sky-400);margin-right:6px;"></i>Items</span>
                    <span class="value"><?= count($_SESSION['cart'] ?? []) ?> product(s)</span>
                </div>
                <div class="summary-row">
                    <span class="label"><i class="fas fa-user" style="color:var(--sky-400);margin-right:6px;"></i>Cashier</span>
                    <span class="value"><?= htmlspecialchars($_SESSION['name'] ?? 'Cashier') ?></span>
                </div>
                <div class="summary-row">
                    <span class="label"><i class="fas fa-calendar" style="color:var(--sky-400);margin-right:6px;"></i>Date</span>
                    <span class="value"><?= date('F d, Y') ?></span>
                </div>

                <?php if ($apply_discount): ?>
                <div class="summary-row">
                    <span class="label"><i class="fas fa-receipt" style="color:var(--sky-400);margin-right:6px;"></i>Subtotal</span>
                    <span class="value">₱<?= number_format($grand_total, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span class="label"><i class="fas fa-tag" style="color:#e74c3c;margin-right:6px;"></i><?= htmlspecialchars($discount_type_display) ?> Discount (12%)</span>
                    <span class="value" style="color:#e74c3c;">-₱<?= number_format($discount_amount, 2) ?></span>
                </div>
                <?php endif; ?>

                <hr class="summary-divider">
                <div class="summary-total">
                    <span class="total-label"><i class="fas fa-receipt"></i> Total Amount</span>
                    <span class="total-amount">₱<?= number_format($final_total, 2) ?></span>
                </div>
            </div>
        </div>

        <div class="actions">
            <a href="addSales.php" class="btn btn-secondary">
                <i class="fas fa-plus"></i> Add More Items
            </a>
            <?php if (!empty($_SESSION['cart'])): ?>
                <form method="POST" id="confirmForm" style="flex:1; display:flex;">
                    <button type="submit" name="confirm_order" value="1" class="btn btn-confirm" style="flex:1;">
                        <i class="fas fa-check-circle"></i> Confirm Order
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-confirm" disabled style="flex:1;">
                    <i class="fas fa-check-circle"></i> Confirm Order
                </button>
            <?php endif; ?>
        </div>

    </div>


<?php if (isset($_GET['success']) && isset($_SESSION['success_message'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    Swal.fire({
        icon: "success",
        title: "<?= $_SESSION['success_message']; ?>",
        showConfirmButton: false,
        timer: 1500,
        allowOutsideClick: false,
        allowEscapeKey: false
    });

    setTimeout(() => {
        window.location.href = "receipt.php";
    }, 1600);
});
</script>
<?php
unset($_SESSION['success_message']);
endif;
?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const showBtn    = document.getElementById("showDiscountFormBtn");
    const cancelBtn  = document.getElementById("cancelDiscountBtn");
    const panel      = document.getElementById("discountFloatPanel");
    const typeInput  = document.getElementById("discountTypeInput");
    const idInput    = document.getElementById("idNumberInput");
    const idLabel    = document.getElementById("idNumberLabel");
    const hint       = document.getElementById("idFormatHint");
    const errorMsg   = document.getElementById("idFormatError");
    const form       = document.getElementById("discountForm");

    if (!showBtn || !panel) return;

    const pwdPattern = /^\d{2}-\d{4}-\d{3}-\d{3,7}$/;

    showBtn.addEventListener("click", function () {
        panel.classList.add("open");
        showBtn.style.display = "none";
    });

    cancelBtn.addEventListener("click", function () {
        panel.classList.remove("open");
        showBtn.style.display = "inline-flex";
        form.reset();
        idInput.disabled = true;
        idInput.style.background = "#f2f2f2";
        idLabel.textContent = "ID Number";
        idInput.placeholder = "Select discount type first";
        hint.textContent = "";
        errorMsg.style.display = "none";
    });

    typeInput.addEventListener("change", function () {
        idInput.disabled = false;
        idInput.style.background = "#fff";
        idInput.value = "";
        errorMsg.style.display = "none";

        if (typeInput.value === "PWD") {
            idLabel.textContent = "PWD ID Number";
            idInput.placeholder = "13-5416-000-001";
          
        } else if (typeInput.value === "Senior Citizen") {
            idLabel.textContent = "Senior Citizen ID Number";
            idInput.placeholder = "Enter OSCA ID number";
           
        } else {
            idLabel.textContent = "ID Number";
            idInput.placeholder = "Select discount type first";
            hint.textContent = "";
            idInput.disabled = true;
            idInput.style.background = "#f2f2f2";
        }
    });

    form.addEventListener("submit", function (e) {
        errorMsg.style.display = "none";

        if (typeInput.value === "PWD" && !pwdPattern.test(idInput.value.trim())) {
            e.preventDefault();
            errorMsg.textContent = "Invalid PWD ID format. Expected RR-PPMM-BBB-NNN (e.g. 13-5416-000-001).";
            errorMsg.style.display = "block";
            idInput.focus();
        }
    });
});
</script>

</body>
</html>
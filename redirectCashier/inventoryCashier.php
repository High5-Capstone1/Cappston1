<?php
require_once '../session.php';
include '../DBconnect.php';

// Only cashier role allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

$store_id   = $_SESSION['store_id'];
$cashier_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mark notifications as read (AJAX)
if (isset($_GET['mark_read']) && $_GET['mark_read'] === '1') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $cashier_id, $store_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit();
}

// Fetch notifications
$stmt = $conn->prepare("
    SELECT notif_id, item_name, requested_qty, status, is_read, created_at, updated_at
    FROM notifications
    WHERE user_id = ? AND store_id = ?
    ORDER BY updated_at DESC
    LIMIT 5
");
$stmt->bind_param("ii", $cashier_id, $store_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$unread_count  = count(array_filter($notifications, fn($n) => !$n['is_read']));

$message = '';

// Handle stock request submission
if (isset($_POST['request_add'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF validation failed!");
    }

    $item_id       = (int)$_POST['item_id'];
    $requested_qty = (int)$_POST['requested_qty'];

    if ($requested_qty <= 0) {
        $message = "Invalid quantity.";
    } else {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO inventory (store_id, item_id, quantity, low_stock_level)
            VALUES (?, ?, 0, 30)
        ");
        $stmt->bind_param("ii", $store_id, $item_id);
        $stmt->execute();

        $stmt = $conn->prepare("
            SELECT quantity, low_stock_level
            FROM inventory
            WHERE store_id = ? AND item_id = ?
        ");
        $stmt->bind_param("ii", $store_id, $item_id);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();

        if ($inv['quantity'] <= $inv['low_stock_level']) {
            $stmt = $conn->prepare("
                SELECT 1 FROM stock_requests
                WHERE store_id = ? AND item_id = ? AND status = 'pending'
            ");
            $stmt->bind_param("ii", $store_id, $item_id);
            $stmt->execute();

            if ($stmt->get_result()->num_rows === 0) {
                $stmt = $conn->prepare("
                    INSERT INTO stock_requests
                    (store_id, item_id, requested_qty, status, requested_by, created_at)
                    VALUES (?, ?, ?, 'pending', ?, NOW())
                ");
                $stmt->bind_param("iiii", $store_id, $item_id, $requested_qty, $cashier_id);
                $stmt->execute();
                $new_request_id = $conn->insert_id;

                $stmt = $conn->prepare("SELECT item_name FROM items WHERE item_id = ?");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $item_row  = $stmt->get_result()->fetch_assoc();
                $item_name = $item_row['item_name'] ?? 'Unknown Item';

                $stmt = $conn->prepare("
                    INSERT INTO notifications
                        (user_id, store_id, request_id, item_name, requested_qty, status, is_read, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', 0, NOW())
                ");
                $stmt->bind_param("iiiis", $cashier_id, $store_id, $new_request_id, $item_name, $requested_qty);
                $stmt->execute();

                $_SESSION['cashier_request_success'] = "Stock request sent successfully.";
                header("Location: inventoryCashier.php");
                exit();
            } else {
                $message = "Pending request already exists.";
            }
        } else {
            $message = "Stock level is still sufficient.";
        }
    }
}

// Fetch all inventory items
$stmt = $conn->prepare("
    SELECT it.item_id, it.item_name, it.category,
           COALESCE(inv.quantity, 0) AS quantity,
           COALESCE(inv.low_stock_level, 30) AS low_stock_level
    FROM items it
    LEFT JOIN inventory inv
        ON it.item_id = inv.item_id
       AND inv.store_id = ?
    WHERE it.status = 'active'
    ORDER BY it.category, it.item_name
");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

$total_items = count($rows);
$low_stock   = count(array_filter($rows, fn($r) => $r['quantity'] <= $r['low_stock_level']));
$sufficient  = $total_items - $low_stock;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory – Store #<?= htmlspecialchars($store_id) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../Design/forStaffInventory.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <a href="cashierDashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="topbar-title">
            <img src="../img/mrsofty1.png" alt="Mr. Softy">
            <div>
                <h1>Inventory Management</h1>
                <span>Store #<?= htmlspecialchars($store_id) ?></span>
            </div>
        </div>
    </div>
    <div class="topbar-right">
        <!-- Notification Bell -->
        <div class="notif-wrapper" id="notifWrapper">
            <button class="notif-bell-btn" id="notifBellBtn" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                <span class="notif-badge" id="notifBadge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                <?php else: ?>
                <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                <?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-dropdown-header">
                    <h4><i class="fas fa-bell" style="margin-right:6px;color:#e91e8c;"></i>My Requests</h4>
                    <button class="notif-mark-all" id="markAllRead">Mark all as read</button>
                </div>
                <div class="notif-list">
                    <?php if (empty($notifications)): ?>
                    <div class="notif-empty"><i class="far fa-bell-slash"></i>No notifications yet</div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n):
                            $icon_fa = $n['status'] === 'pending' ? 'fa-clock' : ($n['status'] === 'approved' ? 'fa-check-circle' : 'fa-times-circle');
                        ?>
                        <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                            <div class="notif-icon <?= $n['status'] ?>">
                                <i class="fas <?= $icon_fa ?>"></i>
                            </div>
                            <div class="notif-body">
                                <p>Request for <strong><?= htmlspecialchars($n['item_name']) ?></strong>
                                (<?= (int)$n['requested_qty'] ?> units)
                                <span class="notif-status-pill <?= $n['status'] ?>"><?= ucfirst($n['status']) ?></span></p>
                                <span class="notif-time"><?= date('M d, Y · h:i A', strtotime($n['updated_at'])) ?></span>
                            </div>
                            <?php if (!$n['is_read']): ?>
                            <div class="notif-unread-dot"></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?= htmlspecialchars($_SESSION['name'] ?? 'Cashier') ?>
        <small><?= date('F d, Y · h:i A') ?></small>
    </div>
</div>

<div class="page-wrapper">
    <div class="page-heading">
        <div class="page-heading-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <div>
            <h2>Inventory Management</h2>
            <p>Monitor stock levels and request replenishments</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px 18px;margin-bottom:16px;color:#92400e;">
        <i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i><?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-cubes"></i></div>
            <div>
                <div class="stat-number"><?= $total_items ?></div>
                <div class="stat-label">Total Items</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="stat-number"><?= $sufficient ?></div>
                <div class="stat-label">Sufficient Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <div class="stat-number"><?= $low_stock ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i>
            <h3>Inventory Items</h3>
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search items...">
            </div>
        </div>

        <table class="inv-table" id="invTable">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Current Qty</th>
                    <th>Status</th>
                    <th>Request Stock</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $current_category = null;
            foreach ($rows as $row):
                $is_low = $row['quantity'] <= $row['low_stock_level'];
                if ($row['category'] !== $current_category):
                    $current_category = $row['category'];
            ?>
            <tr class="category-row">
                <td colspan="5"><i class="fas fa-tag" style="margin-right:6px;"></i><?= htmlspecialchars($row['category']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>
                    <div class="item-cell">
                        <div class="item-avatar"><i class="fas fa-box"></i></div>
                        <div class="item-name"><?= htmlspecialchars($row['item_name']) ?></div>
                    </div>
                </td>
                <td><span class="category-badge"><?= htmlspecialchars($row['category']) ?></span></td>
                <td>
                    <span class="qty-display <?= $is_low ? 'low' : 'ok' ?>">
                        <span class="dot"></span>
                        <?= number_format($row['quantity'], 0) ?>
                    </span>
                </td>
                <td>
                    <?php if ($is_low): ?>
                    <span class="status-badge low"><i class="fas fa-exclamation-circle"></i> Low Stock</span>
                    <?php else: ?>
                    <span class="status-badge sufficient"><i class="fas fa-check"></i> Sufficient</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_low): ?>
                    <form method="POST" id="form_<?= $row['item_id'] ?>" style="margin:0;">
                        <input type="hidden" name="item_id" value="<?= $row['item_id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="request-form-cell">
                            <input type="number" name="requested_qty" min="1" required placeholder="Qty" class="qty-input">
                            <button type="submit" name="request_add" form="form_<?= $row['item_id'] ?>" class="request-btn">
                                <i class="fas fa-paper-plane"></i> Request
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <span class="sufficient-text">— No action needed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('searchInput').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#invTable tbody tr:not(.category-row)').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
</script>

<?php if (isset($_SESSION['cashier_request_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: "success",
        title: "Success",
        text: "<?= addslashes($_SESSION['cashier_request_success']); ?>",
        confirmButtonColor: "#e91e8c",
        timer: 1500,
        showConfirmButton: false
    });
});
</script>
<?php unset($_SESSION['cashier_request_success']); ?>
<?php endif; ?>

<script>
const bellBtn    = document.getElementById('notifBellBtn');
const dropdown   = document.getElementById('notifDropdown');
const notifBadge = document.getElementById('notifBadge');

bellBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    dropdown.classList.toggle('open');
});
document.addEventListener('click', function(e) {
    if (!document.getElementById('notifWrapper').contains(e.target))
        dropdown.classList.remove('open');
});
document.getElementById('markAllRead').addEventListener('click', function() {
    fetch('?mark_read=1').then(r => r.json()).then(() => {
        document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
        document.querySelectorAll('.notif-unread-dot').forEach(el => el.remove());
        notifBadge.style.display = 'none';
    });
});
</script>

</body>
</html>
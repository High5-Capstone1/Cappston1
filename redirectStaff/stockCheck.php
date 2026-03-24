<?php
require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$store_id = $_SESSION['store_id'];
$staff_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['mark_read']) && $_GET['mark_read'] === '1') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $staff_id, $store_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit();
}

$stmt = $conn->prepare("
    SELECT notif_id, item_name, requested_qty, status, is_read, created_at, updated_at
    FROM notifications
    WHERE user_id = ? AND store_id = ?
    ORDER BY updated_at DESC
    LIMIT 5
");
$stmt->bind_param("ii", $staff_id, $store_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$unread_count  = count(array_filter($notifications, fn($n) => !$n['is_read']));


$submit_message = '';
if (isset($_POST['submit_check'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF validation failed!");
    }

    $checked_ids  = $_POST['checked_ids']  ?? [];  
    $remarked_ids = $_POST['remarked_ids'] ?? []; 

    $stmt_log = $conn->prepare("
        INSERT INTO stock_check_logs
            (store_id, staff_id, item_id, checked_at, remarks)
        VALUES (?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE checked_at = NOW(), remarks = VALUES(remarks)
    ");

    foreach ($checked_ids as $item_id) {
        $item_id = (int)$item_id;
        $remark  = $remarked_ids[$item_id] ?? '';
        $stmt_log->bind_param("iiis", $store_id, $staff_id, $item_id, $remark);
        $stmt_log->execute();
    }

    $_SESSION['stock_check_success'] = "Stock check submitted successfully.";
    header("Location: stockCheck.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT it.item_id, it.item_name, it.category,
           COALESCE(inv.quantity, 0)       AS quantity,
           COALESCE(inv.low_stock_level, 5) AS low_stock_level
    FROM items it
    LEFT JOIN inventory inv
        ON it.item_id = inv.item_id AND inv.store_id = ?
    WHERE it.status = 'active'
    ORDER BY (COALESCE(inv.quantity,0) <= COALESCE(inv.low_stock_level,5)) DESC,
             it.category, it.item_name
");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_items = count($rows);
$low_stock   = count(array_filter($rows, fn($r) => $r['quantity'] <= $r['low_stock_level']));
$sufficient  = $total_items - $low_stock;


$already_checked_ids = [];
$stmt2 = $conn->prepare("
    SELECT item_id FROM stock_check_logs
    WHERE store_id = ? AND staff_id = ? AND DATE(checked_at) = CURDATE()
");
$stmt2->bind_param("ii", $store_id, $staff_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row2 = $res2->fetch_assoc()) {
    $already_checked_ids[] = $row2['item_id'];
}
$today_checked = count($already_checked_ids);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Check – Store #<?= htmlspecialchars($store_id) ?></title>
    <link rel="stylesheet" href="../../Design/forStaffStockCheck.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   
</head>
<body>


<div class="topbar">
    <div class="topbar-left">
        <a href="staffDashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="topbar-title">
            <img src="../img/mrsofty1.png" alt="Mr. Softy">
            <div>
                <h1>Stock Check</h1>
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
                    <div class="notif-empty"><i class="far fa-bell-slash"></i> No notifications yet</div>
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
        <?= htmlspecialchars($_SESSION['name'] ?? 'Staff') ?>
        <small><?= date('F d, Y · h:i A') ?></small>
    </div>
</div>

<!-- ── Page ── -->
<div class="page-wrapper">

    <div class="page-heading">
        <div class="page-heading-icon">
            <i class="fas fa-clipboard-check"></i>
        </div>
        <div>
            <h2>Morning Stock Check</h2>
            <p>Verify physical stock levels — tick each item once confirmed</p>
        </div>
    </div>

  
    <?php if ($today_checked === $total_items && $total_items > 0): ?>
    <div class="done-banner">
        <i class="fas fa-check-circle"></i>
        All <?= $total_items ?> items were verified today. Great work!
    </div>
    <?php endif; ?>


    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-cubes"></i></div>
            <div style="flex:1;">
                <div class="stat-number"><?= $total_items ?></div>
                <div class="stat-label">Total Items</div>
                <div class="stat-progress">
                    <div class="progress-track">
                        <div class="progress-fill" id="progressFill" style="width:<?= $total_items > 0 ? round(($today_checked/$total_items)*100) : 0 ?>%"></div>
                    </div>
                    <div class="progress-label" id="progressLabel"><?= $today_checked ?> / <?= $total_items ?> checked today</div>
                </div>
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
                <div class="stat-label">Needs Restock</div>
            </div>
        </div>
    </div>

    <form method="POST" id="checkForm">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="submit_check" value="1">

        <!-- Needs Attention section -->
        <?php
        $low_rows = array_filter($rows, fn($r) => $r['quantity'] <= $r['low_stock_level']);
        $ok_rows  = array_filter($rows, fn($r) => $r['quantity'] >  $r['low_stock_level']);
        ?>

        <?php if (!empty($low_rows)): ?>
        <div class="section-label danger">
            <i class="fas fa-exclamation-circle"></i>
            Needs Attention
            <span class="badge"><span><?= count($low_rows) ?></span></span>
        </div>

        <?php foreach ($low_rows as $row):
            $is_checked_today = in_array($row['item_id'], $already_checked_ids);
        ?>
        <div class="check-row low-stock <?= $is_checked_today ? 'checked-done' : '' ?>" id="row_<?= $row['item_id'] ?>">
            <!-- hidden checkbox that gets checked by the JS button -->
            <input type="checkbox"
                   name="checked_ids[]"
                   value="<?= $row['item_id'] ?>"
                   id="chk_<?= $row['item_id'] ?>"
                   style="display:none;"
                   <?= $is_checked_today ? 'checked' : '' ?>>

            <div class="row-bar low"></div>

            <div class="item-info">
                <div class="item-name-text"><?= htmlspecialchars($row['item_name']) ?></div>
                <div class="item-meta">
                    <i class="fas fa-tag" style="font-size:10px;margin-right:3px;"></i>
                    <?= htmlspecialchars($row['category']) ?>
                </div>
            </div>

            <div class="qty-col">
                <div class="qty-val low">
                    <?= $row['quantity'] == 0 ? 'Out of stock' : number_format($row['quantity'], 0) . ' units' ?>
                </div>
                <div class="qty-sub">Threshold: <?= $row['low_stock_level'] ?></div>
            </div>

            <span class="status-pill low"><i class="fas fa-exclamation-circle"></i> Low Stock</span>

            <button type="button"
                    class="check-btn <?= $is_checked_today ? 'checked' : '' ?>"
                    id="btn_<?= $row['item_id'] ?>"
                    onclick="toggleCheck(<?= $row['item_id'] ?>)"
                    <?= $is_checked_today ? 'disabled' : '' ?>>
                <?php if ($is_checked_today): ?>
                    <i class="fas fa-check"></i> Verified
                <?php else: ?>
                    <i class="far fa-square"></i> Check
                <?php endif; ?>
            </button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Sufficient Stock section -->
        <?php if (!empty($ok_rows)): ?>
        <div class="section-label success">
            <i class="fas fa-check-circle"></i>
            Sufficient Stock
            <span class="badge"><span><?= count($ok_rows) ?></span></span>
        </div>

        <?php foreach ($ok_rows as $row):
            $is_checked_today = in_array($row['item_id'], $already_checked_ids);
        ?>
        <div class="check-row <?= $is_checked_today ? 'checked-done' : '' ?>" id="row_<?= $row['item_id'] ?>">
            <input type="checkbox"
                   name="checked_ids[]"
                   value="<?= $row['item_id'] ?>"
                   id="chk_<?= $row['item_id'] ?>"
                   style="display:none;"
                   <?= $is_checked_today ? 'checked' : '' ?>>

            <div class="row-bar ok"></div>

            <div class="item-info">
                <div class="item-name-text"><?= htmlspecialchars($row['item_name']) ?></div>
                <div class="item-meta">
                    <i class="fas fa-tag" style="font-size:10px;margin-right:3px;"></i>
                    <?= htmlspecialchars($row['category']) ?>
                </div>
            </div>

            <div class="qty-col">
                <div class="qty-val ok"><?= number_format($row['quantity'], 0) ?> units</div>
                <div class="qty-sub">Threshold: <?= $row['low_stock_level'] ?></div>
            </div>

            <span class="status-pill ok"><i class="fas fa-check"></i> Sufficient</span>

            <button type="button"
                    class="check-btn <?= $is_checked_today ? 'checked' : '' ?>"
                    id="btn_<?= $row['item_id'] ?>"
                    onclick="toggleCheck(<?= $row['item_id'] ?>)"
                    <?= $is_checked_today ? 'disabled' : '' ?>>
                <?php if ($is_checked_today): ?>
                    <i class="fas fa-check"></i> Verified
                <?php else: ?>
                    <i class="far fa-square"></i> Check
                <?php endif; ?>
            </button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </form>

    <!-- spacer for fixed footer -->
    <div style="height: 80px;"></div>
</div>

<div class="submit-footer">
    <div class="footer-progress">
        <div class="footer-counter">
            <span id="footerChecked"><?= $today_checked ?></span> / <?= $total_items ?> verified
        </div>
        <div class="footer-bar">
            <div class="footer-bar-fill" id="footerFill"
                 style="width:<?= $total_items > 0 ? round(($today_checked/$total_items)*100) : 0 ?>%"></div>
        </div>
    </div>
    <div class="footer-actions">
        <?php if ($low_stock > 0): ?>
        <a href="inventoryStaff.php" class="manage-link">
            <i class="fas fa-paper-plane"></i> Request Restock
        </a>
        <?php endif; ?>
        <button type="button" class="submit-btn" id="submitBtn" onclick="submitCheck()">
            <i class="fas fa-clipboard-check"></i> Submit Check
        </button>
    </div>
</div>


<?php if (isset($_SESSION['stock_check_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'success',
        title: 'Check Submitted!',
        text: '<?= addslashes($_SESSION['stock_check_success']) ?>',
        confirmButtonColor: '#1976d2',
        timer: 2000,
        showConfirmButton: false
    });
});
</script>
<?php unset($_SESSION['stock_check_success']); ?>
<?php endif; ?>

<script>
const TOTAL = <?= $total_items ?>;
let checkedCount = <?= $today_checked ?>;

function toggleCheck(itemId) {
    const chk = document.getElementById('chk_' + itemId);
    const btn = document.getElementById('btn_' + itemId);
    const row = document.getElementById('row_' + itemId);

    chk.checked = true;
    btn.disabled = true;
    btn.classList.add('checked');
    btn.innerHTML = '<i class="fas fa-check"></i> Verified';
    row.classList.add('checked-done');

    checkedCount++;
    updateProgress();
}

function updateProgress() {
    const pct = TOTAL > 0 ? Math.round((checkedCount / TOTAL) * 100) : 0;

    document.getElementById('progressFill').style.width  = pct + '%';
    document.getElementById('progressLabel').textContent = checkedCount + ' / ' + TOTAL + ' checked today';
    document.getElementById('footerChecked').textContent = checkedCount;
    document.getElementById('footerFill').style.width    = pct + '%';
}

function submitCheck() {
    const checkboxes = document.querySelectorAll('input[name="checked_ids[]"]:checked');

    if (checkboxes.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Nothing Checked',
            text: 'Please verify at least one item before submitting.',
            confirmButtonColor: '#1976d2'
        });
        return;
    }

    Swal.fire({
        icon: 'question',
        title: 'Submit Stock Check?',
        text: checkedCount + ' of ' + TOTAL + ' items verified. This will log today\'s check.',
        showCancelButton: true,
        confirmButtonText: 'Yes, submit',
        cancelButtonText: 'Go back',
        confirmButtonColor: '#1976d2',
        cancelButtonColor: '#adb5bd'
    }).then(result => {
        if (result.isConfirmed) {
            document.getElementById('checkForm').submit();
        }
    });
}

/* Notification bell */
const bellBtn  = document.getElementById('notifBellBtn');
const dropdown = document.getElementById('notifDropdown');

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
        document.getElementById('notifBadge').style.display = 'none';
    });
});
</script>

</body>
</html>
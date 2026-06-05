<?php
require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

//date filter
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

//display all store
$stores = $conn->query("SELECT store_id, store_name, location FROM store ORDER BY store_id")
               ->fetch_all(MYSQLI_ASSOC);

//build variance data for every store
$all_store_data    = [];
$grand_consumed    = 0;
$grand_sales       = 0;
$grand_unaccounted = 0;

foreach ($stores as $store) {
    $sid = $store['store_id'];

    //Actual qty per item for this store
    $stmt = $conn->prepare("SELECT item_id, quantity FROM inventory WHERE store_id = ?");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $inv_map = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'quantity', 'item_id');

    //Approved stock sent to this store in date range
   $stmt = $conn->prepare("
    SELECT item_id, SUM(requested_qty) AS total_approved
    FROM stock_requests
    WHERE store_id = ? AND status = 'approved'
    GROUP BY item_id
");
$stmt->bind_param("i", $sid);
    $stmt->execute();
    $approved_map = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'total_approved', 'item_id');

    //Sales consumed per item for this store in date range
    $stmt = $conn->prepare("
        SELECT pi.item_id, SUM(pi.quantity_needed * s.quantity) AS sales_consumed
        FROM sales s
        JOIN product_items pi ON pi.product_id = s.product_id
        WHERE s.store_id = ? AND s.is_deleted = 0
          AND DATE(s.sale_date) BETWEEN ? AND ?
        GROUP BY pi.item_id
    ");
    $stmt->bind_param("iss", $sid, $date_from, $date_to);
    $stmt->execute();
    $sales_map = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'sales_consumed', 'item_id');

    //Items that have inventory rows for this store
    $stmt = $conn->prepare("
        SELECT item_id, item_name, category
        FROM items
        WHERE status = 'active'
        ORDER BY category, item_name
    ");
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Build rows
    $rows              = [];
    $store_consumed    = 0;
    $store_sales       = 0;
    $store_unaccounted = 0;
    $store_loss        = 0;
    $store_balanced    = 0;

    foreach ($items as $item) {
        $iid         = $item['item_id'];
        $actual      = (float)($inv_map[$iid]      ?? 0);
        $expected    = (float)($approved_map[$iid] ?? 0);
        $sales       = (float)($sales_map[$iid]    ?? 0);
        $variance    = $expected - $actual;
        $unaccounted = $variance - $sales;

        if ($expected == 0)        { $status = 'nodata'; }
        elseif ($unaccounted > 0)  { $status = 'loss';     $store_loss++;    }
        elseif ($unaccounted < -1) { $status = 'mismatch'; }
        else                       { $status = 'balanced'; $store_balanced++; }

        $store_consumed    += max(0, $variance);
        $store_sales       += $sales;
        $store_unaccounted += max(0, $unaccounted);

        $rows[] = [
            'iid'         => $iid,
            'item_name'   => $item['item_name'],
            'category'    => $item['category'],
            'expected'    => $expected,
            'actual'      => $actual,
            'variance'    => $variance,
            'sales'       => $sales,
            'unaccounted' => $unaccounted,
            'status'      => $status,
        ];
    }

    $grand_consumed    += $store_consumed;
    $grand_sales       += $store_sales;
    $grand_unaccounted += $store_unaccounted;

    
    $all_store_data[] = [
        'store'             => $store,
        'rows'              => $rows,
        'store_consumed'    => $store_consumed,
        'store_sales'       => $store_sales,
        'store_unaccounted' => $store_unaccounted,
        'store_loss'        => $store_loss,
        'store_balanced'    => $store_balanced,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Variance – Mr. Softy Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Design/forAdminVariance.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <a href="adminDashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <div class="topbar-title">
            <img src="../img/mrsofty1.png" alt="Mr. Softy">
            <div>
                <h1>Variance Report</h1>
                <span>All Stores · Stock Discrepancy Tracker</span>
            </div>
        </div>
    </div>
    <div class="topbar-right">
        <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?>
        <small><?= date('F d, Y · h:i A') ?></small>
    </div>
</div>

<div class="page-wrapper">

    <div class="page-heading">
        <div class="page-heading-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <h2>Stock Variance – All Stores</h2>
            <p>Approved stock sent vs actual inventory vs sales consumed — every store at a glance</p>
        </div>
    </div>
    
    <form method="GET" class="filter-bar">
        <label><i class="fas fa-calendar" style="margin-right:4px"></i>From</label>
        <input type="date" name="date_from" class="filter-date" value="<?= htmlspecialchars($date_from) ?>">
        <label>To</label>
        <input type="date" name="date_to" class="filter-date" value="<?= htmlspecialchars($date_to) ?>">
        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply Filter</button>
        <span class="filter-note">
            <i class="fas fa-store" style="margin-right:4px"></i>
            Showing all <?= count($stores) ?> stores (including no-stock stores)
        </span>
    </form>
    
    <div class="stats-grid">
        <div class="stat-card" style="animation-delay:.05s">
            <div class="stat-icon blue"><i class="fas fa-store"></i></div>
            <div><div class="stat-number"><?= count($stores) ?></div><div class="stat-label">Total Stores</div></div>
        </div>
        <div class="stat-card" style="animation-delay:.1s">
            <div class="stat-icon orange"><i class="fas fa-chart-bar"></i></div>
            <div><div class="stat-number"><?= number_format($grand_consumed) ?></div><div class="stat-label">Total Consumed (All)</div></div>
        </div>
        <div class="stat-card" style="animation-delay:.15s">
            <div class="stat-icon green"><i class="fas fa-cash-register"></i></div>
            <div><div class="stat-number"><?= number_format($grand_sales) ?></div><div class="stat-label">Sales Consumed (All)</div></div>
        </div>
        <div class="stat-card" style="animation-delay:.2s">
            <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
            <div><div class="stat-number"><?= number_format($grand_unaccounted) ?></div><div class="stat-label">Unaccounted Loss (All)</div></div>
        </div>
    </div>


    <div class="legend-bar">
        <span><i class="fas fa-info-circle" style="margin-right:4px;color:var(--sky-400)"></i>Status:</span>
        <div class="legend-item"><div class="legend-dot" style="background:var(--green)"></div>Balanced</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--red)"></div>Unaccounted Loss</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--orange)"></div>Data Mismatch</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--gray-400)"></div>No Stock Sent Yet</div>
    </div>

   
    <?php if (empty($all_store_data)): ?>
    <div class="no-stores">
        <i class="fas fa-box-open"></i>
        <p>No inventory data found. Try a different date range.</p>
    </div>
    <?php else: ?>

    <?php foreach ($all_store_data as $idx => $sd):
        $store = $sd['store'];
    ?>
    <div class="store-section" style="animation-delay:<?= $idx * 0.08 ?>s">

        <div class="store-header">
            <div class="store-badge">
                <i class="fas fa-store"></i>
                <?= htmlspecialchars($store['store_name']) ?> #<?= $store['store_id'] ?>
            </div>
            <span class="store-location">
                <i class="fas fa-map-marker-alt" style="margin-right:4px;color:var(--sky-400)"></i>
                <?= htmlspecialchars($store['location'] ?? '—') ?>
            </span>
        </div>

        <div class="store-mini-stats">
            <div class="mini-stat consumed">
                <i class="fas fa-boxes"></i>
                Total Consumed: <strong><?= number_format($sd['store_consumed']) ?></strong>
            </div>
            <div class="mini-stat sales-s">
                <i class="fas fa-cash-register"></i>
                Sales Used: <strong><?= number_format($sd['store_sales']) ?></strong>
            </div>
            <div class="mini-stat loss-s">
                <i class="fas fa-exclamation-circle"></i>
                Unaccounted: <strong><?= number_format($sd['store_unaccounted']) ?></strong>
            </div>
            <div class="mini-stat ok-s">
                <i class="fas fa-check-circle"></i>
                Balanced Items: <strong><?= $sd['store_balanced'] ?></strong>
            </div>
            <?php if ($sd['store_loss'] > 0): ?>
            <div class="mini-stat loss-s">
                <i class="fas fa-times-circle"></i>
                Loss Items: <strong><?= $sd['store_loss'] ?></strong>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-table"></i>
                <h3>Variance Breakdown</h3>
                <span class="date-pill"><?= $date_from ?> → <?= $date_to ?></span>
            </div>
            <?php if (empty($sd['rows'])): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No inventory set up for this store yet.</p>
            </div>
            <?php else: ?>
            <table class="var-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Expected <i class="fas fa-question-circle help-icon" title="Total approved stock requests sent to this store in the date range"></i></th>
                        <th>Actual <i class="fas fa-question-circle help-icon" title="Current quantity in inventory right now"></i></th>
                        <th>Variance <i class="fas fa-question-circle help-icon" title="Expected − Actual = total stock consumed so far"></i></th>
                        <th>Sales Consumed <i class="fas fa-question-circle help-icon" title="How much stock sales records say was used"></i></th>
                        <th>Unaccounted <i class="fas fa-question-circle help-icon" title="Variance − Sales = missing stock with no sales explanation"></i></th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $cur_cat = null;
                foreach ($sd['rows'] as $r):
                    $un  = (float)$r['unaccounted'];
                    $var = (float)$r['variance'];
                    $exp = (float)$r['expected'];
                    $pill = match($r['status']) {
                        'loss'     => ['loss',     'fa-exclamation-circle',   'Unaccounted Loss'],
                        'mismatch' => ['mismatch', 'fa-exclamation-triangle', 'Data Mismatch'],
                        'balanced' => ['balanced', 'fa-check-circle',         'Balanced'],
                        default    => ['nodata',   'fa-minus-circle',         'No Stock Sent'],
                    };
                    if ($r['category'] !== $cur_cat): $cur_cat = $r['category'];
                ?>
                <tr class="category-row">
                    <td colspan="8"><i class="fas fa-tag" style="margin-right:6px"></i><?= htmlspecialchars($r['category']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>
                        <div class="item-cell">
                            <div class="item-avatar"><i class="fas fa-box"></i></div>
                            <div class="item-name"><?= htmlspecialchars($r['item_name']) ?></div>
                        </div>
                    </td>
                    <td><span class="cat-badge"><?= htmlspecialchars($r['category']) ?></span></td>
                    <td class="num <?= $exp > 0 ? 'expected' : 'zero' ?>"><?= number_format($r['expected']) ?></td>
                    <td class="num actual"><?= number_format($r['actual']) ?></td>
                    <td class="num <?= $var > 0 ? 'variance-n' : 'zero' ?>"><?= number_format($var) ?></td>
                    <td class="num <?= $r['sales'] > 0 ? 'sales-n' : 'zero' ?>"><?= number_format($r['sales']) ?></td>
                    <td class="num <?= $un > 0 ? 'loss-n' : ($un < 0 ? 'sales-n' : 'ok-n') ?>">
                        <?= $un > 0 ? '+'.number_format($un) : number_format($un) ?>
                    </td>
                    <td><span class="status-pill <?= $pill[0] ?>"><i class="fas <?= $pill[1] ?>"></i> <?= $pill[2] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
document.querySelectorAll('.help-icon').forEach(icon => {
    icon.addEventListener('click', function(e) {
        e.stopPropagation();
        Swal.fire({
            icon: 'info', title: 'Column Info',
            text: this.getAttribute('title'),
            confirmButtonColor: '#0284c7',
            timer: 3000, showConfirmButton: false
        });
    });
});
</script>
</body>
</html>
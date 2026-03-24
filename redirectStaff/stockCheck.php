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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --pink:       #e91e8c;
            --pink-light: #fce4f3;
            --blue:       #1976d2;
            --blue-light: #e3f2fd;
            --red:        #e53935;
            --red-light:  #fdecea;
            --red-border: #f5c6c6;
            --green:      #2e7d32;
            --green-light:#e8f5e9;
            --green-border:#b2dfb4;
            --amber:      #e65100;
            --amber-light:#fff3e0;
            --gray-50:    #f8f9fa;
            --gray-100:   #f1f3f5;
            --gray-200:   #e9ecef;
            --gray-400:   #adb5bd;
            --gray-600:   #6c757d;
            --gray-800:   #343a40;
            --white:      #ffffff;
            --radius-sm:  6px;
            --radius-md:  10px;
            --radius-lg:  14px;
            --shadow-sm:  0 1px 4px rgba(0,0,0,.07);
            --shadow-md:  0 4px 16px rgba(0,0,0,.10);
        }

        body {
            font-family: 'Nunito', sans-serif;
            background: #eaf4fb;
            color: var(--gray-800);
            min-height: 100vh;
        }

        /* ── Topbar ── */
        .topbar {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 64px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(25,118,210,.3);
        }
        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .back-btn {
            background: rgba(255,255,255,.18);
            border: none;
            color: #fff;
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 15px;
            text-decoration: none;
            transition: background .2s;
        }
        .back-btn:hover { background: rgba(255,255,255,.30); }
        .topbar-title { display: flex; align-items: center; gap: 10px; }
        .topbar-title img { height: 36px; }
        .topbar-title h1 { font-size: 18px; font-weight: 800; }
        .topbar-title span { font-size: 12px; opacity: .8; display: block; margin-top: -2px; }
        .topbar-right {
            display: flex; align-items: center; gap: 14px;
            font-size: 14px; font-weight: 600;
        }
        .topbar-right small { font-size: 11px; font-weight: 400; opacity: .8; display: block; }

        /* ── Notification Bell ── */
        .notif-wrapper { position: relative; }
        .notif-bell-btn {
            background: rgba(255,255,255,.18);
            border: none; color: #fff;
            width: 38px; height: 38px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 16px; position: relative;
            transition: background .2s;
        }
        .notif-bell-btn:hover { background: rgba(255,255,255,.30); }
        .notif-badge {
            position: absolute; top: -4px; right: -4px;
            background: var(--red); color: #fff;
            font-size: 10px; font-weight: 700;
            min-width: 18px; height: 18px;
            border-radius: 9px; padding: 0 4px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid var(--blue);
        }
        .notif-dropdown {
            position: absolute; right: 0; top: calc(100% + 10px);
            width: 340px;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            display: none; z-index: 200;
            overflow: hidden;
        }
        .notif-dropdown.open { display: block; }
        .notif-dropdown-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px 10px;
            border-bottom: 1px solid var(--gray-200);
        }
        .notif-dropdown-header h4 { font-size: 14px; font-weight: 700; color: var(--gray-800); }
        .notif-mark-all {
            font-size: 11px; color: var(--pink); background: none;
            border: none; cursor: pointer; font-weight: 600;
            font-family: 'Nunito', sans-serif;
        }
        .notif-list { max-height: 260px; overflow-y: auto; }
        .notif-empty { padding: 24px; text-align: center; color: var(--gray-400); font-size: 13px; }
        .notif-item {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-100);
            position: relative;
            transition: background .15s;
        }
        .notif-item:hover { background: var(--gray-50); }
        .notif-item.unread { background: #fdf0f8; }
        .notif-icon {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
        }
        .notif-icon.pending  { background: var(--amber-light); color: var(--amber); }
        .notif-icon.approved { background: var(--green-light); color: var(--green); }
        .notif-icon.rejected { background: var(--red-light);   color: var(--red);   }
        .notif-body { flex: 1; font-size: 12px; line-height: 1.5; color: var(--gray-800); }
        .notif-time { font-size: 11px; color: var(--gray-400); display: block; margin-top: 2px; }
        .notif-status-pill {
            display: inline-block; font-size: 10px; font-weight: 700;
            padding: 1px 7px; border-radius: 20px; margin-left: 4px;
        }
        .notif-status-pill.pending  { background: var(--amber-light); color: var(--amber); }
        .notif-status-pill.approved { background: var(--green-light); color: var(--green); }
        .notif-status-pill.rejected { background: var(--red-light);   color: var(--red);   }
        .notif-unread-dot {
            width: 8px; height: 8px; background: var(--pink);
            border-radius: 50%; flex-shrink: 0; margin-top: 6px;
        }

        /* ── Page wrapper ── */
        .page-wrapper { max-width: 900px; margin: 0 auto; padding: 28px 20px 60px; }

        /* ── Page heading ── */
        .page-heading {
            display: flex; align-items: center; gap: 16px;
            margin-bottom: 24px;
        }
        .page-heading-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #1976d2, #1565c0);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #fff;
            box-shadow: 0 4px 12px rgba(25,118,210,.3);
        }
        .page-heading h2 { font-size: 22px; font-weight: 800; color: var(--gray-800); }
        .page-heading p  { font-size: 13px; color: var(--gray-600); margin-top: 2px; }

        /* ── Stats grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .stat-icon.blue  { background: var(--blue-light);  color: var(--blue); }
        .stat-icon.green { background: var(--green-light); color: var(--green); }
        .stat-icon.red   { background: var(--red-light);   color: var(--red); }
        .stat-icon.amber { background: var(--amber-light); color: var(--amber); }
        .stat-number { font-size: 26px; font-weight: 800; color: var(--gray-800); line-height: 1; }
        .stat-label  { font-size: 12px; color: var(--gray-600); margin-top: 4px; font-weight: 600; }

        /* Progress bar inside stat */
        .stat-progress { margin-top: 8px; }
        .progress-track {
            height: 6px; background: var(--gray-200); border-radius: 3px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; border-radius: 3px;
            background: linear-gradient(90deg, #1976d2, #42a5f5);
            transition: width .6s ease;
        }
        .progress-label { font-size: 11px; color: var(--gray-600); margin-top: 4px; }

        /* ── Section labels ── */
        .section-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 11px; font-weight: 800; letter-spacing: .08em;
            text-transform: uppercase;
            padding: 0 4px;
            margin: 20px 0 10px;
        }
        .section-label.danger { color: var(--red); }
        .section-label.success { color: var(--green); }
        .section-label .badge {
            background: currentColor;
            color: #fff;
            border-radius: 20px;
            font-size: 10px;
            padding: 1px 8px;
            font-weight: 700;
        }
        .section-label .badge span { color: #fff; }

        /* ── Check rows ── */
        .check-row {
            background: var(--white);
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            padding: 14px 18px;
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 8px;
            transition: box-shadow .2s, border-color .2s, opacity .3s;
            cursor: default;
        }
        .check-row:hover { box-shadow: var(--shadow-sm); }
        .check-row.low-stock {
            background: var(--red-light);
            border-color: var(--red-border);
        }
        .check-row.low-stock:hover { border-color: #e57373; }
        .check-row.checked-done {
            opacity: .65;
            border-color: var(--green-border);
            background: var(--green-light);
        }

        /* colour bar on left */
        .row-bar {
            width: 5px; height: 44px; border-radius: 3px; flex-shrink: 0;
        }
        .row-bar.low   { background: var(--red); }
        .row-bar.ok    { background: var(--green); }

        /* item info */
        .item-info { flex: 1; min-width: 0; }
        .item-name-text { font-size: 14px; font-weight: 700; color: var(--gray-800); }
        .item-meta { font-size: 12px; color: var(--gray-600); margin-top: 2px; }

        /* qty display */
        .qty-col { min-width: 130px; }
        .qty-val { font-size: 14px; font-weight: 700; }
        .qty-val.low { color: var(--red); }
        .qty-val.ok  { color: var(--green); }
        .qty-sub { font-size: 11px; color: var(--gray-600); margin-top: 2px; }

        /* status pill */
        .status-pill {
            font-size: 11px; font-weight: 700;
            padding: 4px 12px; border-radius: 20px;
            white-space: nowrap;
        }
        .status-pill.low  { background: var(--red-light);   color: var(--red);   border: 1px solid var(--red-border); }
        .status-pill.ok   { background: var(--green-light); color: var(--green); border: 1px solid var(--green-border); }

        /* check button */
        .check-btn {
            font-family: 'Nunito', sans-serif;
            font-size: 12px; font-weight: 700;
            padding: 7px 16px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--gray-300, #dee2e6);
            background: var(--white);
            color: var(--gray-600);
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap;
            display: flex; align-items: center; gap: 5px;
        }
        .check-btn:hover:not(:disabled) {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-light);
        }
        .check-btn.checked {
            background: var(--green-light);
            border-color: var(--green-border);
            color: var(--green);
        }
        .check-btn:disabled { cursor: default; }

        /* ── Submit footer ── */
        .submit-footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: var(--white);
            border-top: 1px solid var(--gray-200);
            padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 -4px 16px rgba(0,0,0,.08);
            z-index: 90;
        }
        .footer-progress {
            display: flex; align-items: center; gap: 12px;
        }
        .footer-counter {
            font-size: 14px; font-weight: 700; color: var(--gray-800);
        }
        .footer-counter span { color: var(--blue); }
        .footer-bar {
            width: 180px; height: 8px;
            background: var(--gray-200); border-radius: 4px; overflow: hidden;
        }
        .footer-bar-fill {
            height: 100%; border-radius: 4px;
            background: linear-gradient(90deg, #1976d2, #42a5f5);
            transition: width .4s ease;
        }
        .footer-actions { display: flex; align-items: center; gap: 10px; }
        .submit-btn {
            font-family: 'Nunito', sans-serif;
            font-size: 14px; font-weight: 700;
            padding: 10px 24px;
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff;
            border: none; border-radius: var(--radius-md);
            cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            box-shadow: 0 3px 10px rgba(25,118,210,.35);
            transition: opacity .2s, box-shadow .2s;
        }
        .submit-btn:hover { opacity: .9; box-shadow: 0 4px 14px rgba(25,118,210,.45); }
        .submit-btn:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }

        .manage-link {
            font-family: 'Nunito', sans-serif;
            font-size: 13px; font-weight: 700;
            padding: 10px 18px;
            background: var(--pink-light);
            color: var(--pink);
            border: 1.5px solid #f3acd9;
            border-radius: var(--radius-md);
            text-decoration: none;
            display: flex; align-items: center; gap: 6px;
            transition: background .2s;
        }
        .manage-link:hover { background: #f9d1ed; }

        /* ── Already done banner ── */
        .done-banner {
            background: var(--green-light);
            border: 1.5px solid var(--green-border);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 20px;
            font-size: 14px; font-weight: 600; color: var(--green);
        }
        .done-banner i { font-size: 20px; }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .qty-col, .status-pill { display: none; }
            .topbar-right small { display: none; }
        }
    </style>
</head>
<body>

<!-- ── Topbar ── -->
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

    <!-- Already done today banner -->
    <?php if ($today_checked === $total_items && $total_items > 0): ?>
    <div class="done-banner">
        <i class="fas fa-check-circle"></i>
        All <?= $total_items ?> items were verified today. Great work!
    </div>
    <?php endif; ?>

    <!-- Stats -->
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

    <!-- ── Form ── -->
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

<!-- ── Fixed Submit Footer ── -->
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

<!-- ── Scripts ── -->
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
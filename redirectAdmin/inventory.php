<?php
require_once '../session.php';
include '../DBconnect.php';

define('ENCRYPTION_KEY', 'MrSoftyCapstone2025SecureKey!@#$');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

function decryptData($data) {
    if (empty($data)) return $data;
    $data = base64_decode($data);
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return $data;
    list($iv, $encrypted) = $parts;
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';

if (isset($_POST['action'])) {
  if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("CSRF validation failed!");
  }

  $request_id = (int)$_POST['request_id'];
  $action = $_POST['action'];

  $conn->begin_transaction();

  try {
    $stmt = $conn->prepare("
            SELECT * FROM stock_requests
            WHERE request_id = ? AND status = 'pending'
            FOR UPDATE
        ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();

    if (!$req) {
      throw new Exception("Request already processed.");
    }

    if ($action === 'approve') {
      $stmt = $conn->prepare("
                INSERT IGNORE INTO inventory (store_id, item_id, quantity, low_stock_level)
                VALUES (?, ?, 0, 10)
            ");
      $stmt->bind_param("ii", $req['store_id'], $req['item_id']);
      $stmt->execute();

      $stmt = $conn->prepare("
    UPDATE notifications SET status = 'approved', is_read = 0, updated_at = NOW()
    WHERE request_id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();

      $stmt = $conn->prepare("
                UPDATE inventory
                SET quantity = quantity + ?, updated_at = NOW()
                WHERE store_id = ? AND item_id = ?
            ");
      $stmt->bind_param("iii", $req['requested_qty'], $req['store_id'], $req['item_id']);
      $stmt->execute();

      $stmt = $conn->prepare("
                UPDATE stock_requests SET status = 'approved' WHERE request_id = ?
            ");
      $stmt->bind_param("i", $request_id);
      $stmt->execute();

      $message = "Stock request #$request_id has been approved successfully.";
      $message_type = "success";
    } else {


    $stmt = $conn->prepare("
    UPDATE notifications SET status = 'rejected', is_read = 0, updated_at = NOW()
    WHERE request_id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
      $stmt = $conn->prepare("
                UPDATE stock_requests SET status = 'rejected' WHERE request_id = ?
            ");
      $stmt->bind_param("i", $request_id);
      $stmt->execute();

      $message = "Stock request #$request_id has been rejected.";
      $message_type = "rejected";
    }

    $conn->commit();
  } catch (Exception $e) {
    $conn->rollback();
    $message = $e->getMessage();
    $message_type = "error";
  }
}

$result = $conn->query("
    SELECT sr.request_id, sr.store_id, sr.requested_qty,
           u.name, it.item_name, it.category
    FROM stock_requests sr
    JOIN items it ON sr.item_id = it.item_id
    JOIN users u ON sr.requested_by = u.user_id
    WHERE sr.status = 'pending'
    ORDER BY sr.created_at ASC
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | Pending Stock Requests</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../Design/forAdminInventory.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>


  <header class="site-header">
    <a href="adminDashboard.php" class="header-back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 12H5M12 5l-7 7 7 7" />
      </svg>
    </a>
    <div class="header-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
        <line x1="12" y1="22.08" x2="12" y2="12" />
      </svg>
    </div>
    <div class="header-text">
      <h1>Stock Request Management</h1>
      <p>Review and process pending inventory requests</p>
    </div>
  </header>

  <div class="main">

    <div class="section-header">
      <div class="section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10" />
          <polyline points="12 6 12 12 16 14" />
        </svg>
        <h2>Pending Stock Requests</h2>
        
      </div>
      <?php if ($result->num_rows > 0): ?>
        <span class="badge-count"><?= $result->num_rows ?> pending</span>
      <?php endif; ?>
    </div>

    <div class="table-card">
      <?php if ($result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>#&nbsp;ID</th>
              <th>Store</th>
              <th>Item</th>
              <th>Category</th>
              <th>Qty</th>
              <th>Requested By</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><span class="td-id">#<?= $row['request_id'] ?></span></td>
                <td><span class="store-pill">Store <?= htmlspecialchars($row['store_id']) ?></span></td>
                <td>
                  <div class="item-name"><?= htmlspecialchars($row['item_name']) ?></div>
                </td>
                <td><span class="item-cat"><?= htmlspecialchars($row['category']) ?></span></td>
                <td><span class="qty-badge"><?= $row['requested_qty'] ?> units</span></td>
                <td>
                  <div class="requester">
                    <?php $decryptedName = decryptData($row['name']); ?>
                       <div class="avatar"><?= strtoupper(substr($decryptedName, 0, 2)) ?></div>
                        <?= htmlspecialchars($decryptedName) ?>
                  </div>
                </td>
                <td>
                  <form method="POST" class="action-form">
                    <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="button" class="btn btn-approve"
                      onclick="confirmAction(this.closest('form'), 'approve', <?= $row['request_id'] ?>)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M20 6L9 17l-5-5" />
                      </svg>
                      Approve
                    </button>

                    <button type="button" class="btn btn-reject"
                      onclick="confirmAction(this.closest('form'), 'reject', <?= $row['request_id'] ?>)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                      </svg>
                      Reject
                    </button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
          </svg>
          <p>No pending stock requests at this time.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>


  <script>
  function confirmAction(form, action, requestId) {
    const isApprove = action === 'approve';

    Swal.fire({
      title: isApprove ? 'Approve Request?' : 'Reject Request?',
      text: isApprove
        ? `Stock request #${requestId} will be approved and inventory will be updated.`
        : `Stock request #${requestId} will be rejected.`,
      icon: isApprove ? 'question' : 'warning',
      draggable: true,
      showCancelButton: true,
      confirmButtonColor: isApprove ? '#22c55e' : '#ef4444',
      cancelButtonColor: '#6b7a99',
      confirmButtonText: isApprove ? '✔ Yes, Approve' : '✖ Yes, Reject',
      cancelButtonText: 'Cancel',
    }).then((result) => {
      if (result.isConfirmed) {
        const hiddenAction = document.createElement('input');
        hiddenAction.type  = 'hidden';
        hiddenAction.name  = 'action';
        hiddenAction.value = action;
        form.appendChild(hiddenAction);
        form.submit();
      }
    });
  }

  <?php if ($message): ?>
  <?php
    $swal_icon  = $message_type === 'success'  ? 'success'  : ($message_type === 'rejected' ? 'warning' : 'error');
    $swal_title = $message_type === 'success'  ? 'Approved!' : ($message_type === 'rejected' ? 'Rejected'  : 'Error');
  ?>
  window.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
      title: '<?= $swal_title ?>',
      text: '<?= addslashes($message) ?>',
      icon: '<?= $swal_icon ?>',
      draggable: true,
      confirmButtonColor: <?= $message_type === 'success' ? "'#22c55e'" : ($message_type === 'rejected' ? "'#f59e0b'" : "'#ef4444'") ?>,
      confirmButtonText: 'OK',
      timer: 4000,
      timerProgressBar: true,
    });
  });
  <?php endif; ?>
</script>
</body>

</html>
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
    header("Location: ../../index.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

$admin_name = $_SESSION['username'] ?? 'Admin';

$filter_start = $_GET['start_date'] ?? '';
$filter_end   = $_GET['end_date'] ?? '';
$filter_role  = $_GET['role'] ?? '';
$filter_store = $_GET['store_id'] ?? '';

// delete button
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $del_sql = "DELETE FROM attendance WHERE attendance_id = ?";
    $del_stmt = $conn->prepare($del_sql);
    $del_stmt->bind_param("i", $delete_id);
    $del_stmt->execute();
    $message = "Attendance record deleted successfully.";
    $message_type = "success";
}

// Fetch ALL records with no role filter in SQL (we'll filter in PHP since role is encrypted)
$sql = "
    SELECT a.attendance_id, a.date, a.time_in, a.time_out, a.role AS stored_role,
           u.name, u.role AS user_role, a.store_id, s.location
    FROM attendance a
    LEFT JOIN users u ON a.user_id = u.user_id
    LEFT JOIN store s ON a.store_id = s.store_id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($filter_start)) {
    $sql .= " AND a.date >= ?";
    $params[] = $filter_start;
    $types .= "s";
}
if (!empty($filter_end)) {
    $sql .= " AND a.date <= ?";
    $params[] = $filter_end;
    $types .= "s";
}
if (!empty($filter_store)) {
    $sql .= " AND a.store_id = ?";
    $params[] = $filter_store;
    $types .= "i";
}

$sql .= " ORDER BY a.date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch all rows and decrypt, then apply role filter in PHP
$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['decrypted_name'] = decryptData($row['name']) ?? 'N/A';
    $row['decrypted_role'] = decryptData($row['user_role']) ?? 'N/A';

    // Apply role filter in PHP after decrypting
    if (!empty($filter_role) && strtolower($row['decrypted_role']) !== strtolower($filter_role)) {
        continue;
    }

    $rows[] = $row;
}

$total_records = count($rows);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Attendance Records</title>
    <link rel="stylesheet" href="../Design/forAttendanceRecord.css">
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
                            Attendance Records
                        </h1>
                        <p>Manage all employee attendance</p>
                    </div>
                </div>
                <div class="header-right">
                    <p>Admin Panel</p>
                    <p class="admin-name"><?= htmlspecialchars($admin_name) ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="container">

        <?php if(isset($message)): ?>
        <div class="message <?= $message_type ?>">
            <i class="fas fa-check-circle"></i>
            <p><?= $message ?></p>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Total Records</p>
                    <p class="stat-value"><?= $total_records ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Today's Date</p>
                    <p class="stat-value"><?= date('M d, Y') ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-filter"></i>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Filters Active</p>
                    <p class="stat-value">
                        <?= (!empty($filter_start) || !empty($filter_end) || !empty($filter_role) || !empty($filter_store)) ? 'Yes' : 'No' ?>
                    </p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Current Time</p>
                    <p class="stat-value" id="currentTime"></p>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="fas fa-sliders-h"></i>
                    <h2>Filter Records</h2>
                </div>
            </div>
            <form method="GET" action="" class="filter-form">
                <div class="filter-grid">
                    <div class="filter-field">
                        <label>
                            <i class="fas fa-calendar-alt"></i>
                            Start Date
                        </label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($filter_start) ?>">
                    </div>
                    <div class="filter-field">
                        <label>
                            <i class="fas fa-calendar-alt"></i>
                            End Date
                        </label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($filter_end) ?>">
                    </div>
                    <div class="filter-field">
                        <label>
                            <i class="fas fa-user-tag"></i>
                            Role
                        </label>
                        <select name="role">
                            <option value="">All Roles</option>
                            <option value="cashier" <?= $filter_role=='cashier'?'selected':'' ?>>Cashier</option>
                            <option value="staff" <?= $filter_role=='staff'?'selected':'' ?>>Staff</option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label>
                            <i class="fas fa-store"></i>
                            Store ID
                        </label>
                        <input type="number" name="store_id" placeholder="Enter Store ID" value="<?= htmlspecialchars($filter_store) ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-filter">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                    <a href="../../redirectAdmin/attendanceRecords.php" class="btn btn-reset">
                        <i class="fas fa-redo"></i>
                        Reset Filters
                    </a>
                </div>
            </form>
        </div>

        <div class="records-section">
            <div class="records-header">
                <div class="records-title">
                    <i class="fas fa-table"></i>
                    <h2>Attendance Records</h2>
                </div>
                <div class="records-count">
                    <span class="count-badge"><?= $total_records ?> Records</span>
                </div>
            </div>

            <div class="table-container">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Date</th>
                            <th><i class="fas fa-user"></i> Full Name</th>
                            <th><i class="fas fa-id-badge"></i> Role</th>
                            <th><i class="fas fa-store"></i> Store ID</th>
                            <th><i class="fas fa-map-marker-alt"></i> Location</th>
                            <th><i class="fas fa-sign-in-alt"></i> Time In</th>
                            <th><i class="fas fa-sign-out-alt"></i> Time Out</th>
                            <th><i class="fas fa-cog"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($rows) > 0): ?>
                            <?php foreach($rows as $row): ?>
                            <tr>
                                <td>
                                    <span class="date-badge">
                                        <?= date('M d, Y', strtotime($row['date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($row['decrypted_name'], 0, 1)) ?>
                                        </div>
                                        <span><?= htmlspecialchars($row['decrypted_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?= strtolower($row['decrypted_role']) ?>">
                                        <?= ucfirst($row['decrypted_role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="store-badge">
                                        Store #<?= $row['store_id'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="location-text">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= htmlspecialchars($row['location'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="time-badge time-in">
                                        <i class="fas fa-clock"></i>
                                        <?= date('h:i A', strtotime($row['time_in'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($row['time_out']): ?>
                                        <span class="time-badge time-out">
                                            <i class="fas fa-clock"></i>
                                            <?= date('h:i A', strtotime($row['time_out'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="time-badge in-progress">
                                            <i class="fas fa-hourglass-half"></i>
                                            In Progress
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this attendance record?');">
                                        <input type="hidden" name="delete_id" value="<?= $row['attendance_id'] ?>">
                                        <button type="submit" class="btn-delete">
                                            <i class="fas fa-trash-alt"></i>
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No attendance records found</p>
                                        <span>Try adjusting your filters</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function updateTime() {
            const now = new Date();
            const options = { hour: '2-digit', minute: '2-digit', hour12: true };
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', options);
            }
        }
        updateTime();
        setInterval(updateTime, 1000);
    </script>

</body>
</html>
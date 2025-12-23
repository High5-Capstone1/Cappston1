<?php
session_start();
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

date_default_timezone_set('Asia/Manila');


$filter_start = $_GET['start_date'] ?? '';
$filter_end   = $_GET['end_date'] ?? '';
$filter_role  = $_GET['role'] ?? '';
$filter_store = $_GET['store_id'] ?? '';


$sql = "
    SELECT a.attendance_id, a.date, a.time_in, a.time_out, u.name, u.role, a.store_id, s.location
    FROM attendance a
    LEFT JOIN users u ON a.user_id = u.user_id
    LEFT JOIN store s ON a.store_id = s.store_id
    WHERE 1=1
";

$params = [];
$types = "";

// delete button
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $del_sql = "DELETE FROM attendance WHERE attendance_id = ?";
    $del_stmt = $conn->prepare($del_sql);
    $del_stmt->bind_param("i", $delete_id);
    $del_stmt->execute();
    
    
    $message = "Attendance record deleted successfully.";
}



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


if (!empty($filter_role)) {
    $sql .= " AND u.role = ?";
    $params[] = $filter_role;
    $types .= "s";
}


if (!empty($filter_store)) {
    $sql .= " AND a.store_id = ?";
    $params[] = $filter_store;
    $types .= "i";
}

$sql .= " ORDER BY a.date DESC, u.role ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$history = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin Attendance Records</title>
<link rel="stylesheet" href="../../Design/attendance.css">

</head>
<body>


<div class="attendance-container">
    <a href="adminDashboard.php">Back</a>
<h2>Admin - Attendance Records</h2>


<div class="filter-box">
    <form method="GET" action="">
        <label>Start Date: <input type="date" name="start_date" value="<?= htmlspecialchars($filter_start) ?>"></label>
        <label>End Date: <input type="date" name="end_date" value="<?= htmlspecialchars($filter_end) ?>"></label>
        <label>Role: 
            <select name="role">
                <option value="">All</option>
                <option value="cashier" <?= $filter_role=='cashier'?'selected':'' ?>>Cashier</option>
                <option value="staff" <?= $filter_role=='staff'?'selected':'' ?>>Staff</option>
            </select>
        </label>
        <label>Store ID: <input type="number" name="store_id" value="<?= htmlspecialchars($filter_store) ?>"></label>
        <button type="submit">Filter</button>
        <a href="../../redirectAdmin/attendanceRecords.php"><button type="button">Reset</button></a>
    </form>
</div>


<div class="history-box">
<table>
<tr>
    <th>Date</th>
    <th>Full Name</th>
    <th>Role</th>
    <th>Store ID</th>
     <th>Location</th>
    <th>Time In</th>
    <th>Time Out</th>
    <th>Action</th>
</tr>

<?php if($history->num_rows > 0): ?>
    <?php while($row = $history->fetch_assoc()): ?>
    <tr>
        <td><?= $row['date'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= ucfirst($row['role']) ?></td>
        <td><?= $row['store_id'] ?></td>
         <td><?= $row['location'] ?></td>
        <td><?= $row['time_in'] ?></td>
        <td><?= $row['time_out'] ?? '-' ?></td>
        <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this record?');">
                <input type="hidden" name="delete_id" value="<?= $row['attendance_id'] ?>">
                <button type="submit">Delete</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6">No records found</td></tr>
<?php endif; ?>

</table>
</div>
</div>
</body>
</html>

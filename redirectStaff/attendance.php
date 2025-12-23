<?php
session_start();
include '../DBconnect.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['cashier','staff'])) {
    header("Location: roleLogin/login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];
$role     = $_SESSION['role'];
$today    = date('Y-m-d');

// today attendance
$sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$todayAttendance = $stmt->get_result()->fetch_assoc();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // time In
    if (isset($_POST['time_in_btn']) && !$todayAttendance) {

        $time_in = date('H:i:s'); 

        $insert = "INSERT INTO attendance (user_id, store_id, role, date, time_in)
                   VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("iisss", $user_id, $store_id, $role, $today, $time_in);
        $stmt->execute();

        $message = "Time In recorded at $time_in";

   
    } elseif (isset($_POST['time_out_btn']) && $todayAttendance && empty($todayAttendance['time_out'])) {

        $time_out = date('H:i:s'); 

        
        if ($time_out <= $todayAttendance['time_in']) {
            $message = "Invalid time-out detected. Cannot be earlier than Time In.";
        } else {
            $update = "UPDATE attendance SET time_out = ? WHERE attendance_id = ?";
            $stmt = $conn->prepare($update);
            $stmt->bind_param("si", $time_out, $todayAttendance['attendance_id']);
            $stmt->execute();

            $message = " Time Out recorded at $time_out";
        }

    
    } else {
        $message = "Attendance already completed for today";
    }
}

$history_sql = "SELECT a.date, a.time_in, a.time_out, u.name
                FROM attendance a LEFT JOIN users u
                                ON a.user_id = u.user_id
                WHERE a.user_id = ?
                ORDER BY date DESC";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= ucfirst($role) ?> Attendance</title>
<link rel="stylesheet" href="../../Design/attendance.css">
</head>
<body>

<div class="attendance-container">

<h2><?= ucfirst($role) ?> Attendance</h2>

<?php if(isset($message)): ?>
<p class="message"><?= $message ?></p>
<?php endif; ?>

<div class="form-box">

<?php if (!$todayAttendance): ?>

<div class="logout">
    <form method="POST" action="../logout.php">
        <button type="submit">Logout</button>
    </form>
</div>

<form method="POST">
    <label>Time In:</label>
    <button type="submit" name="time_in_btn">Time In</button>
</form>

</form>

<?php elseif ($todayAttendance && empty($todayAttendance['time_out'])): ?>


<form method="POST">
    <label>Time Out:</label>
    <button type="submit" name="time_out_btn">Time Out</button>
</form>

</form>

<?php else: ?>

<p><strong>✔ Attendance completed for today</strong></p>

<?php endif; ?>

</div>

<div class="history-box">
<h3>Attendance History</h3>
<table>
<tr>
    <th>Date</th>
    <th>Full Name</th>
    <th>Time In</th>
    <th>Time Out</th>
    
</tr>
<?php while($row = $history->fetch_assoc()): ?>
<tr>
    <td><?= $row['date'] ?></td>
     <td><?= $row['name'] ?></td>
     <td>
    <?= $row['time_in'] 
        ? date('h:i A', strtotime($row['time_in'])) 
        : '-' ?>
</td>
<td>
    <?= $row['time_out'] 
        ? date('h:i A', strtotime($row['time_out'])) 
        : '-' ?>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

</div>
</body>
</html>

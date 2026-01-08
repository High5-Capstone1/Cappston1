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
$username = $_SESSION['username'] ?? 'User';

// today attendance
$sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$todayAttendance = $stmt->get_result()->fetch_assoc();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // time in
    if (isset($_POST['time_in_btn']) && !$todayAttendance) {

        $time_in = date('H:i:s'); 

        $insert = "INSERT INTO attendance (user_id, store_id, role, date, time_in)
                   VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("iisss", $user_id, $store_id, $role, $today, $time_in);
        $stmt->execute();

        $message = "Time In successfully recorded";
        $message_type = "success";

   
    } elseif (isset($_POST['time_out_btn']) && $todayAttendance && empty($todayAttendance['time_out'])) {

        $time_out = date('H:i:s'); 

        
        if ($time_out <= $todayAttendance['time_in']) {
            $message = "Invalid time-out detected. Cannot be earlier than Time In.";
            $message_type = "error";
        } else {
            $update = "UPDATE attendance SET time_out = ? WHERE attendance_id = ?";
            $stmt = $conn->prepare($update);
            $stmt->bind_param("si", $time_out, $todayAttendance['attendance_id']);
            $stmt->execute();

            $message = "Time Out successfully recorded";
            $message_type = "success";
        }

    } else {
        $message = "Attendance already completed for today";
        $message_type = "info";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $todayAttendance = $stmt->get_result()->fetch_assoc();
}

$history_sql = "SELECT a.date, a.time_in, a.time_out, u.name
                FROM attendance a LEFT JOIN users u
                                ON a.user_id = u.user_id
                WHERE a.user_id = ?
                ORDER BY date DESC
                LIMIT 10";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($role) ?> Attendance</title>
    <link rel="stylesheet" href="../Design/attendance.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>


    <header class="header">
        <div class="header-container">
            <div class="header-content">
                <div class="header-left">
                    <a href="<?= $role === 'cashier' ? 'cashierDashboard.php' : 'dashboard.php' ?>" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="header-title">
                        <h1>
                            <i class="fas fa-clock"></i>
                            Attendance System
                        </h1>
                        <p>Track your work hours</p>
                    </div>
                </div>
                <div class="header-right">
                    <p>Store #<?= htmlspecialchars($store_id) ?></p>
                    <p class="username"><?= htmlspecialchars($username) ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        
        <?php if(isset($message)): ?>
        <div class="message <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
            <p><?= $message ?></p>
        </div>
        <?php endif; ?>

        <div class="attendance-grid">
       
            <div class="clock-section">
                <div class="clock-card">
                    
                  
                    <div class="date-time-section">
                        <div class="calendar-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <p class="date-label">Today's Date</p>
                        <p class="current-date"><?= date('F j, Y') ?></p>
                        <div class="time-display">
                            <p id="currentTime"></p>
                        </div>
                    </div>                
                    <?php if (!$todayAttendance): ?>
                    
                        <form method="POST" class="action-form">
                            <div class="status-info">
                                <div class="status-badge">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Ready to start your shift</span>
                                </div>
                            </div>
                            <button type="submit" name="time_in_btn" class="btn btn-clock-in">
                                <i class="fas fa-sign-in-alt"></i>
                                Time In Now
                            </button>
                        </form>

                    <?php elseif ($todayAttendance && empty($todayAttendance['time_out'])): ?>
                        
                        <div class="clocked-in-status">
                            <div class="clocked-in-header">
                                <i class="fas fa-check-circle"></i>
                                <p>Clocked In</p>
                            </div>
                            <p>Time In: <span class="time-value"><?= date('h:i A', strtotime($todayAttendance['time_in'])) ?></span></p>
                        </div>
                        
                        <form method="POST">
                            <button type="submit" name="time_out_btn" class="btn btn-clock-out">
                                <i class="fas fa-sign-out-alt"></i>
                                Time Out Now
                            </button>
                        </form>

                    <?php else: ?>
                   
                        <div class="completed-status">
                            <div class="completed-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <h3>All Done!</h3>
                            <p>Attendance completed for today</p>
                            <div class="time-summary">
                                <div class="time-box">
                                    <p>Time In</p>
                                    <p><?= date('h:i A', strtotime($todayAttendance['time_in'])) ?></p>
                                </div>
                                <div class="time-box">
                                    <p>Time Out</p>
                                    <p><?= date('h:i A', strtotime($todayAttendance['time_out'])) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-info">
                                <p>Status</p>
                                <p>
                                    <?php 
                                    if (!$todayAttendance) echo "Not Clocked In";
                                    elseif (empty($todayAttendance['time_out'])) echo "Working";
                                    else echo "Completed";
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon">
                                <i class="fas fa-user-tag"></i>
                            </div>
                            <div class="stat-info">
                                <p>Role</p>
                                <p><?= ucfirst($role) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="history-section">
                <div class="history-card">
                    <div class="history-header">
                        <div class="history-title">
                            <div class="history-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h2>Attendance History</h2>
                        </div>
                        <span class="records-badge">
                            Last 10 Records
                        </span>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        <i class="fas fa-calendar"></i> Date
                                    </th>
                                    <th>
                                        <i class="fas fa-user"></i> Name
                                    </th>
                                    <th>
                                        <i class="fas fa-sign-in-alt"></i> Time In
                                    </th>
                                    <th>
                                        <i class="fas fa-sign-out-alt"></i> Time Out
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($history->num_rows > 0): ?>
                                    <?php while($row = $history->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span class="date-text">
                                                <?= date('M j, Y', strtotime($row['date'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span><?= htmlspecialchars($row['name']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($row['time_in']): ?>
                                                <span class="time-badge time-in">
                                                    <i class="fas fa-clock"></i>
                                                    <?= date('h:i A', strtotime($row['time_in'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="no-data">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['time_out']): ?>
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
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <p>No attendance records yet</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        
        function updateTime() {
            const now = new Date();
            const options = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', options);
        }
        
        updateTime();
        setInterval(updateTime, 1000);
    </script>

</body>
</html>
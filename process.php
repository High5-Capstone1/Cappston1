
<?php
session_start();
include 'DBconnect.php';


$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();


    if (password_verify($password, $user['password'])) {

        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['store_id'] = $user['store_id'];

        if ($user['role'] === 'admin') {
            header("Location: redirectAdmin/adminDashboard.php");
        } elseif ($user['role'] === 'cashier') {
            header("Location: redirectCashier/cashierDashboard.php");
        } else {
            header("Location: redirectStaff/staffDashboard.php");
        }
        exit();

    } else {
        echo "Wrong username or password!";
    }
} else {
    echo "User not found!";
}
?>

    <?php
    require_once 'session.php';
    include 'DBconnect.php';

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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: login.php");
        exit();
    }
    $inputUsername = $_POST['username'];
    $password      = $_POST['password'];
    $source        = $_POST['login_source'] ?? 'staff';


    $sql    = "SELECT * FROM users WHERE deleted_at IS NULL";
    $result = $conn->query($sql);

    $user = null;
    while ($row = $result->fetch_assoc()) {
        $decryptedUsername = decryptData($row['username']);
        $decryptedEmail    = decryptData($row['email']);

        if ($decryptedUsername === $inputUsername || $decryptedEmail === $inputUsername) {
            $user = $row;
            break;
        }
    }

    
    if ($user && password_verify($password, $user['password'])) {

        $role = decryptData($user['role']);
        if ($role === 'admin' && $source !== 'admin') {
            $_SESSION['error'] = "Access denied. Please use the Admin login page.";
            header("Location: roleLogin/login.php");
            exit();
        }

        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['role']     = $role;
        $_SESSION['store_id'] = $user['store_id'];
        $_SESSION['username'] = decryptData($user['username']);
        $_SESSION['name']     = decryptData($user['name']);

        session_regenerate_id(true);

      if ($role === 'admin') {
    header("Location: redirectAdmin/adminDashboard.php");
} elseif ($role === 'cashier') {
    header("Location: redirectCashier/cashierDashboard.php");
} else {

    $_SESSION['error'] = "Account has an invalid role. Please contact admin.";
    header("Location: roleLogin/login.php");
}
exit();

    } else {
        $_SESSION['error'] = "Invalid username or password.";
        if ($source === 'admin') {
            header("Location: roleLogin/adminLogin.php");
        } else {
            header("Location: roleLogin/login.php");
        }
        exit();
    }
    ?>
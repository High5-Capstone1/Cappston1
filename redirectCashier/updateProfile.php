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

function encryptData($data) {
    if (empty($data)) return $data;
    $iv = random_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

$user_id      = $_SESSION['user_id'] ?? 0;
$cashier_name = $_SESSION['username'] ?? 'Cashier';
$store_name   = $_SESSION['store_name'] ?? '';

$success = '';
$error   = '';

// Fetch current user data
$user_stmt = $conn->prepare("SELECT name, username, email FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Decrypt before displaying
$user['name']     = decryptData($user['name']);
$user['username'] = decryptData($user['username']);
$user['email']    = decryptData($user['email']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_name     = trim($_POST['name'] ?? '');
    $raw_username = trim($_POST['username'] ?? '');
    $raw_email    = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $current_pass = $_POST['current_password'] ?? '';

    // Validate current password
    $pass_stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $pass_stmt->bind_param("i", $user_id);
    $pass_stmt->execute();
    $pass_row = $pass_stmt->get_result()->fetch_assoc();

    if (!password_verify($current_pass, $pass_row['password'])) {
        $error = "Current password is incorrect.";
    } elseif (empty($raw_name)) {
        $error = "Full name cannot be empty.";
    } elseif (empty($raw_username)) {
        $error = "Username cannot be empty.";
    } elseif (!empty($new_password) && $new_password !== $confirm_pass) {
        $error = "New passwords do not match.";
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters.";
    } else {
        // Encrypt before saving
        $new_name     = encryptData($raw_name);
        $new_username = encryptData($raw_username);
        $new_email    = encryptData($raw_email);

        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET name=?, username=?, email=?, password=? WHERE user_id=?");
            $upd->bind_param("ssssi", $new_name, $new_username, $new_email, $hashed, $user_id);
        } else {
            $upd = $conn->prepare("UPDATE users SET name=?, username=?, email=? WHERE user_id=?");
            $upd->bind_param("sssi", $new_name, $new_username, $new_email, $user_id);
        }

        if ($upd->execute()) {
            $_SESSION['username'] = $raw_username;
            $cashier_name         = $raw_username;
            $user['name']         = $raw_name;
            $user['username']     = $raw_username;
            $user['email']        = $raw_email;
            $success = "Profile updated successfully!";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile – <?= htmlspecialchars($store_name) ?></title>
    <link rel="stylesheet" href="../Design/forCashierDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-wrapper {
            max-width: 560px;
            margin: 0 auto;
        }
        .profile-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,.08);
            padding: 36px 40px;
        }
        .profile-avatar-row {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 28px;
        }
        .profile-avatar-big {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .profile-avatar-row h2 { margin: 0 0 4px; font-size: 1.2rem; }
        .profile-avatar-row p  { margin: 0; color: #6b7280; font-size: .9rem; }

        .form-section-title {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #9ca3af;
            margin: 24px 0 12px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: .95rem;
            transition: border-color .2s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }
        .input-icon-wrap { position: relative; }
        .input-icon-wrap i {
            position: absolute;
            left: 12px; top: 50%;
            transform: translateY(-50%);
            color: #9ca3af; font-size: .9rem;
        }
        .input-icon-wrap input { padding-left: 36px; }

        .toggle-pass {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; color: #9ca3af;
            font-size: .9rem;
        }

        .alert {
            border-radius: 10px;
            padding: 12px 16px;
            font-size: .9rem;
            margin-bottom: 18px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .btn-save {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity .2s;
        }
        .btn-save:hover { opacity: .9; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            color: #6366f1; font-size: .9rem; font-weight: 500;
            text-decoration: none; margin-bottom: 20px;
        }
        .btn-back:hover { text-decoration: underline; }
        .divider { border: none; border-top: 1.5px solid #f3f4f6; margin: 8px 0 16px; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-cash-register"></i> Cashier Panel</h2>
            <div class="sub">Point of Sale System</div>
        </div>
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($cashier_name, 0, 1)) ?></div>
            <div class="user-details">
                <h3><?= htmlspecialchars($cashier_name) ?></h3>
                <a href="updateProfile.php" style="font-size:.8rem;color:#a5b4fc;">Update Profile</a>
            </div>
        </div>
        <nav>
            <a href="cashierDashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="attendance.php"><i class="fas fa-clock"></i><span>Attendance</span></a>
            <a href="addSales.php"><i class="fas fa-cart-plus"></i><span>New Sale</span></a>
            <a href="salesHistory.php"><i class="fas fa-chart-line"></i><span>Sales History</span></a>
        </nav>
        <div class="logout-section">
            <form action="../../logout.php" method="POST">
                <button type="submit">
                    <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <main>
        <div class="profile-wrapper">
            <a href="cashierDashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <div class="profile-card">
                <div class="profile-avatar-row">
                    <div class="profile-avatar-big"><?= strtoupper(substr($cashier_name, 0, 1)) ?></div>
                    <div>
                        <h2><?= htmlspecialchars($cashier_name) ?></h2>
                        <p><i class="fas fa-store" style="margin-right:5px;"></i><?= htmlspecialchars($store_name) ?></p>
                    </div>
                </div>

                <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= $error ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">

                    <p class="form-section-title"><i class="fas fa-user" style="margin-right:5px;"></i>Personal Information</p>

                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-id-badge"></i>
                            <input type="text" id="name" name="name"
                                   value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username"
                                   value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email"
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                    </div>

                    <hr class="divider">
                    <p class="form-section-title"><i class="fas fa-lock" style="margin-right:5px;"></i>Change Password <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#9ca3af;">(leave blank to keep current)</span></p>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-key"></i>
                            <input type="password" id="new_password" name="new_password"
                                   placeholder="Min. 6 characters">
                            <button type="button" class="toggle-pass" onclick="togglePass('new_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-key"></i>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   placeholder="Repeat new password">
                            <button type="button" class="toggle-pass" onclick="togglePass('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <hr class="divider">
                    <p class="form-section-title"><i class="fas fa-shield-halved" style="margin-right:5px;"></i>Confirm Identity</p>

                    <div class="form-group">
                        <label for="current_password">Current Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="current_password" name="current_password"
                                   placeholder="Required to save changes" required>
                            <button type="button" class="toggle-pass" onclick="togglePass('current_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-save">
                        <i class="fas fa-floppy-disk" style="margin-right:7px;"></i>Save Changes
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        function togglePass(id, btn) {
            const input = document.getElementById(id);
            const icon  = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>                                                                                                                                                   
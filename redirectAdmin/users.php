<?php
ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);
session_start();

include '../DBconnect.php';

define('ENCRYPTION_KEY',   'MrSoftyCapstone2025SecureKey!@#$'); 
define('ENCRYPTION_METHOD','AES-256-CBC');
define('JWT_SECRET',       'MrSoftyJWT2025SuperSecret!@#$');   
define('JWT_EXPIRY',       28800); 


function decryptData($data) {
    if (empty($data)) return $data;
    $data  = base64_decode($data);
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return $data;
    [$iv, $encrypted] = $parts;
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

//jwt
function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

function generateJWT(array $payload): string {
    $header  = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRY;
    $payload        = base64UrlEncode(json_encode($payload));
    $signature      = base64UrlEncode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
}

function validateJWT(string $token): array|false {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header, $payload, $signature] = $parts;

   
    $expectedSig = base64UrlEncode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expectedSig, $signature)) return false;

  
    $data = json_decode(base64UrlDecode($payload), true);
    if (!$data) return false;


    if (!isset($data['exp']) || $data['exp'] < time()) return false;

    return $data;
}

function setJWTCookie(string $token): void {
    setcookie('admin_jwt', $token, [
        'expires'  => time() + JWT_EXPIRY,
        'path'     => '/',
        'secure'   => true,  
        'httponly' => true, 
        'samesite' => 'Strict',
    ]);
}


function clearJWTCookie(): void {
    setcookie('admin_jwt', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    clearJWTCookie();
    header("Location: ../login.php");
    exit();
}

if (!isset($_COOKIE['admin_jwt'])) {
   
    $token = generateJWT([
        'user_id' => $_SESSION['user_id'] ?? 0,
        'role'    => $_SESSION['role'],
    ]);
    setJWTCookie($token);
} else {

    $jwtPayload = validateJWT($_COOKIE['admin_jwt']);

    if ($jwtPayload === false) {
 
        session_destroy();
        clearJWTCookie();
        header("Location: ../login.php?reason=token_expired");
        exit();
    }

    if (($jwtPayload['role'] ?? '') !== 'admin') {
        session_destroy();
        clearJWTCookie();
        header("Location: ../login.php?reason=unauthorized");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../Design/forUsers.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                            Staff Accounts
                        </h1>
                        <p>Manage employee account</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['success']; ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error">
                <i class="fas fa-times-circle"></i>
                <?= $_SESSION['error']; ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="page-container">
            <div class="box">
                <div class="logo">
                    <img src="../img/mrsofty1.png" alt="Mr. Softy Logo" width="100px">
                </div>
                <div class="subtitle">Signature Creations</div>
                <h2>Add Staff / Cashier Account</h2>
                <form action="../addingUser.php" method="POST">
                    <input type="text"     name="name"             placeholder="Full Name" required>
                    <input type="text"     name="username"         placeholder="Username"  required>
                    <input type="text"     name="email"            placeholder="Email"     required>
                    <div class="password-wrapper">
                        <input type="password" name="password"         id="password"         placeholder="Password"         required>
                        <small id="passwordHint" style="color:#888; display:none; margin-bottom:6px;">
                            Password must contain letters and numbers (min. 8 characters)
                        </small>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                        <p id="passwordError" style="color:red; display:none; margin-top:4px;"></p>
                    </div>

                    <input type="number" name="store_id" placeholder="Store ID" required>
                    <button type="submit">Create Account</button>
                </form>
            </div>

            <div class="table">
                <?php
                $sql    = "SELECT u.user_id, u.name, u.username, u.email, u.role,
                                  s.store_id, s.store_name, s.location
                           FROM users u
                           LEFT JOIN store s ON u.store_id = s.store_id
                           WHERE role != 'admin'
                           ORDER BY role";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    echo "<table>
                        <tr>
                            <th><i class='fas fa-hashtag'></i> ID</th>
                            <th><i class='fas fa-user'></i> Name</th>
                            <th><i class='fas fa-user'></i> Email</th>
                            <th><i class='fas fa-user-tag'></i> Username</th>
                            <th><i class='fas fa-store'></i> Store ID</th>
                            <th><i class='fas fa-store-alt'></i> Store Location</th>
                            <th><i class='fas fa-cog'></i> Action</th>
                        </tr>";

                    while ($row = $result->fetch_assoc()) {
                        $name     = htmlspecialchars(decryptData($row['name']));
                        $email    = htmlspecialchars(decryptData($row['email'] ?? 'N/A'));
                        $username = htmlspecialchars(decryptData($row['username']));
                        $role     = decryptData($row['role']);

                        if ($role === 'admin') continue;

                        echo "<tr>
                            <td>{$row['user_id']}</td>
                            <td>{$name}</td>
                            <td>{$email}</td>
                            <td>{$username}</td>
                            <td>{$row['store_id']}</td>
                            <td>" . htmlspecialchars($row['location'] ?? 'N/A') . "</td>
                            <td>
                                <a href='javascript:void(0)'
                                   onclick='deleteUser(\"../deletingAcc.php?user_id={$row['user_id']}\")'>
                                    <i class='fas fa-trash-alt'></i>
                                </a>
                            </td>
                        </tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>No users found.</p>";
                }
                ?>
            </div>
        </div>
    </div>

    <script>
       
        document.addEventListener("DOMContentLoaded", function () {
            const password        = document.getElementById("password");
            const confirmPassword = document.getElementById("confirm_password");
            const errorText       = document.getElementById("passwordError");
            const form            = document.querySelector("form");

            if (!password || !confirmPassword || !errorText) return;
            const alphanumericRegex = /^(?=.*[a-zA-Z])(?=.*\d).{8,}$/;

            function validatePassword() {
                if (password.value && !alphanumericRegex.test(password.value)) {
                    errorText.textContent  = "Password must be at least 8 characters and include both letters and numbers.";
                    errorText.style.display = "block";
                    return false;
                }
                if (confirmPassword.value && password.value !== confirmPassword.value) {
                    errorText.textContent  = "Passwords do not match.";
                    errorText.style.display = "block";
                    return false;
                }
                errorText.style.display = "none";
                errorText.textContent   = "";
                return true;
            }

            password.addEventListener("input", function () {
                document.getElementById("passwordHint").style.display =
                    password.value.length > 0 ? "block" : "none";
                validatePassword();
            });

            confirmPassword.addEventListener("input", validatePassword);

            form.addEventListener("submit", function (e) {
                if (!validatePassword()) e.preventDefault();
            });
        });


        const swalWithBootstrapButtons = Swal.mixin({
            customClass: { confirmButton: "btn btn-success", cancelButton: "btn btn-danger" },
            buttonsStyling: false
        });

        function deleteUser(url) {
            swalWithBootstrapButtons.fire({
                title: "Are you sure?",
                text:  "You won't be able to revert this!",
                icon:  "warning",
                showCancelButton:  true,
                confirmButtonText: "Yes, delete it!",
                cancelButtonText:  "No, cancel!",
                reverseButtons:    true
            }).then((result) => {
                if (result.isConfirmed) {
                    swalWithBootstrapButtons.fire({
                        title: "Deleted!",
                        text:  "The user account has been deleted.",
                        icon:  "success"
                    }).then(() => {
                        setTimeout(() => { window.location.href = url; }, 800);
                    });
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    swalWithBootstrapButtons.fire({
                        title: "Cancelled",
                        text:  "The account is safe :)",
                        icon:  "error"
                    });
                }
            });
        }
    </script>

    <?php if (isset($_SESSION['add_user_success'])): ?>
        <script>
            Swal.fire({
                icon: "success",
                title: "Success",
                text:  "<?= $_SESSION['add_user_success']; ?>",
                confirmButtonColor: "#e91e8c",
                timer: 1500,
                showConfirmButton: false
            });
        </script>
        <?php unset($_SESSION['add_user_success']); ?>
    <?php endif; ?>
</body>
</html>
<?php
require_once '../DBconnect.php';
if (!defined('ENCRYPTION_KEY'))    define('ENCRYPTION_KEY',    'MrSoftyCapstone2025SecureKey!@#$');
if (!defined('ENCRYPTION_METHOD')) define('ENCRYPTION_METHOD', 'AES-256-CBC');

function decryptField($data) {
    if (empty($data)) return $data;
    $data  = base64_decode($data);
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return $data;
    [$iv, $encrypted] = $parts;
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

$token = $_GET['token'] ?? '';
$valid = false;
$user  = null;

if (!empty($token)) {
    $stmt = $conn->prepare(
        "SELECT user_id, username FROM users
         WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $valid = true;
        $row   = $result->fetch_assoc();
        $user  = [
            'user_id'  => $row['user_id'],
            'username' => decryptField($row['username']) 
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password – Mr. Softy</title>
    <link rel="stylesheet" href="../Design/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <!-- ===== BACKGROUND DECORATIONS ===== -->
    <!-- Large transparent circles -->
    <div class="bg-circle bg-circle-1"></div>
    <div class="bg-circle bg-circle-2"></div>
    <div class="bg-circle bg-circle-3"></div>

    <!-- Thin diagonal lines -->
    <div class="bg-line bg-line-1"></div>
    <div class="bg-line bg-line-2"></div>
    <div class="bg-line bg-line-3"></div>

    <!-- Small dots pattern -->
    <div class="bg-dot bg-dot-1"></div>
    <div class="bg-dot bg-dot-2"></div>
    <div class="bg-dot bg-dot-3"></div>
    <div class="bg-dot bg-dot-4"></div>
    <div class="bg-dot bg-dot-5"></div>
    <div class="bg-dot bg-dot-6"></div>

    <!-- Subtle glowing effects -->
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>
    <div class="bg-glow bg-glow-3"></div>

    <!-- Decorative rings -->
    <div class="bg-ring bg-ring-1"></div>
    <div class="bg-ring bg-ring-2"></div>
    <!-- ============================== -->

<div class="container">
    <div class="header">
        <img src="../img/mrsofty2.png" alt="Mr. Softy Logo" class="brand-logo" width="50px">
        <h2>Mr. Softy</h2>
        <p class="sub">New Password</p>
    </div>

    <?php if (!$valid): ?>
        <div style="text-align:center; padding:20px;">
            <p style="color:#e91e25; font-size:15px;">⚠️ This reset link is invalid or has expired.</p>
            <a href="forgot_password.php"
               style="display:inline-block; margin-top:15px; color:#e91e25;
                      text-decoration:none; font-weight:bold;">
               Request a new link →
            </a>
        </div>
    <?php else: ?>
        <p style="text-align:center; font-size:13px; color:#666; margin-bottom:18px;">
            Hi <strong><?= htmlspecialchars($user['username']) ?></strong>, set your new password.
        </p>

        <form id="resetForm">
            <input type="hidden" id="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form">
                <label>New Password</label>
                <div class="input">
                    <input type="password" id="new_password" placeholder="At least 6 characters" required>
                    <span class="icon" onclick="togglePass('new_password')" style="cursor:pointer;">🔒</span>
                </div>

                <div style="margin-top:8px;">
        <div style="background:#ddd; border-radius:4px; height:6px; width:100%;">
            <div id="strength-bar" style="height:6px; border-radius:4px; width:0%; transition:all 0.3s;"></div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:4px;">
            <span id="strength-label" style="font-size:12px; font-weight:bold;"></span>
        </div>
        <div id="strength-hints" style="font-size:12px; margin-top:6px; line-height:1.8;"></div>
    </div>
            </div>

            <div class="form">
                <label>Confirm Password</label>
                <div class="input">
                    <input type="password" id="confirm_password" placeholder="Re-enter password" required>
                    <span class="icon" onclick="togglePass('confirm_password')" style="cursor:pointer;">🔒</span>
                </div>
            </div>

            <button type="submit">Reset Password</button>
        </form>
    <?php endif; ?>

    <div style="text-align:center; margin-top:15px;">
        <a href="login.php" style="color:#e91e25; text-decoration:none; font-size:13px;">← Back to Login</a>
    </div>
</div>

<script>
function togglePass(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}


function checkStrength(password) {
    let score = 0;
    if (password.length >= 8)               score++; // min length
    if (/[A-Z]/.test(password))             score++; // uppercase
    if (/[a-z]/.test(password))             score++; // lowercase
    if (/[0-9]/.test(password))             score++; // number
    if (/[^A-Za-z0-9]/.test(password))      score++; // special char
    return score;
}

function updateStrengthBar(password) {
    const score   = checkStrength(password);
    const bar     = document.getElementById('strength-bar');
    const label   = document.getElementById('strength-label');
    const hints   = document.getElementById('strength-hints');

    const levels = [
        { label: '',          color: '#ddd',     width: '0%'   },
        { label: 'Very Weak', color: '#e74c3c',  width: '20%'  },
        { label: 'Weak',      color: '#e67e22',  width: '40%'  },
        { label: 'Fair',      color: '#f1c40f',  width: '60%'  },
        { label: 'Strong',    color: '#2ecc71',  width: '80%'  },
        { label: 'Very Strong', color: '#27ae60', width: '100%' },
    ];

    bar.style.width            = levels[score].width;
    bar.style.backgroundColor  = levels[score].color;
    label.textContent          = levels[score].label;
    label.style.color          = levels[score].color;

    const missing = [];
    if (password.length < 8)              missing.push('At least 8 characters');
    if (!/[A-Z]/.test(password))          missing.push('One uppercase letter (A-Z)');
    if (!/[a-z]/.test(password))          missing.push('One lowercase letter (a-z)');
    if (!/[0-9]/.test(password))          missing.push('One number (0-9)');
    if (!/[^A-Za-z0-9]/.test(password))   missing.push('One special character (!@#$%^&*)');

    hints.innerHTML = missing.length
        ? missing.map(m => `<span style="color:#e91e25;">✗ ${m}</span>`).join('<br>')
        : '<span style="color:#2ecc71;">✓ Password meets all requirements!</span>';
}

document.getElementById('new_password').addEventListener('input', function() {
    updateStrengthBar(this.value);
});

<?php if ($valid): ?>
document.getElementById('resetForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const token    = document.getElementById('token').value;
    const newPass  = document.getElementById('new_password').value;
    const confPass = document.getElementById('confirm_password').value;

  //streth pass
    if (newPass.length < 8) {
        Swal.fire({ icon: 'warning', title: 'Too Short', text: 'Password must be at least 8 characters.', confirmButtonColor: '#e91e25' });
        return;
    }
    if (!/[A-Z]/.test(newPass)) {
        Swal.fire({ icon: 'warning', title: 'Weak Password', text: 'Add at least one uppercase letter (A-Z).', confirmButtonColor: '#e91e25' });
        return;
    }
    if (!/[a-z]/.test(newPass)) {
        Swal.fire({ icon: 'warning', title: 'Weak Password', text: 'Add at least one lowercase letter (a-z).', confirmButtonColor: '#e91e25' });
        return;
    }
    if (!/[0-9]/.test(newPass)) {
        Swal.fire({ icon: 'warning', title: 'Weak Password', text: 'Add at least one number (0-9).', confirmButtonColor: '#e91e25' });
        return;
    }
    if (!/[^A-Za-z0-9]/.test(newPass)) {
        Swal.fire({ icon: 'warning', title: 'Weak Password', text: 'Add at least one special character (!@#$%^&*).', confirmButtonColor: '#e91e25' });
        return;
    }
    if (checkStrength(newPass) < 4) {
        Swal.fire({ icon: 'warning', title: 'Password Too Weak', text: 'Please make your password stronger before continuing.', confirmButtonColor: '#e91e25' });
        return;
    }
    // ──────────────────────────────────────────────────────────────

    if (newPass !== confPass) {
        Swal.fire({ icon: 'warning', title: 'Mismatch', text: 'Passwords do not match.', confirmButtonColor: '#e91e25' });
        return;
    }

    const res  = await fetch('../process_forgot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reset_password&token=${encodeURIComponent(token)}&new_password=${encodeURIComponent(newPass)}`
    });
    const data = await res.json();

    if (data.success) {
        await Swal.fire({ icon: 'success', title: 'Password Updated!', text: 'You can now log in with your new password.', confirmButtonColor: '#e91e25' });
        window.location.href = 'login.php';
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#e91e25' });
    }
});
<?php endif; ?>
</script>
</body>
</html>
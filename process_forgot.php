<?php
//start output buffering to catch any redirects from session.php 
ob_start();

$action = $_POST['action'] ?? '';

require_once 'DBconnect.php';
require_once 'config/mailer.php';

//clear any output from includes redirects, warnings, etc.
ob_clean();

header('Content-Type: application/json');

if (!defined('ENCRYPTION_KEY'))    define('ENCRYPTION_KEY',    'MrSoftyCapstone2025SecureKey!@#$');
if (!defined('ENCRYPTION_METHOD')) define('ENCRYPTION_METHOD', 'AES-256-CBC');

function decryptData($data) {
    if (empty($data)) return $data;
    $data  = base64_decode($data);
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return $data;
    [$iv, $encrypted] = $parts;
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}


//find user by decrypting emails, send reset link 
if ($action === 'send_reset') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    $result = $conn->query("SELECT user_id, username, email FROM users");

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Database query failed.']);
        exit;
    }

    $user = null;
    while ($row = $result->fetch_assoc()) {
        $decryptedEmail = decryptData($row['email']);
        if (strtolower(trim($decryptedEmail)) === strtolower(trim($email))) {
            $user = $row;
            break;
        }
    }

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Email not found in database.']);
        exit;
    }

    $token  = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
    $update->bind_param("ssi", $token, $expiry, $user['user_id']);

    if (!$update->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to save token: ' . $conn->error]);
        exit;
    }

    $decryptedUsername = decryptData($user['username']);
    $resetLink = "http://localhost:8000/roleLogin/reset_password.php?token={$token}";

    $sent = sendResetEmail($email, $decryptedUsername, $resetLink);

    if (!$sent) {
        echo json_encode(['success' => false, 'message' => 'Token saved but email failed. Check mailer.php credentials.']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

//validate token and update password ──────────────────────
if ($action === 'reset_password') {
    $token        = trim($_POST['token'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (empty($token) || strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Reset link is invalid or has expired.']);
        exit;
    }

    $user   = $result->fetch_assoc();
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $update = $conn->prepare(
        "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?"
    );
    $update->bind_param("si", $hashed, $user['user_id']);

    if ($update->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error. Try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
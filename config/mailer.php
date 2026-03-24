<?php
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

function sendResetEmail(string $toEmail, string $toName, string $resetLink): bool {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true); 

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gemnuell@gmail.com';  
        $mail->Password   = 'dsyoehugzphvshtc';       
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('gemnuell@gmail.com', 'Mr. Softy System');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Mr. Softy - Password Reset Request';

        $mail->Body = "
            <div style='font-family:sans-serif; max-width:500px; margin:auto; padding:30px;
                        border:1px solid #eee; border-radius:10px;'>
                <h2 style='color:#e91e25;'>Mr. Softy</h2>
                <p>Hi <strong>{$toName}</strong>,</p>
                <p>We received a request to reset your password.
                   This link expires in <strong>1 hour</strong>.</p>
                <a href='{$resetLink}'
                   style='display:inline-block; background:#e91e25; color:#fff;
                          padding:12px 24px; border-radius:6px; text-decoration:none;
                          font-weight:bold; margin:16px 0;'>
                   Reset My Password
                </a>
                <p style='color:#888; font-size:12px;'>
                    If you didn't request this, ignore this email.
                </p>
            </div>
        ";

        $mail->send();
        return true;

    } catch (\Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
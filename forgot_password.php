<?php
require_once 'config.php';
require 'vendor/autoload.php';
require_once 'email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendPasswordResetEmail($userEmail, $username, $token) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($userEmail, $username);

        // Generate reset link
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Diane\'s Pharmacy';
        
        // HTML email body
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #5B287B; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 30px; background-color: #f9f9f9; border: 1px solid #ddd; }
                .button { display: inline-block; padding: 12px 30px; background-color: #5B287B; 
                         color: white; text-decoration: none; border-radius: 25px; margin: 20px 0;
                         font-weight: bold; }
                .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; 
                         padding: 20px; border-top: 1px solid #ddd; }
                .link { word-break: break-all; color: #5B287B; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0;'>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>$username</strong>,</p>
                    <p>We received a request to reset your password. Click the button below to reset it:</p>
                    <p style='text-align: center;'>
                        <a href='$resetLink' class='button' style='color: white;'>Reset Password</a>
                    </p>
                    <p>Or copy and paste this link in your browser:</p>
                    <p class='link'>$resetLink</p>
                    <p><strong>Note:</strong> This link will expire in 1 hour for security reasons.</p>
                    <p style='color: #666; font-style: italic;'>If you didn't request this reset, please ignore this email or contact support if you have concerns.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from Diane's Pharmacy.<br>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $htmlBody));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    
    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT user_id, username, email FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token in database
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expiry_date)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['user_id'], $token, $expiry]);
            
            // Send email
            if (sendPasswordResetEmail($user['email'], $user['username'], $token)) {
                $successMessage = "Password reset instructions have been sent to your email address. Please check your inbox and spam folder.";
                logAction($user['user_id'], 'Password Reset Requested', 'Password reset email sent successfully');
            } else {
                $errorMessage = "Unable to send reset email. Please try again later or contact support.";
                logAction($user['user_id'], 'Password Reset Failed', 'Failed to send password reset email');
            }
        } else {
            // Show generic message for security
            $successMessage = "If an account exists with this email, you will receive password reset instructions shortly.";
        }
    } catch(PDOException $e) {
        $errorMessage = "An error occurred. Please try again later.";
        error_log("Database Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Diane's Pharmacy</title>
    <style>
        /* Copy the same styles from login.php and add/modify as needed */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f5f5;
        }
        .container {
            width: 90%;
            max-width: 400px;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #5B287B;
            text-align: center;
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .submit-button {
            background-color: #5B287B;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .submit-button:hover {
            background-color: #186AB1;
        }
        .back-to-login {
            text-align: center;
            margin-top: 1rem;
        }
        .back-to-login a {
            color: #5B287B;
            text-decoration: none;
        }
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            word-wrap: break-word;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Forgot Password</h1>
        
        <?php if (isset($successMessage)): ?>
            <div class="message success">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="message error">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <input type="email" name="email" placeholder="Enter your email address" required>
            </div>
            <button type="submit" class="submit-button">Reset Password</button>
        </form>
        
        <div class="back-to-login">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html> 
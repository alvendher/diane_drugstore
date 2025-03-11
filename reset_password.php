<?php
require_once 'config.php';

if (!isset($_GET['token'])) {
    header('Location: login.php');
    exit;
}

$token = $_GET['token'];

try {
    // Check if token exists and is valid
    $stmt = $pdo->prepare("
        SELECT pr.*, u.username 
        FROM password_resets pr
        JOIN user u ON pr.user_id = u.user_id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expiry_date > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $errorMessage = "Invalid or expired reset link.";
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if ($password !== $confirmPassword) {
            $errorMessage = "Passwords do not match.";
        } else {
            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE user SET password_hash = ? WHERE user_id = ?");
            $stmt->execute([$hashedPassword, $reset['user_id']]);
            
            // Mark reset token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Log the action
            logAction($reset['user_id'], 'Password Reset', 'Password was reset successfully');
            
            $successMessage = "Password has been reset successfully. You can now login with your new password.";
        }
    }
} catch(PDOException $e) {
    $errorMessage = "An error occurred. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Diane's Pharmacy</title>
    <style>
        /* Copy styles from forgot_password.php */
        /* Add password strength indicator styles */
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>
        
        <?php if (isset($successMessage)): ?>
            <div class="message success">
                <?php echo $successMessage; ?>
                <div class="back-to-login" style="margin-top: 1rem;">
                    <a href="login.php">Back to Login</a>
                </div>
            </div>
        <?php elseif (isset($errorMessage)): ?>
            <div class="message error">
                <?php echo $errorMessage; ?>
                <?php if ($errorMessage === "Invalid or expired reset link."): ?>
                    <div class="back-to-login" style="margin-top: 1rem;">
                        <a href="forgot_password.php">Request New Reset Link</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post" onsubmit="return validatePassword()">
                <div class="form-group">
                    <input type="password" name="password" id="password" 
                           placeholder="Enter new password" required 
                           pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                           title="Must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters">
                    <div id="password-strength" class="password-strength"></div>
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" id="confirm_password" 
                           placeholder="Confirm new password" required>
                </div>
                <button type="submit" class="submit-button">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength');
            let strength = 0;
            
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            if (password.length >= 8) strength++;
            
            let strengthText = '';
            let strengthClass = '';
            
            if (strength < 3) {
                strengthText = 'Weak';
                strengthClass = 'strength-weak';
            } else if (strength < 5) {
                strengthText = 'Medium';
                strengthClass = 'strength-medium';
            } else {
                strengthText = 'Strong';
                strengthClass = 'strength-strong';
            }
            
            strengthDiv.textContent = 'Password Strength: ' + strengthText;
            strengthDiv.className = 'password-strength ' + strengthClass;
        });
        
        // Password match validation
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                alert('Passwords do not match!');
                return false;
            }
            return true;
        }
    </script>
</body>
</html> 
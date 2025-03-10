<?php
require_once 'config.php';

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
        $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database (you would need a password_reset table for this)
            // For this example, we'll just show a success message
            
            // In a real application, send an email with reset link
            $successMessage = "Password reset instructions have been sent to your email.";
        } else {
            $errorMessage = "Email not found in our records.";
        }
    } catch(PDOException $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Diane's Pharmacy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-blue-600">Diane's Pharmacy</h1>
                <p class="text-gray-600">Reset Your Password</p>
            </div>
            
            <?php if (isset($successMessage)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <?php echo $successMessage; ?>
                </div>
                <div class="text-center mt-4">
                    <a href="login.php" class="text-blue-500 hover:text-blue-700">Back to Login</a>
                </div>
            <?php else: ?>
                <?php if (isset($errorMessage)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>
                
                <p class="mb-4 text-gray-600">Enter your email address and we'll send you instructions to reset your password.</p>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2" for="email">Email Address</label>
                        <input type="email" name="email" id="email" class="w-full p-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
                            Send Reset Instructions
                        </button>
                    </div>
                    <div class="text-center">
                        <a href="login.php" class="text-blue-500 hover:text-blue-700">Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 
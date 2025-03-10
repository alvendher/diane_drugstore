<?php
require_once 'config.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password']; // Password won't be verified in this test version
    
    try {
        // Get user from database
        $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // TEMPORARY: Bypass password verification for testing
        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Log the login
            logAction($user['user_id'], 'Login', 'User logged in successfully');
            
            // Redirect to home page
            header('Location: home.php');
            exit;
        } else {
            $errorMessage = "Invalid username. User not found.";
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
    <title>Login - Diane's Pharmacy</title>
    <style>
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
            display: flex;
            width: 90vw;
            max-width: 1920px;
            height: 90vh;
            max-height: 1080px;
            background-color: #fff;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 20px;
            overflow: hidden;
        }
        .login-form {
            flex: 1;
            padding: clamp(20px, 5vw, 80px);
            display: flex;
            flex-direction: column;
            justify-content: flex-start; 
            max-width: min(400px, 90%);
        }
        .logo-section {
            flex: 1;
            background: linear-gradient(180deg, #186AB1 -100%, #3A1853 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .logo-section img {
            max-width: 60%;
            height: auto;
            object-fit: contain;
            display: block;
        }
        h1 {
            margin-bottom: clamp(50px, 12vh, 80px); 
            font-size: clamp(24px, 3vw, 32px);
            color: #333;
            text-align: center;
        }
        .form-group {
            margin-bottom: clamp(20px, 3vh, 30px);
            position: relative;
            width: 100%;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: clamp(8px, 1.5vh, 12px) 0;
            border: none;
            border-bottom: 1px solid #ddd;
            outline: none;
            font-size: clamp(14px, 1.5vw, 16px);
            transition: border-color 0.3s;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-bottom: 2px solid #5B287B;
        }
        .forgot-password {
            text-align: right;
            font-size: clamp(12px, 1.2vw, 14px);
            margin-top: 8px;
            font-weight: bold;
        }
        .forgot-password a {
            color: #5B287B;
            text-decoration: none;
            transition: color 0.3s;
        }
        .forgot-password a:hover {
            color: #186AB1;
        }
        .login-button {
            background-color: #5B287B;
            color: white;
            padding: clamp(10px, 2vh, 15px) clamp(20px, 4vw, 40px);
            border: none;
            border-radius: 25px;
            cursor: pointer;
            margin: clamp(40px, 6vh, 60px) auto clamp(20px, 3vh, 30px); 
            font-size: clamp(14px, 1.5vw, 16px);
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            width: fit-content;
        }
        .login-button:hover {
            background-color: #186AB1;
        }
        .login-button::after {
            content: "→";
            margin-left: 8px;
        }
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .test-credentials {
            margin-top: 20px;
            padding: 15px;
            background-color: #fff8e1;
            border-radius: 5px;
            font-size: 14px;
        }
        .test-credentials p {
            margin: 5px 0;
            color: #5B287B;
        }
        @media screen and (max-width: 768px) {
            .container {
                flex-direction: column-reverse;
                height: auto;
                min-height: 100vh;
                border-radius: 0;
            }
            .logo-section {
                padding: 40px 20px;
            }
            .login-form {
                padding: 40px 20px;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-form">
            <h1>Diane's Pharmacy</h1>
            
            <?php if (isset($errorMessage)): ?>
                <div class="error-message">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="post">
                <div class="form-group">
                    <input type="text" name="username" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>
                <button type="submit" class="login-button">Login</button>
            </form>
            
            <!-- TEMPORARY: Testing credentials notice -->
            <div class="test-credentials">
                <p><strong>Testing Credentials:</strong></p>
                <p>Admin: ajfrancisco (any password)</p>
                <p>Pharmacist: jmarcos (any password)</p>
                <p>Cashier: lsantos (any password)</p>
            </div>
        </div>
        <div class="logo-section">
            <img src="img/logo.png" alt="Diane's Pharmacy Logo">
        </div>
    </div>
</body>
</html> 
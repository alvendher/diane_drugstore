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
    $password = $_POST['password'];
    
    try {
        // Get user from database
        $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Verify user exists and password matches exactly
        if ($user && $password === $user['password_hash']) {
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
            $errorMessage = "Invalid username or password. Please try again.";
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
        }

        .container {
            display: flex;
            width: 90vw;
            max-width: 1200px;
            height: 600px;
            background-color: #fff;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border-radius: 20px;
            overflow: hidden;
        }

        .login-form {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: #fff;
            position: relative;
            z-index: 1;
        }

        .logo-section {
            flex: 1.2;
            background: linear-gradient(135deg, #186AB1 0%, #5B287B 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .logo-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><path fill="%23FFFFFF20" d="M37.5,186c-12.1-10.5-11.8-32.3-7.2-46.7c4.8-15,13.1-17.8,30.1-36.7C91,68.8,83.5,56.7,103.4,45 c22.2-13.1,51.1-9.5,69.6-1.6c18.1,7.8,15.7,15.3,43.3,33.2c28.8,18.8,37.2,14.3,46.7,27.9c15.6,22.3,6.4,53.3,4.4,60.2 c-3.3,11.2-7.1,23.9-18.5,32c-16.3,11.5-29.5,0.7-48.6,11c-16.2,8.7-12.6,19.7-28.2,33.2c-22.7,19.7-63.8,25.7-79.9,9.7 c-15.2-15.1,0.3-41.7-16.6-54.9C63,186,49.7,196.7,37.5,186z"/></svg>') no-repeat center center;
            background-size: 140%;
            opacity: 0.1;
            animation: pulse 15s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .logo-section img {
            max-width: 200px;
            height: auto;
            margin-bottom: 30px;
        }

        .logo-section h2 {
            color: #fff;
            font-size: 24px;
            text-align: center;
            font-weight: 700;
            margin-top: 20px;
        }

        h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 40px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        /* Added styles for input containers */
        .input-container {
            position: relative;
            width: 100%;
        }

        input[type="text"], 
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #eee;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            padding-right: 40px; /* Added right padding for icon */
        }

        input[type="text"]:focus, 
        input[type="password"]:focus {
            border-color: #5B287B;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(91, 40, 123, 0.1);
        }

        /* Input icon styling - right side and light gray */
        .input-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaaaaa; /* Light gray color */
            font-size: 16px;
        }

        /* Password toggle button */
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaaaaa; /* Light gray color */
            cursor: pointer;
            background: none;
            border: none;
            font-size: 16px;
        }

        .toggle-password:hover {
            color: #5B287B;
        }

        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }

        .forgot-password a {
            color: #5B287B;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
        }

        .forgot-password a:hover {
            color: #186AB1;
            text-decoration: underline;
        }

        .login-button {
            background: linear-gradient(135deg, #5B287B 0%, #186AB1 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(91, 40, 123, 0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .error-message {
            background-color: #fff3f3;
            color: #dc3545;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #dc3545;
            display: flex;
            align-items: center;
        }

        .error-message::before {
            content: '⚠️';
            margin-right: 10px;
        }

        @media screen and (max-width: 768px) {
            .container {
                flex-direction: column;
                height: auto;
                margin: 20px;
            }

            .logo-section {
                padding: 40px 20px;
                min-height: 200px;
            }

            .login-form {
                padding: 30px;
            }

            h1 {
                font-size: 24px;
                margin-bottom: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-form">
            <h1>Welcome Back!</h1>
            
            <?php if (isset($errorMessage)): ?>
                <div class="error-message">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="post">
                <div class="form-group">
                    <div class="input-container">
                        <input type="text" name="username" placeholder="Enter your username" required>
                        <i class="input-icon fas fa-user"></i>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-container">
                        <input type="password" name="password" id="password-field" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password">
                            <i class="fas fa-lock-open" id="toggle-icon"></i>
                        </button>
                    </div>
                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>
                <button type="submit" class="login-button">Sign In</button>
            </form>
        </div>
        <div class="logo-section">
            <img src="img/logo.png" alt="Diane's Pharmacy Logo">
            <h2>Diane's Pharmacy</h2>
        </div>
    </div>

    <!-- JavaScript for password toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.querySelector('.toggle-password');
            const passwordField = document.getElementById('password-field');
            const toggleIcon = document.getElementById('toggle-icon');
            
            // Set initial state to locked
            toggleIcon.classList.remove('fa-lock-open');
            toggleIcon.classList.add('fa-lock');
            
            togglePassword.addEventListener('click', function() {
                // Toggle password visibility
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                // Toggle icon
                if (type === 'text') {
                    toggleIcon.classList.remove('fa-lock');
                    toggleIcon.classList.add('fa-lock-open');
                } else {
                    toggleIcon.classList.remove('fa-lock-open');
                    toggleIcon.classList.add('fa-lock');
                }
            });
        });
    </script>
</body>
</html>
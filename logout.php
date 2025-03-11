<?php
require_once 'config.php';

// Log the logout action if user is logged in
if (isset($_SESSION['user_id'])) {
    logAction($_SESSION['user_id'], 'Logout', 'User logged out');
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
}

// Redirect to login page
header('Location: login.php');
exit;
?> 
<?php
require_once 'config.php';

// Log the logout action if user is logged in
if (isset($_SESSION['user_id'])) {
    logAction($_SESSION['user_id'], 'Logout', 'User logged out');
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
}

// Redirect to login page
header('Location: login.php');
exit;
?> 
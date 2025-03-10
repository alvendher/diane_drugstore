<?php
// Database configuration
$host = 'localhost:3307';
$dbname = 'drugstore';
$username = 'root';
$password = '';

try {
    // Create PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Function to log user actions
function logAction($userId, $action, $details = '') {
    global $pdo;
    
    try {
        // Check if the audit_log table exists and has auto_increment for log_id
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, timestamp, details) 
            VALUES (?, ?, NOW(), ?)
        ");
        $stmt->execute([$userId, $action, $details]);
    } catch(PDOException $e) {
        // If there's an error, just continue without logging
        // In a production environment, you might want to log this error elsewhere
        error_log("Audit log error: " . $e->getMessage());
    }
}

// Function to get current user ID (assuming stored in session)
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to 1 for testing
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?> 
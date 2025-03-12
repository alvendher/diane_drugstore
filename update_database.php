<?php
require_once 'config.php';

try {
    // Add new columns to inventory table
    $alterQueries = [
        "ALTER TABLE inventory 
         ADD COLUMN IF NOT EXISTS status ENUM('active', 'quarantined', 'expired') DEFAULT 'active'",
        
        "ALTER TABLE inventory 
         ADD COLUMN IF NOT EXISTS batch_number VARCHAR(50) DEFAULT NULL",
        
        "ALTER TABLE inventory 
         ADD COLUMN IF NOT EXISTS last_updated TIMESTAMP 
         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        
        "ALTER TABLE inventory 
         ADD COLUMN IF NOT EXISTS updated_by INT DEFAULT NULL"
    ];

    foreach ($alterQueries as $query) {
        $pdo->exec($query);
    }

    // Update all existing records to 'active' status
    $pdo->exec("UPDATE inventory SET status = 'active' WHERE status IS NULL");

    echo "Database structure updated successfully!";

} catch (PDOException $e) {
    die("Error updating database: " . $e->getMessage());
} 
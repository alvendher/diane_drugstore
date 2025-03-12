<?php
require_once 'config.php';
require_once 'classes/InventoryManager.php';

try {
    $inventoryManager = new InventoryManager($pdo);
    $negativeStocks = $inventoryManager->fixNegativeStock();
    
    if (!empty($negativeStocks)) {
        echo "Fixed negative stock for the following items:\n";
        foreach ($negativeStocks as $item) {
            echo "- {$item['product_name']}: {$item['stock_available']} → 0\n";
        }
    } else {
        echo "No negative stock values found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
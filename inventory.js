function validateStockAdjustment(form) {
    const quantity = parseInt(form.quantity.value);
    const currentStock = parseInt(form.current_stock.value);
    const type = form.adjustment_type.value;
    
    if (type === 'out' && quantity > currentStock) {
        alert(`Cannot remove ${quantity} units. Only ${currentStock} available.`);
        return false;
    }
    
    return true;
}

// Add new functions for low stock notifications
function calculateStockPercentage(available, threshold) {
    if (!threshold || threshold <= 0) return 0;
    return (available / threshold) * 100;
}

function getStockStatusClass(percentage) {
    if (percentage <= 0) return 'out-of-stock';
    if (percentage <= 10) return 'critical-stock';
    if (percentage <= 25) return 'very-low-stock';
    if (percentage <= 50) return 'low-stock';
    return 'good-stock';
}

function getStockStatusText(percentage) {
    if (percentage <= 0) return 'Out of Stock';
    if (percentage <= 10) return 'Critical Stock (≤10%)';
    if (percentage <= 25) return 'Very Low Stock (≤25%)';
    if (percentage <= 50) return 'Low Stock (≤50%)';
    return 'Good Stock';
}

function updateStockStatus(stockAvailable, threshold, displayElement, statusElement = null) {
    const percentage = calculateStockPercentage(stockAvailable, threshold);
    const formattedPercentage = percentage.toFixed(1);
    
    if (displayElement) {
        displayElement.textContent = `${stockAvailable}/${threshold} (${formattedPercentage}%)`;
        
        // Remove all status classes
        displayElement.classList.remove('text-red-600', 'text-orange-600', 'text-yellow-600', 'text-green-600', 'font-bold');
        
        // Add appropriate class based on percentage
        if (percentage <= 0) {
            displayElement.classList.add('text-red-600', 'font-bold');
        } else if (percentage <= 10) {
            displayElement.classList.add('text-red-600');
        } else if (percentage <= 25) {
            displayElement.classList.add('text-orange-600');
        } else if (percentage <= 50) {
            displayElement.classList.add('text-yellow-600');
        } else {
            displayElement.classList.add('text-green-600');
        }
    }
    
    // Update status text if status element is provided
    if (statusElement) {
        statusElement.textContent = getStockStatusText(percentage);
    }
    
    return percentage;
} 
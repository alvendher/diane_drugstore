<?php
require_once 'config.php';
checkLogin();

// Page specific variables
$pageTitle = "Inventory";
$pageIcon = "fas fa-boxes";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_inventory') {
        try {
            // Check if product already exists in inventory
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM inventory 
                WHERE product_id = ? AND status = 'active'
            ");
            $checkStmt->execute([$_POST['product_id']]);
            $productExists = $checkStmt->fetchColumn() > 0;
            
            if ($productExists) {
                $errorMessage = "This product already exists in inventory. Please edit the existing entry instead.";
            } else {
            // Prepare the SQL statement
            $stmt = $pdo->prepare("
                INSERT INTO inventory (inventory_id, product_id, stock_in, stock_out, expiry_date, stock_available, low_stock_threshold)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Calculate stock available
            $stockAvailable = $_POST['stock_in'] - $_POST['stock_out'];
            
            // Execute with the form data
            $stmt->execute([
                $_POST['inventory_id'],
                $_POST['product_id'],
                $_POST['stock_in'],
                $_POST['stock_out'],
                $_POST['expiry_date'],
                $stockAvailable,
                $_POST['low_stock_threshold']
            ]);
            
            // Get product name for the log
            $productName = $pdo->query("SELECT product_name FROM product WHERE product_id = " . $_POST['product_id'])->fetchColumn();
            
            // Log the action
            logAction(getCurrentUserId(), 'Added inventory for product: ' . $productName);
            
            // Set success message
            $successMessage = "Inventory added successfully!";
            }
        } catch(PDOException $e) {
            $errorMessage = "Error adding inventory: " . $e->getMessage();
        }
    }

if ($_POST['action'] === 'edit_inventory') {
        try {
            // Get current product ID for this inventory
            $currentProductStmt = $pdo->prepare("
                SELECT product_id FROM inventory WHERE inventory_id = ?
            ");
            $currentProductStmt->execute([$_POST['inventory_id']]);
            $currentProductId = $currentProductStmt->fetchColumn();
            
            // If product is being changed, check if the new product already exists
            if ($currentProductId != $_POST['product_id']) {
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM inventory 
                    WHERE product_id = ? AND status = 'active'
                ");
                $checkStmt->execute([$_POST['product_id']]);
                $productExists = $checkStmt->fetchColumn() > 0;
                
                if ($productExists) {
                    $errorMessage = "This product already exists in inventory. Please edit the existing entry instead.";
                    // Exit early without updating
                    throw new Exception("Duplicate product");
                }
            }
            
            // If we get here, it's safe to update
            // Prepare and execute the update
            $stmt = $pdo->prepare("
                UPDATE inventory
                SET product_id = ?, stock_in = ?, stock_out = ?, expiry_date = ?,
                    stock_available = ?, low_stock_threshold = ?
                WHERE inventory_id = ?
            ");

            // Calculate stock available
            $stockAvailable = $_POST['stock_in'] - $_POST['stock_out'];

            // Execute with user inputs
            $stmt->execute([
                $_POST['product_id'],
                $_POST['stock_in'],
                $_POST['stock_out'],
                $_POST['expiry_date'],
                $stockAvailable,
                $_POST['low_stock_threshold'],
                $_POST['inventory_id']
            ]);

            // Success message
            $successMessage = "Inventory updated successfully!";
        } catch(Exception $e) {
            if ($e->getMessage() != "Duplicate product") {
            $errorMessage = "Error updating inventory: " . $e->getMessage();
            }
        }
    }
    
if ($_POST['action'] === 'delete_inventory' && isset($_POST['inventory_id'])) {
    try {
        // Prepare the SQL statement for deleting the inventory
        $stmt = $pdo->prepare("
            DELETE FROM inventory
            WHERE inventory_id = ?
        ");

        // Execute the deletion
        $stmt->execute([$_POST['inventory_id']]);

        // Log the action
        logAction(getCurrentUserId(), 'Deleted inventory with ID: ' . $_POST['inventory_id']);

        // Set success message
        $successMessage = "Inventory deleted successfully!";
    } catch (PDOException $e) {
        $errorMessage = "Error deleting inventory: " . $e->getMessage();
    }
}

if ($_POST['action'] === 'quarantine_expired') {
    try {
        // Update inventory status to quarantined
        $stmt = $pdo->prepare("
            UPDATE inventory
            SET status = 'quarantined'
            WHERE product_id = ? AND expiry_date < CURDATE() AND stock_available > 0
        ");
        
        $stmt->execute([$_POST['product_id']]);
        
        // Get product name for the log
        $productName = $pdo->query("SELECT product_name FROM product WHERE product_id = " . $_POST['product_id'])->fetchColumn();
        
        // Log the action
        logAction(getCurrentUserId(), 'Quarantined expired product: ' . $productName);
        
        // Set success message
        $successMessage = "Expired product quarantined successfully!";
    } catch(PDOException $e) {
        $errorMessage = "Error quarantining product: " . $e->getMessage();
    }
}
}

// Handle GET requests for quarantine action
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'quarantine' && isset($_GET['id'])) {
    $productId = $_GET['id'];
    
    try {
        // Get product details
        $stmt = $pdo->prepare("
            SELECT p.product_name, MIN(i.expiry_date) as earliest_expiry
            FROM product p
            JOIN inventory i ON p.product_id = i.product_id
            WHERE p.product_id = ? AND i.status = 'active'
            GROUP BY p.product_id, p.product_name
        ");
        
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Check if product is actually expired
            $expiryDate = new DateTime($product['earliest_expiry']);
            $today = new DateTime();
            
            if ($expiryDate < $today) {
                // Show quarantine confirmation form
                $quarantineForm = true;
                $quarantineProduct = $product;
                $quarantineProductId = $productId;
            } else {
                $errorMessage = "This product is not expired yet. Earliest expiry date is " . $expiryDate->format('m/d/Y');
            }
        } else {
            $errorMessage = "Product not found or has no active inventory.";
        }
    } catch(PDOException $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
}

try {
    // Get the highest inventory_id
    $lastInventoryId = $pdo->query("
        SELECT MAX(inventory_id) AS max_id FROM inventory
    ")->fetchColumn();

    // Calculate the next inventory ID
    $nextInventoryId = $lastInventoryId ? $lastInventoryId + 1 : 1; // If no data exists, start from 1
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
    $nextInventoryId = 1; // Default to 1 in case of error
}

// Get inventory with product names
try {
    $inventory = $pdo->query("
        SELECT i.*, p.product_name
        FROM inventory i
        JOIN product p ON i.product_id = p.product_id
        ORDER BY i.inventory_id DESC
    ")->fetchAll();
    
    // Get products for dropdown
    $products = $pdo->query("SELECT product_id, product_name FROM product ORDER BY product_name")->fetchAll();
    
    // Get list of products already in inventory for client-side validation
    $existingProducts = $pdo->query("
        SELECT DISTINCT product_id 
        FROM inventory 
        WHERE status = 'active'
    ")->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Log the page access
logAction(getCurrentUserId(), 'Viewed Inventory');

// Start output buffering
ob_start();
?>

<!-- Success/Error Messages -->
<?php if (isset($successMessage)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
        <?php echo $errorMessage; ?>
    </div>
<?php endif; ?>

<?php if (isset($quarantineForm) && $quarantineForm): ?>
<div class="bg-red-50 border border-red-400 text-red-700 px-6 py-4 rounded-lg mb-6">
    <div class="flex items-center mb-4">
        <div class="text-red-500 mr-3">
            <i class="fas fa-exclamation-triangle text-2xl"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold">Quarantine Expired Product</h3>
            <p>You are about to quarantine all expired inventory for the following product:</p>
        </div>
    </div>
    
    <div class="bg-white p-4 rounded-lg mb-4">
        <p><strong>Product:</strong> <?php echo htmlspecialchars($quarantineProduct['product_name']); ?></p>
        <p><strong>Earliest Expiry Date:</strong> <?php echo date('m/d/Y', strtotime($quarantineProduct['earliest_expiry'])); ?></p>
        <p class="text-red-600 font-bold mt-2">
            <i class="fas fa-exclamation-circle"></i> 
            This product is expired and should be removed from active inventory.
        </p>
    </div>
    
    <form action="inventory.php" method="POST" class="mt-4">
        <input type="hidden" name="action" value="quarantine_expired">
        <input type="hidden" name="product_id" value="<?php echo $quarantineProductId; ?>">
        
        <div class="flex justify-end space-x-3">
            <a href="inventory.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                <i class="fas fa-exclamation-triangle mr-1"></i> Quarantine Expired Product
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Page Header with Add Button -->
<div class="flex justify-between items-center mb-6">
    <div class="relative">
        <input type="text" id="searchInput" placeholder="Search inventory..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
        </div>
    </div>
    <button onclick="showAddInventoryModal()" class="bg-purple-700 hover:bg-purple-800 text-white py-2 px-4 rounded-lg flex items-center">
        <i class="fas fa-plus mr-2"></i> Add Inventory
    </button>
</div>

<!-- Search Results Message -->
<div id="searchResultsMessage" class="hidden mb-4 text-gray-600 italic text-center"></div>

<!-- Inventory Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock In</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Out</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Available</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Threshold</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($inventory as $item): ?>
                <?php 
                    $isLowStock = $item['stock_available'] <= $item['low_stock_threshold'] && $item['stock_available'] > 0;
                    $isOutOfStock = $item['stock_available'] <= 0;
                    $isExpired = strtotime($item['expiry_date']) < time();
                    
                    // Determine status class and text
                    if ($isExpired) {
                        $statusClass = 'bg-red-100 text-red-800';
                        $statusText = 'Expired';
                    } elseif ($isOutOfStock) {
                        $statusClass = 'bg-red-500 text-white';
                        $statusText = 'Out of Stock';
                    } elseif ($isLowStock) {
                        $statusClass = 'bg-yellow-100 text-yellow-800';
                        $statusText = 'Low Stock';
                    } else {
                        $statusClass = 'bg-green-100 text-green-800';
                        $statusText = 'Good';
                    }
                    
                    // Add special class for negative or zero stock
                    $stockClass = $item['stock_available'] <= 0 ? 'text-red-600 font-bold' : 'text-gray-500';
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['inventory_id']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo $item['product_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['stock_in']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['stock_out']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo $stockClass; ?>">
                        <?php echo $item['stock_available']; ?>
                        <?php if ($item['stock_available'] < 0): ?>
                            <i class="fas fa-exclamation-triangle text-red-500 ml-1" title="Negative stock detected"></i>
                        <?php elseif ($item['stock_available'] == 0): ?>
                            <i class="fas fa-ban text-red-500 ml-1" title="Out of stock"></i>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['low_stock_threshold']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('m/d/Y', strtotime($item['expiry_date'])); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="showEditInventoryModal(<?php echo $item['inventory_id']; ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmDeleteInventory(<?php echo $item['inventory_id']; ?>, '<?php echo addslashes($item['product_name']); ?>')" class="text-red-600 hover:text-red-900">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <?php if (count($inventory) === 0): ?>
                <tr>
                    <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">No inventory items found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    const nextInventoryId = <?php echo $nextInventoryId; ?>;
    // Create a list of existing product IDs for client-side validation
    const existingProducts = <?php echo json_encode($existingProducts); ?>;

    // Show Add Inventory Modal
    function showAddInventoryModal() {
        const modalContent = `
            <form action="inventory.php" method="post" class="space-y-4" onsubmit="return validateInventoryForm(this)">
                <input type="hidden" name="action" value="add_inventory">

                <!-- Inventory ID (Read-Only) -->
                <div class="form-group">
                    <label for="inventory_id" class="form-label">Inventory ID</label>
                    <input type="text" id="inventory_id" name="inventory_id" value="${nextInventoryId}" class="form-input bg-gray-100" readonly>
                </div>

                <div class="form-group">
                    <label for="product_id" class="form-label">Product</label>
                    <select id="product_id" name="product_id" class="form-select" required onchange="checkDuplicateProduct(this.value)">
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['product_id']; ?>"><?php echo $product['product_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="duplicate_product_warning" class="text-red-600 text-sm mt-1 hidden">
                        Warning: This product already exists in inventory. Please edit the existing entry instead.
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="stock_in" class="form-label">Stock In</label>
                        <input type="number" id="stock_in" name="stock_in" class="form-input" min="0" required onchange="updateAvailableStock()" oninput="validatePositiveNumber(this)">
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_out" class="form-label">Stock Out</label>
                        <input type="number" id="stock_out" name="stock_out" class="form-input" min="0" value="0" required onchange="updateAvailableStock()" oninput="validatePositiveNumber(this)">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Available Stock (calculated)</label>
                    <div id="available_stock_display" class="form-input bg-gray-100">0</div>
                    <div id="stock_warning" class="text-red-600 text-sm mt-1 hidden">Warning: Stock Out cannot exceed Stock In</div>
                    <div id="stock_status_display" class="text-sm mt-1 text-gray-500">Enter threshold to see status</div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="expiry_date" class="form-label">Expiry Date</label>
                        <input type="date" id="expiry_date" name="expiry_date" class="form-input" required min="${new Date().toISOString().split('T')[0]}">
                    </div>
                    
                    <div class="form-group">
                        <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                        <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-input" min="1" required oninput="validatePositiveNumber(this); updateAvailableStock();">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" id="submit_button" class="btn-primary">Add Inventory</button>
                </div>
            </form>
        `;
        
        openModal('Add New Inventory', modalContent);
        
        // Initialize the stock_out field with an empty value
        setTimeout(() => {
            const stockOutField = document.getElementById('stock_out');
            if (stockOutField) {
                stockOutField.value = '';
            }
        }, 100);
    }

// Show Edit Inventory Modal
function showEditInventoryModal(inventoryId) {
        // Fetch inventory data from the PHP variable
        const inventory = <?php echo json_encode($inventory); ?>;
        const products = <?php echo json_encode($products); ?>;
        const item = inventory.find(i => i.inventory_id == inventoryId);

        if (!item) {
            alert('Inventory item not found.');
            return;
        }

        // Format the expiry date for the date input (YYYY-MM-DD)
        const expiryDate = new Date(item.expiry_date);
        const formattedExpiryDate = expiryDate.toISOString().split('T')[0];
        
        // Generate product options with the correct one selected
        let productOptions = '<option value="">Select Product</option>';
        products.forEach(product => {
            const selected = parseInt(product.product_id) === parseInt(item.product_id) ? 'selected' : '';
            productOptions += `<option value="${product.product_id}" ${selected}>${product.product_name}</option>`;
        });

        // Calculate stock percentage
        const stockPercentage = item.low_stock_threshold > 0 ? 
            ((item.stock_available / item.low_stock_threshold) * 100).toFixed(1) : 0;
        
        // Get status class based on percentage
        let statusClass = '';
        if (item.stock_available <= 0) {
            statusClass = 'text-red-600 font-bold';
        } else if (stockPercentage <= 10) {
            statusClass = 'text-red-600';
        } else if (stockPercentage <= 25) {
            statusClass = 'text-orange-600';
        } else if (stockPercentage <= 50) {
            statusClass = 'text-yellow-600';
        } else {
            statusClass = 'text-green-600';
        }

        // Dynamically populate the modal fields
        const modalContent = `
            <form action="inventory.php" method="POST" class="space-y-4" onsubmit="return validateInventoryForm(this)">
                <input type="hidden" name="action" value="edit_inventory">
                <input type="hidden" name="inventory_id" value="${item.inventory_id}">
                <input type="hidden" id="original_product_id" value="${item.product_id}">

                <div class="form-group">
                    <label for="edit_product_id" class="form-label">Product</label>
                    <select id="edit_product_id" name="product_id" class="form-select" required onchange="checkDuplicateProductEdit(this.value)">
                        ${productOptions}
                    </select>
                    <div id="edit_duplicate_product_warning" class="text-red-600 text-sm mt-1 hidden">
                        Warning: This product already exists in inventory. Please edit the existing entry instead.
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="edit_stock_in" class="form-label">Stock In</label>
                        <input type="number" id="edit_stock_in" name="stock_in" class="form-input" min="0" value="${item.stock_in}" required onchange="updateEditAvailableStock()">
                    </div>

                    <div class="form-group">
                        <label for="edit_stock_out" class="form-label">Stock Out</label>
                        <input type="number" id="edit_stock_out" name="stock_out" class="form-input" min="0" value="${item.stock_out}" required onchange="updateEditAvailableStock()">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Available Stock (calculated)</label>
                    <div id="edit_available_stock_display" class="form-input bg-gray-100">${item.stock_available}</div>
                    <div id="edit_stock_status_display" class="text-sm mt-1 ${statusClass}">
                        ${stockPercentage}% of threshold
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="edit_expiry_date" class="form-label">Expiry Date</label>
                        <input type="date" id="edit_expiry_date" name="expiry_date" class="form-input" value="${formattedExpiryDate}" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_low_stock_threshold" class="form-label">Low Stock Threshold</label>
                        <input type="number" id="edit_low_stock_threshold" name="low_stock_threshold" class="form-input" min="1" value="${item.low_stock_threshold}" required onchange="updateEditAvailableStock()">
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" id="edit_submit_button" class="btn-primary">Save Changes</button>
                </div>
            </form>`;

        // Open the modal with the dynamically generated content
        openModal('Edit Inventory', modalContent);
}

    // Validate positive numbers only
    function validatePositiveNumber(input) {
        // Remove any negative sign
        if (input.value.startsWith('-')) {
            input.value = input.value.substring(1);
        }
        
        // Ensure the value is a number and not less than 0
        const value = parseFloat(input.value);
        if (isNaN(value) || value < 0) {
            input.value = 0;
        }
    }

    // Update available stock calculation for add form
    function updateAvailableStock() {
        const stockIn = parseInt(document.getElementById('stock_in').value) || 0;
        const stockOut = parseInt(document.getElementById('stock_out').value) || 0;
        const availableStock = stockIn - stockOut;
        const threshold = parseInt(document.getElementById('low_stock_threshold').value) || 0;
        
        const display = document.getElementById('available_stock_display');
        const warning = document.getElementById('stock_warning');
        const submitButton = document.getElementById('submit_button');
        const statusDisplay = document.getElementById('stock_status_display');
        
        // Update the available stock display
        display.textContent = availableStock;
        
        // Show warning and disable submit button if stock out > stock in
        if (availableStock < 0) {
            display.classList.add('text-red-600', 'font-bold');
            warning.classList.remove('hidden');
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            display.classList.remove('text-red-600', 'font-bold');
            warning.classList.add('hidden');
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        
        // Calculate and display percentage if threshold is set
        if (threshold > 0 && availableStock >= 0) {
            const percentage = updateStockStatus(availableStock, threshold, null, statusDisplay);
            
            // Update the status display
            if (statusDisplay) {
                const percentText = percentage.toFixed(1) + '% of threshold';
                statusDisplay.textContent = percentText;
                
                // Apply color coding
                statusDisplay.className = 'text-sm mt-1'; // Reset classes
                if (percentage <= 0) {
                    statusDisplay.classList.add('text-red-600', 'font-bold');
                } else if (percentage <= 10) {
                    statusDisplay.classList.add('text-red-600');
                } else if (percentage <= 25) {
                    statusDisplay.classList.add('text-orange-600');
                } else if (percentage <= 50) {
                    statusDisplay.classList.add('text-yellow-600');
                } else {
                    statusDisplay.classList.add('text-green-600');
                }
            }
        }
    }

    // Update available stock calculation for edit form
    function updateEditAvailableStock() {
        const stockIn = parseInt(document.getElementById('edit_stock_in').value) || 0;
        const stockOut = parseInt(document.getElementById('edit_stock_out').value) || 0;
        const availableStock = stockIn - stockOut;
        const threshold = parseInt(document.getElementById('edit_low_stock_threshold').value) || 0;
        
        const display = document.getElementById('edit_available_stock_display');
        const statusDisplay = document.getElementById('edit_stock_status_display');
        
        // Update the available stock display
        display.textContent = availableStock;
        
        // Highlight negative values
        if (availableStock < 0) {
            display.classList.add('text-red-600', 'font-bold');
        } else if (availableStock === 0) {
            display.classList.add('text-red-600');
            display.classList.remove('font-bold');
        } else {
            display.classList.remove('text-red-600', 'font-bold');
        }
        
        // Calculate and display percentage if threshold is set
        if (threshold > 0 && availableStock >= 0) {
            const percentage = updateStockStatus(availableStock, threshold, null, statusDisplay);
            
            // Update the status display
            if (statusDisplay) {
                const percentText = percentage.toFixed(1) + '% of threshold';
                statusDisplay.textContent = percentText;
                
                // Apply color coding
                statusDisplay.className = 'text-sm mt-1'; // Reset classes
                if (percentage <= 0) {
                    statusDisplay.classList.add('text-red-600', 'font-bold');
                } else if (percentage <= 10) {
                    statusDisplay.classList.add('text-red-600');
                } else if (percentage <= 25) {
                    statusDisplay.classList.add('text-orange-600');
                } else if (percentage <= 50) {
                    statusDisplay.classList.add('text-yellow-600');
                } else {
                    statusDisplay.classList.add('text-green-600');
                }
            }
        }
    }

    // Check for duplicate product when adding
    function checkDuplicateProduct(productId) {
        const warning = document.getElementById('duplicate_product_warning');
        const submitButton = document.getElementById('submit_button');
        
        if (existingProducts.includes(parseInt(productId))) {
            warning.classList.remove('hidden');
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            warning.classList.add('hidden');
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }
    
    // Check for duplicate product when editing
    function checkDuplicateProductEdit(productId) {
        const warning = document.getElementById('edit_duplicate_product_warning');
        const submitButton = document.getElementById('edit_submit_button');
        const originalProductId = document.getElementById('original_product_id').value;
        
        // Only check for duplicates if the product is being changed
        if (productId != originalProductId && existingProducts.includes(parseInt(productId))) {
            warning.classList.remove('hidden');
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            warning.classList.add('hidden');
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    // Validate inventory form before submission
    function validateInventoryForm(form) {
        const stockIn = parseInt(form.querySelector('[name="stock_in"]').value) || 0;
        const stockOut = parseInt(form.querySelector('[name="stock_out"]').value) || 0;
        const availableStock = stockIn - stockOut;
        
        if (availableStock < 0) {
            alert('Error: Stock Out cannot be greater than Stock In. This would result in negative inventory.');
            return false;
        }
        
        const expiryDate = new Date(form.querySelector('[name="expiry_date"]').value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (expiryDate < today) {
            if (!confirm('Warning: The expiry date is in the past. Are you sure you want to continue?')) {
                return false;
            }
        }
        
        // Check for duplicate product
        const productId = parseInt(form.querySelector('[name="product_id"]').value);
        const action = form.querySelector('[name="action"]').value;
        
        if (action === 'add_inventory' && existingProducts.includes(productId)) {
            alert('This product already exists in inventory. Please edit the existing entry instead.');
            return false;
        }
        
        if (action === 'edit_inventory') {
            const originalProductId = parseInt(document.getElementById('original_product_id').value);
            if (productId !== originalProductId && existingProducts.includes(productId)) {
                alert('This product already exists in inventory. Please edit the existing entry instead.');
                return false;
            }
        }
        
        return true;
}
    
    // Confirm Delete Inventory
    function confirmDeleteInventory(inventoryId, productName) {
    if (confirm('Are you sure you want to delete inventory for product: ' + productName + '?')) {
        // Dynamically create a form to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ''; // Submit to the same page

        // Add action input to specify the delete operation
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_inventory';

        // Add inventoryId input to identify the inventory to delete
        const inventoryIdInput = document.createElement('input');
        inventoryIdInput.type = 'hidden';
        inventoryIdInput.name = 'inventory_id';
        inventoryIdInput.value = inventoryId;

        // Append inputs to the form
        form.appendChild(actionInput);
        form.appendChild(inventoryIdInput);

        // Append the form to the document and submit it
        document.body.appendChild(form);
        form.submit();
    }
}
    
    // Search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const table = document.querySelector('table');
        const tbody = table.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        const searchResultsMessage = document.getElementById('searchResultsMessage');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let matchCount = 0;
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                
                if (searchTerm === '') {
                    // Reset all highlighting when search is cleared
                    row.querySelectorAll('mark').forEach(mark => {
                        const parent = mark.parentNode;
                        parent.replaceChild(document.createTextNode(mark.textContent), mark);
                        parent.normalize(); // Combine adjacent text nodes
                    });
                    row.style.display = '';
                    matchCount++;
                } else if (text.includes(searchTerm)) {
                    // Show row and highlight matches
                    row.style.display = '';
                    matchCount++;
                    
                    // Only process text nodes for highlighting
                    row.querySelectorAll('td').forEach(cell => {
                        // Skip cells with buttons or special elements
                        if (cell.querySelector('button') || cell.querySelector('i') || cell.querySelector('span.px-2')) {
                            return;
                        }
                        
                        // First remove any existing highlights
                        cell.querySelectorAll('mark').forEach(mark => {
                            const parent = mark.parentNode;
                            parent.replaceChild(document.createTextNode(mark.textContent), mark);
                            parent.normalize();
                        });
                        
                        // Then add new highlights by only targeting text nodes
                        highlightTextNodes(cell, searchTerm);
                    });
                } else {
                    // Hide non-matching rows
                    row.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (matchCount === 0 && searchTerm !== '') {
                searchResultsMessage.textContent = 'No matching records found';
                searchResultsMessage.classList.remove('hidden');
            } else {
                searchResultsMessage.classList.add('hidden');
            }
        });
        
        // Function to safely highlight text within text nodes only
        function highlightTextNodes(element, searchTerm) {
            if (!element) return;
            
            const nodeIterator = document.createNodeIterator(
                element,
                NodeFilter.SHOW_TEXT,
                { acceptNode: node => node.textContent.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT }
            );
            
            const matches = [];
            let currentNode;
            
            // First, collect all text nodes that contain the search term
            while (currentNode = nodeIterator.nextNode()) {
                if (currentNode.textContent.toLowerCase().includes(searchTerm)) {
                    matches.push(currentNode);
                }
            }
            
            // Then process the matches (in reverse to avoid issues with node replacement)
            for (let i = matches.length - 1; i >= 0; i--) {
                const node = matches[i];
                const text = node.textContent;
                const parent = node.parentNode;
                
                // Skip if parent is already a mark or other special element
                if (parent.nodeName === 'MARK' || parent.nodeName === 'BUTTON' || parent.nodeName === 'I') {
                    continue;
                }
                
                const fragment = document.createDocumentFragment();
                let lastIndex = 0;
                
                // Use regex to find all instances of the search term
                const regex = new RegExp(searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
                let match;
                
                while ((match = regex.exec(text)) !== null) {
                    // Add text before the match
                    if (match.index > lastIndex) {
                        fragment.appendChild(document.createTextNode(text.substring(lastIndex, match.index)));
                    }
                    
                    // Add the highlighted match
                    const mark = document.createElement('mark');
                    mark.className = 'bg-yellow-200';
                    mark.textContent = match[0];
                    fragment.appendChild(mark);
                    
                    lastIndex = regex.lastIndex;
                }
                
                // Add any remaining text
                if (lastIndex < text.length) {
                    fragment.appendChild(document.createTextNode(text.substring(lastIndex)));
                }
                
                // Replace the original node with the fragment
                parent.replaceChild(fragment, node);
            }
        }
    });
</script>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout
include 'layout.php';
?> 
<?php
require_once 'config.php';
checkLogin();

// Add this line right after the require statements
define('DEBUG', true); // Set to false in production

// Page specific variables
$pageTitle = "Expired Products";
$pageIcon = "fas fa-calendar-times";

// Process form submission for disposal actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'dispose_product') {
        try {
            // Prepare the SQL statement to insert into expired_products table
            $stmt = $pdo->prepare("
                INSERT INTO expired_products (
                    inventory_id,
                    product_id,
                    quantity,
                    expiry_date,
                    disposal_date,
                    disposal_method,
                    disposal_notes,
                    disposal_by
                ) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?)
            ");
            
            // Execute with the form data
            $stmt->execute([
                $_POST['inventory_id'],
                $_POST['product_id'],
                $_POST['quantity'],
                $_POST['expiry_date'],
                $_POST['disposal_method'],
                $_POST['disposal_notes'],
                getCurrentUserId()
            ]);
            
            // Log the action
            logAction(getCurrentUserId(), 'Disposed expired product ID: ' . $_POST['product_id']);
            
            // Set success message
            $successMessage = "Product marked for disposal successfully!";
        } catch(PDOException $e) {
            $errorMessage = "Error marking product for disposal: " . $e->getMessage();
        }
    }
    
    // Update edit status action
    if ($_POST['action'] === 'edit_status') {
        try {
            // First check if a record exists
            $checkStmt = $pdo->prepare("
                SELECT * FROM expired_products 
                WHERE inventory_id = ? AND product_id = ?
            ");
            $checkStmt->execute([
                $_POST['inventory_id'],
                $_POST['product_id']
            ]);
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing record
                $stmt = $pdo->prepare("
                    UPDATE expired_products 
                    SET disposal_method = ?,
                        disposal_notes = ?,
                        disposal_by = ?
                    WHERE inventory_id = ? AND product_id = ?
                ");
                
                $stmt->execute([
                    $_POST['disposal_method'],
                    $_POST['disposal_notes'],
                    getCurrentUserId(),
                    $_POST['inventory_id'],
                    $_POST['product_id']
                ]);
            } else {
                // Insert new record only if one doesn't exist
                // Get the next available ID for expired_id
                $nextIdStmt = $pdo->query("SELECT MAX(expired_id) + 1 AS next_id FROM expired_products");
                $nextId = $nextIdStmt->fetch(PDO::FETCH_ASSOC)['next_id'];
                
                // If no records exist or for some reason next_id is null, start with 11
                if (!$nextId) {
                    $nextId = 11;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO expired_products (
                        expired_id,
                        inventory_id,
                        product_id,
                        quantity,
                        expiry_date,
                        disposal_date,
                        disposal_method,
                        disposal_notes,
                        disposal_by
                    ) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)
                ");
                
                // Get the expiry date from inventory
                $expiryStmt = $pdo->prepare("
                    SELECT expiry_date FROM inventory 
                    WHERE inventory_id = ? AND product_id = ?
                ");
                $expiryStmt->execute([
                    $_POST['inventory_id'],
                    $_POST['product_id']
                ]);
                $expiryDate = $expiryStmt->fetchColumn();
                
                $stmt->execute([
                    $nextId,
                    $_POST['inventory_id'],
                    $_POST['product_id'],
                    $_POST['quantity'] ?? 1,
                    $expiryDate,
                    $_POST['disposal_method'],
                    $_POST['disposal_notes'],
                    getCurrentUserId()
                ]);
            }
            
            logAction(getCurrentUserId(), 'Updated expired product status', "Updated status for product ID: {$_POST['product_id']}");
            $successMessage = "Product status updated successfully!";
        } catch(PDOException $e) {
            $errorMessage = "Error updating status: " . $e->getMessage();
        }
    }
    
    // Add new delete action
    if ($_POST['action'] === 'delete_expired') {
        try {
            $stmt = $pdo->prepare("
                DELETE FROM expired_products 
                WHERE inventory_id = ? AND product_id = ?
            ");
            
            $stmt->execute([
                $_POST['inventory_id'],
                $_POST['product_id']
            ]);
            
            logAction(getCurrentUserId(), 'Deleted expired product record', "Deleted record for product ID: {$_POST['product_id']}");
            $successMessage = "Record deleted successfully!";
        } catch(PDOException $e) {
            $errorMessage = "Error deleting record: " . $e->getMessage();
        }
    }
}

// Update the SQL query to only show the latest status for each expired product
try {
    $currentDate = date('Y-m-d');
    
    // Modified query to get only the latest status for each product
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            i.inventory_id,
            i.expiry_date,
            i.stock_available,
            c.category_name,
            s.supplier_name,
            ep.disposal_date,
            ep.disposal_method,
            ep.disposal_notes,
            ep.quantity as disposed_quantity,
            CASE 
                WHEN ep.disposal_date IS NULL THEN 'Pending'
                ELSE ep.disposal_method
            END as status
        FROM product p
        INNER JOIN inventory i ON p.product_id = i.product_id
        LEFT JOIN category c ON p.category_id = c.product_id
        LEFT JOIN supplier s ON p.supplier_id = s.supplier_id
        LEFT JOIN (
            SELECT ep1.*
            FROM expired_products ep1
            LEFT JOIN expired_products ep2 
                ON ep1.product_id = ep2.product_id 
                AND ep1.inventory_id = ep2.inventory_id
                AND ep1.disposal_date < ep2.disposal_date
            WHERE ep2.disposal_date IS NULL
        ) ep ON i.inventory_id = ep.inventory_id AND p.product_id = ep.product_id
        WHERE i.expiry_date <= :currentDate 
        AND i.stock_available > 0
        ORDER BY i.expiry_date ASC
    ");
    
    $stmt->execute(['currentDate' => $currentDate]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add debug logging
    if (empty($products)) {
        error_log("Debug: No expired products found for date: $currentDate");
        
        // Query to check if we have any products at all
        $testQuery = $pdo->query("
            SELECT COUNT(*) as total_products,
                   SUM(CASE WHEN i.expiry_date <= CURDATE() THEN 1 ELSE 0 END) as expired_products
            FROM product p
            JOIN inventory i ON p.product_id = i.product_id
        ");
        $testResult = $testQuery->fetch(PDO::FETCH_ASSOC);
        
        error_log("Debug: Total products: {$testResult['total_products']}, Expired products: {$testResult['expired_products']}");
    }
    
} catch(PDOException $e) {
    error_log("SQL Error in expired products: " . $e->getMessage());
    $errorMessage = "Database error: " . $e->getMessage();
    $products = [];
}

// Add this right after the query to debug the results
if (!empty($products)) {
    error_log("Debug: Found " . count($products) . " expired products");
} else {
    error_log("Debug: No products array is empty");
}

// Define disposal methods
$disposalMethods = [
    'Return to Supplier',
    'Incineration',
    'Medical Waste Disposal',
    'Donation (if applicable)',
    'Pharmacy Disposal Program'
];

// Log the page access
logAction(getCurrentUserId(), 'Viewed Expired Products');

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

<!-- Page Header with Search -->
<div class="flex justify-between items-center mb-6">
    <div class="relative">
        <input type="text" id="searchInput" placeholder="Search expired products..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
        </div>
    </div>
    <button onclick="generateReport()" class="bg-green-600 hover:bg-green-900 text-white py-2 px-4 rounded-lg flex items-center">
        <i class="fas fa-file-alt mr-2"></i> Generate Report
    </button>
</div>

<!-- Search Results Message -->
<div id="searchResultsMessage" class="hidden mb-4 text-gray-600 italic text-center"></div>

<!-- Expired Products Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if (isset($errorMessage)): ?>
        <div class="p-4 text-red-600 bg-red-100">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Info</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                        No expired products found
                        <?php if (isset($testResult)): ?>
                            <br>
                            <small class="text-gray-400">
                                (Total products: <?php echo $testResult['total_products']; ?>, 
                                Expired: <?php echo $testResult['expired_products']; ?>)
                            </small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="text-sm text-gray-500">ID: <?php echo htmlspecialchars($product['product_id']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($product['stock_available'] ?? '0'); ?> units</div>
                            <div class="text-sm text-gray-500">₱<?php echo number_format($product['unit_price'], 2); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                <?php echo date('M d, Y', strtotime($product['expiry_date'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($product['supplier_name'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo htmlspecialchars($product['status']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="showEditStatusModal(
                                <?php echo $product['product_id']; ?>,
                                <?php echo $product['inventory_id']; ?>,
                                '<?php echo addslashes($product['product_name']); ?>',
                                '<?php echo addslashes($product['disposal_method'] ?? ''); ?>',
                                '<?php echo addslashes($product['disposal_notes'] ?? ''); ?>'
                            )" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="confirmDelete(
                                <?php echo $product['product_id']; ?>,
                                <?php echo $product['inventory_id']; ?>,
                                '<?php echo addslashes($product['product_name']); ?>'
                            )" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // Show Disposal Modal
    function showDisposeModal(productId, inventoryId, productName, expiryDate) {
        const modalContent = `
            <form action="expired_product.php" method="post" class="space-y-4">
                <input type="hidden" name="action" value="dispose_product">
                <input type="hidden" name="product_id" value="${productId}">
                <input type="hidden" name="inventory_id" value="${inventoryId}">
                <input type="hidden" name="expiry_date" value="${expiryDate}">
                
                <div class="form-group">
                    <label class="form-label">Product</label>
                    <div class="form-input bg-gray-100">${productName}</div>
                </div>
                
                <div class="form-group">
                    <label for="quantity" class="form-label">Quantity to Dispose</label>
                    <input type="number" id="quantity" name="quantity" class="form-input" min="1" value="1" required>
                </div>
                
                <div class="form-group">
                    <label for="disposal_method" class="form-label">Disposal Method</label>
                    <select id="disposal_method" name="disposal_method" class="form-select" required>
                        <option value="">Select Method</option>
                        <?php foreach ($disposalMethods as $method): ?>
                            <option value="<?php echo $method; ?>"><?php echo $method; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="disposal_notes" class="form-label">Notes</label>
                    <textarea id="disposal_notes" name="disposal_notes" class="form-input" rows="3"></textarea>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary bg-red-700 hover:bg-red-800">Mark for Disposal</button>
                </div>
            </form>
        `;
        
        openModal(`Dispose of Expired Product: ${productName}`, modalContent);
    }
    
    // Reorder Product
    function reorderProduct(productId, productName) {
        if (confirm('Do you want to create a reorder for ' + productName + '?')) {
            // Redirect to purchase order page with pre-filled product
            window.location.href = 'purchase_order.php?add_product=' + productId;
        }
    }
    
    // Generate Report
    function generateReport() {
        // Open report in new window or download as PDF
        window.open('reports/expired_products_report.php', '_blank');
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
                        if (cell.querySelector('button') || cell.querySelector('i')) {
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

    // Edit Status Modal
    function showEditStatusModal(productId, inventoryId, productName, currentMethod, currentNotes) {
        const modalContent = `
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_status">
                <input type="hidden" name="product_id" value="${productId}">
                <input type="hidden" name="inventory_id" value="${inventoryId}">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Product</label>
                    <div class="mt-1 p-2 bg-gray-50 border border-gray-200 rounded-md text-gray-600">
                        ${productName}
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="disposal_method" class="block text-sm font-medium text-gray-700">
                        Disposal Method <span class="text-red-500">*</span>
                    </label>
                    <select id="disposal_method" name="disposal_method" 
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md" 
                        required>
                        <option value="">Select Method</option>
                        <option value="Return to Supplier" ${currentMethod === 'Return to Supplier' ? 'selected' : ''}>Return to Supplier</option>
                        <option value="Incineration" ${currentMethod === 'Incineration' ? 'selected' : ''}>Incineration</option>
                        <option value="Medical Waste Disposal" ${currentMethod === 'Medical Waste Disposal' ? 'selected' : ''}>Medical Waste Disposal</option>
                        <option value="Donation" ${currentMethod === 'Donation' ? 'selected' : ''}>Donation (if applicable)</option>
                        <option value="Pharmacy Disposal Program" ${currentMethod === 'Pharmacy Disposal Program' ? 'selected' : ''}>Pharmacy Disposal Program</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="disposal_notes" class="block text-sm font-medium text-gray-700">
                        Notes
                    </label>
                    <textarea id="disposal_notes" name="disposal_notes" rows="3" 
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Enter any additional notes about the disposal">${currentNotes || ''}</textarea>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" 
                        class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Update Status
                    </button>
                </div>
            </form>
        `;
        
        openModal(`Update Status: ${productName}`, modalContent);
    }
    
    // Delete Confirmation
    function confirmDelete(productId, inventoryId, productName) {
        if (confirm(`Are you sure you want to delete the expired product record for ${productName}?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_expired';
            
            const productIdInput = document.createElement('input');
            productIdInput.type = 'hidden';
            productIdInput.name = 'product_id';
            productIdInput.value = productId;
            
            const inventoryIdInput = document.createElement('input');
            inventoryIdInput.type = 'hidden';
            inventoryIdInput.name = 'inventory_id';
            inventoryIdInput.value = inventoryId;
            
            form.appendChild(actionInput);
            form.appendChild(productIdInput);
            form.appendChild(inventoryIdInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout
include 'layout.php';
?>
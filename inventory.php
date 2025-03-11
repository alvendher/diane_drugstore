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
        } catch(PDOException $e) {
            $errorMessage = "Error adding inventory: " . $e->getMessage();
        }
    }

if ($_POST['action'] === 'edit_inventory') {
        try {
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
        } catch(PDOException $e) {
            $errorMessage = "Error updating inventory: " . $e->getMessage();
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
                    $isLowStock = $item['stock_available'] <= $item['low_stock_threshold'];
                    $isExpired = strtotime($item['expiry_date']) < time();
                    $statusClass = $isExpired ? 'bg-red-100 text-red-800' : ($isLowStock ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800');
                    $statusText = $isExpired ? 'Expired' : ($isLowStock ? 'Low Stock' : 'Good');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['inventory_id']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo $item['product_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['stock_in']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['stock_out']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['stock_available']; ?></td>
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

    // Show Add Inventory Modal
    function showAddInventoryModal() {
        const modalContent = `
            <form action="inventory.php" method="post" class="space-y-4">
                <input type="hidden" name="action" value="add_inventory">

                <!-- Inventory ID (Read-Only) -->
                <div class="form-group">
                    <label for="inventory_id" class="form-label">Inventory ID</label>
                    <input type="text" id="inventory_id" name="inventory_id" value="${nextInventoryId}" class="form-input bg-gray-100" readonly>
                </div>

                <div class="form-group">
                    <label for="product_id" class="form-label">Product</label>
                    <select id="product_id" name="product_id" class="form-select" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['product_id']; ?>"><?php echo $product['product_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="stock_in" class="form-label">Stock In</label>
                        <input type="number" id="stock_in" name="stock_in" class="form-input" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_out" class="form-label">Stock Out</label>
                        <input type="number" id="stock_out" name="stock_out" class="form-input" min="0" value="0" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="expiry_date" class="form-label">Expiry Date</label>
                        <input type="date" id="expiry_date" name="expiry_date" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                        <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-input" min="1" required>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add Inventory</button>
                </div>
            </form>
        `;
        
        openModal('Add New Inventory', modalContent);
    }
// Show Edit Inventory Modal
function showEditInventoryModal(inventoryId) {
        // Fetch inventory data from the PHP variable
        const inventory = <?php echo json_encode($inventory); ?>;
        const item = inventory.find(i => i.inventory_id == inventoryId);

        if (!item) {
            alert('Inventory item not found.');
            return;
        }

        // Dynamically populate the modal fields
        const modalContent = `
            <form action="inventory.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_inventory">
                <input type="hidden" name="inventory_id" value="${item.inventory_id}">

                <div class="form-group">
                    <label for="edit_product_id" class="form-label">Product</label>
                    <select id="edit_product_id" name="product_id" class="form-select" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>

                            <option value="<?php echo $product['product_id']; ?>" <?php echo isset($item['product_id']) && $item['product_id'] == $product['product_id'] ? "selected" : ""; ?>>
                                <?php echo $product['product_name']; ?>
                            </option>

                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="edit_stock_in" class="form-label">Stock In</label>
                        <input type="number" id="edit_stock_in" name="stock_in" class="form-input" min="0" value="${item.stock_in}" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_stock_out" class="form-label">Stock Out</label>
                        <input type="number" id="edit_stock_out" name="stock_out" class="form-input" min="0" value="${item.stock_out}" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="edit_expiry_date" class="form-label">Expiry Date</label>
                        <input type="date" id="edit_expiry_date" name="expiry_date" class="form-input" value="${item.expiry_date}" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_low_stock_threshold" class="form-label">Low Stock Threshold</label>
                        <input type="number" id="edit_low_stock_threshold" name="low_stock_threshold" class="form-input" min="1" value="${item.low_stock_threshold}" required>
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>`;

        // Open the modal with the dynamically generated content
        openModal('Edit Inventory', modalContent);
}
function hideEditInventoryModal() {
    document.getElementById('editInventoryModal').classList.add('hidden');
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
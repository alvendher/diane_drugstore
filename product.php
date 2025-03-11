<?php
global $pdo;
require_once 'config.php';

// Page specific variables
$pageTitle = "Products";
$pageIcon = "fas fa-pills";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_product') {
        try {
            // Get the next product_id by finding the current MAX(product_id) and incrementing
            $stmt = $pdo->query("SELECT MAX(product_id) AS max_id FROM product");
            $lastProductId = $stmt->fetchColumn();
            $nextProductId = $lastProductId ? $lastProductId + 1 : 1;  // Default to 1 if no products exist

            // Prepare the SQL statement
            $stmt = $pdo->prepare("
                INSERT INTO product (product_id, product_name, category_id, description, unit_price, base_price, supplier_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Execute with the form data
            $stmt->execute([
                $nextProductId,
                $_POST['product_name'],
                $_POST['category_id'],
                $_POST['description'],
                $_POST['unit_price'],
                $_POST['base_price'],
                $_POST['supplier_id']
            ]);
            
            // Log the action
            logAction(getCurrentUserId(), 'Added new product: ' . $_POST['product_name']);
            
            // Set success message
            $successMessage = "Product added successfully!";
        } catch(PDOException $e) {
            $errorMessage = "Error adding product: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'edit_product') {
    try {
        // Prepare the SQL statement for updating the product
        $stmt = $pdo->prepare("
            UPDATE product
            SET product_name = ?, category_id = ?, description = ?, unit_price = ?, base_price = ?, supplier_id = ?
            WHERE product_id = ?
        ");

        // Execute with the form data
        $stmt->execute([
            $_POST['product_name'],
            $_POST['category_id'],
            $_POST['description'],
            $_POST['unit_price'],
            $_POST['base_price'],
            $_POST['supplier_id'],
            $_POST['product_id']
        ]);

        // Log the action
        logAction(getCurrentUserId(), 'Edited product: ' . $_POST['product_name']);

        // Set success message
        $successMessage = "Product updated successfully!";
    } catch (PDOException $e) {
        $errorMessage = "Error updating product: " . $e->getMessage();
    }
}
if ($_POST['action'] === 'delete_product' && isset($_POST['product_id'])) {
    try {
        // Prepare the SQL statement for deleting the product
        $stmt = $pdo->prepare("
            DELETE FROM product
            WHERE product_id = ?
        ");

        // Execute the deletion
        $stmt->execute([$_POST['product_id']]);

        // Log the action
        logAction(getCurrentUserId(), 'Deleted product with ID: ' . $_POST['product_id']);

        // Set success message
        $successMessage = "Product deleted successfully!";
    } catch (PDOException $e) {
        $errorMessage = "Error deleting product: " . $e->getMessage();
    }
}
}
try {
    // Get the highest product_id
    $lastProductId = $pdo->query("
        SELECT MAX(product_id) AS max_id FROM product
    ")->fetchColumn();

    // If no products exist, set it to 0
    $lastProductId = $lastProductId ? $lastProductId : 0;
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
    $lastProductId = 0; // Default fallback if there's a database error
}

// Get products with category and supplier names
try {
    $products = $pdo->query("
        SELECT p.*, c.category_name, s.supplier_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.product_id
        LEFT JOIN supplier s ON p.supplier_id = s.supplier_id
        ORDER BY p.product_id DESC
    ")->fetchAll();
    
    // Get categories for dropdown
    $categories = $pdo->query("SELECT * FROM category ORDER BY category_name")->fetchAll();
    
    // Get suppliers for dropdown
    $suppliers = $pdo->query("SELECT * FROM supplier ORDER BY supplier_name")->fetchAll();
    
} catch(PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Log the page access
logAction(getCurrentUserId(), 'Viewed Products');

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
        <input type="text" id="searchInput" placeholder="Search products..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
        </div>
    </div>
    <button onclick="showAddProductModal()" class="bg-purple-700 hover:bg-purple-800 text-white py-2 px-4 rounded-lg flex items-center">
        <i class="fas fa-plus mr-2"></i> Add Product
    </button>
</div>

<!-- Search Results Message -->
<div id="searchResultsMessage" class="hidden mb-4 text-gray-600 italic text-center"></div>

<!-- Products Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Base Price</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($products as $product): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $product['product_id']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo $product['product_name']; ?></div>
                        <div class="text-xs text-gray-500"><?php echo substr($product['description'], 0, 50) . (strlen($product['description']) > 50 ? '...' : ''); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $product['category_name']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₱<?php echo number_format($product['unit_price'], 2); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₱<?php echo number_format($product['base_price'], 2); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $product['supplier_name']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="showEditProductModal(<?php echo $product['product_id']; ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmDeleteProduct(<?php echo $product['product_id']; ?>, '<?php echo addslashes($product['product_name']); ?>')" class="text-red-600 hover:text-red-900">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <?php if (count($products) === 0): ?>
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No products found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    const nextProductId = <?php echo $lastProductId + 1; ?>;

    // Show Add Product Modal
    function showAddProductModal() {
        const modalContent = `
            <form action="product.php" method="post" class="space-y-4">
                <input type="hidden" name="action" value="add_product">

                <div class="form-group">
                    <label for="product_id" class="form-label">Product ID</label>
                    <input type="text" id="product_id" class="form-input bg-gray-100" value="${nextProductId}" readonly>
                </div>

                
                <div class="form-group">
                    <label for="product_name" class="form-label">Product Name</label>
                    <input type="text" id="product_name" name="product_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="category_id" class="form-label">Category</label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['product_id']; ?>"><?php echo $category['category_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-input" rows="3"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="unit_price" class="form-label">Unit Price (₱)</label>
                        <input type="number" id="unit_price" name="unit_price" class="form-input" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="base_price" class="form-label">Base Price (₱)</label>
                        <input type="number" id="base_price" name="base_price" class="form-input" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="supplier_id" class="form-label">Supplier</label>
                    <select id="supplier_id" name="supplier_id" class="form-select" required>
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['supplier_id']; ?>"><?php echo $supplier['supplier_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add Product</button>
                </div>
            </form>
        `;
        
        openModal('Add New Product', modalContent);
    }
    
    // Show Edit Product Modal
function showEditProductModal(productId) {
    // Fetch the products array from PHP data
    const products = <?php echo json_encode($products); ?>;
    const categories = <?php echo json_encode($categories); ?>;
    const suppliers = <?php echo json_encode($suppliers); ?>;

    // Find the specific product by its ID
    const product = products.find(p => p.product_id == productId);

    if (!product) {
        alert("Product not found.");
        return;
    }

    // Build the modal content dynamically with prefilled values for editing
    const modalContent = `
        <form action="product.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="product_id" value="${product.product_id}">

            <div class="form-group">
                <label for="edit_product_name" class="form-label">Product Name</label>
                <input type="text" id="edit_product_name" name="product_name" class="form-input" value="${product.product_name}" required>
            </div>

            <div class="form-group">
                <label for="edit_category_id" class="form-label">Category</label>
                <select id="edit_category_id" name="category_id" class="form-select" required>
                    <option value="">Select Category</option>
                    ${categories.map(category => `
                        <option value="${category.product_id}" ${product.category_id == category.product_id ? "selected" : ""}>
                            ${category.category_name}
                        </option>
                    `).join('')}
                </select>
            </div>

            <div class="form-group">
                <label for="edit_description" class="form-label">Description</label>
                <textarea id="edit_description" name="description" class="form-input" rows="3">${product.description || ''}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label for="edit_unit_price" class="form-label">Unit Price (₱)</label>
                    <input type="number" id="edit_unit_price" name="unit_price" class="form-input" step="0.01" min="0" value="${product.unit_price}" required>
                </div>

                <div class="form-group">
                    <label for="edit_base_price" class="form-label">Base Price (₱)</label>
                    <input type="number" id="edit_base_price" name="base_price" class="form-input" step="0.01" min="0" value="${product.base_price}" required>
                </div>
            </div>

            <div class="form-group">
                <label for="edit_supplier_id" class="form-label">Supplier</label>
                <select id="edit_supplier_id" name="supplier_id" class="form-select" required>
                    <option value="">Select Supplier</option>
                    ${suppliers.map(supplier => `
                        <option value="${supplier.supplier_id}" ${product.supplier_id == supplier.supplier_id ? "selected" : ""}>
                            ${supplier.supplier_name}
                        </option>
                    `).join('')}
                </select>
            </div>

            <div class="flex justify-end space-x-2">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    `;

    // Open the modal with the dynamically generated content
    openModal('Edit Product', modalContent);
}
    
    // Confirm Delete Product
    function confirmDeleteProduct(productId, productName) {
        if (confirm('Are you sure you want to delete ' + productName + '?')) {
            // Dynamically create a form to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ''; // Submit to the same page

        // Add action input to specify the delete operation
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_product';

        // Add productId input to identify the product to delete
        const productIdInput = document.createElement('input');
        productIdInput.type = 'hidden';
        productIdInput.name = 'product_id';
        productIdInput.value = productId;

        // Append inputs to the form
        form.appendChild(actionInput);
        form.appendChild(productIdInput);

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
</script>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout
include 'layout.php';
?> 
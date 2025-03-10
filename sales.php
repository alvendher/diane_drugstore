<?php
require_once 'config.php';

// Page specific variables
$pageTitle = "Sales";
$pageIcon = "fas fa-chart-line";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_invoice'])) {
        // Process add invoice form
        $customerId = $_POST['customer_id'];
        $invoiceDate = $_POST['invoice_date'];
        $totalAmount = $_POST['total_amount'];
        $discountId = !empty($_POST['discount_id']) ? $_POST['discount_id'] : null;
        $netTotal = $_POST['net_total'];
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert into sales table
            $stmt = $pdo->prepare("
                INSERT INTO sales (customer_id, invoice_date, total_amount, discount_id, net_total)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$customerId, $invoiceDate, $totalAmount, $discountId, $netTotal]);
            
            // Get the new invoice ID
            $invoiceId = $pdo->lastInsertId();
            
            // Insert sales details
            $productIds = $_POST['product_id'];
            $quantities = $_POST['quantity'];
            $unitPrices = $_POST['unit_price'];
            $subtotals = $_POST['subtotal'];
            
            for ($i = 0; $i < count($productIds); $i++) {
                if (!empty($productIds[$i]) && !empty($quantities[$i])) {
                    // Insert sales detail
                    $stmt = $pdo->prepare("
                        INSERT INTO sales_details (invoice_id, product_id, quantity, unit_price, subtotal)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$invoiceId, $productIds[$i], $quantities[$i], $unitPrices[$i], $subtotals[$i]]);
                    
                    // Update inventory
                    $stmt = $pdo->prepare("
                        UPDATE inventory 
                        SET stock_out = stock_out + ?, 
                            stock_available = stock_available - ?
                        WHERE product_id = ?
                    ");
                    $stmt->execute([$quantities[$i], $quantities[$i], $productIds[$i]]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            logAction(getCurrentUserId(), 'Added Invoice', "Created invoice #$invoiceId for customer #$customerId");
            
            $successMessage = "Invoice added successfully!";
        } catch(PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errorMessage = "Error: " . $e->getMessage();
        }
    }
}

// Get sales data
try {
    $sales = $pdo->query("
        SELECT s.*, c.first_name, c.last_name, d.discount_type, d.discount_amount
        FROM sales s
        LEFT JOIN customer c ON s.customer_id = c.customer_id
        LEFT JOIN discount d ON s.discount_id = d.discount_id
        ORDER BY s.invoice_id DESC
    ")->fetchAll();
    
    // Get customers for dropdown
    $customers = $pdo->query("SELECT customer_id, first_name, last_name FROM customer")->fetchAll();
    
    // Get products for dropdown
    $products = $pdo->query("
        SELECT p.product_id, p.product_name, p.unit_price, i.stock_available
        FROM product p
        JOIN inventory i ON p.product_id = i.product_id
    ")->fetchAll();
    
    // Get discounts for dropdown
    $discounts = $pdo->query("SELECT discount_id, discount_type, discount_amount FROM discount")->fetchAll();
    
} catch(PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
    $sales = [];
    $customers = [];
    $products = [];
    $discounts = [];
}

// Log the page access
logAction(getCurrentUserId(), 'Viewed Sales');

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
        <input type="text" id="searchInput" placeholder="Search invoices..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
        </div>
    </div>
    <div class="flex space-x-2">
        <button onclick="showAddInvoiceModal()" class="bg-purple-700 hover:bg-purple-800 text-white py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> Create Invoice
        </button>
        <button onclick="exportToCSV()" class="bg-green-600 hover:bg-green-900 text-white py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-file-export mr-2"></i> Export
        </button>
    </div>
</div>

<!-- Search Results Message -->
<div id="searchResultsMessage" class="hidden mb-4 text-gray-600 italic text-center"></div>

<!-- Sales Table -->
<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">Invoice ID</th>
                    <th class="py-3 px-6 text-left">Customer</th>
                    <th class="py-3 px-6 text-left">Date</th>
                    <th class="py-3 px-6 text-left">Total Amount</th>
                    <th class="py-3 px-6 text-left">Discount</th>
                    <th class="py-3 px-6 text-left">Net Total</th>
                    <th class="py-3 px-6 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm">
                <?php foreach ($sales as $sale): ?>
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="py-3 px-6"><?php echo $sale['invoice_id']; ?></td>
                        <td class="py-3 px-6"><?php echo $sale['first_name'] . ' ' . $sale['last_name']; ?></td>
                        <td class="py-3 px-6"><?php echo date('M d, Y', strtotime($sale['invoice_date'])); ?></td>
                        <td class="py-3 px-6">₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                        <td class="py-3 px-6">
                            <?php if ($sale['discount_id']): ?>
                                <?php echo $sale['discount_type'] . ' (' . $sale['discount_amount'] . '%)'; ?>
                            <?php else: ?>
                                None
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-6">₱<?php echo number_format($sale['net_total'], 2); ?></td>
                        <td class="py-3 px-6">
                            <div class="flex">
                                <a href="view_invoice.php?id=<?php echo $sale['invoice_id']; ?>" class="text-green-600 hover:text-green-900 mr-2">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_sale.php?id=<?php echo $sale['invoice_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_sale.php?id=<?php echo $sale['invoice_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this invoice?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Show Add Invoice Modal
    function showAddInvoiceModal() {
        const modalContent = `
            <form action="" method="POST" id="invoiceForm" class="space-y-4">
                <input type="hidden" name="add_invoice" value="1">
                
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 mb-2" for="customer_id">Customer</label>
                        <select name="customer_id" id="customer_id" class="w-full p-2 border rounded" required>
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['customer_id']; ?>">
                                    <?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2" for="invoice_date">Invoice Date</label>
                        <input type="date" name="invoice_date" id="invoice_date" class="w-full p-2 border rounded" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2" for="discount_id">Discount</label>
                        <select name="discount_id" id="discount_id" class="w-full p-2 border rounded">
                            <option value="">No Discount</option>
                            <?php foreach ($discounts as $discount): ?>
                                <option value="<?php echo $discount['discount_id']; ?>" data-amount="<?php echo $discount['discount_amount']; ?>">
                                    <?php echo $discount['discount_type'] . ' (' . $discount['discount_amount'] . '%)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <h3 class="font-bold mb-2">Invoice Items</h3>
                <div id="invoice-items">
                    <div class="grid grid-cols-5 gap-2 mb-2 font-bold">
                        <div>Product</div>
                        <div>Available</div>
                        <div>Quantity</div>
                        <div>Unit Price</div>
                        <div>Subtotal</div>
                    </div>
                    <div class="invoice-item grid grid-cols-5 gap-2 mb-2">
                        <div>
                            <select name="product_id[]" class="product-select w-full p-2 border rounded" required>
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['product_id']; ?>" 
                                            data-price="<?php echo $product['unit_price']; ?>"
                                            data-stock="<?php echo $product['stock_available']; ?>">
                                        <?php echo $product['product_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <input type="text" class="stock-display w-full p-2 border rounded bg-gray-100" readonly>
                        </div>
                        <div>
                            <input type="number" name="quantity[]" class="quantity w-full p-2 border rounded" min="1" required>
                        </div>
                        <div>
                            <input type="number" name="unit_price[]" class="unit-price w-full p-2 border rounded" step="0.01" readonly>
                        </div>
                        <div>
                            <input type="number" name="subtotal[]" class="subtotal w-full p-2 border rounded" step="0.01" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <button type="button" id="add-item" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                        <i class="fas fa-plus mr-1"></i> Add Item
                    </button>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="text-right font-bold">Total Amount:</div>
                        <div>
                            <input type="number" name="total_amount" id="total_amount" class="w-full p-2 border rounded" step="0.01" readonly>
                        </div>
                        <div class="text-right font-bold">Discount:</div>
                        <div>
                            <input type="number" id="discount_amount" class="w-full p-2 border rounded" step="0.01" readonly>
                        </div>
                        <div class="text-right font-bold">Net Total:</div>
                        <div>
                            <input type="number" name="net_total" id="net_total" class="w-full p-2 border rounded" step="0.01" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 mt-4">
                    <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Create Invoice
                    </button>
                </div>
            </form>
        `;
        
        openModal('Create New Invoice', modalContent);
        
        // Setup event listeners for the modal form
        setupInvoiceFormListeners();
    }
    
    function setupInvoiceFormListeners() {
        // Initial item setup
        setupItemListeners(document.querySelector('.invoice-item'));
        
        // Add item button
        document.getElementById('add-item').addEventListener('click', function() {
            const itemsContainer = document.getElementById('invoice-items');
            const newItem = document.querySelector('.invoice-item').cloneNode(true);
            
            // Clear values in the cloned item
            newItem.querySelectorAll('input').forEach(input => {
                input.value = '';
            });
            newItem.querySelector('select').selectedIndex = 0;
            
            // Add remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-item text-red-500 ml-2';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', function() {
                newItem.remove();
                calculateTotals();
            });
            
            newItem.querySelector('.subtotal').parentNode.appendChild(removeBtn);
            
            // Add the new item to the container
            itemsContainer.appendChild(newItem);
            
            // Setup event listeners for the new item
            setupItemListeners(newItem);
        });
        
        // Discount change
        document.getElementById('discount_id').addEventListener('change', calculateTotals);
        
        // Form submission
        document.getElementById('invoiceForm').addEventListener('submit', function(e) {
            const items = document.querySelectorAll('.invoice-item');
            let valid = true;
            
            items.forEach(item => {
                const productSelect = item.querySelector('.product-select');
                const quantity = item.querySelector('.quantity');
                const stock = parseInt(productSelect.options[productSelect.selectedIndex]?.dataset.stock || 0);
                
                if (productSelect.value && quantity.value) {
                    if (parseInt(quantity.value) > stock) {
                        alert(`Quantity exceeds available stock for ${productSelect.options[productSelect.selectedIndex].text}`);
                        valid = false;
                    }
                }
            });
            
            if (!valid) {
                e.preventDefault();
            }
        });
    }
    
    function setupItemListeners(item) {
        const productSelect = item.querySelector('.product-select');
        const stockDisplay = item.querySelector('.stock-display');
        const quantity = item.querySelector('.quantity');
        const unitPrice = item.querySelector('.unit-price');
        const subtotal = item.querySelector('.subtotal');
        
        // Product selection change
        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.dataset.price || 0;
            const stock = selectedOption.dataset.stock || 0;
            
            unitPrice.value = price;
            stockDisplay.value = stock;
            quantity.max = stock;
            
            if (quantity.value) {
                subtotal.value = (price * quantity.value).toFixed(2);
            }
            
            calculateTotals();
        });
        
        // Quantity change
        quantity.addEventListener('input', function() {
            if (productSelect.value && this.value) {
                const price = unitPrice.value;
                subtotal.value = (price * this.value).toFixed(2);
                calculateTotals();
            }
        });
    }
    
    function calculateTotals() {
        const subtotals = Array.from(document.querySelectorAll('.subtotal'))
            .map(input => parseFloat(input.value) || 0);
        
        const totalAmount = subtotals.reduce((sum, value) => sum + value, 0);
        document.getElementById('total_amount').value = totalAmount.toFixed(2);
        
        const discountSelect = document.getElementById('discount_id');
        const selectedOption = discountSelect.options[discountSelect.selectedIndex];
        const discountPercentage = selectedOption?.dataset.amount || 0;
        
        const discountAmount = totalAmount * (discountPercentage / 100);
        document.getElementById('discount_amount').value = discountAmount.toFixed(2);
        
        const netTotal = totalAmount - discountAmount;
        document.getElementById('net_total').value = netTotal.toFixed(2);
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
                        if (cell.querySelector('a') || cell.querySelector('i') || cell.querySelector('div.flex')) {
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
                if (parent.nodeName === 'MARK' || parent.nodeName === 'A' || parent.nodeName === 'I') {
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
    
    // Export to CSV function
    function exportToCSV() {
        const table = document.querySelector('table');
        const rows = table.querySelectorAll('tr');
        let csv = [];
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('th, td');
            const rowData = Array.from(cells).map(cell => {
                return '"' + cell.innerText.replace(/"/g, '""') + '"';
            });
            csv.push(rowData.join(','));
        });
        
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', 'sales_report.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
</script>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout
include 'layout.php';
?> 
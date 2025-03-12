
<?php
global $pdo;
require_once 'config.php';
checkLogin();

// Page specific variables
$pageTitle = "Customers";
$pageIcon = "fas fa-users";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_customer') {
        try {
            // Get the next customer_id by finding the current MAX(customer_id) and incrementing
        $stmt = $pdo->query("SELECT MAX(customer_id) AS max_id FROM customer");
        $lastCustomerId = $stmt->fetchColumn();
        $nextCustomerId = $lastCustomerId ? $lastCustomerId + 1 : 1;  // Default to 1 if no customers exist

            // Prepare the SQL statement
            $stmt = $pdo->prepare("
                INSERT INTO customer (customer_id,first_name, last_name, contact_number)
                VALUES (?,?, ?, ?)
            ");

            // Execute with the form data
            $stmt->execute([
                $nextCustomerId,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['contact_number']
            ]);

            // Log the action
            logAction(getCurrentUserId(), 'Added new customer: ' . $_POST['first_name'] . ' ' . $_POST['last_name']);

            // Set success message
            $successMessage = "Customer added successfully!";
        } catch (PDOException $e) {
            $errorMessage = "Error adding customer: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'edit_customer') {
        try {
            // Prepare the SQL statement for updating the customer
            $stmt = $pdo->prepare("
            UPDATE customer
            SET first_name = ?, last_name = ?, contact_number = ?
            WHERE customer_id = ?
        ");

            // Execute with the form data
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['contact_number'],
                $_POST['customer_id']
            ]);

            // Log the action
            logAction(getCurrentUserId(), 'Edited customer: ' . $_POST['first_name'] . ' ' . $_POST['last_name']);

            // Set success message
            $successMessage = "Customer updated successfully!";
        } catch (PDOException $e) {
            $errorMessage = "Error updating customer: " . $e->getMessage();
        }
    }
if ($_POST['action'] === 'delete_customer' && isset($_POST['customer_id'])) {
    try {
        // Prepare the SQL statement for deleting the customer
        $stmt = $pdo->prepare("
            DELETE FROM customer
            WHERE customer_id = ?
        ");

        // Execute the deletion
        $stmt->execute([$_POST['customer_id']]);

        // Log the action
        logAction(getCurrentUserId(), 'Deleted customer with ID: ' . $_POST['customer_id']);

        // Set success message
        $successMessage = "Customer deleted successfully!";
    } catch (PDOException $e) {
        $errorMessage = "Error deleting customer: " . $e->getMessage();
    }
}
}
try {
    // Get the highest customer_id
    $lastCustomerId = $pdo->query("
        SELECT MAX(customer_id) AS max_id FROM customer
    ")->fetchColumn();

    // If there are no customers, set it to 0
    $lastCustomerId = $lastCustomerId ? $lastCustomerId : 0;

} catch(PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
    $lastCustomerId = 0; // Default fallback if there's a database error
}
// Get customers
try {
    $customers = $pdo->query("
        SELECT * FROM customer
        ORDER BY customer_id DESC
    ")->fetchAll();
    
} catch(PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Log the page access
logAction(getCurrentUserId(), 'Viewed Customers');

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
        <input type="text" id="searchInput" placeholder="Search customers..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
        </div>
    </div>
    <button onclick="showAddCustomerModal()" class="bg-purple-700 hover:bg-purple-800 text-white py-2 px-4 rounded-lg flex items-center">
        <i class="fas fa-plus mr-2"></i> Add Customer
    </button>
</div>

<!-- Search Results Message -->
<div id="searchResultsMessage" class="hidden mb-4 text-gray-600 italic text-center"></div>

<!-- Customers Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($customers as $customer): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $customer['customer_id']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $customer['contact_number']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="showEditCustomerModal(<?php echo $customer['customer_id']; ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmDeleteCustomer(<?php echo $customer['customer_id']; ?>, '<?php echo addslashes($customer['first_name'] . ' ' . $customer['last_name']); ?>')" class="text-red-600 hover:text-red-900">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <?php if (count($customers) === 0): ?>
                <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No customers found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // Make the next customer ID available in JavaScript

    const nextCustomerId = <?php echo $lastCustomerId + 1; ?>;

    // Show Add Customer Modal
    function showAddCustomerModal() {

        const modalContent = `
            <form action="customer.php" method="post" class="space-y-4">
                <input type="hidden" name="action" value="add_customer">

                <div class="form-group">
                    <label for="customer_id" class="form-label">Customer ID</label>
                    <input type="text" id="customer_id" class="form-input bg-gray-100" value="${nextCustomerId}" readonly>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="contact_number" class="form-label">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" class="form-input" placeholder="+63-XXX-XXX-XXXX" required>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add Customer</button>
                </div>
            </form>
        `;

        openModal('Add New Customer', modalContent);
    }

    // Show Edit Customer Modal
    function showEditCustomerModal(customerId) {
        // Ensure the `customers` list is encoded properly in the PHP output
        const customers = <?php echo json_encode($customers); ?>;

        // Find the specific customer by ID
        const customer = customers.find(c => c.customer_id == customerId);

        if (!customer) {
            alert("Customer not found.");
            return;
        }
       const modalContent = `
            <form action="customer.php" method="post" class="space-y-4">
                <input type="hidden" name="action" value="edit_customer">
                <input type="hidden" name="customer_id" value="${customer.customer_id}">

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-input" value="${customer.first_name}" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-input" value="${customer.last_name}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="contact_number" class="form-label">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" class="form-input" placeholder="+63-XXX-XXX-XXXX" value="${customer.contact_number}" required>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        `;

        openModal('Edit Customer', modalContent);
    }


    // Confirm Delete Customer
    function confirmDeleteCustomer(customerId, customerName) {
    if (confirm('Are you sure you want to delete ' + customerName + '?')) {
        // Create a form dynamically to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ''; // Same page

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_customer';

        const customerIdInput = document.createElement('input');
        customerIdInput.type = 'hidden';
        customerIdInput.name = 'customer_id';
        customerIdInput.value = customerId;

        form.appendChild(actionInput);
        form.appendChild(customerIdInput);

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
?>  */
<?php
require_once 'config.php';
checkLogin();

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    // Redirect to home page or show access denied
    header('Location: home.php');
    exit;
}

// Page specific variables
$pageTitle = "User";
$pageIcon = "fas fa-user";
$currentPage = "user";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            try {
                // Get the next user_id by finding the current MAX(user_id) and incrementing
                $stmt = $pdo->query("SELECT MAX(user_id) AS max_id FROM user");
                $lastUserId = $stmt->fetchColumn();
                $nextUserId = $lastUserId ? $lastUserId + 1 : 1;  // Default to 1 if no users exist
                
        // Process add user form
                $stmt = $pdo->prepare("
                    INSERT INTO user (user_id, email, username, password_hash, role)
                    VALUES (?, ?, ?, ?, ?)
                ");
        
        // Hash the password
                $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                // Execute with form data
                $stmt->execute([
                    $nextUserId,
                    $_POST['email'],
                    $_POST['username'],
                    $passwordHash,
                    $_POST['role']
                ]);
                
                logAction(getCurrentUserId(), 'Added User', "Added new user: {$_POST['username']} with role {$_POST['role']}");
            
            $successMessage = "User added successfully!";
        } catch(PDOException $e) {
                $errorMessage = "Error adding user: " . $e->getMessage();
            }
        }

        if ($_POST['action'] === 'edit_user') {
            try {
                $updateFields = [];
                $params = [];

                // Add email if provided
                if (!empty($_POST['email'])) {
                    $updateFields[] = "email = ?";
                    $params[] = $_POST['email'];
                }

                // Add username if provided
                if (!empty($_POST['username'])) {
                    $updateFields[] = "username = ?";
                    $params[] = $_POST['username'];
                }

                // Add password if provided
                if (!empty($_POST['password'])) {
                    $updateFields[] = "password_hash = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                // Add role if provided
                if (!empty($_POST['role'])) {
                    $updateFields[] = "role = ?";
                    $params[] = $_POST['role'];
                }

                // Add user_id to params
                $params[] = $_POST['user_id'];

                // Prepare and execute update query
                $stmt = $pdo->prepare("
                    UPDATE user 
                    SET " . implode(", ", $updateFields) . "
                    WHERE user_id = ?
                ");

                $stmt->execute($params);
                
                logAction(getCurrentUserId(), 'Updated User', "Updated user ID: {$_POST['user_id']}");
                
                $successMessage = "User updated successfully!";
            } catch(PDOException $e) {
                $errorMessage = "Error updating user: " . $e->getMessage();
            }
        }

        if ($_POST['action'] === 'delete_user' && isset($_POST['user_id'])) {
            try {
                $stmt = $pdo->prepare("DELETE FROM user WHERE user_id = ?");
                $stmt->execute([$_POST['user_id']]);
                
                logAction(getCurrentUserId(), 'Deleted User', "Deleted user ID: {$_POST['user_id']}");
                
                $successMessage = "User deleted successfully!";
            } catch(PDOException $e) {
                $errorMessage = "Error deleting user: " . $e->getMessage();
            }
        }
    }
}

// Get the highest user_id (add this before getting users data)
try {
    $lastUserId = $pdo->query("
        SELECT MAX(user_id) AS max_id FROM user
    ")->fetchColumn();

    // If no users exist, set it to 0
    $lastUserId = $lastUserId ? $lastUserId : 0;
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
    $lastUserId = 0; // Default fallback if there's a database error
}

// Get users data
try {
    $users = $pdo->query("
        SELECT * FROM user
        ORDER BY user_id DESC
    ")->fetchAll();
    
} catch(PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
    $users = [];
}

// Log the page access
logAction(getCurrentUserId(), 'Viewed Users');

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
        <input type="text" id="searchInput" placeholder="Search users..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
        </div>
    </div>
    <div class="flex space-x-2">
        <button onclick="showAddUserModal()" class="bg-purple-700 hover:bg-purple-800 text-white py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> Add User
        </button>
        <button onclick="exportToCSV()" class="bg-green-600 hover:bg-green-900 text-white py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-file-export mr-2"></i> Export
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">ID</th>
                    <th class="py-3 px-6 text-left">Username</th>
                    <th class="py-3 px-6 text-left">Email</th>
                    <th class="py-3 px-6 text-left">Role</th>
                    <th class="py-3 px-6 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm">
                <?php foreach ($users as $user): ?>
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="py-3 px-6"><?php echo $user['user_id']; ?></td>
                        <td class="py-3 px-6"><?php echo $user['username']; ?></td>
                        <td class="py-3 px-6"><?php echo $user['email']; ?></td>
                        <td class="py-3 px-6"><?php echo $user['role']; ?></td>
                        <td class="py-3 px-6">
                            <div class="flex">
                                <button onclick="showEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="confirmDeleteUser(<?php echo $user['user_id']; ?>, '<?php echo addslashes($user['username']); ?>')" 
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Show Add User Modal
    function showAddUserModal() {
        const nextUserId = <?php echo $lastUserId + 1; ?>;
        
        const modalContent = `
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="add_user" value="1">
                
                <div class="form-group">
                    <label class="block text-gray-700 mb-2" for="user_id">User ID</label>
                    <input type="text" id="user_id" class="w-full p-2 border rounded bg-gray-100" value="${nextUserId}" readonly>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2" for="email">Email</label>
                        <input type="email" name="email" id="email" class="w-full p-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2" for="username">Username</label>
                        <input type="text" name="username" id="username" class="w-full p-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2" for="password">Password</label>
                        <input type="password" name="password" id="password" class="w-full p-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2" for="role">Role</label>
                        <select name="role" id="role" class="w-full p-2 border rounded" required>
                            <option value="">Select Role</option>
                            <option value="Admin">Admin</option>
                            <option value="Pharmacist">Pharmacist</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Inventory">Inventory</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-4">
                    <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        Add User
                    </button>
                </div>
            </form>
        `;
        
        openModal('Add New User', modalContent);
    }
    
    // Simple search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const table = document.querySelector('table');
        const tbody = table.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        
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
                        if (cell.querySelector('a') || cell.querySelector('i')) {
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
        a.setAttribute('download', 'users_report.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Show Edit User Modal
    function showEditUserModal(user) {
        const modalContent = `
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" value="${user.user_id}">

                <div class="form-group">
                    <label class="block text-gray-700 mb-2" for="edit_user_id">User ID</label>
                    <input type="text" id="edit_user_id" class="w-full p-2 border rounded bg-gray-100" value="${user.user_id}" readonly>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2" for="email">Email</label>
                        <input type="email" name="email" id="email" class="w-full p-2 border rounded" value="${user.email}" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2" for="username">Username</label>
                        <input type="text" name="username" id="username" class="w-full p-2 border rounded" value="${user.username}" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2" for="password">Password</label>
                        <input type="password" name="password" id="password" class="w-full p-2 border rounded" placeholder="Leave blank to keep current">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2" for="role">Role</label>
                        <select name="role" id="role" class="w-full p-2 border rounded" required>
                            <option value="">Select Role</option>
                            <option value="Admin" ${user.role === 'Admin' ? 'selected' : ''}>Admin</option>
                            <option value="Pharmacist" ${user.role === 'Pharmacist' ? 'selected' : ''}>Pharmacist</option>
                            <option value="Cashier" ${user.role === 'Cashier' ? 'selected' : ''}>Cashier</option>
                            <option value="Inventory" ${user.role === 'Inventory' ? 'selected' : ''}>Inventory</option>
                            <option value="Staff" ${user.role === 'Staff' ? 'selected' : ''}>Staff</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-4">
                    <button type="button" class="btn-secondary" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        Save Changes
                    </button>
                </div>
            </form>
        `;
        
        openModal('Edit User', modalContent);
    }

    // Confirm Delete User
    function confirmDeleteUser(userId, username) {
        if (confirm(`Are you sure you want to delete user "${username}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_user';

            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userId;

            form.appendChild(actionInput);
            form.appendChild(userIdInput);
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
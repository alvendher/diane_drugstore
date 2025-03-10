<?php
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    // Redirect to home page or show access denied
    header('Location: home.php');
    exit;
}

// Page specific variables
$pageTitle = "Audit Log";
$pageIcon = "fas fa-clipboard-list";

// Get audit log data
try {
    $auditLogs = $pdo->query("
        SELECT 
            al.log_id,
            u.username,
            al.action,
            al.timestamp,
            al.details
        FROM audit_log al
        JOIN user u ON al.user_id = u.user_id
        ORDER BY al.timestamp DESC
        LIMIT 100
    ")->fetchAll();
    
} catch(PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
    $auditLogs = [];
}

// Log the page access
logAction(getCurrentUserId(), 'Viewed Audit Log');

// Start output buffering
ob_start();
?>

<!-- Success/Error Messages -->
<?php if (isset($errorMessage)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
        <?php echo $errorMessage; ?>
    </div>
<?php endif; ?>

<!-- Audit Log Table -->
<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">System Audit Log</h2>
        <div class="flex">
            <input type="text" id="searchInput" placeholder="Search..." class="p-2 border rounded mr-2">
            <button onclick="exportToCSV()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                <i class="fas fa-file-export mr-1"></i> Export
            </button>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">ID</th>
                    <th class="py-3 px-6 text-left">User</th>
                    <th class="py-3 px-6 text-left">Action</th>
                    <th class="py-3 px-6 text-left">Timestamp</th>
                    <th class="py-3 px-6 text-left">Details</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm">
                <?php foreach ($auditLogs as $log): ?>
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="py-3 px-6"><?php echo $log['log_id']; ?></td>
                        <td class="py-3 px-6"><?php echo $log['username']; ?></td>
                        <td class="py-3 px-6"><?php echo $log['action']; ?></td>
                        <td class="py-3 px-6"><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>
                        <td class="py-3 px-6"><?php echo $log['details']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Simple search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const table = document.querySelector('table');
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
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
        a.setAttribute('download', 'audit_log.csv');
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
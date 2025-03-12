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
$pageTitle = "Audit Log";
$pageIcon = "fas fa-clipboard-list";

// Initialize AuditLog with the existing PDO connection
$auditLog = new AuditLog($pdo);

// Get the current user's ID from session
$current_user_id = getCurrentUserId();

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
logAction($current_user_id, 'Viewed Audit Log');

// Start output buffering
ob_start();

// Example usage of audit log (move these to where the actual actions happen)
/*
// When user logs in
$auditLog->logLogin($current_user_id);

// When user logs out
$auditLog->logLogout($current_user_id);

// When adding a record
$auditLog->logDatabaseChange($current_user_id, 'INSERT', 'table_name', $record_id, 'Added new record');

// When updating a record
$auditLog->logDatabaseChange($current_user_id, 'UPDATE', 'table_name', $record_id, 'Updated record details');

// When deleting a record
$auditLog->logDatabaseChange($current_user_id, 'DELETE', 'table_name', $record_id, 'Deleted record');
*/

// Get all logs
$logs = $auditLog->getAuditLogs();

// Get filtered logs
$filters = [
    'user_id' => 1,
    'action' => 'UPDATE',
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31'
];
$filtered_logs = $auditLog->getAuditLogs($filters);
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
            <button onclick="exportToCSV()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-900">
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

<?php
class AuditLog {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    // Log user activities
    public function logActivity($user_id, $action, $table_name = null, $record_id = null, $details = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address, timestamp) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id, $action, $table_name, $record_id, $details, $ip_address, $timestamp]);
            return true;
        } catch (PDOException $e) {
            error_log("Audit Log Error: " . $e->getMessage());
            return false;
        }
    }

    // Specific method for logging login events
    public function logLogin($user_id) {
        return $this->logActivity($user_id, 'LOGIN', null, null, 'User logged in');
    }

    // Specific method for logging logout events
    public function logLogout($user_id) {
        return $this->logActivity($user_id, 'LOGOUT', null, null, 'User logged out');
    }

    // Log database modifications
    public function logDatabaseChange($user_id, $action, $table_name, $record_id, $details) {
        return $this->logActivity($user_id, $action, $table_name, $record_id, $details);
    }

    // Get audit logs with optional filtering
    public function getAuditLogs($filters = []) {
        $sql = "SELECT al.*, u.username 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $sql .= " AND al.action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND al.timestamp >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND al.timestamp <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY al.timestamp DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Audit Log Retrieval Error: " . $e->getMessage());
            return [];
        }
    }
}
?> 
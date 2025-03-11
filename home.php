<?php
require_once 'config.php';
function checkLogin() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: login.php');
        exit;
    }
}
checkLogin();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Page specific variables
$pageTitle = "Dashboard";
$pageIcon = "fas fa-home";

// Get data from database
try {
    // Get counts
    $inventoryCount = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    $productCount = $pdo->query("SELECT COUNT(*) FROM product")->fetchColumn();
    $customerCount = $pdo->query("SELECT COUNT(*) FROM customer")->fetchColumn();
    $salesCount = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
    $supplierCount = $pdo->query("SELECT COUNT(*) FROM supplier")->fetchColumn();
    $userCount = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
    
    // Get low stock items
    $lowStockItems = $pdo->query("
        SELECT p.product_name, i.stock_available, i.low_stock_threshold, p.unit_price 
        FROM inventory i
        JOIN product p ON i.product_id = p.product_id
        WHERE i.stock_available <= i.low_stock_threshold
        ORDER BY (i.low_stock_threshold - i.stock_available) DESC
        LIMIT 5
    ")->fetchAll();
    
    // Get expired products
    $expiredProducts = $pdo->query("
        SELECT p.product_name, i.expiry_date, i.stock_available, p.unit_price
        FROM inventory i
        JOIN product p ON i.product_id = p.product_id
        WHERE i.expiry_date <= CURDATE() AND i.stock_available > 0
        ORDER BY i.expiry_date ASC
        LIMIT 5
    ")->fetchAll();
    
    // Get today's sales
    $todaySales = $pdo->query("
        SELECT COUNT(*) as transaction_count, COALESCE(SUM(total_amount), 0) as total_amount
        FROM sales
        WHERE DATE(invoice_date) = CURDATE()
    ")->fetch();
    
    // Get top selling products
    $topSellingProducts = $pdo->query("
        SELECT p.product_name, SUM(sd.quantity) as total_quantity
        FROM sales_details sd
        JOIN product p ON sd.product_id = p.product_id
        JOIN sales s ON sd.invoice_id = s.invoice_id
        WHERE DATE(s.invoice_date) = CURDATE()
        GROUP BY sd.product_id
        ORDER BY total_quantity DESC
        LIMIT 1
    ")->fetch();
    
    // Get inventory value
    $inventoryValue = $pdo->query("
        SELECT SUM(p.unit_price * i.stock_available) as total_value
        FROM inventory i
        JOIN product p ON i.product_id = p.product_id
    ")->fetchColumn();
    
    // Get recent sales
    $recentSales = $pdo->query("
        SELECT s.invoice_id, CONCAT(c.first_name, ' ', c.last_name) as customer_name, 
               s.total_amount, s.invoice_date
        FROM sales s
        JOIN customer c ON s.customer_id = c.customer_id
        ORDER BY s.invoice_date DESC
        LIMIT 5
    ")->fetchAll();
    
} catch(PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Log the page access
logAction(getCurrentUserId(), 'Viewed Dashboard');

// Start output buffering
ob_start();
?>

<style>
    .card-gradient {
        background: linear-gradient(135deg, #186AB1 0%, #5B287B 100%);
    }
    .stat-card {
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .alert-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background-color: #ef4444;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
    }
    .quick-action {
        transition: all 0.3s ease;
    }
    .quick-action:hover {
        transform: translateY(-3px);
    }
</style>

<!-- Success/Error Messages -->
<?php if (isset($errorMessage)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
        <?php echo $errorMessage; ?>
    </div>
<?php endif; ?>

<!-- Welcome Banner -->
<div class="bg-white p-6 rounded-lg shadow-md mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Welcome, <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin'; ?>!</h1>
            <p class="text-gray-600">Today is <?php echo date('l, F j, Y'); ?> | <span id="currentTime"><?php echo date('h:i:s A'); ?></span></p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">Last login: <?php echo date('m/d/Y h:i A'); ?></p>
            <p class="text-sm text-gray-500">Role: <?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'Administrator'; ?></p>
        </div>
    </div>
</div>

<!-- Stats Overview -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <div class="stat-card bg-white p-4 rounded-lg shadow-md flex flex-col">
        <div class="text-purple-600 mb-2">
            <i class="fas fa-boxes text-2xl"></i>
        </div>
        <div class="text-gray-500 text-sm">Inventory</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?php echo $inventoryCount; ?></div>
        <div class="text-xs text-gray-500 mt-2">Items in stock</div>
    </div>
    
    <div class="stat-card bg-white p-4 rounded-lg shadow-md flex flex-col">
        <div class="text-purple-600 mb-2">
            <i class="fas fa-capsules text-2xl"></i>
        </div>
        <div class="text-gray-500 text-sm">Products</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?php echo $productCount; ?></div>
        <div class="text-xs text-gray-500 mt-2">Total products</div>
    </div>
    
    <div class="stat-card bg-white p-4 rounded-lg shadow-md flex flex-col">
        <div class="text-purple-600 mb-2">
            <i class="fas fa-users text-2xl"></i>
        </div>
        <div class="text-gray-500 text-sm">Customers</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?php echo $customerCount; ?></div>
        <div class="text-xs text-gray-500 mt-2">Registered customers</div>
    </div>
    
    <div class="stat-card bg-white p-4 rounded-lg shadow-md flex flex-col">
        <div class="text-purple-600 mb-2">
            <i class="fas fa-chart-line text-2xl"></i>
        </div>
        <div class="text-gray-500 text-sm">Sales</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?php echo $salesCount; ?></div>
        <div class="text-xs text-gray-500 mt-2">Total transactions</div>
    </div>
    
    <div class="stat-card bg-white p-4 rounded-lg shadow-md flex flex-col">
        <div class="text-purple-600 mb-2">
            <i class="fas fa-truck text-2xl"></i>
        </div>
        <div class="text-gray-500 text-sm">Suppliers</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?php echo $supplierCount; ?></div>
        <div class="text-xs text-gray-500 mt-2">Active suppliers</div>
    </div>
    
    <div class="stat-card bg-white p-4 rounded-lg shadow-md flex flex-col">
        <div class="text-purple-600 mb-2">
            <i class="fas fa-user text-2xl"></i>
        </div>
        <div class="text-gray-500 text-sm">Users</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?php echo $userCount; ?></div>
        <div class="text-xs text-gray-500 mt-2">System users</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white p-6 rounded-lg shadow-md mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="inventory.php" class="quick-action bg-purple-50 hover:bg-purple-100 p-4 rounded-lg flex flex-col items-center text-center">
            <div class="bg-purple-100 p-3 rounded-full mb-2">
                <i class="fas fa-plus text-purple-600"></i>
            </div>
            <span class="text-sm font-medium text-gray-700">Add Inventory</span>
        </a>
        
        <a href="sales.php" class="quick-action bg-purple-50 hover:bg-purple-100 p-4 rounded-lg flex flex-col items-center text-center">
            <div class="bg-purple-100 p-3 rounded-full mb-2">
                <i class="fas fa-shopping-cart text-purple-600"></i>
            </div>
            <span class="text-sm font-medium text-gray-700">New Sale</span>
        </a>
        
        <a href="customer.php" class="quick-action bg-purple-50 hover:bg-purple-100 p-4 rounded-lg flex flex-col items-center text-center">
            <div class="bg-purple-100 p-3 rounded-full mb-2">
                <i class="fas fa-user-plus text-purple-600"></i>
            </div>
            <span class="text-sm font-medium text-gray-700">Add Customer</span>
        </a>
        
        <a href="report.php" class="quick-action bg-purple-50 hover:bg-purple-100 p-4 rounded-lg flex flex-col items-center text-center">
            <div class="bg-purple-100 p-3 rounded-full mb-2">
                <i class="fas fa-file-alt text-purple-600"></i>
            </div>
            <span class="text-sm font-medium text-gray-700">Generate Report</span>
        </a>
    </div>
</div>

<!-- Recent Sales -->
<div class="bg-white p-6 rounded-lg shadow-md mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Recent Sales</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Invoice ID</th>
                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Customer</th>
                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentSales) > 0): ?>
                    <?php foreach ($recentSales as $sale): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 border-b border-gray-200">#<?php echo $sale['invoice_id']; ?></td>
                            <td class="py-2 px-4 border-b border-gray-200"><?php echo $sale['customer_name']; ?></td>
                            <td class="py-2 px-4 border-b border-gray-200">₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                            <td class="py-2 px-4 border-b border-gray-200"><?php echo date('m/d/Y', strtotime($sale['invoice_date'])); ?></td>
                            <td class="py-2 px-4 border-b border-gray-200">
                                <a href="view_sale.php?id=<?php echo $sale['invoice_id']; ?>" class="text-blue-500 hover:text-blue-700">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="py-4 px-4 border-b border-gray-200 text-center text-gray-500">No recent sales found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-4 text-right">
        <a href="sales.php" class="text-sm text-blue-600 hover:text-blue-800">View all sales →</a>
    </div>
</div>

<!-- Alerts and Reports -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Low Stock Alert -->
    <div class="card-gradient text-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Low Stock Alert</h2>
            <?php if (count($lowStockItems) > 0): ?>
                <div class="relative">
                    <span class="alert-badge"><?php echo count($lowStockItems); ?></span>
                    <i class="fas fa-exclamation-triangle text-yellow-300 text-xl"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($lowStockItems) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($lowStockItems as $item): ?>
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <div class="flex justify-between">
                            <div class="font-semibold"><?php echo $item['product_name']; ?></div>
                            <div class="text-yellow-300">
                                <?php echo $item['stock_available']; ?>/<?php echo $item['low_stock_threshold']; ?>
                            </div>
                        </div>
                        <div class="text-sm mt-1">Only <?php echo $item['stock_available']; ?> units remaining</div>
                        <div class="mt-2 flex justify-between items-center">
                            <div class="text-xs">Value: ₱<?php echo number_format($item['unit_price'] * $item['stock_available'], 2); ?></div>
                            <a href="inventory.php" class="text-xs bg-white bg-opacity-20 hover:bg-opacity-30 py-1 px-2 rounded">
                                Restock
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                <div class="text-center py-4">
                    <i class="fas fa-check-circle text-3xl mb-2"></i>
                    <p>All products are well stocked</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="mt-4 text-right">
            <a href="inventory.php" class="text-sm text-white hover:underline">Manage inventory →</a>
        </div>
    </div>
    
    <!-- Expired Products -->
    <div class="card-gradient text-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Expired Products</h2>
            <?php if (count($expiredProducts) > 0): ?>
                <div class="relative">
                    <span class="alert-badge"><?php echo count($expiredProducts); ?></span>
                    <i class="fas fa-calendar-times text-red-300 text-xl"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($expiredProducts) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($expiredProducts as $product): ?>
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <div class="flex justify-between">
                            <div class="font-semibold"><?php echo $product['product_name']; ?></div>
                            <div class="text-red-300">
                                Expired: <?php echo date('m/d/Y', strtotime($product['expiry_date'])); ?>
                            </div>
                        </div>
                        <div class="text-sm mt-1">Quantity: <?php echo $product['stock_available']; ?> units</div>
                        <div class="mt-2 flex justify-between items-center">
                            <div class="text-xs">Value: ₱<?php echo number_format($product['unit_price'] * $product['stock_available'], 2); ?></div>
                            <a href="inventory.php" class="text-xs bg-white bg-opacity-20 hover:bg-opacity-30 py-1 px-2 rounded">
                                Remove
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                <div class="text-center py-4">
                    <i class="fas fa-check-circle text-3xl mb-2"></i>
                    <p>No expired products found</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="mt-4 text-right">
            <a href="inventory.php" class="text-sm text-white hover:underline">Manage inventory →</a>
        </div>
    </div>
</div>

<!-- Today's Summary -->
<div class="mt-6">
    <div class="card-gradient text-white p-6 rounded-lg shadow-md">
        <h2 class="text-lg font-bold mb-4">Today's Summary</h2>
        <div class="bg-white bg-opacity-10 p-4 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h3 class="font-semibold mb-2">Today's Sales</h3>
                    <p class="text-2xl font-bold">₱<?php echo number_format($todaySales['total_amount'] ?? 0, 2); ?></p>
                    <p class="text-sm"><?php echo $todaySales['transaction_count'] ?? 0; ?> transactions</p>
                </div>
                <div>
                    <h3 class="font-semibold mb-2">Top Selling Product</h3>
                    <p class="text-xl font-bold"><?php echo $topSellingProducts['product_name'] ?? 'No sales today'; ?></p>
                    <?php if (isset($topSellingProducts['total_quantity'])): ?>
                        <p class="text-sm"><?php echo $topSellingProducts['total_quantity']; ?> units sold today</p>
                    <?php else: ?>
                        <p class="text-sm">No units sold today</p>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="font-semibold mb-2">Inventory Value</h3>
                    <p class="text-2xl font-bold">₱<?php echo number_format($inventoryValue ?? 0, 2); ?></p>
                    <p class="text-sm"><?php echo $inventoryCount; ?> total items</p>
                </div>
            </div>
        </div>
        <div class="mt-4 text-right">
            <a href="report.php" class="text-sm text-white hover:underline">View detailed reports →</a>
        </div>
    </div>
</div>

<script>
    // Update current time in Philippine format
    function updateTime() {
        const now = new Date();
        const timeElement = document.getElementById('currentTime');
        
        // Format time as hh:mm:ss AM/PM
        let hours = now.getHours();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        
        timeElement.textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
    }
    
    // Update time every second
    setInterval(updateTime, 1000);
    updateTime(); // Initial call
</script>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout
include 'layout.php';
?> 
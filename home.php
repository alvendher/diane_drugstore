<?php
require_once 'config.php';
require_once 'classes/InventoryManager.php';
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
    
    // Get low stock items - enhanced query with percentage thresholds
    $lowStockItems = $pdo->query("
        SELECT 
            p.product_name, 
            SUM(i.stock_available) as stock_available, 
            i.low_stock_threshold, 
            p.unit_price,
            p.product_id,
            CASE 
                WHEN SUM(i.stock_available) <= 0 THEN 'Out of Stock'
                WHEN (SUM(i.stock_available) / i.low_stock_threshold) * 100 <= 10 THEN 'Critical Stock'
                WHEN (SUM(i.stock_available) / i.low_stock_threshold) * 100 <= 25 THEN 'Very Low Stock'
                WHEN (SUM(i.stock_available) / i.low_stock_threshold) * 100 <= 50 THEN 'Low Stock'
                ELSE 'Good'
            END as stock_status,
            (SUM(i.stock_available) / i.low_stock_threshold) * 100 as stock_percentage
        FROM inventory i
        JOIN product p ON i.product_id = p.product_id
        WHERE i.status = 'active'
        GROUP BY 
            p.product_id, 
            p.product_name, 
            i.low_stock_threshold, 
            p.unit_price
        HAVING 
            SUM(i.stock_available) <= 0 OR (SUM(i.stock_available) / i.low_stock_threshold) * 100 <= 50
        ORDER BY 
            CASE 
                WHEN SUM(i.stock_available) <= 0 THEN 1  -- Out of Stock first
                WHEN (SUM(i.stock_available) / i.low_stock_threshold) * 100 <= 10 THEN 2  -- Critical Stock next
                WHEN (SUM(i.stock_available) / i.low_stock_threshold) * 100 <= 25 THEN 3  -- Very Low Stock next
                WHEN (SUM(i.stock_available) / i.low_stock_threshold) * 100 <= 50 THEN 4  -- Low Stock next
                ELSE 5
            END,
            stock_percentage ASC
        LIMIT 10
    ")->fetchAll();
    
    // Get expired and soon-to-expire products - updated query
    $expiredProducts = $pdo->query("
        SELECT 
            p.product_name, 
            i.expiry_date, 
            SUM(i.stock_available) as stock_available, 
            p.unit_price,
            p.product_id,
            i.status as inventory_status,
            CASE 
                WHEN i.expiry_date < CURDATE() THEN 'Expired'
                WHEN DATEDIFF(i.expiry_date, CURDATE()) <= 7 THEN 'Critical'
                WHEN DATEDIFF(i.expiry_date, CURDATE()) <= 30 THEN 'Warning'
                ELSE 'Good'
            END as status_level,
            CASE 
                WHEN i.expiry_date < CURDATE() THEN 'Expired'
                ELSE CONCAT(DATEDIFF(i.expiry_date, CURDATE()), ' days left')
            END as status,
            DATEDIFF(i.expiry_date, CURDATE()) as days_remaining
        FROM inventory i
        JOIN product p ON i.product_id = p.product_id
        WHERE i.stock_available > 0
            AND (
                i.expiry_date < CURDATE()
                OR i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            )
        GROUP BY 
            p.product_id, 
            p.product_name, 
            i.expiry_date,
            p.unit_price,
            i.status
        ORDER BY 
            i.expiry_date < CURDATE() DESC,
            i.expiry_date ASC
        LIMIT 10
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
    
    // Get critical inventory items with proper GROUP BY clause
    $criticalInventory = $pdo->query("
        SELECT 
            p.product_name,
            p.product_id,
            SUM(i.stock_available) as total_stock,
            MAX(i.low_stock_threshold) as low_stock_threshold,
            MIN(i.expiry_date) as nearest_expiry,
            DATEDIFF(MIN(i.expiry_date), CURDATE()) as days_to_expiry
        FROM inventory i
        JOIN product p ON i.product_id = p.product_id
        WHERE i.status = 'active'
        GROUP BY p.product_id, p.product_name
        HAVING 
            SUM(i.stock_available) < MAX(i.low_stock_threshold) OR
            DATEDIFF(MIN(i.expiry_date), CURDATE()) <= 30
        ORDER BY 
            CASE 
                WHEN SUM(i.stock_available) < MAX(i.low_stock_threshold) THEN 1
                WHEN DATEDIFF(MIN(i.expiry_date), CURDATE()) <= 30 THEN 2
                ELSE 3
            END,
            days_to_expiry ASC
        LIMIT 10
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
            <h1 class="text-2xl font-bold text-gray-800">Welcome, <?php echo $_SESSION['username'] ?? 'Admin'; ?>!</h1>
            <p class="text-gray-600">Today is <?php echo date('l, F j, Y'); ?> | <span id="currentTime"><?php echo date('h:i:s A'); ?></span></p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">Last login: <?php echo date('m/d/Y h:i A'); ?></p>
            <p class="text-sm text-gray-500">Role: <?php echo $_SESSION['role'] ?? 'Administrator'; ?></p>
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
                                <button onclick="viewSale(<?php echo $sale['invoice_id']; ?>)" 
                                        class="text-blue-500 hover:text-blue-700">
                                    <i class="fas fa-eye"></i> View
                                </button>
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
            <h2 class="text-lg font-bold">Stock Status Alert</h2>
            <?php if (count($lowStockItems) > 0): ?>
                <div class="relative">
                    <span class="alert-badge"><?php echo count($lowStockItems); ?></span>
                    <i class="fas fa-exclamation-triangle text-yellow-300 text-xl" title="Stock Alert"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($lowStockItems) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($lowStockItems as $item): ?>
                    <?php 
                    // Calculate percentage of threshold
                    $percentage = $item['low_stock_threshold'] > 0 ? 
                        ($item['stock_available'] / $item['low_stock_threshold']) * 100 : 0;
                    
                    $statusClass = match(true) {
                        $item['stock_available'] <= 0 => 'text-red-300 font-bold',
                        $percentage <= 10 => 'text-red-300',
                        $percentage <= 25 => 'text-orange-300',
                        $percentage <= 50 => 'text-yellow-300',
                        default => 'text-white'
                    };
                    
                    $bgClass = match(true) {
                        $item['stock_available'] <= 0 => 'bg-red-500',
                        $percentage <= 10 => 'bg-red-500',
                        $percentage <= 25 => 'bg-orange-500',
                        $percentage <= 50 => 'bg-yellow-500',
                        default => 'bg-green-500'
                    };
                    
                    $statusText = match(true) {
                        $item['stock_available'] <= 0 => 'Out of Stock',
                        $percentage <= 10 => 'Critical Stock (≤10%)',
                        $percentage <= 25 => 'Very Low Stock (≤25%)',
                        $percentage <= 50 => 'Low Stock (≤50%)',
                        default => 'Good'
                    };
                    ?>
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                    <span class="<?php echo $bgClass; ?> bg-opacity-20 text-xs px-2 py-1 rounded">
                                        <?php echo $statusText; ?>
                                    </span>
                                </div>
                                <div class="text-sm mt-2">
                                    <div class="flex justify-between items-center mb-1">
                                        <span>Stock Level:</span>
                                        <span class="<?php echo $statusClass; ?>">
                                            <?php echo (int)$item['stock_available']; ?>/<?php echo (int)$item['low_stock_threshold']; ?>
                                            (<?php echo number_format($percentage, 1); ?>%)
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 bg-opacity-20 rounded-full h-2">
                                        <div class="<?php echo $bgClass; ?> rounded-full h-2" 
                                             style="width: <?php echo min(100, $percentage); ?>%">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-between items-center">
                            <div class="text-xs">
                                <?php if ($item['stock_status'] !== 'Out of Stock'): ?>
                                    Stock at <?php echo number_format($percentage, 1); ?>% of threshold
                                <?php else: ?>
                                    Immediate restock required
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <a href="inventory.php?action=restock&id=<?php echo $item['product_id']; ?>" 
                                   class="text-xs <?php echo $bgClass; ?> bg-opacity-20 hover:bg-opacity-30 py-1 px-2 rounded">
                                    <?php if ($item['stock_status'] === 'Out of Stock'): ?>
                                        <i class="fas fa-plus-circle"></i> Stock Now
                                    <?php else: ?>
                                        <i class="fas fa-sync-alt"></i> Restock
                                    <?php endif; ?>
                                </a>
                                <button onclick="dismissStockAlert(<?php echo $item['product_id']; ?>)" 
                                        class="text-xs bg-gray-500 bg-opacity-20 hover:bg-opacity-30 py-1 px-2 rounded">
                                    <i class="fas fa-times"></i> Dismiss
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 text-sm text-white opacity-75">
                * Products are marked as "Out of Stock" when stock is 0, and "Low Stock" when below threshold
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
            <a href="inventory.php?view=low_stock" class="text-sm text-white hover:underline">Manage inventory →</a>
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
            <div class="space-y-3" id="expired-products-container">
                <?php foreach ($expiredProducts as $product): ?>
                    <?php 
                    $statusClass = match($product['status_level']) {
                        'Expired' => 'text-red-300',
                        'Critical' => 'text-orange-300',
                        'Warning' => 'text-yellow-300',
                        default => 'text-green-300'
                    };
                    $bgClass = match($product['status_level']) {
                        'Expired' => 'bg-red-500',
                        'Critical' => 'bg-orange-500',
                        'Warning' => 'bg-yellow-500',
                        default => 'bg-green-500'
                    };
                    ?>
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg" id="expired-product-<?php echo $product['product_id']; ?>" data-status="<?php echo $product['status_level']; ?>">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-semibold"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                <div class="text-sm mt-1 <?php echo $statusClass; ?>">
                                    <?php if ($product['status_level'] === 'Expired'): ?>
                                        <span class="text-red-300">
                                            <i class="fas fa-exclamation-circle"></i> 
                                            Expired on <?php echo date('m/d/Y', strtotime($product['expiry_date'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <i class="fas fa-clock"></i> 
                                        Expires: <?php echo date('m/d/Y', strtotime($product['expiry_date'])); ?>
                                        (<?php echo $product['status']; ?>)
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="<?php echo $statusClass; ?> text-sm font-semibold">
                                <span class="<?php echo $bgClass; ?> bg-opacity-20 px-2 py-1 rounded">
                                    <?php echo $product['status_level']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-between items-center">
                            <div>
                                <div class="text-sm">Quantity: <?php echo (int)$product['stock_available']; ?> units</div>
                                <div class="text-xs mt-1">Value: ₱<?php echo number_format($product['unit_price'] * $product['stock_available'], 2); ?></div>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($product['status_level'] === 'Expired'): ?>
                                    <a href="inventory.php?action=quarantine&id=<?php echo $product['product_id']; ?>" 
                                       class="text-xs bg-red-500 bg-opacity-20 hover:bg-opacity-30 py-1 px-2 rounded">
                                        <i class="fas fa-exclamation-triangle"></i> Quarantine
                                    </a>
                                    <button onclick="confirmDismissExpiredProduct(<?php echo $product['product_id']; ?>, '<?php echo $product['status_level']; ?>')" 
                                            class="text-xs bg-gray-500 bg-opacity-20 hover:bg-opacity-30 py-1 px-2 rounded">
                                        <i class="fas fa-times"></i> Dismiss
                                    </button>
                                <?php else: ?>
                                    <a href="inventory.php?action=manage&id=<?php echo $product['product_id']; ?>" 
                                       class="text-xs bg-white bg-opacity-20 hover:bg-opacity-30 py-1 px-2 rounded">
                                        <i class="fas fa-cog"></i> Manage
                                    </a>
                                    <button onclick="confirmDismissExpiredProduct(<?php echo $product['product_id']; ?>, '<?php echo $product['status_level']; ?>')" 
                                            class="text-xs bg-gray-500 bg-opacity-20 hover:bg-opacity-30 py-1 px-2 rounded">
                                        <i class="fas fa-times"></i> Dismiss
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 text-sm text-white opacity-75">
                * Products are categorized as: Expired (past expiry date), Critical (≤7 days), Warning (≤30 days)
            </div>
        <?php else: ?>
            <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                <div class="text-center py-4">
                    <i class="fas fa-check-circle text-3xl mb-2"></i>
                    <p>No expired or expiring products</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="mt-4 text-right">
            <a href="inventory.php?view=expiry" class="text-sm text-white hover:underline">View all expiring products →</a>
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
    
    // Close sale details modal
    function closeSaleModal() {
        const modal = document.getElementById('saleModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    async function viewSale(invoiceId) {
        const modal = document.getElementById('saleModal');
        const detailsContainer = document.getElementById('saleDetails');
        
        if (!modal || !detailsContainer) return;
        
        // Show modal with loading state
        modal.classList.remove('hidden');
        
        detailsContainer.innerHTML = `
            <div class="animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                <div class="h-4 bg-gray-200 rounded w-5/6 mb-4"></div>
            </div>
        `;
    
        try {
            const response = await fetch(`get_sale_details.php?invoice_id=${invoiceId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const data = await response.json();
            
            if (data.error) {
                detailsContainer.innerHTML = `
                    <div class="text-red-500 p-4 text-center">
                        ${data.error}
                    </div>`;
                return;
            }
    
            // Format and display sale details
            detailsContainer.innerHTML = `
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm text-gray-600">Invoice Number</p>
                        <p class="font-semibold">#${data.invoice_id}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Date</p>
                        <p class="font-semibold">${data.invoice_date}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Customer</p>
                        <p class="font-semibold">${data.customer_name}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Status</p>
                        <p class="font-semibold text-green-600">Completed</p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-semibold mb-3">Items</h4>
                    <div class="space-y-2">
                        ${data.items.map(item => `
                            <div class="flex justify-between items-center py-2 border-b">
                                <div>
                                    <p class="font-medium">${item.product_name}</p>
                                    <p class="text-sm text-gray-600">
                                        ${item.quantity} × ₱${Number(item.unit_price).toFixed(2)}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold">₱${Number(item.subtotal).toFixed(2)}</p>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-semibold">₱${Number(data.total_amount).toFixed(2)}</span>
                    </div>
                    ${data.discount_amount ? `
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-600">Discount</span>
                            <span class="font-semibold text-green-600">-₱${Number(data.discount_amount).toFixed(2)}</span>
                        </div>
                    ` : ''}
                    <div class="flex justify-between items-center text-lg font-bold mt-2 pt-2 border-t">
                        <span>Total</span>
                        <span>₱${Number(data.net_total).toFixed(2)}</span>
                    </div>
                </div>
            `;
        } catch (error) {
            detailsContainer.innerHTML = `
                <div class="text-red-500 p-4 text-center">
                    Error loading sale details. Please try again.
                </div>
            `;
        }
    }
    
    // Confirm before dismissing expired product notification
    function confirmDismissExpiredProduct(productId, status) {
        // For expired or critical products, require additional confirmation
        if (status === 'Expired' || status === 'Critical') {
            const action = status === 'Expired' ? 'quarantine' : 'manage';
            const confirmMessage = status === 'Expired' 
                ? 'This product is EXPIRED. It is recommended to quarantine it instead of dismissing the notification.'
                : 'This product is in CRITICAL status (expiring within 7 days). It is recommended to manage it instead of dismissing the notification.';
                
            if (!confirm(confirmMessage + '\n\nAre you sure you want to dismiss this notification?')) {
                return;
            }
            
            const reason = prompt(`Please provide a reason for dismissing this ${status} product notification:`, '');
            
            if (!reason) {
                return; // User cancelled
            }
            
            if (reason.trim().length < 10) {
                alert('Please provide a more detailed reason (at least 10 characters)');
                return;
            }
            
            // Log the dismissal with reason
            logDismissal(productId, status, reason);
        } else if (!confirm('Are you sure you want to dismiss this notification?')) {
            return;
        }
        
        dismissExpiredProduct(productId);
    }
    
    // Log dismissal to server
    function logDismissal(productId, status, reason) {
        fetch('log_dismissal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                status: status,
                reason: reason,
                action: 'dismiss'
            })
        }).catch(error => {
            console.error('Error logging dismissal:', error);
        });
    }
    
    // Dismiss expired product notification
    function dismissExpiredProduct(productId) {
        const productElement = document.getElementById(`expired-product-${productId}`);
        if (productElement) {
            productElement.remove();
            
            // Check if there are any expired products left
            const container = document.getElementById('expired-products-container');
            if (container && container.children.length === 0) {
                container.innerHTML = `
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-3xl mb-2"></i>
                            <p>No expired or expiring products</p>
                        </div>
                    </div>
                `;
            }
            
            // Update the badge count
            const badge = document.querySelector('.card-gradient:nth-child(2) .alert-badge');
            if (badge) {
                const count = parseInt(badge.textContent) - 1;
                badge.textContent = count;
                
                // Hide badge if count is 0
                if (count === 0) {
                    badge.parentElement.classList.add('hidden');
                }
            }
            
            // Save dismissed state in localStorage with timestamp
            const dismissed = JSON.parse(localStorage.getItem('dismissedExpiredProducts') || '[]');
            dismissed.push({
                id: productId,
                timestamp: new Date().getTime(),
                expiresAt: new Date().getTime() + (24 * 60 * 60 * 1000) // Dismissal expires after 24 hours
            });
            localStorage.setItem('dismissedExpiredProducts', JSON.stringify(dismissed));
        }
    }
    
    // Check for previously dismissed alerts on page load and clean expired dismissals
    document.addEventListener('DOMContentLoaded', function() {
        // Load dismissed items from localStorage
        let dismissedExpired = JSON.parse(localStorage.getItem('dismissedExpiredProducts') || '[]');
        const dismissedStock = JSON.parse(localStorage.getItem('dismissedStockAlerts') || '[]');
        
        // Clean up expired dismissals (older than 24 hours)
        const now = new Date().getTime();
        dismissedExpired = dismissedExpired.filter(item => {
            return item.expiresAt > now;
        });
        localStorage.setItem('dismissedExpiredProducts', JSON.stringify(dismissedExpired));
        
        // Remove dismissed expired products that haven't expired yet
        dismissedExpired.forEach(item => {
            const element = document.getElementById(`expired-product-${item.id}`);
            if (element) {
                // Only dismiss if it's not an Expired or Critical status, or if it was recently dismissed
                const status = element.getAttribute('data-status');
                if (status !== 'Expired' && status !== 'Critical') {
                    element.remove();
                } else {
                    // For Expired/Critical, only honor dismissals from the last 4 hours
                    const fourHoursAgo = now - (4 * 60 * 60 * 1000);
                    if (item.timestamp > fourHoursAgo) {
                        element.remove();
                    }
                }
            }
        });
        
        // Remove dismissed stock alerts
        dismissedStock.forEach(productId => {
            const stockAlerts = document.querySelectorAll('.card-gradient:first-child .bg-white.bg-opacity-10.p-4.rounded-lg');
            stockAlerts.forEach(alert => {
                if (alert.innerHTML.includes(`id=${productId}`)) {
                    alert.remove();
                }
            });
        });
        
        // Update badge counts and empty states
        updateAlertContainers();
    });
    
    // Function to check expiry date status
    function checkExpiryDate(dateStr) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const expiryDate = new Date(dateStr);
        expiryDate.setHours(0, 0, 0, 0);
        
        const diffTime = expiryDate - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 0) return 'Expired';
        if (diffDays <= 7) return 'Critical';
        if (diffDays <= 30) return 'Warning';
        return 'Good';
    }
    
    // Function to format expiry date message
    function formatExpiryMessage(dateStr, status) {
        const expiryDate = new Date(dateStr);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const diffTime = expiryDate - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        const formattedDate = expiryDate.toLocaleDateString('en-US', {
            month: '2-digit',
            day: '2-digit',
            year: 'numeric'
        });
        
        if (status === 'Expired') {
            return `Expired on ${formattedDate} (${Math.abs(diffDays)} days ago)`;
        } else {
            return `Expires on ${formattedDate} (${diffDays} days left)`;
        }
    }
</script>

<!-- Update the Modal HTML -->
<div id="saleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <!-- Modal Header -->
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <h3 class="text-lg font-bold text-gray-900">Sale Details</h3>
            <button onclick="closeSaleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div id="saleDetails" class="mt-2 max-h-[60vh] overflow-y-auto">
            <!-- Sale details will be loaded here -->
        </div>
        
        <!-- Modal Footer -->
        <div class="mt-4 pt-3 border-t flex justify-end">
            <button onclick="closeSaleModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 focus:outline-none">
                Close
            </button>
        </div>
    </div>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Automatically quarantine expired products
try {
    $inventoryManager = new InventoryManager($pdo);
    $inventoryManager->quarantineExpiredProducts();
} catch (Exception $e) {
    // Log the error but don't stop page execution
    error_log("Error quarantining expired products: " . $e->getMessage());
}

// Include the layout
include 'layout.php';
?>
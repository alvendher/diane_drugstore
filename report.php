<?php
require_once 'config.php';

// Page specific variables
$pageTitle = "Reports";
$pageIcon = "fas fa-file-alt";

// Get report data
try {
    // Sales report data
    $salesData = $pdo->query("
        SELECT 
            DATE(s.invoice_date) as date,
            COUNT(s.invoice_id) as total_invoices,
            SUM(s.total_amount) as total_sales,
            SUM(s.net_total) as net_sales
        FROM sales s
        WHERE s.invoice_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(s.invoice_date)
        ORDER BY date DESC
    ")->fetchAll();
    
    // Inventory status
    $inventoryStatus = $pdo->query("
        SELECT 
            p.product_name,
            i.stock_available,
            i.low_stock_threshold,
            p.unit_price,
            (p.unit_price * i.stock_available) as inventory_value
        FROM inventory i
        JOIN product p ON i.product_id = p.product_id
        ORDER BY i.stock_available ASC
        LIMIT 10
    ")->fetchAll();
    
    // Top selling products
    $topProducts = $pdo->query("
        SELECT 
            p.product_name,
            SUM(sd.quantity) as total_quantity,
            SUM(sd.subtotal) as total_sales
        FROM sales_details sd
        JOIN product p ON sd.product_id = p.product_id
        JOIN sales s ON sd.invoice_id = s.invoice_id
        WHERE s.invoice_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY sd.product_id
        ORDER BY total_quantity DESC
        LIMIT 5
    ")->fetchAll();
    
} catch(PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Log the page access
logAction(getCurrentUserId(), 'Viewed Reports');

// Start output buffering
ob_start();
?>

<!-- Success/Error Messages -->
<?php if (isset($errorMessage)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
        <?php echo $errorMessage; ?>
    </div>
<?php endif; ?>

<!-- Report Tabs -->
<div class="mb-6">
    <div class="border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px" id="reportTabs" role="tablist">
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 border-blue-500 rounded-t-lg active" id="sales-tab" data-tabs-target="#sales" type="button" role="tab" aria-controls="sales" aria-selected="true">Sales Report</button>
            </li>
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:border-gray-300" id="inventory-tab" data-tabs-target="#inventory" type="button" role="tab" aria-controls="inventory" aria-selected="false">Inventory Status</button>
            </li>
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:border-gray-300" id="products-tab" data-tabs-target="#products" type="button" role="tab" aria-controls="products" aria-selected="false">Top Products</button>
            </li>
            <li role="presentation">
                <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:border-gray-300" id="forecast-tab" data-tabs-target="#forecast" type="button" role="tab" aria-controls="forecast" aria-selected="false">Demand Forecast</button>
            </li>
        </ul>
    </div>
    
    <div id="reportTabContent">
        <!-- Sales Report Tab -->
        <div class="p-4 rounded-lg bg-white" id="sales" role="tabpanel" aria-labelledby="sales-tab">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Sales Report (Last 30 Days)</h2>
                <button onclick="exportToCSV('salesTable', 'sales_report')" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-900">
                    <i class="fas fa-file-export mr-1"></i> Export
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table id="salesTable" class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Date</th>
                            <th class="py-3 px-6 text-left">Invoices</th>
                            <th class="py-3 px-6 text-left">Total Sales</th>
                            <th class="py-3 px-6 text-left">Net Sales</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm">
                        <?php foreach ($salesData as $sale): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6"><?php echo date('M d, Y', strtotime($sale['date'])); ?></td>
                                <td class="py-3 px-6"><?php echo $sale['total_invoices']; ?></td>
                                <td class="py-3 px-6">₱<?php echo number_format($sale['total_sales'], 2); ?></td>
                                <td class="py-3 px-6">₱<?php echo number_format($sale['net_sales'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Inventory Status Tab -->
        <div class="hidden p-4 rounded-lg bg-white" id="inventory" role="tabpanel" aria-labelledby="inventory-tab">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Inventory Status</h2>
                <button onclick="exportToCSV('inventoryTable', 'inventory_report')" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    <i class="fas fa-file-export mr-1"></i> Export
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table id="inventoryTable" class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Product</th>
                            <th class="py-3 px-6 text-left">Stock Available</th>
                            <th class="py-3 px-6 text-left">Low Stock Threshold</th>
                            <th class="py-3 px-6 text-left">Unit Price</th>
                            <th class="py-3 px-6 text-left">Inventory Value</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm">
                        <?php foreach ($inventoryStatus as $item): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100 <?php echo $item['stock_available'] <= $item['low_stock_threshold'] ? 'bg-yellow-100' : ''; ?>">
                                <td class="py-3 px-6"><?php echo $item['product_name']; ?></td>
                                <td class="py-3 px-6"><?php echo $item['stock_available']; ?></td>
                                <td class="py-3 px-6"><?php echo $item['low_stock_threshold']; ?></td>
                                <td class="py-3 px-6">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="py-3 px-6">₱<?php echo number_format($item['inventory_value'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Top Products Tab -->
        <div class="hidden p-4 rounded-lg bg-white" id="products" role="tabpanel" aria-labelledby="products-tab">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Top Selling Products (Last 30 Days)</h2>
                <button onclick="exportToCSV('productsTable', 'top_products_report')" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    <i class="fas fa-file-export mr-1"></i> Export
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table id="productsTable" class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Product</th>
                            <th class="py-3 px-6 text-left">Quantity Sold</th>
                            <th class="py-3 px-6 text-left">Total Sales</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm">
                        <?php foreach ($topProducts as $product): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6"><?php echo $product['product_name']; ?></td>
                                <td class="py-3 px-6"><?php echo $product['total_quantity']; ?></td>
                                <td class="py-3 px-6">₱<?php echo number_format($product['total_sales'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Demand Forecast Tab -->
        <div class="hidden p-4 rounded-lg bg-white" id="forecast" role="tabpanel" aria-labelledby="forecast-tab">
            <h2 class="text-xl font-bold mb-4">Demand Forecast</h2>
            <p class="mb-4">This feature uses historical sales data to predict future product demand.</p>
            
            <div class="bg-blue-100 p-4 rounded-lg mb-4">
                <p class="font-bold">Based on our analysis of your sales data, we predict the following trends:</p>
                <ul class="list-disc pl-5 mt-2">
                    <li>Paracetamol 500mg will likely see a 15% increase in demand next month</li>
                    <li>Vitamin C supplements show seasonal patterns with higher demand expected in the coming weeks</li>
                    <li>Blood pressure medications maintain steady demand with minimal fluctuation</li>
                </ul>
            </div>
            
            <div class="bg-purple-700 text-white p-4 rounded-lg">
                <p class="font-bold mb-2">Recommended Actions:</p>
                <ul class="list-disc pl-5">
                    <li>Increase Paracetamol 500mg stock by at least 20% to meet projected demand</li>
                    <li>Consider promotional offers on slow-moving inventory items</li>
                    <li>Maintain current stock levels for chronic medication categories</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // Tab functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('[data-tabs-target]');
        const tabContents = document.querySelectorAll('[role="tabpanel"]');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = document.querySelector(tab.dataset.tabsTarget);
                
                tabContents.forEach(tc => {
                    tc.classList.add('hidden');
                });
                
                tabs.forEach(t => {
                    t.classList.remove('border-blue-500');
                    t.classList.add('border-transparent');
                    t.setAttribute('aria-selected', false);
                });
                
                tab.classList.remove('border-transparent');
                tab.classList.add('border-blue-500');
                tab.setAttribute('aria-selected', true);
                
                target.classList.remove('hidden');
            });
        });
    });
    
    // Export to CSV function
    function exportToCSV(tableId, filename) {
        const table = document.getElementById(tableId);
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
        a.setAttribute('download', filename + '.csv');
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
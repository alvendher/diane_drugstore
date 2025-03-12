<?php
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diane's Pharmacy - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .sidebar {
            background: linear-gradient(180deg, #186AB1 -100%, #3A1853 100%);
        }
        .nav-item {
            transition: all 0.3s ease;
        }
        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .active-nav-item {
            background-color: white;
            color: #5B287B;
            font-weight: bold;
        }
        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .logo-img {
            width: 100px;
            height: 100px;
            margin-bottom: 0.5rem;
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 8px;
        }
        .dropdown-content a {
            color: #5B287B;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: all 0.2s ease;
        }
        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        .show {
            display: block;
        }
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow: auto;
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            animation: modalFadeIn 0.3s;
        }
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .modal-close {
            cursor: pointer;
            font-size: 24px;
            color: #5B287B;
        }
        .modal-close:hover {
            color: #186AB1;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-input:focus {
            border-color: #5B287B;
            outline: none;
            box-shadow: 0 0 0 2px rgba(91, 40, 123, 0.2);
        }
        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
        }
        .btn-primary {
            background-color: #5B287B;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .btn-primary:hover {
            background-color: #3A1853;
        }
        .btn-secondary {
            background-color: #f3f4f6;
            color: #333;
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .btn-secondary:hover {
            background-color: #e5e7eb;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 sidebar text-white overflow-y-auto">
            <!-- Logo and Brand -->
            <div class="logo-container p-4">
                <img src="img/logo.png" alt="Diane's Pharmacy Logo" class="logo-img">
                <div class="text-center text-xl font-bold">Diane's Pharmacy</div>
                <div class="w-full h-0.5 bg-white mt-3 md:mt-4 lg:mt-5 hidden md:block"></div>
                <div class="w-1/2 h-0.5 bg-white mt-2 mx-auto md:hidden"></div>
            </div>
            
            <!-- Navigation -->
            <ul class="px-4">
                <li class="mb-1">
                    <a href="home.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-home mr-3 w-5 text-center"></i> Dashboard
                    </a>
                </li>
                <li class="mb-1">
                    <a href="inventory.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-box mr-3 w-5 text-center"></i> Inventory
                    </a>
                </li>
                <li class="mb-1">
                    <a href="product.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'product.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-pills mr-3 w-5 text-center"></i> Products
                    </a>
                </li>
                <li class="mb-1">
                    <a href="expired_product.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'expired_product.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-prescription-bottle-alt mr-3 w-5 text-center"></i> Expired Products
                    </a>
                </li>
                <li class="mb-1">
                    <a href="sales.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-cash-register mr-3 w-5 text-center"></i> Sales
                    </a>
                </li>
                <li class="mb-1">
                    <a href="customer.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'customer.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-users mr-3 w-5 text-center"></i> Customers
                    </a>
                </li>
                <li class="mb-1">
                    <a href="supplier.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'supplier.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-truck mr-3 w-5 text-center"></i> Suppliers
                    </a>
                </li>
                <li class="mb-1">
                    <a href="user.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'user.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-user-shield mr-3 w-5 text-center"></i> Users
                    </a>
                </li>
                <li class="mb-1">
                    <a href="report.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-file-alt mr-3 w-5 text-center"></i> Report
                    </a>
                </li>
                <li class="mb-1">
                    <a href="auditlog.php" class="nav-item flex items-center p-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'auditlog.php' ? 'active-nav-item' : ''; ?>">
                        <i class="fas fa-clipboard-list mr-3 w-5 text-center"></i> Audit Log
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center p-4">
                    <div class="text-xl font-bold text-purple-800">
                        <i class="<?php echo $pageIcon; ?> mr-2"></i> <?php echo $pageTitle; ?>
                    </div>
                    <div class="flex items-center">
                        <span class="mr-2">Hello, <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?></span>
                        <div class="dropdown">
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-800 cursor-pointer" onclick="toggleDropdown()">
                                <i class="fas fa-user"></i>
                            </div>
                            <div id="userDropdown" class="dropdown-content">
                                <a href="profile.php"><i class="fas fa-user-circle mr-2"></i> Profile</a>
                                <a href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                <?php echo $content; ?>
            </main>
        </div>
    </div>

    <!-- Modal Container (will be populated dynamically) -->
    <div id="modalContainer" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle" class="text-xl font-bold text-purple-800">Add Record</h2>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody">
                <!-- Modal content will be inserted here -->
            </div>
        </div>
    </div>

    <script>
        // Toggle dropdown menu
        function toggleDropdown() {
            document.getElementById("userDropdown").classList.toggle("show");
        }

        // Close the dropdown if clicked outside
        window.onclick = function(event) {
            if (!event.target.matches('.dropdown *')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
            
            // Close modal if clicked outside
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        }
        
        // Modal functions
        function openModal(title, content) {
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalBody').innerHTML = content;
            document.getElementById('modalContainer').style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
        }
        
        function closeModal() {
            document.getElementById('modalContainer').style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }
    </script>
</body>
</html>
<?php
ob_end_flush();
?> 
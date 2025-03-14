-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 12, 2025 at 02:19 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `drugstore`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`log_id`, `user_id`, `action`, `timestamp`, `details`) VALUES
(0, 1, 'Login', '2025-03-10 02:15:25', 'User logged in successfully'),
(1, 1, 'Login', '2025-01-05 08:00:00', 'System login from IP 192.168.1.1'),
(2, 2, 'Inventory Update', '2025-01-05 09:15:00', 'Added 50 units of Amoxicillin'),
(3, 3, 'Product Added', '2025-01-06 10:30:00', 'Added new product: Vitamin C 500mg'),
(4, 4, 'Sale Completed', '2025-01-07 14:45:00', 'Sale #2 processed'),
(5, 5, 'Discount Applied', '2025-01-10 11:20:00', 'Applied senior discount to Sale #3'),
(6, 6, 'Stock Transfer', '2025-01-12 16:00:00', 'Transferred 20 units of Paracetamol to front display'),
(7, 7, 'Report Generated', '2025-01-15 17:30:00', 'Generated monthly sales report'),
(8, 8, 'Password Change', '2025-01-18 09:45:00', 'User changed password'),
(9, 9, 'User Added', '2025-01-20 13:15:00', 'Added new user: Nina Lopez'),
(10, 10, 'Logout', '2025-01-22 18:00:00', 'System logout');

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `product_id` int(11) NOT NULL,
  `category_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`product_id`, `category_name`) VALUES
(1, 'Prescription Medication'),
(2, 'Over-the-Counter Medicine'),
(3, 'Vitamins and Supplements'),
(4, 'Personal Care'),
(5, 'Medical Supplies'),
(6, 'Baby Care'),
(7, 'Health and Wellness'),
(8, 'First Aid'),
(9, 'Hygiene Products'),
(10, 'Miscellaneous Medical');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `first_name`, `last_name`, `contact_number`) VALUES
(1, 'Maria', 'Santos', '+63-945-123-4567'),
(2, 'Juan', 'Reyes', '+63-918-234-5678'),
(3, 'Elena', 'Mendoza', '+63-927-345-6789'),
(4, 'Antonio', 'Cruz', '+63-939-456-7890'),
(5, 'Sofia', 'Garcia', '+63-956-567-8901'),
(6, 'Ramon', 'Bautista', '+63-908-678-9012'),
(7, 'Luisa', 'Gonzales', '+63-917-789-0123'),
(8, 'Miguel', 'Villanueva', '+63-929-890-1234'),
(9, 'Teresa', 'Lopez', '+63-946-901-2345'),
(10, 'Pedro', 'Ramos', '+63-938-012-3456');

-- --------------------------------------------------------

--
-- Table structure for table `discount`
--

CREATE TABLE `discount` (
  `discount_id` int(11) NOT NULL,
  `discount_type` varchar(50) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `discount`
--

INSERT INTO `discount` (`discount_id`, `discount_type`, `discount_amount`) VALUES
(1, 'Senior Citizen', 20.00),
(2, 'PWD', 20.00),
(3, 'Employee', 10.00),
(4, 'Loyalty Program', 5.00),
(5, 'Bulk Purchase', 15.00),
(6, 'Holiday Promo', 12.00),
(7, 'Clearance Sale', 25.00),
(8, 'Bundle Discount', 8.00),
(9, 'First-time Customer', 5.00),
(10, 'Anniversary Promo', 10.00);

-- --------------------------------------------------------

--
-- Table structure for table `expired_products`
--

CREATE TABLE `expired_products` (
  `expired_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `disposal_date` date DEFAULT NULL,
  `disposal_method` varchar(50) DEFAULT NULL,
  `disposal_notes` text DEFAULT NULL,
  `disposal_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expired_products`
--

INSERT INTO `expired_products` (`expired_id`, `inventory_id`, `product_id`, `quantity`, `expiry_date`, `disposal_date`, `disposal_method`, `disposal_notes`, `disposal_by`) VALUES
(0, 10, 1, 1, '2024-06-19', '2025-03-12', 'Return to Supplier', 'Disposed through medical waste contractor', 1),
(1, 1, 1, 25, '2025-01-05', '2025-01-06', 'Disposal', 'Returned to supplier for proper disposal', 5),
(2, 2, 2, 15, '2025-01-10', '2025-01-12', 'Disposal', 'Disposed through medical waste contractor', 3),
(3, 3, 3, 10, '2025-01-12', '2025-01-14', 'Disposal', 'Disposed through medical waste contractor', 5),
(4, 4, 4, 5, '2025-01-15', '2025-01-16', 'Return', 'Returned to supplier for credit', 6),
(5, 5, 5, 8, '2025-01-18', '2025-01-20', 'Disposal', 'Disposed through medical waste contractor', 3),
(6, 6, 6, 3, '2025-01-20', '2025-01-21', 'Return', 'Returned to supplier for credit', 6),
(7, 7, 7, 2, '2025-01-22', '2025-01-23', 'Disposal', 'Disposed through medical waste contractor', 5),
(8, 8, 8, 12, '2025-01-24', '2025-01-25', 'Disposal', 'Disposed through medical waste contractor', 3),
(9, 9, 9, 7, '2025-01-26', '2025-01-27', 'Return', 'Returned to supplier for credit', 6),
(10, 10, 10, 20, '2025-01-28', '2025-01-30', 'Disposal', 'Disposed through medical waste contractor', 5);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `stock_in` int(11) DEFAULT NULL,
  `stock_out` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `stock_available` int(11) DEFAULT NULL,
  `low_stock_threshold` int(11) DEFAULT NULL,
  `status` enum('active','quarantined','expired') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `product_id`, `stock_in`, `stock_out`, `expiry_date`, `stock_available`, `low_stock_threshold`, `status`) VALUES
(1, 1, 500, 138, '2026-06-15', 362, 100, 'active'),
(2, 2, 1000, 350, '2027-03-20', 650, 200, 'active'),
(3, 3, 300, 85, '2026-12-10', 215, 50, 'active'),
(4, 4, 250, 60, '2026-09-05', 190, 40, 'active'),
(5, 5, 400, 120, '2028-01-15', 280, 80, 'active'),
(6, 7, 90, 35, '2026-05-22', 55, 30, 'active'),
(7, 8, 9, 11, '2027-07-18', -2, 10, 'active'),
(8, 8, 350, 95, '2026-11-30', 255, 70, 'active'),
(9, 4, 200, 45, '2025-03-29', 155, 50, 'active'),
(10, 6, 800, 175, '2024-06-19', 625, 100, 'expired');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expiry_date` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`reset_id`, `user_id`, `token`, `expiry_date`, `used`, `created_at`) VALUES
(1, 1, '8f5adee3174a814c5a910ebfbc0886835408bc156bfe042c67a8af0a1689851c', '2025-03-10 18:48:45', 0, '2025-03-10 16:48:45'),
(2, 1, '5799bf34c88bac6ec2851886493de078b25b4f51bf47ff0b3d6a29ca31933a0c', '2025-03-10 18:49:51', 0, '2025-03-10 16:49:51'),
(3, 1, 'b65a1e57ba39a38e7034d73241053946f48ea0877410c24a61671632e3c9ca83', '2025-03-10 18:51:12', 0, '2025-03-10 16:51:12'),
(4, 1, '4890a0aa1be93e7f9ed4968283556c0b9466cc7d8d4c57f43f645b630f67644f', '2025-03-10 18:51:29', 0, '2025-03-10 16:51:29'),
(5, 1, '2a65ad09ec62e3f61bb8c11f87dae4dd2e216c8bcc964aa3447ed3c38435e223', '2025-03-10 18:52:56', 0, '2025-03-10 16:52:56'),
(6, 1, '24085b946cdce2013189a18d3c43e0b7dbd502b4f218674f5e73b23de4a7f43c', '2025-03-10 19:09:41', 0, '2025-03-10 17:09:41'),
(7, 1, '97cd6574e28a46122c423b96981c744a3cf0ea5829e0cc612f6659ca9663fec7', '2025-03-10 19:09:53', 0, '2025-03-10 17:09:53'),
(8, 1, '165fda79786e814d168376898e9073e7be769d011fe2de734620fda85efbbb4b', '2025-03-10 19:11:04', 0, '2025-03-10 17:11:04'),
(9, 1, '6d60441eda1eb81191487e1f68dce50673bea8a899b480f1df46dd838e689447', '2025-03-10 19:26:25', 0, '2025-03-10 17:26:25'),
(10, 1, 'b14f3af22d57d669979d927697db56fa204bd33f2ad3df33d646b26fbaf761f7', '2025-03-10 19:28:02', 0, '2025-03-10 17:28:02'),
(11, 1, 'ec6954c8c3f5d08a44ac57f6dc81a6b9bbf6a2115e97020c930b7481d482f896', '2025-03-10 19:28:17', 0, '2025-03-10 17:28:17'),
(12, 1, '991a68730aa4960bdf1b42a3b9ff626ba823822bbaa822382aaa2a5aeef88743', '2025-03-10 19:29:21', 0, '2025-03-10 17:29:21'),
(13, 1, 'e95e769c755e576bd30320f2c23f78eb1b7ab505fe8e6426d3ef59e80c80548b', '2025-03-10 19:31:19', 0, '2025-03-10 17:31:19'),
(14, 1, '556b60ebeabe17e5a5c959b091c68723f67409114d5164eff624f2db48475ce8', '2025-03-10 19:32:12', 0, '2025-03-10 17:32:12'),
(15, 1, '461e58eb245725e7e0307baee4b2863ae930097694c2a2aec655ed55c19ad360', '2025-03-10 19:34:07', 0, '2025-03-10 17:34:07'),
(16, 1, '2bc3ccf48a7e7f6f94367bd9316da7c8889b1196e80977c779f3e4fadf41aa9e', '2025-03-11 17:51:08', 0, '2025-03-11 15:51:08'),
(17, 1, 'f54f05534faca127c31baac3b446d8997d2c1e9d3913670fffaaf7c85701df98', '2025-03-11 17:51:12', 0, '2025-03-11 15:51:12'),
(18, 1, 'ea2ed891d7f71066f9e61a22bf811420801d0e08931efa0babb35bb71c5260f3', '2025-03-11 17:51:16', 0, '2025-03-11 15:51:16'),
(19, 1, '70efdcfb2883d9d3b1179e2ff027b4b860884c9d599e2dfdb6406ca42a892ca0', '2025-03-11 17:51:20', 0, '2025-03-11 15:51:20'),
(20, 1, '0f000f5042f2aa1346866779fe9b0517783879efb99d995a0d34e72cd2f96839', '2025-03-11 17:51:24', 0, '2025-03-11 15:51:24'),
(21, 1, '6aa6d070cc3ab6149bdfbf9d2cdeba4d35e776b81c76257837d9bacbf634907a', '2025-03-11 17:51:27', 0, '2025-03-11 15:51:27');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `base_price` decimal(10,2) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`product_id`, `product_name`, `category_id`, `description`, `unit_price`, `base_price`, `supplier_id`) VALUES
(1, 'Amoxicillin 500mg', 1, 'Antibiotic for bacterial infections', 8.50, 5.75, 1),
(2, 'Paracetamol 500mg', 2, 'Pain reliever and fever reducer', 3.25, 1.80, 2),
(3, 'Multivitamins', 3, 'Daily nutritional supplement', 12.75, 7.40, 3),
(4, 'Facial Cleanser', 4, 'Gentle face wash', 9.95, 5.50, 4),
(5, 'Disposable Syringes', 5, 'Sterile syringes for injections', 4.50, 2.25, 5),
(6, 'Baby Formula Stage 1', 6, 'Infant milk formula', 24.95, 17.50, 6),
(7, 'Blood Pressure Monitor', 7, 'Digital BP monitoring device', 45.99, 30.00, 7),
(8, 'Antiseptic Solution', 8, 'For cleaning wounds and cuts', 7.80, 4.50, 8),
(9, 'Adult Diapers', 9, 'Absorbent adult undergarments', 14.50, 9.75, 9),
(10, 'Face Mask N95', 10, 'Protective respiratory mask', 6.99, 3.25, 10);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order`
--

CREATE TABLE `purchase_order` (
  `purchase_order_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `arrival_date` date DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order`
--

INSERT INTO `purchase_order` (`purchase_order_id`, `supplier_id`, `order_date`, `arrival_date`, `total_amount`) VALUES
(1, 1, '2025-01-05', '2025-01-10', 2875.00),
(2, 2, '2025-01-08', '2025-01-13', 1800.00),
(3, 3, '2025-01-10', '2025-01-15', 2220.00),
(4, 4, '2025-01-12', '2025-01-17', 1650.00),
(5, 5, '2025-01-15', '2025-01-20', 3000.00),
(6, 6, '2025-01-18', '2025-01-23', 2625.00),
(7, 7, '2025-01-20', '2025-01-25', 1350.00),
(8, 8, '2025-01-22', '2025-01-27', 2250.00),
(9, 9, '2025-01-25', '2025-01-30', 1950.00),
(10, 10, '2025-01-28', '2025-02-02', 1625.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_details`
--

CREATE TABLE `purchase_order_details` (
  `purchase_order_detail_id` int(11) NOT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_details`
--

INSERT INTO `purchase_order_details` (`purchase_order_detail_id`, `purchase_order_id`, `product_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 1, 500, 5.75, 2875.00),
(2, 2, 2, 1000, 1.80, 1800.00),
(3, 3, 3, 300, 7.40, 2220.00),
(4, 4, 4, 300, 5.50, 1650.00),
(5, 5, 5, 400, 7.50, 3000.00),
(6, 6, 6, 150, 17.50, 2625.00),
(7, 7, 8, 300, 4.50, 1350.00),
(8, 8, 9, 150, 15.00, 2250.00),
(9, 9, 10, 600, 3.25, 1950.00),
(10, 10, 2, 500, 3.25, 1625.00);

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `report_id` int(11) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `report_type` varchar(50) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report`
--

INSERT INTO `report` (`report_id`, `title`, `description`, `report_type`, `generated_by`, `timestamp`) VALUES
(1, 'January Sales Report', 'Monthly sales summary for January 2025', 'Sales', 1, '2025-02-01 09:00:00'),
(2, 'Q1 Inventory Status', 'Inventory levels and valuation for Q1', 'Inventory', 2, '2025-01-15 14:30:00'),
(3, 'Top Selling Products', 'Analysis of best-selling items for January', 'Product Analysis', 3, '2025-02-02 11:15:00'),
(4, 'Expired Medications Report', 'List of expired or near-expiry medications', 'Expiry', 5, '2025-01-10 10:00:00'),
(5, 'Customer Purchase Patterns', 'Analysis of customer buying habits', 'Customer', 1, '2025-01-25 15:45:00'),
(6, 'Supplier Performance Review', 'Evaluation of supplier delivery times and quality', 'Supplier', 6, '2025-01-18 13:30:00'),
(7, 'Daily Sales Summary', 'Summary of daily sales for January 28', 'Sales', 4, '2025-01-28 18:00:00'),
(8, 'Low Stock Alert Report', 'Products below reorder threshold', 'Inventory', 6, '2025-01-30 09:30:00'),
(9, 'Discount Impact Analysis', 'How discounts affected sales in January', 'Financial', 2, '2025-02-05 10:15:00'),
(10, 'User Activity Log', 'Summary of system user activities', 'Security', 10, '2025-01-31 16:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `discount_id` int(11) DEFAULT NULL,
  `net_total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`invoice_id`, `customer_id`, `invoice_date`, `total_amount`, `discount_id`, `net_total`) VALUES
(1, 1, '2025-01-05', 25.50, 1, 20.40),
(2, 2, '2025-01-07', 15.75, NULL, 15.75),
(3, 3, '2025-01-10', 45.99, 4, 43.69),
(4, 4, '2025-01-12', 32.25, NULL, 32.25),
(5, 5, '2025-01-15', 68.80, 2, 55.04),
(6, 6, '2025-01-18', 24.95, NULL, 24.95),
(7, 7, '2025-01-20', 15.30, 3, 13.77),
(8, 8, '2025-01-22', 9.95, NULL, 9.95),
(9, 9, '2025-01-25', 43.50, 5, 36.98),
(10, 10, '2025-01-28', 21.75, NULL, 21.75);

-- --------------------------------------------------------

--
-- Table structure for table `sales_details`
--

CREATE TABLE `sales_details` (
  `sales_details_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_details`
--

INSERT INTO `sales_details` (`sales_details_id`, `invoice_id`, `product_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(0, 1, 1, 6, 8.50, 51.00),
(2, 2, 2, 5, 3.15, 15.75),
(3, 3, 7, 1, 45.99, 45.99),
(4, 4, 4, 3, 9.95, 29.85),
(5, 4, 8, 1, 7.80, 7.80),
(6, 5, 6, 2, 24.95, 49.90),
(7, 5, 3, 1, 12.75, 12.75),
(8, 6, 6, 1, 24.95, 24.95),
(9, 7, 2, 4, 3.15, 12.60),
(10, 7, 5, 1, 4.50, 4.50);

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `supplier_name`, `contact_number`, `email`, `address`) VALUES
(1, 'Unilab Pharmaceuticals', '+63-2-8858-9111', 'info@unilab.com.ph', '66 United St., Mandaluyong City, Metro Manila'),
(2, 'Mercury Drug Corporation', '+63-2-8911-5555', 'customerservice@mercurydrug.com', '7 Mercury Ave., Quezon City, Metro Manila'),
(3, 'Pascual Laboratories', '+63-2-8635-7300', 'contact@pascuallab.com', '16 Shaw Blvd., Pasig City, Metro Manila'),
(4, 'Natrapharm Inc.', '+63-2-8651-7800', 'orders@natrapharm.ph', '789 Rodriguez St., Rizal'),
(5, 'GlaxoSmithKline Philippines', '+63-2-8892-0761', 'gsk.philippines@gsk.com', '2266 Chino Roces Ave., Makati City'),
(6, 'Multicare Pharmaceuticals', '+63-2-8556-7890', 'orders@multicarepharma.ph', '123 Pioneer St., Rizal'),
(7, 'Pharma Care Plus', '+63-49-534-2215', 'info@pharmacareplus.com', '456 Rizal Ave., Jala-Jala, Rizal'),
(8, 'Cathay Drug Co., Inc.', '+63-2-8733-6888', 'sales@cathaydrug.com.ph', '2445 Taft Avenue, Manila'),
(9, 'Ritemed Philippines', '+63-2-8884-7711', 'customercare@ritemed.ph', '1150 EDSA, Quezon City'),
(10, 'Medilines Distributors', '+63-2-8543-1111', 'info@medilines.com.ph', '782 Aurora Blvd., Quezon City');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `email`, `username`, `password_hash`, `role`) VALUES
(1, 'franciscoalvendherjoy01@gmail.com', 'ajfrancisco', 'hashed_password_1', 'Admin'),
(2, 'fssumagaysay@djerpharmacy.com', 'fssumagaysay', 'hashed_password_2', 'Admin'),
(3, 'marcos@djerpharmacy.com', 'jmarcos', 'hashed_password_3', 'Pharmacist'),
(4, 'santos@djerpharmacy.com', 'lsantos', 'hashed_password_4', 'Cashier'),
(5, 'reyes@djerpharmacy.com', 'dreyes', 'hashed_password_5', 'Pharmacist'),
(6, 'villanueva@djerpharmacy.com', 'rvillanueva', 'hashed_password_6', 'Inventory'),
(7, 'cruz@djerpharmacy.com', 'mcruz', 'hashed_password_7', 'Cashier'),
(8, 'garcia@djerpharmacy.com', 'cgarcia', 'hashed_password_8', 'Staff'),
(9, 'mendoza@djerpharmacy.com', 'pmendoza', 'hashed_password_9', 'Staff'),
(10, 'marayag@djerpharmacy.com', 'vpmarayag', 'hashed_password_10', 'Supervisor');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `discount`
--
ALTER TABLE `discount`
  ADD PRIMARY KEY (`discount_id`);

--
-- Indexes for table `expired_products`
--
ALTER TABLE `expired_products`
  ADD PRIMARY KEY (`expired_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `disposal_by` (`disposal_by`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_order`
--
ALTER TABLE `purchase_order`
  ADD PRIMARY KEY (`purchase_order_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_order_details`
--
ALTER TABLE `purchase_order_details`
  ADD PRIMARY KEY (`purchase_order_detail_id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`invoice_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `discount_id` (`discount_id`);

--
-- Indexes for table `sales_details`
--
ALTER TABLE `sales_details`
  ADD PRIMARY KEY (`sales_details_id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `expired_products`
--
ALTER TABLE `expired_products`
  ADD CONSTRAINT `expired_products_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`),
  ADD CONSTRAINT `expired_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`),
  ADD CONSTRAINT `expired_products_ibfk_3` FOREIGN KEY (`disposal_by`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`product_id`),
  ADD CONSTRAINT `product_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`);

--
-- Constraints for table `purchase_order`
--
ALTER TABLE `purchase_order`
  ADD CONSTRAINT `purchase_order_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`);

--
-- Constraints for table `purchase_order_details`
--
ALTER TABLE `purchase_order_details`
  ADD CONSTRAINT `purchase_order_details_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_order` (`purchase_order_id`),
  ADD CONSTRAINT `purchase_order_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`);

--
-- Constraints for table `report`
--
ALTER TABLE `report`
  ADD CONSTRAINT `report_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`discount_id`) REFERENCES `discount` (`discount_id`);

--
-- Constraints for table `sales_details`
--
ALTER TABLE `sales_details`
  ADD CONSTRAINT `sales_details_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `sales` (`invoice_id`),
  ADD CONSTRAINT `sales_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 27, 2024 at 12:37 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbs13455438`
--

-- --------------------------------------------------------

--
-- Table structure for table `activation_codes`
--

CREATE TABLE `activation_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activation_code` varchar(100) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activation_codes`
--

INSERT INTO `activation_codes` (`id`, `user_id`, `activation_code`, `expires_at`, `created_at`) VALUES
(2, 2, '6726e9481e942', '2024-11-04 04:08:56', '2024-11-03 03:08:56');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(8, 'Devices', NULL, '2024-11-03 03:13:26'),
(9, 'Foods', NULL, '2024-11-03 19:35:05'),
(10, 'Auto', NULL, '2024-11-03 19:38:49'),
(11, 'Fashion', NULL, '2024-11-03 19:40:54'),
(12, 'Cosmetics', NULL, '2024-11-03 19:46:28'),
(13, 'Items', NULL, '2024-11-17 19:00:56');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phone` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_location` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_name`, `customer_email`, `customer_phone`, `customer_location`, `created_at`) VALUES
(15, 'james', 'james@gmail.com', '876543', 'Madrid', '2024-11-03 03:27:42'),
(16, 'sam', 'sam@gmail.com', '2345678', 'Port Louis', '2024-11-03 19:49:49'),
(17, 'neema', 'neema@gmail.com', '3498765', 'Nairobi', '2024-11-03 19:50:49'),
(18, 'tom', 'tom@gmail.com', '+1 345678', 'New York', '2024-11-03 19:52:34'),
(19, 'mickey', 'mickey@gmail.com', '1234567', 'Lagos', '2024-11-17 18:34:11'),
(20, 'kim', 'kim@gmail.com', '3456789', 'Tokyo', '2024-11-17 18:46:57');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `description`, `amount`, `expense_date`, `created_by`) VALUES
(4, 'Rent', 500.00, '2024-11-02 04:00:00', 'Tom'),
(5, 'Taxes and Levy', 800.00, '2024-10-03 04:00:00', 'karim'),
(6, 'Renovation', 800.00, '2024-09-06 04:00:00', 'jerry'),
(7, 'Marketing', 1000.00, '2024-07-19 04:00:00', 'john'),
(8, 'Bills', 3500.00, '2024-08-14 04:00:00', 'william'),
(9, 'Wages', 2800.00, '2024-06-13 04:00:00', 'tamika');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `sales_qty` int(11) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `stock_qty` int(11) DEFAULT NULL,
  `supply_qty` int(11) DEFAULT NULL,
  `available_stock` int(11) GENERATED ALWAYS AS (`stock_qty` + `supply_qty` - `sales_qty`) STORED,
  `inventory_qty` int(11) GENERATED ALWAYS AS (`stock_qty` + `supply_qty`) STORED,
  `product_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `product_id`, `sales_qty`, `last_updated`, `stock_qty`, `supply_qty`, `product_name`) VALUES
(5, 11, 20, '2024-11-03 03:33:41', 15, 12, 'Samsung Galaxy'),
(6, 18, 20, '2024-11-03 20:02:26', 25, 50, 'Makeup kit'),
(7, 17, 40, '2024-11-03 20:02:26', 40, 100, 'Beauty Soap'),
(8, 16, 20, '2024-11-03 20:02:26', 49, 60, 'Nike Sneakers'),
(9, 15, 15, '2024-11-03 20:02:26', 40, 50, 'Floral Dress'),
(10, 14, 2, '2024-11-03 20:02:26', 5, 10, 'Chevrolet AWD'),
(11, 13, 5, '2024-11-03 20:02:26', 25, 10, 'Sony Camera'),
(12, 12, 20, '2024-11-03 20:02:26', 50, 20, 'Pilsner Beer'),
(13, 19, 10, '2024-11-04 08:58:16', 20, 50, 'Apple IPhone 15'),
(14, 20, 25, '2024-11-17 19:21:36', 45, 30, 'Headphones'),
(15, 21, 1, '2024-11-17 19:21:36', 4, 1, 'Toyota Corolla'),
(16, 23, 18, '2024-11-17 19:21:36', 10, 30, 'YSL Perfume'),
(17, 24, 90, '2024-11-17 19:21:36', 100, 150, 'Vitamin Water'),
(18, 25, 15, '2024-11-17 19:21:36', 35, 50, 'Outdoor Hike Bag'),
(19, 26, 35, '2024-11-17 19:21:36', 40, 60, 'Wrist watch');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `invoice_description` text DEFAULT NULL,
  `order_date` date NOT NULL,
  `order_status` enum('Paid','Unpaid') NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `delivery_address` text NOT NULL,
  `mode_of_payment` varchar(255) NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(5,2) NOT NULL,
  `total_amount` decimal(10,2) GENERATED ALWAYS AS (`subtotal` - `subtotal` * (`discount` / 100)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `invoice_number`, `customer_name`, `invoice_description`, `order_date`, `order_status`, `order_id`, `delivery_address`, `mode_of_payment`, `due_date`, `subtotal`, `discount`) VALUES
(12, '567', 'james', 'delivery', '2024-11-01', 'Paid', '4567', '112 freway oho', 'mpesa', '2024-11-05', 640.00, 2.00),
(13, '6578', 'Macro stores', 'product delivery', '2024-10-02', 'Paid', '76890', '112 freeway atlanta', 'Visa Card', '2024-11-16', 1930.00, 5.00),
(14, '09876', 'smooth travels', 'customer invoice', '2024-10-09', 'Paid', '12378', '345 uhuru highway nairobi', 'Master Card', '2024-10-25', 7500.00, 10.00);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `invoice_items_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) GENERATED ALWAYS AS (`qty` * `price`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`invoice_items_id`, `invoice_id`, `item_name`, `qty`, `price`) VALUES
(23, 12, 'laptop', 2, 200.00),
(24, 12, 'bag', 2, 120.00),
(25, 13, 'Apple IPhone', 2, 900.00),
(26, 13, 'Headphones', 2, 65.00),
(27, 14, 'Web design', 1, 5000.00),
(28, 14, 'SEO services', 1, 2500.00);

-- --------------------------------------------------------

--
-- Table structure for table `page_access`
--

CREATE TABLE `page_access` (
  `id` int(11) NOT NULL,
  `page` varchar(255) NOT NULL,
  `required_access_level` enum('trial','starter','business','enterprise') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `page_access`
--

INSERT INTO `page_access` (`id`, `page`, `required_access_level`, `created_at`, `updated_at`) VALUES
(1, 'dashboard.php', 'trial', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(13, 'page-add-expense.php', 'starter', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(14, 'page-add-product.php', 'starter', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(15, 'page-list-inventory.php', 'starter', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(16, 'page-list-sale.php', 'starter', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(17, 'page-add-sale.php', 'starter', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(18, 'page-list-category.php', 'starter', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(19, 'page-list-product.php', 'starter', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(20, 'analytics-report.php', 'business', '2024-11-12 18:01:55', '2024-11-30 16:53:16'),
(21, 'analytics.php', 'enterprise', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(22, 'inventory-metrics.php', 'business', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(23, 'sales-metrics.php', 'business', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(28, 'invoice-form.php', 'enterprise', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(29, 'edit_invoice.php', 'enterprise', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(30, 'subscription.php', 'trial', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(31, 'pay.php', 'trial', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(32, 'pages-invoice.php', 'enterprise', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(33, 'page-add-customers.php', 'business', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(37, 'page-add-staffs.php', 'business', '2024-11-12 18:01:55', '2024-11-30 16:51:00'),
(38, 'page-add-supplier.php', 'business', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(40, 'page-list-customers.php', 'business', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(41, 'page-list-expense.php', 'starter', '2024-11-12 18:01:55', '2024-11-12 18:01:55'),
(45, 'page-list-staffs.php', 'business', '2024-11-12 18:01:55', '2024-11-30 16:51:43'),
(46, 'page-list-suppliers.php', 'business', '2024-11-12 18:01:55', '2024-11-12 18:01:55');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reset_code` varchar(100) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_method` enum('paypal','binance','mpesa','naira') NOT NULL,
  `payment_proof` varchar(255) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `subscription_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `stock_qty` int(11) NOT NULL,
  `supply_qty` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `product_type` enum('Goods','Services','Digital') NOT NULL DEFAULT 'Goods',
  `staff_name` varchar(45) NOT NULL,
  `category` varchar(255) NOT NULL,
  `inventory_qty` int(11) GENERATED ALWAYS AS (`stock_qty` + `supply_qty`) STORED,
  `profit` decimal(10,2) GENERATED ALWAYS AS (`price` - `cost`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `cost`, `category_id`, `created_at`, `stock_qty`, `supply_qty`, `image_path`, `product_type`, `staff_name`, `category`) VALUES
(11, 'Samsung Galaxy', 'A10s', 150.00, 100.00, 8, '2024-11-03 03:13:26', 15, 12, 'uploads/products/samsung-galaxy-a10s.jpg', 'Goods', 'joe', 'Devices'),
(12, 'Pilsner Beer', 'Premium Beer', 25.00, 20.00, 9, '2024-11-03 19:35:05', 50, 20, 'uploads/products/beer.jpg', 'Goods', 'kim', 'Food'),
(13, 'Sony Camera', 'AI Digital Camera', 75.00, 50.00, 8, '2024-11-03 19:36:50', 25, 10, 'uploads/products/camera.jpg', 'Goods', 'mike', 'Devices'),
(14, 'Chevrolet AWD', 'Chevrolet AWD 2024', 18000.00, 15000.00, 10, '2024-11-03 19:38:49', 5, 10, 'uploads/products/Chevrolet.jpg', 'Goods', 'james', 'Auto'),
(15, 'Floral Dress', 'floral-pleated-weave-dress', 55.00, 40.00, 11, '2024-11-03 19:40:54', 40, 50, 'uploads/products/floral-pleated-weave-dress.jpg', 'Goods', 'joe', 'Fashon'),
(16, 'Nike Sneakers', 'Nike Air Jordan', 95.00, 70.00, 11, '2024-11-03 19:42:47', 49, 60, 'uploads/products/nike-sneakers.jpg', 'Goods', 'mike', 'Fashion'),
(17, 'Beauty Soap', 'Skincare Soap', 20.00, 10.00, 12, '2024-11-03 19:46:28', 40, 100, 'uploads/products/soap.jpg', 'Goods', 'james', 'Cosmetic'),
(18, 'Makeup kit', 'Mary Kay Makeup kit', 25.00, 20.00, 12, '2024-11-03 19:48:20', 25, 50, 'uploads/products/make-up.jpg', 'Goods', 'kim', 'Cosmetic'),
(19, 'Apple IPhone 15', 'Apple IPhone 15 128GB', 1500.00, 900.00, 8, '2024-11-03 20:19:50', 20, 50, 'uploads/products/iphone-15-128gb.jpg', 'Goods', 'james', 'Devices'),
(20, 'Headphones', 'Sony Digital Headphones', 70.00, 45.00, 8, '2024-11-17 18:30:03', 45, 30, 'uploads/products/headphones.jpg', 'Goods', 'luke', 'Devices'),
(21, 'Toyota Corolla', 'Toyota Corolla 2024', 14000.00, 12000.00, 10, '2024-11-17 18:36:39', 4, 1, 'uploads/products/toyota-corolla-2024.jpg', 'Goods', 'john', 'Auto'),
(22, 'Ray Sunglases', 'Ray Sunglases', 85.00, 50.00, 11, '2024-11-17 18:40:24', 12, 20, 'uploads/products/rayban.jpg', 'Goods', 'mark', 'Fashion'),
(23, 'YSL Perfume', 'Yves Saint Laurent', 120.00, 80.00, 12, '2024-11-17 18:42:51', 10, 30, 'uploads/products/perfume.jpg', 'Goods', 'luke', 'Cosmetic'),
(24, 'Vitamin Water', 'Vitamin Water Supplement', 25.00, 10.00, 9, '2024-11-17 18:48:51', 100, 150, 'uploads/products/water.jpg', 'Goods', 'luke', 'Foods'),
(25, 'Outdoor Hike Bag', 'Outdoor Hike Bag', 90.00, 65.00, 13, '2024-11-17 19:00:56', 35, 50, 'uploads/products/hike-bag.jpg', 'Goods', 'john', 'Items'),
(26, 'Wrist watch', 'Wrist watch', 350.00, 200.00, 11, '2024-11-17 19:06:23', 40, 60, 'uploads/products/wristwatch.jpg', 'Goods', 'matt', 'Fashion'),
(27, 'Premium Mugs', 'Premium Mugs', 45.00, 30.00, 13, '2024-11-17 19:14:37', 50, 100, 'uploads/products/mugs.jpg', 'Goods', 'matt', 'Items');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `reports_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `revenue` decimal(10,2) NOT NULL,
  `profit_margin` decimal(5,2) NOT NULL,
  `revenue_by_product` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`revenue_by_product`)),
  `year_over_year_growth` decimal(5,2) NOT NULL,
  `cost_of_selling` decimal(10,2) NOT NULL,
  `inventory_turnover_rate` decimal(5,2) NOT NULL,
  `stock_to_sales_ratio` decimal(5,2) NOT NULL,
  `sell_through_rate` decimal(10,2) NOT NULL,
  `gross_margin_by_product` decimal(10,2) NOT NULL,
  `net_margin_by_product` decimal(10,2) NOT NULL,
  `gross_margin` decimal(10,2) NOT NULL,
  `net_margin` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_sales` decimal(10,0) NOT NULL,
  `total_quantity` int(11) NOT NULL,
  `total_profit` decimal(10,2) NOT NULL,
  `total_expenses` decimal(10,2) NOT NULL,
  `net_profit` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`reports_id`, `report_date`, `revenue`, `profit_margin`, `revenue_by_product`, `year_over_year_growth`, `cost_of_selling`, `inventory_turnover_rate`, `stock_to_sales_ratio`, `sell_through_rate`, `gross_margin_by_product`, `net_margin_by_product`, `gross_margin`, `net_margin`, `created_at`, `total_sales`, `total_quantity`, `total_profit`, `total_expenses`, `net_profit`) VALUES
(5, '2024-02-15', 58900.00, 24.53, '[{\"product_id\":11,\"product_name\":\"Samsung Galaxy\",\"total_quantity\":\"20\",\"total_sales\":\"3000.00\",\"total_cost\":\"2000.00\",\"total_profit\":\"1000.00\",\"inventory_turnover_rate\":\"1.3333\",\"sell_through_rate\":\"150.000000\"},{\"product_id\":12,\"product_name\":\"Pilsner Beer\",\"total_quantity\":\"20\",\"total_sales\":\"500.00\",\"total_cost\":\"400.00\",\"total_profit\":\"100.00\",\"inventory_turnover_rate\":\"0.4000\",\"sell_through_rate\":\"125.000000\"},{\"product_id\":13,\"product_name\":\"Sony Camera\",\"total_quantity\":\"5\",\"total_sales\":\"375.00\",\"total_cost\":\"250.00\",\"total_profit\":\"125.00\",\"inventory_turnover_rate\":\"0.2000\",\"sell_through_rate\":\"150.000000\"},{\"product_id\":14,\"product_name\":\"Chevrolet AWD\",\"total_quantity\":\"2\",\"total_sales\":\"36000.00\",\"total_cost\":\"30000.00\",\"total_profit\":\"6000.00\",\"inventory_turnover_rate\":\"0.4000\",\"sell_through_rate\":\"120.000000\"},{\"product_id\":15,\"product_name\":\"Floral Dress\",\"total_quantity\":\"15\",\"total_sales\":\"825.00\",\"total_cost\":\"600.00\",\"total_profit\":\"225.00\",\"inventory_turnover_rate\":\"0.3750\",\"sell_through_rate\":\"137.500000\"},{\"product_id\":16,\"product_name\":\"Nike Sneakers\",\"total_quantity\":\"20\",\"total_sales\":\"1900.00\",\"total_cost\":\"1400.00\",\"total_profit\":\"500.00\",\"inventory_turnover_rate\":\"0.4082\",\"sell_through_rate\":\"135.714286\"},{\"product_id\":17,\"product_name\":\"Beauty Soap\",\"total_quantity\":\"40\",\"total_sales\":\"800.00\",\"total_cost\":\"400.00\",\"total_profit\":\"400.00\",\"inventory_turnover_rate\":\"1.0000\",\"sell_through_rate\":\"200.000000\"},{\"product_id\":18,\"product_name\":\"Makeup kit\",\"total_quantity\":\"20\",\"total_sales\":\"500.00\",\"total_cost\":\"400.00\",\"total_profit\":\"100.00\",\"inventory_turnover_rate\":\"0.8000\",\"sell_through_rate\":\"125.000000\"},{\"product_id\":19,\"product_name\":\"Apple IPhone 15\",\"total_quantity\":\"10\",\"total_sales\":\"15000.00\",\"total_cost\":\"9000.00\",\"total_profit\":\"6000.00\",\"inventory_turnover_rate\":\"0.5000\",\"sell_through_rate\":\"166.666667\"}]', 0.00, 0.00, 3.00, 0.26, 3.88, 0.00, 0.00, 14450.00, -30000.00, '2024-11-15 17:14:59', 58900, 152, 14450.00, 44450.00, -30000.00),
(6, '2024-06-17', 92660.00, 26.73, '[{\"product_id\":11,\"product_name\":\"Samsung Galaxy\",\"total_quantity\":\"20\",\"total_sales\":\"3000.00\",\"total_cost\":\"2000.00\",\"total_profit\":\"1000.00\",\"inventory_turnover_rate\":\"1.3333\",\"sell_through_rate\":\"150.000000\"},{\"product_id\":12,\"product_name\":\"Pilsner Beer\",\"total_quantity\":\"20\",\"total_sales\":\"500.00\",\"total_cost\":\"400.00\",\"total_profit\":\"100.00\",\"inventory_turnover_rate\":\"0.4000\",\"sell_through_rate\":\"125.000000\"},{\"product_id\":13,\"product_name\":\"Sony Camera\",\"total_quantity\":\"5\",\"total_sales\":\"375.00\",\"total_cost\":\"250.00\",\"total_profit\":\"125.00\",\"inventory_turnover_rate\":\"0.2000\",\"sell_through_rate\":\"150.000000\"},{\"product_id\":14,\"product_name\":\"Chevrolet AWD\",\"total_quantity\":\"2\",\"total_sales\":\"36000.00\",\"total_cost\":\"30000.00\",\"total_profit\":\"6000.00\",\"inventory_turnover_rate\":\"0.4000\",\"sell_through_rate\":\"120.000000\"},{\"product_id\":15,\"product_name\":\"Floral Dress\",\"total_quantity\":\"15\",\"total_sales\":\"825.00\",\"total_cost\":\"600.00\",\"total_profit\":\"225.00\",\"inventory_turnover_rate\":\"0.3750\",\"sell_through_rate\":\"137.500000\"},{\"product_id\":16,\"product_name\":\"Nike Sneakers\",\"total_quantity\":\"20\",\"total_sales\":\"1900.00\",\"total_cost\":\"1400.00\",\"total_profit\":\"500.00\",\"inventory_turnover_rate\":\"0.4082\",\"sell_through_rate\":\"135.714286\"},{\"product_id\":17,\"product_name\":\"Beauty Soap\",\"total_quantity\":\"40\",\"total_sales\":\"800.00\",\"total_cost\":\"400.00\",\"total_profit\":\"400.00\",\"inventory_turnover_rate\":\"1.0000\",\"sell_through_rate\":\"200.000000\"},{\"product_id\":18,\"product_name\":\"Makeup kit\",\"total_quantity\":\"20\",\"total_sales\":\"500.00\",\"total_cost\":\"400.00\",\"total_profit\":\"100.00\",\"inventory_turnover_rate\":\"0.8000\",\"sell_through_rate\":\"125.000000\"},{\"product_id\":19,\"product_name\":\"Apple IPhone 15\",\"total_quantity\":\"10\",\"total_sales\":\"15000.00\",\"total_cost\":\"9000.00\",\"total_profit\":\"6000.00\",\"inventory_turnover_rate\":\"0.5000\",\"sell_through_rate\":\"166.666667\"},{\"product_id\":20,\"product_name\":\"Headphones\",\"total_quantity\":\"25\",\"total_sales\":\"1750.00\",\"total_cost\":\"1125.00\",\"total_profit\":\"625.00\",\"inventory_turnover_rate\":\"0.5556\",\"sell_through_rate\":\"155.555556\"},{\"product_id\":21,\"product_name\":\"Toyota Corolla\",\"total_quantity\":\"1\",\"total_sales\":\"14000.00\",\"total_cost\":\"12000.00\",\"total_profit\":\"2000.00\",\"inventory_turnover_rate\":\"0.2500\",\"sell_through_rate\":\"116.666667\"},{\"product_id\":23,\"product_name\":\"YSL Perfume\",\"total_quantity\":\"18\",\"total_sales\":\"2160.00\",\"total_cost\":\"1440.00\",\"total_profit\":\"720.00\",\"inventory_turnover_rate\":\"1.8000\",\"sell_through_rate\":\"150.000000\"},{\"product_id\":24,\"product_name\":\"Vitamin Water\",\"total_quantity\":\"90\",\"total_sales\":\"2250.00\",\"total_cost\":\"900.00\",\"total_profit\":\"1350.00\",\"inventory_turnover_rate\":\"0.9000\",\"sell_through_rate\":\"250.000000\"},{\"product_id\":25,\"product_name\":\"Outdoor Hike Bag\",\"total_quantity\":\"15\",\"total_sales\":\"1350.00\",\"total_cost\":\"975.00\",\"total_profit\":\"375.00\",\"inventory_turnover_rate\":\"0.4286\",\"sell_through_rate\":\"138.461538\"},{\"product_id\":26,\"product_name\":\"Wrist watch\",\"total_quantity\":\"35\",\"total_sales\":\"12250.00\",\"total_cost\":\"7000.00\",\"total_profit\":\"5250.00\",\"inventory_turnover_rate\":\"0.8750\",\"sell_through_rate\":\"175.000000\"}]', 0.00, 0.00, 2.50, 0.36, 4.76, 0.00, 0.00, 24770.00, -43120.00, '2024-11-17 18:25:29', 92660, 336, 24770.00, 67890.00, -43120.00);

-- --------------------------------------------------------

--
-- Table structure for table `revenue_by_product`
--

CREATE TABLE `revenue_by_product` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `total_quantity` int(11) NOT NULL,
  `total_sales` decimal(15,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `total_profit` decimal(15,2) NOT NULL,
  `inventory_turnover_rate` decimal(10,4) NOT NULL,
  `sell_through_rate` decimal(10,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sales_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `sales_qty` int(11) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `sale_status` enum('completed','pending') NOT NULL DEFAULT 'pending',
  `payment_status` enum('completed','due','paid') NOT NULL DEFAULT 'due',
  `name` varchar(255) NOT NULL,
  `product_type` enum('Goods','Services','Digital') NOT NULL,
  `sale_note` varchar(255) NOT NULL,
  `sales_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`sales_qty` * `sales_price`) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sales_id`, `product_id`, `user_id`, `customer_id`, `staff_id`, `sales_qty`, `sale_date`, `sale_status`, `payment_status`, `name`, `product_type`, `sale_note`, `sales_price`) VALUES
(10, 11, 2, 15, 13, 20, '2024-01-03 04:32:21', 'completed', 'paid', 'Samsung Galaxy', 'Goods', 'sold', 150.00),
(11, 18, 2, 16, 13, 20, '2024-02-03 19:49:49', 'completed', 'paid', 'Makeup kit', 'Goods', 'sold', 25.00),
(12, 17, 2, 17, 14, 40, '2024-03-13 18:50:49', 'completed', 'paid', 'Beauty Soap', 'Goods', 'sold', 20.00),
(13, 16, 2, 18, 15, 20, '2024-04-23 18:52:34', 'completed', 'paid', 'Nike Sneakers', 'Goods', 'sold', 95.00),
(14, 15, 2, 15, 16, 15, '2024-05-03 18:54:15', 'completed', 'paid', 'Floral Dress', 'Goods', 'sold', 55.00),
(15, 14, 2, 16, 13, 2, '2024-06-23 18:56:16', 'completed', 'paid', 'Chevrolet AWD', 'Goods', 'sold', 18000.00),
(16, 13, 2, 17, 17, 5, '2024-07-09 18:57:31', 'completed', 'paid', 'Sony Camera', 'Goods', 'SOLD', 75.00),
(17, 12, 2, 18, 15, 20, '2024-11-03 19:58:32', 'completed', 'paid', 'Pilsner Beer', 'Goods', 'sold', 25.00),
(18, 19, 2, 15, 13, 10, '2024-11-03 20:21:11', 'completed', 'paid', 'Apple IPhone 15', 'Goods', 'sold', 1500.00),
(19, 20, 2, 19, 18, 25, '2024-11-17 18:34:11', 'completed', 'paid', 'Headphones', 'Goods', 'sold', 75.00),
(20, 21, 2, 15, 19, 1, '2024-11-17 18:37:20', 'pending', '', 'Toyota Corolla', 'Goods', '', 14000.00),
(21, 23, 2, 20, 20, 18, '2024-11-17 18:46:57', 'pending', 'due', 'YSL Perfume', 'Goods', '', 120.00),
(22, 24, 2, 19, 21, 90, '2024-11-17 18:50:14', 'completed', 'paid', 'Vitamin Water', 'Goods', '', 25.00),
(23, 25, 2, 20, 20, 15, '2024-11-17 19:04:27', 'completed', 'paid', 'Outdoor Hike Bag', 'Goods', '', 90.00),
(24, 26, 2, 15, 18, 35, '2024-11-17 19:06:55', 'completed', 'paid', 'Wrist watch', 'Goods', '', 350.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales_analytics`
--

CREATE TABLE `sales_analytics` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `revenue` decimal(10,2) NOT NULL,
  `profit_margin` decimal(10,2) NOT NULL,
  `year_over_year_growth` decimal(10,2) NOT NULL,
  `cost_of_selling` decimal(10,2) NOT NULL,
  `inventory_turnover_rate` decimal(10,2) NOT NULL,
  `stock_to_sales_ratio` decimal(10,2) NOT NULL,
  `sell_through_rate` decimal(10,2) NOT NULL,
  `gross_margin_by_category` decimal(10,2) NOT NULL,
  `net_margin_by_category` decimal(10,2) NOT NULL,
  `gross_margin` decimal(10,2) NOT NULL,
  `net_margin` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_sales` decimal(10,2) NOT NULL,
  `total_quantity` int(11) NOT NULL,
  `total_profit` decimal(10,2) NOT NULL,
  `total_expenses` decimal(10,2) NOT NULL,
  `net_profit` decimal(10,2) NOT NULL,
  `revenue_by_category` decimal(10,2) NOT NULL,
  `most_sold_product_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_analytics`
--

INSERT INTO `sales_analytics` (`id`, `date`, `revenue`, `profit_margin`, `year_over_year_growth`, `cost_of_selling`, `inventory_turnover_rate`, `stock_to_sales_ratio`, `sell_through_rate`, `gross_margin_by_category`, `net_margin_by_category`, `gross_margin`, `net_margin`, `created_at`, `total_sales`, `total_quantity`, `total_profit`, `total_expenses`, `net_profit`, `revenue_by_category`, `most_sold_product_id`) VALUES
(9, '2024-06-12', 30000.00, 33.33, 0.00, 0.00, 150.00, 0.67, 999.99, 0.00, 0.00, 2000.00, 1000.00, '2024-11-03 19:17:23', 3000.00, 20, 99.00, 0.00, 0.00, 0.00, 0),
(10, '2024-07-12', 43900.00, 19.25, 0.00, 0.00, 309.15, 0.32, 999.99, 0.00, 0.00, 35450.00, 8450.00, '2024-11-03 20:03:18', 43900.00, 142, 99.00, 0.00, 0.00, 0.00, 0),
(11, '2024-08-24', 58900.00, 24.53, 0.00, 0.00, 387.50, 0.26, 999.99, 0.00, 0.00, 44450.00, 14450.00, '2024-11-04 07:07:40', 58900.00, 152, 99.00, 0.00, 0.00, 0.00, 0),
(18, '2024-11-02', 58900.00, 24.53, 0.00, 0.00, 387.50, 0.26, 3.88, 0.00, 0.00, 44450.00, 14450.00, '2024-11-17 18:25:40', 58900.00, 152, 14450.00, 0.00, 0.00, 0.00, 0),
(19, '2024-11-02', 92660.00, 26.73, 0.00, 0.00, 275.77, 0.36, 2.76, 0.00, 0.00, 67890.00, 24770.00, '2024-11-17 19:25:31', 92660.00, 336, 24770.00, 0.00, 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `staffs`
--

CREATE TABLE `staffs` (
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(100) NOT NULL,
  `staff_email` varchar(100) NOT NULL,
  `staff_phone` varchar(20) NOT NULL,
  `position` enum('manager','sales') NOT NULL DEFAULT 'sales',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staffs`
--

INSERT INTO `staffs` (`staff_id`, `staff_name`, `staff_email`, `staff_phone`, `position`, `created_at`) VALUES
(13, 'joe', 'joe@gmail.com', '123456876', 'sales', '2024-11-03 03:27:42'),
(14, 'jack', 'jack@gmail.com', '+44 456789', 'sales', '2024-11-03 19:50:49'),
(15, 'chris', 'chris@gmail.com', '+1 456789', 'sales', '2024-11-03 19:52:34'),
(16, 'mike', 'mike@gmail.com', '+49 3456789', 'sales', '2024-11-03 19:54:15'),
(17, 'james', 'james@gmail.com', '+234567890', 'sales', '2024-11-03 19:57:31'),
(18, 'luke', 'luke@live.com', '34567890', 'sales', '2024-11-17 18:34:11'),
(19, 'mark', 'mark1@gmail.com', '+14567890', 'sales', '2024-11-17 18:37:20'),
(20, 'matt', 'matt@gmail.com', '+254 456789', 'sales', '2024-11-17 18:46:57'),
(21, 'john', 'john@hotmail.com', '+34567890', 'sales', '2024-11-17 18:50:14');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subscription_plan` enum('trial','starter','business','enterprise') NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiration_date` timestamp NOT NULL DEFAULT '2030-12-31 20:59:59',
  `status` enum('active','expired','canceled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_free_trial_used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `supplier_email` varchar(255) NOT NULL,
  `supplier_phone` varchar(20) NOT NULL,
  `supplier_location` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_name` varchar(255) NOT NULL,
  `supply_qty` int(11) NOT NULL,
  `note` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `supplier_email`, `supplier_phone`, `supplier_location`, `created_at`, `product_name`, `supply_qty`, `note`) VALUES
(2, 'skystore', 'skystores@gmail.com', '876544567', 'London', '2024-11-03 03:37:04', 'Samsung Galaxy', 10, ''),
(3, 'Spring waters ltd', 'springwater@gmail.com', '+3456789', 'Texas', '2024-11-17 19:19:25', 'Vitamin Water', 100, 'cool');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `role` enum('admin','sales','inventory') DEFAULT 'sales',
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmpassword` varchar(255) NOT NULL,
  `user_image` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `location` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `status` int(1) NOT NULL,
  `plan` enum('trial','starter','business','enterprise') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `is_active`, `role`, `date`, `confirmpassword`, `user_image`, `phone`, `location`, `google_id`, `status`, `plan`) VALUES
(2, 'Megastores', 'olphemie@hotmail.com', '$2y$10$X8uPrpPbouNMJNqhZvfKMOk.cxbTO0Cmqm2UIj9Y2r/f3wEyXA2sm', 0, 'admin', '2024-11-03 03:08:56', 'mega1234', 'uploads/user/1730604250_1730520358_1726523112_20230712_130458.jpg', '', 'Texas', NULL, 1, 'trial');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activation_codes`
--
ALTER TABLE `activation_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`category_name`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `unique_invoice_item` (`invoice_number`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`invoice_items_id`),
  ADD KEY `invoice_id_idx` (`invoice_id`);

--
-- Indexes for table `page_access`
--
ALTER TABLE `page_access`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `subscription_id` (`subscription_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `products_ibfk_1` (`category_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`reports_id`);

--
-- Indexes for table `revenue_by_product`
--
ALTER TABLE `revenue_by_product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sales_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `sales_analytics`
--
ALTER TABLE `sales_analytics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staffs`
--
ALTER TABLE `staffs`
  ADD PRIMARY KEY (`staff_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_UNIQUE` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activation_codes`
--
ALTER TABLE `activation_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `invoice_items_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `page_access`
--
ALTER TABLE `page_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `reports_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `revenue_by_product`
--
ALTER TABLE `revenue_by_product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sales_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `sales_analytics`
--
ALTER TABLE `sales_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `staffs`
--
ALTER TABLE `staffs`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`);

--
-- Constraints for table `revenue_by_product`
--
ALTER TABLE `revenue_by_product`
  ADD CONSTRAINT `revenue_by_product_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`reports_id`);

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

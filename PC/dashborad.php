<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require '../config/config.php';
date_default_timezone_set('Asia/Kolkata');

// Redirect if not logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Get logged-in user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'] ?? 1; // Default to firm_id 1 if not set

// Fetch admin and firm name
$query = "SELECT Firm_Users.Username, Firm.FirmName 
          FROM Firm_Users 
          JOIN Firm ON Firm_Users.FirmID = Firm.id 
          WHERE Firm_Users.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$admin_name = $user['Username'] ?? 'Admin';
$firm_name = $user['FirmName'] ?? 'Gupta Jewellers Pvt Ltd';

// Handle date range
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 month'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Validate and sanitize date range
$start_date = date('Y-m-d', strtotime($start_date));
$end_date = date('Y-m-d', strtotime($end_date));

// Initialize empty data arrays for JavaScript
$totals = [];
$stock = [];
$rates = [];
$recent_transactions = [];
$top_customers = [];
$upcoming_birthdays = [];
$inventory_summary = [];
$sales_change = 0;

// Chart data placeholders
$months_json = json_encode([]);
$sales_json = json_encode([]);
$purchases_json = json_encode([]);
$expenses_json = json_encode([]);
$manufacturing_json = json_encode([]);
$transaction_types_json = json_encode([]);
$transaction_amounts_json = json_encode([]);
$material_types_json = json_encode([]);
$stock_levels_json = json_encode([]);
$minimum_levels_json = json_encode([]);
$jewelry_types_json = json_encode([]);
$jewelry_counts_json = json_encode([]);

?>


<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($firm_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="../css/dashboard.css" rel="stylesheet">
   <style>
       
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
        }
        
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 16rem;
            background-color: #1e293b;
            color: white;
            z-index: 40;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        /* Card styles */
        .stat-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
            overflow: hidden;
        }
        
        .stat-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
        
        .stat-header {
            padding: 1.25rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-body {
            padding: 1.25rem;
        }
        
        .stat-title {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        
        .stat-change {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background-color: #f9fafb;
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
            color: #374151;
        }
        
        .data-table tr:hover {
            background-color: #f9fafb;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-green {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-red {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .badge-blue {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .badge-amber {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-purple {
            background-color: #ede9fe;
            color: #5b21b6;
        }
        
        .badge-gray {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        
        .date-picker {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            color: #374151;
        }
        
        .date-picker input {
            border: none;
            outline: none;
            padding: 0.25rem;
            width: 7rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: #f59e0b;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #d97706;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #e5e7eb;
            color: #4b5563;
        }
        
        .btn-outline:hover {
            background-color: #f9fafb;
        }
        
        .progress-bar {
            height: 0.5rem;
            border-radius: 9999px;
            background-color: #e5e7eb;
            overflow: hidden;
        }
        
        .progress-value {
            height: 100%;
            border-radius: 9999px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
    </style>

</head>
<body class="relative">
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center hidden">
        <div class="loading-dots flex space-x-2">
            <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
            <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
            <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>

    <!-- Add Mobile Toggle Button -->
    <button id="mobile-toggle" class="mobile-toggle">
        <i class="ri-menu-line text-xl"></i>
    </button>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar">
        <!-- Fixed Logo Section -->
        <div class="logo-section">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="ri-vip-diamond-fill text-white text-xl"></i>
                </div>
                <div class="flex flex-col">
                    <span class="logo-text"><?php echo htmlspecialchars($firm_name); ?></span>
                    <span class="text-xs text-gray-400">Jewelry Store Management</span>
                </div>
            </div>
            <button id="collapse-toggle" class="text-gray-400 hover:text-white transition-colors">
                <i class="ri-arrow-left-s-line text-xl"></i>
            </button>
        </div>

        <!-- Scrollable Navigation -->
        <nav class="sidebar-nav">
            <!-- Dashboard Section -->
            <div class="menu-category">Dashboard</div>
            <a href="dashborad.php" class="menu-item active">
                <i class="ri-dashboard-line menu-icon"></i>
                <span class="menu-text">Overview</span>
                <div class="menu-tooltip">Store Overview</div>
            </a>

            <!-- Inventory Management Section -->
            <div class="menu-category">Inventory</div>
            <a href="add-stock.php" class="menu-item">
                <i class="ri-archive-line menu-icon"></i>
                <span class="menu-text">Add Stock</span>
                <div class="menu-tooltip">Manage Store Stock</div>
            </a>
            <a href="add-product.php" class="menu-item">
                <i class="ri-add-circle-line menu-icon"></i>
                <span class="menu-text">Add New Product</span>
                <div class="menu-tooltip">Add Jewelry to Stock</div>
            </a>
            <a href="inventory_reports.php" class="menu-item">
                <i class="ri-file-chart-line menu-icon"></i>
                <span class="menu-text">Stock Reports</span>
                <div class="menu-tooltip">View Stock Reports</div>
            </a>

            <!-- Sales Management Section -->
            <div class="menu-category">Sales</div>
            <a href="sell.php" class="menu-item">
                <i class="ri-shopping-cart-line menu-icon"></i>
                <span class="menu-text">Sale</span>
                <div class="menu-tooltip">Manage Sales </div>
            </a>
            <a href="sales_reports.php" class="menu-item">
                <i class="ri-file-chart-line menu-icon"></i>
                <span class="menu-text">Sales Reports</span>
                <div class="menu-tooltip">View Sales Reports</div>
            </a>

            <!-- Accounting Section -->
            <div class="menu-category">Accounting</div>
            <a href="accounts.php" class="menu-item">
                <i class="ri-wallet-line menu-icon"></i>
                <span class="menu-text">Account Overview</span>
                <div class="menu-tooltip">Track Store Accounts</div>
            </a>
            <a href="transactions.php" class="menu-item">
                <i class="ri-money-dollar-circle-line menu-icon"></i>
                <span class="menu-text">Transactions</span>
                <div class="menu-tooltip">View Store Transactions</div>
            </a>
            <a href="expense_reports.php" class="menu-item">
                <i class="ri-file-chart-line menu-icon"></i>
                <span class="menu-text">Expenses</span>
                <div class="menu-tooltip">View Expenses</div>
            </a>

            <!-- Customer Management Section -->
            <div class="menu-category">Customers</div>
            <a href="customer_list.php" class="menu-item">
                <i class="ri-group-line menu-icon"></i>
                <span class="menu-text">Customer List</span>
                <div class="menu-tooltip">Manage Customer Details</div>
            </a>
            <a href="customer_orders.php" class="menu-item">
                <i class="ri-file-list-line menu-icon"></i>
                <span class="menu-text">Customer Orders</span>
                <div class="menu-tooltip">View Customer Orders</div>
            </a>

            <!-- Reports Section -->
            <div class="menu-category">Reports</div>
            <a href="daily_reports.php" class="menu-item">
                <i class="ri-calendar-check-line menu-icon"></i>
                <span class="menu-text">Daily Reports</span>
                <div class="menu-tooltip">View Daily Reports</div>
            </a>
            <a href="monthly_reports.php" class="menu-item">
                <i class="ri-calendar-line menu-icon"></i>
                <span class="menu-text">Monthly Reports</span>
                <div class="menu-tooltip">View Monthly Reports</div>
            </a>

            <!-- Settings Section -->
            <div class="menu-category">Settings</div>
            <a href="settings.php" class="menu-item">
                <i class="ri-settings-3-line menu-icon"></i>
                <span class="menu-text">Store Settings</span>
                <div class="menu-tooltip">Manage Store Settings</div>
            </a>
            <a href="user_management.php" class="menu-item">
                <i class="ri-user-line menu-icon"></i>
                <span class="menu-text">User Management</span>
                <div class="menu-tooltip">Manage Store Users</div>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main id="main-content" class="transition-all duration-300 lg:ml-64">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="flex flex-col md:flex-row md:items-center justify-between p-4 gap-4">
                
                <!-- Left Section -->
                <div class="flex items-center gap-4">
                    <button id="sidebar-toggle" class="p-2 hover:bg-gray-100 rounded-xl lg:hidden">
                        <i class="ri-menu-line text-xl"></i>
                    </button>
                    
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <div id="logoToggle" class="flex-shrink-0 cursor-pointer">
                                <img src="../uploads/logo.png" alt="Logo" class="h-14 w-auto">
                            </div>
                        </div>
                        <div class="text-gray-600">
                            <span class="text-sm font-semibold"><?php echo htmlspecialchars($firm_name); ?></span>
                        </div>
                        <div class="relative flex-1 md:w-96">
                            <input type="text" 
                                   placeholder="Search products, orders, customers..." 
                                   class="header-search w-full pl-12 pr-4 py-2.5 text-sm focus:outline-none">
                            <i class="ri-search-line absolute left-4 top-3 text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <!-- Right Section -->
                <div class="flex items-center gap-4">
                    <!-- Notifications -->
                    <div class="relative">
                        <button id="notifications-toggle" class="p-2 hover:bg-gray-100 rounded-xl relative">
                            <i class="ri-notification-3-line text-xl"></i>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>
                        <!-- Notifications Dropdown -->
                        <div id="notifications-dropdown" class="notification-dropdown">
                            <div class="p-4 border-b">
                                <h3 class="font-semibold">Notifications</h3>
                            </div>
                            <div class="max-h-[400px] overflow-y-auto">
                                <div class="p-4 border-b hover:bg-gray-50">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                            <i class="ri-shopping-bag-line text-green-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">New order received</p>
                                            <p class="text-xs text-gray-500 mt-1">Order #45678 needs processing</p>
                                            <p class="text-xs text-gray-400 mt-1">5 minutes ago</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-4 border-b hover:bg-gray-50">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                                            <i class="ri-alert-line text-amber-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">Low stock alert</p>
                                            <p class="text-xs text-gray-500 mt-1">Diamond rings (2mm) running low</p>
                                            <p class="text-xs text-gray-400 mt-1">1 hour ago</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 text-center">
                                <button class="text-sm text-amber-500 hover:text-amber-600">View All Notifications</button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <button class="p-2 hover:bg-gray-100 rounded-xl">
                        <i class="ri-add-circle-line text-xl"></i>
                    </button>

                    <!-- Profile -->
                    <div class="relative group">
                        <button class="flex items-center gap-3 p-2 hover:bg-gray-100 rounded-xl" 
                                onclick="toggleProfileMenu(event)">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center text-white font-semibold">
                                <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                            </div>
                            <div class="hidden md:block text-left">
                                <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($admin_name); ?></p>
                                <p class="text-xs text-gray-500">Administrator</p>
                            </div>
                            <i class="ri-arrow-down-s-line text-gray-400"></i>
                        </button>

                        <!-- Enhanced Profile Dropdown Menu -->
                        <div id="profileMenu" 
                             class="hidden absolute right-0 mt-2 w-56 rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                            <div class="py-1">
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($admin_name); ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($firm_name); ?></p>
                                </div>
                                
                                <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="ri-user-line w-5 h-5 mr-3 text-gray-400"></i>
                                    My Profile
                                </a>
                                
                                <?php if ($_SESSION['role'] === 'SuperAdmin'): ?>
                                <a href="profile_management.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="ri-settings-4-line w-5 h-5 mr-3 text-gray-400"></i>
                                    Profile Management
                                </a>
                                <?php endif; ?>
                                
                                <div class="border-t border-gray-100 my-1"></div>
                                
                                <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <i class="ri-logout-box-line w-5 h-5 mr-3 text-red-500"></i>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Dashboard Content -->
       <!-- Main Dashboard Content -->
            <div class="p-4 md:p-6 space-y-6">
                <!-- Dashboard Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Analytics Dashboard</h1>
                        <p class="text-gray-600 mt-1">Track your business performance</p>
                    </div>
                    <form class="flex gap-4 items-center" method="GET">
                        <div class="date-picker">
                            <i class="ri-calendar-line text-gray-400"></i>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <span class="text-gray-400">to</span>
                        <div class="date-picker">
                            <i class="ri-calendar-line text-gray-400"></i>
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            Apply Filter
                        </button>
                    </form>
                </div>

                <!-- Stats Cards -->
            <!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Sales Card -->
    <div class="rounded-lg bg-gradient-to-br from-green-50 to-green-100 shadow-sm p-4 border border-green-200">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center">
                <div class="w-8 h-8 flex items-center justify-center rounded-md bg-green-200 text-green-700">
                    <i class="ri-shopping-cart-line text-lg"></i>
                </div>
                <span class="font-medium text-green-800 text-sm ml-2">Sales</span>
            </div>
            <div id="salesChange" class="text-xs font-medium px-2 py-0.5 rounded-full bg-green-200 text-green-700">
                <i class="ri-arrow-up-line text-xs"></i>
                <span>0%</span>
            </div>
        </div>
        <div class="mt-1">
            <div id="totalSales" class="font-bold text-xl text-gray-800">₹0</div>
            <div id="salesWeight" class="text-sm text-gray-500 mt-1">Total Weight: 0g</div>
            <div class="text-xs text-gray-400"><?php echo date('d M', strtotime($start_date)); ?> - <?php echo date('d M', strtotime($end_date)); ?></div>
        </div>
    </div>

    <!-- Total Revenue Card -->
    <div class="rounded-lg bg-gradient-to-br from-yellow-50 to-yellow-100 shadow-sm p-4 border border-yellow-200">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center">
                <div class="w-8 h-8 flex items-center justify-center rounded-md bg-yellow-200 text-yellow-700">
                    <i class="ri-bank-card-line text-lg"></i>
                </div>
                <span class="font-medium text-yellow-800 text-sm ml-2">Total Revenue</span>
            </div>
        </div>
        <div class="mt-1">
            <div id="totalRevenue" class="font-bold text-xl text-gray-800">₹0</div>
            <div class="text-sm text-gray-500 mt-1">
                <span class="inline-flex items-center mr-2"><i class="ri-bank-card-line mr-1 text-yellow-600"></i>UPI: <span id="upiIn">₹0</span></span>
                <span class="inline-flex items-center mr-2"><i class="ri-bank-line mr-1 text-yellow-600"></i>BANK: <span id="bankIn">₹0</span></span>
                <span class="inline-flex items-center"><i class="ri-mastercard-line mr-1 text-yellow-600"></i>CARD: <span id="cardIn">₹0</span></span>
            </div>
        </div>
    </div>

    <!-- Total Due Card -->
    <div class="rounded-lg bg-gradient-to-br from-orange-50 to-orange-100 shadow-sm p-4 border border-orange-200">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center">
                <div class="w-8 h-8 flex items-center justify-center rounded-md bg-orange-200 text-orange-700">
                    <i class="ri-time-line text-lg"></i>
                </div>
                <span class="font-medium text-orange-800 text-sm ml-2">Total Due</span>
            </div>
        </div>
        <div class="mt-1">
            <div id="totalDue" class="font-bold text-xl text-gray-800">₹0</div>
            <div class="text-sm text-gray-500 mt-1">
                <span class="inline-flex items-center">
                    <i class="ri-file-list-3-line mr-1 text-orange-600"></i>Pending Invoices
                </span>
            </div>
        </div>
    </div>

    <!-- Items Sold Card -->
    <div class="rounded-lg bg-gradient-to-br from-indigo-50 to-indigo-100 shadow-sm p-4 border border-indigo-200">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center">
                <div class="w-8 h-8 flex items-center justify-center rounded-md bg-indigo-200 text-indigo-700">
                    <i class="ri-archive-2-line text-lg"></i>
                </div>
                <span class="font-medium text-indigo-800 text-sm ml-2">Items Sold</span>
            </div>
        </div>
        <div class="mt-1">
            <div id="itemsSold" class="font-bold text-xl text-gray-800">0</div>
            <div id="itemsSoldWeight" class="text-sm text-gray-500 mt-1">Total Weight: 0g</div>
        </div>
    </div>

    <!-- Items Added Card -->
    <div class="rounded-lg bg-gradient-to-br from-pink-50 to-pink-100 shadow-sm p-4 border border-pink-200">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center">
                <div class="w-8 h-8 flex items-center justify-center rounded-md bg-pink-200 text-pink-700">
                    <i class="ri-money-rupee-circle-line text-lg"></i>
                </div>
                <span class="font-medium text-pink-800 text-sm ml-2">Items Added</span>
            </div>
        </div>
        <div class="mt-1">
            <div id="itemsAdded" class="font-bold text-xl text-gray-800">0</div>
            <div id="itemsAddedWeight" class="text-sm text-gray-500 mt-1">Total Weight: 0g</div>
        </div>
    </div>
</div>
                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Sales & Purchases Chart -->
                    <div class="stat-card p-6">
                        <h2 class="text-lg font-semibold mb-4">Sales & Purchases Trend</h2>
                        <div class="chart-container">
                            <canvas id="salesPurchasesChart"></canvas>
                        </div>
                    </div>

                    <!-- Transaction Types Chart -->
                    <div class="stat-card p-6">
                        <h2 class="text-lg font-semibold mb-4">Transaction Distribution</h2>
                        <div class="chart-container">
                            <canvas id="transactionTypesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Inventory & Jewelry Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Inventory Chart -->
                    <div class="stat-card p-6">
                        <h2 class="text-lg font-semibold mb-4">Inventory Levels</h2>
                        <div class="chart-container">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                    </div>

                    <!-- Jewelry Types Chart -->
                    <div class="stat-card p-6">
                        <h2 class="text-lg font-semibold mb-4">Jewelry Types</h2>
                        <div class="chart-container">
                            <canvas id="jewelryChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions & Top Customers -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Transactions -->
                    <div class="stat-card p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold">Recent Transactions</h2>
                            <a href="transactions.php" class="text-amber-500 hover:text-amber-600 text-sm font-medium">View All</a>
                        </div>
                        <div class="overflow-x-auto" style="max-height:320px; overflow-y:auto;">
                            <table class="data-table" style="table-layout:fixed; width:100%;">
                                <thead>
                                    <tr>
                                        <th style="width:13%">Payment Type</th>
                                        <th style="width:13%">Amount</th>
                                        <th style="width:13%">Transaction Type</th>
                                       
                                        <th style="width:15%">Date</th>
                                        <th style="width:16%">Details</th>
                                        <th style="width:15%">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody id="recentTransactionsBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Customers -->
                    <div class="stat-card p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold">Top Customers</h2>
                            <a href="customer_list.php" class="text-amber-500 hover:text-amber-600 text-sm font-medium">View All</a>
                        </div>
                        <div class="overflow-x-auto" style="max-height:320px; overflow-y:auto;">
                            <table class="data-table" style="table-layout:fixed; width:100%;">
                                <thead>
                                    <tr>
                                        <th style="width:30%">Name</th>
                                        <th style="width:25%">Mobile</th>
                                        <th style="width:20%">Total Due</th>
                                        <th style="width:25%">Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody id="topCustomersBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Birthdays & Inventory Status -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Upcoming Birthdays -->
                    <div class="stat-card p-6">
                        <h2 class="text-lg font-semibold mb-4">Upcoming Customer Birthdays</h2>
                        <div id="upcomingBirthdaysContainer" class="space-y-4">
                            <div class="text-center py-8 text-gray-500">
                                Loading birthday data...
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Status -->
                    <div class="stat-card p-6">
                        <h2 class="text-lg font-semibold mb-4">Inventory Status (Gross Weight by Purity)</h2>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Purity</th>
                                        <th>Gross Weight (g)</th>
                                    </tr>
                                </thead>
                                <tbody id="inventoryStatusBody">
                                    <tr>
                                        <td colspan="2" class="text-center py-4 text-gray-500">Loading inventory data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            <h3 class="font-semibold text-md mb-2">Stock Summary</h3>
                            <div id="stockSummary" class="text-gray-700 text-sm">Loading...</div>
                        </div>
                        <div class="mt-4">
                            <h3 class="font-semibold text-md mb-2">Breakdown by Jewelry Type & Purity</h3>
                            <div class="overflow-x-auto">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Jewelry Type</th>
                                            <th>Purity</th>
                                            <th>Count</th>
                                            <th>Gross Weight (g)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="stockBreakdownBody">
                                        <tr><td colspan="4" class="text-center py-4 text-gray-500">Loading breakdown...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Elements
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const mobileToggle = document.getElementById('mobile-toggle');
            const collapseToggle = document.getElementById('collapse-toggle');
            const logoToggle = document.getElementById('logoToggle');
            let sidebarState = 'full'; // 'full' | 'collapsed' | 'hidden'

            // --- Desktop state management ---
            function updateSidebarState(newState) {
                if (window.innerWidth <= 768) return; // skip on mobile

                // reset all
                sidebar.classList.remove('collapsed', 'hidden');
                mainContent.classList.remove('collapsed', 'full');
                sidebarOverlay.classList.remove('show');

                // apply new
                switch (newState) {
                    case 'full':
                        collapseToggle.innerHTML = '<i class="ri-arrow-left-s-line text-xl"></i>';
                        break;

                    case 'collapsed':
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('collapsed');
                        collapseToggle.innerHTML = '<i class="ri-arrow-right-s-line text-xl"></i>';
                        break;

                    case 'hidden':
                        sidebar.classList.add('hidden');
                        mainContent.classList.add('full');
                        collapseToggle.innerHTML = '<i class="ri-menu-line text-xl"></i>';
                        break;
                }

                sidebarState = newState;
            }

            function toggleSidebar() {
                if (window.innerWidth <= 768) {
                    // On mobile, slide in/out
                    toggleMobileSidebar();
                } else {
                    // On desktop, cycle states
                    const states = ['full', 'collapsed', 'hidden'];
                    const idx = states.indexOf(sidebarState);
                    const next = states[(idx + 1) % states.length];
                    updateSidebarState(next);
                }
            }

            // --- Mobile slide-in/out ---
            function toggleMobileSidebar() {
                sidebar.classList.toggle('sidebar-expanded');
                sidebarOverlay.classList.toggle('show');
                document.body.classList.toggle('overflow-hidden');

                // swap hamburger/close icon
                const icon = sidebar.classList.contains('sidebar-expanded')
                    ? 'ri-close-line'
                    : 'ri-menu-line';
                mobileToggle.innerHTML = `<i class="${icon} text-xl"></i>`;
            }

            // --- Event listeners ---
            mobileToggle?.addEventListener('click', toggleMobileSidebar);
            sidebarOverlay?.addEventListener('click', toggleMobileSidebar);

            collapseToggle?.addEventListener('click', toggleSidebar);
            logoToggle?.addEventListener('click', toggleSidebar);

            // close mobile sidebar when tapping outside
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 &&
                    sidebar.classList.contains('sidebar-expanded') &&
                    !sidebar.contains(e.target) &&
                    !mobileToggle.contains(e.target)
                ) {
                    toggleMobileSidebar();
                }
            });

            // restore appropriate state on resize
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    // leave desktop in last known state
                    sidebar.classList.remove('sidebar-expanded');
                    sidebarOverlay.classList.remove('show');
                    document.body.classList.remove('overflow-hidden');
                    mobileToggle.innerHTML = '<i class="ri-menu-line text-xl"></i>';
                    updateSidebarState(sidebarState);
                } else {
                    // reset desktop classes for mobile
                    sidebar.classList.remove('collapsed', 'hidden');
                    mainContent.classList.remove('collapsed', 'full');
                }
            });

            // keyboard shortcut (Ctrl+B)
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.key === 'b') toggleSidebar();
            });

            // initialize
            updateSidebarState('full');

            // Load Dashboard Data
            loadDashboardData();

            // Notifications Toggle
            const notificationsToggle = document.getElementById('notifications-toggle');
            const notificationsDropdown = document.getElementById('notifications-dropdown');

            notificationsToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                notificationsDropdown.classList.toggle('show');
            });

            document.addEventListener('click', (e) => {
                if (!notificationsDropdown.contains(e.target)) {
                    notificationsDropdown.classList.remove('show');
                }
            });

            function toggleProfileMenu(event) {
                event.stopPropagation(); // Prevent event bubbling
                const menu = document.getElementById('profileMenu');
                const isHidden = menu.classList.contains('hidden');
                
                // Close all other dropdowns first
                document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
                    dropdown.classList.add('hidden');
                });
                
                // Toggle current menu
                menu.classList.toggle('hidden');
                
                // Add animation classes if opening
                if (isHidden) {
                    menu.classList.add('animate-fadeIn');
                }
            }

            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                const menu = document.getElementById('profileMenu');
                const profileButton = event.target.closest('.group button');
                
                if (!profileButton && !menu.contains(event.target)) {
                    menu.classList.add('hidden');
                }
            });

            // Prevent menu close when clicking inside
            document.getElementById('profileMenu').addEventListener('click', function(event) {
                event.stopPropagation();
            });

            // Make toggleProfileMenu available globally
            window.toggleProfileMenu = toggleProfileMenu;

            // Dashboard Data Loading Function
            async function loadDashboardData() {
                try {
                    const startDate = '<?php echo $start_date; ?>';
                    const endDate = '<?php echo $end_date; ?>';
                    // Use the new dashboard API endpoint
                    const response = await fetch(`API/dashboard_data.php?start_date=${startDate}&end_date=${endDate}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        updateDashboardStats(result.data);
                        updateRecentTransactions(result.data);
                        updateCharts(result.data);
                        updateInventoryStatus(result.data);
                        updateTopCustomers(result.data);
                    } else {
                        console.error('Failed to load dashboard data:', result.message);
                    }
                } catch (error) {
                    console.error('Error loading dashboard data:', error);
                }
            }

            // Update Dashboard Statistics
            function updateDashboardStats(data) {
                const summary = data.summary;
                const balances = data.balances;

                // Helper to safely format numbers
                function safeFixed(val, digits = 2) {
                    const num = Number(val);
                    return isNaN(num) ? '0.00' : num.toFixed(digits);
                }
                function safeInt(val) {
                    const num = Number(val);
                    return isNaN(num) ? '0' : num.toLocaleString('en-IN');
                }

                // Update Sales Card
                document.getElementById('totalSales').textContent = `₹${safeInt(summary.total_sales)}`;
                document.getElementById('salesWeight').textContent = `Total Weight: ${safeFixed(summary.items_sold_weight)}g`;

                // Update Revenue Card
                document.getElementById('totalRevenue').textContent = `₹${safeInt(summary.total_revenue)}`;
                document.getElementById('upiIn').textContent = `₹${safeInt(balances.upi?.in)}`;
                document.getElementById('bankIn').textContent = `₹${safeInt(balances.bank?.in)}`;
                document.getElementById('cardIn').textContent = `₹${safeInt(balances.bank?.in)}`; // If you have a separate card field, use it here

                // Update Due Card
                document.getElementById('totalDue').textContent = `₹${safeInt(summary.total_sales_due)}`;

                // Update Items Sold Card
                document.getElementById('itemsSold').textContent = safeInt(summary.items_sold_count);
                document.getElementById('itemsSoldWeight').textContent = `Total Weight: ${safeFixed(summary.items_sold_weight)}g`;

                // Update Items Added Card
                document.getElementById('itemsAdded').textContent = safeInt(summary.items_added_count);
                document.getElementById('itemsAddedWeight').textContent = `Total Weight: ${safeFixed(summary.items_added_weight)}g`;
            }

            // Update Recent Transactions
            function updateRecentTransactions(data) {
                const transactions = data.recent_transactions || [];
                const tbody = document.getElementById('recentTransactionsBody');
                if (!transactions.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-gray-500">No recent transactions found</td></tr>';
                    return;
                }
                tbody.innerHTML = transactions.map(tx => {
                    let details = '-';
                    if (tx.party_type === 'customer' && tx.party_id) { 
                        details = tx.FirstName || 'Customer';
                    } else if (tx.sale_id && tx.sale_id !== '0') {
                        details = tx.invoice_no ? 'Bill #' + tx.invoice_no : 'Bill #' + tx.sale_id;
                    } else if (tx.reference_no) {
                        details = tx.reference_no;
                    }
                    const remarks = tx.remarks ? `<span title="${tx.remarks}">${tx.remarks.length > 20 ? tx.remarks.slice(0, 20) + '...' : tx.remarks}</span>` : '-';
                    return `<tr style="font-size:13px; line-height:1.2;">
                        <td style="padding:6px 4px;">${tx.payment_type || '-'}</td>
                        <td style="padding:6px 4px;">₹${Number(tx.amount).toLocaleString('en-IN')}</td>
                        <td style="padding:6px 4px;">${tx.transctions_type || '-'}</td>
                       
                        <td style="padding:6px 4px;">${new Date(tx.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                        <td style="padding:6px 4px;">${details}</td>
                        <td style="padding:6px 4px;">${remarks}</td>
                    </tr>`;
                }).join('');
            }

            // Initialize Charts with Dynamic Data
            function updateCharts(data) {
                // Sales & Purchases Chart
                const salesPurchasesCtx = document.getElementById('salesPurchasesChart').getContext('2d');
                const salesData = data.charts?.sales_over_time || [];
                new Chart(salesPurchasesCtx, {
                    type: 'line',
                    data: {
                        labels: salesData.map(item => new Date(item.sale_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })),
                        datasets: [
                            {
                                label: 'Sales',
                                data: salesData.map(item => Number(item.daily_sales)),
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) label += ': ';
                                        if (context.parsed.y !== null) {
                                            label += new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) { return '₹' + value.toLocaleString('en-IN'); }
                                }
                            }
                        }
                    }
                });

                // Transaction Types Chart
                const transactionTypesCtx = document.getElementById('transactionTypesChart').getContext('2d');
                const transactionTypes = data.charts?.transaction_types || [];
                new Chart(transactionTypesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: transactionTypes.map(t => t.payment_type),
                        datasets: [{
                            data: transactionTypes.map(t => Number(t.total)),
                            backgroundColor: [
                                '#10b981', '#3b82f6', '#ef4444', '#f59e0b', '#8b5cf6', '#6b7280', '#f97316', '#14b8a6'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right' },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) label += ': ';
                                        if (context.parsed !== null) {
                                            label += new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(context.parsed);
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });

                // Inventory Chart - Remaining Stock by Purity
                const inventoryByPurity = data.inventory_by_purity || [];
                const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');
                const palette = [
                    '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ec4899', '#6b7280', '#f97316', '#14b8a6', '#ef4444', '#6366f1', '#eab308', '#22d3ee'
                ];
                new Chart(inventoryCtx, {
                    type: 'bar',
                    data: {
                        labels: inventoryByPurity.map(row => row.purity + 'K'),
                        datasets: [{
                            label: 'Gross Weight (g)',
                            data: inventoryByPurity.map(row => Number(row.total_weight)),
                            backgroundColor: inventoryByPurity.map((_, i) => palette[i % palette.length]),
                            borderColor: inventoryByPurity.map((_, i) => palette[i % palette.length]),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Weight (g)' }
                            }
                        }
                    }
                });

                // Jewelry Types Chart
                const jewelryTypes = data.charts?.jewelry_types || [];
                const jewelryCtx = document.getElementById('jewelryChart').getContext('2d');
                new Chart(jewelryCtx, {
                    type: 'pie',
                    data: {
                        labels: jewelryTypes.map(j => j.label),
                        datasets: [{
                            data: jewelryTypes.map(j => Number(j.value)),
                            backgroundColor: [
                                '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ec4899', '#6b7280', '#f97316', '#14b8a6'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right' }
                        }
                    }
                });
            }

            function updateInventoryStatus(data) {
                // Inventory by Purity Table
                const tbody = document.getElementById('inventoryStatusBody');
                const purityRows = (data.inventory_by_purity || []).map(row =>
                    `<tr><td>${row.purity}K</td><td>${Number(row.total_weight).toFixed(2)}</td></tr>`
                ).join('');
                tbody.innerHTML = purityRows || '<tr><td colspan="2" class="text-center py-4 text-gray-500">No data</td></tr>';

                // Stock Summary
                const summary = data.stock_summary || {};
                document.getElementById('stockSummary').innerHTML =
                    `Total Items: <b>${summary.total_count || 0}</b> &nbsp; | &nbsp; Total Gross Weight: <b>${Number(summary.total_gross_weight || 0).toFixed(2)}g</b>`;

                // Breakdown Table
                const breakdown = data.stock_breakdown || {};
                const breakdownRows = Object.entries(breakdown).flatMap(([type, purities]) =>
                    Object.entries(purities).map(([purity, val]) =>
                        `<tr><td>${type}</td><td>${purity}K</td><td>${val.count}</td><td>${Number(val.total_weight).toFixed(2)}</td></tr>`
                    )
                ).join('');
                document.getElementById('stockBreakdownBody').innerHTML = breakdownRows || '<tr><td colspan="4" class="text-center py-4 text-gray-500">No data</td></tr>';
            }

            function updateTopCustomers(data) {
                const customers = data.top_customers || [];
                const tbody = document.getElementById('topCustomersBody');
                if (!customers.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No customers found</td></tr>';
                    return;
                }
                tbody.innerHTML = customers.map(cust => {
                    const name = `${cust.FirstName || ''} ${cust.LastName || ''}`.trim();
                    return `<tr style="font-size:13px; line-height:1.2;">
                        <td style="padding:6px 4px;">${name || '-'}</td>
                        <td style="padding:6px 4px;">${cust.mobile || '-'}</td>
                        <td style="padding:6px 4px; color:${cust.total_due > 0 ? '#b91c1c' : '#065f46'}; font-weight:600;">₹${Number(cust.total_due).toLocaleString('en-IN')}</td>
                        <td style="padding:6px 4px;">₹${Number(cust.total_sales).toLocaleString('en-IN')}</td>
                    </tr>`;
                }).join('');
            }
        });
    </script>
</body>
</html>

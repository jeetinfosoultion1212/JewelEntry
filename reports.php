<?php
session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];



// You can add subscription checks here if needed, similar to home.php
$hasFeatureAccess = true; // Assuming access for now

try {
    // Jewellery Stock by Purity (Available only)
    $jewelleryPurityQuery = "SELECT purity, COUNT(*) as item_count, SUM(gross_weight) as total_weight FROM jewellery_items WHERE firm_id = ? AND status = 'Available' GROUP BY purity ORDER BY purity DESC";
    $jewelleryPurityStmt = $conn->prepare($jewelleryPurityQuery);
    $jewelleryPurityStmt->bind_param("i", $firm_id);
    $jewelleryPurityStmt->execute();
    $jewelleryPurityResult = $jewelleryPurityStmt->get_result();
    $jewelleryPurityData = $jewelleryPurityResult->fetch_all(MYSQLI_ASSOC);
    $data['stock']['jewellery_purity'] = $jewelleryPurityData;

    // Inventory Metal by Purity
    $inventoryMetalPurityQuery = "SELECT purity, COUNT(*) as lot_count, SUM(remaining_stock) as total_weight FROM inventory_metals WHERE firm_id = ? GROUP BY purity ORDER BY purity DESC";
    $inventoryMetalPurityStmt = $conn->prepare($inventoryMetalPurityQuery);
    $inventoryMetalPurityStmt->bind_param("i", $firm_id);
    $inventoryMetalPurityStmt->execute();
    $inventoryMetalPurityResult = $inventoryMetalPurityStmt->get_result();
    $inventoryMetalPurityData = $inventoryMetalPurityResult->fetch_all(MYSQLI_ASSOC);
    $data['stock']['inventorymetal_purity'] = $inventoryMetalPurityData;

    // echo '<pre style="color:blue">Jewellery Data: '; print_r($data['stock']['jewellery_purity']); echo '</pre>';
    // echo '<pre style="color:blue">Inventory Metal Data: '; print_r($data['stock']['inventorymetal_purity']); echo '</pre>';
} catch (Exception $e) {
    error_log("Report data fetch error: " . $e->getMessage());
}

$reportData = $data;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Reports - JewelEntry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link rel="stylesheet" href="css/main.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .stat-card {
            flex: 0 0 auto;
            width: 105px;
            border-radius: 10px;
            padding: 5px 5px 5px 8px;
            background: white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06), 0 1px 1px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }
        
        .stat-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            transform: translateY(-1px) scale(1.01);
        }
        
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        .table-responsive { 
            overflow-x: auto; 
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .table-responsive table { 
            min-width: 100%; 
            font-size: 11px;
        }
        
        .tab-button {
            position: relative;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .tab-button.active {
            background: #4f46e5;
            color: white;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.3);
        }
        
        .tab-button:not(.active) {
            color: #6b7280;
            background: #f9fafb;
        }
        
        .header-glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .gradient-gold {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .gradient-purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        
        .bottom-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        .nav-btn {
            transition: all 0.2s ease;
        }
        
        .nav-btn:hover {
            transform: scale(1.05);
        }
        
        .compact-input {
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            background: white;
        }
        
        .status-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-partial { background: #e0e7ff; color: #3730a3; }
        
        .section-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.03);
            padding: 12px 10px;
        }
        
        .metric-value {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.1;
        }
        
        .metric-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-sub {
            font-size: 9px;
            font-weight: 500;
        }
        
        .icon-wrapper {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }
        
        .table-header {
            background: #f8fafc;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }
        
        .table-cell {
            padding: 8px 6px;
            font-size: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        @media (max-width: 375px) {
            .stat-card { width: 90px; padding: 6px; }
            .metric-value { font-size: 11px; }
            .table-responsive table { font-size: 9px; }
        }
        
        .scrollable-table-body {
            max-height: 260px;
            overflow-y: auto;
            display: block;
        }
        .scrollable-table-body tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .scrollable-table-body td, .scrollable-table-body th {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .scrollable-table-wrapper {
            max-height: 180px;
            overflow-y: auto;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #fff;
            padding: 0.1rem 0.1rem 0.2rem 0.1rem;
        }
        .scrollable-table-wrapper table {
            width: 100%;
            table-layout: auto;
            border-collapse: separate;
            border-spacing: 0;
        }
        .scrollable-table-wrapper th, .scrollable-table-wrapper td {
            max-width: 80px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 3px 4px;
            font-size: 10px;
            vertical-align: middle;
        }
        .scrollable-table-wrapper th {
            background: #e0e7ef;
            color: #374151;
            font-weight: 700;
            border-bottom: 1.5px solid #cbd5e1;
            text-align: left;
        }
        .scrollable-table-wrapper td {
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }
        .scrollable-table-wrapper tbody tr:nth-child(even) td {
            background: #f8fafc;
        }
        .scrollable-table-wrapper tr:hover td {
            background: #f3f4f6;
        }
        .scrollable-table-wrapper td.text-right, .scrollable-table-wrapper th.text-right {
            text-align: right;
        }
        .scrollable-table-wrapper td.text-center, .scrollable-table-wrapper th.text-center {
            text-align: center;
        }
        .scrollable-table-wrapper td, .scrollable-table-wrapper th {
            vertical-align: middle;
        }
        .scrollable-table-wrapper th {
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .card-list-row {
            background: #f9fafb;
            border-radius: 6px;
            margin-bottom: 5px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.02);
            padding: 5px 7px;
            display: flex;
            align-items: center;
            font-size: 11px;
            gap: 6px;
            min-width: 0;
        }
        .card-list-row .card-col {
            flex: 1 1 0;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .card-list-row .card-col.amount {
            text-align: right;
            font-weight: 600;
            color: #2563eb;
        }
        .card-list-row .card-col.status {
            text-align: center;
            min-width: 60px;
        }
        .card-list-row .card-col.date {
            color: #64748b;
            font-size: 11px;
            min-width: 70px;
        }
        .card-list-row .card-col.id {
            font-weight: 600;
            color: #a21caf;
            min-width: 40px;
        }
        .card-list-row .card-col.category {
            color: #f59e42;
            font-size: 11px;
            min-width: 60px;
        }
        .card-list-row .card-col.desc {
            color: #334155;
            font-size: 11px;
            min-width: 80px;
        }
        .card-list-row .card-col.paid-badge {
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-left: 6px;
        }
        .min-w-full { min-width: 100%; }
        .table-cell, .table-header th, .table-header td {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 4px 4px;
            font-size: 11px;
        }
        .table-header th {
            background: #f8fafc;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/header.php'; ?>

    <main class="px-4 pb-24 pt-4">
        <!-- Date Range Picker -->
        <div class="mb-4 section-card p-3">
            <div class="flex items-center justify-between">
                <label for="reportrange" class="metric-label text-gray-600">Date Range</label>
                <div id="reportrange" class="flex items-center cursor-pointer compact-input bg-gray-50 hover:bg-gray-100 transition-colors">
                    <i class="fas fa-calendar-alt text-gray-400 mr-2 text-xs"></i>
                    <span class="font-medium text-gray-800 text-xs"></span>
                    <i class="fa fa-caret-down text-gray-400 ml-2 text-xs"></i>
                </div>
            </div>
        </div>

        <!-- Compact Stats Grid -->
        <div class="flex space-x-3 overflow-x-auto pb-3 hide-scrollbar mb-4">
            <!-- Cash Balance -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <div class="icon-wrapper bg-green-100">
                        <i class="fas fa-money-bill-wave text-green-600 text-sm"></i>
                    </div>
                    <h3 class="metric-label text-gray-600">Cash</h3>
                </div>
                <p id="cashNet" class="metric-value text-gray-900 mb-1">₹0.00</p>
                <div class="flex justify-between">
                    <span class="text-green-600 flex items-center metric-sub">
                        <i class="fas fa-arrow-down text-xs mr-1"></i>
                        <span id="cashIn">₹0</span>
                    </span>
                    <span class="text-red-600 flex items-center metric-sub">
                        <i class="fas fa-arrow-up text-xs mr-1"></i>
                        <span id="cashOut">₹0</span>
                    </span>
                </div>
            </div>

            <!-- Bank Balance -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <div class="icon-wrapper bg-blue-100">
                        <i class="fas fa-university text-blue-600 text-sm"></i>
                    </div>
                    <h3 class="metric-label text-gray-600">Bank</h3>
                </div>
                <p id="bankNet" class="metric-value text-gray-900 mb-1">₹0.00</p>
                <div class="flex justify-between">
                    <span class="text-green-600 flex items-center metric-sub">
                        <i class="fas fa-arrow-down text-xs mr-1"></i>
                        <span id="bankIn">₹0</span>
                    </span>
                    <span class="text-red-600 flex items-center metric-sub">
                        <i class="fas fa-arrow-up text-xs mr-1"></i>
                        <span id="bankOut">₹0</span>
                    </span>
                </div>
            </div>

            <!-- UPI Balance -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <div class="icon-wrapper bg-purple-100">
                        <i class="fab fa-google-pay text-purple-600 text-sm"></i>
                    </div>
                    <h3 class="metric-label text-gray-600">UPI</h3>
                </div>
                <p id="upiNet" class="metric-value text-gray-900 mb-1">₹0.00</p>
                <div class="flex justify-between">
                    <span class="text-green-600 flex items-center metric-sub">
                        <i class="fas fa-arrow-down text-xs mr-1"></i>
                        <span id="upiIn">₹0</span>
                    </span>
                    <span class="text-red-600 flex items-center metric-sub">
                        <i class="fas fa-arrow-up text-xs mr-1"></i>
                        <span id="upiOut">₹0</span>
                    </span>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <div class="icon-wrapper bg-yellow-100">
                        <i class="fas fa-chart-line text-yellow-600 text-sm"></i>
                    </div>
                    <h3 class="metric-label text-gray-600">Revenue</h3>
                </div>
                <p id="totalRevenue" class="metric-value text-gray-900">₹0.00</p>
            </div>

            <!-- NEW: Total Sales Card -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <div class="icon-wrapper bg-pink-100">
                        <i class="fas fa-receipt text-pink-600 text-sm"></i>
                    </div>
                    <h3 class="metric-label text-gray-600">Total Sales</h3>
                </div>
                <p id="totalSales" class="metric-value text-gray-900 mb-1">₹0.00</p>
                <div class="flex justify-between">
                    <span class="text-green-600 flex items-center metric-sub">
                        <i class="fas fa-check-circle text-xs mr-1"></i>
                        <span id="totalSalesPaid">₹0</span>
                    </span>
                    <span class="text-red-600 flex items-center metric-sub">
                        <i class="fas fa-exclamation-circle text-xs mr-1"></i>
                        <span id="totalSalesDue">₹0</span>
                    </span>
                </div>
            </div>

            <!-- Items Sold -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <div class="icon-wrapper bg-orange-100">
                        <i class="fas fa-arrow-down text-orange-600 text-sm"></i>
                    </div>
                    <h3 class="metric-label text-gray-600">Sold</h3>
                </div>
                <p id="itemsSold" class="metric-value text-gray-900 mb-1">0</p>
                <div class="metric-sub text-orange-700">
                    <span id="itemsSoldWeight">0.00 g</span>
                </div>
            </div>

            <!-- Items Added -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <div class="icon-wrapper bg-indigo-100">
                        <i class="fas fa-arrow-up text-indigo-600 text-sm"></i>
                    </div>
                    <h3 class="metric-label text-gray-600">Added</h3>
                </div>
                <p id="itemsAdded" class="metric-value text-gray-900 mb-1">0</p>
                <div class="metric-sub text-indigo-700">
                    <span id="itemsAddedWeight">0.00 g</span>
                </div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <section class="section-card p-4">
            <!-- Tab Navigation -->
            <div class="flex space-x-2 mb-4 bg-gray-100 p-1 rounded-lg">
                <button data-tab="cashflow" class="tab-button active flex-1 text-center">
                    <i class="fas fa-money-bill-wave mr-2"></i>Cash Flow
                </button>
                <button data-tab="inventory" class="tab-button flex-1 text-center">
                    <i class="fas fa-boxes mr-2"></i>Inventory
                </button>
            </div>

            <!-- Tab Content -->
            <div class="space-y-4">
                <div id="cashflow-content" class="tab-content">
                    <!-- Income Section -->
                    <div class="mb-6">
                        <div class="flex items-center mb-3">
                            <div class="icon-wrapper bg-green-100 mr-3">
                                <i class="fas fa-plus text-green-600 text-sm"></i>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900">Income (Sales)</h3>
                        </div>
                        <div class="scrollable-table-wrapper">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="table-header">
                                        <th class="px-3 py-2 text-left">Invoice</th>
                                        <th class="px-3 py-2 text-left">Date</th>
                                        <th class="px-3 py-2 text-left">Customer</th>
                                        <th class="px-3 py-2 text-right">Amount</th>
                                        <th class="px-3 py-2 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="incomeTableBody" class="bg-white"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Expenses Section -->
                    <div>
                        <div class="flex items-center mb-3">
                            <div class="icon-wrapper bg-red-100 mr-3">
                                <i class="fas fa-minus text-red-600 text-sm"></i>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900">Expenses</h3>
                        </div>
                        <div class="scrollable-table-wrapper">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="table-header">
                                        <th class="px-3 py-2 text-left">Date</th>
                                        <th class="px-3 py-2 text-left">Category</th>
                                        <th class="px-3 py-2 text-left">Description</th>
                                        <th class="px-3 py-2 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="expenseTableBody" class="bg-white"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="inventory-content" class="tab-content hidden">
                    <!-- Stock In Section -->
                    <div class="mb-6">
                        <div class="flex items-center mb-3">
                            <div class="icon-wrapper bg-blue-100 mr-3">
                                <i class="fas fa-arrow-up text-blue-600 text-sm"></i>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900">Stock In (New Items)</h3>
                        </div>
                        <div class="scrollable-table-wrapper">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="table-header">
                                        <th class="px-3 py-2 text-left">Product ID</th>
                                        <th class="px-3 py-2 text-left">Item Name</th>
                                        <th class="px-3 py-2 text-left">Date Added</th>
                                        <th class="px-3 py-2 text-right">Gross Wt.</th>
                                        <th class="px-3 py-2 text-left">Supplier</th>
                                    </tr>
                                </thead>
                                <tbody id="stockInTableBody" class="bg-white"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Stock Out Section -->
                    <div>
                        <div class="flex items-center mb-3">
                            <div class="icon-wrapper bg-orange-100 mr-3">
                                <i class="fas fa-arrow-down text-orange-600 text-sm"></i>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900">Stock Out (Sold Items)</h3>
                        </div>
                        <div class="scrollable-table-wrapper">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="table-header">
                                        <th class="px-3 py-2 text-left">Product ID</th>
                                        <th class="px-3 py-2 text-left">Item Name</th>
                                        <th class="px-3 py-2 text-left">Date Sold</th>
                                        <th class="px-3 py-2 text-right">Gross Wt.</th>
                                        <th class="px-3 py-2 text-left">Invoice ID</th>
                                    </tr>
                                </thead>
                                <tbody id="stockOutTableBody" class="bg-white"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Jewellery Stock by Purity Chart -->
        <div class="bg-white rounded-lg p-4 shadow-sm mt-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-layer-group mr-2 text-yellow-500"></i>Jewellery Stock by Purity
            </h3>
            <div class="chart-container" style="height:220px;">
                <canvas id="jewelleryPurityChart"></canvas>
            </div>
            <div class="overflow-x-auto mt-2">
                <table class="min-w-full text-xs">
                    <thead><tr><th>Purity</th><th>Count</th><th>Total Weight (g)</th></tr></thead>
                    <tbody>
                    <?php foreach (($reportData['stock']['jewellery_purity'] ?? []) as $row): ?>
                        <tr class="border-b"><td><?php echo htmlspecialchars($row['purity']); ?></td><td><?php echo $row['item_count']; ?></td><td><?php echo number_format($row['total_weight'],2); ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($reportData['stock']['jewellery_purity'])): ?><tr><td colspan="3" class="text-center text-gray-400">No jewellery stock data</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Inventory Metal by Purity Chart -->
        <div class="bg-white rounded-lg p-4 shadow-sm mt-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-cubes mr-2 text-blue-500"></i>Inventory Metal by Purity
            </h3>
            <div class="chart-container" style="height:220px;">
                <canvas id="inventoryMetalPurityChart"></canvas>
            </div>
            <div class="overflow-x-auto mt-2">
                <table class="min-w-full text-xs">
                    <thead><tr><th>Purity</th><th>Count</th><th>Total Weight (g)</th></tr></thead>
                    <tbody>
                    <?php foreach (($reportData['stock']['inventorymetal_purity'] ?? []) as $row): ?>
                        <tr class="border-b"><td><?php echo htmlspecialchars($row['purity']); ?></td><td><?php echo $row['lot_count']; ?></td><td><?php echo number_format($row['total_weight'],2); ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($reportData['stock']['inventorymetal_purity'])): ?><tr><td colspan="3" class="text-center text-gray-400">No inventory metal data</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php include 'includes/bottom_nav.php'; ?>

    <script src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        // Enhanced tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and hide all content
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.add('hidden'));
                    
                    // Add active class to clicked button and show corresponding content
                    button.classList.add('active');
                    document.getElementById(tabId + '-content').classList.remove('hidden');
                });
            });
        });
    </script>
    <script src="js/reports.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Jewellery Stock by Purity Chart
        const jewelleryPurityData = <?php echo json_encode($reportData['stock']['jewellery_purity'] ?? []); ?>;
        if (jewelleryPurityData.length > 0) {
            const ctxJewellery = document.getElementById('jewelleryPurityChart').getContext('2d');
            new Chart(ctxJewellery, {
                type: 'bar',
                data: {
                    labels: jewelleryPurityData.map(row => row.purity + 'K'),
                    datasets: [
                        {
                            label: 'Count',
                            data: jewelleryPurityData.map(row => row.item_count),
                            backgroundColor: '#fbbf24',
                            yAxisID: 'y1'
                        },
                        {
                            label: 'Total Weight (g)',
                            data: jewelleryPurityData.map(row => row.total_weight),
                            backgroundColor: '#fde68a',
                            yAxisID: 'y2'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y1: { beginAtZero: true, position: 'left', title: { display: true, text: 'Count' } },
                        y2: { beginAtZero: true, position: 'right', title: { display: true, text: 'Weight (g)' }, grid: { drawOnChartArea: false } }
                    }
                }
            });
        }

        // Inventory Metal by Purity Chart
        const inventoryMetalPurityData = <?php echo json_encode($reportData['stock']['inventorymetal_purity'] ?? []); ?>;
        if (inventoryMetalPurityData.length > 0) {
            const ctxMetal = document.getElementById('inventoryMetalPurityChart').getContext('2d');
            new Chart(ctxMetal, {
                type: 'bar',
                data: {
                    labels: inventoryMetalPurityData.map(row => row.purity + 'K'),
                    datasets: [
                        {
                            label: 'Count',
                            data: inventoryMetalPurityData.map(row => row.lot_count),
                            backgroundColor: '#60a5fa',
                            yAxisID: 'y1'
                        },
                        {
                            label: 'Total Weight (g)',
                            data: inventoryMetalPurityData.map(row => row.total_weight),
                            backgroundColor: '#bfdbfe',
                            yAxisID: 'y2'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y1: { beginAtZero: true, position: 'left', title: { display: true, text: 'Count' } },
                        y2: { beginAtZero: true, position: 'right', title: { display: true, text: 'Weight (g)' }, grid: { drawOnChartArea: false } }
                    }
                }
            });
        }
    });
    </script>
</body>
</html>
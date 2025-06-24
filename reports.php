<?php
session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Get user details
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.City
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();

// Date range setup
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch comprehensive report data
function fetchReportData($conn, $firm_id, $start_date, $end_date) {
    $data = [
        'summary' => [],
        'cash_flow' => [],
        'inventory' => [],
        'balances' => [],
        'trends' => [],
        'top_items' => [],
        'customer_insights' => []
    ];

    try {
        // Summary Statistics
        $summaryQuery = "SELECT 
            COALESCE(SUM(js.grand_total), 0) as total_revenue,
            COUNT(DISTINCT js.id) as total_sales,
            COUNT(DISTINCT js.customer_id) as unique_customers,
            COALESCE(AVG(js.grand_total), 0) as avg_sale_value,
            COALESCE(SUM(jsi.gross_weight), 0) as total_weight_sold
            FROM jewellery_sales js 
            LEFT JOIN jewellery_sales_items jsi ON js.id = jsi.sale_id
            WHERE js.firm_id = ? AND DATE(js.created_at) BETWEEN ? AND ?";
        $summaryStmt = $conn->prepare($summaryQuery);
        $summaryStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $summaryStmt->execute();
        $data['summary'] = $summaryStmt->get_result()->fetch_assoc();

        // Payment Balances
        $balanceQuery = "SELECT 
            payment_type,
            transctions_type,
            SUM(amount) as total
            FROM jewellery_payments 
            WHERE Firm_id = ? AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY payment_type, transctions_type";
        $balanceStmt = $conn->prepare($balanceQuery);
        $balanceStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result();
        
        $balances = ['cash' => ['in' => 0, 'out' => 0], 'bank' => ['in' => 0, 'out' => 0], 'upi' => ['in' => 0, 'out' => 0]];
        while ($row = $balanceResult->fetch_assoc()) {
            $type = strtolower($row['payment_type']);
            $direction = $row['transctions_type'] === 'credit' ? 'in' : 'out';
            $amount = floatval($row['total']);
            
            if ($type === 'cash') $balances['cash'][$direction] += $amount;
            elseif ($type === 'upi') $balances['upi'][$direction] += $amount;
            elseif (in_array($type, ['bank', 'bank_transfer', 'card'])) $balances['bank'][$direction] += $amount;
        }
        $data['balances'] = $balances;

        // Recent Sales
        $salesQuery = "SELECT js.id, js.created_at, js.grand_total, js.payment_status,
                       CONCAT(c.FirstName, ' ', c.LastName) as customer_name,
                       COUNT(jsi.id) as item_count
                       FROM jewellery_sales js
                       LEFT JOIN customer c ON js.customer_id = c.id
                       LEFT JOIN jewellery_sales_items jsi ON js.id = jsi.sale_id
                       WHERE js.firm_id = ? AND DATE(js.created_at) BETWEEN ? AND ?
                       GROUP BY js.id
                       ORDER BY js.created_at DESC LIMIT 10";
        $salesStmt = $conn->prepare($salesQuery);
        $salesStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $salesStmt->execute();
        $salesResult = $salesStmt->get_result();
        $data['cash_flow']['recent_sales'] = $salesResult->fetch_all(MYSQLI_ASSOC);

        // Top Selling Items
        $topItemsQuery = "SELECT jsi.product_name, jsi.category,
                          COUNT(*) as sold_count,
                          SUM(jsi.gross_weight) as total_weight,
                          SUM(jsi.total_amount) as total_revenue
                          FROM jewellery_sales_items jsi
                          JOIN jewellery_sales js ON jsi.sale_id = js.id
                          WHERE js.firm_id = ? AND DATE(js.created_at) BETWEEN ? AND ?
                          GROUP BY jsi.product_name, jsi.category
                          ORDER BY sold_count DESC LIMIT 5";
        $topItemsStmt = $conn->prepare($topItemsQuery);
        $topItemsStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $topItemsStmt->execute();
        $data['top_items'] = $topItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Inventory Summary
        $inventoryQuery = "SELECT 
            COUNT(*) as total_items,
            SUM(gross_weight) as total_weight,
            SUM(selling_price) as total_value,
            AVG(selling_price) as avg_price
            FROM jewellery_items 
            WHERE firm_id = ? AND status = 'available'";
        $inventoryStmt = $conn->prepare($inventoryQuery);
        $inventoryStmt->bind_param("i", $firm_id);
        $inventoryStmt->execute();
        $data['inventory']['summary'] = $inventoryStmt->get_result()->fetch_assoc();

        // Daily Trends
        $trendsQuery = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as sales_count,
            SUM(grand_total) as daily_revenue
            FROM jewellery_sales 
            WHERE firm_id = ? AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
        $trendsStmt = $conn->prepare($trendsQuery);
        $trendsStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $trendsStmt->execute();
        $data['trends'] = $trendsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    } catch (Exception $e) {
        error_log("Report data fetch error: " . $e->getMessage());
    }

    return $data;
}

$reportData = fetchReportData($conn, $firm_id, $start_date, $end_date);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Reports Dashboard - <?php echo htmlspecialchars($userInfo['FirmName']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'primary': {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        .glass-effect { 
            background: rgba(255,255,255,0.95); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255,255,255,0.2); 
        }
        .gradient-primary { background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%); }
        .gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .gradient-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .compact-card { transition: all 0.2s ease; }
        .compact-card:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .tab-button { transition: all 0.3s ease; }
        .tab-button.active { background: #3b82f6; color: white; font-weight: 600; }
        .tab-button:not(.active) { color: #6b7280; background: #f9fafb; }
        .metric-card { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); }
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .chart-container { position: relative; height: 200px; }
    </style>
</head>
<body class="font-inter bg-gray-50 text-sm">
    <!-- Compact Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="px-4 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <a href="home.php" class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-arrow-left text-gray-600 text-xs"></i>
                    </a>
                    <div>
                        <h1 class="text-sm font-semibold text-gray-900">Reports Dashboard</h1>
                        <p class="text-xs text-gray-500"><?php echo strtoupper(htmlspecialchars($userInfo['FirmName'])); ?></p>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button onclick="exportReport()" class="bg-blue-500 text-white px-3 py-1.5 rounded-md text-xs font-semibold">
                        <i class="fas fa-download mr-1"></i>Export
                    </button>
                    <button onclick="refreshData()" class="bg-gray-100 text-gray-700 px-3 py-1.5 rounded-md text-xs font-semibold">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="px-4 py-3 pb-20 space-y-4">
        <!-- Date Range Selector -->
        <div class="bg-white rounded-lg p-3 shadow-sm">
            <div class="flex items-center justify-between">
                <label class="text-xs font-medium text-gray-700">Report Period:</label>
                <div class="flex space-x-2">
                    <button onclick="setDateRange('today')" class="date-btn px-2 py-1 text-xs rounded-md bg-gray-100 text-gray-700">Today</button>
                    <button onclick="setDateRange('week')" class="date-btn px-2 py-1 text-xs rounded-md bg-gray-100 text-gray-700">Week</button>
                    <button onclick="setDateRange('month')" class="date-btn px-2 py-1 text-xs rounded-md bg-blue-500 text-white">Month</button>
                    <input type="date" id="customDate" class="px-2 py-1 text-xs border border-gray-300 rounded-md" value="<?php echo $start_date; ?>">
                </div>
            </div>
        </div>

        <!-- Key Metrics Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <!-- Revenue Card -->
            <div class="metric-card rounded-lg p-3 shadow-sm compact-card border border-blue-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 gradient-primary rounded-lg flex items-center justify-center">
                        <i class="fas fa-rupee-sign text-white text-xs"></i>
                    </div>
                    <span class="trend-up text-xs font-medium">
                        <i class="fas fa-arrow-up mr-1"></i>12%
                    </span>
                </div>
                <p class="text-lg font-bold text-gray-800">₹<?php echo number_format($reportData['summary']['total_revenue'] ?? 0); ?></p>
                <p class="text-xs text-gray-600">Total Revenue</p>
                <div class="mt-1 text-xs text-blue-600">
                    <?php echo $reportData['summary']['total_sales'] ?? 0; ?> sales
                </div>
            </div>

            <!-- Cash Flow Card -->
            <div class="metric-card rounded-lg p-3 shadow-sm compact-card border border-green-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 gradient-success rounded-lg flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-white text-xs"></i>
                    </div>
                    <span class="trend-up text-xs font-medium">
                        <i class="fas fa-arrow-up mr-1"></i>8%
                    </span>
                </div>
                <?php 
                $cashNet = ($reportData['balances']['cash']['in'] ?? 0) - ($reportData['balances']['cash']['out'] ?? 0);
                ?>
                <p class="text-lg font-bold text-gray-800">₹<?php echo number_format($cashNet); ?></p>
                <p class="text-xs text-gray-600">Cash Flow</p>
                <div class="mt-1 text-xs text-green-600">
                    Net: ₹<?php echo number_format($cashNet); ?>
                </div>
            </div>

            <!-- Inventory Value Card -->
            <div class="metric-card rounded-lg p-3 shadow-sm compact-card border border-yellow-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 gradient-warning rounded-lg flex items-center justify-center">
                        <i class="fas fa-boxes text-white text-xs"></i>
                    </div>
                    <span class="trend-up text-xs font-medium">
                        <i class="fas fa-arrow-up mr-1"></i>5%
                    </span>
                </div>
                <p class="text-lg font-bold text-gray-800">₹<?php echo number_format($reportData['inventory']['summary']['total_value'] ?? 0); ?></p>
                <p class="text-xs text-gray-600">Inventory Value</p>
                <div class="mt-1 text-xs text-yellow-600">
                    <?php echo $reportData['inventory']['summary']['total_items'] ?? 0; ?> items
                </div>
            </div>

            <!-- Customers Card -->
            <div class="metric-card rounded-lg p-3 shadow-sm compact-card border border-purple-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-white text-xs"></i>
                    </div>
                    <span class="trend-up text-xs font-medium">
                        <i class="fas fa-arrow-up mr-1"></i>15%
                    </span>
                </div>
                <p class="text-lg font-bold text-gray-800"><?php echo $reportData['summary']['unique_customers'] ?? 0; ?></p>
                <p class="text-xs text-gray-600">Active Customers</p>
                <div class="mt-1 text-xs text-purple-600">
                    Avg: ₹<?php echo number_format($reportData['summary']['avg_sale_value'] ?? 0); ?>
                </div>
            </div>
        </div>

        <!-- Payment Methods Breakdown -->
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-credit-card mr-2 text-blue-500"></i>Payment Methods
            </h3>
            <div class="grid grid-cols-3 gap-3">
                <!-- Cash -->
                <div class="text-center p-3 bg-green-50 rounded-lg">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-money-bill text-green-600"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-800">₹<?php echo number_format($reportData['balances']['cash']['in'] ?? 0); ?></p>
                    <p class="text-xs text-gray-600">Cash</p>
                    <div class="mt-1">
                        <div class="w-full bg-green-200 rounded-full h-1">
                            <?php 
                            $totalIn = ($reportData['balances']['cash']['in'] ?? 0) + ($reportData['balances']['bank']['in'] ?? 0) + ($reportData['balances']['upi']['in'] ?? 0);
                            $cashPercent = $totalIn > 0 ? (($reportData['balances']['cash']['in'] ?? 0) / $totalIn) * 100 : 0;
                            ?>
                            <div class="bg-green-500 h-1 rounded-full" style="width: <?php echo $cashPercent; ?>%"></div>
                        </div>
                        <p class="text-xs text-green-600 mt-1"><?php echo round($cashPercent); ?>%</p>
                    </div>
                </div>

                <!-- UPI -->
                <div class="text-center p-3 bg-purple-50 rounded-lg">
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fab fa-google-pay text-purple-600"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-800">₹<?php echo number_format($reportData['balances']['upi']['in'] ?? 0); ?></p>
                    <p class="text-xs text-gray-600">UPI</p>
                    <div class="mt-1">
                        <div class="w-full bg-purple-200 rounded-full h-1">
                            <?php $upiPercent = $totalIn > 0 ? (($reportData['balances']['upi']['in'] ?? 0) / $totalIn) * 100 : 0; ?>
                            <div class="bg-purple-500 h-1 rounded-full" style="width: <?php echo $upiPercent; ?>%"></div>
                        </div>
                        <p class="text-xs text-purple-600 mt-1"><?php echo round($upiPercent); ?>%</p>
                    </div>
                </div>

                <!-- Bank -->
                <div class="text-center p-3 bg-blue-50 rounded-lg">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-university text-blue-600"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-800">₹<?php echo number_format($reportData['balances']['bank']['in'] ?? 0); ?></p>
                    <p class="text-xs text-gray-600">Bank</p>
                    <div class="mt-1">
                        <div class="w-full bg-blue-200 rounded-full h-1">
                            <?php $bankPercent = $totalIn > 0 ? (($reportData['balances']['bank']['in'] ?? 0) / $totalIn) * 100 : 0; ?>
                            <div class="bg-blue-500 h-1 rounded-full" style="width: <?php echo $bankPercent; ?>%"></div>
                        </div>
                        <p class="text-xs text-blue-600 mt-1"><?php echo round($bankPercent); ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Trend Chart -->
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-chart-line mr-2 text-blue-500"></i>Sales Trend
                </h3>
                <div class="flex space-x-1">
                    <button onclick="changeChartView('revenue')" class="chart-btn px-2 py-1 text-xs rounded-md bg-blue-500 text-white">Revenue</button>
                    <button onclick="changeChartView('count')" class="chart-btn px-2 py-1 text-xs rounded-md bg-gray-100 text-gray-700">Count</button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <!-- Top Selling Items -->
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-star mr-2 text-yellow-500"></i>Top Selling Items
            </h3>
            <div class="space-y-2">
                <?php foreach (array_slice($reportData['top_items'], 0, 5) as $index => $item): ?>
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 bg-yellow-100 rounded-full flex items-center justify-center">
                            <span class="text-yellow-600 font-bold text-xs"><?php echo $index + 1; ?></span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($item['product_name']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($item['category']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-800"><?php echo $item['sold_count']; ?> sold</p>
                        <p class="text-xs text-gray-600">₹<?php echo number_format($item['total_revenue']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($reportData['top_items'])): ?>
                <div class="text-center py-6">
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-box text-gray-400 text-xl"></i>
                    </div>
                    <p class="text-gray-600 text-sm">No sales data available for this period</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-receipt mr-2 text-green-500"></i>Recent Sales
                </h3>
                <a href="sales.php" class="text-xs text-blue-600 hover:underline">View All</a>
            </div>
            <div class="space-y-2">
                <?php foreach (array_slice($reportData['cash_flow']['recent_sales'], 0, 5) as $sale): ?>
                <div class="flex items-center justify-between p-2 border border-gray-100 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-shopping-bag text-green-600 text-xs"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800 text-sm">#<?php echo $sale['id']; ?></p>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></p>
                            <p class="text-xs text-gray-500"><?php echo date('d M Y, h:i A', strtotime($sale['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-800">₹<?php echo number_format($sale['grand_total']); ?></p>
                        <span class="<?php 
                            echo $sale['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                                ($sale['payment_status'] === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); 
                        ?> px-2 py-1 rounded-full text-xs font-medium">
                            <?php echo ucfirst($sale['payment_status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($reportData['cash_flow']['recent_sales'])): ?>
                <div class="text-center py-6">
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-receipt text-gray-400 text-xl"></i>
                    </div>
                    <p class="text-gray-600 text-sm">No sales recorded for this period</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-bolt mr-2 text-orange-500"></i>Quick Actions
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <button onclick="window.location.href='sale-entry.php'" class="flex items-center justify-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-plus-circle text-blue-600 mr-2"></i>
                    <span class="text-sm font-medium text-blue-700">New Sale</span>
                </button>
                <button onclick="window.location.href='add.php'" class="flex items-center justify-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fas fa-box text-green-600 mr-2"></i>
                    <span class="text-sm font-medium text-green-700">Add Item</span>
                </button>
                <button onclick="window.location.href='customers.php'" class="flex items-center justify-center p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                    <i class="fas fa-user-plus text-purple-600 mr-2"></i>
                    <span class="text-sm font-medium text-purple-700">Add Customer</span>
                </button>
                <button onclick="exportReport()" class="flex items-center justify-center p-3 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                    <i class="fas fa-file-export text-orange-600 mr-2"></i>
                    <span class="text-sm font-medium text-orange-700">Export Report</span>
                </button>
            </div>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40">
        <div class="px-4 py-2">
            <div class="flex justify-around">
                <a href="home.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-home text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Home</span>
                </a>
                <a href="reports.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-chart-bar text-white text-xs"></i>
                    </div>
                    <span class="text-xs text-blue-600 font-semibold mt-1">Reports</span>
                </a>
                <a href="sale-entry.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-plus text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Sale</span>
                </a>
                <a href="customers.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Customers</span>
                </a>
                <a href="settings.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-cog text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Settings</span>
                </a>
            </div>
        </div>
    </nav>

    <script>
        // Chart.js configuration
        let salesChart;
        const chartData = <?php echo json_encode($reportData['trends']); ?>;

        function initializeChart() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            const labels = chartData.map(item => moment(item.date).format('MMM DD'));
            const revenueData = chartData.map(item => parseFloat(item.daily_revenue));
            const countData = chartData.map(item => parseInt(item.sales_count));

            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue',
                        data: revenueData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                font: {
                                    size: 10
                                },
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 10
                                }
                            }
                        }
                    }
                }
            });
        }

        function changeChartView(type) {
            document.querySelectorAll('.chart-btn').forEach(btn => {
                btn.classList.remove('bg-blue-500', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-700');
            });
            event.target.classList.remove('bg-gray-100', 'text-gray-700');
            event.target.classList.add('bg-blue-500', 'text-white');

            const labels = chartData.map(item => moment(item.date).format('MMM DD'));
            let data, label, color;

            if (type === 'revenue') {
                data = chartData.map(item => parseFloat(item.daily_revenue));
                label = 'Revenue';
                color = '#3b82f6';
            } else {
                data = chartData.map(item => parseInt(item.sales_count));
                label = 'Sales Count';
                color = '#10b981';
            }

            salesChart.data.datasets[0] = {
                label: label,
                data: data,
                borderColor: color,
                backgroundColor: color + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            };
            salesChart.update();
        }

        function setDateRange(period) {
            document.querySelectorAll('.date-btn').forEach(btn => {
                btn.classList.remove('bg-blue-500', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-700');
            });
            event.target.classList.remove('bg-gray-100', 'text-gray-700');
            event.target.classList.add('bg-blue-500', 'text-white');

            let startDate, endDate;
            const today = new Date();

            switch(period) {
                case 'today':
                    startDate = endDate = today.toISOString().split('T')[0];
                    break;
                case 'week':
                    startDate = new Date(today.setDate(today.getDate() - 7)).toISOString().split('T')[0];
                    endDate = new Date().toISOString().split('T')[0];
                    break;
                case 'month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    endDate = new Date().toISOString().split('T')[0];
                    break;
            }

            window.location.href = `?start_date=${startDate}&end_date=${endDate}`;
        }

        function refreshData() {
            window.location.reload();
        }

        function exportReport() {
            const startDate = '<?php echo $start_date; ?>';
            const endDate = '<?php echo $end_date; ?>';
            window.open(`export_report.php?start_date=${startDate}&end_date=${endDate}&format=pdf`, '_blank');
        }

        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeChart();
        });

        // Auto-refresh every 5 minutes
        setInterval(refreshData, 300000);
    </script>
</body>
</html>

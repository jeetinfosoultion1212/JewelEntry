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

// Handle date range
$period = $_GET['period'] ?? 'today';
$custom_date = $_GET['custom_date'] ?? date('Y-m-d');

switch($period) {
    case 'today':
        $start_date = $end_date = date('Y-m-d');
        break;
    case 'yesterday':
        $start_date = $end_date = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('-6 days'));
        $end_date = date('Y-m-d');
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-d');
        break;
    case 'custom':
        $start_date = $end_date = $custom_date;
        break;
    default:
        $start_date = $end_date = date('Y-m-d');
}

// Fetch comprehensive report data
function fetchComprehensiveReportData($conn, $firm_id, $start_date, $end_date) {
    $data = [
        'summary' => [],
        'payments' => [],
        'payment_details' => [],
        'expenses' => [],
        'sold_items' => [],
        'inventory_metals' => [],
        'cash_flow' => []
    ];

    try {
        // Summary Statistics with weights and pieces
        $summaryQuery = "SELECT 
            COALESCE(SUM(js.grand_total), 0) as total_revenue,
            COUNT(DISTINCT js.id) as total_sales,
            COUNT(DISTINCT js.customer_id) as unique_customers,
            COALESCE(AVG(js.grand_total), 0) as avg_sale_value,
            COALESCE(SUM(jsi.gross_weight), 0) as total_weight_sold,
            COALESCE(SUM(jsi.net_weight), 0) as total_net_weight_sold,
            COUNT(jsi.id) as total_pieces_sold
            FROM jewellery_sales js 
            LEFT JOIN jewellery_sales_items jsi ON js.id = jsi.sale_id
            WHERE js.firm_id = ? AND DATE(js.created_at) BETWEEN ? AND ?";
        $summaryStmt = $conn->prepare($summaryQuery);
        $summaryStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $summaryStmt->execute();
        $data['summary'] = $summaryStmt->get_result()->fetch_assoc();

        // Payment method breakdown with detailed in/out
        $paymentQuery = "SELECT 
            payment_type,
            transctions_type,
            SUM(amount) as total,
            COUNT(*) as count
            FROM jewellery_payments 
            WHERE Firm_id = ? AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY payment_type, transctions_type";
        $paymentStmt = $conn->prepare($paymentQuery);
        $paymentStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        
        $payments = [
            'cash' => ['in' => 0, 'out' => 0, 'net' => 0, 'count_in' => 0, 'count_out' => 0],
            'upi' => ['in' => 0, 'out' => 0, 'net' => 0, 'count_in' => 0, 'count_out' => 0],
            'bank' => ['in' => 0, 'out' => 0, 'net' => 0, 'count_in' => 0, 'count_out' => 0],
            'card' => ['in' => 0, 'out' => 0, 'net' => 0, 'count_in' => 0, 'count_out' => 0]
        ];
        
        while ($row = $paymentResult->fetch_assoc()) {
            $type = strtolower($row['payment_type']);
            $direction = $row['transctions_type'] === 'credit' ? 'in' : 'out';
            $amount = floatval($row['total']);
            $count = intval($row['count']);
            
            if ($type === 'cash') {
                $payments['cash'][$direction] += $amount;
                $payments['cash']['count_' . $direction] += $count;
            } elseif ($type === 'upi') {
                $payments['upi'][$direction] += $amount;
                $payments['upi']['count_' . $direction] += $count;
            } elseif (in_array($type, ['bank', 'bank_transfer'])) {
                $payments['bank'][$direction] += $amount;
                $payments['bank']['count_' . $direction] += $count;
            } elseif ($type === 'card') {
                $payments['card'][$direction] += $amount;
                $payments['card']['count_' . $direction] += $count;
            }
        }
        
        // Calculate net for each payment method
        foreach ($payments as $method => &$values) {
            $values['net'] = $values['in'] - $values['out'];
        }
        $data['payments'] = $payments;

        // Detailed payment transactions for modals
        $paymentDetailsQuery = "SELECT jp.*, 
                               CONCAT(COALESCE(c.FirstName, ''), ' ', COALESCE(c.LastName, '')) as customer_name,
                               js.id as sale_id
                               FROM jewellery_payments jp
                               LEFT JOIN jewellery_sales js ON jp.sale_id = js.id
                               LEFT JOIN customer c ON js.customer_id = c.id
                               WHERE jp.Firm_id = ? AND DATE(jp.created_at) BETWEEN ? AND ?
                               ORDER BY jp.created_at DESC";
        $paymentDetailsStmt = $conn->prepare($paymentDetailsQuery);
        $paymentDetailsStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $paymentDetailsStmt->execute();
        $data['payment_details'] = $paymentDetailsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Expenses data
        $expensesQuery = "SELECT 
            category,
            SUM(amount) as total_amount,
            COUNT(*) as count,
            payment_method
            FROM expenses 
            WHERE firm_id = ? AND DATE(date) BETWEEN ? AND ?
            GROUP BY category, payment_method
            ORDER BY total_amount DESC";
        $expensesStmt = $conn->prepare($expensesQuery);
        $expensesStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $expensesStmt->execute();
        $data['expenses']['summary'] = $expensesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Detailed expenses
        $expensesDetailQuery = "SELECT * FROM expenses 
                               WHERE firm_id = ? AND DATE(date) BETWEEN ? AND ?
                               ORDER BY date DESC, created_at DESC LIMIT 20";
        $expensesDetailStmt = $conn->prepare($expensesDetailQuery);
        $expensesDetailStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $expensesDetailStmt->execute();
        $data['expenses']['details'] = $expensesDetailStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Total expenses
        $totalExpensesQuery = "SELECT SUM(amount) as total_expenses FROM expenses 
                              WHERE firm_id = ? AND DATE(date) BETWEEN ? AND ?";
        $totalExpensesStmt = $conn->prepare($totalExpensesQuery);
        $totalExpensesStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $totalExpensesStmt->execute();
        $data['expenses']['total'] = $totalExpensesStmt->get_result()->fetch_assoc()['total_expenses'] ?? 0;

        // Sold items with weight and pieces
        $soldItemsQuery = "SELECT jsi.*, js.created_at as sale_date,
                          CONCAT(COALESCE(c.FirstName, ''), ' ', COALESCE(c.LastName, '')) as customer_name
                          FROM jewellery_sales_items jsi
                          JOIN jewellery_sales js ON jsi.sale_id = js.id
                          LEFT JOIN customer c ON js.customer_id = c.id
                          WHERE js.firm_id = ? AND DATE(js.created_at) BETWEEN ? AND ?
                          ORDER BY js.created_at DESC";
        $soldItemsStmt = $conn->prepare($soldItemsQuery);
        $soldItemsStmt->bind_param("iss", $firm_id, $start_date, $end_date);
        $soldItemsStmt->execute();
        $data['sold_items'] = $soldItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Inventory metals by purity
        $inventoryMetalsQuery = "SELECT 
            material_type,
            purity,
            SUM(current_stock) as total_stock,
            SUM(remaining_stock) as remaining_stock,
            AVG(cost_price_per_gram) as avg_cost_price,
            COUNT(*) as stock_count,
            unit_measurement
            FROM inventory_metals 
            WHERE firm_id = ?
            GROUP BY material_type, purity
            ORDER BY material_type, purity DESC";
        $inventoryMetalsStmt = $conn->prepare($inventoryMetalsQuery);
        $inventoryMetalsStmt->bind_param("i", $firm_id);
        $inventoryMetalsStmt->execute();
        $data['inventory_metals'] = $inventoryMetalsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Calculate cash flow
        $totalIn = $payments['cash']['in'] + $payments['upi']['in'] + $payments['bank']['in'] + $payments['card']['in'];
        $totalOut = $payments['cash']['out'] + $payments['upi']['out'] + $payments['bank']['out'] + $payments['card']['out'];
        $data['cash_flow'] = [
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'expenses' => $data['expenses']['total'],
            'net' => $totalIn - $totalOut - $data['expenses']['total']
        ];

    } catch (Exception $e) {
        error_log("Report data fetch error: " . $e->getMessage());
    }

    return $data;
}

$reportData = fetchComprehensiveReportData($conn, $firm_id, $start_date, $end_date);
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
        .gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .gradient-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .gradient-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .gradient-danger { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .gradient-purple { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .gradient-orange { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); }
        
        .metric-card { 
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .metric-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
        }
        
        .period-btn { 
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .period-btn.active { 
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white; 
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .period-btn:not(.active) { 
            background: #f8fafc; 
            color: #64748b; 
            border: 1px solid #e2e8f0;
        }
        
        .payment-card { 
            cursor: pointer; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
        .payment-card:hover { 
            transform: translateY(-1px) scale(1.02); 
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .data-row {
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        .data-row:hover {
            background: #f8fafc;
            border-left-color: #3b82f6;
            transform: translateX(2px);
        }
        
        .compact-scroll {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }
        .compact-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .compact-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 2px;
        }
        .compact-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }
        
        .stat-icon {
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.1) 100%);
            backdrop-filter: blur(10px);
        }
        
        .trend-indicator {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .section-header {
            background: linear-gradient(90deg, #f8fafc 0%, #ffffff 50%, #f8fafc 100%);
            border-bottom: 2px solid #e2e8f0;
        }
    </style>
</head>
<body class="font-inter bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 text-sm">
    <!-- Professional Header -->
    <header class="glass-effect border-b border-white/20 sticky top-0 z-50 shadow-lg">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="home.php" class="w-8 h-8 gradient-primary rounded-lg flex items-center justify-center shadow-md">
                        <i class="fas fa-arrow-left text-white text-sm"></i>
                    </a>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">Analytics Dashboard</h1>
                        <p class="text-xs text-gray-600 font-medium"><?php echo strtoupper(htmlspecialchars($userInfo['FirmName'])); ?> • <?php echo strtoupper($period); ?></p>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button onclick="exportReport()" class="gradient-primary text-white px-4 py-2 rounded-lg text-xs font-semibold shadow-md hover:shadow-lg transition-all">
                        <i class="fas fa-download mr-1"></i>Export
                    </button>
                    <button onclick="refreshData()" class="bg-white/80 text-gray-700 px-4 py-2 rounded-lg text-xs font-semibold shadow-md hover:shadow-lg transition-all">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="px-4 py-4 pb-24 space-y-4">
        <!-- Compact Date Range Selector -->
        <div class="glass-effect rounded-xl p-3 shadow-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 gradient-primary rounded-md flex items-center justify-center">
                        <i class="fas fa-calendar text-white text-xs"></i>
                    </div>
                    <span class="text-sm font-semibold text-gray-800">Report Period</span>
                </div>
                <div class="flex space-x-1">
                    <button onclick="setPeriod('today')" class="period-btn px-3 py-1.5 text-xs rounded-lg <?php echo $period === 'today' ? 'active' : ''; ?>">Today</button>
                    <button onclick="setPeriod('yesterday')" class="period-btn px-3 py-1.5 text-xs rounded-lg <?php echo $period === 'yesterday' ? 'active' : ''; ?>">Yesterday</button>
                    <button onclick="setPeriod('week')" class="period-btn px-3 py-1.5 text-xs rounded-lg <?php echo $period === 'week' ? 'active' : ''; ?>">Week</button>
                    <button onclick="setPeriod('month')" class="period-btn px-3 py-1.5 text-xs rounded-lg <?php echo $period === 'month' ? 'active' : ''; ?>">Month</button>
                    <input type="date" id="customDate" onchange="setCustomDate()" class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="<?php echo $custom_date; ?>">
                </div>
            </div>
        </div>

        <!-- Key Performance Indicators -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Revenue Card -->
            <div class="metric-card rounded-xl p-4 shadow-lg">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 gradient-primary rounded-xl flex items-center justify-center stat-icon">
                        <i class="fas fa-rupee-sign text-white text-sm"></i>
                    </div>
                    <div class="text-right">
                        <span class="trend-indicator text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded-full">
                            <i class="fas fa-arrow-up mr-1"></i>+12%
                        </span>
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 mb-1">₹<?php echo number_format($reportData['summary']['total_revenue'] ?? 0); ?></p>
                    <p class="text-xs text-gray-600 font-medium">Total Revenue</p>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                        <span class="text-xs text-blue-600 font-medium"><?php echo $reportData['summary']['total_sales'] ?? 0; ?> Sales</span>
                        <span class="text-xs text-purple-600 font-medium"><?php echo $reportData['summary']['unique_customers'] ?? 0; ?> Customers</span>
                    </div>
                </div>
            </div>

            <!-- Cash Flow Card -->
            <div class="metric-card rounded-xl p-4 shadow-lg">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 gradient-success rounded-xl flex items-center justify-center stat-icon">
                        <i class="fas fa-exchange-alt text-white text-sm"></i>
                    </div>
                    <div class="text-right">
                        <span class="trend-indicator text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded-full">
                            <i class="fas fa-arrow-up mr-1"></i>+8%
                        </span>
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 mb-1">₹<?php echo number_format($reportData['cash_flow']['net'] ?? 0); ?></p>
                    <p class="text-xs text-gray-600 font-medium">Net Cash Flow</p>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                        <span class="text-xs text-green-600 font-medium">In: ₹<?php echo number_format($reportData['cash_flow']['total_in'] ?? 0); ?></span>
                        <span class="text-xs text-red-600 font-medium">Out: ₹<?php echo number_format($reportData['cash_flow']['total_out'] + $reportData['cash_flow']['expenses']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Expenses Card -->
            <div class="metric-card rounded-xl p-4 shadow-lg">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 gradient-warning rounded-xl flex items-center justify-center stat-icon">
                        <i class="fas fa-credit-card text-white text-sm"></i>
                    </div>
                    <div class="text-right">
                        <span class="trend-indicator text-xs font-bold text-red-600 bg-red-100 px-2 py-1 rounded-full">
                            <i class="fas fa-arrow-up mr-1"></i>+3%
                        </span>
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 mb-1">₹<?php echo number_format($reportData['expenses']['total'] ?? 0); ?></p>
                    <p class="text-xs text-gray-600 font-medium">Total Expenses</p>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                        <span class="text-xs text-orange-600 font-medium"><?php echo count($reportData['expenses']['details'] ?? []); ?> Transactions</span>
                        <span class="text-xs text-gray-500 font-medium">Multiple Categories</span>
                    </div>
                </div>
            </div>

            <!-- Weight & Pieces Card -->
            <div class="metric-card rounded-xl p-4 shadow-lg">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 gradient-danger rounded-xl flex items-center justify-center stat-icon">
                        <i class="fas fa-weight text-white text-sm"></i>
                    </div>
                    <div class="text-right">
                        <span class="trend-indicator text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded-full">
                            <i class="fas fa-arrow-up mr-1"></i>+15%
                        </span>
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($reportData['summary']['total_weight_sold'] ?? 0, 1); ?>g</p>
                    <p class="text-xs text-gray-600 font-medium">Weight Sold</p>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                        <span class="text-xs text-yellow-600 font-medium"><?php echo $reportData['summary']['total_pieces_sold'] ?? 0; ?> Pieces</span>
                        <span class="text-xs text-blue-600 font-medium">Net: <?php echo number_format($reportData['summary']['total_net_weight_sold'] ?? 0, 1); ?>g</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods Grid -->
        <div class="glass-effect rounded-xl p-4 shadow-lg">
            <div class="section-header -mx-4 -mt-4 px-4 py-3 mb-4 rounded-t-xl">
                <h3 class="text-sm font-bold text-gray-800 flex items-center">
                    <i class="fas fa-credit-card mr-2 text-blue-600"></i>Payment Methods Analysis
                    <span class="ml-auto text-xs text-gray-500 font-normal">Click for detailed transactions</span>
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <!-- Cash -->
                <div class="payment-card bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-4 border border-green-200" onclick="showPaymentModal('cash')">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-money-bill text-white text-sm"></i>
                            </div>
                            <span class="text-sm font-bold text-gray-800">CASH</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-600">Net Amount</span>
                            <span class="text-lg font-bold text-gray-900">₹<?php echo number_format($reportData['payments']['cash']['net'] ?? 0); ?></span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-white/60 rounded-lg p-2 text-center">
                                <div class="text-green-600 font-bold">↓ ₹<?php echo number_format($reportData['payments']['cash']['in'] ?? 0); ?></div>
                                <div class="text-gray-500">Credit ({<?php echo $reportData['payments']['cash']['count_in'] ?? 0; ?>})</div>
                            </div>
                            <div class="bg-white/60 rounded-lg p-2 text-center">
                                <div class="text-red-600 font-bold">↑ ₹<?php echo number_format($reportData['payments']['cash']['out'] ?? 0); ?></div>
                                <div class="text-gray-500">Debit ({<?php echo $reportData['payments']['cash']['count_out'] ?? 0; ?>})</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- UPI -->
                <div class="payment-card bg-gradient-to-br from-purple-50 to-violet-50 rounded-xl p-4 border border-purple-200" onclick="showPaymentModal('upi')">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                                <i class="fab fa-google-pay text-white text-sm"></i>
                            </div>
                            <span class="text-sm font-bold text-gray-800">UPI</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-600">Net Amount</span>
                            <span class="text-lg font-bold text-gray-900">₹<?php echo number_format($reportData['payments']['upi']['net'] ?? 0); ?></span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-white/60 rounded-lg p-2 text-center">
                                <div class="text-green-600 font-bold">↓ ₹<?php echo number_format($reportData['payments']['upi']['in'] ?? 0); ?></div>
                                <div class="text-gray-500">Credit ({<?php echo $reportData['payments']['upi']['count_in'] ?? 0; ?>})</div>
                            </div>
                            <div class="bg-white/60 rounded-lg p-2 text-center">
                                <div class="text-red-600 font-bold">↑ ₹<?php echo number_format($reportData['payments']['upi']['out'] ?? 0); ?></div>
                                <div class="text-gray-500">Debit ({<?php echo $reportData['payments']['upi']['count_out'] ?? 0; ?>})</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank -->
                <div class="payment-card bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-4 border border-blue-200" onclick="showPaymentModal('bank')">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-university text-white text-sm"></i>
                            </div>
                            <span class="text-sm font-bold text-gray-800">BANK</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-600">Net Amount</span>
                            <span class="text-lg font-bold text-gray-900">₹<?php echo number_format($reportData['payments']['bank']['net'] ?? 0); ?></span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-white/60 rounded-lg p-2 text-center">
                                <div class="text-green-600 font-bold">↓ ₹<?php echo number_format($reportData['payments']['bank']['in'] ?? 0); ?></div>
                                <div class="text-gray-500">Credit ({<?php echo $reportData['payments']['bank']['count_in'] ?? 0; ?>})</div>
                            </div>
                            <div class="bg-white/60 rounded-lg p-2 text-center">
                                <div class="text-red-600 font-bold">↑ ₹<?php echo number_format($reportData['payments']['bank']['out'] ?? 0); ?></div>
                                <div class="text-gray-500">Debit ({<?php echo $reportData['payments']['bank']['count_out'] ?? 0; ?>})</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Showcase Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Inventory Metals -->
            <div class="glass-effect rounded-xl p-4 shadow-lg">
                <div class="section-header -mx-4 -mt-4 px-4 py-3 mb-4 rounded-t-xl">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center">
                        <i class="fas fa-chart-pie mr-2 text-yellow-600"></i>Inventory by Purity
                        <span class="ml-auto text-xs text-gray-500 font-normal"><?php echo count($reportData['inventory_metals']); ?> Types</span>
                    </h3>
                </div>
                <div class="space-y-2 max-h-64 compact-scroll overflow-y-auto">
                    <?php foreach ($reportData['inventory_metals'] as $index => $metal): ?>
                    <div class="data-row flex items-center justify-between p-3 bg-gradient-to-r from-yellow-50 to-amber-50 rounded-lg border border-yellow-200">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-lg flex items-center justify-center">
                                <span class="text-white font-bold text-xs"><?php echo substr($metal['material_type'], 0, 1); ?></span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($metal['material_type']); ?></p>
                                <p class="text-xs text-gray-600"><?php echo $metal['purity']; ?> Purity • <?php echo $metal['stock_count']; ?> Items</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-gray-900"><?php echo number_format($metal['remaining_stock'], 2); ?><?php echo $metal['unit_measurement']; ?></p>
                            <p class="text-xs text-yellow-600">₹<?php echo number_format($metal['avg_cost_price'], 2); ?>/g</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($reportData['inventory_metals'])): ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-box text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-600 text-sm font-medium">No inventory metals data available</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sold Items -->
            <div class="glass-effect rounded-xl p-4 shadow-lg">
                <div class="section-header -mx-4 -mt-4 px-4 py-3 mb-4 rounded-t-xl">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center">
                        <i class="fas fa-list mr-2 text-green-600"></i>Sold Items
                        <span class="ml-auto text-xs text-gray-500 font-normal"><?php echo count($reportData['sold_items']); ?> Items • <?php echo number_format($reportData['summary']['total_weight_sold'] ?? 0, 2); ?>g</span>
                    </h3>
                </div>
                <div class="space-y-2 max-h-64 compact-scroll overflow-y-auto">
                    <?php foreach (array_slice($reportData['sold_items'], 0, 8) as $item): ?>
                    <div class="data-row flex items-center justify-between p-3 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border border-green-200">
                        <div class="flex-1">
                            <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($item['product_name']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?> • <?php echo htmlspecialchars($item['customer_name'] ?: 'Walk-in Customer'); ?></p>
                            <p class="text-xs text-gray-500"><?php echo date('d M, h:i A', strtotime($item['sale_date'])); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-gray-900">₹<?php echo number_format($item['total_amount']); ?></p>
                            <div class="flex space-x-2 text-xs">
                                <span class="text-yellow-600 font-medium"><?php echo number_format($item['gross_weight'] ?? 0, 2); ?>g</span>
                                <span class="text-blue-600 font-medium">1 pc</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($reportData['sold_items'])): ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-shopping-bag text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-600 text-sm font-medium">No items sold for this period</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Expenses Analysis -->
        <div class="glass-effect rounded-xl p-4 shadow-lg">
            <div class="section-header -mx-4 -mt-4 px-4 py-3 mb-4 rounded-t-xl">
                <h3 class="text-sm font-bold text-gray-800 flex items-center">
                    <i class="fas fa-receipt mr-2 text-red-600"></i>Expenses Analysis
                    <span class="ml-auto text-xs text-gray-500 font-normal">Total: ₹<?php echo number_format($reportData['expenses']['total'] ?? 0); ?></span>
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php 
                $expensesByCategory = [];
                foreach ($reportData['expenses']['summary'] as $expense) {
                    if (!isset($expensesByCategory[$expense['category']])) {
                        $expensesByCategory[$expense['category']] = 0;
                    }
                    $expensesByCategory[$expense['category']] += $expense['total_amount'];
                }
                arsort($expensesByCategory);
                $colors = ['red', 'orange', 'yellow', 'green', 'blue', 'purple'];
                $colorIndex = 0;
                ?>
                <?php foreach (array_slice($expensesByCategory, 0, 6, true) as $category => $amount): ?>
                <div class="data-row bg-gradient-to-r from-<?php echo $colors[$colorIndex % count($colors)]; ?>-50 to-<?php echo $colors[$colorIndex % count($colors)]; ?>-100 rounded-lg p-3 border border-<?php echo $colors[$colorIndex % count($colors)]; ?>-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 bg-<?php echo $colors[$colorIndex % count($colors)]; ?>-500 rounded-md flex items-center justify-center">
                                <i class="fas fa-tag text-white text-xs"></i>
                            </div>
                            <span class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($category); ?></span>
                        </div>
                        <span class="text-sm font-bold text-<?php echo $colors[$colorIndex % count($colors)]; ?>-700">₹<?php echo number_format($amount); ?></span>
                    </div>
                </div>
                <?php $colorIndex++; endforeach; ?>
                
                <?php if (empty($expensesByCategory)): ?>
                <div class="col-span-full text-center py-8">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-receipt text-gray-400 text-2xl"></i>
                    </div>
                    <p class="text-gray-600 text-sm font-medium">No expenses recorded for this period</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Business Summary -->
        <div class="gradient-primary rounded-xl p-6 text-white shadow-xl">
            <h3 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-chart-line mr-2"></i>Business Summary
            </h3>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold mb-1">₹<?php echo number_format($reportData['cash_flow']['total_in'] ?? 0); ?></div>
                    <div class="text-sm opacity-80">Total Inflow</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold mb-1">₹<?php echo number_format($reportData['cash_flow']['total_out'] + $reportData['cash_flow']['expenses']); ?></div>
                    <div class="text-sm opacity-80">Total Outflow</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold mb-1"><?php echo number_format($reportData['summary']['total_net_weight_sold'] ?? 0, 1); ?>g</div>
                    <div class="text-sm opacity-80">Net Weight Sold</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold mb-1">₹<?php echo number_format($reportData['summary']['avg_sale_value'] ?? 0); ?></div>
                    <div class="text-sm opacity-80">Average Sale Value</div>
                </div>
            </div>
        </div>
    </main>

    <!-- Enhanced Payment Details Modal -->
    <div id="paymentModal" class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-[90] hidden">
        <div class="glass-effect rounded-2xl p-6 w-full max-w-2xl mx-4 max-h-[85vh] overflow-y-auto shadow-2xl border border-white/20">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-800">Payment Details</h3>
                <button onclick="closePaymentModal()" class="text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-all">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <!-- Enhanced Tabs -->
            <div class="flex space-x-2 mb-6 bg-gray-100 rounded-xl p-1">
                <button onclick="switchModalTab('credit')" id="creditTab" class="flex-1 py-3 px-4 rounded-lg text-sm font-bold gradient-success text-white shadow-md">
                    <i class="fas fa-arrow-down mr-2"></i>Credit (Inflow)
                </button>
                <button onclick="switchModalTab('debit')" id="debitTab" class="flex-1 py-3 px-4 rounded-lg text-sm font-medium text-gray-600 hover:bg-white transition-all">
                    <i class="fas fa-arrow-up mr-2"></i>Debit (Outflow)
                </button>
            </div>

            <!-- Modal Content -->
            <div id="modalContent" class="space-y-3">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 glass-effect border-t border-white/20 z-40 shadow-2xl">
        <div class="px-4 py-3">
            <div class="flex justify-around">
                <a href="home.php" class="flex flex-col items-center py-2 px-3 rounded-lg hover:bg-white/20 transition-all">
                    <div class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center mb-1">
                        <i class="fas fa-home text-gray-500 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-500 font-medium">Home</span>
                </a>
                <a href="reports.php" class="flex flex-col items-center py-2 px-3 rounded-lg bg-white/20">
                    <div class="w-7 h-7 gradient-primary rounded-lg flex items-center justify-center mb-1 shadow-md">
                        <i class="fas fa-chart-bar text-white text-sm"></i>
                    </div>
                    <span class="text-xs text-blue-600 font-bold">Reports</span>
                </a>
                <a href="sale-entry.php" class="flex flex-col items-center py-2 px-3 rounded-lg hover:bg-white/20 transition-all">
                    <div class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center mb-1">
                        <i class="fas fa-plus text-gray-500 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-500 font-medium">Sale</span>
                </a>
                <a href="customers.php" class="flex flex-col items-center py-2 px-3 rounded-lg hover:bg-white/20 transition-all">
                    <div class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center mb-1">
                        <i class="fas fa-users text-gray-500 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-500 font-medium">Customers</span>
                </a>
                <a href="settings.php" class="flex flex-col items-center py-2 px-3 rounded-lg hover:bg-white/20 transition-all">
                    <div class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center mb-1">
                        <i class="fas fa-cog text-gray-500 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-500 font-medium">Settings</span>
                </a>
            </div>
        </div>
    </nav>

    <script>
        // Payment details data
        const paymentDetails = <?php echo json_encode($reportData['payment_details']); ?>;
        let currentPaymentType = '';

        function setPeriod(period) {
            window.location.href = `?period=${period}`;
        }

        function setCustomDate() {
            const date = document.getElementById('customDate').value;
            window.location.href = `?period=custom&custom_date=${date}`;
        }

        function refreshData() {
            window.location.reload();
        }

        function exportReport() {
            const period = '<?php echo $period; ?>';
            const customDate = '<?php echo $custom_date; ?>';
            window.open(`export_report.php?period=${period}&custom_date=${customDate}&format=pdf`, '_blank');
        }

        function showPaymentModal(paymentType) {
            currentPaymentType = paymentType;
            document.getElementById('modalTitle').innerHTML = `<i class="fas fa-${paymentType === 'cash' ? 'money-bill' : paymentType === 'upi' ? 'mobile-alt' : 'university'} mr-2"></i>${paymentType.toUpperCase()} Payment Analysis`;
            document.getElementById('paymentModal').classList.remove('hidden');
            switchModalTab('credit');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
        }

        function switchModalTab(type) {
            // Update tab styles
            const creditTab = document.getElementById('creditTab');
            const debitTab = document.getElementById('debitTab');
            
            if (type === 'credit') {
                creditTab.className = 'flex-1 py-3 px-4 rounded-lg text-sm font-bold gradient-success text-white shadow-md';
                debitTab.className = 'flex-1 py-3 px-4 rounded-lg text-sm font-medium text-gray-600 hover:bg-white transition-all';
            } else {
                creditTab.className = 'flex-1 py-3 px-4 rounded-lg text-sm font-medium text-gray-600 hover:bg-white transition-all';
                debitTab.className = 'flex-1 py-3 px-4 rounded-lg text-sm font-bold gradient-warning text-white shadow-md';
            }

            // Filter and display payments
            const filteredPayments = paymentDetails.filter(payment => {
                const matchesType = payment.payment_type.toLowerCase() === currentPaymentType || 
                                  (currentPaymentType === 'bank' && ['bank', 'bank_transfer', 'card'].includes(payment.payment_type.toLowerCase()));
                const matchesDirection = payment.transctions_type === (type === 'credit' ? 'credit' : 'debit');
                return matchesType && matchesDirection;
            });

            let content = '';
            if (filteredPayments.length === 0) {
                content = `<div class="text-center py-12">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-receipt text-gray-400 text-3xl"></i>
                    </div>
                    <p class="text-gray-600 text-lg font-medium">No ${type} transactions found</p>
                    <p class="text-gray-500 text-sm mt-1">No ${type} transactions recorded for this period</p>
                </div>`;
            } else {
                let totalAmount = 0;
                filteredPayments.forEach(payment => {
                    totalAmount += parseFloat(payment.amount);
                    const isCredit = payment.transctions_type === 'credit';
                    content += `
                        <div class="data-row flex items-center justify-between p-4 bg-gradient-to-r from-${isCredit ? 'green' : 'red'}-50 to-${isCredit ? 'emerald' : 'rose'}-50 rounded-xl border border-${isCredit ? 'green' : 'red'}-200 shadow-sm">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 ${isCredit ? 'gradient-success' : 'gradient-warning'} rounded-xl flex items-center justify-center shadow-md">
                                    <i class="fas ${isCredit ? 'fa-arrow-down text-white' : 'fa-arrow-up text-white'} text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-800">${payment.customer_name || 'Direct Payment'}</p>
                                    <p class="text-xs text-gray-600">${payment.sale_id ? 'Sale #' + payment.sale_id : 'Direct Transaction'} • ${payment.payment_type.toUpperCase()}</p>
                                    <p class="text-xs text-gray-500">${new Date(payment.created_at).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric'})} at ${new Date(payment.created_at).toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'})}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold ${isCredit ? 'text-green-700' : 'text-red-700'}">
                                    ${isCredit ? '+' : '-'}₹${parseFloat(payment.amount).toLocaleString('en-IN')}
                                </p>
                                <p class="text-xs text-gray-500 font-medium">${payment.payment_type.toUpperCase()}</p>
                            </div>
                        </div>
                    `;
                });
                
                // Add summary at the top
                content = `
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl p-4 text-white mb-4 shadow-lg">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm opacity-80">${type.charAt(0).toUpperCase() + type.slice(1)} Summary</p>
                                <p class="text-2xl font-bold">₹${totalAmount.toLocaleString('en-IN')}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm opacity-80">Transactions</p>
                                <p class="text-2xl font-bold">${filteredPayments.length}</p>
                            </div>
                        </div>
                    </div>
                ` + content;
            }

            document.getElementById('modalContent').innerHTML = content;
        }

        // Auto-refresh every 5 minutes for today's data
        <?php if ($period === 'today'): ?>
        setInterval(refreshData, 300000);
        <?php endif; ?>

        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>

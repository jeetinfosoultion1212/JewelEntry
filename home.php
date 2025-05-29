<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

// Handle rate form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_action'])) {
    $material_type = $_POST['material_type'] ?? '';
    $purity = $_POST['purity'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $rate = $_POST['rate'] ?? '';
    $firm_id = $_SESSION['firmID'];

    if ($_POST['rate_action'] === 'save_rate') {
        if ($material_type && $purity && $unit && is_numeric($rate) && $rate > 0) {
            // Check if rate exists
            $checkQuery = "SELECT id FROM jewellery_price_config WHERE firm_id = ? AND material_type = ? AND purity = ? AND unit = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("isss", $firm_id, $material_type, $purity, $unit);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            if ($existing) {
                // Update
                $updateQuery = "UPDATE jewellery_price_config SET rate = ?, effective_date = CURRENT_TIMESTAMP WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("di", $rate, $existing['id']);
                $updateStmt->execute();
            } else {
                // Insert
                $insertQuery = "INSERT INTO jewellery_price_config (firm_id, material_type, purity, unit, rate, effective_date) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("isssd", $firm_id, $material_type, $purity, $unit, $rate);
                $insertStmt->execute();
            }
            $_SESSION['success'] = 'Rate saved successfully.';
        } else {
            $_SESSION['error'] = 'Please fill all fields correctly with a valid rate.';
        }
        header("Location: home.php");
        exit();
    } elseif ($_POST['rate_action'] === 'clear_rate') {
        if ($material_type && $firm_id) {
            $deleteQuery = "DELETE FROM jewellery_price_config WHERE firm_id = ? AND material_type = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("is", $firm_id, $material_type);
            $deleteStmt->execute();
            $_SESSION['success'] = 'Rate cleared successfully.';
        } else {
            $_SESSION['error'] = 'Invalid material type.';
        }
        header("Location: home.php");
        exit();
    }
}

// Function to format number in Indian system (Lakhs, Crores)
function formatIndianAmount($num) {
    $num = (float) $num;
    if ($num < 1000) {
        return number_format($num, 0); // Below 1000, use simple format
    } else if ($num < 100000) {
        // Thousands
        return number_format($num, 0); // Use standard format for 1k to 99k
    } else if ($num < 10000000) {
        // Lakhs
        return number_format($num / 100000, 2) . 'L';
    } else {
        // Crores
        return number_format($num / 10000000, 2) . 'Cr';
    }
}

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user and firm details
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.City
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();


// Fetch current rates
$ratesQuery = "SELECT * FROM jewellery_price_config 
               WHERE firm_id = ? 
               AND material_type IN ('Gold', 'Silver', 'Platinum')
               AND purity IN ('24K', '99.9', '95')
               ORDER BY effective_date DESC";
$ratesStmt = $conn->prepare($ratesQuery);
$ratesStmt->bind_param("i", $firm_id);
$ratesStmt->execute();
$ratesResult = $ratesStmt->get_result();

// Get today's date
$today = date('Y-m-d');

// Get today's inventory IN count and weight
$inQuery = "SELECT COUNT(*) as total_in, COALESCE(SUM(gross_weight), 0) as total_weight FROM jewellery_items WHERE DATE(created_at) = ?";
$inStmt = $conn->prepare($inQuery);
$inStmt->bind_param("s", $today);
$inStmt->execute();
$inResult = $inStmt->get_result()->fetch_assoc();
$totalIn = $inResult['total_in'] ?? 0;
$totalInWeight = $inResult['total_weight'] ?? 0;

// Get today's inventory OUT count and weight
$outQuery = "SELECT COUNT(*) as total_out, COALESCE(SUM(gross_weight), 0) as total_weight FROM jewellery_sales_items WHERE DATE(created_at) = ?";
$outStmt = $conn->prepare($outQuery);
$outStmt->bind_param("s", $today);
$outStmt->execute();
$outResult = $outStmt->get_result()->fetch_assoc();
$totalOut = $outResult['total_out'] ?? 0;
$totalOutWeight = $outResult['total_weight'] ?? 0;

// Get today's total sales amount
$salesQuery = "SELECT SUM(grand_total) as total_sales FROM jewellery_sales WHERE DATE(created_at) = ?";
$salesStmt = $conn->prepare($salesQuery);
$salesStmt->bind_param("s", $today);
$salesStmt->execute();
$salesResult = $salesStmt->get_result()->fetch_assoc();
$totalSales = $salesResult['total_sales'] ?? 0;

// Fetch today's orders count
$orderQuery = "SELECT COUNT(*) as total_orders FROM jewellery_sales WHERE DATE(created_at) = ?";
$orderStmt = $conn->prepare($orderQuery);
$orderStmt->bind_param("s", $today);
$orderStmt->execute();
$orderResult = $orderStmt->get_result()->fetch_assoc();
$totalOrders = $orderResult['total_orders'] ?? 0;   

// Fetch today's new customers count
$newCustomerQuery = "SELECT COUNT(*) as new_customers FROM customer WHERE DATE(CreatedAt) = ?";
$newCustomerStmt = $conn->prepare($newCustomerQuery);
$newCustomerStmt->bind_param("s", $today);
$newCustomerStmt->execute();
$newCustomerResult = $newCustomerStmt->get_result()->fetch_assoc();
$newCustomers = $newCustomerResult['new_customers'] ?? 0;

// Fetch total customers count
$totalCustomerQuery = "SELECT COUNT(*) as total_customers FROM customer";
$totalCustomerStmt = $conn->prepare($totalCustomerQuery);
$totalCustomerStmt->execute();
$totalCustomerResult = $totalCustomerStmt->get_result()->fetch_assoc();
$totalCustomers = $totalCustomerResult['total_customers'] ?? 0;

// Fetch total items ever added
$totalAddedQuery = "SELECT COUNT(*) as total_added, COALESCE(SUM(gross_weight), 0) as total_added_weight FROM jewellery_items";
$totalAddedStmt = $conn->prepare($totalAddedQuery);
$totalAddedStmt->execute();
$totalAddedResult = $totalAddedStmt->get_result()->fetch_assoc();
$totalAddedItems = $totalAddedResult['total_added'] ?? 0;
$totalAddedWeight = $totalAddedResult['total_added_weight'] ?? 0;

// Fetch total items ever sold
$totalSoldQuery = "SELECT COUNT(*) as total_sold, COALESCE(SUM(gross_weight), 0) as total_sold_weight FROM jewellery_sales_items";
$totalSoldStmt = $conn->prepare($totalSoldQuery);
$totalSoldStmt->execute();
$totalSoldResult = $totalSoldStmt->get_result()->fetch_assoc();
$totalSoldItems = $totalSoldResult['total_sold'] ?? 0;
$totalSoldWeight = $totalSoldResult['total_sold_weight'] ?? 0;

// Fetch total available stock (items from jewellery_items table where status is not 'Sold')
$availableStockQuery = "SELECT COUNT(*) as total_available, COALESCE(SUM(gross_weight), 0) as total_available_weight FROM jewellery_items WHERE status != 'Sold'";
$availableStockStmt = $conn->prepare($availableStockQuery);
$availableStockStmt->execute();
$availableStockResult = $availableStockStmt->get_result()->fetch_assoc();
$totalAvailableItems = $availableStockResult['total_available'] ?? 0;
$totalAvailableWeight = $availableStockResult['total_available_weight'] ?? 0;

$rates = [];
while ($row = $ratesResult->fetch_assoc()) {
    $rates[$row['material_type']] = $row;
}

// Fetch active Lucky Draw scheme details
$luckyDrawScheme = null;
$luckyDrawEntriesCount = 0;

$luckyDrawQuery = "SELECT id, scheme_name, end_date FROM schemes WHERE firm_id = ? AND scheme_type = 'lucky_draw' AND status = 'active' AND start_date <= CURRENT_DATE() AND end_date >= CURRENT_DATE() LIMIT 1";
$luckyDrawStmt = $conn->prepare($luckyDrawQuery);
$luckyDrawStmt->bind_param("i", $firm_id);
$luckyDrawStmt->execute();
$luckyDrawResult = $luckyDrawStmt->get_result();

if ($luckyDrawResult->num_rows > 0) {
    $luckyDrawScheme = $luckyDrawResult->fetch_assoc();
    
    // Count entries for the active Lucky Draw scheme
    $entriesQuery = "SELECT COUNT(DISTINCT customer_id) as total_entries FROM scheme_entries WHERE scheme_id = ?";
    $entriesStmt = $conn->prepare($entriesQuery);
    $entriesStmt->bind_param("i", $luckyDrawScheme['id']);
    $entriesStmt->execute();
    $entriesResult = $entriesStmt->get_result()->fetch_assoc();
    $luckyDrawEntriesCount = $entriesResult['total_entries'] ?? 0;
}

// Fetch active Gold Saving Plan details
$goldSaverPlan = null;
$goldSaverEnrollmentsCount = 0; // Placeholder for now

$goldSaverQuery = "SELECT id, plan_name, description FROM gold_saving_plans WHERE firm_id = ? AND status = 'active' LIMIT 1";
$goldSaverStmt = $conn->prepare($goldSaverQuery);
$goldSaverStmt->bind_param("i", $firm_id);
$goldSaverStmt->execute();
$goldSaverResult = $goldSaverStmt->get_result();

if ($goldSaverResult->num_rows > 0) {
    $goldSaverPlan = $goldSaverResult->fetch_assoc();
    // TODO: Add query here later to count enrollments for this plan if needed
}

// Total Sales Till Date
$totalSalesAllTimeQuery = "SELECT COALESCE(SUM(grand_total), 0) as total_sales_all_time FROM jewellery_sales WHERE firm_id = ?";
$totalSalesAllTimeStmt = $conn->prepare($totalSalesAllTimeQuery);
$totalSalesAllTimeStmt->bind_param("i", $firm_id);
$totalSalesAllTimeStmt->execute();
$totalSalesAllTimeResult = $totalSalesAllTimeStmt->get_result()->fetch_assoc();
$totalSalesAllTime = $totalSalesAllTimeResult['total_sales_all_time'] ?? 0;

// Total Pending Bills (Assuming payment_status column in jewellery_sales)
$totalPendingBillsQuery = "SELECT COUNT(*) as total_pending_bills FROM jewellery_sales WHERE firm_id = ? AND payment_status IN ('Unpaid', 'Partial')";
$totalPendingBillsStmt = $conn->prepare($totalPendingBillsQuery);
$totalPendingBillsStmt->bind_param("i", $firm_id);
$totalPendingBillsStmt->execute();
$totalPendingBillsResult = $totalPendingBillsStmt->get_result()->fetch_assoc();
$totalPendingBills = $totalPendingBillsResult['total_pending_bills'] ?? 0;

// Total Staff
$totalStaffQuery = "SELECT COUNT(*) as total_staff FROM Firm_Users WHERE FirmID = ?";
$totalStaffStmt = $conn->prepare($totalStaffQuery);
$totalStaffStmt->bind_param("i", $firm_id);
$totalStaffStmt->execute();
$totalStaffResult = $totalStaffStmt->get_result()->fetch_assoc();
$totalStaff = $totalStaffResult['total_staff'] ?? 0;

// Total Suppliers (Assuming a suppliers table)
$totalSuppliers = 0; // Default
$totalSuppliersQuery = "SELECT COUNT(*) as total_suppliers FROM suppliers WHERE firm_id = ?";
$totalSuppliersStmt = $conn->prepare($totalSuppliersQuery);
$totalSuppliersStmt->bind_param("i", $firm_id);
$totalSuppliersStmt->execute();
$totalSuppliersResult = $totalSuppliersStmt->get_result()->fetch_assoc();
$totalSuppliers = $totalSuppliersResult['total_suppliers'] ?? 0;

// Total Loans (Updated to query 'loans' table)
$totalLoanSchemes = 0; // Default
$totalLoansQuery = "SELECT COUNT(*) as total_loans FROM loans WHERE firm_id = ?";
$totalLoansStmt = $conn->prepare($totalLoansQuery);
$totalLoansStmt->bind_param("i", $firm_id);
$totalLoansStmt->execute();
$totalLoansResult = $totalLoansStmt->get_result()->fetch_assoc();
$totalLoanSchemes = $totalLoansResult['total_loans'] ?? 0; // Keep variable name for consistency with HTML

// Total Bookings (Assuming a bookings table)
$totalBookings = 0; // Default
$totalBookingsQuery = "SELECT COUNT(*) as total_bookings FROM jewellery_customer_order WHERE FirmID = ?";
$totalBookingsStmt = $conn->prepare($totalBookingsQuery);
$totalBookingsStmt->bind_param("i", $firm_id);
$totalBookingsStmt->execute();
$totalBookingsResult = $totalBookingsStmt->get_result()->fetch_assoc();
$totalBookings = $totalBookingsResult['total_bookings'] ?? 0;

// --- Fetch data for Marquee --- //
$marqueeItems = [];

// Fetch recent sales for marquee
$recentSalesQuery = "SELECT grand_total, created_at FROM jewellery_sales WHERE firm_id = ? ORDER BY created_at DESC LIMIT 5";
$recentSalesStmt = $conn->prepare($recentSalesQuery);
$recentSalesStmt->bind_param("i", $firm_id);
$recentSalesStmt->execute();
$recentSalesResult = $recentSalesStmt->get_result();

while ($row = $recentSalesResult->fetch_assoc()) {
    $marqueeItems[] = [
        'text' => '💎 Recent Sale: ₹' . formatIndianAmount($row['grand_total']) . '!',
        'timestamp' => strtotime($row['created_at'])
    ];
}

// Fetch recent inventory additions for marquee (using gross_weight as a placeholder for item description)
$recentInventoryQuery = "SELECT gross_weight, created_at FROM jewellery_items WHERE firm_id = ? ORDER BY created_at DESC LIMIT 5";
$recentInventoryStmt = $conn->prepare($recentInventoryQuery);
$recentInventoryStmt->bind_param("i", $firm_id);
$recentInventoryStmt->execute();
$recentInventoryResult = $recentInventoryStmt->get_result();

while ($row = $recentInventoryResult->fetch_assoc()) {
     // Use a placeholder text if weight is 0 or null
    $weightText = ($row['gross_weight'] > 0) ? number_format($row['gross_weight'], 2) . 'g' : 'Item';
    $marqueeItems[] = [
        'text' => '📦 New Stock: ' . $weightText . ' Added!',
        'timestamp' => strtotime($row['created_at'])
    ];
}

// Sort marquee items by timestamp (most recent first)
usort($marqueeItems, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Combine texts into a single string
$marqueeText = implode(' | ', array_column($marqueeItems, 'text'));

// If no dynamic data, use a default message
if (empty($marqueeText)) {
    $marqueeText = "Welcome to JewelEntry Dashboard! Get started by adding Inventory or recording a Sale.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Jewelry Store Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/home.css">
</head>
<body class="font-poppins bg-gray-100">
    <!-- Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="fixed top-4 right-4 bg-green-500 text-white p-3 rounded shadow-lg z-[70]">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="fixed top-4 right-4 bg-red-500 text-white p-3 rounded shadow-lg z-[70]">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <header class="header-glass sticky top-0 z-50 shadow-md">
        <div class="px-3 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-9 h-9 gradient-gold rounded-xl flex items-center justify-center shadow-lg floating">
                        <i class="fas fa-gem text-white text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-sm font-bold text-gray-800"><?php echo $userInfo['FirmName']; ?></h1>
                        <p class="text-xs text-gray-600 font-medium">Powered by JewelEntry</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p id="headerUserName" class="text-sm font-bold text-gray-800"><?php echo $userInfo['Name']; ?></p>
                        <p id="headerUserRole" class="text-xs text-purple-600 font-medium"><?php echo $userInfo['Role']; ?></p>
                    </div>
                    <!-- User Profile Icon and Dropdown Trigger -->
                    <div id="userProfileMenuToggle" class="w-9 h-9 gradient-purple rounded-xl flex items-center justify-center shadow-lg overflow-hidden cursor-pointer relative">
                        <?php 
                        $defaultImage = 'public/uploads/jewelry/user.png';
                        if (!empty($userInfo['image_path']) && file_exists($userInfo['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="User Profile" class="w-full h-full object-cover">
                        <?php elseif (file_exists($defaultImage)): ?>
                            <img src="<?php echo htmlspecialchars($defaultImage); ?>" alt="Default User" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user-crown text-white text-sm"></i>
                        <?php endif; ?>

                         <!-- Logout Dropdown Menu -->
                        <div id="userLogoutDropdown" class="absolute top-11 right-0 w-32 bg-white rounded-md shadow-lg py-1 z-50 hidden">
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                   
                </div>
            </div>
        </div>
    </header>

    <!-- Marquee -->
    <div class="bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-300 overflow-hidden py-2 shadow-inner">
        <div class="marquee whitespace-nowrap">
            <span id="liveRatesMarquee" class="text-amber-900 font-bold text-sm pulse-animation">
                <?php echo htmlspecialchars($marqueeText); // Display dynamic text ?>
            </span>
        </div>
    </div>

    <div class="px-3 pb-72">
        <!-- Stats Section -->
        <div class="py-3">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-bold text-gray-800">Today's Performance</h2>
                <div class="glass-effect px-3 py-1 rounded-full">
                    <span class="text-gray-700 text-xs font-medium">Store Highlights</span>
                </div>
            </div>
            <div class="flex space-x-2 overflow-x-auto pb-1 hide-scrollbar">
                <!-- Gold Rate Card -->
                <div id="goldStatCard" class="stat-card min-w-[100px] stat-gradient-gold-rate rounded-xl px-2 py-1.5 shadow-md" data-metal-code="XAU" data-metal-name="Gold 24K">
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-coins text-amber-500 text-[11px]"></i>
                        </div>
                        <button onclick="openRateModal('Gold', '24K', 'gram', <?php echo isset($rates['Gold']) ? $rates['Gold']['rate'] : '0'; ?>)" 
                                class="text-xs text-gray-600 hover:text-gray-800">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    <p id="gold24kRate" class="text-sm font-bold text-gray-800 mt-1 rate-text">
                        ₹<?php echo isset($rates['Gold']) ? number_format($rates['Gold']['rate'], 2) : 'Set Rate'; ?>
                    </p>
                    <p class="text-[11px] text-gray-600 font-medium">
                        Gold 24K 
                        <span id="gold24kRateUnit" class="font-normal"><?php echo isset($rates['Gold']) ? '/'.$rates['Gold']['unit'] : ''; ?></span>
                        <?php if (isset($rates['Gold'])): ?>
                            <span class="text-[10px] text-gray-500"> <?php echo date('d M', strtotime($rates['Gold']['effective_date'])); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <!-- Silver Rate Card -->
                <div id="silverStatCard" class="stat-card min-w-[100px] stat-gradient-silver-rate rounded-xl px-2 py-1.5 shadow-md" data-metal-code="XAG" data-metal-name="Silver 99.9">
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-coins text-slate-500 text-[11px]"></i> 
                        </div>
                        <button onclick="openRateModal('Silver', '99.9', 'gram', <?php echo isset($rates['Silver']) ? $rates['Silver']['rate'] : '0'; ?>)" 
                                class="text-xs text-gray-600 hover:text-gray-800">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    <p id="silverRate" class="text-sm font-bold text-gray-800 mt-1 rate-text">
                        ₹<?php echo isset($rates['Silver']) ? number_format($rates['Silver']['rate'], 2) : 'Set Rate'; ?>
                    </p>
                    <p class="text-[11px] text-gray-600 font-medium">
                        Silver 
                        <span id="silverRateUnit" class="font-normal"><?php echo isset($rates['Silver']) ? '/'.$rates['Silver']['unit'] : ''; ?></span>
                        <?php if (isset($rates['Silver'])): ?>
                            <span class="text-[10px] text-gray-500"> <?php echo date('d M', strtotime($rates['Silver']['effective_date'])); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <!-- Platinum Rate Card -->
                <div id="platinumStatCard" class="stat-card min-w-[100px] stat-gradient-platinum-rate rounded-xl px-2 py-1.5 shadow-md" data-metal-code="XPT" data-metal-name="Platinum 95">
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-coins text-gray-400 text-[11px]"></i>
                        </div>
                        <button onclick="openRateModal('Platinum', '95', 'gram', <?php echo isset($rates['Platinum']) ? $rates['Platinum']['rate'] : '0'; ?>)" 
                                class="text-xs text-gray-600 hover:text-gray-800">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    <p id="platinumRate" class="text-sm font-bold text-gray-800 mt-1 rate-text">
                        ₹<?php echo isset($rates['Platinum']) ? number_format($rates['Platinum']['rate'], 2) : 'Set Rate'; ?>
                    </p>
                    <p class="text-[11px] text-gray-600 font-medium">
                        Platinum 
                        <span id="platinumRateUnit" class="font-normal"><?php echo isset($rates['Platinum']) ? '/'.$rates['Platinum']['unit'] : ''; ?></span>
                        <?php if (isset($rates['Platinum'])): ?>
                            <span class="text-[10px] text-gray-500"> <?php echo date('d M', strtotime($rates['Platinum']['effective_date'])); ?></span>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Platinum Rate Card -->
              
                <!-- Sales Stat -->
                <div class="stat-card min-w-[95px] stat-gradient-rose rounded-xl px-2 py-1.5 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-chart-line text-red-400 text-[11px]"></i>
                        </div>
                        <span class="text-[10px] text-red-500 font-bold bg-white px-1.5 py-0 rounded-full">
                            <?php echo $totalSales > 0 ? '+0%' : '0%'; // Placeholder, percentage change logic might need refinement ?>
                        </span>
                    </div>
                    <p id="today-sales-value" class="text-sm font-bold text-gray-800 mt-1"><?php echo '₹' . formatIndianAmount($totalSales); ?></p>
                    <p class="text-[11px] text-gray-700 font-medium">Sales</p>
                </div>

                <!-- Orders Stat -->
                <div class="stat-card min-w-[95px] stat-gradient-sky rounded-xl px-2 py-1.5 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-shopping-bag text-blue-400 text-[11px]"></i>
                        </div>
                        <span class="text-[10px] text-blue-500 font-bold bg-white px-1.5 py-0 rounded-full">
                            <?php echo $totalOrders > 0 ? '+' . $totalOrders : '0'; ?>
                        </span>
                    </div>
                    <p class="text-sm font-bold text-gray-800 mt-1"><?php echo $totalOrders; ?></p>
                    <p class="text-[11px] text-gray-700 font-medium">Orders</p>
                </div>

                <!-- Inventory Stats -->
                <div class="stat-card min-w-[220px] stat-gradient-emerald rounded-xl px-3 py-2 shadow-md flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-1">
                        <div class="w-7 h-7 bg-white rounded-md flex items-center justify-center shadow-sm flex-shrink-0">
                            <i class="fas fa-boxes text-green-500 text-xs"></i>
                        </div>
                        <span class="text-xs font-bold text-gray-800">Stock Movement</span>
                    </div>

                    <div class="flex justify-between text-xs mb-1 leading-tight">
                         <span class="text-green-700 font-bold">
                             +<?php echo $totalIn; ?> IN (<?php echo number_format($totalInWeight, 2); ?>g)
                         </span>
                         <span class="text-red-700 font-bold">
                             -<?php echo $totalOut; ?> OUT (<?php echo number_format($totalOutWeight, 2); ?>g)
                         </span>
                    </div>

                    <div class=" border-t border-gray-200 leading-tight">
                        <p class="text-xs font-semibold text-gray-800">
                           Available: <?php echo $totalAvailableItems; ?> Items
                        </p>
                         <p class="text-xs  text-gray-700 leading-tight">
                            <?php echo number_format($totalAvailableWeight, 2); ?> g Total
                        </p>
                    </div>
                </div>

                <!-- Customers Stat -->
                <div class="stat-card min-w-[85px] stat-gradient-violet rounded-xl px-2 py-1.5 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-users text-purple-400 text-[11px]"></i>
                        </div>
                        <span class="text-[10px] text-purple-500 font-bold bg-white px-1.5 py-0 rounded-full">
                            <?php echo $newCustomers > 0 ? '+' . $newCustomers : '0'; ?>
                        </span>
                    </div>
                    <p class="text-sm font-bold text-gray-800 mt-1"><?php echo $totalCustomers; ?></p>
                    <p class="text-[11px] text-gray-700 font-medium">Customers</p>
                </div>

                <!-- Revenue Stat -->
                <div class="stat-card min-w-[85px] stat-gradient-amber rounded-xl px-2 py-1.5 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-coins text-amber-500 text-[11px]"></i>
                        </div>
                        <span class="text-[10px] text-amber-600 font-bold bg-white px-1.5 py-0 rounded-full">+18%</span>
                    </div>
                    <p class="text-sm font-bold text-gray-800 mt-1">₹12L</p>
                    <p class="text-[11px] text-gray-700 font-medium">Revenue</p>
                </div>

                <!-- Profit Stat -->
                <div class="stat-card min-w-[85px] stat-gradient-cyan rounded-xl px-2 py-1.5 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-chart-bar text-teal-400 text-[11px]"></i>
                        </div>
                        <span class="text-[10px] text-teal-500 font-bold bg-white px-1.5 py-0 rounded-full">+25%</span>
                    </div>
                    <p class="text-sm font-bold text-gray-800 mt-1">₹4.2L</p>
                    <p class="text-[11px] text-gray-700 font-medium">Profit</p>
                </div>
            </div>
        </div>

        <!-- Featured Schemes Section -->
        <div class="py-3">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-base font-bold text-gray-800">Featured Schemes</h2>
                <a href="#" class="text-xs text-purple-600 font-medium hover:text-purple-800">View All</a>
            </div>
            <div class="flex space-x-2 overflow-x-auto pb-1 hide-scrollbar">

                <!-- Lucky Draw Scheme Card (Dynamic/Inactive) -->
                <div class="min-w-[200px] rounded-xl p-2.5 shadow-md flex flex-col justify-between <?php echo $luckyDrawScheme ? 'scheme-gradient-lottery' : 'bg-gray-200 text-gray-500'; ?>" <?php echo $luckyDrawScheme ? '' : 'style="opacity: 0.7; filter: grayscale(80%);"'; ?>>
                    <div class="flex items-center space-x-2 mb-1">
                        <div class="w-7 h-7 bg-white rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
                            <i class="fas fa-ticket-alt text-base <?php echo $luckyDrawScheme ? 'text-yellow-500' : 'text-gray-400'; ?>"></i>
                        </div>
                        <h3 class="text-sm font-bold <?php echo $luckyDrawScheme ? 'text-yellow-800' : 'text-gray-600'; ?> leading-tight">
                            <?php echo $luckyDrawScheme ? htmlspecialchars($luckyDrawScheme['scheme_name']) : 'Lucky Draw'; ?>
                        </h3>
                    </div>

                    <div class="text-xs <?php echo $luckyDrawScheme ? 'text-yellow-700' : 'text-gray-500'; ?> leading-tight">
                         <?php if ($luckyDrawScheme): ?>
                            <p class="font-medium">
                                <span class="<?php echo $luckyDrawScheme ? 'text-yellow-600' : 'text-gray-500'; ?> font-semibold"><?php echo $luckyDrawEntriesCount; ?> Joined</span>
                                <span class="mx-1">•</span>
                                <span class="text-[10px]">Ends: <?php echo date('d M Y', strtotime($luckyDrawScheme['end_date'])); ?></span>
                            </p>
                         <?php else: ?>
                            <p class="font-medium">Not Active / Set Up</p>
                            <p class="mt-0.5 text-[10px]">Available feature.</p>
                         <?php endif; ?>
                    </div>
                </div>

                
                
                <!-- Gold Saver Scheme Card (Dynamic) -->
                 <?php if ($goldSaverPlan): ?>
                     <div class="min-w-[190px] scheme-gradient-savings rounded-xl p-2 shadow-md">
                         <div class="flex items-center space-x-2">
                             <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
                                 <i class="fas fa-piggy-bank text-teal-500 text-base"></i>
                             </div>
                             <div>
                                 <h3 class="text-sm font-bold text-teal-800"><?php echo htmlspecialchars($goldSaverPlan['plan_name']); ?></h3>
                                 <p class="text-[11px] text-teal-700 leading-tight">
                                     <?php echo htmlspecialchars($goldSaverPlan['description']); ?>
                                     <span class="text-teal-600 font-medium ml-1">• <?php echo $goldSaverEnrollmentsCount; ?> Savers</span>
                                 </p>
                             </div>
                         </div>
                     </div>
                 <?php else: ?>
                 <!-- No Gold Saver Scheme Placeholder -->
                     <div class="min-w-[190px] bg-gray-100 rounded-xl p-3 shadow-md flex items-center justify-center text-center">
                         <p class="text-xs text-gray-500 font-medium">No active Gold Saving plans currently.</p>
                     </div>
                 <?php endif; ?>

                <div class="min-w-[190px] scheme-gradient-exchange rounded-xl p-2 shadow-md">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
                            <i class="fas fa-sync-alt text-blue-500 text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-blue-800">Old Gold Value</h3>
                            <p class="text-[11px] text-blue-700 leading-tight">Best Exchange Rates</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Menu Grid -->
        <div class="py-2">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-base font-bold text-gray-800">Store Management</h2>
                <div class="glass-effect px-3 py-1 rounded-full">
                    <span class="text-gray-700 text-xs font-medium">15 Modules</span>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="menu-card menu-gradient-blue rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="inventory">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-warehouse text-blue-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Inventory</h3>
                    <p class="text-xs mt-1"><span class="text-blue-500 font-bold"><?php echo $totalAvailableItems; ?> Items</span></p>
                </div>
                <div class="menu-card menu-gradient-green rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="sales">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-cash-register text-green-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Sales</h3>
                    <p class="text-xs mt-1"><span class="text-green-500 font-bold">₹<?php echo formatIndianAmount($totalSalesAllTime); ?> Total</span></p>
                </div>
                <div class="menu-card menu-gradient-purple rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="customers">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-address-book text-purple-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Customers</h3>
                    <p class="text-xs mt-1"><span class="text-purple-500 font-bold"><?php echo $totalCustomers; ?> Clients</span></p>
                </div>
                <a href="catalog.html" class="menu-card menu-gradient-amber rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="catalog">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-ring text-amber-700 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Catalog</h3>
                    <p class="text-xs mt-1"><span class="text-amber-600 font-bold">Manage Items</span></p>
                </a>
                <div class="menu-card menu-gradient-red rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="billing">
                     <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-file-invoice-dollar text-red-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Billing</h3>
                    <p class="text-xs mt-1"><span class="text-red-500 font-bold"><?php echo $totalPendingBills; ?> Pending</span></p>
                </div>
                <div class="menu-card menu-gradient-teal rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="repairs">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-tools text-teal-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Repairs</h3>
                    <p class="text-xs mt-1"><span class="text-gray-500 font-bold">Not Set Up</span></p>
                </div>
                <div class="menu-card menu-gradient-indigo rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="analytics">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-chart-pie text-indigo-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Analytics</h3>
                    <p class="text-xs mt-1"><span class="text-indigo-500 font-bold">35 Today</span></p>
                </div>
                <div class="menu-card menu-gradient-orange rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="staff">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-user-tie text-orange-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Staff</h3>
                    <p class="text-xs mt-1"><span class="text-orange-500 font-bold"><?php echo $totalStaff; ?> Active</span></p>
                </div>
                <div class="menu-card menu-gradient-gray rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="suppliers">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-truck text-gray-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Suppliers</h3>
                    <p class="text-xs mt-1"><span class="text-gray-600 font-bold"><?php echo $totalSuppliers; ?> Connected</span></p>
                </div>
                <div class="menu-card menu-gradient-yellow rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="testing">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="fas fa-vial text-yellow-700 text-xs"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-vial text-yellow-700 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Testing</h3>
                    <p class="text-xs mt-1"><span class="text-yellow-600 font-bold">Not Active</span></p>
                </div>
                <div class="menu-card menu-gradient-pink rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="security">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-shield-alt text-pink-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Security</h3>
                    <p class="text-xs mt-1"><span class="text-pink-500 font-bold">1.5K Entries</span></p>
                </div>
                <div class="menu-card menu-gradient-emerald rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="loans">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-hand-holding-usd text-emerald-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Loans</h3>
                    <p class="text-xs mt-1"><span class="text-emerald-500 font-bold"><?php echo $totalLoanSchemes; ?> Loans</span></p>
                </div>
                <div class="menu-card menu-gradient-violet rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="bookings">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-calendar-check text-violet-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Bookings</h3>
                    <p class="text-xs mt-1"><span class="text-violet-500 font-bold"><?php echo $totalBookings; ?> Appts</span></p>
                </div>
                <div class="menu-card menu-gradient-sky rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="alerts">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-bell text-blue-700 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Alerts</h3>
                    <p class="text-xs mt-1"><span class="text-blue-600 font-bold">5 Critical</span></p>
                </div>
                <div class="menu-card menu-gradient-slate rounded-2xl p-2 shadow-lg flex flex-col items-center text-center" data-module-id="settings">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-10">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-cog text-slate-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Settings</h3>
                    <p class="text-xs mt-1"><span class="text-slate-500 font-bold">All Synced</span></p>
                </div>
            </div>
        </div>

        <!-- App Credits, Support & Subscription Section -->
        <div class="pt-4"> 
            <div class="bg-gradient-to-br from-slate-100 via-gray-100 to-slate-200 p-3 rounded-xl shadow-lg">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-purple-800">Application Status</h2>
                    <span class="text-xs text-gray-500">JewelEntry v0.1</span>
                </div>
                <div id="trialStatusContainer" class="mb-3 transition-all duration-500 ease-in-out">
                    <?php
                    // Get current firm's subscription status
                    $subscriptionQuery = "SELECT fs.*, sp.name as plan_name, sp.price, sp.duration_in_days 
                                        FROM firm_subscriptions fs 
                                        JOIN subscription_plans sp ON fs.plan_id = sp.id 
                                        WHERE fs.firm_id = ? AND fs.is_active = 1 
                                        ORDER BY fs.end_date DESC LIMIT 1";
                    $subStmt = $conn->prepare($subscriptionQuery);
                    $subStmt->bind_param("i", $firm_id);
                    $subStmt->execute();
                    $subscription = $subStmt->get_result()->fetch_assoc();

                    if ($subscription && $subscription['is_trial']):
                        $endDate = new DateTime($subscription['end_date']);
                        $now = new DateTime();
                        $interval = $now->diff($endDate);
                        $daysLeft = $interval->days;
                        $isExpired = $now > $endDate;
                    ?>
                        <div class="bg-gradient-to-r from-amber-100 to-amber-50 p-2 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-amber-200 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-clock text-amber-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-amber-800">Trial Version</h3>
                                        <p class="text-xs text-amber-700">Valid until: <?php echo date('d M Y', strtotime($subscription['end_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <?php if ($isExpired): ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                                            Expired
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-medium">
                                            <?php echo $daysLeft; ?> days left
                                        </span>
                                    <?php endif; ?>
                                    <button onclick="togglePlans()" class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-medium transition-colors">
                                        <i class="fas fa-eye mr-1"></i>View Plans
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($subscription): ?>
                        <div class="bg-gradient-to-r from-green-100 to-green-50 p-2 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-green-200 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-check-circle text-green-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-green-800"><?php echo htmlspecialchars($subscription['plan_name']); ?> Plan</h3>
                                        <p class="text-xs text-green-700">Valid until: <?php echo date('d M Y', strtotime($subscription['end_date'])); ?></p>
                                    </div>
                                </div>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                    Active
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-gradient-to-r from-blue-100 to-blue-50 p-2 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-blue-200 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-info-circle text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-blue-800">No Active Subscription</h3>
                                        <p class="text-xs text-blue-700">Choose a plan to get started</p>
                                    </div>
                                </div>
                                <button onclick="togglePlans()" class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-medium transition-colors">
                                    <i class="fas fa-eye mr-1"></i>View Plans
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pricing Plans Section -->
                <div id="pricingPlansSection" class="hidden mb-3">
                    <div class="grid grid-cols-1 gap-3">
                        <?php
                        // Fetch all active plans except trial
                        $plansQuery = "SELECT * FROM subscription_plans WHERE is_active = 1 AND name != 'Trial' ORDER BY price ASC";
                        $plansResult = $conn->query($plansQuery);
                        while ($plan = $plansResult->fetch_assoc()):
                            // Duration formatting
                            $duration = (int)$plan['duration_in_days'];
                            if ($duration == 7) {
                                $durationText = '7 Days';
                            } elseif ($duration == 30) {
                                $durationText = '1 Month';
                            } elseif ($duration == 365) {
                                $durationText = '1 Year';
                            } elseif ($duration == 1095) {
                                $durationText = '3 Years';
                            } elseif ($duration % 365 == 0) {
                                $durationText = ($duration / 365) . ' Years';
                            } elseif ($duration % 30 == 0) {
                                $durationText = ($duration / 30) . ' Months';
                            } else {
                                $durationText = $duration . ' Days';
                            }
                            // Plan color
                            $color = 'blue';
                            if (stripos($plan['name'], 'Standard') !== false) $color = 'purple';
                            if (stripos($plan['name'], 'Premium') !== false) $color = 'green';
                            if (stripos($plan['name'], 'Basic') !== false) $color = 'sky';
                        ?>
                        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4 relative overflow-hidden">
                            <?php if (stripos($plan['name'], 'Standard') !== false): ?>
                                <span class="absolute top-3 right-3 bg-purple-100 text-purple-700 text-[11px] font-bold px-2 py-0.5 rounded-full shadow">Most Popular</span>
                            <?php endif; ?>
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-gem text-<?php echo $color; ?>-500 text-lg"></i>
                                    <h3 class="text-base font-bold text-<?php echo $color; ?>-700"><?php echo htmlspecialchars($plan['name']); ?> Plan</h3>
                                </div>
                                <span class="px-2 py-1 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 rounded-full text-xs font-semibold">
                                    ₹<?php echo number_format($plan['price']); ?>
                                    <span class="font-normal">/<?php echo ($duration >= 365) ? 'yr' : (($duration >= 30) ? 'mo' : 'period'); ?></span>
                                </span>
                            </div>
                            <div class="mb-2 text-xs text-gray-500 font-medium flex items-center">
                                <i class="fas fa-clock mr-1 text-<?php echo $color; ?>-400"></i> <?php echo $durationText; ?>
                            </div>
                            <ul class="text-sm text-gray-700 space-y-1 mb-3">
                                <?php foreach (explode(',', $plan['features']) as $feature): ?>
                                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> <?php echo htmlspecialchars(trim($feature)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="flex flex-col sm:flex-row gap-2 w-full">
                                <button class="w-full sm:w-1/2 py-2 bg-<?php echo $color; ?>-500 hover:bg-<?php echo $color; ?>-600 text-white rounded-lg text-sm font-semibold transition-colors shadow">
                                    Choose Plan
                                </button>
                                <a href="https://wa.me/919810359334" target="_blank" class="w-full sm:w-1/2 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-semibold flex items-center justify-center gap-2 transition-colors shadow" style="text-decoration:none;">
                                    <i class="fab fa-whatsapp text-lg"></i> WhatsApp
                                </a>
                            </div>
                            <div class="mt-3 flex items-center text-xs text-yellow-700 bg-yellow-50 rounded px-2 py-1">
                                <i class="fas fa-info-circle mr-2"></i> Terms &amp; conditions apply.
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="mt-2 text-xs text-gray-400 text-center">
                        *Terms &amp; conditions apply
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-3 gap-2">
                    <a href="tel:+919876543210" class="bg-gradient-to-r from-purple-100 to-purple-50 p-1.5 rounded-lg shadow-sm flex flex-col items-center text-center">
                        <div class="w-7 h-7 bg-purple-200 rounded-lg flex items-center justify-center mb-1">
                            <i class="fas fa-phone-alt text-purple-600 text-xs"></i>
                        </div>
                        <span class="text-xs text-purple-800 font-medium">Sales</span>
                    </a>
                    <a href="mailto:support@jewelentry.com" class="bg-gradient-to-r from-blue-100 to-blue-50 p-1.5 rounded-lg shadow-sm flex flex-col items-center text-center">
                        <div class="w-7 h-7 bg-blue-200 rounded-lg flex items-center justify-center mb-1">
                            <i class="fas fa-headset text-blue-600 text-xs"></i>
                        </div>
                        <span class="text-xs text-blue-800 font-medium">Support</span>
                    </a>
                    <a href="#" class="bg-gradient-to-r from-green-100 to-green-50 p-1.5 rounded-lg shadow-sm flex flex-col items-center text-center">
                        <div class="w-7 h-7 bg-green-200 rounded-lg flex items-center justify-center mb-1">
                            <i class="fas fa-coins text-green-600 text-xs"></i>
                        </div>
                        <span class="text-xs text-green-800 font-medium">Credits</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- JewelEntry Banner (not sticky, normal block) -->
        <div class="px-3 pt-2 pb-4">
            <div class="bg-gradient-to-r from-purple-600 via-purple-500 to-indigo-600 rounded-xl shadow-lg overflow-hidden">
                <div class="relative">
                    <!-- Decorative Elements -->
                    <div class="absolute top-0 right-0 w-24 h-24 bg-white opacity-10 rounded-full -mr-12 -mt-12"></div>
                    <div class="absolute bottom-0 left-0 w-16 h-16 bg-white opacity-10 rounded-full -ml-8 -mb-8"></div>
                    <div class="p-3 relative">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h3 class="text-white font-bold text-sm mb-1">JewelEntry</h3>
                                <p class="text-white/90 text-xs leading-tight">
                                    Manage your jewelry store with precision and ease. 
                                    <span class="font-semibold">Everything at your fingertips!</span>
                                </p>
                            </div>
                            <div class="flex items-center space-x-2 ml-3">
                                <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-gem text-white text-lg"></i>
                                </div>
                                <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-chart-line text-white text-lg"></i>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 bg-white/20 rounded-full text-white text-xs">Inventory</span>
                                <span class="px-2 py-0.5 bg-white/20 rounded-full text-white text-xs">Sales</span>
                                <span class="px-2 py-0.5 bg-white/20 rounded-full text-white text-xs">Analytics</span>
                            </div>
                            <button class="px-3 py-1 bg-white text-purple-600 rounded-lg text-xs font-semibold hover:bg-white/90 transition-colors">
                                Learn More
                            </button>
                        </div>
                        <div class="mt-2 text-right">
                            <span class="text-[11px] text-white/70">Developed & Managed by <span class="font-semibold text-white">Prosenjit Tech Hub</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Rate Modal -->
    <div id="customRateModal" role="dialog" aria-modal="true" aria-labelledby="customRateModalTitle" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-[60] hidden p-4 transition-opacity duration-300 opacity-0">
        <form id="customRateModalContent" method="POST" action="home.php" class="bg-white rounded-xl p-5 shadow-2xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300">
            <input type="hidden" name="rate_action" value="save_rate">
            <input type="hidden" id="modalMaterialType" name="material_type" value="">
            <input type="hidden" id="modalPurity" name="purity" value="">
            <div class="flex justify-between items-center mb-4">
                <h3 id="customRateModalTitle" class="text-lg font-bold text-gray-800">Set Custom Rate</h3>
                <button id="closeCustomRateModalBtn" type="button" aria-label="Close custom rate modal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Current Rate Source: 
                        <span id="currentRateSourceIndicator" class="font-semibold text-purple-600">Not Set</span>
                    </label>
                </div>
                <div>
                    <label for="customRateInput" class="block text-sm font-medium text-gray-700">Rate for <span id="customRateModalMetalNamePlaceholder">Metal</span></label>
                    <input type="number" id="customRateInput" name="rate" class="modal-input" placeholder="Enter rate">
                </div>
                <div>
                    <label for="customRateUnitSelect" class="block text-sm font-medium text-gray-700">Rate Unit</label>
                    <select id="customRateUnitSelect" name="unit" class="modal-input">
                        <option value="gram">per Gram</option>
                        <option value="10gram">per 10 Grams</option>
                        <option value="tola">per Tola</option>
                        <option value="vori">per Vori</option>
                    </select>
                </div>
                <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3 pt-3">
                    <button type="submit" id="saveCustomRateBtn" class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-md shadow-sm transition-colors">
                        <i class="fas fa-save mr-1"></i> Save Custom Rate
                    </button>
                    <!-- Added Clear Manual Rate Button -->
                    <button type="button" id="clearManualRateBtn" class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-100 border border-gray-300 rounded-md shadow-sm transition-colors">
                         <i class="fas fa-eraser mr-1"></i> Clear Manual Rate
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Enhanced Bottom Navigation -->
    <nav class="bottom-nav fixed bottom-0 left-0 right-0 shadow-xl">
        <div class="px-4 py-2">
            <div class="flex justify-around">
                <a href="index.html" data-nav-id="home" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Home</span>
                </a>
                <button data-nav-id="search" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Search</span>
                </button>
                <button data-nav-id="add" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Add</span>
                </button>
                <button data-nav-id="alerts_nav" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Alerts</span>
                </button>
                <a href="profile.html" data-nav-id="profile" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Profile</span>
                </a>
            </div>
        </div>
    </nav>

    <script type="module" src="js/home.js"></script>
</body>
</html>

<?php
// Close database connection at the end of the file
$conn->close();
?>

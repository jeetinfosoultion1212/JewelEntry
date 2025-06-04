<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

// --- Permission check for rate management ---
$allowed_roles = ['admin', 'manager', 'super admin'];
$user_role = strtolower($_SESSION['role'] ?? '');
$canEditRates = in_array($user_role, $allowed_roles);

// --- AJAX handler for rate save/clear ---
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    if (!$canEditRates) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to manage rates.']);
        exit;
    }
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $material_type = $input['material_type'] ?? '';
    $unit = $input['unit'] ?? '';
    $rate = $input['rate'] ?? '';
    $action = $input['action'] ?? '';
    $firm_id = $_SESSION['firmID'];
    $allowed_purities = [
        'Gold' => '99.99',
        'Silver' => '999.9',
        'Platinum' => '95'
    ];
    $purity = $allowed_purities[$material_type] ?? '';

    if ($action === 'save') {
        if ($material_type && $purity && $unit && is_numeric($rate) && $rate > 0) {
            $checkQuery = "SELECT id FROM jewellery_price_config WHERE firm_id = ? AND material_type = ? AND purity = ? AND unit = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("isss", $firm_id, $material_type, $purity, $unit);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            if ($existing) {
                $updateQuery = "UPDATE jewellery_price_config SET rate = ?, effective_date = CURRENT_DATE WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("di", $rate, $existing['id']);
                $updateStmt->execute();
            } else {
                $insertQuery = "INSERT INTO jewellery_price_config (firm_id, material_type, purity, unit, rate, effective_date) VALUES (?, ?, ?, ?, ?, CURRENT_DATE)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("isssd", $firm_id, $material_type, $purity, $unit, $rate);
                $insertStmt->execute();
            }
            echo json_encode(['success' => true, 'message' => 'Rate saved successfully.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Please fill all fields correctly with a valid rate.']);
            exit;
        }
    } elseif ($action === 'clear') {
        if ($material_type && $firm_id) {
            $deleteQuery = "DELETE FROM jewellery_price_config WHERE firm_id = ? AND material_type = ? AND purity = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("iss", $firm_id, $material_type, $purity);
            $deleteStmt->execute();
            echo json_encode(['success' => true, 'message' => 'Rate cleared successfully.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid material type.']);
            exit;
        }
    }
}

// Function to format number in Indian system (Lakhs, Crores)
function formatIndianAmount($num) {
    $num = (float) $num;
    if ($num < 1000) {
        return number_format($num, 0);
    } else if ($num < 100000) {
        return number_format($num, 0);
    } else if ($num < 10000000) {
        return number_format($num / 100000, 2) . 'L';
    } else {
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

// Enhanced subscription status check
$subscriptionQuery = "SELECT fs.*, sp.name as plan_name, sp.price, sp.duration_in_days, sp.features 
                    FROM firm_subscriptions fs 
                    JOIN subscription_plans sp ON fs.plan_id = sp.id 
                    WHERE fs.firm_id = ? AND fs.is_active = 1 
                    ORDER BY fs.end_date DESC LIMIT 1";
$subStmt = $conn->prepare($subscriptionQuery);
$subStmt->bind_param("i", $firm_id);
$subStmt->execute();
$subscription = $subStmt->get_result()->fetch_assoc();

// Enhanced subscription status variables
$isTrialUser = false;
$isPremiumUser = false;
$isExpired = false;
$daysRemaining = 0;
$subscriptionStatus = 'none';

if ($subscription) {
    $endDate = new DateTime($subscription['end_date']);
    $now = new DateTime();
    $isExpired = $now > $endDate;
    $daysRemaining = max(0, $now->diff($endDate)->days);
    
    if ($subscription['is_trial']) {
        $isTrialUser = true;
        $subscriptionStatus = $isExpired ? 'trial_expired' : 'trial_active';
    } else {
        $isPremiumUser = true;
        $subscriptionStatus = $isExpired ? 'premium_expired' : 'premium_active';
    }
} else {
    $subscriptionStatus = 'no_subscription';
}

// Feature access control
$hasFeatureAccess = ($isPremiumUser && !$isExpired) || ($isTrialUser && !$isExpired);

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
               AND purity IN ('99.99', '999.9', '95')
               ORDER BY effective_date DESC";
$ratesStmt = $conn->prepare($ratesQuery);
$ratesStmt->bind_param("i", $firm_id);
$ratesStmt->execute();
$ratesResult = $ratesStmt->get_result();

// Get today's date
$today = date('Y-m-d');

// Dashboard statistics (only if user has access)
$totalIn = $totalInWeight = $totalOut = $totalOutWeight = $totalSales = $totalOrders = 0;
$newCustomers = $totalCustomers = $totalAddedItems = $totalAddedWeight = 0;
$totalSoldItems = $totalSoldWeight = $totalAvailableItems = $totalAvailableWeight = 0;
$totalSalesAllTime = $totalPendingBills = $totalStaff = $totalSuppliers = 0;
$totalLoanSchemes = $totalBookings = 0;

if ($hasFeatureAccess) {
    // Get today's inventory IN count and weight
    $inQuery = "SELECT COUNT(*) as total_in, COALESCE(SUM(gross_weight), 0) as total_weight FROM jewellery_items WHERE DATE(created_at) = ? AND firm_id = ?";
    $inStmt = $conn->prepare($inQuery);
    $inStmt->bind_param("si", $today, $firm_id);
    $inStmt->execute();
    $inResult = $inStmt->get_result()->fetch_assoc();
    $totalIn = $inResult['total_in'] ?? 0;
    $totalInWeight = $inResult['total_weight'] ?? 0;

    // Get today's inventory OUT count and weight
    $outQuery = "SELECT COUNT(jsi.id) as total_out, COALESCE(SUM(jsi.gross_weight), 0) as total_weight FROM jewellery_sales_items jsi JOIN jewellery_sales js ON jsi.sale_id = js.id WHERE DATE(js.created_at) = ? AND js.firm_id = ?";
    $outStmt = $conn->prepare($outQuery);
    $outStmt->bind_param("si", $today, $firm_id);
    $outStmt->execute();
    $outResult = $outStmt->get_result()->fetch_assoc();
    $totalOut = $outResult['total_out'] ?? 0;
    $totalOutWeight = $outResult['total_weight'] ?? 0;

    // Get today's total sales amount
    $salesQuery = "SELECT SUM(grand_total) as total_sales FROM jewellery_sales WHERE DATE(created_at) = ? AND firm_id = ?";
    $salesStmt = $conn->prepare($salesQuery);
    $salesStmt->bind_param("si", $today, $firm_id);
    $salesStmt->execute();
    $salesResult = $salesStmt->get_result()->fetch_assoc();
    $totalSales = $salesResult['total_sales'] ?? 0;

    // Continue with other queries...
    $orderQuery = "SELECT COUNT(*) as total_orders FROM jewellery_sales WHERE DATE(created_at) = ? AND firm_id = ?";
    $orderStmt = $conn->prepare($orderQuery);
    $orderStmt->bind_param("si", $today, $firm_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result()->fetch_assoc();
    $totalOrders = $orderResult['total_orders'] ?? 0;

    $newCustomerQuery = "SELECT COUNT(*) as new_customers FROM customer WHERE DATE(CreatedAt) = ? AND firm_id = ?";
    $newCustomerStmt = $conn->prepare($newCustomerQuery);
    $newCustomerStmt->bind_param("si", $today, $firm_id);
    $newCustomerStmt->execute();
    $newCustomerResult = $newCustomerStmt->get_result()->fetch_assoc();
    $newCustomers = $newCustomerResult['new_customers'] ?? 0;

    $totalCustomerQuery = "SELECT COUNT(*) as total_customers FROM customer WHERE firm_id = ?";
    $totalCustomerStmt = $conn->prepare($totalCustomerQuery);
    $totalCustomerStmt->bind_param("i", $firm_id);
    $totalCustomerStmt->execute();
    $totalCustomerResult = $totalCustomerStmt->get_result()->fetch_assoc();
    $totalCustomers = $totalCustomerResult['total_customers'] ?? 0;

    // Additional statistics queries...
    $totalAddedQuery = "SELECT COUNT(*) as total_added, COALESCE(SUM(gross_weight), 0) as total_added_weight FROM jewellery_items WHERE firm_id = ?";
    $totalAddedStmt = $conn->prepare($totalAddedQuery);
    $totalAddedStmt->bind_param("i", $firm_id);
    $totalAddedStmt->execute();
    $totalAddedResult = $totalAddedStmt->get_result()->fetch_assoc();
    $totalAddedItems = $totalAddedResult['total_added'] ?? 0;
    $totalAddedWeight = $totalAddedResult['total_added_weight'] ?? 0;

    $totalSoldQuery = "SELECT COUNT(jsi.id) as total_sold, COALESCE(SUM(jsi.gross_weight), 0) as total_sold_weight FROM jewellery_sales_items jsi JOIN jewellery_sales js ON jsi.sale_id = js.id WHERE js.firm_id = ?";
    $totalSoldStmt = $conn->prepare($totalSoldQuery);
    $totalSoldStmt->bind_param("i", $firm_id);
    $totalSoldStmt->execute();
    $totalSoldResult = $totalSoldStmt->get_result()->fetch_assoc();
    $totalSoldItems = $totalSoldResult['total_sold'] ?? 0;
    $totalSoldWeight = $totalSoldResult['total_sold_weight'] ?? 0;

    $availableStockQuery = "SELECT COUNT(*) as total_available, COALESCE(SUM(gross_weight), 0) as total_available_weight FROM jewellery_items WHERE status != 'Sold' AND firm_id = ?";
    $availableStockStmt = $conn->prepare($availableStockQuery);
    $availableStockStmt->bind_param("i", $firm_id);
    $availableStockStmt->execute();
    $availableStockResult = $availableStockStmt->get_result()->fetch_assoc();
    $totalAvailableItems = $availableStockResult['total_available'] ?? 0;
    $totalAvailableWeight = $availableStockResult['total_available_weight'] ?? 0;

    $totalSalesAllTimeQuery = "SELECT COALESCE(SUM(grand_total), 0) as total_sales_all_time FROM jewellery_sales WHERE firm_id = ?";
    $totalSalesAllTimeStmt = $conn->prepare($totalSalesAllTimeQuery);
    $totalSalesAllTimeStmt->bind_param("i", $firm_id);
    $totalSalesAllTimeStmt->execute();
    $totalSalesAllTimeResult = $totalSalesAllTimeStmt->get_result()->fetch_assoc();
    $totalSalesAllTime = $totalSalesAllTimeResult['total_sales_all_time'] ?? 0;

    $totalPendingBillsQuery = "SELECT COUNT(*) as total_pending_bills FROM jewellery_sales WHERE firm_id = ? AND payment_status IN ('Unpaid', 'Partial')";
    $totalPendingBillsStmt = $conn->prepare($totalPendingBillsQuery);
    $totalPendingBillsStmt->bind_param("i", $firm_id);
    $totalPendingBillsStmt->execute();
    $totalPendingBillsResult = $totalPendingBillsStmt->get_result()->fetch_assoc();
    $totalPendingBills = $totalPendingBillsResult['total_pending_bills'] ?? 0;

    $totalStaffQuery = "SELECT COUNT(*) as total_staff FROM Firm_Users WHERE FirmID = ?";
    $totalStaffStmt = $conn->prepare($totalStaffQuery);
    $totalStaffStmt->bind_param("i", $firm_id);
    $totalStaffStmt->execute();
    $totalStaffResult = $totalStaffStmt->get_result()->fetch_assoc();
    $totalStaff = $totalStaffResult['total_staff'] ?? 0;

    $totalSuppliersQuery = "SELECT COUNT(*) as total_suppliers FROM suppliers WHERE firm_id = ?";
    $totalSuppliersStmt = $conn->prepare($totalSuppliersQuery);
    $totalSuppliersStmt->bind_param("i", $firm_id);
    $totalSuppliersStmt->execute();
    $totalSuppliersResult = $totalSuppliersStmt->get_result()->fetch_assoc();
    $totalSuppliers = $totalSuppliersResult['total_suppliers'] ?? 0;

    $totalLoansQuery = "SELECT COUNT(*) as total_loans FROM loans WHERE firm_id = ?";
    $totalLoansStmt = $conn->prepare($totalLoansQuery);
    $totalLoansStmt->bind_param("i", $firm_id);
    $totalLoansStmt->execute();
    $totalLoansResult = $totalLoansStmt->get_result()->fetch_assoc();
    $totalLoanSchemes = $totalLoansResult['total_loans'] ?? 0;

    $totalBookingsQuery = "SELECT COUNT(*) as total_bookings FROM jewellery_customer_order WHERE FirmID = ?";
    $totalBookingsStmt = $conn->prepare($totalBookingsQuery);
    $totalBookingsStmt->bind_param("i", $firm_id);
    $totalBookingsStmt->execute();
    $totalBookingsResult = $totalBookingsStmt->get_result()->fetch_assoc();
    $totalBookings = $totalBookingsResult['total_bookings'] ?? 0;
}

$rates = [];
while ($row = $ratesResult->fetch_assoc()) {
    $rates[$row['material_type']] = $row;
}

// Fetch schemes data (only if user has access)
$luckyDrawSchemes = [];
$goldSaverPlans = [];

if ($hasFeatureAccess) {
    $luckyDrawQuery = "SELECT id, scheme_name, start_date, end_date, status FROM schemes WHERE firm_id = ? AND scheme_type = 'lucky_draw' AND status = 'active'";
    $luckyDrawStmt = $conn->prepare($luckyDrawQuery);
    $luckyDrawStmt->bind_param("i", $firm_id);
    $luckyDrawStmt->execute();
    $luckyDrawResult = $luckyDrawStmt->get_result();

    while ($row = $luckyDrawResult->fetch_assoc()) {
        // Fetch total entries for each lucky draw scheme
        $entriesQuery = "SELECT COUNT(DISTINCT customer_id) as total_entries FROM scheme_entries WHERE scheme_id = ?";
        $entriesStmt = $conn->prepare($entriesQuery);
        $entriesStmt->bind_param("i", $row['id']);
        $entriesStmt->execute();
        $entriesResult = $entriesStmt->get_result()->fetch_assoc();
        $row['total_entries'] = $entriesResult['total_entries'] ?? 0;
        $luckyDrawSchemes[] = $row;
    }

    $goldSaverQuery = "SELECT id, plan_name, description, status FROM gold_saving_plans WHERE firm_id = ? AND status = 'active'";
    $goldSaverStmt = $conn->prepare($goldSaverQuery);
    $goldSaverStmt->bind_param("i", $firm_id);
    $goldSaverStmt->execute();
    $goldSaverResult = $goldSaverStmt->get_result();

    while ($row = $goldSaverResult->fetch_assoc()) {
        // Optionally fetch enrollments for gold saver plans if needed
        // $enrollmentsQuery = "SELECT COUNT(*) as total_enrollments FROM gold_saver_enrollments WHERE plan_id = ?";
        // $enrollmentsStmt = $conn->prepare($enrollmentsQuery);
        // $enrollmentsStmt->bind_param("i", $row['id']);
        // $enrollmentsStmt->execute();
        // $enrollmentsResult = $enrollmentsStmt->get_result()->fetch_assoc();
        // $row['total_enrollments'] = $enrollmentsResult['total_enrollments'] ?? 0;
        $goldSaverPlans[] = $row;
    }
}

// Marquee data
$marqueeItems = [];
$marqueeText = "👋 Welcome to JewelEntry! Your all-in-one jewelry store management app.";

if ($hasFeatureAccess) {
    $recentSalesQuery = "SELECT js.grand_total, js.created_at, GROUP_CONCAT(jsi.product_name) as items 
                        FROM jewellery_sales js 
                        LEFT JOIN jewellery_sales_items jsi ON js.id = jsi.sale_id 
                        WHERE js.firm_id = ? 
                        GROUP BY js.id 
                        ORDER BY js.created_at DESC 
                        LIMIT 5";
    $recentSalesStmt = $conn->prepare($recentSalesQuery);
    $recentSalesStmt->bind_param("i", $firm_id);
    $recentSalesStmt->execute();
    $recentSalesResult = $recentSalesStmt->get_result();

    while ($row = $recentSalesResult->fetch_assoc()) {
        $items = isset($row['items']) ? explode(',', $row['items']) : [];
        $itemText = count($items) > 1 ? $items[0] . ' & more' : (isset($items[0]) ? $items[0] : 'Item');
        $marqueeItems[] = [
            'text' => '💎 Recent Sale: ' . htmlspecialchars($itemText) . ' - ₹' . formatIndianAmount($row['grand_total']),
            'timestamp' => strtotime($row['created_at'])
        ];
    }

    $recentInventoryQuery = "SELECT product_name, gross_weight, created_at 
                            FROM jewellery_items 
                            WHERE firm_id = ?
                            ORDER BY created_at DESC 
                            LIMIT 5";
    $recentInventoryStmt = $conn->prepare($recentInventoryQuery);
    $recentInventoryStmt->bind_param("i", $firm_id);
    $recentInventoryStmt->execute();
    $recentInventoryResult = $recentInventoryStmt->get_result();

    while ($row = $recentInventoryResult->fetch_assoc()) {
        $weightText = (isset($row['gross_weight']) && $row['gross_weight'] > 0) ? number_format($row['gross_weight'], 2) . 'g' : '';
        $productName = isset($row['product_name']) ? htmlspecialchars($row['product_name']) : 'Item';
        $marqueeItems[] = [
            'text' => '✨ New Stock: ' . $productName . ($weightText ? ' (' . $weightText . ')' : ''),
            'timestamp' => isset($row['created_at']) ? strtotime($row['created_at']) : time()
        ];
    }

    usort($marqueeItems, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    $marqueeText = implode(' | ', array_column($marqueeItems, 'text'));
}

if (empty(trim($marqueeText))) {
    $marqueeText = "👋 Welcome to JewelEntry! Your all-in-one jewelry store management app.";
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
                    <?php if ($hasFeatureAccess): ?>
                    <a href="profile.php" class="w-9 h-9 gradient-purple rounded-xl flex items-center justify-center shadow-lg overflow-hidden cursor-pointer relative transition-transform duration-200">
                        <?php 
                        $defaultImage = 'public/uploads/user.png';
                        if (!empty($userInfo['image_path']) && file_exists($userInfo['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="User Profile" class="w-full h-full object-cover">
                        <?php elseif (file_exists($defaultImage)): ?>
                            <img src="<?php echo htmlspecialchars($defaultImage); ?>" alt="Default User" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user-crown text-white text-sm"></i>
                        <?php endif; ?>
                    </a>
                    <?php else: ?>
                    <div class="w-9 h-9 gradient-purple rounded-xl flex items-center justify-center shadow-lg overflow-hidden cursor-pointer relative transition-transform duration-200" onclick="showFeatureLockedModal()">
                        <i class="fas fa-user-crown text-white text-sm"></i>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Marquee -->
    <div class="bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-300 overflow-hidden py-2 shadow-inner">
        <div class="marquee whitespace-nowrap">
            <span id="liveRatesMarquee" class="text-amber-900 font-bold text-sm pulse-animation">
                <?php echo htmlspecialchars($marqueeText); ?>
            </span>
        </div>
    </div>

    <div class="px-3 pb-72">
        <!-- Enhanced Subscription Status Alert -->
        <?php if ($subscriptionStatus === 'trial_expired' || $subscriptionStatus === 'premium_expired'): ?>
            <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-red-800">
                            <?php echo $isTrialUser ? 'Trial Expired' : 'Subscription Expired'; ?>
                        </h3>
                        <p class="mt-1 text-sm text-red-700">
                            Your <?php echo $isTrialUser ? 'trial period' : 'subscription'; ?> has ended. 
                            <?php if ($isTrialUser): ?>
                                Upgrade to continue using all features.
                            <?php else: ?>
                                Please renew your subscription to continue.
                            <?php endif; ?>
                        </p>
                        <div class="mt-3 flex space-x-3">
                            <button onclick="showUpgradeModal()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                                <i class="fas fa-rocket mr-2"></i>Upgrade Now
                            </button>
                            <a href="https://wa.me/919810359334" target="_blank" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors inline-flex items-center">
                                <i class="fab fa-whatsapp mr-2"></i>Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($subscriptionStatus === 'trial_active' && $daysRemaining <= 2): ?>
            <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-yellow-400 text-xl"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-yellow-800">Trial Ending Soon</h3>
                        <p class="mt-1 text-sm text-yellow-700">
                            Your trial expires in <?php echo $daysRemaining; ?> day<?php echo $daysRemaining != 1 ? 's' : ''; ?>. 
                            Upgrade now to continue enjoying all features.
                        </p>
                        <div class="mt-3">
                            <button onclick="showUpgradeModal()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                                <i class="fas fa-star mr-2"></i>View Premium Plans
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Section -->
        <div class="py-3">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-bold text-gray-800">Today's Performance</h2>
                <div class="glass-effect px-3 py-1 rounded-full">
                    <span class="text-gray-700 text-xs font-medium">
                        <?php echo $hasFeatureAccess ? 'Live Data' : 'Limited Access'; ?>
                    </span>
                </div>
            </div>
            <div class="flex space-x-2 overflow-x-auto pb-1 hide-scrollbar">
                <!-- Gold Rate Card -->
                <div id="goldStatCard" class="stat-card min-w-[100px] stat-gradient-gold-rate rounded-xl px-2 py-1.5 shadow-md <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-metal-code="XAU" data-metal-name="Gold 99.99" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-coins text-amber-500 text-[11px]"></i>
                        </div>
                        <?php if ($hasFeatureAccess && $canEditRates): ?>
                            <button class="rate-edit-btn" onclick="openRateModal('Gold', '99.99', <?php echo isset($rates['Gold']) ? $rates['Gold']['rate'] : '0'; ?>)" 
                                    class="text-xs text-gray-600 hover:text-gray-800">
                                <i class="fas fa-edit"></i>
                            </button>
                        <?php else: ?>
                            <i class="fas fa-lock text-gray-400 text-xs"></i>
                        <?php endif; ?>
                    </div>
                    <p id="gold24kRate" class="text-sm font-bold text-gray-800 mt-1 rate-text">
                        <?php echo $hasFeatureAccess ? (isset($rates['Gold']) ? '₹' . number_format($rates['Gold']['rate'], 2) : 'Set Rate') : '₹****'; ?>
                    </p>
                    <p class="text-[11px] text-gray-600 font-medium">
                        Gold 99.99 
                        <span id="gold24kRateUnit" class="font-normal"><?php echo $hasFeatureAccess && isset($rates['Gold']) ? '/'.$rates['Gold']['unit'] : ''; ?></span>
                        <?php if ($hasFeatureAccess && isset($rates['Gold'])): ?>
                            <span class="text-[10px] text-gray-500"> <?php echo date('d M', strtotime($rates['Gold']['effective_date'])); ?></span>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Silver Rate Card -->
                <div id="silverStatCard" class="stat-card min-w-[100px] stat-gradient-silver-rate rounded-xl px-2 py-1.5 shadow-md <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-metal-code="XAG" data-metal-name="Silver 999.9" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-coins text-slate-500 text-[11px]"></i> 
                        </div>
                        <?php if ($hasFeatureAccess && $canEditRates): ?>
                            <button class="rate-edit-btn" onclick="openRateModal('Silver', '999.9', <?php echo isset($rates['Silver']) ? $rates['Silver']['rate'] : '0'; ?>)" 
                                    class="text-xs text-gray-600 hover:text-gray-800">
                                <i class="fas fa-edit"></i>
                            </button>
                        <?php else: ?>
                            <i class="fas fa-lock text-gray-400 text-xs"></i>
                        <?php endif; ?>
                    </div>
                    <p id="silverRate" class="text-sm font-bold text-gray-800 mt-1 rate-text">
                        <?php echo $hasFeatureAccess ? (isset($rates['Silver']) ? '₹' . number_format($rates['Silver']['rate'], 2) : 'Set Rate') : '₹****'; ?>
                    </p>
                    <p class="text-[11px] text-gray-600 font-medium">
                        Silver 999.9 
                        <span id="silverRateUnit" class="font-normal"><?php echo $hasFeatureAccess && isset($rates['Silver']) ? '/'.$rates['Silver']['unit'] : ''; ?></span>
                        <?php if ($hasFeatureAccess && isset($rates['Silver'])): ?>
                            <span class="text-[10px] text-gray-500"> <?php echo date('d M', strtotime($rates['Silver']['effective_date'])); ?></span>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Sales Stat -->
                <div class="stat-card min-w-[95px] stat-gradient-rose rounded-xl px-2 py-1.5 shadow-md <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-chart-line text-red-400 text-[11px]"></i>
                        </div>
                        <span class="text-[10px] text-red-500 font-bold bg-white px-1.5 py-0 rounded-full">
                            <?php echo $hasFeatureAccess ? ($totalSales > 0 ? '+0%' : '0%') : '**%'; ?>
                        </span>
                    </div>
                    <p id="today-sales-value" class="text-sm font-bold text-gray-800 mt-1">
                        <?php echo $hasFeatureAccess ? '₹' . formatIndianAmount($totalSales) : '₹****'; ?>
                    </p>
                    <p class="text-[11px] text-gray-700 font-medium">Sales</p>
                </div>

                <!-- Orders Stat -->
                <div class="stat-card min-w-[95px] stat-gradient-sky rounded-xl px-2 py-1.5 shadow-md <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-shopping-bag text-blue-400 text-[11px]"></i>
                        </div>
                        <span class="text-[10px] text-blue-500 font-bold bg-white px-1.5 py-0 rounded-full">
                            <?php echo $hasFeatureAccess ? ($totalOrders > 0 ? '+' . $totalOrders : '0') : '**'; ?>
                        </span>
                    </div>
                    <p class="text-sm font-bold text-gray-800 mt-1"><?php echo $hasFeatureAccess ? $totalOrders : '**'; ?></p>
                    <p class="text-[11px] text-gray-700 font-medium">Orders</p>
                </div>

                <!-- Inventory Stats -->
                <div class="stat-card min-w-[220px] stat-gradient-emerald rounded-xl px-3 py-2 shadow-md flex flex-col justify-between <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <div class="flex items-center justify-between mb-1">
                        <div class="w-7 h-7 bg-white rounded-md flex items-center justify-center shadow-sm flex-shrink-0">
                            <i class="fas fa-boxes text-green-500 text-xs"></i>
                        </div>
                        <span class="text-xs font-bold text-gray-800">Stock Movement</span>
                    </div>

                    <div class="flex justify-between text-xs mb-1 leading-tight">
                         <span class="text-green-700 font-bold">
                             +<?php echo $hasFeatureAccess ? $totalIn : '**'; ?> IN (<?php echo $hasFeatureAccess ? number_format($totalInWeight, 2) : '**'; ?>g)
                         </span>
                         <span class="text-red-700 font-bold">
                             -<?php echo $hasFeatureAccess ? $totalOut : '**'; ?> OUT (<?php echo $hasFeatureAccess ? number_format($totalOutWeight, 2) : '**'; ?>g)
                         </span>
                    </div>

                    <div class="border-t border-gray-200 leading-tight">
                        <p class="text-xs font-semibold text-gray-800">
                           Available: <?php echo $hasFeatureAccess ? $totalAvailableItems : '**'; ?> Items
                        </p>
                         <p class="text-xs text-gray-700 leading-tight">
                            <?php echo $hasFeatureAccess ? number_format($totalAvailableWeight, 2) : '**'; ?> g Total
                        </p>
                    </div>
                </div>

                <!-- Customers Stat -->
                <div class="stat-card min-w-[85px] stat-gradient-violet rounded-xl px-2 py-1.5 shadow-md <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-users text-purple-400 text-[11px]"></i>
                        </div>
                        <span class="text-[10px] text-purple-500 font-bold bg-white px-1.5 py-0 rounded-full">
                            <?php echo $hasFeatureAccess ? ($newCustomers > 0 ? '+' . $newCustomers : '0') : '**'; ?>
                        </span>
                    </div>
                    <p class="text-sm font-bold text-gray-800 mt-1"><?php echo $hasFeatureAccess ? $totalCustomers : '**'; ?></p>
                    <p class="text-[11px] text-gray-700 font-medium">Customers</p>
                </div>
            </div>
        </div>
        <div class="py-3">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-base font-bold text-gray-800">Featured Schemes</h2>
                <a href="#" class="text-xs text-purple-600 font-medium hover:text-purple-800">View All</a>
            </div>
            <div class="flex space-x-2 overflow-x-auto pb-1 hide-scrollbar">

                <?php foreach ($luckyDrawSchemes as $scheme):
                    $today = new DateTime();
                    $startDate = new DateTime($scheme['start_date']);
                    $endDate = new DateTime($scheme['end_date']);
                    $statusText = '';
                    $statusColor = 'gray';
                    $iconClass = 'fas fa-ticket-alt';

                    if ($today < $startDate) {
                        $statusText = 'Upcoming';
                        $statusColor = 'blue';
                        $iconClass = 'fas fa-calendar-alt';
                    } elseif ($today >= $startDate && $today <= $endDate) {
                        $statusText = 'Ongoing';
                        $statusColor = 'yellow';
                        $iconClass = 'fas fa-ticket-alt';
                    } else {
                        $statusText = 'Ended';
                        $statusColor = 'red';
                        $iconClass = 'fas fa-archive';
                    }
                ?>
                <!-- Lucky Draw Scheme Card -->
                <div class="min-w-[200px] rounded-xl p-2.5 shadow-md flex flex-col justify-between scheme-gradient-lottery text-yellow-800 <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <div class="flex items-center space-x-2 mb-1">
                        <div class="w-7 h-7 bg-white rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
                            <i class="<?php echo $iconClass; ?> text-base text-<?php echo $statusColor; ?>-500"></i>
                        </div>
                        <h3 class="text-sm font-bold leading-tight"><?php echo htmlspecialchars($scheme['scheme_name']); ?></h3>
                    </div>

                    <div class="text-xs text-<?php echo $statusColor; ?>-700 leading-tight">
                         <p class="font-medium">
                            <span class="text-<?php echo $statusColor; ?>-600 font-semibold"><?php echo $scheme['total_entries']; ?> Joined</span>
                            <span class="mx-1">•</span>
                            <span class="text-[10px]">Status: <?php echo $statusText; ?></span>
                         </p>
                         <p class="mt-0.5 text-[10px] text-gray-600">
                            <?php if ($statusText === 'Ongoing'): ?>
                                Ends: <?php echo date('d M Y', strtotime($scheme['end_date'])); ?>
                            <?php elseif ($statusText === 'Upcoming'): ?>
                                Starts: <?php echo date('d M Y', strtotime($scheme['start_date'])); ?>
                            <?php else: ?>
                                Ended: <?php echo date('d M Y', strtotime($scheme['end_date'])); ?>
                            <?php endif; ?>
                         </p>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php foreach ($goldSaverPlans as $plan): ?>
                <!-- Gold Saver Scheme Card -->
                 <div class="min-w-[190px] scheme-gradient-savings rounded-xl p-2 shadow-md <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                         <div class="flex items-center space-x-2">
                             <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
                                 <i class="fas fa-piggy-bank text-teal-500 text-base"></i>
                             </div>
                             <div>
                                 <h3 class="text-sm font-bold text-teal-800"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                 <p class="text-[11px] text-teal-700 leading-tight">
                                     <?php echo htmlspecialchars($plan['description']); ?>
                                     <?php // if (isset($plan['total_enrollments'])): ?>
                                     <?php // endif; ?>
                                 </p>
                             </div>
                         </div>
                     </div>
                <?php endforeach; ?>

                <!-- No Schemes/Plans Placeholder -->
                 <?php if (empty($luckyDrawSchemes) && empty($goldSaverPlans)): ?>
                     <div class="min-w-[250px] bg-gray-100 rounded-xl p-3 shadow-md flex items-center justify-center text-center">
                         <p class="text-xs text-gray-500 font-medium">No active schemes or plans available currently.</p>
                     </div>
                 <?php endif; ?>

                <div class="min-w-[190px] scheme-gradient-exchange rounded-xl p-2 shadow-md <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
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
        <!-- Enhanced Menu Grid with Feature Locking -->
        <div class="py-2">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-base font-bold text-gray-800">Store Management</h2>
                <div class="glass-effect px-3 py-1 rounded-full">
                    <span class="text-gray-700 text-xs font-medium">
                        <?php echo $hasFeatureAccess ? '15 Modules' : 'Limited Access'; ?>
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <!-- Inventory Module -->
                <?php if ($hasFeatureAccess): ?>
                <a href="add.php" class="menu-card menu-gradient-blue rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-warehouse text-blue-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Inventory</h3>
                    <p class="text-xs mt-1"><span class="text-blue-500 font-bold"><?php echo $totalAvailableItems; ?> Items</span></p>
                </a>
                <?php else: ?>
                <div class="menu-card menu-gradient-blue rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative opacity-50" onclick="showFeatureLockedModal()">
                    <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                        <i class="fas fa-lock text-white text-lg"></i>
                    </div>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-warehouse text-blue-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Inventory</h3>
                    <p class="text-xs mt-1"><span class="text-blue-500 font-bold">** Items</span></p>
                </div>
                <?php endif; ?>

                <!-- Sales Module -->
                <?php if ($hasFeatureAccess): ?>
                <a href="sale-entry.php" class="menu-card menu-gradient-green rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-cash-register text-green-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Sales</h3>
                    <p class="text-xs mt-1"><span class="text-green-500 font-bold">₹<?php echo formatIndianAmount($totalSalesAllTime); ?> Total</span></p>
                </a>
                <?php else: ?>
                <div class="menu-card menu-gradient-green rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative opacity-50" onclick="showFeatureLockedModal()">
                    <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                        <i class="fas fa-lock text-white text-lg"></i>
                    </div>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-cash-register text-green-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Sales</h3>
                    <p class="text-xs mt-1"><span class="text-green-500 font-bold">₹**** Total</span></p>
                </div>
                <?php endif; ?>

                <!-- Customers Module -->
                <?php if ($hasFeatureAccess): ?>
                <a href="customers.php" class="menu-card menu-gradient-purple rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-address-book text-purple-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Customers</h3>
                    <p class="text-xs mt-1"><span class="text-purple-500 font-bold"><?php echo $totalCustomers; ?> Clients</span></p>
                </a>
                <?php else: ?>
                <div class="menu-card menu-gradient-purple rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative opacity-50" onclick="showFeatureLockedModal()">
                    <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                        <i class="fas fa-lock text-white text-lg"></i>
                    </div>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-address-book text-purple-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Customers</h3>
                    <p class="text-xs mt-1"><span class="text-purple-500 font-bold">** Clients</span></p>
                </div>
                <?php endif; ?>

                <!-- Catalog Module -->
                <?php if ($hasFeatureAccess): ?>
                <a href="catalog.php" class="menu-card menu-gradient-amber rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-ring text-amber-700 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Catalog</h3>
                    <p class="text-xs mt-1"><span class="text-amber-600 font-bold">Manage Items</span></p>
                </a>
                <?php else: ?>
                <div class="menu-card menu-gradient-amber rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative opacity-50" onclick="showFeatureLockedModal()">
                    <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                        <i class="fas fa-lock text-white text-lg"></i>
                    </div>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-ring text-amber-700 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Catalog</h3>
                    <p class="text-xs mt-1"><span class="text-amber-600 font-bold">Manage Items</span></p>
                </div>
                <?php endif; ?>

                <!-- Add similar structure for remaining modules... -->
                <!-- For brevity, I'll add a few more key modules -->
                
                <div class="menu-card menu-gradient-red rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-module-id="billing" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <?php if (!$hasFeatureAccess): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                            <i class="fas fa-lock text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-file-invoice-dollar text-red-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Billing</h3>
                    <p class="text-xs mt-1"><span class="text-red-500 font-bold"><?php echo $hasFeatureAccess ? $totalPendingBills : '**'; ?> Pending</span></p>
                </div>

                <div class="menu-card menu-gradient-teal rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-module-id="repairs" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <?php if (!$hasFeatureAccess): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                            <i class="fas fa-lock text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-tools text-teal-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Repairs</h3>
                    <p class="text-xs mt-1"><span class="text-gray-500 font-bold">Premium Feature</span></p>
                </div>

                <!-- Gold Loan Module -->
                <div class="menu-card menu-gradient-amber rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-module-id="gold_loan" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <?php if (!$hasFeatureAccess): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                            <i class="fas fa-lock text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-hand-holding-usd text-amber-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Gold Loan</h3>
                    <p class="text-xs mt-1"><span class="text-amber-600 font-bold"><?php echo $hasFeatureAccess ? $totalLoanSchemes : '**'; ?> Schemes</span></p>
                </div>

                <!-- Bookings Module -->
                <div class="menu-card menu-gradient-indigo rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-module-id="bookings" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <?php if (!$hasFeatureAccess): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                            <i class="fas fa-lock text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-calendar-check text-indigo-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Bookings</h3>
                    <p class="text-xs mt-1"><span class="text-indigo-600 font-bold"><?php echo $hasFeatureAccess ? $totalBookings : '**'; ?> Orders</span></p>
                </div>

                <!-- Suppliers Module -->
                <div class="menu-card menu-gradient-orange rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-module-id="suppliers" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <?php if (!$hasFeatureAccess): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                            <i class="fas fa-lock text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-truck text-orange-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Suppliers</h3>
                    <p class="text-xs mt-1"><span class="text-orange-600 font-bold"><?php echo $hasFeatureAccess ? $totalSuppliers : '**'; ?> Vendors</span></p>
                </div>

                <!-- Staff Module -->
                <div class="menu-card menu-gradient-pink rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-module-id="staff" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <?php if (!$hasFeatureAccess): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                            <i class="fas fa-lock text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-user-tie text-pink-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Staff</h3>
                    <p class="text-xs mt-1"><span class="text-pink-600 font-bold"><?php echo $hasFeatureAccess ? $totalStaff : '**'; ?> Members</span></p>
                </div>

                <!-- Reports Module -->
                <div class="menu-card menu-gradient-cyan rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-module-id="reports" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <?php if (!$hasFeatureAccess): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                            <i class="fas fa-lock text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-chart-bar text-cyan-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Reports</h3>
                    <p class="text-xs mt-1"><span class="text-cyan-600 font-bold">Analytics</span></p>
                </div>

                <!-- Settings Module -->
                <div class="menu-card menu-gradient-gray rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative <?php echo !$hasFeatureAccess ? 'opacity-50' : ''; ?>" 
                     data-module-id="settings" <?php echo !$hasFeatureAccess ? 'onclick="showFeatureLockedModal()"' : ''; ?>>
                    <?php if (!$hasFeatureAccess): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                            <i class="fas fa-lock text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-cog text-gray-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Settings</h3>
                    <p class="text-xs mt-1"><span class="text-gray-600 font-bold">Configure</span></p>
                </div>
            </div>
        </div>

        <!-- New Menu Modules -->
        <div class="py-2">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-base font-bold text-gray-800">Promotions & Schemes</h2>
                <div class="glass-effect px-3 py-1 rounded-full">
                    <span class="text-gray-700 text-xs font-medium">
                        New Modules
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <!-- Schemes Module -->
                <?php if ($hasFeatureAccess): ?>
                <a href="schemes.php" class="menu-card menu-gradient-yellow rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-tags text-yellow-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Schemes</h3>
                    <p class="text-xs mt-1"><span class="text-yellow-600 font-bold">Manage Offers</span></p>
                </a>
                <?php else: ?>
                <div class="menu-card menu-gradient-yellow rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative opacity-50" onclick="showFeatureLockedModal()">
                    <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                        <i class="fas fa-lock text-white text-lg"></i>
                    </div>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-tags text-yellow-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Schemes</h3>
                    <p class="text-xs mt-1"><span class="text-yellow-600 font-bold">Manage Offers</span></p>
                </div>
                <?php endif; ?>

                <!-- Lucky Draw Module -->
                <?php if ($hasFeatureAccess): ?>
                <a href="lucky_draw.php" class="menu-card menu-gradient-red rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-dice text-red-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Lucky Draw</h3>
                    <p class="text-xs mt-1"><span class="text-red-600 font-bold">View Entries</span></p>
                </a>

                <!-- Expense Module -->
                <a href="expenses.php" class="menu-card menu-gradient-purple rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative">
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-receipt text-purple-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Expenses</h3>
                    <p class="text-xs mt-1"><span class="text-purple-600 font-bold">Track Expenses</span></p>
                </a>
                <?php else: ?>
                <div class="menu-card menu-gradient-red rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative opacity-50" onclick="showFeatureLockedModal()">
                    <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                        <i class="fas fa-lock text-white text-lg"></i>
                    </div>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-dice text-red-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Lucky Draw</h3>
                    <p class="text-xs mt-1"><span class="text-red-600 font-bold">View Entries</span></p>
                </div>
                <div class="menu-card menu-gradient-purple rounded-2xl p-2 shadow-lg flex flex-col items-center text-center relative opacity-50" onclick="showFeatureLockedModal()">
                    <div class="absolute inset-0 bg-black bg-opacity-20 rounded-2xl flex items-center justify-center z-10">
                        <i class="fas fa-lock text-white text-lg"></i>
                    </div>
                    <button aria-label="Toggle favorite" aria-pressed="false" class="favorite-btn absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-yellow-500 focus:outline-none z-20">
                        <i class="far fa-star text-base"></i>
                    </button>
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-receipt text-purple-600 text-xs"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 text-xs mt-1">Expenses</h3>
                    <p class="text-xs mt-1"><span class="text-purple-600 font-bold">Track Expenses</span></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enhanced Subscription Section -->
        <div class="pt-4"> 
            <div class="bg-gradient-to-br from-slate-100 via-gray-100 to-slate-200 p-3 rounded-xl shadow-lg">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-purple-800">Subscription Status</h2>
                    <span class="text-xs text-gray-500">JewelEntry v2.0</span>
                </div>
                
                <div id="subscriptionStatusContainer" class="mb-3">
                    <?php if ($subscriptionStatus === 'trial_active'): ?>
                        <div class="bg-gradient-to-r from-amber-100 to-amber-50 p-3 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-amber-200 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-clock text-amber-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-amber-800">Trial Version</h3>
                                        <p class="text-xs text-amber-700">
                                            <?php echo $daysRemaining; ?> days remaining
                                        </p>
                                    </div>
                                </div>
                                <button onclick="showUpgradeModal()" class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-medium transition-colors">
                                    <i class="fas fa-star mr-1"></i>Upgrade
                                </button>
                            </div>
                        </div>
                    <?php elseif ($subscriptionStatus === 'premium_active'): ?>
                        <div class="bg-gradient-to-r from-green-100 to-green-50 p-3 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-green-200 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-check-circle text-green-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-green-800"><?php echo htmlspecialchars($subscription['plan_name']); ?> Plan</h3>
                                        <p class="text-xs text-green-700">Active until: <?php echo date('d M Y', strtotime($subscription['end_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                    <a href="https://wa.me/919810359334" target="_blank" class="px-2 py-1 bg-green-500 hover:bg-green-600 text-white rounded-lg text-xs font-medium transition-colors">
                                        <i class="fab fa-whatsapp mr-1"></i>Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-gradient-to-r from-red-100 to-red-50 p-3 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-red-200 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-red-800">
                                            <?php echo $subscriptionStatus === 'trial_expired' ? 'Trial Expired' : 'No Active Plan'; ?>
                                        </h3>
                                        <p class="text-xs text-red-700">Upgrade to access all features</p>
                                    </div>
                                </div>
                                <button onclick="showUpgradeModal()" class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-medium transition-colors">
                                    <i class="fas fa-rocket mr-1"></i>Upgrade
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Enhanced Pricing Plans Section -->
                <div id="pricingPlansSection" class="hidden mb-3">
                    <h3 class="text-sm font-bold text-gray-800 mb-3">Choose Your Plan</h3>
                    <div class="grid grid-cols-1 gap-3">
                        <?php
                        $plansQuery = "SELECT * FROM subscription_plans WHERE is_active = 1 AND name != 'Trial' ORDER BY price ASC";
                        $plansResult = $conn->query($plansQuery);
                        while ($plan = $plansResult->fetch_assoc()):
                            $duration = (int)$plan['duration_in_days'];
                            if ($duration == 30) {
                                $durationText = '1 Month';
                            } elseif ($duration == 365) {
                                $durationText = '1 Year';
                            } elseif ($duration % 365 == 0) {
                                $durationText = ($duration / 365) . ' Years';
                            } elseif ($duration % 30 == 0) {
                                $durationText = ($duration / 30) . ' Months';
                            } else {
                                $durationText = $duration . ' Days';
                            }
                            
                            $color = 'blue';
                            if (stripos($plan['name'], 'Standard') !== false) $color = 'purple';
                            if (stripos($plan['name'], 'Premium') !== false) $color = 'green';
                            if (stripos($plan['name'], 'Basic') !== false) $color = 'sky';
                        ?>
                        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4 relative overflow-hidden">
                            <?php if (stripos($plan['name'], 'Standard') !== false): ?>
                                <span class="absolute top-3 right-3 bg-purple-100 text-purple-700 text-[11px] font-bold px-2 py-0.5 rounded-full">Most Popular</span>
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
                                <button onclick="selectPlan(<?php echo $plan['id']; ?>)" class="w-full sm:w-1/2 py-2 bg-<?php echo $color; ?>-500 hover:bg-<?php echo $color; ?>-600 text-white rounded-lg text-sm font-semibold transition-colors shadow">
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
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-3 gap-2">
                    <a href="tel:+919810359334" class="bg-gradient-to-r from-purple-100 to-purple-50 p-1.5 rounded-lg shadow-sm flex flex-col items-center text-center">
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
                    <a href="https://wa.me/919810359334" target="_blank" class="bg-gradient-to-r from-green-100 to-green-50 p-1.5 rounded-lg shadow-sm flex flex-col items-center text-center">
                        <div class="w-7 h-7 bg-green-200 rounded-lg flex items-center justify-center mb-1">
                            <i class="fab fa-whatsapp text-green-600 text-xs"></i>
                        </div>
                        <span class="text-xs text-green-800 font-medium">WhatsApp</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Feature Locked Modal -->
    <div id="featureLockedModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-[80] hidden">
        <div class="bg-white rounded-xl p-6 shadow-2xl w-full max-w-md mx-4">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lock text-red-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Feature Locked</h3>
                <p class="text-gray-600 mb-4">
                    <?php if ($subscriptionStatus === 'trial_expired'): ?>
                        Your trial has expired. Upgrade to a premium plan to access this feature.
                    <?php elseif ($subscriptionStatus === 'premium_expired'): ?>
                        Your subscription has expired. Please renew to continue using this feature.
                    <?php else: ?>
                        This feature requires an active subscription. Please upgrade to access it.
                    <?php endif; ?>
                </p>
                <div class="flex flex-col space-y-3">
                    <button onclick="showUpgradeModal(); closeFeatureLockedModal();" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg font-medium transition-colors">
                        <i class="fas fa-star mr-2"></i>View Plans & Upgrade
                    </button>
                    <a href="https://wa.me/919810359334" target="_blank" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg font-medium transition-colors text-center">
                        <i class="fab fa-whatsapp mr-2"></i>Contact Support
                    </a>
                    <button onclick="closeFeatureLockedModal()" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-lg font-medium transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upgrade Modal -->
    <div id="upgradeModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-[80] hidden">
        <div class="bg-white rounded-xl p-6 shadow-2xl w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800">Choose Your Perfect Plan</h3>
                    <p class="text-gray-600 mt-1">Select the plan that best fits your business needs</p>
                </div>
                <button onclick="closeUpgradeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <?php if ($isTrialUser): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-clock text-amber-500 mr-3"></i>
                        <div>
                            <h4 class="font-semibold text-amber-800">Trial Status</h4>
                            <p class="text-sm text-amber-700">
                                <?php echo $isExpired ? 'Your trial has expired.' : "Your trial expires in {$daysRemaining} days."; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php
                $plansQuery = "SELECT * FROM subscription_plans WHERE is_active = 1 AND name != 'Trial' ORDER BY price ASC";
                $plansResult = $conn->query($plansQuery);
                while ($plan = $plansResult->fetch_assoc()):
                    $duration = (int)$plan['duration_in_days'];
                    $durationText = $duration == 30 ? '1 Month' : ($duration == 365 ? '1 Year' : $duration . ' Days');
                    
                    // Determine plan color and features
                    $color = 'blue';
                    $isPopular = false;
                    if (stripos($plan['name'], 'Standard') !== false) {
                        $color = 'purple';
                        $isPopular = true;
                    } elseif (stripos($plan['name'], 'Premium') !== false) {
                        $color = 'green';
                    }
                    
                    // Split features into core and additional
                    $allFeatures = explode(',', $plan['features']);
                    $coreFeatures = array_slice($allFeatures, 0, 5);
                    $additionalFeatures = array_slice($allFeatures, 5);
                ?>
                <div class="relative">
                    <?php if ($isPopular): ?>
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                        <span class="bg-purple-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg">
                            Most Popular
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="border border-gray-200 rounded-xl p-6 h-full flex flex-col <?php echo $isPopular ? 'border-purple-300 shadow-lg' : ''; ?>">
                        <div class="mb-6">
                            <div class="flex items-center space-x-2 mb-2">
                                <i class="fas fa-gem text-<?php echo $color; ?>-500 text-xl"></i>
                                <h4 class="text-xl font-bold text-<?php echo $color; ?>-700"><?php echo htmlspecialchars($plan['name']); ?></h4>
                            </div>
                            <div class="flex items-baseline mb-2">
                                <span class="text-3xl font-bold text-gray-900">₹<?php echo number_format($plan['price']); ?></span>
                                <span class="text-gray-500 ml-2">
                                    <?php
                                    if ($duration == 30) {
                                        echo '/month';
                                    } elseif ($duration == 365) {
                                        echo '/year';
                                    } elseif ($duration > 365 && $duration % 365 == 0) {
                                        echo '/' . ($duration / 365) . ' years';
                                    } else {
                                        echo '/' . $duration . ' days'; // Fallback for other durations
                                    }
                                    ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-600"><?php echo $durationText; ?> subscription</p>
                        </div>

                        <div class="flex-grow">
                            <h5 class="font-semibold text-gray-800 mb-3">Core Features</h5>
                            <ul class="space-y-2 mb-4">
                                <?php foreach ($coreFeatures as $feature): ?>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars(trim($feature)); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if (!empty($additionalFeatures)): ?>
                            <div class="border-t border-gray-200 pt-4">
                                <h5 class="font-semibold text-gray-800 mb-3">Additional Features</h5>
                                <ul class="space-y-2">
                                    <?php foreach ($additionalFeatures as $feature): ?>
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                                        <span class="text-sm text-gray-700"><?php echo htmlspecialchars(trim($feature)); ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-6 space-y-3">
                            <button onclick="selectPlan(<?php echo $plan['id']; ?>)" 
                                    class="w-full bg-<?php echo $color; ?>-600 hover:bg-<?php echo $color; ?>-700 text-white py-3 px-4 rounded-lg text-sm font-semibold transition-colors shadow-md">
                                Choose <?php echo htmlspecialchars($plan['name']); ?>
                            </button>
                            <a href="https://wa.me/919810359334?text=I'm interested in the <?php echo urlencode($plan['name']); ?> plan" 
                               target="_blank" 
                               class="w-full bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-lg text-sm font-semibold transition-colors shadow-md flex items-center justify-center">
                                <i class="fab fa-whatsapp mr-2"></i> Chat with Sales
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="mt-8 bg-gray-50 rounded-xl p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-shield-alt text-blue-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">Secure Payment</h4>
                            <p class="text-sm text-gray-600 mt-1">All transactions are secure and encrypted</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-headset text-green-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">24/7 Support</h4>
                            <p class="text-sm text-gray-600 mt-1">Get help whenever you need it</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-sync text-purple-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">Easy Upgrade</h4>
                            <p class="text-sm text-gray-600 mt-1">Upgrade or downgrade anytime</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600 mb-3">Need help choosing? Contact our sales team</p>
                <a href="https://wa.me/919810359334" target="_blank" 
                   class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-medium transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i>Chat with Sales Team
                </a>
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
                    <input type="number" id="customRateInput" name="rate" class="modal-input" placeholder="Enter rate" step="0.01">
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
                <a href="home.php" data-nav-id="home" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Home</span>
                </a>
                <?php if ($hasFeatureAccess): ?>
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
                <a href="profile.php" data-nav-id="profile" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Profile</span>
                </a>
                <?php else: ?>
                <button onclick="showFeatureLockedModal()" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Search</span>
                </button>
                <button onclick="showFeatureLockedModal()" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Add</span>
                </button>
                <button onclick="showFeatureLockedModal()" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Alerts</span>
                </button>
                <button onclick="showFeatureLockedModal()" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Profile</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <script type="module" src="js/home.js"></script>
    <script>
        window.canEditRates = <?php echo $canEditRates ? 'true' : 'false'; ?>;
        window.hasFeatureAccess = <?php echo $hasFeatureAccess ? 'true' : 'false'; ?>;
        window.subscriptionStatus = '<?php echo $subscriptionStatus; ?>';
        window.isTrialUser = <?php echo $isTrialUser ? 'true' : 'false'; ?>;
        window.isPremiumUser = <?php echo $isPremiumUser ? 'true' : 'false'; ?>;
        window.isExpired = <?php echo $isExpired ? 'true' : 'false'; ?>;
        window.daysRemaining = <?php echo $daysRemaining; ?>;

        // Global functions for modals
        function showFeatureLockedModal() {
            document.getElementById('featureLockedModal').classList.remove('hidden');
        }

        function closeFeatureLockedModal() {
            document.getElementById('featureLockedModal').classList.add('hidden');
        }

        function showUpgradeModal() {
            document.getElementById('upgradeModal').classList.remove('hidden');
            document.getElementById('pricingPlansSection').classList.add('hidden');
        }

        function closeUpgradeModal() {
            document.getElementById('upgradeModal').classList.add('hidden');
        }

        function selectPlan(planId) {
            // Redirect to payment or subscription page
            window.location.href = `subscription.php?plan=${planId}`;
        }

        function togglePlans() {
            const plansSection = document.getElementById('pricingPlansSection');
            if (plansSection) {
                plansSection.classList.toggle('hidden');
                if (!plansSection.classList.contains('hidden')) {
                    plansSection.style.opacity = '0';
                    plansSection.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        plansSection.style.opacity = '1';
                        plansSection.style.transform = 'translateY(0)';
                    }, 10);
                }
            }
        }

        // Auto-show upgrade modal for expired users
        <?php if ($subscriptionStatus === 'trial_expired' || $subscriptionStatus === 'premium_expired'): ?>
            setTimeout(() => {
                showUpgradeModal();
            }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
$conn->close();
?>

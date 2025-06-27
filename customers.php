<?php
session_start();
require 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$firm_id = $_SESSION['firmID'];

// Fetch user info for header
$user_id = $_SESSION['id'];
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user and firm details
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.City, f.Logo
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();

// Fetch all customers for this firm
$customers = [];
$customerQuery = "SELECT id, FirstName, LastName, Address, City, State, PhoneNumber, Email FROM customer WHERE firm_id = ? ORDER BY FirstName, LastName";
$customerStmt = $conn->prepare($customerQuery);
$customerStmt->bind_param("i", $firm_id);
$customerStmt->execute();
$customerResult = $customerStmt->get_result();
while ($row = $customerResult->fetch_assoc()) {
    $customers[$row['id']] = $row;
}

// Fetch all due/unpaid sales for these customers
$dues = [];
if (count($customers) > 0) {
    $ids = implode(',', array_map('intval', array_keys($customers)));
    $salesQuery = "SELECT customer_id, SUM(due_amount) as total_due FROM jewellery_sales WHERE customer_id IN ($ids) AND payment_status IN ('Unpaid','Partial') GROUP BY customer_id";
    $salesResult = $conn->query($salesQuery);
    while ($row = $salesResult->fetch_assoc()) {
        $dues[$row['customer_id']] = $row['total_due'];
    }
}

// Fetch all active loans for these customers
$loans = [];
if (count($customers) > 0) {
    $loansQuery = "SELECT customer_id, SUM(outstanding_amount) as total_loan FROM loans WHERE customer_id IN ($ids) AND current_status = 'active' GROUP BY customer_id";
    $loansResult = $conn->query($loansQuery);
    while ($row = $loansResult->fetch_assoc()) {
        $loans[$row['customer_id']] = $row['total_loan'];
    }
}

// Fetch EMI due for current month for these customers
$emiDues = [];
if (count($customers) > 0) {
    $emiQuery = "SELECT customer_id, SUM(amount) as emi_due FROM loan_emis WHERE customer_id IN ($ids) AND status = 'due' AND MONTH(due_date) = MONTH(CURDATE()) AND YEAR(due_date) = YEAR(CURDATE()) GROUP BY customer_id";
    $emiResult = $conn->query($emiQuery);
    if ($emiResult) {
        while ($row = $emiResult->fetch_assoc()) {
            $emiDues[$row['customer_id']] = $row['emi_due'];
        }
    }
}

// Fetch Gold Saving Plan Installment due for current month for these customers
$goldDues = [];
$goldPlanDetails = [];
if (count($customers) > 0) {
    $planQuery = "SELECT 
        cgp.id as plan_id,
        cgp.customer_id,
        cgp.enrollment_date,
        cgp.maturity_date,
        cgp.total_amount_paid,
        cgp.total_gold_accrued,
        gp.plan_name,
        gp.duration_months,
        gp.min_amount_per_installment,
        gp.installment_frequency,
        gp.bonus_percentage,
        DATEDIFF(CURDATE(), cgp.enrollment_date) as days_enrolled,
        DATEDIFF(cgp.maturity_date, CURDATE()) as days_remaining,
        FLOOR(DATEDIFF(CURDATE(), cgp.enrollment_date) / 30) as months_completed,
        gp.duration_months - FLOOR(DATEDIFF(CURDATE(), cgp.enrollment_date) / 30) as months_remaining,
        FLOOR(cgp.total_amount_paid / gp.min_amount_per_installment) as installments_paid,
        gp.duration_months - FLOOR(cgp.total_amount_paid / gp.min_amount_per_installment) as installments_remaining
    FROM customer_gold_plans cgp 
    JOIN gold_saving_plans gp ON gp.id = cgp.plan_id 
    WHERE cgp.customer_id IN ($ids) AND cgp.current_status = 'active'";
    
    $planResult = $conn->query($planQuery);
    if ($planResult) {
        while ($row = $planResult->fetch_assoc()) {
            $customer_id = $row['customer_id'];
            $goldPlanDetails[$customer_id][] = $row;
            
            // Calculate monthly due amount
            $monthlyDue = $row['min_amount_per_installment'];
            $goldDues[$customer_id] = ($goldDues[$customer_id] ?? 0) + $monthlyDue;
        }
    }
}

// Calculate stats
$totalCustomers = count($customers);
$totalDuePayments = count($dues);
$totalEmiDue = count($emiDues);
$totalGoldDue = count($goldDues);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kolkata');

// --- Permission check for rate management ---
$allowed_roles = ['admin', 'manager', 'super admin'];
$user_role = strtolower($_SESSION['role'] ?? '');
$canEditRates = in_array($user_role, $allowed_roles);

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
$hasFeatureAccess = ($isPremiumUser && !$isExpired) || ($isTrialUser && !$isExpired);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Customer Management - JewelEntry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/home.css">
    <style>
        * { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif; }
        
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .card-hover {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-hover:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .gradient-jewel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .gradient-soft {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        
        .floating-action {
            position: fixed;
            bottom: 90px;
            right: 20px;
            z-index: 40;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-3px); }
        }
        
        .modal-backdrop {
            backdrop-filter: blur(12px);
            background: rgba(0, 0, 0, 0.5);
        }
        
        .input-focus {
            transition: all 0.2s ease;
        }
        
        .input-focus:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .status-badge {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.025em;
        }
        
        .avatar-gradient {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        }
        
        .jewel-primary {
            color: #667eea;
        }
        
        .jewel-secondary {
            color: #764ba2;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
    
    <!-- Header -->
    <header class="header-glass sticky top-0 z-50 shadow-md">
        <div class="px-3 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-9 h-9 gradient-gold rounded-xl flex items-center justify-center shadow-lg floating">
                        <?php if (!empty($userInfo['Logo'])): ?>
                            <img src="<?php echo htmlspecialchars($userInfo['Logo']); ?>" alt="Firm Logo" class="w-full h-full object-cover rounded-xl">
                        <?php else: ?>
                            <i class="fas fa-gem text-white text-sm"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 class="text-sm font-bold text-gray-800"><?php echo $userInfo['FirmName']; ?></h1>
                        <p class="text-xs text-gray-600 font-medium">Powered by JewelEntry</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p id="headerUserName" class="text-xs font-bold text-gray-800"><?php echo $userInfo['Name']; ?></p>
                        <p id="headerUserRole" class="text-xs text-purple-600 font-medium"><?php echo $userInfo['Role']; ?></p>
                    </div>
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
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 pt-4 pb-24 max-w-md mx-auto">
        
        <!-- Summary Cards -->
        <div class="mb-3">
            <div class="flex gap-2 overflow-x-auto pb-1 hide-scrollbar">
                <!-- Total Customers -->
                <div class="stats-card stat-filter rounded-xl p-2 min-w-[90px] shadow-sm flex flex-col items-center cursor-pointer" data-filter="all">
                    <div class="flex items-center justify-between mb-1 w-full">
                        <div class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-blue-600 text-sm"></i>
                        </div>
                        <span class="text-lg font-bold text-gray-900"><?php echo $totalCustomers; ?></span>
                    </div>
                    <p class="text-[11px] font-medium text-gray-600">Customers</p>
                </div>
                <!-- Due Payments -->
                <div class="stats-card stat-filter rounded-xl p-2 min-w-[90px] shadow-sm flex flex-col items-center cursor-pointer" data-filter="due">
                    <div class="flex items-center justify-between mb-1 w-full">
                        <div class="w-7 h-7 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-circle text-red-600 text-sm"></i>
                        </div>
                        <span class="text-lg font-bold text-gray-900"><?php echo $totalDuePayments; ?></span>
                    </div>
                    <p class="text-[11px] font-medium text-gray-600">Due </p>
                </div>
                <!-- EMI Due -->
                <div class="stats-card stat-filter rounded-xl p-2 min-w-[90px] shadow-sm flex flex-col items-center cursor-pointer" data-filter="emi">
                    <div class="flex items-center justify-between mb-1 w-full">
                        <div class="w-7 h-7 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-indigo-600 text-sm"></i>
                        </div>
                        <span class="text-lg font-bold text-gray-900"><?php echo $totalEmiDue; ?></span>
                    </div>
                    <p class="text-[11px] font-medium text-gray-600">EMI Due</p>
                </div>
                <!-- Gold Plans -->
                <div class="stats-card stat-filter rounded-xl p-2 min-w-[90px] shadow-sm flex flex-col items-center cursor-pointer" data-filter="gold">
                    <div class="flex items-center justify-between mb-1 w-full">
                        <div class="w-7 h-7 bg-amber-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-award text-amber-600 text-sm"></i>
                        </div>
                        <span class="text-lg font-bold text-gray-900"><?php echo $totalGoldDue; ?></span>
                    </div>
                    <p class="text-[11px] font-medium text-gray-600">Gold Plans</p>
                </div>
            </div>
        </div>
        <div id="statsFilterClear" class="hidden mb-2 text-center">
            <button class="text-xs text-blue-600 bg-blue-50 px-3 py-1 rounded-full font-semibold">Clear Filter</button>
        </div>

        <!-- Search Bar -->
        <div class="mb-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" id="customerSearch"
                    class="w-full pl-10 pr-3 py-2 bg-white rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm placeholder-gray-500 shadow-sm input-focus"
                    placeholder="Search customers by name, phone...">
            </div>
        </div>

        <!-- Customer List -->
        <div id="customerList" class="space-y-2">
            <?php if (count($customers) === 0): ?>
                <div class="text-center py-10">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-br from-indigo-100 to-purple-100 rounded-2xl flex items-center justify-center mb-4">
                        <i class="fas fa-users text-2xl text-indigo-400"></i>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900 mb-1">No customers yet</h3>
                    <p class="text-gray-500 text-xs mb-4">Start building your customer base</p>
                    <button id="addCustomerBtnEmpty" class="bg-gradient-to-br from-purple-500 to-indigo-500 text-white px-4 py-2 rounded-xl text-xs font-semibold shadow-lg flex items-center">
                        <i class="fas fa-user-plus mr-2"></i>
                        Add Your First Customer
                    </button>
                </div>
            <?php else: ?>
                <?php $serial = 1; ?>
                <?php foreach ($customers as $id => $c): ?>
                <div class="customer-item flex items-center gap-3 p-2 bg-white rounded-xl shadow-sm border border-gray-100" data-customer-id="<?= $id ?>" data-name="<?= strtolower($c['FirstName'] . ' ' . $c['LastName']) ?>" data-phone="<?= $c['PhoneNumber'] ?>" data-details-url="customer_details.php?id=<?= $id ?>">
                    <!-- Avatar -->
                    <div class="relative">
                        <div class="w-10 h-10 avatar-gradient rounded-xl flex items-center justify-center shadow-sm">
                            <span class="text-indigo-600 font-bold text-xs">
                                <?= strtoupper(substr($c['FirstName'], 0, 1) . substr($c['LastName'], 0, 1)) ?>
                            </span>
                        </div>
                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-[10px] font-bold"><?= $serial++ ?></span>
                        </div>
                    </div>
                    <!-- Customer Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 text-sm leading-tight truncate">
                                    <?= htmlspecialchars($c['FirstName'] . ' ' . $c['LastName']) ?>
                                </h3>
                                <div class="flex items-center space-x-2 mt-0.5">
                                    <?php if (!empty($c['PhoneNumber'])): ?>
                                        <span class="inline-flex items-center text-xs text-gray-500">
                                            <?= htmlspecialchars($c['PhoneNumber']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <?php if (isset($dues[$id]) && $dues[$id] > 0): ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-[11px] font-semibold">Due: ₹<?= number_format($dues[$id], 0) ?></span>
                                    <?php endif; ?>
                                    <?php if (isset($loans[$id]) && $loans[$id] > 0): ?>
                                        <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-[11px] font-semibold">Loan: ₹<?= number_format($loans[$id], 0) ?></span>
                                    <?php endif; ?>
                                    <?php $monthlyDueTotal = ($emiDues[$id] ?? 0) + ($goldDues[$id] ?? 0); ?>
                                    <?php if ($monthlyDueTotal > 0): ?>
                                        <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-[11px] font-semibold">Monthly: ₹<?= number_format($monthlyDueTotal, 0) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- WhatsApp Button (optional, can be removed for compactness) -->
                    <?php if (!empty($c['PhoneNumber'])): ?>
                        <a href="https://wa.me/91<?= $c['PhoneNumber'] ?>?text=<?= urlencode('Greetings from ' . ($userInfo['FirmName'] ?? 'JewelEntry') . '!') ?>"
                           target="_blank"
                           onclick="event.stopPropagation();"
                           class="w-8 h-8 bg-green-500 rounded-xl flex items-center justify-center text-white shadow-sm hover:bg-green-600 transition-colors">
                            <i class="fab fa-whatsapp text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button id="addCustomerBtn" class="floating-action w-11 h-11 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-full shadow-xl flex items-center justify-center text-white text-xl">
        <i class="fas fa-user-plus"></i>
    </button>

    <!-- Add Customer Modal -->
    <div id="addCustomerModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden p-2">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md mx-auto max-h-[90vh] overflow-y-auto">
            <form id="addCustomerForm" method="POST" action="add_customer.php" enctype="multipart/form-data">
                <input type="hidden" name="customer_id" id="customerIdField" value="">
                <!-- Modal Header -->
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 id="customerModalTitle" class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-user-plus text-purple-500 mr-2"></i>
                        Add New Customer
                    </h3>
                    <button type="button" id="cancelAddCustomer" class="w-8 h-8 bg-gray-100 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <!-- Modal Body -->
                <div class="px-4 py-4">
                    <!-- Profile Image Section -->
                    <div class="flex flex-col items-center mb-4">
                        <div class="relative">
                            <label for="customerImage" class="block w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-100 to-purple-100 border-2 border-dashed border-indigo-200 flex items-center justify-center cursor-pointer overflow-hidden hover:border-indigo-300 transition-colors">
                                <img id="customerImagePreview" src="/placeholder.svg" alt="Preview" class="object-cover w-full h-full hidden rounded-2xl" />
                                <div id="customerImagePlaceholder" class="flex flex-col items-center justify-center text-indigo-400">
                                    <i class="fas fa-camera text-2xl mb-1"></i>
                                    <span class="text-xs font-medium">Photo</span>
                                </div>
                                <input type="file" id="customerImage" name="CustomerImage" accept="image/*" capture="environment" class="hidden" />
                            </label>
                            <button type="button" id="removeCustomerImage" class="absolute -bottom-1 -right-1 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-white shadow-lg hidden hover:bg-red-600 transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <span class="text-xs text-gray-500 mt-1 font-medium">Tap to add photo</span>
                    </div>
                    <!-- Form Fields -->
                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input type="text" name="FirstName" required 
                                       class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="First Name *">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input type="text" name="LastName" 
                                       class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="Last Name">
                            </div>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-phone text-gray-400"></i>
                            </div>
                            <input type="tel" name="PhoneNumber" required pattern="[0-9]{10,15}" 
                                   class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                   placeholder="Phone Number *">
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" name="Email" 
                                   class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                   placeholder="Email Address">
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-map-marker-alt text-gray-400"></i>
                            </div>
                            <input type="text" name="Address" 
                                   class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                   placeholder="Address">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-city text-gray-400"></i>
                                </div>
                                <input type="text" name="City" 
                                       class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="City">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-flag text-gray-400"></i>
                                </div>
                                <input type="text" name="State" 
                                       class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="State">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-hashtag text-gray-400"></i>
                                </div>
                                <input type="text" name="PostalCode" 
                                       class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="Postal Code">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-globe-asia text-gray-400"></i>
                                </div>
                                <input type="text" name="Country" 
                                       class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="Country" value="India">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-calendar-alt text-gray-400"></i>
                                </div>
                                <input type="date" name="DateOfBirth" 
                                       class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-venus-mars text-gray-400"></i>
                                </div>
                                <select name="Gender" 
                                        class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-id-card text-gray-400"></i>
                                </div>
                                <input type="text" name="PANNumber" maxlength="10" 
                                       class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="PAN Number">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-id-badge text-gray-400"></i>
                                </div>
                                <input type="text" name="AadhaarNumber" maxlength="12" 
                                       class="w-full pl-10 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="Aadhaar Number">
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Modal Footer -->
                <div class="px-4 py-3 border-t border-gray-100 flex justify-end space-x-2">
                    <button type="button" id="cancelAddCustomer2" 
                            class="px-4 py-2 text-sm font-semibold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="customerModalSubmitBtn"
                            class="bg-gradient-to-br from-purple-500 to-indigo-500 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-lg flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav fixed bottom-0 left-0 right-0 shadow-xl">
        <div class="px-4 py-2">
            <div class="flex justify-around">
                <a href="home.php" data-nav-id="home" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
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
                <a href="customers.php" data-nav-id="customers" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Customers</span>
                </a>
                
            </div>
        </div>
    </nav>

    <script>
        // Font Awesome icons are already loaded via CDN
        // Remove feather.replace();

        // Floating Add Customer Button - open modal
        const addCustomerBtn = document.getElementById('addCustomerBtn');
        const addCustomerBtnEmpty = document.getElementById('addCustomerBtnEmpty');
        const addCustomerModal = document.getElementById('addCustomerModal');
        const cancelAddCustomer = document.getElementById('cancelAddCustomer');
        const cancelAddCustomer2 = document.getElementById('cancelAddCustomer2');
        const addCustomerForm = document.getElementById('addCustomerForm');
        const customerImageInput = document.getElementById('customerImage');
        const customerImagePreview = document.getElementById('customerImagePreview');
        const customerImagePlaceholder = document.getElementById('customerImagePlaceholder');
        const removeCustomerImageBtn = document.getElementById('removeCustomerImage');
        const customerModalTitle = document.getElementById('customerModalTitle');
        const customerModalSubmitBtn = document.getElementById('customerModalSubmitBtn');
        const customerIdField = document.getElementById('customerIdField');
        const searchInput = document.getElementById('customerSearch');
        const customerItems = document.querySelectorAll('.customer-item');

        function showModal() {
            addCustomerForm.reset();
            document.getElementById('customerImagePreview').src = '';
            document.getElementById('customerImagePreview').classList.add('hidden');
            document.getElementById('customerImagePlaceholder').style.display = '';
            document.getElementById('removeCustomerImage').classList.add('hidden');
            document.getElementById('customerModalTitle').innerHTML = '<i class="fas fa-user-plus text-purple-500 mr-2"></i>Add New Customer';
            document.getElementById('customerModalSubmitBtn').innerHTML = '<i class="fas fa-plus mr-2"></i>Add Customer';
            addCustomerForm.action = 'add_customer.php';
            document.getElementById('customerIdField').value = '';
            addCustomerModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function hideModal() {
            addCustomerModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            addCustomerForm.reset();
            document.getElementById('customerImagePreview').src = '';
            document.getElementById('customerImagePreview').classList.add('hidden');
            document.getElementById('customerImagePlaceholder').style.display = '';
            document.getElementById('removeCustomerImage').classList.add('hidden');
        }
        if (addCustomerBtn) addCustomerBtn.addEventListener('click', showModal);
        if (addCustomerBtnEmpty) addCustomerBtnEmpty.addEventListener('click', showModal);
        if (cancelAddCustomer) cancelAddCustomer.addEventListener('click', hideModal);
        if (cancelAddCustomer2) cancelAddCustomer2.addEventListener('click', hideModal);
        if (addCustomerModal) {
            addCustomerModal.addEventListener('click', (e) => {
                if (e.target === addCustomerModal) {
                    hideModal();
                }
            });
        }
        // --- Bottom Nav Active State Logic (from home.js) ---
        function setActiveNavButton(activeButton) {
            const navButtons = document.querySelectorAll('.nav-btn');
            navButtons.forEach((btn) => {
                const iconDiv = btn.querySelector('div');
                const textSpan = btn.querySelector('span');
                const iconI = btn.querySelector('i');
                btn.style.transform = 'translateY(0)';
                if (iconDiv) {
                    iconDiv.className = 'w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center transition-all duration-200';
                }
                if (iconI) {
                    iconI.classList.remove('text-white');
                    ['text-blue-500', 'text-green-500', 'text-purple-500', 'text-red-500', 'text-amber-500'].forEach((cls) => iconI.classList.remove(cls));
                    iconI.classList.add('text-gray-400');
                }
                if (textSpan) {
                    textSpan.className = 'text-xs text-gray-400 font-medium transition-all duration-200';
                }
            });
            if (!activeButton) return;
            const currentIconDiv = activeButton.querySelector('div');
            const currentTextSpan = activeButton.querySelector('span');
            const currentIconI = activeButton.querySelector('i');
            const navId = activeButton.dataset.navId;
            let colorName = 'blue';
            if (navId === 'home') colorName = 'blue';
            else if (navId === 'search') colorName = 'green';
            else if (navId === 'add') colorName = 'purple';
            else if (navId === 'alerts_nav') colorName = 'red';
            else if (navId === 'profile') colorName = 'amber';
            else if (navId === 'customers') colorName = 'purple';
            if (currentIconDiv) {
                currentIconDiv.className = `w-8 h-8 bg-gradient-to-br from-${colorName}-500 to-${colorName}-600 rounded-lg flex items-center justify-center shadow-lg transition-all duration-200`;
            }
            if (currentIconI) {
                currentIconI.classList.remove('text-gray-400');
                currentIconI.classList.add('text-white');
            }
            if (currentTextSpan) {
                currentTextSpan.className = `text-xs text-${colorName}-600 font-bold transition-all duration-200`;
            }
            activeButton.style.transform = 'translateY(-5px)';
        }
        function initializeNavigation() {
            const navButtons = document.querySelectorAll('.nav-btn');
            navButtons.forEach((btn) => {
                btn.addEventListener('click', function (event) {
                    setActiveNavButton(this);
                });
            });
            // Set active navigation based on current page
            const currentPath = window.location.pathname.split('/').pop();
            if (currentPath === 'home.php' || currentPath === '' || currentPath === 'index.html') {
                const homeButton = document.querySelector('.nav-btn[data-nav-id="home"]');
                if (homeButton) setActiveNavButton(homeButton);
            } else if (currentPath === 'profile.php') {
                const profileButton = document.querySelector('.nav-btn[data-nav-id="profile"]');
                if (profileButton) setActiveNavButton(profileButton);
            } else if (currentPath === 'customers.php') {
                const customersButton = document.querySelector('.nav-btn[data-nav-id="customers"]');
                if (customersButton) setActiveNavButton(customersButton);
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            initializeNavigation();
        });
        // --- Fix Search Functionality ---
        if (searchInput && customerItems.length > 0) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                customerItems.forEach(item => {
                    const customerName = item.getAttribute('data-name') || '';
                    const customerPhone = item.getAttribute('data-phone') || '';
                    const shouldShow = (customerName.includes(searchTerm) || customerPhone.includes(searchTerm));
                    if (shouldShow) {
                        item.style.display = 'flex';
                        item.style.animation = 'fadeIn 0.3s ease-out';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        // Make customer-item clickable except on WhatsApp button
        customerItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // If the click is on a WhatsApp button or its child, do nothing
                if (e.target.closest('a[target="_blank"]')) return;
                const url = item.getAttribute('data-details-url');
                if (url) window.location.href = url;
            });
        });
    </script>
</body>
</html>
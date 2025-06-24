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
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.City
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Customer Management - JewelEntry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
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
    <header class="glass-effect sticky top-0 z-50 border-b border-white/20">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 gradient-jewel rounded-xl flex items-center justify-center shadow-lg">
                        <i data-feather="gem" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($userInfo['FirmName']); ?></h1>
                        <p class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($userInfo['Role']); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($userInfo['Name']); ?></p>
                        <p class="text-xs jewel-primary font-medium">JewelEntry</p>
                    </div>
                    <div class="w-10 h-10 gradient-jewel rounded-xl flex items-center justify-center shadow-lg overflow-hidden cursor-pointer">
                        <?php 
                        $defaultImage = 'public/uploads/user.png';
                        if (!empty($userInfo['image_path']) && file_exists($userInfo['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i data-feather="user" class="w-5 h-5 text-white"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 pt-4 pb-24 max-w-md mx-auto">
        
        <!-- Summary Cards -->
        <div class="mb-6">
            <div class="flex gap-3 overflow-x-auto pb-2 hide-scrollbar">
                <!-- Total Customers -->
                <div class="flex-shrink-0 stats-card rounded-2xl p-4 min-w-[120px] shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 bg-blue-100 rounded-xl flex items-center justify-center">
                            <i data-feather="users" class="w-4 h-4 text-blue-600"></i>
                        </div>
                        <span class="text-2xl font-bold text-gray-900"><?php echo $totalCustomers; ?></span>
                    </div>
                    <p class="text-xs font-medium text-gray-600">Total Customers</p>
                </div>

                <!-- Due Payments -->
                <div class="flex-shrink-0 stats-card rounded-2xl p-4 min-w-[120px] shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 bg-red-100 rounded-xl flex items-center justify-center">
                            <i data-feather="alert-circle" class="w-4 h-4 text-red-600"></i>
                        </div>
                        <span class="text-2xl font-bold text-gray-900"><?php echo $totalDuePayments; ?></span>
                    </div>
                    <p class="text-xs font-medium text-gray-600">Due Payments</p>
                </div>

                <!-- EMI Due -->
                <div class="flex-shrink-0 stats-card rounded-2xl p-4 min-w-[120px] shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 bg-indigo-100 rounded-xl flex items-center justify-center">
                            <i data-feather="calendar" class="w-4 h-4 text-indigo-600"></i>
                        </div>
                        <span class="text-2xl font-bold text-gray-900"><?php echo $totalEmiDue; ?></span>
                    </div>
                    <p class="text-xs font-medium text-gray-600">EMI Due</p>
                </div>

                <!-- Gold Plans -->
                <div class="flex-shrink-0 stats-card rounded-2xl p-4 min-w-[120px] shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 bg-amber-100 rounded-xl flex items-center justify-center">
                            <i data-feather="award" class="w-4 h-4 text-amber-600"></i>
                        </div>
                        <span class="text-2xl font-bold text-gray-900"><?php echo $totalGoldDue; ?></span>
                    </div>
                    <p class="text-xs font-medium text-gray-600">Gold Plans</p>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="mb-6">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i data-feather="search" class="w-5 h-5 text-gray-400"></i>
                </div>
                <input type="text" id="customerSearch"
                    class="w-full pl-12 pr-4 py-4 bg-white rounded-2xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm placeholder-gray-500 shadow-sm input-focus"
                    placeholder="Search customers by name, phone...">
            </div>
        </div>

        <!-- Customer List -->
        <div id="customerList" class="space-y-3">
            <?php if (count($customers) === 0): ?>
                <div class="text-center py-16">
                    <div class="w-20 h-20 mx-auto bg-gradient-to-br from-indigo-100 to-purple-100 rounded-3xl flex items-center justify-center mb-6">
                        <i data-feather="users" class="w-8 h-8 text-indigo-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No customers yet</h3>
                    <p class="text-gray-500 text-sm mb-6">Start building your customer base</p>
                    <button id="addCustomerBtnEmpty" class="btn-primary text-white px-6 py-3 rounded-xl text-sm font-semibold shadow-lg">
                        <i data-feather="user-plus" class="w-4 h-4 mr-2 inline"></i>
                        Add Your First Customer
                    </button>
                </div>
            <?php else: ?>
                <?php $serial = 1; ?>
                <?php foreach ($customers as $id => $c): ?>
                <div class="customer-item" data-customer-id="<?= $id ?>" data-name="<?= strtolower($c['FirstName'] . ' ' . $c['LastName']) ?>">
                    <a href="customer_details.php?id=<?= $id ?>" class="block">
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 card-hover">
                            <div class="flex items-start space-x-4">
                                <!-- Avatar -->
                                <div class="relative">
                                    <div class="w-12 h-12 avatar-gradient rounded-2xl flex items-center justify-center shadow-sm">
                                        <span class="text-indigo-600 font-bold text-sm">
                                            <?= strtoupper(substr($c['FirstName'], 0, 1) . substr($c['LastName'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div class="absolute -top-1 -right-1 w-5 h-5 gradient-jewel rounded-full flex items-center justify-center">
                                        <span class="text-white text-xs font-bold"><?= $serial++ ?></span>
                                    </div>
                                </div>

                                <!-- Customer Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-gray-900 text-base leading-tight">
                                                <?= htmlspecialchars($c['FirstName'] . ' ' . $c['LastName']) ?>
                                            </h3>
                                            <div class="flex items-center space-x-2 mt-1">
                                                <?php if (!empty($c['PhoneNumber'])): ?>
                                                    <span class="inline-flex items-center text-xs text-gray-500">
                                                        <i data-feather="phone" class="w-3 h-3 mr-1"></i>
                                                        <?= htmlspecialchars($c['PhoneNumber']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($c['City'])): ?>
                                                    <span class="inline-flex items-center text-xs text-gray-500">
                                                        <i data-feather="map-pin" class="w-3 h-3 mr-1"></i>
                                                        <?= htmlspecialchars($c['City']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- WhatsApp Button -->
                                        <?php if (!empty($c['PhoneNumber'])): ?>
                                            <a href="https://wa.me/91<?= $c['PhoneNumber'] ?>?text=<?= urlencode('Greetings from ' . ($userInfo['FirmName'] ?? 'JewelEntry') . '!') ?>"
                                               target="_blank"
                                               onclick="event.stopPropagation();"
                                               class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center text-white shadow-sm hover:bg-green-600 transition-colors">
                                                <i data-feather="message-circle" class="w-4 h-4"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Status Badges -->
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        <?php if (isset($dues[$id]) && $dues[$id] > 0): ?>
                                            <span class="status-badge bg-red-100 text-red-700 px-2 py-1 rounded-lg">
                                                Due: ₹<?= number_format($dues[$id], 0) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (isset($loans[$id]) && $loans[$id] > 0): ?>
                                            <span class="status-badge bg-blue-100 text-blue-700 px-2 py-1 rounded-lg">
                                                Loan: ₹<?= number_format($loans[$id], 0) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php $monthlyDueTotal = ($emiDues[$id] ?? 0) + ($goldDues[$id] ?? 0); ?>
                                        <?php if ($monthlyDueTotal > 0): ?>
                                            <span class="status-badge bg-amber-100 text-amber-700 px-2 py-1 rounded-lg">
                                                Monthly: ₹<?= number_format($monthlyDueTotal, 0) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Gold Plan Progress -->
                                    <?php if (isset($goldPlanDetails[$id])): ?>
                                        <div class="mt-3 space-y-2">
                                            <?php foreach ($goldPlanDetails[$id] as $plan): ?>
                                                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                                                    <div class="flex justify-between items-center mb-2">
                                                        <span class="text-xs font-semibold text-amber-800">
                                                            <?= htmlspecialchars($plan['plan_name']) ?>
                                                        </span>
                                                        <span class="text-xs text-amber-600">
                                                            <?= $plan['installments_paid'] ?>/<?= $plan['duration_months'] ?>
                                                        </span>
                                                    </div>
                                                    <div class="w-full bg-amber-200 rounded-full h-2">
                                                        <div class="bg-amber-500 h-2 rounded-full transition-all duration-300" 
                                                             style="width: <?= ($plan['installments_paid'] / $plan['duration_months']) * 100 ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button id="addCustomerBtn" class="floating-action w-14 h-14 btn-primary rounded-2xl shadow-xl flex items-center justify-center">
        <i data-feather="user-plus" class="w-6 h-6 text-white"></i>
    </button>

    <!-- Add Customer Modal -->
    <div id="addCustomerModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg mx-auto max-h-[90vh] overflow-y-auto">
            <form id="addCustomerForm" method="POST" action="add_customer.php" enctype="multipart/form-data">
                <input type="hidden" name="customer_id" id="customerIdField" value="">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 id="customerModalTitle" class="text-xl font-bold text-gray-900 flex items-center">
                            <i data-feather="user-plus" class="w-5 h-5 jewel-primary mr-3"></i>
                            Add New Customer
                        </h3>
                        <button type="button" id="cancelAddCustomer" class="w-8 h-8 bg-gray-100 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                            <i data-feather="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="px-6 py-6">
                    <!-- Profile Image Section -->
                    <div class="flex flex-col items-center mb-8">
                        <div class="relative">
                            <label for="customerImage" class="block w-24 h-24 rounded-3xl bg-gradient-to-br from-indigo-100 to-purple-100 border-2 border-dashed border-indigo-200 flex items-center justify-center cursor-pointer overflow-hidden hover:border-indigo-300 transition-colors">
                                <img id="customerImagePreview" src="/placeholder.svg" alt="Preview" class="object-cover w-full h-full hidden rounded-3xl" />
                                <div id="customerImagePlaceholder" class="flex flex-col items-center justify-center text-indigo-400">
                                    <i data-feather="camera" class="w-6 h-6 mb-1"></i>
                                    <span class="text-xs font-medium">Photo</span>
                                </div>
                                <input type="file" id="customerImage" name="CustomerImage" accept="image/*" capture="environment" class="hidden" />
                            </label>
                            <button type="button" id="removeCustomerImage" class="absolute -bottom-1 -right-1 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-white shadow-lg hidden hover:bg-red-600 transition-colors">
                                <i data-feather="x" class="w-3 h-3"></i>
                            </button>
                        </div>
                        <span class="text-xs text-gray-500 mt-2 font-medium">Tap to add photo</span>
                    </div>

                    <!-- Form Fields -->
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="user" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="text" name="FirstName" required 
                                       class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="First Name *">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="user" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="text" name="LastName" 
                                       class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="Last Name">
                            </div>
                        </div>
                        
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-feather="phone" class="w-4 h-4 text-gray-400"></i>
                            </div>
                            <input type="tel" name="PhoneNumber" required pattern="[0-9]{10,15}" 
                                   class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                   placeholder="Phone Number *">
                        </div>
                        
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-feather="mail" class="w-4 h-4 text-gray-400"></i>
                            </div>
                            <input type="email" name="Email" 
                                   class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                   placeholder="Email Address">
                        </div>
                        
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-feather="map-pin" class="w-4 h-4 text-gray-400"></i>
                            </div>
                            <input type="text" name="Address" 
                                   class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                   placeholder="Address">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="home" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="text" name="City" 
                                       class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="City">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="flag" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="text" name="State" 
                                       class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="State">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="hash" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="text" name="PostalCode" 
                                       class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="Postal Code">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="globe" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="text" name="Country" 
                                       class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="Country" value="India">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="calendar" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="date" name="DateOfBirth" 
                                       class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="users" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <select name="Gender" 
                                        class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="credit-card" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="text" name="PANNumber" maxlength="10" 
                                       class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="PAN Number">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-feather="file-text" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="text" name="AadhaarNumber" maxlength="12" 
                                       class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm input-focus" 
                                       placeholder="Aadhaar Number">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-5 border-t border-gray-100 flex justify-end space-x-3">
                    <button type="button" id="cancelAddCustomer2" 
                            class="px-6 py-3 text-sm font-semibold text-gray-600 bg-gray-100 rounded-2xl hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="customerModalSubmitBtn"
                            class="btn-primary text-white px-6 py-3 rounded-2xl text-sm font-semibold shadow-lg">
                        <i data-feather="plus" class="w-4 h-4 mr-2 inline"></i>
                        Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 glass-effect border-t border-white/20 z-40">
        <div class="px-4 py-3">
            <div class="flex justify-around max-w-md mx-auto">
                <a href="home.php" class="flex flex-col items-center space-y-1 py-2 px-3 rounded-2xl transition-all">
                    <div class="w-8 h-8 bg-gray-100 rounded-xl flex items-center justify-center">
                        <i data-feather="home" class="w-4 h-4 text-gray-500"></i>
                    </div>
                    <span class="text-xs text-gray-500 font-medium">Home</span>
                </a>
                <a href="add.php" class="flex flex-col items-center space-y-1 py-2 px-3 rounded-2xl transition-all">
                    <div class="w-8 h-8 bg-gray-100 rounded-xl flex items-center justify-center">
                        <i data-feather="package" class="w-4 h-4 text-gray-500"></i>
                    </div>
                    <span class="text-xs text-gray-500 font-medium">Inventory</span>
                </a>
                <button class="flex flex-col items-center space-y-1 py-2 px-3 rounded-2xl transition-all">
                    <div class="w-8 h-8 bg-gray-100 rounded-xl flex items-center justify-center">
                        <i data-feather="bell" class="w-4 h-4 text-gray-500"></i>
                    </div>
                    <span class="text-xs text-gray-500 font-medium">Alerts</span>
                </button>
                <a href="customer.php" class="flex flex-col items-center space-y-1 py-2 px-3 rounded-2xl transition-all">
                    <div class="w-8 h-8 gradient-jewel rounded-xl flex items-center justify-center shadow-lg">
                        <i data-feather="users" class="w-4 h-4 text-white"></i>
                    </div>
                    <span class="text-xs jewel-primary font-bold">Customers</span>
                </a>
            </div>
        </div>
    </nav>

    <script>
        // Initialize Feather Icons
        feather.replace();

        // DOM Elements
        const addCustomerBtn = document.getElementById('addCustomerBtn');
        const addCustomerBtnEmpty = document.getElementById('addCustomerBtnEmpty');
        const addCustomerModal = document.getElementById('addCustomerModal');
        const cancelAddCustomer = document.getElementById('cancelAddCustomer');
        const cancelAddCustomer2 = document.getElementById('cancelAddCustomer2');
        const customerImageInput = document.getElementById('customerImage');
        const customerImagePreview = document.getElementById('customerImagePreview');
        const customerImagePlaceholder = document.getElementById('customerImagePlaceholder');
        const removeCustomerImageBtn = document.getElementById('removeCustomerImage');
        const addCustomerForm = document.getElementById('addCustomerForm');
        const customerModalTitle = document.getElementById('customerModalTitle');
        const customerModalSubmitBtn = document.getElementById('customerModalSubmitBtn');
        const customerIdField = document.getElementById('customerIdField');
        const searchInput = document.getElementById('customerSearch');
        const customerItems = document.querySelectorAll('.customer-item');

        // Modal Functions
        function showModal() {
            addCustomerForm.reset();
            customerImagePreview.src = '';
            customerImagePreview.classList.add('hidden');
            customerImagePlaceholder.style.display = '';
            removeCustomerImageBtn.classList.add('hidden');

            customerModalTitle.innerHTML = '<i data-feather="user-plus" class="w-5 h-5 jewel-primary mr-3"></i>Add New Customer';
            customerModalSubmitBtn.innerHTML = '<i data-feather="plus" class="w-4 h-4 mr-2 inline"></i>Add Customer';
            addCustomerForm.action = 'add_customer.php';
            customerIdField.value = '';

            addCustomerModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Re-initialize icons after DOM change
            feather.replace();
        }

        function hideModal() {
            addCustomerModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            addCustomerForm.reset();
            customerImagePreview.src = '';
            customerImagePreview.classList.add('hidden');
            customerImagePlaceholder.style.display = '';
            removeCustomerImageBtn.classList.add('hidden');
        }

        // Event Listeners
        if (addCustomerBtn) addCustomerBtn.addEventListener('click', showModal);
        if (addCustomerBtnEmpty) addCustomerBtnEmpty.addEventListener('click', showModal);
        if (cancelAddCustomer) cancelAddCustomer.addEventListener('click', hideModal);
        if (cancelAddCustomer2) cancelAddCustomer2.addEventListener('click', hideModal);

        // Close modal when clicking outside
        if (addCustomerModal) {
            addCustomerModal.addEventListener('click', (e) => {
                if (e.target === addCustomerModal) {
                    hideModal();
                }
            });
        }

        // Image Upload Handling
        if (customerImageInput) {
            customerImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        customerImagePreview.src = e.target.result;
                        customerImagePreview.classList.remove('hidden');
                        customerImagePlaceholder.style.display = 'none';
                        removeCustomerImageBtn.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                } else {
                    customerImagePreview.src = '';
                    customerImagePreview.classList.add('hidden');
                    customerImagePlaceholder.style.display = '';
                    removeCustomerImageBtn.classList.add('hidden');
                }
            });
        }

        if (removeCustomerImageBtn) {
            removeCustomerImageBtn.addEventListener('click', function() {
                customerImageInput.value = '';
                customerImagePreview.src = '';
                customerImagePreview.classList.add('hidden');
                customerImagePlaceholder.style.display = '';
                removeCustomerImageBtn.classList.add('hidden');
            });
        }

        // Search Functionality
        if (searchInput && customerItems.length > 0) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                customerItems.forEach(item => {
                    const customerName = item.getAttribute('data-name');
                    const shouldShow = customerName && customerName.includes(searchTerm);
                    
                    if (shouldShow) {
                        item.style.display = 'block';
                        item.style.animation = 'fadeIn 0.3s ease-out';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        // Form Validation
        if (addCustomerForm) {
            addCustomerForm.addEventListener('submit', function(e) {
                const firstName = this.querySelector('input[name="FirstName"]').value.trim();
                const phoneNumber = this.querySelector('input[name="PhoneNumber"]').value.trim();
                
                if (!firstName) {
                    e.preventDefault();
                    showNotification('First Name is required', 'error');
                    return;
                }
                
                if (!phoneNumber) {
                    e.preventDefault();
                    showNotification('Phone Number is required', 'error');
                    return;
                }
                
                const phoneRegex = /^[0-9]{10,15}$/;
                if (!phoneRegex.test(phoneNumber)) {
                    e.preventDefault();
                    showNotification('Please enter a valid phone number (10-15 digits)', 'error');
                    return;
                }
            });
        }

        // Notification System
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-4 rounded-2xl shadow-lg z-50 transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i data-feather="${type === 'success' ? 'check-circle' : 'alert-circle'}" class="w-5 h-5 mr-2"></i>
                    ${message}
                </div>
            `;
            document.body.appendChild(notification);
            feather.replace();
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Handle success/error messages from PHP
        <?php if (isset($_GET['success'])): ?>
            if (<?php echo $_GET['success']; ?> == 1) {
                showNotification('Customer added successfully!', 'success');
            }
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            showNotification('<?php echo htmlspecialchars($_GET['error']); ?>', 'error');
        <?php endif; ?>

        // Add fade-in animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>

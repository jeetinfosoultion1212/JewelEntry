<?php
session_start();
require 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    header("Location: login.php?error=Session+expired.+Please+login+again.");
    exit();
}

$firm_id = $_SESSION['firmID'];
$user_id = $_SESSION['id'];

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

// Fetch Gold Saving Plans with enhanced data
$plans = [];
$plansQuery = "SELECT gsp.*, 
               COUNT(DISTINCT cgp.customer_id) as enrolled_customers,
               SUM(cgp.total_amount_paid) as total_revenue,
               AVG(cgp.total_gold_accrued) as avg_gold_accrued
               FROM gold_saving_plans gsp 
               LEFT JOIN customer_gold_plans cgp ON gsp.id = cgp.plan_id AND cgp.firm_id = ?
               WHERE gsp.firm_id = ? 
               GROUP BY gsp.id 
               ORDER BY gsp.id DESC";
$plansStmt = $conn->prepare($plansQuery);
$plansStmt->bind_param("ii", $firm_id, $firm_id);
$plansStmt->execute();
$plansResult = $plansStmt->get_result();
while ($row = $plansResult->fetch_assoc()) {
    $plans[] = $row;
}
$plansStmt->close();

// Fetch Enrolled Customers with enhanced data
$enrollments = [];
$enrollQuery = "SELECT cgp.*, c.FirstName, c.LastName, c.PhoneNumber, c.Email, gsp.plan_name, gsp.duration_months, gsp.installment_frequency,
               DATEDIFF(cgp.maturity_date, CURDATE()) as days_to_maturity,
               (SELECT COUNT(*) FROM gold_plan_installments gpi WHERE gpi.customer_plan_id = cgp.id) as installments_paid,
               (SELECT SUM(amount_paid) FROM gold_plan_installments gpi WHERE gpi.customer_plan_id = cgp.id) as total_paid_verified
               FROM customer_gold_plans cgp 
               JOIN customer c ON cgp.customer_id = c.id 
               JOIN gold_saving_plans gsp ON cgp.plan_id = gsp.id 
               WHERE cgp.firm_id = ? 
               ORDER BY cgp.created_at DESC";
$enrollStmt = $conn->prepare($enrollQuery);
$enrollStmt->bind_param("i", $firm_id);
$enrollStmt->execute();
$enrollResult = $enrollStmt->get_result();
while ($row = $enrollResult->fetch_assoc()) {
    $enrollments[] = $row;
}
$enrollStmt->close();

// Fetch Recent Installments
$installments = [];
$instQuery = "SELECT gpi.*, cgp.customer_id, cgp.plan_id, c.FirstName, c.LastName, c.PhoneNumber, gsp.plan_name,
              cgp.enrollment_date, cgp.maturity_date
              FROM gold_plan_installments gpi 
              JOIN customer_gold_plans cgp ON gpi.customer_plan_id = cgp.id 
              JOIN customer c ON cgp.customer_id = c.id 
              JOIN gold_saving_plans gsp ON cgp.plan_id = gsp.id 
              WHERE cgp.firm_id = ? 
              ORDER BY gpi.created_at DESC LIMIT 50";
$instStmt = $conn->prepare($instQuery);
$instStmt->bind_param("i", $firm_id);
$instStmt->execute();
$instResult = $instStmt->get_result();
while ($row = $instResult->fetch_assoc()) {
    $installments[] = $row;
}
$instStmt->close();

// Calculate Due Installments
$dueInstallments = [];
$dueQuery = "SELECT cgp.*, c.FirstName, c.LastName, c.PhoneNumber, c.Email, gsp.plan_name, gsp.min_amount_per_installment,
             gsp.installment_frequency, gsp.duration_months,
             DATEDIFF(CURDATE(), cgp.enrollment_date) as days_since_enrollment,
             DATEDIFF(cgp.maturity_date, CURDATE()) as days_to_maturity,
             (SELECT MAX(payment_date) FROM gold_plan_installments gpi WHERE gpi.customer_plan_id = cgp.id) as last_payment_date,
             (SELECT COUNT(*) FROM gold_plan_installments gpi WHERE gpi.customer_plan_id = cgp.id) as installments_paid
             FROM customer_gold_plans cgp 
             JOIN customer c ON cgp.customer_id = c.id 
             JOIN gold_saving_plans gsp ON cgp.plan_id = gsp.id 
             WHERE cgp.firm_id = ? AND cgp.current_status = 'active'
             ORDER BY cgp.enrollment_date ASC";
$dueStmt = $conn->prepare($dueQuery);
$dueStmt->bind_param("i", $firm_id);
$dueStmt->execute();
$dueResult = $dueStmt->get_result();

while ($row = $dueResult->fetch_assoc()) {
    $isDue = false;
    $daysSinceLastPayment = 0;
    
    if ($row['last_payment_date']) {
        $daysSinceLastPayment = (new DateTime())->diff(new DateTime($row['last_payment_date']))->days;
    } else {
        $daysSinceLastPayment = $row['days_since_enrollment'];
    }
    
    switch ($row['installment_frequency']) {
        case 'Monthly':
            $isDue = $daysSinceLastPayment >= 30;
            break;
        case 'Weekly':
            $isDue = $daysSinceLastPayment >= 7;
            break;
        case 'Quarterly':
            $isDue = $daysSinceLastPayment >= 90;
            break;
    }
    
    if ($isDue && $row['days_to_maturity'] > 0) {
        $row['days_overdue'] = max(0, $daysSinceLastPayment - 30);
        $dueInstallments[] = $row;
    }
}
$dueStmt->close();

// Get summary statistics
$totalPlans = count($plans);
$activePlans = count(array_filter($plans, fn($p) => strtolower($p['status']) === 'active'));
$totalCustomers = count($enrollments);
$activeCustomers = count(array_filter($enrollments, fn($e) => $e['current_status'] === 'active'));
$totalRevenue = array_sum(array_column($plans, 'total_revenue'));
$totalDueInstallments = count($dueInstallments);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Gold Saving Plans - <?php echo htmlspecialchars($userInfo['FirmName']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'gold': {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            200: '#fde68a',
                            300: '#fcd34d',
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                            900: '#78350f',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        .compact-card { transition: all 0.2s ease; }
        .compact-card:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .tab-button { transition: all 0.3s ease; }
        .tab-button.active { background: #f59e0b; color: white; font-weight: 600; }
        .tab-button:not(.active) { color: #6b7280; background: #f9fafb; }
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
                        <h1 class="text-sm font-semibold text-gray-900">Gold Saving Plans</h1>
                        <p class="text-xs text-gray-500"><?php echo strtoupper(htmlspecialchars($userInfo['FirmName'])); ?></p>
                    </div>
                </div>
                <button onclick="showCreatePlanModal()" class="bg-orange-500 text-white px-3 py-1.5 rounded-md text-xs font-semibold">
                    + New Plan
                </button>
            </div>
        </div>
    </header>

    <main class="px-4 py-3 pb-20 space-y-3">
        <!-- Compact Stats Grid -->
        <div class="grid grid-cols-3 gap-2">
            <div class="bg-white rounded-lg p-2.5 shadow-sm compact-card">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-orange-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-award text-orange-600 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-800"><?php echo $totalPlans; ?></p>
                        <p class="text-xs text-gray-600">Total Plans</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg p-2.5 shadow-sm compact-card">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-green-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-play text-green-600 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-800"><?php echo $activePlans; ?></p>
                        <p class="text-xs text-gray-600">Active Plans</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg p-2.5 shadow-sm compact-card">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-purple-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-purple-600 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-800"><?php echo $totalCustomers; ?></p>
                        <p class="text-xs text-gray-600">Customers</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg p-2.5 shadow-sm compact-card">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-green-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-check text-green-600 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-800"><?php echo $activeCustomers; ?></p>
                        <p class="text-xs text-gray-600">Active</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg p-2.5 shadow-sm compact-card">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-blue-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-rupee-sign text-blue-600 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-800">₹<?php echo number_format($totalRevenue/1000, 0); ?>K</p>
                        <p class="text-xs text-gray-600">Revenue</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg p-2.5 shadow-sm compact-card">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-red-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-800"><?php echo $totalDueInstallments; ?></p>
                        <p class="text-xs text-gray-600">Due</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white rounded-lg p-1 shadow-sm">
            <div class="flex space-x-1">
                <button class="tab-button active flex-1 py-2 px-3 rounded-md text-xs font-medium" onclick="switchTab('plans')" id="tab-plans">
                    <i class="fas fa-award mr-1"></i>Plans
                </button>
                <button class="tab-button flex-1 py-2 px-3 rounded-md text-xs font-medium" onclick="switchTab('customers')" id="tab-customers">
                    <i class="fas fa-users mr-1"></i>Customers
                </button>
                <button class="tab-button flex-1 py-2 px-3 rounded-md text-xs font-medium" onclick="switchTab('due')" id="tab-due">
                    <i class="fas fa-clock mr-1"></i>Due
                    <?php if ($totalDueInstallments > 0): ?>
                    <span class="ml-1 bg-red-500 text-white text-xs px-1 py-0.5 rounded-full"><?php echo $totalDueInstallments; ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-button flex-1 py-2 px-3 rounded-md text-xs font-medium" onclick="switchTab('installments')" id="tab-installments">
                    <i class="fas fa-receipt mr-1"></i>Paid
                </button>
            </div>
        </div>

        <!-- Plans Tab -->
        <section id="content-plans" class="tab-content">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-award mr-2 text-orange-500"></i>Gold Saving Plans
                </h2>
                <button onclick="showCreatePlanModal()" class="bg-orange-500 text-white px-3 py-1.5 rounded-md text-xs font-semibold">
                    + Create Plan
                </button>
            </div>
            
            <div class="space-y-2">
                <?php foreach ($plans as $plan): ?>
                <div class="bg-white rounded-lg p-3 shadow-sm compact-card">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                            <div class="flex items-center space-x-3 mt-1">
                                <span class="text-xs text-gray-600"><?php echo $plan['duration_months']; ?> months</span>
                                <span class="text-xs text-gray-600">₹<?php echo number_format($plan['min_amount_per_installment']); ?>/<?php echo $plan['installment_frequency']; ?></span>
                                <span class="text-xs text-gray-600"><?php echo $plan['bonus_percentage']; ?>% bonus</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="<?php echo strtolower($plan['status']) === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?> px-2 py-1 rounded-full text-xs font-medium">
                                <?php echo ucfirst($plan['status']); ?>
                            </span>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $plan['enrolled_customers'] ?? 0; ?> customers</p>
                        </div>
                    </div>
                    <div class="flex space-x-2 mt-2">
                        <button onclick="editPlan(<?php echo $plan['id']; ?>)" class="flex-1 bg-gray-100 text-gray-700 py-1.5 rounded-md text-xs hover:bg-gray-200">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button onclick="enrollCustomer(<?php echo $plan['id']; ?>)" class="flex-1 bg-blue-500 text-white py-1.5 rounded-md text-xs hover:bg-blue-600">
                            <i class="fas fa-user-plus mr-1"></i>Enroll
                        </button>
                        <button onclick="viewPlan(<?php echo $plan['id']; ?>)" class="flex-1 bg-orange-500 text-white py-1.5 rounded-md text-xs hover:bg-orange-600">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($plans)): ?>
                <div class="bg-white rounded-lg p-6 shadow-sm text-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-award text-orange-500 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">No Plans Created</h3>
                    <p class="text-gray-600 text-sm mb-3">Create your first gold saving plan to get started.</p>
                    <button onclick="showCreatePlanModal()" class="bg-orange-500 text-white px-4 py-2 rounded-md text-sm font-semibold">
                        Create First Plan
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Customers Tab -->
        <section id="content-customers" class="tab-content hidden">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-users mr-2 text-blue-500"></i>Enrolled Customers
                </h2>
                <button onclick="showEnrollModal()" class="bg-blue-500 text-white px-3 py-1.5 rounded-md text-xs font-semibold">
                    + Enroll
                </button>
            </div>
            
            <div class="space-y-2">
                <?php foreach ($enrollments as $enrollment): ?>
                <div class="bg-white rounded-lg p-3 shadow-sm compact-card">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 font-semibold text-xs">
                                    <?php echo strtoupper(substr($enrollment['FirstName'], 0, 1) . substr($enrollment['LastName'], 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($enrollment['FirstName'] . ' ' . $enrollment['LastName']); ?></h3>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($enrollment['plan_name']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-800">₹<?php echo number_format($enrollment['total_amount_paid']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo number_format($enrollment['total_gold_accrued'], 2); ?>g gold</p>
                        </div>
                    </div>
                    <div class="flex space-x-2 mt-2">
                        <button onclick="addInstallment(<?php echo $enrollment['id']; ?>)" class="flex-1 bg-green-500 text-white py-1.5 rounded-md text-xs hover:bg-green-600">
                            <i class="fas fa-plus mr-1"></i>Pay
                        </button>
                        <button onclick="viewCustomer(<?php echo $enrollment['customer_id']; ?>)" class="flex-1 bg-gray-100 text-gray-700 py-1.5 rounded-md text-xs hover:bg-gray-200">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($enrollments)): ?>
                <div class="bg-white rounded-lg p-6 shadow-sm text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-users text-blue-500 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">No Customers Enrolled</h3>
                    <p class="text-gray-600 text-sm mb-3">Start enrolling customers to your plans.</p>
                    <button onclick="showEnrollModal()" class="bg-blue-500 text-white px-4 py-2 rounded-md text-sm font-semibold">
                        Enroll First Customer
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Due Installments Tab -->
        <section id="content-due" class="tab-content hidden">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-clock mr-2 text-red-500"></i>Due Installments
                    <?php if ($totalDueInstallments > 0): ?>
                    <span class="ml-2 bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium">
                        <?php echo $totalDueInstallments; ?>
                    </span>
                    <?php endif; ?>
                </h2>
                <button onclick="sendReminders()" class="bg-red-500 text-white px-3 py-1.5 rounded-md text-xs font-semibold">
                    <i class="fas fa-bell mr-1"></i>Remind
                </button>
            </div>
            
            <div class="space-y-2">
                <?php foreach ($dueInstallments as $due): ?>
                <div class="bg-white rounded-lg p-3 shadow-sm compact-card border-l-4 border-red-400">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                <span class="text-red-600 font-semibold text-xs">
                                    <?php echo strtoupper(substr($due['FirstName'], 0, 1) . substr($due['LastName'], 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($due['FirstName'] . ' ' . $due['LastName']); ?></h3>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($due['plan_name']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-red-600">₹<?php echo number_format($due['min_amount_per_installment']); ?></p>
                            <p class="text-xs text-red-600">Due</p>
                        </div>
                    </div>
                    <div class="flex space-x-2 mt-2">
                        <button onclick="collectPayment(<?php echo $due['id']; ?>)" class="flex-1 bg-green-500 text-white py-1.5 rounded-md text-xs hover:bg-green-600">
                            <i class="fas fa-money-bill mr-1"></i>Collect
                        </button>
                        <button onclick="callCustomer('<?php echo $due['PhoneNumber']; ?>')" class="flex-1 bg-blue-500 text-white py-1.5 rounded-md text-xs hover:bg-blue-600">
                            <i class="fas fa-phone mr-1"></i>Call
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($dueInstallments)): ?>
                <div class="bg-white rounded-lg p-6 shadow-sm text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">All Caught Up!</h3>
                    <p class="text-gray-600 text-sm">No installments are currently due.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Paid Installments Tab -->
        <section id="content-installments" class="tab-content hidden">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-receipt mr-2 text-green-500"></i>Paid Installments
                </h2>
                <button onclick="exportData()" class="bg-green-500 text-white px-3 py-1.5 rounded-md text-xs font-semibold">
                    <i class="fas fa-download mr-1"></i>Export
                </button>
            </div>
            
            <div class="space-y-2">
                <?php foreach (array_slice($installments, 0, 20) as $installment): ?>
                <div class="bg-white rounded-lg p-3 shadow-sm compact-card">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <span class="text-green-600 font-semibold text-xs">
                                    <?php echo strtoupper(substr($installment['FirstName'], 0, 1) . substr($installment['LastName'], 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($installment['FirstName'] . ' ' . $installment['LastName']); ?></h3>
                                <p class="text-xs text-gray-600"><?php echo date('d M Y', strtotime($installment['payment_date'])); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-green-600">₹<?php echo number_format($installment['amount_paid']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo number_format($installment['gold_credited_g'], 3); ?>g</p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($installments)): ?>
                <div class="bg-white rounded-lg p-6 shadow-sm text-center">
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-receipt text-gray-400 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">No Payments Yet</h3>
                    <p class="text-gray-600 text-sm">Payment history will appear here.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Create Plan Modal -->
    <div id="createPlanModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-4 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Create New Plan</h3>
                <button onclick="closeCreatePlanModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="createPlanForm" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Plan Name</label>
                    <input type="text" name="plan_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Duration (months)</label>
                        <input type="number" name="duration_months" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Min Amount</label>
                        <input type="number" name="min_amount_per_installment" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Frequency</label>
                        <select name="installment_frequency" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="Monthly">Monthly</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Quarterly">Quarterly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Bonus %</label>
                        <input type="number" name="bonus_percentage" min="0" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Terms & Conditions</label>
                    <textarea name="terms_conditions" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></textarea>
                </div>
                <div class="flex justify-end space-x-2 pt-3">
                    <button type="button" onclick="closeCreatePlanModal()" class="px-4 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="bg-orange-500 text-white px-4 py-2 text-sm rounded-md hover:bg-orange-600">Create Plan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enroll Customer Modal -->
    <div id="enrollModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-4 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Enroll Customer</h3>
                <button onclick="closeEnrollModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="enrollForm" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Customer</label>
                    <input type="text" id="customerSearch" placeholder="Search customer..." class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <input type="hidden" name="customer_id" id="selectedCustomerId">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Plan</label>
                    <select name="plan_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">Select Plan</option>
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['plan_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="planDetails" class="bg-gray-50 border border-gray-200 rounded-md p-2 mt-2 text-xs text-gray-700 hidden"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="enrollment_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Maturity Date</label>
                        <input type="date" name="maturity_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
                <div class="flex justify-end space-x-2 pt-3">
                    <button type="button" onclick="closeEnrollModal()" class="px-4 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 text-sm rounded-md hover:bg-blue-600">Enroll Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Installment Modal -->
    <div id="installmentModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-4 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add Installment</h3>
                <button onclick="closeInstallmentModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="installmentForm" class="space-y-3">
                <input type="hidden" name="customer_plan_id" id="installmentCustomerPlanId">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Amount Paid</label>
                        <input type="number" name="amount_paid" min="1" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Gold Rate (per gram)</label>
                        <input type="number" name="gold_rate_per_gram" min="1" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Payment Date</label>
                        <input type="date" name="payment_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="UPI">UPI</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Receipt Number</label>
                    <input type="text" name="receipt_number" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                </div>
                <div class="flex justify-end space-x-2 pt-3">
                    <button type="button" onclick="closeInstallmentModal()" class="px-4 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="bg-green-500 text-white px-4 py-2 text-sm rounded-md hover:bg-green-600">Add Payment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Plan Modal -->
    <div id="editPlanModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-4 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Edit Plan</h3>
                <button onclick="closeEditPlanModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editPlanForm" class="space-y-3">
                <input type="hidden" name="plan_id" id="editPlanId">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Plan Name</label>
                    <input type="text" name="plan_name" id="editPlanName" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Duration (months)</label>
                        <input type="number" name="duration_months" id="editDurationMonths" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Min Amount</label>
                        <input type="number" name="min_amount_per_installment" id="editMinAmount" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Frequency</label>
                        <select name="installment_frequency" id="editFrequency" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="Monthly">Monthly</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Quarterly">Quarterly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Bonus %</label>
                        <input type="number" name="bonus_percentage" id="editBonus" min="0" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="editStatus" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="editDescription" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Terms & Conditions</label>
                    <textarea name="terms_conditions" id="editTerms" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></textarea>
                </div>
                <div class="flex justify-end space-x-2 pt-3">
                    <button type="button" onclick="closeEditPlanModal()" class="px-4 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="bg-orange-500 text-white px-4 py-2 text-sm rounded-md hover:bg-orange-600">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

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
                <a href="add.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-package text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Inventory</span>
                </a>
                <button class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-bell text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Alerts</span>
                </button>
                <a href="customer.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Customers</span>
                </a>
                <a href="gold_plan.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-orange-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-award text-white text-xs"></i>
                    </div>
                    <span class="text-xs text-orange-600 font-semibold mt-1">Gold Plans</span>
                </a>
            </div>
        </div>
    </nav>

    <script>
        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            document.getElementById(`content-${tabName}`).classList.remove('hidden');
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }

        // Modal functions
        function showCreatePlanModal() {
            document.getElementById('createPlanModal').classList.remove('hidden');
        }

        function closeCreatePlanModal() {
            document.getElementById('createPlanModal').classList.add('hidden');
        }

        function showEnrollModal() {
            document.getElementById('enrollModal').classList.remove('hidden');
        }

        function closeEnrollModal() {
            document.getElementById('enrollModal').classList.add('hidden');
        }

        function showInstallmentModal(customerPlanId) {
            document.getElementById('installmentCustomerPlanId').value = customerPlanId;
            document.getElementById('installmentModal').classList.remove('hidden');
        }

        function closeInstallmentModal() {
            document.getElementById('installmentModal').classList.add('hidden');
        }

        // Action functions
        function editPlan(planId) {
            // Find the plan data from the global JS array
            const plan = window.plans.find(p => p.id == planId);
            if (!plan) {
                alert('Plan not found!');
                return;
            }
            document.getElementById('editPlanId').value = plan.id;
            document.getElementById('editPlanName').value = plan.plan_name;
            document.getElementById('editDurationMonths').value = plan.duration_months;
            document.getElementById('editMinAmount').value = plan.min_amount_per_installment;
            document.getElementById('editFrequency').value = plan.installment_frequency;
            document.getElementById('editBonus').value = plan.bonus_percentage;
            document.getElementById('editStatus').value = plan.status;
            document.getElementById('editDescription').value = plan.description;
            document.getElementById('editTerms').value = plan.terms_conditions;
            document.getElementById('editPlanModal').classList.remove('hidden');
        }

        function closeEditPlanModal() {
            document.getElementById('editPlanModal').classList.add('hidden');
        }

        function enrollCustomer(planId) {
            showEnrollModal();
            document.querySelector('[name="plan_id"]').value = planId;
        }

        function viewPlan(planId) {
            alert('View plan functionality will be implemented for plan ID: ' + planId);
        }

        function addInstallment(customerPlanId) {
            showInstallmentModal(customerPlanId);
        }

        function viewCustomer(customerId) {
            alert('View customer functionality will be implemented for customer ID: ' + customerId);
        }

        function collectPayment(customerPlanId) {
            showInstallmentModal(customerPlanId);
        }

        function callCustomer(phoneNumber) {
            window.open('tel:' + phoneNumber);
        }

        function sendReminders() {
            alert('Send reminders functionality will be implemented');
        }

        function exportData() {
            alert('Export data functionality will be implemented');
        }

        // Customer search dropdown implementation
        const customerInput = document.getElementById('customerSearch');
        const customerDropdown = document.createElement('div');
        customerDropdown.id = 'customerDropdown';
        customerDropdown.className = 'absolute z-50 bg-white border border-gray-300 rounded-md mt-1 w-full hidden';
        customerInput.parentNode.appendChild(customerDropdown);
        const selectedCustomerId = document.getElementById('selectedCustomerId');

        customerInput.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length < 2) {
                customerDropdown.classList.add('hidden');
                return;
            }
            fetch(`api/search_customers.php?term=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(customers => {
                    if (!Array.isArray(customers) || customers.length === 0) {
                        customerDropdown.innerHTML = '<div class="p-2 text-gray-500">No customers found</div>';
                        customerDropdown.classList.remove('hidden');
                        return;
                    }
                    customerDropdown.innerHTML = customers.map(c =>
                        `<div class="p-2 hover:bg-blue-100 cursor-pointer" data-id="${c.id}" data-name="${c.name}">${c.name} <span class="text-xs text-gray-400">(${c.phone})</span></div>`
                    ).join('');
                    customerDropdown.classList.remove('hidden');
                });
        });

        customerDropdown.addEventListener('click', function(e) {
            const target = e.target.closest('[data-id]');
            if (target) {
                customerInput.value = target.dataset.name;
                selectedCustomerId.value = target.dataset.id;
                customerDropdown.classList.add('hidden');
            }
        });

        document.addEventListener('click', function(e) {
            if (!customerInput.contains(e.target) && !customerDropdown.contains(e.target)) {
                customerDropdown.classList.add('hidden');
            }
        });

        // Create Plan form submission with new fields
        const createPlanForm = document.getElementById('createPlanForm');
        createPlanForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('firm_id', '<?php echo $firm_id; ?>');
            formData.append('created_by', '<?php echo $user_id; ?>');
            fetch('create_gold_plan.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Plan created successfully!');
                    window.location.reload();
                } else {
                    alert(data.message || 'Error creating plan');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating plan');
            });
        });

        // Handle Edit Plan form submit
        document.getElementById('editPlanForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('edit_gold_plan.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Plan updated successfully!');
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating plan');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating plan');
            });
        });

        // Plan details and maturity date auto-fill
        const planSelect = document.querySelector('#enrollModal select[name="plan_id"]');
        const planDetailsDiv = document.getElementById('planDetails');
        const startDateInput = document.querySelector('#enrollModal input[name="enrollment_date"]');
        const maturityDateInput = document.querySelector('#enrollModal input[name="maturity_date"]');

        function showPlanDetails(planId) {
            const plan = window.plans.find(p => p.id == planId);
            if (!plan) {
                planDetailsDiv.classList.add('hidden');
                planDetailsDiv.innerHTML = '';
                return;
            }
            // Show plan details
            planDetailsDiv.innerHTML = `
                <div><b>Duration:</b> ${plan.duration_months} months</div>
                <div><b>EMI Amount:</b> ₹${Number(plan.min_amount_per_installment).toLocaleString()} / ${plan.installment_frequency}</div>
                <div><b>Bonus:</b> ${plan.bonus_percentage}%</div>
                <div><b>Description:</b> ${plan.description || 'N/A'}</div>
                <div><b>Terms:</b> ${plan.terms_conditions || 'N/A'}</div>
            `;
            planDetailsDiv.classList.remove('hidden');
            // Auto-calculate maturity date if start date is selected
            if (startDateInput.value) {
                const start = new Date(startDateInput.value);
                start.setMonth(start.getMonth() + parseInt(plan.duration_months));
                // Format as yyyy-mm-dd
                const yyyy = start.getFullYear();
                const mm = String(start.getMonth() + 1).padStart(2, '0');
                const dd = String(start.getDate()).padStart(2, '0');
                maturityDateInput.value = `${yyyy}-${mm}-${dd}`;
            }
        }

        // When plan changes
        planSelect.addEventListener('change', function() {
            showPlanDetails(this.value);
        });

        // When start date changes, recalculate maturity date if plan is selected
        startDateInput.addEventListener('change', function() {
            if (planSelect.value) {
                showPlanDetails(planSelect.value);
            }
        });

        // Enroll Customer form submission
        const enrollForm = document.getElementById('enrollForm');
        enrollForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('firm_id', '<?php echo $firm_id; ?>');
            formData.append('created_by', '<?php echo $user_id; ?>');
            fetch('api/add_gold_plan_enrollment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Customer enrolled and installments created successfully!');
                    window.location.reload();
                } else {
                    alert(data.message || 'Error enrolling customer');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error enrolling customer');
            });
        });
    </script>

    <script>
    window.plans = <?php echo json_encode($plans); ?>;
    </script>
</body>
</html>

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

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    header("Location: customers.php?error=Invalid+customer+ID");
    exit();
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user info for header
$userQuery = "SELECT Name, Role, image_path FROM Firm_Users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userInfo = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

// Fetch customer details
$customer = null;
$customerQuery = "SELECT id, FirstName, LastName, Address, City, State, PhoneNumber, Email, DateOfBirth, Gender, PANNumber, AadhaarNumber, CustomerImage FROM customer WHERE id = ? AND firm_id  = ?";
$customerStmt = $conn->prepare($customerQuery);
$customerStmt->bind_param("ii", $customer_id, $firm_id);
$customerStmt->execute();
$customerResult = $customerStmt->get_result();
if ($customerResult->num_rows > 0) {
    $customer = $customerResult->fetch_assoc();
} else {
    header("Location: customers.php?error=Customer+not+found");
    exit();
}
$customerStmt->close();

// Fetch financial summaries
$total_due_sales = 0;
$salesQuery = "SELECT SUM(due_amount) as total_due FROM jewellery_sales WHERE customer_id = ? AND payment_status IN ('Unpaid','Partial')";
$salesStmt = $conn->prepare($salesQuery);
$salesStmt->bind_param("i", $customer_id);
$salesStmt->execute();
$salesResult = $salesStmt->get_result();
if ($salesResult->num_rows > 0) {
    $row = $salesResult->fetch_assoc();
    $total_due_sales = $row['total_due'] ?? 0;
}
$salesStmt->close();

$total_outstanding_loans = 0;
$loansQuery = "SELECT SUM(outstanding_amount) as total_loan FROM loans WHERE customer_id = ? AND current_status = 'active'";
$loansStmt = $conn->prepare($loansQuery);
$loansStmt->bind_param("i", $customer_id);
$loansStmt->execute();
$loansResult = $loansStmt->get_result();
if ($loansResult->num_rows > 0) {
    $row = $loansResult->fetch_assoc();
    $total_outstanding_loans = $row['total_loan'] ?? 0;
}
$loansStmt->close();

$total_emi_due = 0;
$emiQuery = "SELECT SUM(amount) as emi_due FROM loan_emis WHERE customer_id = ? AND status = 'due' AND MONTH(due_date) = MONTH(CURDATE()) AND YEAR(due_date) = YEAR(CURDATE())";
$emiStmt = $conn->prepare($emiQuery);
$emiStmt->bind_param("i", $customer_id);
$emiStmt->execute();
$emiResult = $emiStmt->get_result();
if ($emiResult && $emiResult->num_rows > 0) {
    $row = $emiResult->fetch_assoc();
    $total_emi_due = $row['emi_due'] ?? 0;
}
$emiStmt->close();

$total_gold_due = 0;
$planQuery = "SELECT id FROM customer_gold_plans WHERE customer_id = ? AND current_status = 'active'";
$planStmt = $conn->prepare($planQuery);
$planStmt->bind_param("i", $customer_id);
$planStmt->execute();
$planResult = $planStmt->get_result();
$planIds = [];
while ($row = $planResult->fetch_assoc()) {
    $planIds[] = $row['id'];
}
$planStmt->close();

if (count($planIds) > 0) {
    $planIdsString = implode(',', array_map('intval', $planIds));
    $installmentQuery = "SELECT SUM(gsp.min_amount_per_installment) as gold_due FROM gold_saving_plans gsp JOIN customer_gold_plans cgp ON gsp.id = cgp.plan_id WHERE cgp.id IN ($planIdsString)";
    $installmentResult = $conn->query($installmentQuery);
    if ($installmentResult) {
        $row = $installmentResult->fetch_assoc();
        $total_gold_due = $row['gold_due'] ?? 0;
    }
}

// Fetch recent transactions
$recent_sales = [];
$recentSalesQuery = "SELECT id, sale_date, grand_total, due_amount FROM jewellery_sales WHERE customer_id = ? ORDER BY sale_date DESC LIMIT 10";
$recentSalesStmt = $conn->prepare($recentSalesQuery);
$recentSalesStmt->bind_param("i", $customer_id);
$recentSalesStmt->execute();
$recentSalesResult = $recentSalesStmt->get_result();
while ($row = $recentSalesResult->fetch_assoc()) {
    $recent_sales[] = $row;
}
$recentSalesStmt->close();

// Fetch active loans
$active_loans = [];
$activeLoansQuery = "SELECT id, principal_amount, outstanding_amount, interest_rate, loan_term_months FROM loans WHERE customer_id = ? AND current_status = 'active' ORDER BY loan_date DESC";
$activeLoansStmt = $conn->prepare($activeLoansQuery);
$activeLoansStmt->bind_param("i", $customer_id);
$activeLoansStmt->execute();
$activeLoansResult = $activeLoansStmt->get_result();
while ($row = $activeLoansResult->fetch_assoc()) {
    $active_loans[] = $row;
}
$activeLoansStmt->close();

// Fetch active schemes
$active_schemes = [];
$activeSchemesQuery = "SELECT 
    cgp.id,
    cgp.enrollment_date,
    cgp.maturity_date,
    cgp.total_amount_paid,
    cgp.total_gold_accrued,
    gp.plan_name,
    gp.duration_months,
    gp.min_amount_per_installment,
    gp.installment_frequency,
    gp.bonus_percentage,
    FLOOR(cgp.total_amount_paid / gp.min_amount_per_installment) as installments_paid,
    gp.duration_months - FLOOR(cgp.total_amount_paid / gp.min_amount_per_installment) as installments_remaining
FROM customer_gold_plans cgp 
JOIN gold_saving_plans gp ON cgp.plan_id = gp.id 
WHERE cgp.customer_id = ? AND cgp.current_status = 'active' 
ORDER BY cgp.enrollment_date DESC";
$activeSchemesStmt = $conn->prepare($activeSchemesQuery);
$activeSchemesStmt->bind_param("i", $customer_id);
$activeSchemesStmt->execute();
$activeSchemesResult = $activeSchemesStmt->get_result();
while ($row = $activeSchemesResult->fetch_assoc()) {
    $active_schemes[] = $row;
}
$activeSchemesStmt->close();

// Fetch payments for this customer
$customer_payments = [];
$paymentsQuery = "SELECT id, amount, payment_type, payment_notes, reference_type, reference_no, remarks, created_at FROM jewellery_payments WHERE party_type = 'customer' AND party_id = ? AND Firm_id = ? AND transctions_type = 'credit' ORDER BY created_at DESC LIMIT 20";
$paymentsStmt = $conn->prepare($paymentsQuery);
$paymentsStmt->bind_param("ii", $customer_id, $firm_id);
$paymentsStmt->execute();
$paymentsResult = $paymentsStmt->get_result();
while ($row = $paymentsResult->fetch_assoc()) {
    $customer_payments[] = $row;
}
$paymentsStmt->close();

$conn->close();

// Check for success/error messages
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title><?php echo htmlspecialchars($customer['FirstName'] . ' ' . $customer['LastName']); ?> - JewelEntry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        
        .gradient-jewel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card-hover {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-hover:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .tab-button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .modal-backdrop {
            backdrop-filter: blur(12px);
            background: rgba(0, 0, 0, 0.5);
        }
        
        .payment-item {
            transition: all 0.2s ease;
        }
        
        .payment-item:hover {
            background-color: #f8fafc;
        }
        
        .payment-item.selected {
            background-color: #e0f2fe;
            border-color: #0284c7;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 350px;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease-out;
        }
        
        .notification-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .notification-error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
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
        
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        
        .status-active { background-color: #10b981; }
        .status-due { background-color: #ef4444; }
        .status-partial { background-color: #f59e0b; }
        .status-upcoming { background-color: #3b82f6; }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform-origin: 50% 50%;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    
    <!-- Success/Error Notifications -->
    <?php if ($success_message): ?>
        <div class="notification notification-success" id="successNotification">
            <div class="flex items-center">
                <i data-feather="check-circle" class="w-4 h-4 mr-2"></i>
                <span class="text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="notification notification-error" id="errorNotification">
            <div class="flex items-center">
                <i data-feather="alert-circle" class="w-4 h-4 mr-2"></i>
                <span class="text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Compact Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="customers.php" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center hover:bg-gray-200 transition-colors">
                        <i data-feather="arrow-left" class="w-4 h-4 text-gray-600"></i>
                    </a>
                    <div>
                        <h1 class="text-base font-semibold text-gray-900">Customer Details</h1>
                        <p class="text-xs text-gray-500">JewelEntry</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p class="text-xs font-medium text-gray-900"><?php echo htmlspecialchars($userInfo['Name'] ?? ''); ?></p>
                        <p class="text-xs text-indigo-600"><?php echo htmlspecialchars($userInfo['Role'] ?? ''); ?></p>
                    </div>
                    <div class="w-8 h-8 gradient-jewel rounded-lg flex items-center justify-center overflow-hidden">
                        <?php if (!empty($userInfo['image_path']) && file_exists($userInfo['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i data-feather="user" class="w-4 h-4 text-white"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 pt-3 pb-20 max-w-md mx-auto">
        
        <!-- Compact Customer Profile -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-3 relative">
            <!-- Edit Button -->
            <button id="editCustomerBtn" class="absolute top-3 right-3 w-7 h-7 bg-gray-50 hover:bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center transition-colors">
                <i data-feather="edit-2" class="w-3.5 h-3.5"></i>
            </button>
            
            <div class="flex items-start space-x-3">
                <!-- Compact Avatar -->
                <div class="relative">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center overflow-hidden">
                        <?php if (!empty($customer['CustomerImage']) && file_exists($customer['CustomerImage'])): ?>
                            <img src="<?= htmlspecialchars($customer['CustomerImage']); ?>" alt="Customer" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-indigo-600 font-semibold text-sm">
                                <?= strtoupper(substr($customer['FirstName'], 0, 1) . substr($customer['LastName'], 0, 1)) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                </div>
                
                <!-- Customer Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h2 class="text-base font-semibold text-gray-900 leading-tight">
                                <?php echo htmlspecialchars($customer['FirstName'] . ' ' . $customer['LastName']); ?>
                            </h2>
                            <div class="flex items-center text-xs text-gray-500 mt-0.5">
                                <?php if ($customer['PhoneNumber']): ?>
                                    <i data-feather="phone" class="w-3 h-3 mr-1"></i>
                                    <span><?= htmlspecialchars($customer['PhoneNumber']); ?></span>
                                <?php endif; ?>
                                <?php if ($customer['City']): ?>
                                    <span class="mx-1">•</span>
                                    <span><?= htmlspecialchars($customer['City']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- WhatsApp Button -->
                        <?php if ($customer['PhoneNumber']): ?>
                            <a href="https://wa.me/91<?= $customer['PhoneNumber'] ?>?text=<?= urlencode('Hello ' . $customer['FirstName'] . ', Greetings from JewelEntry!') ?>" 
                               target="_blank" 
                               class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center text-white hover:bg-green-600 transition-colors">
                                <i data-feather="message-circle" class="w-4 h-4"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compact Financial Summary -->
        <?php 
        $hasFinancials = ($total_due_sales > 0) || ($total_outstanding_loans > 0) || (($total_emi_due + $total_gold_due) > 0);
        if ($hasFinancials): 
        ?>
        <div class="bg-white rounded-xl border border-gray-200 p-3 mb-3">
            <h3 class="text-sm font-semibold text-gray-900 mb-2 flex items-center">
                <i data-feather="credit-card" class="w-4 h-4 mr-1.5 text-gray-500"></i>
                Financial Summary
            </h3>
            <div class="grid grid-cols-2 gap-2">
                <?php if ($total_due_sales > 0): ?>
                    <div class="bg-red-50 border border-red-100 rounded-lg p-2">
                        <div class="flex items-center justify-between">
                            <div class="w-6 h-6 bg-red-100 rounded-md flex items-center justify-center">
                                <i data-feather="alert-circle" class="w-3 h-3 text-red-600"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-red-700">₹<?= number_format($total_due_sales, 0) ?></p>
                                <p class="text-xs text-red-600">Due Amount</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($total_outstanding_loans > 0): ?>
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-2">
                        <div class="flex items-center justify-between">
                            <div class="w-6 h-6 bg-blue-100 rounded-md flex items-center justify-center">
                                <i data-feather="credit-card" class="w-3 h-3 text-blue-600"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-blue-700">₹<?= number_format($total_outstanding_loans, 0) ?></p>
                                <p class="text-xs text-blue-600">Loan Outstanding</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php $monthlyDueTotal = $total_emi_due + $total_gold_due; ?>
                <?php if ($monthlyDueTotal > 0): ?>
                    <div class="bg-amber-50 border border-amber-100 rounded-lg p-2 <?= ($total_due_sales > 0 && $total_outstanding_loans > 0) ? 'col-span-2' : '' ?>">
                        <div class="flex items-center justify-between">
                            <div class="w-6 h-6 bg-amber-100 rounded-md flex items-center justify-center">
                                <i data-feather="calendar" class="w-3 h-3 text-amber-600"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-amber-700">₹<?= number_format($monthlyDueTotal, 0) ?></p>
                                <p class="text-xs text-amber-600">Monthly Due</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Compact Action Buttons -->
        <div class="grid grid-cols-2 gap-2 mb-3">
            <button id="openPaymentModalBtn" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white py-2.5 px-3 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all flex items-center justify-center">
                <i data-feather="credit-card" class="w-4 h-4 mr-1.5"></i>
                Accept Payment
            </button>
            <button class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white py-2.5 px-3 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all flex items-center justify-center">
                <i data-feather="plus" class="w-4 h-4 mr-1.5"></i>
                New Transaction
            </button>
        </div>

        <!-- Compact Section Tabs -->
        <div class="mb-3">
            <div id="detailTabs" class="flex bg-gray-100 rounded-lg p-1 overflow-x-auto hide-scrollbar">
                <button class="tab-button flex-shrink-0 px-3 py-1.5 text-xs font-medium rounded-md transition-all duration-200 active" data-tab="transactions">
                    <i data-feather="activity" class="w-3.5 h-3.5 mr-1 inline"></i>
                    Transactions
                </button>
                <button class="tab-button flex-shrink-0 px-3 py-1.5 text-xs font-medium rounded-md transition-all duration-200 text-gray-600 hover:text-gray-900" data-tab="loans">
                    <i data-feather="credit-card" class="w-3.5 h-3.5 mr-1 inline"></i>
                    Loans
                </button>
                <button class="tab-button flex-shrink-0 px-3 py-1.5 text-xs font-medium rounded-md transition-all duration-200 text-gray-600 hover:text-gray-900" data-tab="schemes">
                    <i data-feather="award" class="w-3.5 h-3.5 mr-1 inline"></i>
                    Schemes
                </button>
                <button class="tab-button flex-shrink-0 px-3 py-1.5 text-xs font-medium rounded-md transition-all duration-200 text-gray-600 hover:text-gray-900" data-tab="payments">
                    <i data-feather="dollar-sign" class="w-3.5 h-3.5 mr-1 inline"></i>
                    Payments
                </button>
            </div>
        </div>

        <!-- Compact Tab Content -->
        <div id="tabContent">
            <!-- Recent Transactions -->
            <div id="transactions" class="tab-pane">
                <div class="bg-white rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                            <i data-feather="activity" class="w-4 h-4 mr-1.5 text-indigo-500"></i>
                            Recent Transactions
                        </h3>
                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"><?= count($recent_sales) ?></span>
                    </div>
                    
                    <?php if (count($recent_sales) > 0): ?>
                        <div class="space-y-2">
                            <?php foreach ($recent_sales as $sale): ?>
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-7 h-7 bg-indigo-100 rounded-md flex items-center justify-center">
                                            <i data-feather="shopping-bag" class="w-3.5 h-3.5 text-indigo-600"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Sale #<?= $sale['id'] ?></p>
                                            <p class="text-xs text-gray-500"><?= date('d M, Y', strtotime($sale['sale_date'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-gray-900">₹<?= number_format($sale['grand_total'], 0) ?></p>
                                        <?php if ($sale['due_amount'] > 0): ?>
                                            <p class="text-xs text-red-600 font-medium">Due: ₹<?= number_format($sale['due_amount'], 0) ?></p>
                                        <?php else: ?>
                                            <p class="text-xs text-green-600 font-medium">Paid</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i data-feather="shopping-bag" class="w-6 h-6 text-gray-400"></i>
                            </div>
                            <p class="text-sm text-gray-500 font-medium">No transactions found</p>
                            <p class="text-xs text-gray-400">Start by creating a new sale</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Loans -->
            <div id="loans" class="tab-pane hidden">
                <div class="bg-white rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                            <i data-feather="credit-card" class="w-4 h-4 mr-1.5 text-blue-500"></i>
                            Active Loans
                        </h3>
                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"><?= count($active_loans) ?></span>
                    </div>
                    
                    <?php if (count($active_loans) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($active_loans as $loan): ?>
                                <div class="bg-blue-50 border border-blue-100 rounded-lg p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-7 h-7 bg-blue-100 rounded-md flex items-center justify-center">
                                                <i data-feather="credit-card" class="w-3.5 h-3.5 text-blue-600"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-blue-900">Loan #<?= $loan['id'] ?></p>
                                                <p class="text-xs text-blue-600"><?= $loan['loan_term_months'] ?> months</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-blue-900">₹<?= number_format($loan['outstanding_amount'], 0) ?></p>
                                            <p class="text-xs text-blue-600">Outstanding</p>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="bg-white/60 rounded-md p-2">
                                            <p class="text-xs text-blue-600">Principal</p>
                                            <p class="text-sm font-medium text-blue-900">₹<?= number_format($loan['principal_amount'], 0) ?></p>
                                        </div>
                                        <div class="bg-white/60 rounded-md p-2">
                                            <p class="text-xs text-blue-600">Interest</p>
                                            <p class="text-sm font-medium text-blue-900"><?= $loan['interest_rate'] ?>% p.a.</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i data-feather="credit-card" class="w-6 h-6 text-gray-400"></i>
                            </div>
                            <p class="text-sm text-gray-500 font-medium">No active loans</p>
                            <p class="text-xs text-gray-400">Customer has no outstanding loans</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Schemes -->
            <div id="schemes" class="tab-pane hidden">
                <div class="bg-white rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                            <i data-feather="award" class="w-4 h-4 mr-1.5 text-amber-500"></i>
                            Gold Saving Schemes
                        </h3>
                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"><?= count($active_schemes) ?></span>
                    </div>
                    
                    <?php if (count($active_schemes) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($active_schemes as $scheme): ?>
                                <div class="bg-amber-50 border border-amber-100 rounded-lg p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 bg-amber-100 rounded-md flex items-center justify-center">
                                                <i data-feather="award" class="w-4 h-4 text-amber-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-sm font-medium text-amber-900"><?= htmlspecialchars($scheme['plan_name']) ?></h4>
                                                <p class="text-xs text-amber-600">
                                                    Started: <?= date('d M, Y', strtotime($scheme['enrollment_date'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-amber-900">₹<?= number_format($scheme['min_amount_per_installment'], 0) ?></p>
                                            <p class="text-xs text-amber-600">per month</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Compact Progress -->
                                    <div class="mb-2">
                                        <div class="flex justify-between text-xs text-amber-700 mb-1">
                                            <span><?= $scheme['installments_paid'] ?>/<?= $scheme['duration_months'] ?> installments</span>
                                            <span><?= round(($scheme['installments_paid'] / $scheme['duration_months']) * 100, 1) ?>%</span>
                                        </div>
                                        <div class="w-full bg-amber-200 rounded-full h-2">
                                            <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-2 rounded-full transition-all duration-500" 
                                                 style="width: <?= ($scheme['installments_paid'] / $scheme['duration_months']) * 100 ?>%">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Compact Details Grid -->
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="bg-white/60 rounded-md p-2">
                                            <p class="text-xs text-amber-600">Total Paid</p>
                                            <p class="text-sm font-medium text-amber-900">₹<?= number_format($scheme['total_amount_paid'], 0) ?></p>
                                        </div>
                                        <div class="bg-white/60 rounded-md p-2">
                                            <p class="text-xs text-amber-600">Gold Accrued</p>
                                            <p class="text-sm font-medium text-amber-900"><?= number_format($scheme['total_gold_accrued'], 3) ?> g</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i data-feather="award" class="w-6 h-6 text-gray-400"></i>
                            </div>
                            <p class="text-sm text-gray-500 font-medium">No active schemes</p>
                            <p class="text-xs text-gray-400">Enroll in a gold saving plan</p>
                            <button class="mt-2 bg-gradient-to-r from-amber-500 to-amber-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium">
                                <i data-feather="plus" class="w-3 h-3 mr-1 inline"></i>
                                Enroll in Scheme
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payments Tab -->
            <div id="payments" class="tab-pane hidden">
                <div class="bg-white rounded-xl border border-gray-200 p-3">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                            <i data-feather="dollar-sign" class="w-4 h-4 mr-1.5 text-green-500"></i>
                            Payments
                        </h3>
                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"><?php echo count($customer_payments); ?></span>
                    </div>
                    <?php if (count($customer_payments) > 0): ?>
                        <div class="space-y-2">
                            <?php foreach ($customer_payments as $payment): ?>
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors text-xs">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-gray-800">₹<?= number_format($payment['amount'], 2) ?></span>
                                            <span class="text-gray-500 ml-2"><?php echo date('d M, Y', strtotime($payment['created_at'])); ?></span>
                                        </div>
                                        <div class="flex items-center mt-1 space-x-2">
                                            <span class="text-green-600 font-semibold"><?php echo htmlspecialchars($payment['payment_type']); ?></span>
                                            <?php if ($payment['reference_type']): ?>
                                                <span class="text-gray-400">|</span>
                                                <span class="text-gray-500"><?php echo htmlspecialchars($payment['reference_type']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($payment['payment_notes']): ?>
                                                <span class="text-gray-400">|</span>
                                                <span class="text-gray-500"><?php echo htmlspecialchars($payment['payment_notes']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($payment['remarks']): ?>
                                            <div class="text-gray-400 mt-0.5 truncate"><?php echo htmlspecialchars($payment['remarks']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-2 text-gray-400 text-[10px]">
                                        <span>#<?= $payment['id'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i data-feather="dollar-sign" class="w-6 h-6 text-gray-400"></i>
                            </div>
                            <p class="text-sm text-gray-500 font-medium">No payments found</p>
                            <p class="text-xs text-gray-400">No payment records for this customer</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden p-2">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xs mx-auto max-h-[90vh] overflow-y-auto">
            <form id="paymentForm" method="POST" action="process_payment.php">
                <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                <!-- Modal Header -->
                <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-green-50 to-emerald-50 rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-gray-900 flex items-center">
                            <i data-feather="credit-card" class="w-5 h-5 text-green-600 mr-2"></i>
                            Accept Payment
                        </h3>
                        <button type="button" id="cancelPaymentModal" class="w-7 h-7 bg-white/80 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors">
                            <i data-feather="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
                <!-- Modal Body -->
                <div class="px-4 py-4">
                    <div class="space-y-3">
                        <div>
                            <label for="payment_type" class="block text-xs font-semibold text-gray-700 mb-1">Payment For</label>
                            <select name="type" id="payment_type" required class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500/20 focus:border-green-300 text-xs bg-white font-medium">
                                <option value="">Select Payment Type</option>
                                <option value="Sale Due">Sale Due</option>
                                <option value="Loan EMI">Loan EMI</option>
                                <option value="Loan Principal">Loan Principal</option>
                                <option value="Scheme Installment">Scheme Installment</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div id="paymentDetailsSection" class="hidden">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label for="payment_amount" class="block text-xs font-semibold text-gray-700 mb-1">Amount</label>
                                    <div class="relative">
                                        <span class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 font-medium text-xs">₹</span>
                                        <input type="number" step="0.01" name="amount" id="payment_amount" required class="w-full pl-6 pr-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500/20 focus:border-green-300 text-xs font-medium">
                                    </div>
                                </div>
                                <div>
                                    <label for="payment_method" class="block text-xs font-semibold text-gray-700 mb-1">Method</label>
                                    <select name="method" id="payment_method" required class="w-full px-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500/20 focus:border-green-300 text-xs bg-white font-medium">
                                        <option value="Cash">Cash</option>
                                        <option value="Card">Card</option>
                                        <option value="UPI">UPI</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-2">
                                <label for="payment_notes" class="block text-xs font-semibold text-gray-700 mb-1">Notes (Optional)</label>
                                <textarea name="notes" id="payment_notes" rows="2" class="w-full px-2 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500/20 focus:border-green-300 text-xs resize-none" placeholder="Add any notes..."></textarea>
                            </div>
                        </div>
                        <!-- FIFO Allocation Section (unchanged, but compact) -->
                        <div id="fifoAllocationSection" class="hidden mt-2">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-xs font-semibold text-gray-700">Payment Allocation (FIFO)</h4>
                                <button type="button" id="autoAllocateBtn" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-xl hover:bg-green-200 transition-colors font-medium">Auto Allocate</button>
                            </div>
                            <div id="fifoItemsList" class="space-y-2 max-h-32 overflow-y-auto hide-scrollbar border border-gray-200 rounded-xl p-2 bg-gray-50 text-xs">
                                <p class="text-xs text-gray-500 text-center py-2">Select payment type to load items.</p>
                            </div>
                            <div class="mt-1 text-[10px] text-gray-500 flex items-center">
                                <i data-feather="info" class="w-3 h-3 mr-1"></i>
                                Payments are allocated to oldest dues first (FIFO method)
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Modal Footer -->
                <div class="px-4 py-3 border-t border-gray-100 flex justify-end space-x-2">
                    <button type="button" id="cancelPaymentModal2" class="px-4 py-2 text-xs font-semibold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">Cancel</button>
                    <button type="submit" id="submitPaymentBtn" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white py-2 px-4 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center text-xs">
                        <i data-feather="check-circle" class="w-4 h-4 mr-1"></i>
                        Accept Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="editCustomerModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg mx-auto max-h-[90vh] overflow-y-auto">
            <form id="editCustomerForm" method="POST" action="update_customer.php" enctype="multipart/form-data">
                <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-t-3xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i data-feather="edit-2" class="w-6 h-6 text-indigo-600 mr-3"></i>
                            Edit Customer
                        </h3>
                        <button type="button" id="cancelEditCustomer" class="w-8 h-8 bg-white/80 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors">
                            <i data-feather="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="px-6 py-6">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="edit_first_name" class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                                <input type="text" name="first_name" id="edit_first_name" required
                                       class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm"
                                       value="<?= htmlspecialchars($customer['FirstName']) ?>">
                            </div>
                            <div>
                                <label for="edit_last_name" class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" required
                                       class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm"
                                       value="<?= htmlspecialchars($customer['LastName']) ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="edit_phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" name="phone" id="edit_phone" required
                                   class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm"
                                   value="<?= htmlspecialchars($customer['PhoneNumber']) ?>">
                        </div>
                        
                        <div>
                            <label for="edit_email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                            <input type="email" name="email" id="edit_email"
                                   class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm"
                                   value="<?= htmlspecialchars($customer['Email']) ?>">
                        </div>
                        
                        <div>
                            <label for="edit_address" class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                            <textarea name="address" id="edit_address" rows="2"
                                      class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm resize-none"><?= htmlspecialchars($customer['Address']) ?></textarea>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="edit_city" class="block text-sm font-semibold text-gray-700 mb-2">City</label>
                                <input type="text" name="city" id="edit_city"
                                       class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm"
                                       value="<?= htmlspecialchars($customer['City']) ?>">
                            </div>
                            <div>
                                <label for="edit_state" class="block text-sm font-semibold text-gray-700 mb-2">State</label>
                                <input type="text" name="state" id="edit_state"
                                       class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm"
                                       value="<?= htmlspecialchars($customer['State']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-5 border-t border-gray-100 flex justify-end space-x-3">
                    <button type="button" id="cancelEditCustomer2"
                            class="px-6 py-3 text-sm font-semibold text-gray-600 bg-gray-100 rounded-2xl hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white py-3 px-6 rounded-2xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center">
                        <i data-feather="save" class="w-4 h-4 mr-2"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="glass-effect fixed bottom-0 left-0 right-0 border-t border-white/20 z-40">
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
                <a href="customers.php" class="flex flex-col items-center space-y-1 py-2 px-3 rounded-2xl transition-all">
                    <div class="w-8 h-8 gradient-jewel rounded-xl flex items-center justify-center shadow-lg">
                        <i data-feather="users" class="w-4 h-4 text-white"></i>
                    </div>
                    <span class="text-xs text-indigo-600 font-bold">Customers</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Keep all existing JavaScript unchanged -->
    <script>
        // Initialize Feather Icons
        feather.replace();

        // Auto-hide notifications
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.transform = 'translateX(100%)';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            });
        }, 5000);

        // Tab functionality
        const tabs = document.querySelectorAll('.tab-button');
        const tabPanes = document.querySelectorAll('.tab-pane');

        function showTab(tabId) {
            tabPanes.forEach(pane => {
                if (pane.id === tabId) {
                    pane.classList.remove('hidden');
                } else {
                    pane.classList.add('hidden');
                }
            });
            tabs.forEach(tab => {
                if (tab.dataset.tab === tabId) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
            feather.replace(); // Re-initialize icons after DOM changes
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                showTab(tab.dataset.tab);
            });
        });

        // Payment Modal Functionality
        const openPaymentModalBtn = document.getElementById('openPaymentModalBtn');
        const paymentModal = document.getElementById('paymentModal');
        const cancelPaymentModal = document.getElementById('cancelPaymentModal');
        const cancelPaymentModal2 = document.getElementById('cancelPaymentModal2');
        const paymentForm = document.getElementById('paymentForm');
        const paymentTypeSelect = document.getElementById('payment_type');
        const paymentAmountInput = document.getElementById('payment_amount');
        const paymentDetailsSection = document.getElementById('paymentDetailsSection');
        const fifoAllocationSection = document.getElementById('fifoAllocationSection');
        const fifoItemsList = document.getElementById('fifoItemsList');
        const autoAllocateBtn = document.getElementById('autoAllocateBtn');
        let outstandingItems = [];
        if (openPaymentModalBtn && paymentModal) {
            openPaymentModalBtn.addEventListener('click', (e) => {
                e.preventDefault();
                paymentModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                feather.replace();
            });
            function hidePaymentModal() {
                paymentModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
                paymentForm.reset();
                paymentDetailsSection.classList.add('hidden');
                fifoAllocationSection.classList.add('hidden');
                outstandingItems = [];
            }
            if (cancelPaymentModal) {
                cancelPaymentModal.addEventListener('click', hidePaymentModal);
            }
            if (cancelPaymentModal2) {
                cancelPaymentModal2.addEventListener('click', hidePaymentModal);
            }
            paymentModal.addEventListener('click', (e) => {
                if (e.target === paymentModal) {
                    hidePaymentModal();
                }
            });
            // Show payment details only after type is selected
            if (paymentTypeSelect) {
                paymentTypeSelect.addEventListener('change', function() {
                    const selectedType = this.value;
                    const customerId = paymentModal.querySelector('input[name="customer_id"]').value;
                    if (selectedType) {
                        paymentDetailsSection.classList.remove('hidden');
                    } else {
                        paymentDetailsSection.classList.add('hidden');
                    }
                    fifoAllocationSection.classList.add('hidden');
                    fifoItemsList.innerHTML = '<p class="text-xs text-gray-500 text-center py-2">Loading...</p>';
                    paymentAmountInput.value = '';
                    outstandingItems = [];
                    if (selectedType && customerId) {
                        fetch('fetch_due_amount.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `customer_id=${customerId}&payment_type=${selectedType}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.outstanding_items && data.outstanding_items.length > 0) {
                                outstandingItems = data.outstanding_items.sort((a, b) => new Date(a.date) - new Date(b.date));
                                displayFIFOItems();
                                fifoAllocationSection.classList.remove('hidden');
                            } else if (data.due_amount !== undefined) {
                                paymentAmountInput.value = data.due_amount;
                                fifoAllocationSection.classList.add('hidden');
                            } else if (data.error) {
                                fifoItemsList.innerHTML = `<p class="text-xs text-red-500 text-center py-2">Error: ${data.error}</p>`;
                                fifoAllocationSection.classList.add('hidden');
                            } else {
                                fifoItemsList.innerHTML = '<p class="text-xs text-gray-500 text-center py-2">No outstanding items found.</p>';
                                fifoAllocationSection.classList.add('hidden');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            fifoItemsList.innerHTML = '<p class="text-xs text-red-500 text-center py-2">Error loading items.</p>';
                        });
                    }
                });
            }
            function displayFIFOItems() {
                fifoItemsList.innerHTML = '';
                const paymentType = paymentTypeSelect.value;

                if (outstandingItems.length === 0) {
                    fifoItemsList.innerHTML = '<p class="text-xs text-gray-500 text-center py-2">No outstanding items found for this type.</p>';
                    return;
                }
                
                outstandingItems.forEach((item, index) => {
                    const itemDiv = document.createElement('div');
                    itemDiv.classList.add('payment-item', 'flex', 'justify-between', 'items-center', 'p-3', 'border', 'border-gray-200', 'rounded-2xl', 'bg-white');

                    let itemHtml = '';
                    if (paymentType === 'Sale Due') {
                        itemHtml = `
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-800">Sale #${item.id}</p>
                                    <span class="text-xs text-gray-500">${item.date}</span>
                                </div>
                                <p class="text-xs text-gray-600">Due: ₹${parseFloat(item.due).toFixed(2)}</p>
                            </div>
                            <div class="ml-3 flex items-center space-x-2">
                                <input type="number" 
                                       step="0.01" 
                                       name="allocated_amount[${item.id}]" 
                                       class="allocation-input w-20 px-2 py-1 border border-gray-300 rounded text-sm text-right" 
                                       placeholder="0.00"
                                       data-due="${item.due}"
                                       data-index="${index}"
                                       max="${item.due}">
                            </div>
                        `;
                    } else if (paymentType === 'Scheme Installment') {
                        let statusColor = 'text-gray-500';
                        if (item.status === 'due') {
                            statusColor = 'text-red-600';
                        } else if (item.status === 'partial') {
                            statusColor = 'text-orange-600';
                        } else if (item.status === 'upcoming') {
                            statusColor = 'text-blue-600';
                        }

                        const paidForThisInstallment = parseFloat(item.paid_current_installment || 0);
                        const displayDueAmount = parseFloat(item.due || 0);
                        const currentInstallmentAmount = parseFloat(item.amount);

                        itemHtml = `
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-800">${item.plan_name} - Installment #${item.installment_id}</p>
                                    <span class="text-xs ${statusColor}">${item.installment_date} (${item.status})</span>
                                </div>
                                <p class="text-xs text-gray-600">Total: ₹${currentInstallmentAmount.toFixed(2)}</p>
                                ${displayDueAmount > 0 ? `<p class="text-xs text-red-600">Due: ₹${displayDueAmount.toFixed(2)}</p>` : ''}
                                ${paidForThisInstallment > 0 ? `<p class="text-xs text-green-600">Paid: ₹${paidForThisInstallment.toFixed(2)}</p>` : ''}
                            </div>
                            <div class="ml-3 flex items-center space-x-2">
                                <input type="number" 
                                    step="0.01" 
                                    name="allocated_amount[${item.customer_plan_id}-${item.installment_id}-${item.installment_date}]" 
                                    class="allocation-input w-20 px-2 py-1 border border-gray-300 rounded text-sm text-right" 
                                    placeholder="0.00"
                                    data-customer-plan-id="${item.customer_plan_id}"
                                    data-installment-id="${item.installment_id}"
                                    data-installment-date="${item.installment_date}"
                                    data-original-amount="${item.amount}"
                                    data-due="${displayDueAmount}"
                                    data-paid-current-installment="${paidForThisInstallment}"
                                    data-index="${index}"
                                    max="${currentInstallmentAmount}">
                            </div>
                        `;
                    } else if (paymentType === 'Loan EMI') {
                        let statusColor = 'text-gray-500';
                        if (item.status === 'due') {
                            statusColor = 'text-red-600';
                        } else if (item.status === 'partial') {
                            statusColor = 'text-orange-600';
                        } else if (item.status === 'upcoming') {
                            statusColor = 'text-blue-600';
                        }
                        const paidForThisInstallment = parseFloat(item.paid_current_installment || 0);
                        const displayDueAmount = parseFloat(item.due || 0);
                        const currentInstallmentAmount = parseFloat(item.amount);
                        itemHtml = `
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-800">Loan #${item.id} - EMI #${item.installment_number}</p>
                                    <span class="text-xs ${statusColor}">${item.installment_date} (${item.status})</span>
                                </div>
                                <p class="text-xs text-gray-600">Total: ₹${currentInstallmentAmount.toFixed(2)}</p>
                                ${displayDueAmount > 0 ? `<p class="text-xs text-red-600">Due: ₹${displayDueAmount.toFixed(2)}</p>` : ''}
                                ${paidForThisInstallment > 0 ? `<p class="text-xs text-green-600">Paid: ₹${paidForThisInstallment.toFixed(2)}</p>` : ''}
                            </div>
                            <div class="ml-3 flex items-center space-x-2">
                                <input type="number" 
                                    step="0.01" 
                                    name="allocated_amount[${item.id}-${item.installment_number}-${item.installment_date}]" 
                                    class="allocation-input w-20 px-2 py-1 border border-gray-300 rounded text-sm text-right" 
                                    placeholder="0.00"
                                    data-loan-id="${item.id}"
                                    data-installment-number="${item.installment_number}"
                                    data-installment-date="${item.installment_date}"
                                    data-original-amount="${item.amount}"
                                    data-due="${displayDueAmount}"
                                    data-paid-current-installment="${paidForThisInstallment}"
                                    data-index="${index}"
                                    max="${currentInstallmentAmount}">
                            </div>
                        `;
                    } else if (paymentType === 'Loan Principal') {
                        itemHtml = `
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-800">Loan #${item.id}</p>
                                    <span class="text-xs text-gray-500">${item.date}</span>
                                </div>
                                <p class="text-xs text-gray-600">Principal: ₹${parseFloat(item.principal_amount).toFixed(2)}</p>
                            </div>
                            <div class="ml-3 flex items-center space-x-2">
                                <input type="number" 
                                    step="0.01" 
                                    name="allocated_amount[${item.id}]" 
                                    class="allocation-input w-20 px-2 py-1 border border-gray-300 rounded text-sm text-right" 
                                    placeholder="0.00"
                                    data-due="${item.principal_amount}"
                                    data-index="${index}"
                                    max="${item.principal_amount}">
                            </div>
                        `;
                    }
                    
                    itemDiv.innerHTML = itemHtml;
                    fifoItemsList.appendChild(itemDiv);
                });

                // Add event listeners to allocation inputs
                document.querySelectorAll('.allocation-input').forEach((input, index) => {
                    input.addEventListener('input', updateTotalAndValidate);
                    input.addEventListener('blur', updateTotalAndValidate);
                });
            }

            function updateTotalAndValidate() {
                let total = 0;
                const paymentType = paymentTypeSelect.value;
                
                document.querySelectorAll('.allocation-input').forEach((input) => {
                    const value = parseFloat(input.value) || 0;
                    let limit = 0;

                    if (paymentType === 'Sale Due') {
                        limit = parseFloat(input.dataset.due) || 0;
                    } else if (paymentType === 'Scheme Installment') {
                        limit = parseFloat(input.dataset.originalAmount) || 0;
                    } else if (paymentType === 'Loan EMI') {
                        limit = parseFloat(input.dataset.originalAmount) || 0;
                    } else if (paymentType === 'Loan Principal') {
                        limit = parseFloat(input.dataset.due) || 0;
                    }
                    
                    if (value > limit) {
                        input.value = limit;
                    }
                    if (value < 0) {
                        input.value = 0;
                    }
                    
                    total += parseFloat(input.value) || 0;
                });
                
                paymentAmountInput.value = total.toFixed(2);
            }

            // Auto-allocate using FIFO method
            if (autoAllocateBtn) {
                autoAllocateBtn.addEventListener('click', function() {
                    const totalAmount = parseFloat(paymentAmountInput.value) || 0;
                    
                    if (totalAmount <= 0) {
                        alert('Please enter a payment amount first');
                        return;
                    }

                    let remainingAmount = totalAmount;
                    const allocationInputs = document.querySelectorAll('.allocation-input');
                    const paymentType = paymentTypeSelect.value;
                    
                    // Clear all inputs first
                    allocationInputs.forEach((input) => {
                        input.value = '';
                        input.closest('.payment-item').classList.remove('selected');
                    });
                    
                    // Allocate using FIFO (oldest first)
                    allocationInputs.forEach((input) => {
                        if (remainingAmount <= 0) return;
                        
                        let dueForThisItem = 0;

                        if (paymentType === 'Sale Due') {
                            dueForThisItem = parseFloat(input.dataset.due) || 0;
                        } else if (paymentType === 'Scheme Installment') {
                            dueForThisItem = parseFloat(input.dataset.due) || 0;
                        } else if (paymentType === 'Loan EMI') {
                            dueForThisItem = parseFloat(input.dataset.due) || 0;
                        } else if (paymentType === 'Loan Principal') {
                            dueForThisItem = parseFloat(input.dataset.due) || 0;
                        }

                        const allocateAmount = Math.min(remainingAmount, dueForThisItem);
                        
                        input.value = allocateAmount;
                        remainingAmount -= allocateAmount;
                        
                        // Highlight allocated items
                        const paymentItem = input.closest('.payment-item');
                        if (parseFloat(input.value) > 0) {
                            paymentItem.classList.add('selected');
                        }
                    });
                    
                    paymentAmountInput.value = (parseFloat(paymentAmountInput.value)).toFixed(2);
                });
            }

            // Handle amount input change
            if (paymentAmountInput) {
                paymentAmountInput.addEventListener('input', function() {
                    if (this.value && parseFloat(this.value) > 0) {
                        setTimeout(() => {
                            if (autoAllocateBtn) {
                                autoAllocateBtn.click();
                            }
                        }, 100);
                    } else {
                        document.querySelectorAll('.allocation-input').forEach(input => {
                            input.value = '';
                            input.closest('.payment-item').classList.remove('selected');
                        });
                    }
                });
            }

            // Form submission
            if (paymentForm) {
                paymentForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const submitButton = this.querySelector('button[type="submit"]');
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i data-feather="loader" class="w-4 h-4 mr-2 animate-spin"></i>Processing...';
                    feather.replace();
                    
                    try {
                        const formData = new FormData(this);
                        
                        // Validate payment amount
                        const paymentAmount = parseFloat(formData.get('amount'));
                        if (isNaN(paymentAmount) || paymentAmount <= 0) {
                            throw new Error('Please enter a valid payment amount');
                        }
                        
                        // Validate payment type
                        const paymentType = formData.get('type');
                        if (!paymentType) {
                            throw new Error('Please select a payment type');
                        }
                        
                        // Validate payment method
                        const paymentMethod = formData.get('method');
                        if (!paymentMethod) {
                            throw new Error('Please select a payment method');
                        }
                        
                        const response = await fetch('process_payment.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.status === 401) {
                            window.location.href = 'login.php?error=Session+expired.+Please+login+again.';
                            return;
                        }
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Show success notification
                            const notification = document.createElement('div');
                            notification.className = 'notification notification-success';
                            notification.innerHTML = `
                                <div class="flex items-center">
                                    <i data-feather="check-circle" class="w-5 h-5 mr-3"></i>
                                    <span class="font-medium">${result.message}</span>
                                </div>
                            `;
                            document.body.appendChild(notification);
                            feather.replace();
                            
                            // Remove notification after 5 seconds
                            setTimeout(() => {
                                notification.style.transform = 'translateX(100%)';
                                notification.style.opacity = '0';
                                setTimeout(() => notification.remove(), 300);
                            }, 5000);
                            
                            // Close modal and refresh content
                            hidePaymentModal();
                            location.reload();
                        } else {
                            throw new Error(result.message || 'Payment processing failed');
                        }
                    } catch (error) {
                        console.error('Payment error:', error);
                        alert(error.message || 'An error occurred while processing the payment. Please try again.');
                    } finally {
                        submitButton.disabled = false;
                        submitButton.innerHTML = '<i data-feather="check-circle" class="w-4 h-4 mr-2"></i>Accept Payment';
                        feather.replace();
                    }
                });
            }
        }

        // Edit Customer Modal Functionality
        const editCustomerBtn = document.getElementById('editCustomerBtn');
        const editCustomerModal = document.getElementById('editCustomerModal');
        const cancelEditCustomer = document.getElementById('cancelEditCustomer');
        const cancelEditCustomer2 = document.getElementById('cancelEditCustomer2');
        const editCustomerForm = document.getElementById('editCustomerForm');

        if (editCustomerBtn && editCustomerModal) {
            editCustomerBtn.addEventListener('click', (e) => {
                e.preventDefault();
                editCustomerModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                feather.replace();
            });

            function hideEditModal() {
                editCustomerModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }

            if (cancelEditCustomer) {
                cancelEditCustomer.addEventListener('click', hideEditModal);
            }

            if (cancelEditCustomer2) {
                cancelEditCustomer2.addEventListener('click', hideEditModal);
            }

            editCustomerModal.addEventListener('click', (e) => {
                if (e.target === editCustomerModal) {
                    hideEditModal();
                }
            });

            // Form submission
            editCustomerForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i data-feather="loader" class="w-4 h-4 mr-2 animate-spin"></i>Saving...';
                    feather.replace();
                }
            });
        }

        // Initialize default tab
        showTab('transactions');
    </script>
</body>
</html>

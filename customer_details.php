<?php
session_start();
require 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$firm_id = $_SESSION['firmID'];

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    header("Location: customer.php?error=Invalid+customer+ID");
    exit();
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user info for header
$user_id = $_SESSION['id'];
$userQuery = "SELECT Name, Role, image_path FROM Firm_Users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userInfo = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

// Fetch customer details
$customer = null;
$customerQuery = "SELECT id, FirstName, LastName, Address, City, State, PhoneNumber, Email, DateOfBirth, Gender, PANNumber, AadhaarNumber, CustomerImage FROM customer WHERE id = ? AND FirmID = ?";
$customerStmt = $conn->prepare($customerQuery);
$customerStmt->bind_param("ii", $customer_id, $firm_id);
$customerStmt->execute();
$customerResult = $customerStmt->get_result();
if ($customerResult->num_rows > 0) {
    $customer = $customerResult->fetch_assoc();
} else {
    header("Location: customer.php?error=Customer+not+found");
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
$activeSchemesQuery = "SELECT cgp.id, gsp.plan_name, cgp.enrollment_date, cgp.maturity_date FROM customer_gold_plans cgp JOIN gold_saving_plans gsp ON cgp.plan_id = gsp.id WHERE cgp.customer_id = ? AND cgp.current_status = 'active' ORDER BY cgp.enrollment_date DESC";
$activeSchemesStmt = $conn->prepare($activeSchemesQuery);
$activeSchemesStmt->bind_param("i", $customer_id);
$activeSchemesStmt->execute();
$activeSchemesResult = $activeSchemesStmt->get_result();
while ($row = $activeSchemesResult->fetch_assoc()) {
    $active_schemes[] = $row;
}
$activeSchemesStmt->close();

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
    <title>Customer Details - <?php echo htmlspecialchars($customer['FirstName'] . ' ' . $customer['LastName']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .header-glass {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .bottom-nav {
             background: rgba(255, 255, 255, 0.9);
             backdrop-filter: blur(20px);
             border-top: 1px solid rgba(0, 0, 0, 0.06);
        }
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-3px); } 
        }
         .hide-scrollbar::-webkit-scrollbar { display: none; }
         .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
         .tab-button.active {
            background: linear-gradient(135deg, var(--tw-gradient-stops));
            color: white;
            font-weight: 600;
            box-shadow: var(--tw-shadow-md);
         }
         .modal-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.4);
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
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease-out;
        }
        .alert-success {
            background-color: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
        }
        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-amber-50 via-slate-50 to-indigo-50 pb-24">
    
    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success" id="successAlert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error" id="errorAlert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Enhanced Header -->
    <header class="header-glass sticky top-0 z-50 shadow-md">
        <div class="px-3 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div>
                        <h1 class="text-sm font-bold text-gray-800">JewelEntry</h1>
                        <p class="text-xs text-gray-600 font-medium">Powered by JewelEntry</p>
                    </div>
                </div>
                 <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p id="headerUserName" class="text-sm font-bold text-gray-800"><?php echo $userInfo['Name'] ?? ''; ?></p>
                        <p id="headerUserRole" class="text-xs text-purple-600 font-medium"><?php echo $userInfo['Role'] ?? ''; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="px-3 pt-3 max-w-2xl mx-auto">
        <!-- Customer Header -->
        <div class="bg-white/90 backdrop-blur-sm rounded-xl shadow-md border border-gray-100 p-3 mb-3 relative">
             <!-- Edit Icon -->
            <button id="editCustomerBtn" class="absolute top-2 right-2 z-10 bg-white/80 hover:bg-indigo-100 text-indigo-500 rounded-full p-1.5 shadow transition" title="Edit Customer">
                <i class="fas fa-pen text-xs"></i>
            </button>
            <div class="flex items-start space-x-3">
                 <!-- Avatar -->
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center text-indigo-600 font-bold text-lg shadow flex-shrink-0">
                     <?php if (!empty($customer['CustomerImage']) && file_exists($customer['CustomerImage'])): ?>
                        <img src="<?= htmlspecialchars($customer['CustomerImage']); ?>" alt="Customer" class="w-full h-full object-cover rounded-full">
                    <?php else: ?>
                        <?= strtoupper(substr($customer['FirstName'], 0, 1) . substr($customer['LastName'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                         <div class="flex-1 min-w-0 mr-2">
                            <h1 class="text-lg font-bold text-gray-800 mb-0.5"><?php echo htmlspecialchars($customer['FirstName'] . ' ' . $customer['LastName']); ?></h1>
                            <p class="text-xs text-gray-600 truncate">
                                <?php if ($customer['Address']): ?><i class="fas fa-map-marker-alt text-gray-400 mr-1"></i><?= htmlspecialchars($customer['Address']) ?><?php endif; ?>
                            </p>
                             <div class="flex flex-wrap items-center text-xs text-gray-700 mt-1 gap-x-2 gap-y-0.5">
                                <?php if ($customer['PhoneNumber']): ?>
                                    <a href="tel:<?= htmlspecialchars($customer['PhoneNumber']); ?>" class="flex items-center hover:text-blue-600 transition"><i class="fas fa-phone mr-1 text-gray-400 text-[10px]"></i><?= htmlspecialchars($customer['PhoneNumber']); ?></a>
                                <?php endif; ?>
                                <?php if ($customer['Email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($customer['Email']); ?>" class="flex items-center hover:text-blue-600 transition"><i class="fas fa-envelope mr-1 text-gray-400 text-[10px]"></i><?= htmlspecialchars($customer['Email']); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($customer['PhoneNumber']): ?>
                            <a href="https://wa.me/91<?= $customer['PhoneNumber'] ?>?text=<?= urlencode('Hello ' . $customer['FirstName'] . ', Greetings from ' . ($_SESSION['FirmName'] ?? 'JewelEntry') . '!') ?>" target="_blank" class="ml-auto flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-green-600 shadow hover:scale-110 transition-transform" title="Send WhatsApp Greeting">
                                <i class="fab fa-whatsapp text-white text-base"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Financial Badges -->
                    <div class="flex flex-wrap gap-1 mt-2">
                         <?php if ($total_due_sales > 0): ?>
                             <span class="bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full text-xs font-semibold flex items-center shadow-sm">
                                 <i class="fas fa-dollar-sign mr-0.5 text-[9px]"></i>Due: ₹<?= number_format($total_due_sales, 0) ?>
                             </span>
                         <?php endif; ?>
                         <?php if ($total_outstanding_loans > 0): ?>
                             <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full text-xs font-semibold flex items-center shadow-sm">
                                 <i class="fas fa-university mr-0.5 text-[9px]"></i>Loan: ₹<?= number_format($total_outstanding_loans, 0) ?>
                             </span>
                         <?php endif; ?>
                         <?php $monthlyDueTotal = $total_emi_due + $total_gold_due; ?>
                         <?php if ($monthlyDueTotal > 0): ?>
                             <span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full text-xs font-semibold flex items-center shadow-sm">
                                 <i class="fas fa-calendar-alt mr-0.5 text-[9px]"></i>Monthly: ₹<?= number_format($monthlyDueTotal, 0) ?>
                             </span>
                         <?php endif; ?>
                     </div>
                </div>
            </div>
        </div>

        <!-- Section Tabs -->
        <div class="mb-4">
            <div id="detailTabs" class="flex space-x-1 bg-white/70 rounded-lg p-1 shadow-sm overflow-x-auto hide-scrollbar">
                <button class="tab-button flex-shrink-0 px-2 py-1 text-sm rounded-md transition-colors duration-200 bg-indigo-500 text-white shadow-md" data-tab="transactions">Transactions</button>
                <button class="tab-button flex-shrink-0 px-2 py-1 text-sm rounded-md transition-colors duration-200 text-gray-700 hover:bg-gray-200" data-tab="loans">Loans</button>
                <button class="tab-button flex-shrink-0 px-2 py-1 text-sm rounded-md transition-colors duration-200 text-gray-700 hover:bg-gray-200" data-tab="schemes">Schemes</button>
                 <!-- Accept Payment Button -->
                 <button id="openPaymentModalBtn" class="flex-shrink-0 ml-auto bg-gradient-to-br from-green-500 to-emerald-600 text-white px-2 py-1 text-sm rounded-md shadow-md hover:opacity-90 transition flex items-center">
                     <i class="fas fa-receipt mr-1 text-xs"></i> Payment
                 </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div id="tabContent">
            <!-- Recent Transactions -->
            <div id="transactions" class="tab-pane bg-white/90 backdrop-blur-sm rounded-xl shadow-md border border-gray-100 p-4 mb-4">
                <h2 class="text-lg font-semibold text-gray-800 mb-3 flex items-center"><i class="fas fa-history mr-2 text-indigo-500"></i>Recent Transactions</h2>
                <?php if (count($recent_sales) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_sales as $sale): ?>
                            <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">Sale #<?= $sale['id'] ?></p>
                                    <p class="text-xs text-gray-500"><?= date('d M, Y', strtotime($sale['sale_date'])); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-800">₹<?= number_format($sale['grand_total'], 0) ?></p>
                                    <?php if ($sale['due_amount'] > 0): ?>
                                        <p class="text-xs text-red-600">Due: ₹<?= number_format($sale['due_amount'], 0) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-500">No recent transactions found.</p>
                <?php endif; ?>
            </div>

            <!-- Active Loans -->
            <div id="loans" class="tab-pane bg-white/90 backdrop-blur-sm rounded-xl shadow-md border border-gray-100 p-4 mb-4 hidden">
                 <h2 class="text-lg font-semibold text-gray-800 mb-3 flex items-center"><i class="fas fa-university mr-2 text-emerald-500"></i>Active Loans</h2>
                 <?php if (count($active_loans) > 0): ?>
                     <div class="space-y-3">
                         <?php foreach ($active_loans as $loan): ?>
                             <div class="border-b border-gray-100 pb-2">
                                 <div class="flex justify-between items-center mb-1">
                                     <p class="text-sm font-medium text-gray-800">Loan #<?= $loan['id'] ?></p>
                                     <p class="text-sm font-semibold text-emerald-600">₹<?= number_format($loan['outstanding_amount'], 0) ?> Out.</p>
                                 </div>
                                 <div class="flex justify-between text-xs text-gray-500">
                                     <span>Amt: ₹<?= number_format($loan['principal_amount'], 0) ?></span>
                                     <span>Rate: <?= $loan['interest_rate'] ?>%</span>
                                     <span>Tenure: <?= $loan['loan_term_months'] ?> mos</span>
                                 </div>
                             </div>
                         <?php endforeach; ?>
                     </div>
                 <?php else: ?>
                     <p class="text-sm text-gray-500">No active loans found.</p>
                 <?php endif; ?>
             </div>

            <!-- Active Schemes -->
            <div id="schemes" class="tab-pane bg-white/90 backdrop-blur-sm rounded-xl shadow-md border border-gray-100 p-4 mb-4 hidden">
                 <h2 class="text-lg font-semibold text-gray-800 mb-3 flex items-center"><i class="fas fa-coins mr-2 text-amber-500"></i>Active Schemes</h2>
                 <?php if (count($active_schemes) > 0): ?>
                     <div class="space-y-3">
                         <?php foreach ($active_schemes as $scheme): ?>
                             <div class="border-b border-gray-100 pb-2">
                                 <div class="flex justify-between items-center mb-1">
                                     <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($scheme['plan_name']) ?></p>
                                     <p class="text-xs text-gray-500">Started: <?= date('d M, Y', strtotime($scheme['enrollment_date'])); ?></p>
                                 </div>
                             </div>
                         <?php endforeach; ?>
                     </div>
                 <?php else: ?>
                     <p class="text-sm text-gray-500">No active schemes found.</p>
                 <?php endif; ?>
             </div>
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
                <a href="add.php" data-nav-id="inventory" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-warehouse text-blue-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-blue-400 font-medium">Inventory</span>
                </a>
                <button data-nav-id="alerts_nav" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Alerts</span>
                </button>
                <a href="customer.php" data-nav-id="customers" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300 active">
                    <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center shadow-lg">
                        <i class="fas fa-address-book text-white text-sm"></i>
                    </div>
                    <span class="text-xs text-purple-500 font-bold">Customers</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Accept Payment Modal -->
    <div id="paymentModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto max-h-[90vh] overflow-y-auto border border-indigo-100">
            <form id="paymentForm" method="POST" action="process_payment.php">
                <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-purple-50 via-indigo-50 to-white rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-receipt text-purple-500 mr-2"></i>
                            Accept Payment
                        </h3>
                        <button type="button" id="cancelPaymentModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                <!-- Modal Body -->
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="payment_amount" class="block text-sm font-medium text-gray-700 mb-1">Amount Received</label>
                            <input type="number" step="0.01" name="amount" id="payment_amount" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        </div>
                         <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select name="method" id="payment_method" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-white">
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="UPI">UPI</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                         <label for="payment_type" class="block text-sm font-medium text-gray-700 mb-1">Payment For</label>
                         <select name="type" id="payment_type" required
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-white">
                             <option value="">Select Payment Type</option>
                             <option value="Sale Due">Sale Due</option>
                             <option value="Loan EMI">Loan EMI</option>
                             <option value="Loan Principal">Loan Principal</option>
                             <option value="Scheme Installment">Scheme Installment</option>
                             <option value="Other">Other</option>
                         </select>
                     </div>
                     
                     <!-- FIFO Payment Allocation Section -->
                    <div id="fifoAllocationSection" class="mt-4 hidden">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-medium text-gray-700">Payment Allocation (FIFO)</h4>
                            <button type="button" id="autoAllocateBtn" class="text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded hover:bg-blue-200 transition">
                                Auto Allocate
                            </button>
                        </div>
                        <div id="fifoItemsList" class="space-y-2 max-h-48 overflow-y-auto hide-scrollbar border border-gray-200 rounded-lg p-3 bg-gray-50">
                            <p class="text-sm text-gray-500 text-center py-4">Select payment type to load items.</p>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Payments are allocated to oldest dues first (FIFO method)
                        </div>
                    </div>
                     
                     <div class="mt-4">
                         <label for="payment_notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                         <textarea name="notes" id="payment_notes" rows="2"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                     </div>
                </div>
                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end space-x-3">
                    <button type="button" id="cancelPaymentModal2"
                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="submitPaymentBtn"
                            class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-2 px-6 rounded-lg font-semibold text-sm shadow hover:opacity-90 transition flex items-center justify-center gap-2">
                         <i class="fas fa-check-circle"></i> Accept Payment
                     </button>
                 </div>
            </form>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="editCustomerModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto max-h-[90vh] overflow-y-auto border border-indigo-100">
            <form id="editCustomerForm" method="POST" action="update_customer.php" enctype="multipart/form-data">
                <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-purple-50 via-indigo-50 to-white rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-user-edit text-purple-500 mr-2"></i>
                            Edit Customer
                        </h3>
                        <button type="button" id="cancelEditCustomer" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                <!-- Modal Body -->
                <div class="px-6 py-4">
                    <div class="space-y-4">
                        <!-- First Name -->
                        <div>
                            <label for="edit_first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   value="<?= htmlspecialchars($customer['FirstName']) ?>">
                        </div>
                        <!-- Last Name -->
                        <div>
                            <label for="edit_last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   value="<?= htmlspecialchars($customer['LastName']) ?>">
                        </div>
                        <!-- Phone Number -->
                        <div>
                            <label for="edit_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" name="phone" id="edit_phone" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   value="<?= htmlspecialchars($customer['PhoneNumber']) ?>">
                        </div>
                        <!-- Email -->
                        <div>
                            <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" id="edit_email"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   value="<?= htmlspecialchars($customer['Email']) ?>">
                        </div>
                        <!-- Address -->
                        <div>
                            <label for="edit_address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea name="address" id="edit_address" rows="2"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"><?= htmlspecialchars($customer['Address']) ?></textarea>
                        </div>
                        <!-- City -->
                        <div>
                            <label for="edit_city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <input type="text" name="city" id="edit_city"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   value="<?= htmlspecialchars($customer['City']) ?>">
                        </div>
                        <!-- State -->
                        <div>
                            <label for="edit_state" class="block text-sm font-medium text-gray-700 mb-1">State</label>
                            <input type="text" name="state" id="edit_state"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   value="<?= htmlspecialchars($customer['State']) ?>">
                        </div>
                        <!-- PAN Number -->
                        <div>
                            <label for="edit_pan" class="block text-sm font-medium text-gray-700 mb-1">PAN Number</label>
                            <input type="text" name="pan" id="edit_pan"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   value="<?= htmlspecialchars($customer['PANNumber']) ?>">
                        </div>
                        <!-- Aadhaar Number -->
                        <div>
                            <label for="edit_aadhaar" class="block text-sm font-medium text-gray-700 mb-1">Aadhaar Number</label>
                            <input type="text" name="aadhaar" id="edit_aadhaar"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   value="<?= htmlspecialchars($customer['AadhaarNumber']) ?>">
                        </div>
                        <!-- Customer Image -->
                        <div>
                            <label for="edit_customer_image" class="block text-sm font-medium text-gray-700 mb-1">Profile Picture</label>
                            <input type="file" name="customer_image" id="edit_customer_image" accept="image/*"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <?php if (!empty($customer['CustomerImage'])): ?>
                                <div class="mt-2">
                                    <img src="<?= htmlspecialchars($customer['CustomerImage']) ?>" alt="Current Profile Picture" class="w-20 h-20 rounded-full object-cover">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end space-x-3">
                    <button type="button" id="cancelEditCustomer2"
                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-2 px-6 rounded-lg font-semibold text-sm shadow hover:opacity-90 transition flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Enable detailed console logging
    const DEBUG = true;
    
    function debugLog(message, data = null) {
        if (DEBUG) {
            console.log(`[PAYMENT DEBUG] ${message}`, data || '');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        debugLog('DOM Content Loaded - Initializing payment system');

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Tab functionality
        const tabs = document.querySelectorAll('.tab-button');
        const tabPanes = document.querySelectorAll('.tab-pane');

        function showTab(tabId) {
            debugLog(`Switching to tab: ${tabId}`);
            tabPanes.forEach(pane => {
                if (pane.id === tabId) {
                    pane.classList.remove('hidden');
                } else {
                    pane.classList.add('hidden');
                }
            });
            tabs.forEach(tab => {
                if (tab.dataset.tab === tabId) {
                    tab.classList.add('bg-indigo-500', 'text-white', 'shadow-md');
                    tab.classList.remove('text-gray-700', 'hover:bg-gray-200');
                } else {
                    tab.classList.remove('bg-indigo-500', 'text-white', 'shadow-md');
                    tab.classList.add('text-gray-700', 'hover:bg-gray-200');
                }
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                showTab(tab.dataset.tab);
            });
        });

        showTab('transactions');

        // Payment Modal Functionality
        const openPaymentModalBtn = document.getElementById('openPaymentModalBtn');
        const paymentModal = document.getElementById('paymentModal');
        const cancelPaymentModal = document.getElementById('cancelPaymentModal');
        const cancelPaymentModal2 = document.getElementById('cancelPaymentModal2');
        const paymentForm = document.getElementById('paymentForm');
        const paymentTypeSelect = document.getElementById('payment_type');
        const paymentAmountInput = document.getElementById('payment_amount');
        const fifoAllocationSection = document.getElementById('fifoAllocationSection');
        const fifoItemsList = document.getElementById('fifoItemsList');
        const autoAllocateBtn = document.getElementById('autoAllocateBtn');

        let outstandingItems = [];
        let totalPaymentAmount = 0;

        if (openPaymentModalBtn && paymentModal) {
            debugLog('Payment modal elements found, setting up event listeners');
            
            openPaymentModalBtn.addEventListener('click', (e) => {
                debugLog('Payment modal open button clicked');
                e.preventDefault();
                e.stopPropagation();
                paymentModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                debugLog('Payment modal opened successfully');
            });

            function hidePaymentModal() {
                debugLog('Hiding payment modal');
                paymentModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
                paymentForm.reset();
                fifoAllocationSection.classList.add('hidden');
                outstandingItems = [];
                totalPaymentAmount = 0;
                debugLog('Payment modal hidden and reset');
            }

            if (cancelPaymentModal) {
                cancelPaymentModal.addEventListener('click', (e) => {
                    debugLog('Cancel button 1 clicked');
                    e.preventDefault();
                    hidePaymentModal();
                });
            }
            
            if (cancelPaymentModal2) {
                cancelPaymentModal2.addEventListener('click', (e) => {
                    debugLog('Cancel button 2 clicked');
                    e.preventDefault();
                    hidePaymentModal();
                });
            }

            // Prevent modal from closing when clicking inside the modal content
            paymentModal.addEventListener('click', (e) => {
                if (e.target === paymentModal) {
                    debugLog('Modal backdrop clicked, closing modal');
                    hidePaymentModal();
                }
            });

            // Prevent form submission from closing modal prematurely
            paymentForm.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            // Handle payment type change
            if (paymentTypeSelect) {
                debugLog('Setting up payment type change listener');
                paymentTypeSelect.addEventListener('change', function() {
                    const selectedType = this.value;
                    const customerId = paymentModal.querySelector('input[name="customer_id"]').value;
                    
                    debugLog(`Payment type changed to: ${selectedType}`, {
                        customerId: customerId,
                        selectedType: selectedType
                    });

                    fifoAllocationSection.classList.add('hidden');
                    fifoItemsList.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">Loading...</p>';
                    paymentAmountInput.value = '';
                    outstandingItems = [];

                    if (selectedType && customerId) {
                        debugLog('Fetching due amounts for payment type');
                        
                        const requestData = `customer_id=${customerId}&payment_type=${selectedType}`;
                        debugLog('Request data:', requestData);
                        
                        fetch('fetch_due_amount.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: requestData
                        })
                        .then(response => {
                            debugLog('Fetch response received', {
                                status: response.status,
                                statusText: response.statusText,
                                ok: response.ok
                            });
                            
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            
                            return response.json();
                        })
                        .then(data => {
                            debugLog('Fetch response data:', data);
                            
                            if (data.outstanding_items && data.outstanding_items.length > 0) {
                                debugLog(`Found ${data.outstanding_items.length} outstanding items`);
                                outstandingItems = data.outstanding_items.sort((a, b) => new Date(a.date) - new Date(b.date));
                                debugLog('Sorted outstanding items:', outstandingItems);
                                displayFIFOItems();
                                fifoAllocationSection.classList.remove('hidden');
                            } else if (data.due_amount !== undefined) {
                                debugLog(`Setting due amount: ${data.due_amount}`);
                                paymentAmountInput.value = data.due_amount;
                                fifoAllocationSection.classList.add('hidden');
                            } else if (data.error) {
                                debugLog('Error in response:', data.error);
                                fifoItemsList.innerHTML = `<p class="text-sm text-red-500 text-center py-4">Error: ${data.error}</p>`;
                                fifoAllocationSection.classList.add('hidden');
                            } else {
                                debugLog('No outstanding items found');
                                fifoItemsList.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No outstanding items found.</p>';
                                fifoAllocationSection.classList.add('hidden');
                            }
                        })
                        .catch(error => {
                            debugLog('Fetch error:', error);
                            console.error('Error:', error);
                            fifoItemsList.innerHTML = '<p class="text-sm text-red-500 text-center py-4">Error loading items.</p>';
                        });
                    } else {
                        debugLog('No payment type or customer ID selected');
                    }
                });
            }

            function displayFIFOItems() {
                debugLog('Displaying FIFO items', outstandingItems);
                fifoItemsList.innerHTML = '';
                
                outstandingItems.forEach((item, index) => {
                    debugLog(`Creating item ${index}:`, item);
                    
                    const itemDiv = document.createElement('div');
                    itemDiv.classList.add('payment-item', 'flex', 'justify-between', 'items-center', 'p-2', 'border', 'border-gray-200', 'rounded-lg', 'bg-white');
                    itemDiv.innerHTML = `
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
                    fifoItemsList.appendChild(itemDiv);
                });

                // Add event listeners to allocation inputs
                document.querySelectorAll('.allocation-input').forEach((input, index) => {
                    debugLog(`Adding event listeners to allocation input ${index}`);
                    
                    input.addEventListener('input', (e) => {
                        debugLog(`Allocation input ${index} changed:`, e.target.value);
                        updateTotalAndValidate();
                    });
                    
                    input.addEventListener('blur', (e) => {
                        debugLog(`Allocation input ${index} blur:`, e.target.value);
                        updateTotalAndValidate();
                    });
                });
                
                debugLog('FIFO items displayed successfully');
            }

            function updateTotalAndValidate() {
                debugLog('Updating total and validating allocations');
                let total = 0;
                
                document.querySelectorAll('.allocation-input').forEach((input, index) => {
                    const value = parseFloat(input.value) || 0;
                    const due = parseFloat(input.dataset.due) || 0;
                    
                    debugLog(`Input ${index}: value=${value}, due=${due}`);
                    
                    // Validate individual input
                    if (value > due) {
                        debugLog(`Input ${index}: value exceeds due, setting to due amount`);
                        input.value = due;
                    }
                    if (value < 0) {
                        debugLog(`Input ${index}: negative value, setting to 0`);
                        input.value = 0;
                    }
                    
                    total += parseFloat(input.value) || 0;
                });
                
                debugLog(`Total calculated: ${total}`);
                paymentAmountInput.value = total.toFixed(2);
                totalPaymentAmount = total;
                debugLog('Total updated in payment amount input');
            }

            // Auto-allocate using FIFO method
            if (autoAllocateBtn) {
                debugLog('Setting up auto-allocate button');
                autoAllocateBtn.addEventListener('click', function() {
                    debugLog('Auto-allocate button clicked');
                    
                    const totalAmount = parseFloat(paymentAmountInput.value) || 0;
                    debugLog(`Total amount for allocation: ${totalAmount}`);
                    
                    if (totalAmount <= 0) {
                        alert('Please enter a payment amount first');
                        return;
                    }

                    let remainingAmount = totalAmount;
                    const allocationInputs = document.querySelectorAll('.allocation-input');
                    
                    debugLog(`Found ${allocationInputs.length} allocation inputs`);
                    
                    // Clear all inputs first
                    allocationInputs.forEach((input, index) => {
                        debugLog(`Clearing input ${index}`);
                        input.value = '';
                    });
                    
                    // Allocate using FIFO (oldest first)
                    allocationInputs.forEach((input, index) => {
                        if (remainingAmount <= 0) return;
                        
                        const due = parseFloat(input.dataset.due) || 0;
                        const allocateAmount = Math.min(remainingAmount, due);
                        
                        debugLog(`Allocating to input ${index}: ${allocateAmount} (due: ${due}, remaining: ${remainingAmount})`);
                        
                        input.value = allocateAmount.toFixed(2);
                        remainingAmount -= allocateAmount;
                        
                        // Highlight allocated items
                        const paymentItem = input.closest('.payment-item');
                        if (allocateAmount > 0) {
                            paymentItem.classList.add('selected');
                            debugLog(`Input ${index} selected (allocated: ${allocateAmount})`);
                        } else {
                            paymentItem.classList.remove('selected');
                            debugLog(`Input ${index} not selected (no allocation)`);
                        }
                    });
                    
                    debugLog(`Remaining amount after allocation: ${remainingAmount}`);
                    updateTotalAndValidate();
                    debugLog('Auto-allocation completed');
                });
            }

            // Handle amount input change to trigger auto-allocation
            if (paymentAmountInput) {
                debugLog('Setting up payment amount input listener');
                paymentAmountInput.addEventListener('input', function() {
                    debugLog(`Payment amount changed: ${this.value}`);
                    
                    if (outstandingItems.length > 0 && this.value) {
                        debugLog('Triggering auto-allocation after amount change');
                        // Auto-trigger FIFO allocation when amount changes
                        setTimeout(() => {
                            if (autoAllocateBtn) {
                                autoAllocateBtn.click();
                            }
                        }, 500);
                    }
                });
            }

            // Form submission
            if (paymentForm) {
                debugLog('Setting up form submission handler');
                paymentForm.addEventListener('submit', function(e) {
                    debugLog('Form submission started');
                    
                    const amount = parseFloat(paymentAmountInput.value);
                    const paymentType = paymentTypeSelect.value;
                    const paymentMethod = document.getElementById('payment_method').value;

                    debugLog('Form validation data:', {
                        amount: amount,
                        paymentType: paymentType,
                        paymentMethod: paymentMethod
                    });

                    if (!amount || amount <= 0) {
                        e.preventDefault();
                        debugLog('Form validation failed: Invalid amount');
                        alert('Please enter a valid payment amount');
                        return;
                    }

                    if (!paymentType) {
                        e.preventDefault();
                        debugLog('Form validation failed: No payment type');
                        alert('Please select a payment type');
                        return;
                    }

                    if (!paymentMethod) {
                        e.preventDefault();
                        debugLog('Form validation failed: No payment method');
                        alert('Please select a payment method');
                        return;
                    }

                    // Validate allocations for Sale Due
                    if (paymentType === 'Sale Due' && outstandingItems.length > 0) {
                        debugLog('Validating Sale Due allocations');
                        let totalAllocated = 0;
                        const allocations = [];
                        
                        document.querySelectorAll('.allocation-input').forEach((input, index) => {
                            const value = parseFloat(input.value) || 0;
                            totalAllocated += value;
                            allocations.push({
                                index: index,
                                saleId: input.name.match(/\[(\d+)\]/)[1],
                                value: value
                            });
                        });

                        debugLog('Allocation validation:', {
                            totalAllocated: totalAllocated,
                            paymentAmount: amount,
                            difference: Math.abs(totalAllocated - amount),
                            allocations: allocations
                        });

                        if (Math.abs(totalAllocated - amount) > 0.01) {
                            e.preventDefault();
                            debugLog('Form validation failed: Allocation mismatch');
                            alert('Total allocated amount must equal the payment amount');
                            return;
                        }
                    }

                    debugLog('Form validation passed, proceeding with submission');

                    // Show loading state
                    const submitBtn = document.getElementById('submitPaymentBtn');
                    if (submitBtn) {
                        debugLog('Setting submit button to loading state');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                    }

                    // Log form data being submitted
                    const formData = new FormData(this);
                    const formDataObj = {};
                    for (let [key, value] of formData.entries()) {
                        formDataObj[key] = value;
                    }
                    debugLog('Form data being submitted:', formDataObj);
                });
            }
        } else {
            debugLog('ERROR: Payment modal elements not found!');
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
                e.stopPropagation();
                editCustomerModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });

            function hideEditModal() {
                editCustomerModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }

            if (cancelEditCustomer) {
                cancelEditCustomer.addEventListener('click', (e) => {
                    e.preventDefault();
                    hideEditModal();
                });
            }

            if (cancelEditCustomer2) {
                cancelEditCustomer2.addEventListener('click', (e) => {
                    e.preventDefault();
                    hideEditModal();
                });
            }

            // Close modal when clicking outside
            editCustomerModal.addEventListener('click', (e) => {
                if (e.target === editCustomerModal) {
                    hideEditModal();
                }
            });

            // Prevent form submission from closing modal prematurely
            editCustomerForm.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            // Form submission
            editCustomerForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                }
            });
        }
    });
    </script>
</body>
</html>

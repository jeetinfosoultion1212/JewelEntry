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
$customerQuery = "SELECT id, FirstName, LastName, Address, City, State, PhoneNumber, Email FROM customer WHERE FirmID = ? ORDER BY FirstName, LastName";
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
if (count($customers) > 0) {
    $planQuery = "SELECT id, customer_id FROM customer_gold_plans WHERE customer_id IN ($ids) AND current_status = 'active'";
    $planResult = $conn->query($planQuery);
    $planMap = [];
    if ($planResult) {
        while ($row = $planResult->fetch_assoc()) {
            $planMap[$row['id']] = $row['customer_id'];
        }
    }
    if (count($planMap) > 0) {
        $planIds = implode(',', array_map('intval', array_keys($planMap)));
        $installmentQuery = "SELECT customer_plan_id, SUM(min_amount_per_installment) as gold_due FROM gold_saving_plans gp JOIN customer_gold_plans cgp ON gp.id = cgp.plan_id WHERE cgp.id IN ($planIds) AND cgp.current_status = 'active' GROUP BY cgp.id";
        $installmentResult = $conn->query($installmentQuery);
        if ($installmentResult) {
            while ($row = $installmentResult->fetch_assoc()) {
                $customer_id = $planMap[$row['customer_plan_id']];
                $goldDues[$customer_id] = ($goldDues[$customer_id] ?? 0) + $row['gold_due'];
            }
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
    <title>Customers - JewelEntry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glassmorphism {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.08);
        }
        .gradient-soft {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        .gradient-accent {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        }
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-3px); }
        }
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.4);
        }
        .input-group {
            position: relative;
            margin-bottom: 1rem;
        }
        .input-group input:focus, .input-group select:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
        }
        .stats-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
        }
        .customer-avatar {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-amber-50 via-slate-50 to-indigo-50">
    <!-- Enhanced Header -->
    <header class="header-glass sticky top-0 z-50 shadow-md">
        <div class="px-3 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
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
                    <div id="userProfileMenuToggle" class="w-9 h-9 gradient-purple rounded-xl flex items-center justify-center shadow-lg overflow-hidden cursor-pointer relative transition-transform duration-200">
                        <?php 
                        $defaultImage = 'public/uploads/user.png';
                        if (!empty($userInfo['image_path']) && file_exists($userInfo['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="User Profile" class="w-full h-full object-cover">
                        <?php elseif (file_exists($defaultImage)): ?>
                            <img src="<?php echo htmlspecialchars($defaultImage); ?>" alt="Default User" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user-crown text-white text-sm"></i>
                        <?php endif; ?>
                        <div id="userLogoutDropdown" class="absolute top-12 right-0 w-48 bg-white rounded-lg shadow-xl py-1 z-[9999] hidden transform transition-all duration-200 ease-in-out" style="opacity: 0; transform: translateY(-10px);">
                            <div class="px-3 py-2 border-b border-gray-100">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($userInfo['Name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($userInfo['Role']); ?></p>
                            </div>
                            <a href="profile.html" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                <i class="fas fa-user mr-2 text-purple-600"></i> View Profile
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                <i class="fas fa-sign-out-alt mr-2 text-red-600"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <div class="px-2 pt-2 pb-24 max-w-2xl mx-auto">
        <!-- Quick Stats -->
        <div class="mb-2">
            <div class="flex gap-2 overflow-x-auto pb-1 hide-scrollbar">
                <!-- Total Customers -->
                <div class="flex-shrink-0 bg-white/80 backdrop-blur-sm rounded-xl px-3 py-2.5 shadow-sm border border-gray-100 min-w-[85px]">
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center shadow-sm">
                            <i class="fas fa-users text-white text-[10px]"></i>
                        </div>
                        <div class="text-right ml-2">
                            <div class="text-xl font-bold text-gray-800 leading-none"><?php echo $totalCustomers; ?></div>
                            <div class="text-[9px] text-gray-500 font-medium mt-0.5">Customers</div>
                        </div>
                    </div>
                </div>

                <!-- Due Payments -->
                <div class="flex-shrink-0 bg-white/80 backdrop-blur-sm rounded-xl px-3 py-2.5 shadow-sm border border-gray-100 min-w-[85px]">
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 bg-gradient-to-br from-red-500 to-red-600 rounded-lg flex items-center justify-center shadow-sm">
                            <i class="fas fa-exclamation-circle text-white text-[10px]"></i>
                        </div>
                        <div class="text-right ml-2">
                            <div class="text-xl font-bold text-gray-800 leading-none"><?php echo $totalDuePayments; ?></div>
                            <div class="text-[9px] text-gray-500 font-medium mt-0.5">Due Pay</div>
                        </div>
                    </div>
                </div>

                <!-- EMI Due -->
                <div class="flex-shrink-0 bg-white/80 backdrop-blur-sm rounded-xl px-3 py-2.5 shadow-sm border border-gray-100 min-w-[85px]">
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg flex items-center justify-center shadow-sm">
                            <i class="fas fa-calendar-alt text-white text-[10px]"></i>
                        </div>
                        <div class="text-right ml-2">
                            <div class="text-xl font-bold text-gray-800 leading-none"><?php echo $totalEmiDue; ?></div>
                            <div class="text-[9px] text-gray-500 font-medium mt-0.5">EMI Due</div>
                        </div>
                    </div>
                </div>

                <!-- Gold Plans -->
                <div class="flex-shrink-0 bg-white/80 backdrop-blur-sm rounded-xl px-3 py-2.5 shadow-sm border border-gray-100 min-w-[85px]">
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 bg-gradient-to-br from-amber-500 to-amber-600 rounded-lg flex items-center justify-center shadow-sm">
                            <i class="fas fa-coins text-white text-[10px]"></i>
                        </div>
                        <div class="text-right ml-2">
                            <div class="text-xl font-bold text-gray-800 leading-none"><?php echo $totalGoldDue; ?></div>
                            <div class="text-[9px] text-gray-500 font-medium mt-0.5">Gold Plans</div>
                        </div>
                    </div>
                </div>

                <!-- Active Loans -->
                <div class="flex-shrink-0 bg-white/80 backdrop-blur-sm rounded-xl px-3 py-2.5 shadow-sm border border-gray-100 min-w-[85px]">
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 bg-gradient-to-br from-green-500 to-green-600 rounded-lg flex items-center justify-center shadow-sm">
                            <i class="fas fa-university text-white text-[10px]"></i>
                        </div>
                        <div class="text-right ml-2">
                            <div class="text-xl font-bold text-gray-800 leading-none">8</div>
                            <div class="text-[9px] text-gray-500 font-medium mt-0.5">Loans</div>
                        </div>
                    </div>
                </div>

                <!-- This Month -->
                <div class="flex-shrink-0 bg-white/80 backdrop-blur-sm rounded-xl px-3 py-2.5 shadow-sm border border-gray-100 min-w-[85px]">
                    <div class="flex items-center justify-between">
                        <div class="w-7 h-7 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center shadow-sm">
                            <i class="fas fa-chart-line text-white text-[10px]"></i>
                        </div>
                        <div class="text-right ml-2">
                            <div class="text-xl font-bold text-gray-800 leading-none">15</div>
                            <div class="text-[9px] text-gray-500 font-medium mt-0.5">New</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Search + Add Bar -->
        <div class="mb-4">
            <div class="relative flex items-center bg-gradient-to-r from-white via-indigo-50 to-purple-50 rounded-xl shadow border border-gray-100 px-2 py-1.5">
                <i class="fas fa-search text-indigo-400 text-base ml-2"></i>
                <input type="text" id="customerSearch"
                    class="flex-1 bg-transparent border-0 focus:ring-0 px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:outline-none"
                    placeholder="Search customers by name...">
                <button id="addCustomerBtn" class="ml-2 w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-indigo-400 flex items-center justify-center shadow hover:scale-110 transition-transform" title="Add Customer">
                    <i class="fas fa-user-plus text-white text-lg"></i>
                </button>
            </div>
        </div>
        <!-- Customer List -->
        <div id="customerList" class="space-y-1.5">
    <?php if (count($customers) === 0): ?>
        <div class="text-center py-12">
            <div class="w-16 h-16 mx-auto bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-users text-indigo-300 text-2xl"></i>
            </div>
            <p class="text-gray-500 text-sm mb-4">No customers found</p>
            <button id="addCustomerBtnEmpty" class="bg-gradient-to-br from-purple-500 to-indigo-400 text-white px-4 py-2 rounded-lg text-sm font-medium shadow hover:scale-105 transition-transform flex items-center mx-auto">
                <i class="fas fa-user-plus mr-2"></i> Add Your First Customer
            </button>
        </div>
    <?php else: ?>
        <?php $serial = 1; ?>
        <?php foreach ($customers as $id => $c): ?>
        <a href="customer_details.php?id=<?= $id ?>" class="block card-hover group">
            <div class="relative flex items-start bg-white rounded-lg shadow-sm border border-gray-200 px-2 py-1.5 hover:shadow-md transition">

                <!-- Serial Badge -->
                <span class="absolute -top-2 -left-2 bg-gradient-to-br from-purple-500 to-indigo-400 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow border border-white z-10">
                    #<?= $serial++ ?>
                </span>

                <!-- Avatar -->
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center text-indigo-600 font-bold mr-3 text-sm shadow">
                    <?= strtoupper(substr($c['FirstName'], 0, 1) . substr($c['LastName'], 0, 1)) ?>
                </div>

                <!-- Info Block -->
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                        <!-- Name + Icons -->
                        <div class="flex flex-col">
                            <div class="flex items-center gap-1 text-sm font-semibold text-gray-800 leading-tight">
                                <?= htmlspecialchars($c['FirstName'] . ' ' . $c['LastName']) ?>
                                <?php if (!empty($c['PhoneNumber'])): ?><i class="fas fa-phone text-green-500 text-xs"></i><?php endif; ?>
                                <?php if (!empty($c['City'])): ?><i class="fas fa-map-marker-alt text-blue-400 text-xs"></i><?php endif; ?>
                                <?php
                                if (!empty($c['DateOfBirth'])) {
                                    $dob = DateTime::createFromFormat('Y-m-d', $c['DateOfBirth']);
                                    $now = new DateTime();
                                    $nextBirthday = DateTime::createFromFormat('Y-m-d', $now->format('Y') . '-' . $dob->format('m-d'));
                                    if ($nextBirthday < $now) {
                                        $nextBirthday->modify('+1 year');
                                    }
                                    $daysUntilBirthday = $now->diff($nextBirthday)->days;
                                    if ($dob->format('m-d') === $now->format('m-d')) {
                                        echo '<span class="ml-1 px-1 py-0 rounded-full bg-pink-100 text-pink-600 text-[9px] font-bold flex items-center"><i class="fas fa-birthday-cake mr-1"></i>Today!</span>';
                                    } elseif ($daysUntilBirthday <= 7) {
                                        echo '<span class="ml-1 px-1 py-0 rounded-full bg-yellow-100 text-yellow-600 text-[9px] font-bold flex items-center"><i class="fas fa-gift mr-1"></i>Soon!</span>';
                                    }
                                }
                                ?>
                            </div>
                            <div class="text-[11px] text-gray-500 truncate">
                                <?= htmlspecialchars($c['Address']) ?>
                            </div>
                        </div>

                        <!-- WhatsApp Button -->
                        <?php if (!empty($c['PhoneNumber'])): ?>
                            <a href="https://wa.me/91<?= $c['PhoneNumber'] ?>?text=<?= urlencode('Greetings from ' . ($userInfo['FirmName'] ?? 'JewelEntry') . '!') ?>"
                               target="_blank"
                               title="Send WhatsApp Greeting"
                               onclick="event.stopPropagation();"
                               class="ml-2 w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white shadow hover:scale-110 transition">
                                <i class="fab fa-whatsapp text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Badge Row -->
                    <div class="flex flex-wrap gap-1 mt-1">
                        <?php if (isset($dues[$id]) && $dues[$id] > 0): ?>
                            <span class="bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full text-[10px] font-semibold flex items-center shadow-sm">
                                <i class="fas fa-dollar-sign mr-1"></i>₹<?= number_format($dues[$id], 0) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (isset($loans[$id]) && $loans[$id] > 0): ?>
                            <span class="bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded-full text-[10px] font-semibold flex items-center shadow-sm">
                                <i class="fas fa-university mr-1"></i>₹<?= number_format($loans[$id], 0) ?>
                            </span>
                        <?php endif; ?>
                        <?php $monthlyDueTotal = ($emiDues[$id] ?? 0) + ($goldDues[$id] ?? 0); ?>
                        <?php if ($monthlyDueTotal > 0): ?>
                            <span class="bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded-full text-[10px] font-semibold flex items-center shadow-sm">
                                <i class="fas fa-calendar-alt mr-1"></i>EMI/Scheme: ₹<?= number_format($monthlyDueTotal, 0) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

    </div>

    <!-- Add Customer Modal -->
    <div id="addCustomerModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto max-h-[90vh] overflow-y-auto border border-indigo-100">
            <form id="addCustomerForm" method="POST" action="add_customer.php" enctype="multipart/form-data">
                <input type="hidden" name="customer_id" id="customerIdField" value="">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-indigo-50 via-purple-50 to-white rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <h3 id="customerModalTitle" class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-user-plus text-indigo-500 mr-2"></i>
                            Add New Customer
                        </h3>
                        <button type="button" id="cancelAddCustomer" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                <!-- Modal Body -->
                <div class="px-6 py-4">
                    <!-- Profile Image Section -->
                    <div class="flex flex-col items-center mb-6">
                        <div class="relative group">
                            <label for="customerImage" class="block w-24 h-24 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 border-2 border-dashed border-indigo-200 flex items-center justify-center cursor-pointer overflow-hidden hover:border-indigo-300 transition-colors">
                                <img id="customerImagePreview" src="" alt="Preview" class="object-cover w-full h-full hidden rounded-full" />
                                <div id="customerImagePlaceholder" class="flex flex-col items-center justify-center text-indigo-400">
                                    <i class="fas fa-camera text-2xl mb-1"></i>
                                    <span class="text-xs">Photo</span>
                                </div>
                                <input type="file" id="customerImage" name="CustomerImage" accept="image/*" capture="environment" class="hidden" />
                            </label>
                            <button type="button" id="removeCustomerImage" class="absolute bottom-2 right-2 bg-white/80 hover:bg-red-100 text-red-500 rounded-full p-1 shadow hidden" title="Remove photo">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <span class="text-xs text-gray-400 mt-1">Tap to capture or upload</span>
                    </div>
                    <!-- Form Fields -->
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="text" name="FirstName" required 
                                           class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                           placeholder="First Name *">
                                </div>
                            </div>
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="text" name="LastName" 
                                           class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                           placeholder="Last Name">
                                </div>
                            </div>
                        </div>
                        
                        <div class="input-group">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-phone text-gray-400 text-sm"></i>
                                </div>
                                <input type="tel" name="PhoneNumber" required pattern="[0-9]{10,15}" 
                                       class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                       placeholder="Phone Number *">
                            </div>
                        </div>
                        
                        <div class="input-group">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400 text-sm"></i>
                                </div>
                                <input type="email" name="Email" 
                                       class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                       placeholder="Email">
                            </div>
                        </div>
                        
                        <div class="input-group">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-map-marker-alt text-gray-400 text-sm"></i>
                                </div>
                                <input type="text" name="Address" 
                                       class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                       placeholder="Address">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-city text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="text" name="City" 
                                           class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                           placeholder="City">
                                </div>
                            </div>
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-flag text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="text" name="State" 
                                           class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                           placeholder="State">
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-mail-bulk text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="text" name="PostalCode" 
                                           class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                           placeholder="Postal Code">
                                </div>
                            </div>
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-globe text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="text" name="Country" 
                                           class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                           placeholder="Country" value="India">
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-birthday-cake text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="date" name="DateOfBirth" 
                                           class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm">
                                </div>
                            </div>
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-venus-mars text-gray-400 text-sm"></i>
                                    </div>
                                    <select name="Gender" 
                                            class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm">
                                        <option value="">Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-id-card text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="text" name="PANNumber" maxlength="10" 
                                           class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                           placeholder="PAN Number">
                                </div>
                            </div>
                            <div class="input-group">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-id-badge text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="text" name="AadhaarNumber" maxlength="12" 
                                           class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 text-sm" 
                                           placeholder="Aadhaar Number">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end space-x-3">
                    <button type="button" id="cancelAddCustomer2" 
                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="customerModalSubmitBtn"
                            class="gradient-accent text-white px-4 py-2 rounded-lg text-sm font-medium hover:shadow-md transition-all">
                        <i class="fas fa-plus mr-2"></i>Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

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


    <script>
        // Declare all DOM variables only once at the top
        var addCustomerBtn = document.getElementById('addCustomerBtn');
        var addCustomerModal = document.getElementById('addCustomerModal');
        var cancelAddCustomer = document.getElementById('cancelAddCustomer');
        var cancelAddCustomer2 = document.getElementById('cancelAddCustomer2');
        var customerImageInput = document.getElementById('customerImage');
        var customerImagePreview = document.getElementById('customerImagePreview');
        var customerImagePlaceholder = document.getElementById('customerImagePlaceholder');
        var removeCustomerImageBtn = document.getElementById('removeCustomerImage');
        var addCustomerForm = document.getElementById('addCustomerForm');
        var customerModalTitle = document.getElementById('customerModalTitle');
        var customerModalSubmitBtn = document.getElementById('customerModalSubmitBtn');
        var customerIdField = document.getElementById('customerIdField');

        // Get all customer card divs (the clickable area within the link)
        var customerCardDivs = document.querySelectorAll('div[data-customer-id]'); // Correct and singular declaration

        // Modal functionality
        if (addCustomerBtn) {
            addCustomerBtn.addEventListener('click', () => {
                // Reset form for adding new customer
                addCustomerForm.reset();
                customerImagePreview.src = '';
                customerImagePreview.classList.add('hidden');
                customerImagePlaceholder.style.display = '';
                removeCustomerImageBtn.classList.add('hidden');

                customerModalTitle.innerHTML = '<i class="fas fa-user-plus text-indigo-500 mr-2"></i>Add New Customer';
                customerModalSubmitBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>Add Customer';
                addCustomerForm.action = 'add_customer.php';
                customerIdField.value = '';

                addCustomerModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
            });
        }

        function hideModal() {
            addCustomerModal.classList.add('hidden');
            document.body.style.overflow = 'auto'; // Restore scrolling
            addCustomerForm.reset(); // Reset form on close
            // Reset image preview on modal close
            customerImagePreview.src = '';
            customerImagePreview.classList.add('hidden');
            customerImagePlaceholder.style.display = '';
            removeCustomerImageBtn.classList.add('hidden');
        }

        // Close modal listeners
        if (cancelAddCustomer) cancelAddCustomer.addEventListener('click', hideModal);
        if (cancelAddCustomer2) cancelAddCustomer2.addEventListener('click', hideModal);
        if (addCustomerModal) {
            // Close modal when clicking outside the modal content
            addCustomerModal.addEventListener('click', (e) => {
                if (e.target === addCustomerModal) {
                    hideModal();
                }
            });
        }

        // Image preview and remove functionality
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
                } else { // If user cancels file selection
                     customerImagePreview.src = '';
                     customerImagePreview.classList.add('hidden');
                     customerImagePlaceholder.style.display = '';
                     removeCustomerImageBtn.classList.add('hidden');
                }
            });
        }

        if (removeCustomerImageBtn && customerImageInput) {
            removeCustomerImageBtn.addEventListener('click', function() {
                customerImageInput.value = ''; // Clear the file input
                customerImagePreview.src = '';
                customerImagePreview.classList.add('hidden');
                customerImagePlaceholder.style.display = '';
                removeCustomerImageBtn.classList.add('hidden');
            });
        }

        // Edit customer logic (if needed on this page - currently commented out in HTML)
        // var editButtons = document.querySelectorAll('.edit-customer-btn');
        // editButtons.forEach(btn => {
        //     btn.addEventListener('click', function(e) {
        //         e.stopPropagation(); // Prevent card click
        //         var card = btn.closest('[data-customer-id]');
        //         var customer = JSON.parse(card.getAttribute('data-customer'));
        //         // ... (modal opening and field population logic)
        //     });
        // });

        // Customer card click handling (primarily to prevent navigation on WhatsApp icon click)
        customerCardDivs.forEach(cardDiv => {
            cardDiv.addEventListener('click', function(e) {
                // Check if the click target is the WhatsApp link or an icon inside it
                if (e.target.closest('a[title="Send WhatsApp Greeting"]')) {
                    return; // Do nothing if WhatsApp link is clicked
                }
                // The entire <a> tag wrapping this div handles the navigation
            });
        });

        // Form validation (remains the same)
        if (addCustomerForm) { // Add a check if the form element exists
            addCustomerForm.addEventListener('submit', function(e) {
                var firstName = this.querySelector('input[name="FirstName"]').value.trim();
                var phoneNumber = this.querySelector('input[name="PhoneNumber"]').value.trim();
                if (!firstName) {
                    e.preventDefault();
                    alert('First Name is required');
                    return;
                }
                if (!phoneNumber) {
                    e.preventDefault();
                    alert('Phone Number is required');
                    return;
                }
                var phoneRegex = /^[0-9]{10,15}$/;
                if (!phoneRegex.test(phoneNumber)) {
                    e.preventDefault();
                    alert('Please enter a valid phone number (10-15 digits)');
                    return;
                }
            });
        }

        // Defensive: share modal button (if present) - check if the element exists
        var shareModalBtn = document.getElementById('shareModalBtn');
        if (shareModalBtn) {
            shareModalBtn.addEventListener('click', function() {
                // Your share modal logic here
                console.log('Share modal button clicked'); // Debugging line
            });
        }

        // Search functionality
        const searchInput = document.getElementById('customerSearch');
        // customerCardDivs variable is already declared and selected at the top

        if (searchInput && customerCardDivs.length > 0) { // Add checks for elements existence
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                // Iterate through the correctly selected customer item divs
                customerCardDivs.forEach(cardDiv => {
                    const customerName = cardDiv.getAttribute('data-name');
                    // Check if customerName exists before using include
                    const shouldShow = customerName && customerName.includes(searchTerm);
                    
                    // Find the parent <a> tag which is the list item
                    const listItemLink = cardDiv.parentElement;

                    if (listItemLink && listItemLink.tagName === 'A') { // Ensure parent is an <a> tag
                        if (shouldShow) {
                            listItemLink.style.display = 'block'; // Show the list item
                            // Optional: reapply animation if needed
                            // listItemLink.style.animation = 'fadeIn 0.3s ease-out';
                        } else {
                            listItemLink.style.display = 'none'; // Hide the list item
                        }
                    }
                });
            });
        }

        // Add fade-in animation CSS (remains the same)
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);

        // Handle form submission success/error messages (remains the same)
        <?php if (isset($_GET['success'])): ?>
            if (<?php echo $_GET['success']; ?> == 1) {
                const successDiv = document.createElement('div');
                successDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                successDiv.innerHTML = '<i class="fas fa-check mr-2"></i>Customer added successfully!';
                document.body.appendChild(successDiv);
                setTimeout(() => { successDiv.remove(); }, 3000);
            }
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            const errorDiv = document.createElement('div');
            errorDiv.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            errorDiv.innerHTML = '<i class="fas fa-exclamation mr-2"></i><?php echo htmlspecialchars($_GET['error']); ?>';
            document.body.appendChild(errorDiv);
            setTimeout(() => { errorDiv.remove(); }, 5000);
        <?php endif; ?>

    </script>
</body>
</html>
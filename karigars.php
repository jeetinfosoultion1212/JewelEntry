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

// Fetch karigar statistics
function fetchKarigarStats($conn, $firm_id) {
    $stats = [];
    
    try {
        // Total Karigars
        $totalQuery = "SELECT COUNT(*) as total FROM karigars WHERE firm_id = ?";
        $totalStmt = $conn->prepare($totalQuery);
        $totalStmt->bind_param("i", $firm_id);
        $totalStmt->execute();
        $stats['total'] = $totalStmt->get_result()->fetch_assoc()['total'];

        // Active Karigars
        $activeQuery = "SELECT COUNT(*) as active FROM karigars WHERE firm_id = ? AND status = 'Active'";
        $activeStmt = $conn->prepare($activeQuery);
        $activeStmt->bind_param("i", $firm_id);
        $activeStmt->execute();
        $stats['active'] = $activeStmt->get_result()->fetch_assoc()['active'];

        // Active Orders
        $ordersQuery = "SELECT COUNT(*) as orders FROM jewellery_manufacturing_orders WHERE firm_id = ? AND status IN ('Pending', 'In Progress')";
        $ordersStmt = $conn->prepare($ordersQuery);
        $ordersStmt->bind_param("i", $firm_id);
        $ordersStmt->execute();
        $stats['orders'] = $ordersStmt->get_result()->fetch_assoc()['orders'];

        // Completed This Month
        $completedQuery = "SELECT COUNT(*) as completed FROM jewellery_manufacturing_orders WHERE firm_id = ? AND status = 'Delivered' AND MONTH(completed_at) = MONTH(CURDATE())";
        $completedStmt = $conn->prepare($completedQuery);
        $completedStmt->bind_param("i", $firm_id);
        $completedStmt->execute();
        $stats['completed'] = $completedStmt->get_result()->fetch_assoc()['completed'];

        // Total Revenue This Month
        $revenueQuery = "SELECT COALESCE(SUM(total_estimated), 0) as revenue FROM jewellery_manufacturing_orders WHERE firm_id = ? AND status = 'Delivered' AND MONTH(completed_at) = MONTH(CURDATE())";
        $revenueStmt = $conn->prepare($revenueQuery);
        $revenueStmt->bind_param("i", $firm_id);
        $revenueStmt->execute();
        $stats['revenue'] = $revenueStmt->get_result()->fetch_assoc()['revenue'];

        // Pending Payments
        $pendingQuery = "SELECT COUNT(*) as pending FROM jewellery_manufacturing_orders WHERE firm_id = ? AND status = 'Delivered' AND advance_amount < total_estimated";
        $pendingStmt = $conn->prepare($pendingQuery);
        $pendingStmt->bind_param("i", $firm_id);
        $pendingStmt->execute();
        $stats['pending'] = $pendingStmt->get_result()->fetch_assoc()['pending'];

    } catch (Exception $e) {
        error_log("Karigar stats error: " . $e->getMessage());
        $stats = ['total' => 0, 'active' => 0, 'orders' => 0, 'completed' => 0, 'revenue' => 0, 'pending' => 0];
    }
    
    return $stats;
}

// Fetch karigars list
function fetchKarigars($conn, $firm_id) {
    $karigars = [];
    
    try {
        $query = "SELECT k.*, 
                  COUNT(DISTINCT jmo.id) as active_orders,
                  COUNT(DISTINCT CASE WHEN jmo.status = 'Delivered' AND MONTH(jmo.completed_at) = MONTH(CURDATE()) THEN jmo.id END) as completed_orders,
                  COALESCE(SUM(CASE WHEN jmo.status = 'Delivered' AND MONTH(jmo.completed_at) = MONTH(CURDATE()) THEN jmo.total_estimated END), 0) as monthly_revenue
                  FROM karigars k
                  LEFT JOIN jewellery_manufacturing_orders jmo ON k.id = jmo.karigar_id
                  WHERE k.firm_id = ?
                  GROUP BY k.id
                  ORDER BY k.status DESC, k.name ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $firm_id);
        $stmt->execute();
        $karigars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Fetch karigars error: " . $e->getMessage());
    }
    
    return $karigars;
}

// Fetch compact order items for Orders tab (with customer name)
function fetchOrderItems($conn, $firm_id) {
    $orders = [];
    $sql = "SELECT joi.id, joi.karigar_id, k.name as karigar_name, joi.item_name, joi.product_type, joi.metal_type, joi.purity, joi.gross_weight, joi.net_weight, joi.item_status as status, joi.created_at, c.FirstName as customer_first, c.LastName as customer_last
            FROM jewellery_order_items joi
            LEFT JOIN karigars k ON joi.karigar_id = k.id
            LEFT JOIN jewellery_customer_order o ON joi.order_id = o.id
            LEFT JOIN customer c ON o.customer_id = c.id
            WHERE joi.firm_id = ?
            ORDER BY joi.created_at DESC LIMIT 30";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $firm_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['customer_name'] = trim(($row['customer_first'] ?? '') . ' ' . ($row['customer_last'] ?? ''));
        $orders[] = $row;
    }
    return $orders;
}

$stats = fetchKarigarStats($conn, $firm_id);
$karigars = fetchKarigars($conn, $firm_id);
$orderItems = fetchOrderItems($conn, $firm_id);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Karigar Management - <?php echo htmlspecialchars($userInfo['FirmName']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/home.css">
</head>
<body class="font-poppins bg-gray-100">
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
                        <h1 class="text-sm font-bold text-gray-800">Karigar Management</h1>
                        <p class="text-xs text-gray-600 font-medium"><?php echo strtoupper(htmlspecialchars($userInfo['FirmName'])); ?></p>
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

    <main class="px-2 pt-2 pb-20">
        <!-- Compact Horizontal Scrollable Stats -->
        <div class="overflow-x-auto hide-scrollbar mb-3">
            <div class="flex space-x-2 min-w-max">
                <div class="stat-card min-w-[110px] bg-white rounded-xl px-3 py-2 text-center shadow-sm">
                    <div class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                        <i class="fas fa-users text-blue-600 text-base"></i>
                    </div>
                    <div class="font-bold text-base text-gray-800"><?php echo $stats['total']; ?></div>
                    <div class="text-xs text-gray-500 font-medium">Total</div>
                </div>
                <div class="stat-card min-w-[110px] bg-white rounded-xl px-3 py-2 text-center shadow-sm">
                    <div class="w-7 h-7 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                        <i class="fas fa-user-check text-green-600 text-base"></i>
            </div>
                    <div class="font-bold text-base text-gray-800"><?php echo $stats['active']; ?></div>
                    <div class="text-xs text-gray-500 font-medium">Active</div>
                </div>
                <div class="stat-card min-w-[110px] bg-white rounded-xl px-3 py-2 text-center shadow-sm">
                    <div class="w-7 h-7 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                        <i class="fas fa-clipboard-list text-purple-600 text-base"></i>
            </div>
                    <div class="font-bold text-base text-gray-800"><?php echo $stats['orders']; ?></div>
                    <div class="text-xs text-gray-500 font-medium">Orders</div>
                </div>
                <div class="stat-card min-w-[110px] bg-white rounded-xl px-3 py-2 text-center shadow-sm">
                    <div class="w-7 h-7 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                        <i class="fas fa-check-circle text-green-600 text-base"></i>
            </div>
                    <div class="font-bold text-base text-gray-800"><?php echo $stats['completed']; ?></div>
                    <div class="text-xs text-gray-500 font-medium">Completed</div>
                </div>
                <div class="stat-card min-w-[110px] bg-white rounded-xl px-3 py-2 text-center shadow-sm">
                    <div class="w-7 h-7 bg-yellow-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                        <i class="fas fa-rupee-sign text-yellow-600 text-base"></i>
            </div>
                    <div class="font-bold text-base text-gray-800">₹<?php echo number_format($stats['revenue']); ?></div>
                    <div class="text-xs text-gray-500 font-medium">Revenue</div>
                </div>
                <div class="stat-card min-w-[110px] bg-white rounded-xl px-3 py-2 text-center shadow-sm">
                    <div class="w-7 h-7 bg-red-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                        <i class="fas fa-exclamation-triangle text-red-600 text-base"></i>
            </div>
                    <div class="font-bold text-base text-gray-800"><?php echo $stats['pending']; ?></div>
                    <div class="text-xs text-gray-500 font-medium">Pending</div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white rounded-xl p-1 mb-4 flex space-x-1 overflow-x-auto hide-scrollbar">
            <button class="tab-btn active flex-1 py-2 px-3 rounded-lg text-xs font-medium" data-tab="karigars" onclick="switchKarigarTab('karigars', this)">
                <i class="fas fa-users mr-1"></i>All Karigars
            </button>
            <button class="tab-btn flex-1 py-2 px-3 rounded-lg text-xs font-medium" data-tab="orders" onclick="switchKarigarTab('orders', this)">
                <i class="fas fa-clipboard-list mr-1"></i>Orders
            </button>
            <button class="tab-btn flex-1 py-2 px-3 rounded-lg text-xs font-medium" data-tab="payments" onclick="switchKarigarTab('payments', this)">
                <i class="fas fa-rupee-sign mr-1"></i>Payments
            </button>
        </div>
        <!-- Tab Contents -->
        <div id="tab-content-karigars" class="tab-content block">
        <!-- Karigars List -->
        <div class="bg-white rounded-xl">
            <div class="flex items-center justify-between p-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-hammer mr-2 text-orange-500"></i>Karigars
                </h3>
                <button onclick="openAddKarigarModal()" class="bg-orange-500 text-white px-3 py-1.5 rounded-lg text-xs font-semibold">
                    + Add Karigar
                </button>
            </div>
            <div class="divide-y divide-gray-100">
                <?php foreach ($karigars as $karigar): ?>
                <div class="p-4 karigar-item" data-status="<?php echo strtolower($karigar['status']); ?>">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                <span class="text-white font-bold text-sm">
                                    <?php echo strtoupper(substr($karigar['name'], 0, 2)); ?>
                                </span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($karigar['name']); ?></h4>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($karigar['phone_number'] ?? 'No phone'); ?></p>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="<?php echo $karigar['status'] === 'Active' ? 'status-active' : 'status-inactive'; ?> px-2 py-0.5 rounded-full text-xs font-medium">
                                        <?php echo $karigar['status']; ?>
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        ₹<?php echo number_format($karigar['default_making_charge']); ?>/<?php echo $karigar['charge_type']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-800"><?php echo $karigar['active_orders']; ?> orders</p>
                            <p class="text-xs text-gray-600">₹<?php echo number_format($karigar['monthly_revenue']); ?> revenue</p>
                            <div class="flex space-x-1 mt-2">
                                <button onclick="editKarigar(<?php echo $karigar['id']; ?>)" class="btn-secondary px-2 py-1 rounded text-xs">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="assignWork(<?php echo $karigar['id']; ?>)" class="btn-success px-2 py-1 rounded text-xs">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button onclick="viewDetails(<?php echo $karigar['id']; ?>)" class="btn-primary px-2 py-1 rounded text-xs text-white">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($karigars)): ?>
                <div class="p-8 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-sm font-medium text-gray-800 mb-2">No Karigars Found</h3>
                    <p class="text-xs text-gray-600 mb-4">Start by adding your first karigar to manage work assignments.</p>
                    <button onclick="openAddKarigarModal()" class="btn-primary text-white px-4 py-2 rounded-lg text-xs font-semibold">
                        <i class="fas fa-plus mr-2"></i>Add First Karigar
                    </button>
                </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
        <div id="tab-content-orders" class="tab-content hidden">
            <div class="bg-white rounded-xl">
                <div class="flex items-center justify-between p-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-clipboard-list mr-2 text-purple-500"></i>Orders
                    </h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($orderItems as $order): ?>
                    <div class="p-2 flex items-center justify-between hover:bg-purple-50 transition text-xs">
                        <div>
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['item_name']); ?> <span class="text-gray-400">(<?php echo htmlspecialchars($order['product_type']); ?>)</span></div>
                            <div class="text-gray-500">Cust: <span class="font-medium text-gray-700"><?php echo htmlspecialchars($order['customer_name']); ?></span></div>
                            <div class="text-gray-500">Karigar: <span class="font-medium text-gray-700"><?php echo htmlspecialchars($order['karigar_name'] ?? 'N/A'); ?></span></div>
                        </div>
                        <div class="text-right flex flex-col items-end space-y-1">
                            <span class="font-semibold <?php echo $order['status'] === 'Completed' ? 'text-green-600' : 'text-blue-600'; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                            <span class="text-gray-400"><?php echo date('d M', strtotime($order['created_at'])); ?></span>
                            <span class="text-gray-600"><?php echo number_format($order['net_weight'], 2); ?>g</span>
                            <div class="flex space-x-1 mt-1">
                                <button onclick="editOrderItem(<?php echo $order['id']; ?>)" class="p-1 rounded hover:bg-gray-200"><i class="fas fa-edit text-blue-500"></i></button>
                                <button onclick="openIssueGoldModal(<?php echo $order['id']; ?>)" class="p-1 rounded hover:bg-yellow-100"><i class="fas fa-coins text-yellow-500"></i></button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($orderItems)): ?>
                    <div class="p-8 text-center text-gray-400">
                        <i class="fas fa-clipboard-list text-2xl mb-2"></i>
                        <div class="font-semibold mb-1">No Orders</div>
                        <div class="text-xs">No jewellery orders found for this firm.</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div id="tab-content-payments" class="tab-content hidden">
            <div class="bg-white rounded-xl p-6 text-center text-gray-500">
                <i class="fas fa-rupee-sign text-2xl mb-2"></i>
                <div class="font-semibold mb-1">Payments</div>
                <div class="text-xs">Payment records for karigars will appear here.</div>
            </div>
        </div>
    </main>

    <!-- Enhanced Bottom Navigation -->
    <nav class="bottom-nav fixed bottom-0 left-0 right-0 shadow-xl z-40">
        <div class="px-4 py-2">
            <div class="flex justify-around">
                <a href="home.php" data-nav-id="home" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Home</span>
                </a>
                <a href="inventory.php" data-nav-id="inventory" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-boxes text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Inventory</span>
                </a>
                <a href="alerts.php" data-nav-id="alerts_nav" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Alerts</span>
                </a>
                <a href="customers.php" data-nav-id="customers" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Customers</span>
                </a>
                <a href="karigars.php" data-nav-id="karigars" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gradient-to-br from-orange-500 to-pink-500 rounded-lg flex items-center justify-center shadow-lg">
                        <i class="fas fa-hammer text-white text-sm"></i>
                    </div>
                    <span class="text-xs text-orange-600 font-bold">Karigars</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Add Karigar Modal -->
    <div id="addKarigarModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl w-full max-w-md">
                <div class="p-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Add New Karigar</h3>
                        <button onclick="closeAddKarigarModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <form id="addKarigarForm" class="p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="tel" name="phone_number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Making Charge</label>
                            <input type="number" name="default_making_charge" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Charge Type</label>
                            <select name="charge_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="PerGram">Per Gram</option>
                                <option value="PerPiece">Per Piece</option>
                                <option value="Fixed">Fixed</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="address_line1" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div class="flex space-x-3 pt-4">
                        <button type="button" onclick="closeAddKarigarModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">
                            Add Karigar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Issue Gold Modal -->
    <div id="issueGoldModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-4 w-full max-w-xs mx-2">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-base font-semibold text-gray-800">Issue Gold</h3>
                <button onclick="closeIssueGoldModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="issueGoldForm" class="space-y-2">
                <input type="hidden" name="order_item_id" id="issueGoldOrderItemId">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Metal Type</label>
                    <select name="metal_type" class="w-full px-2 py-1 border border-gray-300 rounded text-xs">
                        <option value="Gold">Gold</option>
                        <option value="Silver">Silver</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Purity</label>
                    <input type="text" name="purity" class="w-full px-2 py-1 border border-gray-300 rounded text-xs" placeholder="e.g. 22K, 24K">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Weight (g)</label>
                    <input type="number" name="weight" step="0.01" min="0" class="w-full px-2 py-1 border border-gray-300 rounded text-xs" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                    <input type="text" name="notes" class="w-full px-2 py-1 border border-gray-300 rounded text-xs" placeholder="Optional">
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="closeIssueGoldModal()" class="px-3 py-1 text-xs border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="bg-yellow-500 text-white px-3 py-1 text-xs rounded hover:bg-yellow-600">Issue</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter functionality
        function filterKarigars(type) {
            // Update active tab
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('bg-blue-500', 'text-white');
                btn.classList.add('text-gray-600');
            });
            event.target.classList.add('bg-blue-500', 'text-white');
            event.target.classList.remove('text-gray-600');

            // Filter items
            const items = document.querySelectorAll('.karigar-item');
            items.forEach(item => {
                const status = item.dataset.status;
                const hasOrders = item.querySelector('.font-semibold').textContent.includes('orders');
                
                switch(type) {
                    case 'all':
                        item.style.display = 'block';
                        break;
                    case 'active':
                        item.style.display = status === 'active' ? 'block' : 'none';
                        break;
                    case 'orders':
                        item.style.display = hasOrders ? 'block' : 'none';
                        break;
                    case 'payments':
                        // Show karigars with pending payments
                        item.style.display = 'block';
                        break;
                }
            });
        }

        // Modal functions
        function openAddKarigarModal() {
            document.getElementById('addKarigarModal').classList.remove('hidden');
        }

        function closeAddKarigarModal() {
            document.getElementById('addKarigarModal').classList.add('hidden');
            document.getElementById('addKarigarForm').reset();
        }

        // Form submission
        document.getElementById('addKarigarForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_karigar');
            formData.append('firm_id', <?php echo $firm_id; ?>);

            fetch('ajax/karigar_operations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddKarigarModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the karigar.');
            });
        });

        // Action functions
        function editKarigar(id) {
            window.location.href = `edit_karigar.php?id=${id}`;
        }

        function assignWork(id) {
            window.location.href = `assign_work.php?karigar_id=${id}`;
        }

        function viewDetails(id) {
            window.location.href = `karigar_details.php?id=${id}`;
        }

        function exportData() {
            window.open('export_karigars.php', '_blank');
        }

        function refreshData() {
            location.reload();
        }

        // Close modal when clicking outside
        document.getElementById('addKarigarModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddKarigarModal();
            }
        });

        function switchKarigarTab(tab, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active', 'bg-blue-500', 'text-white'));
            btn.classList.add('active', 'bg-blue-500', 'text-white');
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.add('hidden'));
            document.getElementById('tab-content-' + tab).classList.remove('hidden');
        }

        function editOrderItem(id) {
            window.location.href = 'edit_order_item.php?id=' + id;
        }

        function openIssueGoldModal(orderItemId) {
            document.getElementById('issueGoldOrderItemId').value = orderItemId;
            document.getElementById('issueGoldModal').classList.remove('hidden');
        }

        function closeIssueGoldModal() {
            document.getElementById('issueGoldModal').classList.add('hidden');
            document.getElementById('issueGoldForm').reset();
        }

        document.getElementById('issueGoldForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'issue_gold');
            fetch('ajax/karigar_operations.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeIssueGoldModal();
                    alert('Gold issued and ledger updated!');
                    location.reload();
                } else {
                    alert(data.message || 'Error issuing gold');
                }
            })
            .catch(() => alert('Error issuing gold'));
        });
    </script>
    <script type="module" src="js/home.js"></script>
</body>
</html>


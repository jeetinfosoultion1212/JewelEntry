<?php
// Prevent PHP errors from breaking JSON responses
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['id'])) {
   header("Location: login.php");
   exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Fetch user and firm details
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();

// Fetch suppliers for dropdown
$suppliers = [];
$supplierQuery = $conn->query("SELECT id, name, gst FROM suppliers ORDER BY name");
while ($row = $supplierQuery->fetch_assoc()) {
    $suppliers[] = $row;
}

// Fetch inventory metals for list and stats
$inventoryMetals = [];
$statsByPurity = [];
$totalStock = 0;
$totalValue = 0;
$inventoryQuery = $conn->prepare("SELECT material_type, stock_name, purity, unit_measurement, current_stock, cost_price_per_gram, total_cost, last_updated FROM inventory_metals WHERE firm_id = ? ORDER BY material_type, purity DESC, stock_name");
$inventoryQuery->bind_param("i", $firm_id);
$inventoryQuery->execute();
$inventoryResult = $inventoryQuery->get_result();
while ($row = $inventoryResult->fetch_assoc()) {
    $inventoryMetals[] = $row;
    $purityKey = $row['purity'];
    if (!isset($statsByPurity[$purityKey])) {
        $statsByPurity[$purityKey] = ['stock' => 0, 'value' => 0];
    }
    $statsByPurity[$purityKey]['stock'] += $row['current_stock'];
    $statsByPurity[$purityKey]['value'] += $row['total_cost'];
    $totalStock += $row['current_stock'];
    $totalValue += $row['total_cost'];
}

// Handle AJAX form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['material_type'])) {
    header('Content-Type: application/json');
    $entryType = $_POST['entry_type'] ?? 'opening_stock';
    $materialType = $_POST['material_type'];
    $stockName = $_POST['stock_name'];
    $purity = floatval($_POST['purity']);
    $unit = $_POST['unit_measurement'];
    $weight = floatval($_POST['weight']);
    $rate = floatval($_POST['rate']);
    $makingCharges = floatval($_POST['making_charges'] ?? 0);
    $costPricePerGram = floatval($_POST['cost_price_per_gram']);
    $totalTaxableAmount = floatval($_POST['total_taxable_amount']);
    $finalAmount = floatval($_POST['final_amount']);
    $gst = isset($_POST['gst']) && $_POST['gst'] === 'true' ? 1 : 0;
    $firm_id = $_SESSION['firmID'];
    $sourceType = $entryType === 'purchase' ? 'purchase' : 'opening_stock';
    $sourceId = null;
    $customSourceInfo = $_POST['custom_source_info'] ?? '';
    $supplierId = $_POST['supplier_id'] ?? null;
    $invoiceNumber = $_POST['invoice_number'] ?? '';
    $invoiceDate = $_POST['invoice_date'] ?? date('Y-m-d');
    $paidAmount = floatval($_POST['paid_amount'] ?? 0);
    $paymentStatus = $_POST['payment_status'] ?? 'unpaid';
    $paymentMode = $_POST['payment_mode'] ?? '';
    $transactionRef = $_POST['transaction_ref'] ?? '';
    $debugLog = [];
    try {
        $conn->begin_transaction();
        // Insert or update inventory_metals
        $stmt = $conn->prepare("SELECT inventory_id, current_stock, total_cost FROM inventory_metals WHERE material_type = ? AND stock_name = ? AND purity = ? AND unit_measurement = ? AND firm_id = ?");
        $stmt->bind_param("ssdsi", $materialType, $stockName, $purity, $unit, $firm_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        if ($existing) {
            $newStock = $existing['current_stock'] + $weight;
            $newTotalCost = $existing['total_cost'] + $finalAmount;
            $newCostPerUnit = $newTotalCost / $newStock;
            $stmt = $conn->prepare("UPDATE inventory_metals SET current_stock = ?, remaining_stock = ?, cost_price_per_gram = ?, total_cost = ?, last_updated = NOW() WHERE inventory_id = ?");
            $stmt->bind_param("dddsi", $newStock, $newStock, $newCostPerUnit, $newTotalCost, $existing['inventory_id']);
            $stmt->execute();
            $inventoryId = $existing['inventory_id'];
            $debugLog[] = "Updated existing inventory ID $inventoryId";
        } else {
            $stmt = $conn->prepare("INSERT INTO inventory_metals (material_type, stock_name, purity, current_stock, remaining_stock, cost_price_per_gram, unit_measurement, total_cost, source_type, minimum_stock_level, created_at, updated_at, firm_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
            $minStock = 10;
            $stmt->bind_param("ssdddsdsdii", $materialType, $stockName, $purity, $weight, $weight, $costPricePerGram, $unit, $finalAmount, $sourceType, $minStock, $firm_id);
            $stmt->execute();
            $inventoryId = $conn->insert_id;
            $debugLog[] = "Inserted new inventory ID $inventoryId";
        }
        // Insert into metal_purchases only for purchase
        if ($entryType === 'purchase') {
        $stmt = $conn->prepare("INSERT INTO metal_purchases (source_type, source_id, purchase_date, invoice_number, paid_amount, payment_status, payment_mode, transaction_reference, entry_type, firm_id, created_at, updated_at, inventory_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
            $entryTypeStr = 'purchase';
            $stmt->bind_param("sissdssssiii", $sourceType, $supplierId, $invoiceDate, $invoiceNumber, $paidAmount, $paymentStatus, $paymentMode, $transactionRef, $entryTypeStr, $firm_id, $inventoryId);
        $stmt->execute();
            $debugLog[] = "Inserted purchase record for inventory ID $inventoryId";
        }
        $conn->commit();
        $debugLog[] = "Transaction committed";
        error_log(json_encode($debugLog));
        echo json_encode(['success' => true, 'message' => 'Stock entry saved successfully!', 'debug' => $debugLog]);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $debugLog[] = 'Error saving stock entry: ' . $e->getMessage();
        error_log(json_encode($debugLog));
        echo json_encode(['success' => false, 'message' => 'Error saving stock entry: ' . $e->getMessage(), 'debug' => $debugLog]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Entry</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="../css/dashboard.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; }
    /* Sidebar, header, and utility styles copied from add-stock.php */
    .header-gradient { background: linear-gradient(to right, #4f46e5, #7c3aed); }
    .sidebar { /* ...sidebar styles... */ }
    /* Add more styles as needed for sidebar/header */
    * { animation: none !important; transition: none !important; }
    .section-card { background: linear-gradient(135deg, #f0f4ff 60%, #fceabb 100%); border-radius: 16px; box-shadow: 0 2px 8px #e5e7eb; margin-bottom: 22px; padding: 22px 22px 18px 22px; border: 1.5px solid #e0e7ff; }
    .section-title { font-size: 1.2rem; font-weight: 700; color: #4f46e5; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
    .form-row { display: flex; flex-wrap: wrap; gap: 18px; margin-bottom: 10px; }
    .form-col { flex: 1; min-width: 170px; position: relative; }
    label { font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
    .inv-input-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #6366f1;
      font-size: 1.18rem;
      pointer-events: none;
      opacity: 0.92;
      display: flex;
      align-items: center;
    }
    input, select { width: 100%; padding: 7px 8px 7px 40px; border: 1.2px solid #e5e7eb; border-radius: 7px; font-size: 0.98rem; background: #f9fafb; transition: border 0.2s, box-shadow 0.2s; line-height: 1.6; }
    input:focus, select:focus { border-color: #6366f1; outline: none; background: #fff; box-shadow: 0 0 0 2px #6366f133; }
    .btn { padding: 11px 22px; border-radius: 9px; font-weight: 700; font-size: 1rem; border: none; cursor: pointer; transition: background 0.2s, box-shadow 0.2s; }
    .btn-primary { background: linear-gradient(90deg, #6366f1 60%, #7c3aed 100%); color: #fff; box-shadow: 0 2px 8px #6366f122; }
    .btn-primary:hover { background: linear-gradient(90deg, #4f46e5 60%, #6366f1 100%); }
    .btn-danger { background: #ef4444; color: #fff; }
    .btn-danger:hover { background: #dc2626; }
    .readonly { background: #f3f4f6; color: #6b7280; }
    .required { color: #ef4444; }
    .hidden { display: none; }
    @media (max-width: 800px) { .form-row { flex-direction: column; gap: 8px; } .section-card { padding: 14px 8px; } }
    .stock-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 4px 24px 0 rgba(80, 112, 255, 0.08);
      border: 1.5px solid #e0e7ff;
      padding: 28px 20px 20px 20px;
      max-width: 480px;
      margin: 32px auto;
    }
    .stock-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: #4f46e5;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 22px;
    }
     .input-with-icon {
            position: relative;
        }
        .input-with-icon input,
        .input-with-icon select {
            padding-left: 2.25rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .input-with-icon i {
            position: absolute;
            left: 0.6rem;
            top: 50%;
            transform: translateY(-50%);
            color: #374151;
            font-size: 0.75rem;
            z-index: 10;
            font-weight: 600;
        }
        .section-card {
            transition: all 0.2s ease;
            border-radius: 0.75rem;
        }
       
        .toggle-radio {
            display: flex;
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 0.25rem;
            gap: 0.25rem;
        }
        .toggle-radio label {
            flex: 1;
            text-align: center;
            padding: 0.4rem 0.8rem;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .toggle-radio input[type="radio"] {
            display: none;
        }
        .toggle-radio input[type="radio"]:checked + label {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .gst-toggle {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }
        .gst-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .gst-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e5e7eb;
            transition: .3s;
            border-radius: 20px;
        }
        .gst-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .gst-slider {
            background-color: #10b981;
        }
        input:checked + .gst-slider:before {
            transform: translateX(20px);
        }
        .compact-input {
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 0.5rem;
            border: 1.5px solid #d1d5db;
            transition: border-color 0.2s ease;
            background-color: #ffffff;
        }
        .compact-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .readonly-input {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            color: #4b5563;
            cursor: not-allowed;
            font-weight: 700;
        }
    .form-row {
      display: flex;
      gap: 16px;
      margin-bottom: 14px;
    }
    @media (max-width: 600px) {
  </style>
</head>
<body class="relative">
    <!-- Sidebar Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>
    <!-- Mobile Toggle Button -->
    <button id="mobile-toggle" class="mobile-toggle">
        <i class="ri-menu-line text-xl"></i>
    </button>
    <!-- Sidebar (copied from add-stock.php, set Stock Entry as active) -->
    <aside id="sidebar" class="sidebar">
        <div class="logo-section">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="ri-vip-diamond-fill text-white text-xl"></i>
                </div>
                <div class="flex flex-col">
                    <span class="logo-text"><?php echo htmlspecialchars($userInfo['FirmName'] ?? ''); ?></span>
                    <span class="text-xs text-gray-400">Jewelry Store Management</span>
                </div>
            </div>
            <button id="collapse-toggle" class="text-gray-400 hover text-white transition-colors">
                <i class="ri-arrow-left-s-line text-xl"></i>
            </button>
        </div>
        <nav class="sidebar-nav">
            <div class="menu-category">Dashboard</div>
            <a href="dashborad.php" class="menu-item">
                <i class="ri-dashboard-line menu-icon"></i>
                <span class="menu-text">Overview</span>
                <div class="menu-tooltip">Store Overview</div>
            </a>
            <div class="menu-category">Inventory</div>
            <a href="add-stock.php" class="menu-item">
                <i class="ri-archive-line menu-icon"></i>
                <span class="menu-text">Add Stock</span>
                <div class="menu-tooltip">Manage Store Stock</div>
            </a>
            <a href="stock-entry.php" class="menu-item active">
                <i class="ri-edit-2-line menu-icon"></i>
                <span class="menu-text">Stock Entry</span>
                <div class="menu-tooltip">Stock Entry Form</div>
            </a>
            <a href="add-product.php" class="menu-item">
                <i class="ri-add-circle-line menu-icon"></i>
                <span class="menu-text">Add New Product</span>
                <div class="menu-tooltip">Add Jewelry to Stock</div>
            </a>
            <a href="inventory_reports.php" class="menu-item">
                <i class="ri-file-chart-line menu-icon"></i>
                <span class="menu-text">Stock Reports</span>
                <div class="menu-tooltip">View Stock Reports</div>
            </a>
            <div class="menu-category">Sales</div>
            <a href="sell.php" class="menu-item">
                <i class="ri-shopping-cart-line menu-icon"></i>
                <span class="menu-text">Sale</span>
                <div class="menu-tooltip">Manage Sales </div>
            </a>
            <a href="sales_reports.php" class="menu-item">
                <i class="ri-file-chart-line menu-icon"></i>
                <span class="menu-text">Sales Reports</span>
                <div class="menu-tooltip">View Sales Reports</div>
            </a>
            <div class="menu-category">Accounting</div>
            <a href="accounts.php" class="menu-item">
                <i class="ri-wallet-line menu-icon"></i>
                <span class="menu-text">Account Overview</span>
                <div class="menu-tooltip">Track Store Accounts</div>
            </a>
            <a href="transactions.php" class="menu-item">
                <i class="ri-money-dollar-circle-line menu-icon"></i>
                <span class="menu-text">Transactions</span>
                <div class="menu-tooltip">View Store Transactions</div>
            </a>
            <a href="expense_reports.php" class="menu-item">
                <i class="ri-file-chart-line menu-icon"></i>
                <span class="menu-text">Expenses</span>
                <div class="menu-tooltip">View Expenses</div>
            </a>
            <div class="menu-category">Customers</div>
            <a href="customer_list.php" class="menu-item">
                <i class="ri-group-line menu-icon"></i>
                <span class="menu-text">Customer List</span>
                <div class="menu-tooltip">Manage Customer Details</div>
            </a>
            <a href="customer_orders.php" class="menu-item">
                <i class="ri-file-list-line menu-icon"></i>
                <span class="menu-text">Customer Orders</span>
                <div class="menu-tooltip">View Customer Orders</div>
            </a>
            <div class="menu-category">Reports</div>
            <a href="daily_reports.php" class="menu-item">
                <i class="ri-calendar-check-line menu-icon"></i>
                <span class="menu-text">Daily Reports</span>
                <div class="menu-tooltip">View Daily Reports</div>
            </a>
            <a href="monthly_reports.php" class="menu-item">
                <i class="ri-calendar-line menu-icon"></i>
                <span class="menu-text">Monthly Reports</span>
                <div class="menu-tooltip">View Monthly Reports</div>
            </a>
            <div class="menu-category">Settings</div>
            <a href="settings.php" class="menu-item">
                <i class="ri-settings-3-line menu-icon"></i>
                <span class="menu-text">Store Settings</span>
                <div class="menu-tooltip">Manage Store Settings</div>
            </a>
            <a href="user_management.php" class="menu-item">
                <i class="ri-user-line menu-icon"></i>
                <span class="menu-text">User Management</span>
                <div class="menu-tooltip">Manage Store Users</div>
            </a>
        </nav>
    </aside>
    <!-- Main Content Area -->
    <main id="main-content" class="transition-all duration-300">
        <!-- Header (copied from add-stock.php) -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="flex flex-col md:flex-row md:items-center justify-between p-4 gap-4">
                <!-- Left Section -->
                <div class="flex items-center gap-4">
                    <button id="sidebar-toggle" class="p-2 hover:bg-gray-100 rounded-xl lg:hidden">
                        <i class="ri-menu-line text-xl"></i>
                    </button>
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <div id="logoToggle" class="flex-shrink-0 cursor-pointer">
                                <img src="../uploads/logo.png" alt="Logo" class="h-14 w-auto">
                            </div>
                        </div>
                        <div class="text-gray-600">
                            <span class="text-sm font-semibold"><?php echo htmlspecialchars($userInfo['FirmName'] ?? ''); ?></span>
                        </div>
                        <div class="relative flex-1 md:w-96">
                            <input type="text" placeholder="Search products, orders, customers..." class="header-search w-full pl-12 pr-4 py-2.5 text-sm focus:outline-none">
                            <i class="ri-search-line absolute left-4 top-3 text-gray-400"></i>
                        </div>
                    </div>
                </div>
                <!-- Right Section -->
                <div class="flex items-center gap-4">
                    <!-- Notifications -->
                    <div class="relative">
                        <button id="notifications-toggle" class="p-2 hover:bg-gray-100 rounded-xl relative">
                            <i class="ri-notification-3-line text-xl"></i>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>
                        <!-- Notifications Dropdown -->
                        <div id="notifications-dropdown" class="notification-dropdown">
                            <div class="p-4 border-b">
                                <h3 class="font-semibold">Notifications</h3>
                            </div>
                            <div class="max-h-[400px] overflow-y-auto">
                                <div class="p-4 border-b hover:bg-gray-50">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                            <i class="ri-shopping-bag-line text-green-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">New order received</p>
                                            <p class="text-xs text-gray-500 mt-1">Order #45678 needs processing</p>
                                            <p class="text-xs text-gray-400 mt-1">5 minutes ago</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-4 border-b hover:bg-gray-50">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                                            <i class="ri-alert-line text-amber-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">Low stock alert</p>
                                            <p class="text-xs text-gray-500 mt-1">Diamond rings (2mm) running low</p>
                                            <p class="text-xs text-gray-400 mt-1">1 hour ago</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 text-center">
                                <button class="text-sm text-amber-500 hover:text-amber-600">View All Notifications</button>
                            </div>
                        </div>
                    </div>
                    <!-- Quick Actions -->
                    <button class="p-2 hover:bg-gray-100 rounded-xl">
                        <i class="ri-add-circle-line text-xl"></i>
                    </button>
                    <!-- Profile -->
                    <div class="relative group">
                        <button class="flex items-center gap-3 p-2 hover:bg-gray-100 rounded-xl" onclick="toggleProfileMenu(event)">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center text-white font-semibold">
                                <?php echo strtoupper(substr($userInfo['Name'] ?? '', 0, 2)); ?>
                            </div>
                            <div class="hidden md:block text-left">
                                <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($userInfo['Name'] ?? ''); ?></p>
                                <p class="text-xs text-gray-500">Administrator</p>
                            </div>
                            <i class="ri-arrow-down-s-line text-gray-400"></i>
                        </button>
                        <!-- Enhanced Profile Dropdown Menu -->
                        <div id="profileMenu" class="hidden absolute right-0 mt-2 w-56 rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                            <div class="py-1">
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($userInfo['Name'] ?? ''); ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($userInfo['FirmName'] ?? ''); ?></p>
                                </div>
                                <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="ri-user-line w-5 h-5 mr-3 text-gray-400"></i>
                                    My Profile
                                </a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <i class="ri-logout-box-line w-5 h-5 mr-3 text-red-500"></i>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <div class="container mx-auto px-4 py-8">
          <div class="flex flex-col md:flex-row gap-8">
            <!-- Left: Stock Entry Form -->
          <div class="max-w-2xl mx-auto">
        <form id="inventoryMetalsForm" autocomplete="off" class="bg-white rounded-xl shadow-lg border border-gray-100 p-5">
            
            <!-- Entry Type Toggle -->
            <div class="mb-4">
                <div class="section-header text-gray-700">
                    <i class="fas fa-exchange-alt text-indigo-600"></i>
                    Entry Type
                </div>
                <div class="toggle-radio">
                    <input type="radio" name="entry_type" value="opening_stock" id="entryTypeOpening" checked>
                    <label for="entryTypeOpening">
                        <i class="fas fa-box-open mr-1"></i> Opening
                    </label>
                    <input type="radio" name="entry_type" value="purchase" id="entryTypePurchase">
                    <label for="entryTypePurchase">
                        <i class="fas fa-shopping-cart mr-1"></i> Purchase
                    </label>
                </div>
            </div>

            <!-- Source Section -->
            <div class="mb-4">
                <div id="customSourceDiv">
                    <div class="input-with-icon">
                        <i class="fas fa-info-circle"></i>
                        <input type="text" name="custom_source_info" id="customSourceInfo" 
                               class="compact-input w-full" placeholder="Source info (e.g. Initial Inventory, Migration)">
                    </div>
                </div>
                <div id="supplierDiv" style="display:none;">
                    <div class="input-with-icon">
                        <i class="fas fa-truck"></i>
                        <select name="supplier_id" id="supplierSelect" class="compact-input w-full">
                            <option value="">Select Supplier</option>
                            <!-- PHP suppliers options here -->
                        </select>
                    </div>
                </div>
            </div>

            <!-- Material Section -->
            <div class="section-card bg-gradient-to-r from-amber-100 to-yellow-100 border-2 border-amber-300 p-4 mb-4">
                <div class="section-header text-amber-800">
                    <i class="fas fa-gem"></i>
                    Material Details
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                    <div class="input-with-icon">
                        <i class="fas fa-layer-group"></i>
                        <select name="material_type" required class="compact-input w-full">
                            <option value="">Material</option>
                            <option value="Gold">Gold</option>
                            <option value="Silver">Silver</option>
                            <option value="Gems">Gems</option>
                            <option value="Stone">Stone</option>
                            <option value="Diamond">Diamond</option>
                            <option value="KD">KD</option>
                            <option value="Copper">Copper</option>
                        </select>
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-tag"></i>
                        <input type="text" name="stock_name" required class="compact-input w-full" placeholder="Stock Name">
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-balance-scale"></i>
                        <select name="unit_measurement" id="unitSelect" required class="compact-input w-full">
                            <option value="">Unit</option>
                            <option value="gms">gms</option>
                            <option value="carat">carat</option>
                            <option value="pcs">pcs</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-certificate"></i>
                        <input type="number" name="purity" step="0.01" min="0" max="100" 
                               placeholder="Purity %" required class="compact-input w-full">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="input-with-icon">
                        <i class="fas fa-weight"></i>
                        <input type="number" name="weight" id="weight" step="0.001" 
                               placeholder="Weight (g)" required class="compact-input w-full">
                    </div>
                    <div class="input-with-icon" id="customUnitDiv" style="display:none;">
                        <i class="fas fa-ruler"></i>
                        <input type="text" id="customUnitInput" name="custom_unit_measurement" 
                               placeholder="Custom unit" class="compact-input w-full">
                    </div>
                </div>
            </div>

            <!-- Pricing Section -->
            <div class="section-card bg-gradient-to-r from-emerald-100 to-green-100 border border-emerald-200 p-4 mb-4">
                <div class="section-header text-emerald-700">
                    <i class="fas fa-rupee-sign"></i>
                    Pricing Details
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-3">
                    <div class="input-with-icon">
                        <i class="fas fa-money-bill-wave"></i>
                        <input type="number" name="rate" id="rate" step="0.01" 
                               placeholder="Rate" required class="compact-input w-full">
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-percent"></i>
                        <input type="number" name="making_charges" id="makingCharges" step="0.01" 
                               placeholder="Making %" class="compact-input w-full">
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-calculator"></i>
                        <input type="number" name="cost_price_per_gram" id="costPricePerGram" step="0.01" 
                               placeholder="Cost/Gram" readonly class="compact-input w-full readonly-input">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 items-end">
                    <div class="input-with-icon">
                        <i class="fas fa-receipt"></i>
                        <input type="number" name="total_taxable_amount" id="totalTaxableAmount" step="0.01" 
                               placeholder="Taxable Amount" readonly class="compact-input w-full readonly-input">
                    </div>
                    <div class="flex items-center justify-center gap-2 bg-white rounded-lg p-2">
                        <label class="gst-toggle">
                            <input type="checkbox" id="gstToggle">
                            <span class="gst-slider"></span>
                        </label>
                        <span class="text-sm font-semibold text-gray-600">GST 3%</span>
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                        <input type="number" name="final_amount" id="finalAmount" step="0.01" 
                               placeholder="Final Amount" readonly class="compact-input w-full readonly-input">
                    </div>
                </div>
            </div>

            <!-- Purchase Details Section -->
            <div id="purchaseDetailsSection" class="section-card bg-gradient-to-r from-blue-100 to-indigo-100 border border-blue-200 p-4 mb-4" style="display:none;">
                <div class="section-header text-blue-700">
                    <i class="fas fa-file-invoice"></i>
                    Purchase Details
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="input-with-icon">
                        <i class="fas fa-hashtag"></i>
                        <input type="text" name="invoice_number" class="compact-input w-full" placeholder="Invoice Number">
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-calendar"></i>
                        <input type="date" name="invoice_date" class="compact-input w-full">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <div class="input-with-icon">
                        <i class="fas fa-money-check"></i>
                        <input type="number" name="paid_amount" step="0.01" class="compact-input w-full" placeholder="Paid Amount">
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-check-circle"></i>
                        <select name="payment_status" class="compact-input w-full">
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-credit-card"></i>
                        <select name="payment_mode" class="compact-input w-full">
                            <option value="">Payment Mode</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="upi">UPI</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="input-with-icon">
                    <i class="fas fa-reference"></i>
                    <input type="text" name="transaction_ref" class="compact-input w-full" placeholder="Transaction Reference (optional)">
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <button type="submit" class="px-8 py-3 rounded-full font-bold text-white bg-gradient-to-r from-indigo-500 to-purple-500 shadow-lg hover:from-indigo-600 hover:to-purple-600 transform hover:scale-105 transition-all duration-200 flex items-center gap-2">
                    <i class="fas fa-save"></i>
                    Save Entry
                </button>
            </div>
        </form>
    </div>
            <!-- Right: Inventory List & Stats -->
            <div class="md:w-1/2 w-full flex flex-col gap-6">
              <!-- Stats Card -->
              <div class="bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-xl p-4 shadow flex flex-col gap-2">
                <div class="flex items-center gap-4 mb-2">
                  <div class="text-2xl text-green-600"><i class="fa-solid fa-warehouse"></i></div>
                  <div class="font-bold text-lg text-green-800">Inventory Stats</div>
                </div>
                <div class="flex flex-wrap gap-4">
                  <div class="flex flex-col items-center">
                    <div class="text-xs text-gray-500">Total Stock</div>
                    <div class="font-bold text-lg text-indigo-700"><?php echo number_format($totalStock, 2); ?></div>
                  </div>
                  <div class="flex flex-col items-center">
                    <div class="text-xs text-gray-500">Total Value</div>
                    <div class="font-bold text-lg text-indigo-700">₹<?php echo number_format($totalValue, 2); ?></div>
                  </div>
                  <?php foreach ($statsByPurity as $purity => $stat): ?>
                    <div class="flex flex-col items-center">
                      <div class="text-xs text-gray-500">Purity <?php echo htmlspecialchars($purity); ?></div>
                      <div class="font-bold text-md text-blue-700"><?php echo number_format($stat['stock'], 2); ?>g</div>
                      <div class="text-xs text-gray-400">₹<?php echo number_format($stat['value'], 2); ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <!-- Inventory Table -->
              <div class="bg-white border border-indigo-100 rounded-xl shadow overflow-x-auto">
                <table class="min-w-full text-xs md:text-sm">
                  <thead class="bg-indigo-50">
                    <tr>
                      <th class="px-2 py-2 text-left font-bold text-indigo-700">Material</th>
                      <th class="px-2 py-2 text-left font-bold text-indigo-700">Stock Name</th>
                      <th class="px-2 py-2 text-left font-bold text-indigo-700">Purity</th>
                      <th class="px-2 py-2 text-left font-bold text-indigo-700">Unit</th>
                      <th class="px-2 py-2 text-right font-bold text-indigo-700">Stock</th>
                      <th class="px-2 py-2 text-right font-bold text-indigo-700">Cost/Gram</th>
                      <th class="px-2 py-2 text-right font-bold text-indigo-700">Value</th>
                      <th class="px-2 py-2 text-left font-bold text-indigo-700">Updated</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($inventoryMetals as $row): ?>
                      <tr class="border-b hover:bg-indigo-50/40">
                        <td class="px-2 py-1 font-semibold flex items-center gap-1">
                          <?php if ($row['material_type'] === 'Gold'): ?><span class="text-yellow-500"><i class="fa-solid fa-coins"></i></span><?php endif; ?>
                          <?php if ($row['material_type'] === 'Silver'): ?><span class="text-gray-400"><i class="fa-solid fa-gem"></i></span><?php endif; ?>
                          <?php if ($row['material_type'] === 'Diamond'): ?><span class="text-blue-400"><i class="fa-regular fa-gem"></i></span><?php endif; ?>
                          <?php echo htmlspecialchars($row['material_type']); ?>
                        </td>
                        <td class="px-2 py-1"><?php echo htmlspecialchars($row['stock_name']); ?></td>
                        <td class="px-2 py-1"><span class="inline-block rounded bg-blue-100 text-blue-700 px-2 py-0.5 text-xs font-bold"><?php echo htmlspecialchars($row['purity']); ?></span></td>
                        <td class="px-2 py-1"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                        <td class="px-2 py-1 text-right"><?php echo number_format($row['current_stock'], 2); ?></td>
                        <td class="px-2 py-1 text-right">₹<?php echo number_format($row['cost_price_per_gram'], 2); ?></td>
                        <td class="px-2 py-1 text-right">₹<?php echo number_format($row['total_cost'], 2); ?></td>
                        <td class="px-2 py-1 text-left text-gray-500"><?php echo date('d M Y', strtotime($row['last_updated'])); ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inventoryMetals)): ?>
                      <tr><td colspan="8" class="text-center text-gray-400 py-4">No stock found.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
       
    </main>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    // Sidebar and header JS from add-stock.php (sidebar toggling, profile menu, etc.)
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const mobileToggle = document.getElementById('mobile-toggle');
        const collapseToggle = document.getElementById('collapse-toggle');
        const logoToggle = document.getElementById('logoToggle');
        let sidebarState = 'full';
        function updateSidebarState(newState) {
            if (window.innerWidth <= 768) return;
            sidebar.classList.remove('collapsed', 'hidden');
            mainContent.classList.remove('collapsed', 'full');
            sidebarOverlay.classList.remove('show');
            switch (newState) {
                case 'full':
                    collapseToggle.innerHTML = '<i class="ri-arrow-left-s-line text-xl"></i>';
                    break;
                case 'collapsed':
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('collapsed');
                    collapseToggle.innerHTML = '<i class="ri-arrow-right-s-line text-xl"></i>';
                    break;
                case 'hidden':
                    sidebar.classList.add('hidden');
                    mainContent.classList.add('full');
                    collapseToggle.innerHTML = '<i class="ri-menu-line text-xl"></i>';
                    break;
            }
            sidebarState = newState;
        }
        function toggleSidebar() {
            if (window.innerWidth <= 768) {
                toggleMobileSidebar();
            } else {
                const states = ['full', 'collapsed', 'hidden'];
                const idx = states.indexOf(sidebarState);
                const next = states[(idx + 1) % states.length];
                updateSidebarState(next);
            }
        }
        function toggleMobileSidebar() {
            sidebar.classList.toggle('sidebar-expanded');
            sidebarOverlay.classList.toggle('show');
            document.body.classList.toggle('overflow-hidden');
            const icon = sidebar.classList.contains('sidebar-expanded')
                ? 'ri-close-line'
                : 'ri-menu-line';
            mobileToggle.innerHTML = `<i class="${icon} text-xl"></i>`;
        }
        mobileToggle?.addEventListener('click', toggleMobileSidebar);
        sidebarOverlay?.addEventListener('click', toggleMobileSidebar);
        collapseToggle?.addEventListener('click', toggleSidebar);
        logoToggle?.addEventListener('click', toggleSidebar);
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 &&
                sidebar.classList.contains('sidebar-expanded') &&
                !sidebar.contains(e.target) &&
                !mobileToggle.contains(e.target)
            ) {
                toggleMobileSidebar();
            }
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('sidebar-expanded');
                sidebarOverlay.classList.remove('show');
                document.body.classList.remove('overflow-hidden');
                mobileToggle.innerHTML = '<i class="ri-menu-line text-xl"></i>';
                updateSidebarState(sidebarState);
            } else {
                sidebar.classList.remove('collapsed', 'hidden');
                mainContent.classList.remove('collapsed', 'full');
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'b') toggleSidebar();
        });
        updateSidebarState('full');
        // Notifications Toggle
        const notificationsToggle = document.getElementById('notifications-toggle');
        const notificationsDropdown = document.getElementById('notifications-dropdown');
        notificationsToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('show');
        });
        document.addEventListener('click', (e) => {
            if (!notificationsDropdown.contains(e.target)) {
                notificationsDropdown.classList.remove('show');
            }
        });
        function toggleProfileMenu(event) {
            event.stopPropagation();
            const menu = document.getElementById('profileMenu');
            const isHidden = menu.classList.contains('hidden');
            document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
            menu.classList.toggle('hidden');
            if (isHidden) {
                menu.classList.add('animate-fadeIn');
            }
        }
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('profileMenu');
            const profileButton = event.target.closest('.group button');
            if (!profileButton && !menu.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });
        document.getElementById('profileMenu').addEventListener('click', function(event) {
            event.stopPropagation();
        });
        window.toggleProfileMenu = toggleProfileMenu;
        // Custom Unit Logic
            var unitSelect = document.getElementById('unitSelect');
            var customUnitInput = document.getElementById('customUnitInput');
            if(unitSelect && customUnitInput) {
                unitSelect.addEventListener('change', function() {
                    if (unitSelect.value === 'custom') {
                        customUnitInput.style.display = 'block';
                        customUnitInput.required = true;
                    } else {
                        customUnitInput.style.display = 'none';
                        customUnitInput.required = false;
                    }
                });
            if(unitSelect.closest('form')) {
                unitSelect.closest('form').addEventListener('submit', function(e) {
                        if(unitSelect.value === 'custom') {
                            if(customUnitInput.value.trim() !== '') {
                                unitSelect.value = customUnitInput.value.trim();
                            }
                        }
                    });
                }
            }
        // Pricing Section Calculations
        function updatePricing() {
            const rate = parseFloat(document.getElementById('rate').value) || 0;
            const makingCharges = parseFloat(document.getElementById('makingCharges').value) || 0;
            const weight = parseFloat(document.getElementById('weight').value) || 0;
            // Cost Price per Gram
            const costPerGram = rate + (rate * makingCharges / 100);
            document.getElementById('costPricePerGram').value = (rate ? costPerGram.toFixed(2) : '');
            // Total Taxable Amount
            const totalTaxable = costPerGram * weight;
            document.getElementById('totalTaxableAmount').value = (rate && weight ? totalTaxable.toFixed(2) : '');
            // GST
            const gstChecked = document.getElementById('gstToggle').checked;
            let finalAmount = totalTaxable;
            if (gstChecked) {
                finalAmount = totalTaxable + (totalTaxable * 0.03);
            }
            document.getElementById('finalAmount').value = (rate && weight ? finalAmount.toFixed(2) : '');
        }
        document.getElementById('rate').addEventListener('input', updatePricing);
        document.getElementById('makingCharges').addEventListener('input', updatePricing);
        document.getElementById('weight').addEventListener('input', updatePricing);
        document.getElementById('gstToggle').addEventListener('change', updatePricing);
        updatePricing();
        // Entry Type Toggle Logic
        const entryTypeOpening = document.getElementById('entryTypeOpening');
        const entryTypePurchase = document.getElementById('entryTypePurchase');
        const customSourceDiv = document.getElementById('customSourceDiv');
        const supplierDiv = document.getElementById('supplierDiv');
        const purchaseDetailsSection = document.getElementById('purchaseDetailsSection');
        function updateEntryTypeUI() {
            if (entryTypeOpening.checked) {
                if(customSourceDiv) customSourceDiv.style.display = '';
                if(supplierDiv) supplierDiv.style.display = 'none';
                if(purchaseDetailsSection) purchaseDetailsSection.style.display = 'none';
            } else {
                if(customSourceDiv) customSourceDiv.style.display = 'none';
                if(supplierDiv) supplierDiv.style.display = '';
                if(purchaseDetailsSection) purchaseDetailsSection.style.display = '';
            }
        }
        entryTypeOpening.addEventListener('change', updateEntryTypeUI);
        entryTypePurchase.addEventListener('change', updateEntryTypeUI);
        updateEntryTypeUI();
        // AJAX form submission for inventoryMetalsForm
        const form = document.getElementById('inventoryMetalsForm');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            // Add GST toggle value
            formData.append('gst', document.getElementById('gstToggle').checked ? 'true' : 'false');
            console.log('Submitting form data:', Object.fromEntries(formData.entries()));
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Server response:', data);
                if (data.success) {
                    showSuccessModal(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error('AJAX error:', err);
                alert('AJAX error: ' + err);
            });
        });
        // Success Modal
        function showSuccessModal(message) {
            let modal = document.getElementById('successModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'successModal';
                modal.innerHTML = `
                <div class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-40">
                    <div class="bg-white rounded-xl shadow-lg p-8 max-w-sm w-full text-center">
                        <div class="text-green-600 text-4xl mb-2"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="font-bold text-lg mb-2">${message}</div>
                        <button id="closeSuccessModal" class="mt-4 px-6 py-2 rounded-full bg-green-500 text-white font-bold">OK</button>
                    </div>
                </div>
                `;
                document.body.appendChild(modal);
            }
            document.getElementById('closeSuccessModal').onclick = function() {
                modal.remove();
                window.location.reload();
            };
        }
    });
    </script>
</body>
</html> 

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

$stats = fetchKarigarStats($conn, $firm_id);
$karigars = fetchKarigars($conn, $firm_id);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Karigar Management - <?php echo htmlspecialchars($userInfo['FirmName']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        .gradient-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fef2f2; color: #dc2626; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
    </style>
</head>
<body class="font-inter bg-gray-50 text-sm">
    <!-- Header -->
    <header class="gradient-header text-white sticky top-0 z-50">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="home.php" class="w-8 h-8 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-left text-white text-sm"></i>
                    </a>
                    <div>
                        <h1 class="text-lg font-bold">Karigar Management</h1>
                        <p class="text-xs text-white text-opacity-80"><?php echo strtoupper(htmlspecialchars($userInfo['FirmName'])); ?></p>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button onclick="openAddKarigarModal()" class="bg-orange-500 text-white px-3 py-2 rounded-lg text-xs font-semibold flex items-center">
                        <i class="fas fa-plus mr-1"></i>New Karigar
                    </button>
                    <button onclick="exportData()" class="bg-white bg-opacity-20 text-white px-3 py-2 rounded-lg text-xs font-semibold">
                        <i class="fas fa-download mr-1"></i>Export
                    </button>
                    <button onclick="refreshData()" class="bg-white bg-opacity-20 text-white px-3 py-2 rounded-lg text-xs font-semibold">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="px-4 py-4 pb-20">
        <!-- Stats Grid -->
        <div class="grid grid-cols-3 gap-3 mb-4">
            <!-- Total Karigars -->
            <div class="bg-white rounded-xl p-4 text-center card-hover">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                <p class="text-xs text-gray-600">Total Karigars</p>
            </div>

            <!-- Active Karigars -->
            <div class="bg-white rounded-xl p-4 text-center card-hover">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-user-check text-green-600"></i>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active']; ?></p>
                <p class="text-xs text-gray-600">Active</p>
            </div>

            <!-- Active Orders -->
            <div class="bg-white rounded-xl p-4 text-center card-hover">
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-clipboard-list text-purple-600"></i>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['orders']; ?></p>
                <p class="text-xs text-gray-600">Orders</p>
            </div>

            <!-- Completed This Month -->
            <div class="bg-white rounded-xl p-4 text-center card-hover">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed']; ?></p>
                <p class="text-xs text-gray-600">Completed</p>
            </div>

            <!-- Monthly Revenue -->
            <div class="bg-white rounded-xl p-4 text-center card-hover">
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-rupee-sign text-yellow-600"></i>
                </div>
                <p class="text-lg font-bold text-gray-800">₹<?php echo number_format($stats['revenue']); ?></p>
                <p class="text-xs text-gray-600">Revenue</p>
            </div>

            <!-- Pending Payments -->
            <div class="bg-white rounded-xl p-4 text-center card-hover">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending']; ?></p>
                <p class="text-xs text-gray-600">Pending</p>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="bg-white rounded-xl p-1 mb-4 flex space-x-1">
            <button onclick="filterKarigars('all')" class="filter-btn flex-1 py-2 px-3 rounded-lg text-xs font-medium bg-blue-500 text-white">
                <i class="fas fa-users mr-1"></i>All Karigars
            </button>
            <button onclick="filterKarigars('active')" class="filter-btn flex-1 py-2 px-3 rounded-lg text-xs font-medium text-gray-600">
                <i class="fas fa-user-check mr-1"></i>Active
            </button>
            <button onclick="filterKarigars('orders')" class="filter-btn flex-1 py-2 px-3 rounded-lg text-xs font-medium text-gray-600">
                <i class="fas fa-clipboard-list mr-1"></i>Orders
            </button>
            <button onclick="filterKarigars('payments')" class="filter-btn flex-1 py-2 px-3 rounded-lg text-xs font-medium text-gray-600">
                <i class="fas fa-rupee-sign mr-1"></i>Payments
            </button>
        </div>

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
    </main>

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
                <a href="inventory.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-boxes text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Inventory</span>
                </a>
                <a href="alerts.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-bell text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Alerts</span>
                </a>
                <a href="customers.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-gray-100 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-gray-500 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">Customers</span>
                </a>
                <a href="karigar_management.php" class="flex flex-col items-center py-2 px-3">
                    <div class="w-6 h-6 bg-orange-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-hammer text-white text-xs"></i>
                    </div>
                    <span class="text-xs text-orange-600 font-semibold mt-1">Karigars</span>
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
    </script>
</body>
</html>

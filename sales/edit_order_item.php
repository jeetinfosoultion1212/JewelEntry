<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    header("Location: login.php");
    exit();
}



// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Get user details
$userQuery = "
    SELECT 
        u.Name, u.Role, u.image_path, 
        f.FirmName, f.City, f.logo_path AS Logo 
    FROM Firm_Users u 
    JOIN Firm f ON f.id = u.FirmID 
    WHERE u.id = ?
";
$userStmt = $conn->prepare($userQuery);
if (!$userStmt) {
    die("User query prepare failed: {$conn->error}");
}
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();
$userStmt->close();

// Fetch order item details
$orderItemQuery = "
    SELECT joi.*, k.name AS karigar_name, 
           c.FirstName, c.LastName, c.phone_number AS customer_phone 
    FROM jewellery_order_items joi 
    LEFT JOIN karigars k ON joi.karigar_id = k.id 
    LEFT JOIN jewellery_customer_order o ON joi.order_id = o.id 
    LEFT JOIN customer c ON o.customer_id = c.id 
    WHERE joi.id = ? AND joi.firm_id = ?
";
$orderItemStmt = $conn->prepare($orderItemQuery);
if (!$orderItemStmt) {
    die("Order item query prepare failed: {$conn->error}");
}
$orderItemStmt->bind_param("ii", $order_item_id, $firm_id);
$orderItemStmt->execute();
$orderItem = $orderItemStmt->get_result()->fetch_assoc();
$orderItemStmt->close();

if (!$orderItem) {
    error_log("Order item not found: ID=$order_item_id, FirmID=$firm_id");
    header("Location: karigars.php");
    exit();
}

// Fetch all karigars for dropdown
$karigarsQuery = "
    SELECT id, name, phone_number 
    FROM karigars 
    WHERE firm_id = ? AND status = 'Active' 
    ORDER BY name
";
$karigarsStmt = $conn->prepare($karigarsQuery);
if (!$karigarsStmt) {
    die("Karigars query prepare failed: {$conn->error}");
}
$karigarsStmt->bind_param("i", $firm_id);
$karigarsStmt->execute();
$karigars = $karigarsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$karigarsStmt->close();

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Edit Order Item - <?php echo htmlspecialchars($userInfo['FirmName']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/home.css">
</head>
<body class="font-poppins bg-gray-100">
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
    <!-- Debug Information (Remove in production) -->
    <div class="bg-yellow-100 p-4 text-xs">
        <strong>Debug Info:</strong><br>
        Order Item ID: <?php echo $order_item_id; ?><br>
        Firm ID: <?php echo $firm_id; ?><br>
        User ID: <?php echo $user_id; ?><br>
        Order Item Found: <?php echo $orderItem ? 'Yes' : 'No'; ?><br>
        Karigars Count: <?php echo count($karigars); ?><br>
        <?php if ($orderItem): ?>
        Item Name: <?php echo htmlspecialchars($orderItem['item_name']); ?><br>
        Karigar Name: <?php echo htmlspecialchars($orderItem['karigar_name'] ?? 'None'); ?><br>
        Customer: <?php echo htmlspecialchars(($orderItem['FirstName'] ?? '') . ' ' . ($orderItem['LastName'] ?? '')); ?><br>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
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
                        <h1 class="text-sm font-bold text-gray-800">Edit Order Item</h1>
                        <p class="text-xs text-gray-600 font-medium"><?php echo strtoupper(htmlspecialchars($userInfo['FirmName'])); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p id="headerUserName" class="text-xs font-bold text-gray-800"><?php echo htmlspecialchars($userInfo['Name']); ?></p>
                        <p id="headerUserRole" class="text-xs text-purple-600 font-medium"><?php echo htmlspecialchars($userInfo['Role']); ?></p>
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
        <!-- Back Button -->
        <div class="mb-4">
            <a href="karigars.php" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Karigars
            </a>
        </div>

        <!-- Order Item Details -->
        <div class="bg-white rounded-xl shadow-sm mb-4">
            <div class="p-4 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800">Order Item Details</h2>
            </div>
            <div class="p-4">
                <form id="editOrderItemForm" class="space-y-4">
                    <input type="hidden" name="order_item_id" value="<?php echo $order_item_id; ?>">
                    
                    <!-- Customer Info -->
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">Customer Information</h3>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="text-gray-500">Name:</span>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars(($orderItem['FirstName'] ?? '') . ' ' . ($orderItem['LastName'] ?? '')); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Phone:</span>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($orderItem['customer_phone'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Item Details -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Item Name *</label>
                            <input type="text" name="item_name" value="<?php echo htmlspecialchars($orderItem['item_name']); ?>" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product Type</label>
                            <select name="product_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="Ring" <?php echo $orderItem['product_type'] === 'Ring' ? 'selected' : ''; ?>>Ring</option>
                                <option value="Necklace" <?php echo $orderItem['product_type'] === 'Necklace' ? 'selected' : ''; ?>>Necklace</option>
                                <option value="Earring" <?php echo $orderItem['product_type'] === 'Earring' ? 'selected' : ''; ?>>Earring</option>
                                <option value="Bracelet" <?php echo $orderItem['product_type'] === 'Bracelet' ? 'selected' : ''; ?>>Bracelet</option>
                                <option value="Pendant" <?php echo $orderItem['product_type'] === 'Pendant' ? 'selected' : ''; ?>>Pendant</option>
                                <option value="Chain" <?php echo $orderItem['product_type'] === 'Chain' ? 'selected' : ''; ?>>Chain</option>
                                <option value="Other" <?php echo $orderItem['product_type'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Metal Details -->
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Metal Type</label>
                            <select name="metal_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="Gold" <?php echo $orderItem['metal_type'] === 'Gold' ? 'selected' : ''; ?>>Gold</option>
                                <option value="Silver" <?php echo $orderItem['metal_type'] === 'Silver' ? 'selected' : ''; ?>>Silver</option>
                                <option value="Platinum" <?php echo $orderItem['metal_type'] === 'Platinum' ? 'selected' : ''; ?>>Platinum</option>
                                <option value="Diamond" <?php echo $orderItem['metal_type'] === 'Diamond' ? 'selected' : ''; ?>>Diamond</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Purity</label>
                            <input type="text" name="purity" value="<?php echo htmlspecialchars($orderItem['purity']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 22K, 24K">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gross Weight (g)</label>
                            <input type="number" name="gross_weight" value="<?php echo $orderItem['gross_weight']; ?>" step="0.01" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Less Weight (g)</label>
                            <input type="number" name="less_weight" value="<?php echo $orderItem['less_weight']; ?>" step="0.01" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Net Weight (g)</label>
                            <input type="number" name="net_weight" value="<?php echo $orderItem['net_weight']; ?>" step="0.01" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Karigar Assignment -->
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">Karigar Assignment</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Assign Karigar</label>
                                <select name="karigar_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Karigar</option>
                                    <?php foreach ($karigars as $karigar): ?>
                                    <option value="<?php echo $karigar['id']; ?>" <?php echo $orderItem['karigar_id'] == $karigar['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($karigar['name']); ?> (<?php echo htmlspecialchars($karigar['phone_number']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="item_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Pending" <?php echo $orderItem['item_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="In Progress" <?php echo $orderItem['item_status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo $orderItem['item_status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Delivered" <?php echo $orderItem['item_status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="Cancelled" <?php echo $orderItem['item_status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Design Reference -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Design Reference</label>
                        <input type="text" name="design_reference" value="<?php echo htmlspecialchars($orderItem['design_reference'] ?? ''); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Design reference or notes">
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-3 pt-4">
                        <button type="button" onclick="window.location.href='karigars.php'" 
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                            Update Order Item
                        </button>
                    </div>
                </form>
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

    <script>
        // Form submission
        document.getElementById('editOrderItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_order_item');

            fetch('ajax/karigar_operations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Order item updated successfully!');
                    window.location.href = 'karigars.php';
                } else {
                    alert('Error: ' + (data.message || 'Failed to update order item'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the order item.');
            });
        });

        // Auto-calculate net weight
        document.querySelector('input[name="gross_weight"]').addEventListener('input', calculateNetWeight);
        document.querySelector('input[name="less_weight"]').addEventListener('input', calculateNetWeight);

        function calculateNetWeight() {
            const grossWeight = parseFloat(document.querySelector('input[name="gross_weight"]').value) || 0;
            const lessWeight = parseFloat(document.querySelector('input[name="less_weight"]').value) || 0;
            const netWeight = grossWeight - lessWeight;
            document.querySelector('input[name="net_weight"]').value = netWeight.toFixed(2);
        }
    </script>
    <script type="module" src="js/home.js"></script>
</body>
</html>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Establish database connection
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

// Fetch firm configurations
$configQuery = "SELECT * FROM firm_configurations WHERE firm_id = ?";
$configStmt = $conn->prepare($configQuery);
$configStmt->bind_param("i", $firm_id);
$configStmt->execute();
$configResult = $configStmt->get_result();
$firmConfig = $configResult->fetch_assoc();

// Fetch expense categories
$categoriesQuery = "SELECT * FROM expense_categories WHERE firm_id = ? ORDER BY name";
$categoriesStmt = $conn->prepare($categoriesQuery);
$categoriesStmt->bind_param("i", $firm_id);
$categoriesStmt->execute();
$categoriesResult = $categoriesStmt->get_result();
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch recent expenses
$expensesQuery = "SELECT e.*, u.Name as created_by_name 
                 FROM expenses e 
                 JOIN Firm_Users u ON e.created_by = u.id 
                 WHERE e.firm_id = ? 
                 ORDER BY e.date DESC 
                 LIMIT 10";
$expensesStmt = $conn->prepare($expensesQuery);
$expensesStmt->bind_param("i", $firm_id);
$expensesStmt->execute();
$expensesResult = $expensesStmt->get_result();
$expenses = [];
while ($row = $expensesResult->fetch_assoc()) {
    $expenses[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Expenses - Jewelry Store</title>
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
                    <a href="home.php" class="w-9 h-9 gradient-gold rounded-xl flex items-center justify-center shadow-lg floating">
                        <i class="fas fa-arrow-left text-white text-sm"></i>
                    </a>
                    <div>
                        <h1 class="text-sm font-bold text-gray-800">Expenses</h1>
                        <p class="text-xs text-gray-600 font-medium">Track your business expenses</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p id="headerUserName" class="text-sm font-bold text-gray-800"><?php echo $userInfo['Name']; ?></p>
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

    <!-- Main Content -->
    <div class="px-3 pb-72">
        <!-- Add Expense Button -->
        <div class="py-4">
            <button id="addExpenseBtn" class="w-full py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition-colors duration-200 flex items-center justify-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add New Expense</span>
            </button>
        </div>

        <!-- Expense Summary -->
        <div class="bg-white rounded-xl p-4 shadow-sm mb-4">
            <h3 class="text-base font-semibold text-gray-800 mb-3">Expense Summary</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-sm text-gray-600">This Month</p>
                    <p class="text-xl font-bold text-gray-800">₹0.00</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-sm text-gray-600">Last Month</p>
                    <p class="text-xl font-bold text-gray-800">₹0.00</p>
                </div>
            </div>
        </div>

        <!-- Recent Expenses -->
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <h3 class="text-base font-semibold text-gray-800 mb-3">Recent Expenses</h3>
            <div class="space-y-3">
                <?php if (empty($expenses)): ?>
                    <p class="text-gray-500 text-center py-4">No expenses recorded yet</p>
                <?php else: ?>
                    <?php foreach ($expenses as $expense): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($expense['category']); ?></p>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($expense['description']); ?></p>
                                <p class="text-xs text-gray-500">By <?php echo htmlspecialchars($expense['created_by_name']); ?> on <?php echo date('d M Y', strtotime($expense['date'])); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-800">₹<?php echo number_format($expense['amount'], 2); ?></p>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($expense['payment_method']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div id="addExpenseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-4 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add New Expense</h3>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="expenseForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input type="number" step="0.01" name="amount" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select name="payment_method" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <?php 
                        $paymentMethods = explode(',', $firmConfig['default_payment_methods'] ?? 'Cash,Card,UPI,Bank Transfer');
                        foreach ($paymentMethods as $method): ?>
                            <option value="<?php echo htmlspecialchars(trim($method)); ?>"><?php echo htmlspecialchars(trim($method)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($firmConfig['require_expense_receipt'] ?? false): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Receipt</label>
                    <input type="file" name="receipt" accept="image/*,.pdf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                <?php endif; ?>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelExpense" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200">
                        Save Expense
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
                <a href="profile.php" data-nav-id="profile" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Profile</span>
                </a>
            </div>
        </div>
    </nav>

    <script type="module" src="js/home.js"></script>
    <script src="js/expenses.js"></script>
</body>
</html>

<?php
$conn->close();
?> 
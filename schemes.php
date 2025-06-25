<?php
// Enhanced Compact Schemes Management Page with Advanced Features

session_start();
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch firm_id from session
$firm_id = $_SESSION['firmID'];

// Get user details
$user_id = $_SESSION['id'];
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.City
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();

// Enhanced subscription status check
$subscriptionQuery = "SELECT fs.*, sp.name as plan_name, sp.price, sp.duration_in_days, sp.features 
                    FROM firm_subscriptions fs 
                    JOIN subscription_plans sp ON fs.plan_id = sp.id 
                    WHERE fs.firm_id = ? AND fs.is_active = 1 
                    ORDER BY fs.end_date DESC LIMIT 1";
$subStmt = $conn->prepare($subscriptionQuery);
$subStmt->bind_param("i", $firm_id);
$subStmt->execute();
$subscription = $subStmt->get_result()->fetch_assoc();

// Enhanced subscription status variables
$isTrialUser = false;
$isPremiumUser = false;
$isExpired = false;
$daysRemaining = 0;
$subscriptionStatus = 'none';

if ($subscription) {
    $endDate = new DateTime($subscription['end_date']);
    $now = new DateTime();
    $isExpired = $now > $endDate;
    $daysRemaining = max(0, $now->diff($endDate)->days);
    
    if ($subscription['is_trial']) {
        $isTrialUser = true;
        $subscriptionStatus = $isExpired ? 'trial_expired' : 'trial_active';
    } else {
        $isPremiumUser = true;
        $subscriptionStatus = $isExpired ? 'premium_expired' : 'premium_active';
    }
} else {
    $subscriptionStatus = 'no_subscription';
}

// Feature access control
$hasFeatureAccess = ($isPremiumUser && !$isExpired) || ($isTrialUser && !$isExpired);

if (!$hasFeatureAccess) {
    $_SESSION['error'] = 'You do not have access to this feature.';
    header("Location: home.php");
    exit();
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_desc';

// Build query with filters
$whereConditions = ["s.firm_id = ?"];
$params = [$firm_id];
$paramTypes = "i";

if (!empty($search)) {
    $whereConditions[] = "(s.scheme_name LIKE ? OR s.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $paramTypes .= "ss";
}

if (!empty($status_filter)) {
    $whereConditions[] = "s.status = ?";
    $params[] = $status_filter;
    $paramTypes .= "s";
}

$whereClause = implode(" AND ", $whereConditions);

// Determine sort order
$orderClause = "ORDER BY ";
switch ($sort_by) {
    case 'name_asc':
        $orderClause .= "s.scheme_name ASC";
        break;
    case 'name_desc':
        $orderClause .= "s.scheme_name DESC";
        break;
    case 'status_asc':
        $orderClause .= "s.status ASC";
        break;
    case 'participants_desc':
        $orderClause .= "participant_count DESC";
        break;
    case 'created_desc':
    default:
        $orderClause .= "s.created_at DESC";
        break;
}

// Fetch schemes with enhanced data
$schemes = [];
$schemesQuery = "SELECT s.*, 
                 COUNT(DISTINCT se.customer_id) as participant_count,
                 COUNT(se.id) as total_entries,
                 SUM(s.entry_fee) as total_revenue
                 FROM schemes s 
                 LEFT JOIN scheme_entries se ON s.id = se.scheme_id 
                 WHERE $whereClause 
                 GROUP BY s.id 
                 $orderClause";

$stmt = $conn->prepare($schemesQuery);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($scheme = $result->fetch_assoc()) {
    // Calculate days remaining
    $endDate = new DateTime($scheme['end_date']);
    $now = new DateTime();
    $daysLeft = $now < $endDate ? $now->diff($endDate)->days : 0;
    $scheme['days_remaining'] = $daysLeft;
    
    // Calculate completion percentage
    $totalDays = (new DateTime($scheme['start_date']))->diff(new DateTime($scheme['end_date']))->days;
    $elapsedDays = (new DateTime($scheme['start_date']))->diff($now)->days;
    $scheme['completion_percentage'] = $totalDays > 0 ? min(100, max(0, ($elapsedDays / $totalDays) * 100)) : 0;
    
    $schemes[] = $scheme;
}

// Get summary statistics
$totalSchemes = count($schemes);
$activeSchemes = count(array_filter($schemes, fn($s) => $s['status'] === 'active'));
$totalParticipants = array_sum(array_column($schemes, 'participant_count'));
$totalRevenue = array_sum(array_column($schemes, 'total_revenue'));

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Manage Schemes - <?php echo htmlspecialchars($userInfo['FirmName']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        'primary': {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
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
        .glass-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .glass-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        .gradient-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        }
        .gradient-gold {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .gradient-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .compact-card {
            transition: all 0.2s ease;
        }
        .compact-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        .status-active { @apply bg-green-100 text-green-800 border-green-200; }
        .status-draft { @apply bg-gray-100 text-gray-800 border-gray-200; }
        .status-completed { @apply bg-blue-100 text-blue-800 border-blue-200; }
        .status-cancelled { @apply bg-red-100 text-red-800 border-red-200; }
        .progress-bar {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
    </style>
</head>
<body class="font-poppins bg-gray-50 text-sm">
    <!-- Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="fixed top-16 right-3 bg-green-500 text-white p-3 rounded-lg shadow-lg z-[70] text-xs animate-bounce">
            <i class="fas fa-check mr-1"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="fixed top-16 right-3 bg-red-500 text-white p-3 rounded-lg shadow-lg z-[70] text-xs animate-bounce">
            <i class="fas fa-times mr-1"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Compact Glass Header -->
    <header class="glass-header sticky top-0 z-50 shadow-sm">
        <div class="px-3 py-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 gradient-gold rounded-lg flex items-center justify-center shadow-sm">
                        <i class="fas fa-gem text-white text-xs"></i>
                    </div>
                    <div>
                        <h1 class="text-sm font-bold text-gray-800 leading-tight"><?php echo $userInfo['FirmName']; ?></h1>
                        <p class="text-xs text-primary-600 font-medium leading-tight">Powered by JewelEntry</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p class="text-xs font-semibold text-gray-800 leading-tight"><?php echo $userInfo['Name']; ?></p>
                        <p class="text-xs text-primary-600 leading-tight"><?php echo $userInfo['Role']; ?></p>
                    </div>
                    <div class="w-8 h-8 gradient-primary rounded-lg flex items-center justify-center shadow-sm overflow-hidden">
                        <?php 
                        $defaultImage = 'public/uploads/user.png';
                        if (!empty($userInfo['image_path']) && file_exists($userInfo['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user text-white text-xs"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="px-3 pb-20 pt-3 space-y-3">
        <!-- Page Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-lg font-bold text-gray-800">Manage Schemes</h1>
                <p class="text-xs text-gray-600"><?php echo $totalSchemes; ?> schemes • <?php echo $activeSchemes; ?> active</p>
            </div>
            <button onclick="showCreateSchemeModal()" class="gradient-primary text-white px-4 py-2 rounded-lg text-xs font-semibold flex items-center shadow-lg hover:opacity-90 transition-all">
                <i class="fas fa-plus mr-1.5"></i>Create New Scheme
            </button>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-4 gap-2">
            <div class="bg-white rounded-lg p-3 shadow-sm text-center compact-card">
                <div class="w-8 h-8 gradient-primary rounded-lg flex items-center justify-center mx-auto mb-1">
                    <i class="fas fa-list text-white text-xs"></i>
                </div>
                <p class="text-lg font-bold text-gray-800"><?php echo $totalSchemes; ?></p>
                <p class="text-xs text-gray-600">Total</p>
            </div>
            <div class="bg-white rounded-lg p-3 shadow-sm text-center compact-card">
                <div class="w-8 h-8 gradient-success rounded-lg flex items-center justify-center mx-auto mb-1">
                    <i class="fas fa-play text-white text-xs"></i>
                </div>
                <p class="text-lg font-bold text-gray-800"><?php echo $activeSchemes; ?></p>
                <p class="text-xs text-gray-600">Active</p>
            </div>
            <div class="bg-white rounded-lg p-3 shadow-sm text-center compact-card">
                <div class="w-8 h-8 gradient-gold rounded-lg flex items-center justify-center mx-auto mb-1">
                    <i class="fas fa-users text-white text-xs"></i>
                </div>
                <p class="text-lg font-bold text-gray-800"><?php echo $totalParticipants; ?></p>
                <p class="text-xs text-gray-600">Participants</p>
            </div>
            <div class="bg-white rounded-lg p-3 shadow-sm text-center compact-card">
                <div class="w-8 h-8 bg-gradient-to-r from-green-400 to-green-600 rounded-lg flex items-center justify-center mx-auto mb-1">
                    <i class="fas fa-rupee-sign text-white text-xs"></i>
                </div>
                <p class="text-lg font-bold text-gray-800">₹<?php echo number_format($totalRevenue); ?></p>
                <p class="text-xs text-gray-600">Revenue</p>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white rounded-xl p-3 shadow-sm">
            <form method="GET" class="space-y-3">
                <div class="flex space-x-2">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search schemes..." 
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <button type="submit" class="gradient-primary text-white px-4 py-2 rounded-lg hover:opacity-90 transition-all">
                        <i class="fas fa-search text-xs"></i>
                    </button>
                </div>
                
                <div class="flex space-x-2">
                    <select name="status" class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <select name="sort" class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="created_desc" <?php echo $sort_by === 'created_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="participants_desc" <?php echo $sort_by === 'participants_desc' ? 'selected' : ''; ?>>Most Participants</option>
                        <option value="status_asc" <?php echo $sort_by === 'status_asc' ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>
                
                <?php if (!empty($search) || !empty($status_filter) || $sort_by !== 'created_desc'): ?>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-600"><?php echo count($schemes); ?> results found</span>
                    <a href="?" class="text-xs text-primary-600 hover:underline">Clear filters</a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Schemes List -->
        <?php if (!empty($schemes)): ?>
        <div class="space-y-3">
            <?php foreach ($schemes as $scheme): ?>
            <div class="bg-white rounded-xl p-3 shadow-sm compact-card">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800 text-sm leading-tight"><?php echo htmlspecialchars($scheme['scheme_name']); ?></h3>
                        <p class="text-xs text-gray-600 leading-tight">Type: <?php echo ucfirst(str_replace('_', ' ', $scheme['scheme_type'] ?? 'Lucky Draw')); ?></p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="status-<?php echo $scheme['status']; ?> px-2 py-0.5 rounded-full text-xs font-medium">
                            <?php echo ucfirst($scheme['status']); ?>
                        </span>
                        <div class="relative">
                            <button onclick="toggleSchemeMenu(<?php echo $scheme['id']; ?>)" class="text-gray-400 hover:text-gray-600 p-1">
                                <i class="fas fa-ellipsis-v text-xs"></i>
                            </button>
                            <div id="menu-<?php echo $scheme['id']; ?>" class="hidden absolute right-0 top-6 bg-white rounded-lg shadow-lg border z-10 min-w-32">
                                <a href="manage_scheme_entries.php?scheme_id=<?php echo $scheme['id']; ?>" class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-eye mr-2"></i>View Details
                                </a>
                                <button onclick="openEditSchemeModal(<?php echo $scheme['id']; ?>)" class="block w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-edit mr-2"></i>Edit Scheme
                                </button>
                                <button onclick="duplicateScheme(<?php echo $scheme['id']; ?>)" class="block w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-copy mr-2"></i>Duplicate
                                </button>
                                <hr class="my-1">
                                <button onclick="confirmDeleteScheme(<?php echo $scheme['id']; ?>)" class="block w-full text-left px-3 py-2 text-xs text-red-600 hover:bg-red-50">
                                    <i class="fas fa-trash mr-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div class="text-center">
                        <p class="text-lg font-bold text-primary-600"><?php echo $scheme['participant_count']; ?></p>
                        <p class="text-xs text-gray-600">Participants</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-gold-600"><?php echo $scheme['total_entries']; ?></p>
                        <p class="text-xs text-gray-600">Entries</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-green-600">₹<?php echo number_format($scheme['entry_fee'] * $scheme['total_entries']); ?></p>
                        <p class="text-xs text-gray-600">Revenue</p>
                    </div>
                </div>

                <!-- Progress Bar -->
                <?php if ($scheme['status'] === 'active'): ?>
                <div class="mb-3">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs text-gray-600">Progress</span>
                        <span class="text-xs text-gray-600"><?php echo round($scheme['completion_percentage']); ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="progress-bar h-1.5 rounded-full" style="width: <?php echo $scheme['completion_percentage']; ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center text-xs text-gray-500">
                    <span>
                        <i class="fas fa-calendar mr-1"></i>
                        <?php echo date('d M Y', strtotime($scheme['start_date'])); ?> - <?php echo date('d M Y', strtotime($scheme['end_date'])); ?>
                    </span>
                    <?php if ($scheme['status'] === 'active' && $scheme['days_remaining'] > 0): ?>
                    <span class="text-orange-600 font-medium">
                        <i class="fas fa-clock mr-1"></i><?php echo $scheme['days_remaining']; ?> days left
                    </span>
                    <?php endif; ?>
                </div>

                <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-100">
                    <div class="flex space-x-2">
                        <?php if ($scheme['entry_fee'] > 0): ?>
                        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-rupee-sign mr-1"></i>₹<?php echo number_format($scheme['entry_fee']); ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($scheme['min_purchase_amount'] > 0): ?>
                        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-shopping-cart mr-1"></i>Min ₹<?php echo number_format($scheme['min_purchase_amount']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <a href="manage_scheme_entries.php?scheme_id=<?php echo $scheme['id']; ?>" 
                       class="text-primary-600 hover:text-primary-700 text-xs font-semibold hover:underline">
                        Manage <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-xl p-8 shadow-sm text-center">
            <div class="w-16 h-16 gradient-primary rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-trophy text-white text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Schemes Found</h3>
            <p class="text-gray-600 text-sm mb-4">
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    No schemes match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                    You haven't created any lottery schemes yet. Create your first scheme to get started!
                <?php endif; ?>
            </p>
            <?php if (empty($search) && empty($status_filter)): ?>
            <button onclick="showCreateSchemeModal()" class="gradient-primary text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition-all">
                <i class="fas fa-plus mr-2"></i>Create Your First Scheme
            </button>
            <?php else: ?>
            <a href="?" class="text-primary-600 hover:underline text-sm">Clear all filters</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Create Scheme Modal -->
    <div id="createSchemeModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[90] hidden">
        <div class="bg-white rounded-xl p-4 w-full max-w-lg mx-3 max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Create New Scheme</h3>
                <button onclick="closeCreateSchemeModal()" class="text-gray-500 hover:text-gray-700 p-1">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Quick Templates -->
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Quick Templates</h4>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="selectTemplate('dhanteras')" class="template-card border-2 border-gray-200 rounded-lg p-2 hover:border-primary-500 transition-all text-left">
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-gem text-yellow-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="font-medium text-xs">Dhanteras</p>
                                <p class="text-xs text-gray-600">Festival Special</p>
                            </div>
                        </div>
                    </button>
                    <button onclick="selectTemplate('monthly')" class="template-card border-2 border-gray-200 rounded-lg p-2 hover:border-primary-500 transition-all text-left">
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-calendar text-blue-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="font-medium text-xs">Monthly</p>
                                <p class="text-xs text-gray-600">Regular Draw</p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <form id="schemeForm" class="space-y-3">
                <input type="hidden" name="scheme_type" value="lucky_draw">
                <input type="hidden" name="scheme_id" id="scheme_id">
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Scheme Name</label>
                        <input type="text" name="scheme_name" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Entry Fee (₹)</label>
                        <input type="number" name="entry_fee" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" min="0" step="0.01">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Min Purchase (₹)</label>
                        <input type="number" name="min_purchase_amount" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" min="0" step="0.01">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                </div>

                <!-- Compact Rewards Section -->
                <div class="border-t border-gray-200 pt-3">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="text-sm font-semibold text-gray-800">Prizes</h4>
                        <button type="button" onclick="addRewardField()" class="gradient-primary text-white px-3 py-1 rounded-lg text-xs hover:opacity-90">
                            <i class="fas fa-plus mr-1"></i>Add
                        </button>
                    </div>
                    <div id="rewardsContainer" class="space-y-2">
                        <!-- Reward fields will be added here -->
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-2 pt-3">
                    <button type="button" onclick="closeCreateSchemeModal()" class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="schemeSubmitBtn" class="gradient-primary text-white px-4 py-2 text-sm rounded-lg hover:opacity-90">
                        <i class="fas fa-save mr-1"></i><span id="schemeSubmitBtnText">Create</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fixed Bottom Navigation -->
    <nav class="glass-nav fixed bottom-0 left-0 right-0 shadow-lg z-40 border-t border-gray-200 bg-white/90 backdrop-blur">
        <div class="px-4 py-2">
            <div class="flex justify-around items-center">
                <a href="home.php" class="flex flex-col items-center space-y-0.5 py-1.5 px-2 rounded-lg transition-all duration-300 hover:bg-primary-50">
                    <div class="w-6 h-6 bg-gray-200 rounded-md flex items-center justify-center">
                        <i class="fas fa-home text-gray-600 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Home</span>
                </a>
                <a href="schemes.php" class="flex flex-col items-center space-y-0.5 py-1.5 px-2 rounded-lg transition-all duration-300 bg-primary-100">
                    <div class="w-6 h-6 gradient-primary rounded-md flex items-center justify-center">
                        <i class="fas fa-trophy text-white text-xs"></i>
                    </div>
                    <span class="text-xs text-primary-700 font-medium">Schemes</span>
                </a>
                <a href="add.php" class="flex flex-col items-center space-y-0.5 py-1.5 px-2 rounded-lg transition-all duration-300 hover:bg-primary-50">
                    <div class="w-6 h-6 bg-gray-200 rounded-md flex items-center justify-center">
                        <i class="fas fa-plus-circle text-gray-600 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Add</span>
                </a>
                <a href="alerts.php" class="flex flex-col items-center space-y-0.5 py-1.5 px-2 rounded-lg transition-all duration-300 hover:bg-primary-50">
                    <div class="w-6 h-6 bg-gray-200 rounded-md flex items-center justify-center">
                        <i class="fas fa-bell text-gray-600 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Alerts</span>
                </a>
                <a href="profile.php" class="flex flex-col items-center space-y-0.5 py-1.5 px-2 rounded-lg transition-all duration-300 hover:bg-primary-50">
                    <div class="w-6 h-6 bg-gray-200 rounded-md flex items-center justify-center">
                        <i class="fas fa-user-circle text-gray-600 text-xs"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Profile</span>
                </a>
            </div>
        </div>
    </nav>

    <script>
        let rewardCounter = 0;

        // Template selection
        function selectTemplate(type) {
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('border-primary-500');
            });
            event.currentTarget.classList.add('border-primary-500');
            
            const form = document.getElementById('schemeForm');
            const today = new Date();
            const endDate = new Date();
            endDate.setDate(today.getDate() + 30);

            switch(type) {
                case 'dhanteras':
                    form.querySelector('[name="scheme_name"]').value = 'Dhanteras Lucky Draw';
                    form.querySelector('[name="description"]').value = 'Celebrate Dhanteras with our special lucky draw!';
                    form.querySelector('[name="min_purchase_amount"]').value = '10000';
                    form.querySelector('[name="entry_fee"]').value = '100';
                    break;
                case 'monthly':
                    form.querySelector('[name="scheme_name"]').value = 'Monthly Lucky Draw';
                    form.querySelector('[name="description"]').value = 'Participate in our monthly lucky draw!';
                    form.querySelector('[name="min_purchase_amount"]').value = '5000';
                    form.querySelector('[name="entry_fee"]').value = '50';
                    break;
            }

            form.querySelector('[name="start_date"]').value = today.toISOString().split('T')[0];
            form.querySelector('[name="end_date"]').value = endDate.toISOString().split('T')[0];
        }

        // Modal functions
        function showCreateSchemeModal() {
            document.getElementById('createSchemeModal').classList.remove('hidden');
            // Reset form to create mode if not editing
            if (!document.getElementById('scheme_id').value) {
                document.getElementById('schemeSubmitBtnText').textContent = 'Create';
                document.getElementById('schemeSubmitBtn').classList.add('gradient-primary');
                document.getElementById('schemeSubmitBtn').classList.remove('bg-green-600');
            }
        }

        function closeCreateSchemeModal() {
            document.getElementById('createSchemeModal').classList.add('hidden');
            document.getElementById('schemeForm').reset();
            document.getElementById('rewardsContainer').innerHTML = '';
            rewardCounter = 0;
            document.getElementById('scheme_id').value = '';
            document.getElementById('schemeSubmitBtnText').textContent = 'Create';
            document.getElementById('schemeSubmitBtn').classList.add('gradient-primary');
            document.getElementById('schemeSubmitBtn').classList.remove('bg-green-600');
        }

        // Add reward field
        function addRewardField(reward = {}) {
            const container = document.getElementById('rewardsContainer');
            const rewardIndex = rewardCounter++;
            const rewardFieldHTML = `
                <div class="reward-item bg-gray-50 p-2 rounded-lg border">
                    <div class="flex justify-between items-center mb-2">
                        <h5 class="text-xs font-semibold text-gray-700">Prize #${rewardIndex + 1}</h5>
                        <button type="button" onclick="removeRewardField(this)" class="text-red-500 hover:text-red-700 text-xs">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <input type="number" name="rewards[${rewardIndex}][rank]" placeholder="Rank" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-primary-500 focus:border-primary-500" min="1" value="${reward.rank || ''}" required>
                        </div>
                        <div class="col-span-2">
                            <input type="text" name="rewards[${rewardIndex}][prize_name]" placeholder="Prize Name" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-primary-500 focus:border-primary-500" value="${reward.prize_name || ''}" required>
                        </div>
                        <div>
                            <input type="number" name="rewards[${rewardIndex}][quantity]" placeholder="Qty" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-primary-500 focus:border-primary-500" min="1" value="${reward.quantity || '1'}" required>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rewardFieldHTML);
        }

        function removeRewardField(button) {
            button.closest('.reward-item').remove();
        }

        // Toggle scheme menu
        function toggleSchemeMenu(schemeId) {
            const menu = document.getElementById(`menu-${schemeId}`);
            // Close all other menus
            document.querySelectorAll('[id^="menu-"]').forEach(m => {
                if (m.id !== `menu-${schemeId}`) m.classList.add('hidden');
            });
            menu.classList.toggle('hidden');
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[onclick^="toggleSchemeMenu"]')) {
                document.querySelectorAll('[id^="menu-"]').forEach(m => m.classList.add('hidden'));
            }
        });

        // Scheme actions
        function duplicateScheme(schemeId) {
            if (confirm('Create a copy of this scheme?')) {
                // Implementation for duplicating scheme
                alert('Duplicate functionality to be implemented');
            }
        }

        function confirmDeleteScheme(schemeId) {
            if (confirm('Are you sure you want to delete this scheme? This action cannot be undone.')) {
                // Implementation for deleting scheme
                alert('Delete functionality to be implemented');
            }
        }

        function openEditSchemeModal(schemeId) {
            document.getElementById('scheme_id').value = schemeId;
            document.getElementById('schemeSubmitBtnText').textContent = 'Update';
            document.getElementById('schemeSubmitBtn').classList.remove('gradient-primary');
            document.getElementById('schemeSubmitBtn').classList.add('bg-green-600');
            showCreateSchemeModal();
            fetch('api/fetch_scheme_details.php?scheme_id=' + schemeId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const scheme = data.scheme;
                        const rewards = data.rewards || [];
                        const form = document.getElementById('schemeForm');
                        form.querySelector('[name="scheme_name"]').value = scheme.scheme_name || '';
                        form.querySelector('[name="status"]').value = scheme.status || 'draft';
                        form.querySelector('[name="entry_fee"]').value = scheme.entry_fee || '';
                        form.querySelector('[name="min_purchase_amount"]').value = scheme.min_purchase_amount || '';
                        form.querySelector('[name="start_date"]').value = scheme.start_date || '';
                        form.querySelector('[name="end_date"]').value = scheme.end_date || '';
                        form.querySelector('[name="description"]').value = scheme.description || '';
                        document.getElementById('rewardsContainer').innerHTML = '';
                        rewardCounter = 0;
                        rewards.forEach(reward => addRewardField(reward));
                    } else {
                        alert(data.message || 'Failed to fetch scheme details');
                    }
                })
                .catch(err => {
                    alert('Error fetching scheme details');
                });
        }

        // Form submission
        document.getElementById('schemeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('firm_id', '<?php echo $firm_id; ?>');
            const rewards = [];
            document.querySelectorAll('#rewardsContainer .reward-item').forEach(rewardItem => {
                const rank = rewardItem.querySelector('[name*="[rank]"]').value;
                const prize_name = rewardItem.querySelector('[name*="[prize_name]"]').value;
                const quantity = rewardItem.querySelector('[name*="[quantity]"]').value;
                rewards.push({ rank, prize_name, quantity });
            });
            formData.append('rewards', JSON.stringify(rewards));
            const schemeId = formData.get('scheme_id');
            const url = schemeId ? 'api/update_scheme.php' : 'create_scheme.php';
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(schemeId ? 'Scheme updated successfully!' : 'Scheme created successfully!');
                    window.location.reload();
                } else {
                    alert(data.message || (schemeId ? 'Error updating scheme' : 'Error creating scheme'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(schemeId ? 'Error updating scheme' : 'Error creating scheme');
            });
        });

        // Auto-hide notifications
        setTimeout(() => {
            const notifications = document.querySelectorAll('.fixed.top-16.right-3');
            notifications.forEach(notification => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            });
        }, 4000);
    </script>
</body>
</html>
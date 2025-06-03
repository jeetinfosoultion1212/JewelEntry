<?php
// Basic structure for Schemes page

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

// Enhanced subscription status check (needed for header/nav feature access)
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

// Redirect if no access (adjust based on your feature access logic)
if (!$hasFeatureAccess) {
    $_SESSION['error'] = 'You do not have access to this feature.';
    header("Location: home.php");
    exit();
}

// --- Schemes Page Logic --- 

// Fetch all schemes for the firm
$schemes = [];
$allSchemesQuery = "SELECT * FROM schemes WHERE firm_id = ? ORDER BY CASE WHEN status = 'active' THEN 1 ELSE 2 END, start_date DESC";
$allSchemesStmt = $conn->prepare($allSchemesQuery);
$allSchemesStmt->bind_param("i", $firm_id);
$allSchemesStmt->execute();
$allSchemesResult = $allSchemesStmt->get_result();

while ($scheme = $allSchemesResult->fetch_assoc()) {
    // Fetch participation count for each scheme
    $participationCount = 0;
    if (isset($scheme['id'])) {
        $countQuery = "SELECT COUNT(DISTINCT customer_id) as participant_count FROM scheme_entries WHERE scheme_id = ?";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param("i", $scheme['id']);
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        $participationCount = $countResult['participant_count'] ?? 0;
    }
    $scheme['participant_count'] = $participationCount;
    $schemes[] = $scheme;
}


// Close the database connection
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Manage Schemes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/home.css">
</head>
<body class="font-poppins bg-gray-100">
    <!-- Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="fixed top-4 right-4 bg-green-500 text-white p-3 rounded shadow-lg z-[70]">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="fixed top-4 right-4 bg-red-500 text-white p-3 rounded shadow-lg z-[70]">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <header class="header-glass sticky top-0 z-50 shadow-md">
        <div class="px-3 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-9 h-9 gradient-gold rounded-xl flex items-center justify-center shadow-lg floating">
                        <i class="fas fa-gem text-white text-sm"></i>
                    </div>
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
                    <?php if ($hasFeatureAccess): ?>
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
                    <?php else: ?>
                    <div class="w-9 h-9 gradient-purple rounded-xl flex items-center justify-center shadow-lg overflow-hidden cursor-pointer relative transition-transform duration-200" onclick="showFeatureLockedModal()">
                        <i class="fas fa-lock text-white text-sm"></i>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="px-3 pb-24">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-2xl font-bold text-gray-800">Manage Schemes</h1>
            <button onclick="showCreateSchemeModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center">
                <i class="fas fa-plus mr-2"></i>Create New Scheme
            </button>
        </div>
        <!-- Schemes List -->
        <div class="mt-4">
            <?php if (!empty($schemes)): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($schemes as $scheme): ?>
                            <li class="p-4 hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-base font-bold text-gray-800"><?php echo htmlspecialchars($scheme['scheme_name']); ?></h3>
                                        <p class="text-sm text-gray-600 mt-0.5">Type: <?php echo ucfirst(htmlspecialchars(str_replace('_', ' ', $scheme['scheme_type']))); ?></p>
                                        <p class="text-sm text-gray-600 mt-0.5">Status: 
                                            <span class="font-semibold <?php 
                                                if ($scheme['status'] === 'active') echo 'text-green-600';
                                                elseif ($scheme['status'] === 'draft') echo 'text-gray-500';
                                                elseif ($scheme['status'] === 'completed') echo 'text-blue-600';
                                                elseif ($scheme['status'] === 'cancelled') echo 'text-red-600';
                                            ?>">
                                                <?php echo ucfirst(htmlspecialchars($scheme['status'])); ?>
                                            </span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Dates: <?php echo date('d M Y', strtotime($scheme['start_date'])); ?> - <?php echo date('d M Y', strtotime($scheme['end_date'])); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-purple-600 mb-0.5"><?php echo $scheme['participant_count']; ?></p>
                                        <p class="text-xs text-gray-500">Participants</p>
                                        <div class="flex justify-end space-x-2 mt-2">
                                             <a href="manage_scheme_entries.php?scheme_id=<?php echo $scheme['id']; ?>" class="text-blue-500 text-sm hover:underline">Manage</a>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <p class="text-gray-600 font-medium">No schemes found for this firm.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feature Locked Modal -->
    <div id="featureLockedModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-[80] hidden">
        <div class="bg-white rounded-xl p-6 shadow-2xl w-full max-w-md mx-4">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lock text-red-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Feature Locked</h3>
                <p class="text-gray-600 mb-4">
                    <?php if ($subscriptionStatus === 'trial_expired'): ?>
                        Your trial has expired. Upgrade to a premium plan to access this feature.
                    <?php elseif ($subscriptionStatus === 'premium_expired'): ?>
                        Your subscription has expired. Please renew to continue using this feature.
                    <?php else: ?>
                        This feature requires an active subscription. Please upgrade to access it.
                    <?php endif; ?>
                </p>
                <div class="flex flex-col space-y-3">
                    <button onclick="showUpgradeModal(); closeFeatureLockedModal();" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg font-medium transition-colors">
                        <i class="fas fa-star mr-2"></i>View Plans & Upgrade
                    </button>
                    <a href="https://wa.me/919810359334" target="_blank" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg font-medium transition-colors text-center">
                        <i class="fab fa-whatsapp mr-2"></i>Contact Support
                    </a>
                    <button onclick="closeFeatureLockedModal()" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-lg font-medium transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upgrade Modal (Placeholder - content will be added as needed) -->
     <div id="upgradeModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-[80] hidden">
         <div class="bg-white rounded-xl p-6 shadow-2xl w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto">
             <div class="flex justify-between items-center mb-6">
                 <div>
                     <h3 class="text-2xl font-bold text-gray-800">Choose Your Perfect Plan</h3>
                     <p class="text-gray-600 mt-1">Select the plan that best fits your business needs</p>
                 </div>
                 <button onclick="closeUpgradeModal()" class="text-gray-500 hover:text-gray-700">
                     <i class="fas fa-times text-xl"></i>
                 </button>
             </div>
             <p>Plan details will load here...</p>
             <!-- Plan details can be loaded via AJAX or rendered here if simple -->
         </div>
     </div>

    <!-- Edit Scheme Modal -->
    <div id="editSchemeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[90] hidden">
        <div class="bg-white rounded-xl p-4 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Edit Scheme</h3>
                <button onclick="closeEditSchemeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="editSchemeForm" class="space-y-2">
                <input type="hidden" id="editSchemeId" name="scheme_id" value="">
                <input type="hidden" name="_method" value="PUT"> <!-- To simulate PUT request -->
                
                <!-- Main Form Fields - Similar to Create Modal -->
                <div class="grid grid-cols-2 gap-2">
                    <!-- Left Column -->
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Scheme Name</label>
                            <input type="text" name="scheme_name" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Status</label>
                            <select name="status" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Start Date</label>
                            <input type="date" name="start_date" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">End Date</label>
                            <input type="date" name="end_date" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Min. Purchase (₹)</label>
                            <input type="number" name="min_purchase_amount" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" min="0" step="0.01">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Entry Fee (₹)</label>
                            <input type="number" name="entry_fee" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" min="0" step="0.01">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Description</label>
                            <textarea name="description" rows="2" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Terms & Conditions</label>
                            <textarea name="terms_conditions" rows="2" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Rewards Section - Similar to Create Modal -->
                <div class="space-y-3 border-t border-gray-200 pt-3 mt-3">
                    <h4 class="text-sm font-bold text-gray-800">Scheme Rewards</h4>
                    <div id="editRewardsContainer" class="space-y-2">
                        <!-- Existing and new reward fields will be added here by JavaScript -->
                    </div>
                    <button type="button" onclick="addEditRewardField()" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-plus mr-1"></i> Add Reward
                    </button>
                </div>

                <!-- Auto-entry Options - Similar to Create Modal -->
                <div class="flex items-center space-x-4 pt-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="auto_entry_on_purchase" id="editAutoEntryOnPurchase" class="w-3.5 h-3.5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <label for="editAutoEntryOnPurchase" class="ml-2 text-xs text-gray-700">Auto-entry on purchase</label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="auto_entry_on_registration" id="editAutoEntryOnRegistration" class="w-3.5 h-3.5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <label for="editAutoEntryOnRegistration" class="ml-2 text-xs text-gray-700">Auto-entry on registration</label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-2 pt-3">
                    <button type="button" onclick="closeEditSchemeModal()" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Scheme Modal -->
    <div id="createSchemeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[90] hidden">
        <div class="bg-white rounded-xl p-4 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Create New Scheme</h3>
                <button onclick="closeCreateSchemeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="schemeForm" class="space-y-2">
                <input type="hidden" id="schemeType" name="scheme_type" value="">
                
                <!-- Template Selection -->
                <div class="grid grid-cols-2 gap-2 mb-4">
                    <div onclick="selectTemplate('dhanteras')" class="template-card border-2 border-gray-200 rounded-lg p-2 cursor-pointer hover:border-purple-500 transition-colors">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-gem text-yellow-600 text-sm"></i>
                            </div>
                            <div>
                                <h5 class="font-medium text-gray-800 text-sm">Dhanteras Special</h5>
                                <p class="text-[10px] text-gray-600">Festival of Wealth</p>
                            </div>
                        </div>
                    </div>
                    <div onclick="selectTemplate('rakhi')" class="template-card border-2 border-gray-200 rounded-lg p-2 cursor-pointer hover:border-purple-500 transition-colors">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-pink-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-heart text-pink-600 text-sm"></i>
                            </div>
                            <div>
                                <h5 class="font-medium text-gray-800 text-sm">Rakhi Special</h5>
                                <p class="text-[10px] text-gray-600">Brother-Sister Festival</p>
                            </div>
                        </div>
                    </div>
                    <div onclick="selectTemplate('chhath')" class="template-card border-2 border-gray-200 rounded-lg p-2 cursor-pointer hover:border-purple-500 transition-colors">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-sun text-orange-600 text-sm"></i>
                            </div>
                            <div>
                                <h5 class="font-medium text-gray-800 text-sm">Chhath Puja</h5>
                                <p class="text-[10px] text-gray-600">Sun Worship Festival</p>
                            </div>
                        </div>
                    </div>
                    <div onclick="selectTemplate('holi')" class="template-card border-2 border-gray-200 rounded-lg p-2 cursor-pointer hover:border-purple-500 transition-colors">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-palette text-green-600 text-sm"></i>
                            </div>
                            <div>
                                <h5 class="font-medium text-gray-800 text-sm">Holi Special</h5>
                                <p class="text-[10px] text-gray-600">Festival of Colors</p>
                            </div>
                        </div>
                    </div>
                    <div onclick="selectTemplate('monthly')" class="template-card border-2 border-gray-200 rounded-lg p-2 cursor-pointer hover:border-purple-500 transition-colors">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-calendar-alt text-blue-600 text-sm"></i>
                            </div>
                            <div>
                                <h5 class="font-medium text-gray-800 text-sm">Monthly Special</h5>
                                <p class="text-[10px] text-gray-600">Regular Monthly Draw</p>
                            </div>
                        </div>
                    </div>
                    <div onclick="selectTemplate('custom')" class="template-card border-2 border-gray-200 rounded-lg p-2 cursor-pointer hover:border-purple-500 transition-colors">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-plus text-purple-600 text-sm"></i>
                            </div>
                            <div>
                                <h5 class="font-medium text-gray-800 text-sm">Custom Theme</h5>
                                <p class="text-[10px] text-gray-600">Create Your Own</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Form Fields -->
                <div class="grid grid-cols-2 gap-2">
                    <!-- Left Column -->
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Scheme Name</label>
                            <input type="text" name="scheme_name" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Status</label>
                            <select name="status" class="w-full px-2 py-1.5 text-sm border borderborder-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Start Date</label>
                            <input type="date" name="start_date" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">End Date</label>
                            <input type="date" name="end_date" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Min. Purchase (₹)</label>
                            <input type="number" name="min_purchase_amount" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" min="0" step="0.01">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Entry Fee (₹)</label>
                            <input type="number" name="entry_fee" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" min="0" step="0.01">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Description</label>
                            <textarea name="description" rows="2" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Terms & Conditions</label>
                            <textarea name="terms_conditions" rows="2" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Rewards Section -->
                <div class="space-y-3 border-t border-gray-200 pt-3 mt-3">
                    <h4 class="text-sm font-bold text-gray-800">Scheme Rewards</h4>
                    <div id="rewardsContainer" class="space-y-2">
                        <!-- Reward fields will be added here by JavaScript -->
                    </div>
                    <button type="button" onclick="addRewardField()" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-plus mr-1"></i> Add Reward
                    </button>
                </div>

                <!-- Auto-entry Options -->
                <div class="flex items-center space-x-4 pt-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="auto_entry_on_purchase" id="autoEntryOnPurchase" class="w-3.5 h-3.5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <label for="autoEntryOnPurchase" class="ml-2 text-xs text-gray-700">Auto-entry on purchase</label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="auto_entry_on_registration" id="autoEntryOnRegistration" class="w-3.5 h-3.5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <label for="autoEntryOnRegistration" class="ml-2 text-xs text-gray-700">Auto-entry on registration</label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-2 pt-3">
                    <button type="button" onclick="closeCreateSchemeModal()" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700">Create Scheme</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enhanced Bottom Navigation -->
    <nav class="bottom-nav fixed bottom-0 left-0 right-0 shadow-xl">
        <div class="px-4 py-2">
            <div class="flex justify-around">
                <a href="home.php" data-nav-id="home" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Home</span>
                </a>
                <?php if ($hasFeatureAccess): ?>
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
                <?php else: ?>
                <button onclick="showFeatureLockedModal()" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Search</span>
                </button>
                <button onclick="showFeatureLockedModal()" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Add</span>
                </button>
                <button onclick="showFeatureLockedModal()" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Alerts</span>
                </button>
                <button onclick="showFeatureLockedModal()" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Profile</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <script type="module" src="js/home.js"></script>
    <script>
        window.hasFeatureAccess = <?php echo $hasFeatureAccess ? 'true' : 'false'; ?>;
        window.subscriptionStatus = '<?php echo $subscriptionStatus; ?>';
        window.isTrialUser = <?php echo $isTrialUser ? 'true' : 'false'; ?>;
        window.isPremiumUser = <?php echo $isPremiumUser ? 'true' : 'false'; ?>;
        window.isExpired = <?php echo $isExpired ? 'true' : 'false'; ?>;
        window.daysRemaining = <?php echo $daysRemaining; ?>;

        // Global functions for modals
        function showFeatureLockedModal() {
            document.getElementById('featureLockedModal').classList.remove('hidden');
        }

        function closeFeatureLockedModal() {
            document.getElementById('featureLockedModal').classList.add('hidden');
        }

        function showUpgradeModal() {
            document.getElementById('upgradeModal').classList.remove('hidden');
            // You might want to load plan details here via AJAX if they are complex
        }

        function closeUpgradeModal() {
            document.getElementById('upgradeModal').classList.add('hidden');
        }

        // Add event listener to close modal on outside click
         window.onclick = function(event) {
             const lockedModal = document.getElementById('featureLockedModal');
             const upgradeModal = document.getElementById('upgradeModal');
             const createModal = document.getElementById('createSchemeModal');
             const editModal = document.getElementById('editSchemeModal');
             if (event.target === lockedModal) {
                 closeFeatureLockedModal();
             } else if (event.target === upgradeModal) {
                 closeUpgradeModal();
             } else if (event.target === createModal) {
                 closeCreateSchemeModal();
             } else if (event.target === editModal) {
                 closeEditSchemeModal();
             }
         };

        // Template selection
        function selectTemplate(type) {
            document.getElementById('schemeType').value = 'lucky_draw'; // Always set as lucky_draw
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('border-purple-500');
            });
            event.currentTarget.classList.add('border-purple-500');
            
            // Pre-fill form based on template
            const form = document.getElementById('schemeForm');
            const today = new Date();
            const endDate = new Date();
            endDate.setDate(today.getDate() + 30); // Default 30 days duration

            switch(type) {
                case 'dhanteras':
                    form.querySelector('[name="scheme_name"]').value = 'Dhanteras Lucky Draw';
                    form.querySelector('[name="description"]').value = 'Celebrate Dhanteras with our special lucky draw! Purchase jewelry worth ₹10,000 or more to get an entry.';
                    form.querySelector('[name="terms_conditions"]').value = '1. Minimum purchase of ₹10,000 required\n2. One entry per customer\n3. Winner will be selected randomly\n4. Valid only during Dhanteras period';
                    form.querySelector('[name="min_purchase_amount"]').value = '10000';
                    form.querySelector('[name="auto_entry_on_purchase"]').checked = true;
                    break;
                case 'rakhi':
                    form.querySelector('[name="scheme_name"]').value = 'Rakhi Special Lucky Draw';
                    form.querySelector('[name="description"]').value = 'Make this Rakhi special with our exclusive lucky draw! Buy any Rakhi gift set to participate.';
                    form.querySelector('[name="terms_conditions"]').value = '1. Purchase any Rakhi gift set\n2. One entry per customer\n3. Winner will be selected randomly\n4. Valid only during Rakhi period';
                    form.querySelector('[name="min_purchase_amount"]').value = '5000';
                    form.querySelector('[name="auto_entry_on_purchase"]').checked = true;
                    break;
                case 'chhath':
                    form.querySelector('[name="scheme_name"]').value = 'Chhath Puja Lucky Draw';
                    form.querySelector('[name="description"]').value = 'Celebrate Chhath Puja with our special lucky draw! Get an entry with every purchase.';
                    form.querySelector('[name="terms_conditions"]').value = '1. Any purchase amount qualifies\n2. One entry per customer\n3. Winner will be selected randomly\n4. Valid only during Chhath Puja period';
                    form.querySelector('[name="min_purchase_amount"]').value = '0';
                    form.querySelector('[name="auto_entry_on_purchase"]').checked = true;
                    break;
                case 'holi':
                    form.querySelector('[name="scheme_name"]').value = 'Holi Special Lucky Draw';
                    form.querySelector('[name="description"]').value = 'Add colors to your Holi with our special lucky draw! Purchase jewelry worth ₹15,000 or more to participate.';
                    form.querySelector('[name="terms_conditions"]').value = '1. Minimum purchase of ₹15,000 required\n2. One entry per customer\n3. Winner will be selected randomly\n4. Valid only during Holi period';
                    form.querySelector('[name="min_purchase_amount"]').value = '15000';
                    form.querySelector('[name="auto_entry_on_purchase"]').checked = true;
                    break;
                case 'monthly':
                    form.querySelector('[name="scheme_name"]').value = 'Monthly Lucky Draw';
                    form.querySelector('[name="description"]').value = 'Participate in our monthly lucky draw! Every purchase gives you a chance to win exciting prizes.';
                    form.querySelector('[name="terms_conditions"]').value = '1. Any purchase amount qualifies\n2. One entry per customer\n3. Winner will be selected randomly\n4. Draw will be held at the end of each month';
                    form.querySelector('[name="min_purchase_amount"]').value = '0';
                    form.querySelector('[name="auto_entry_on_purchase"]').checked = true;
                    break;
                case 'custom':
                    // Clear form for custom scheme
                    form.reset();
                    form.querySelector('[name="scheme_type"]').value = 'lucky_draw';
                    break;
            }

            // Set default dates
            form.querySelector('[name="start_date"]').value = today.toISOString().split('T')[0];
            form.querySelector('[name="end_date"]').value = endDate.toISOString().split('T')[0];
        }

        // Modal functions
        function showCreateSchemeModal() {
            document.getElementById('createSchemeModal').classList.remove('hidden');
        }

        function closeCreateSchemeModal() {
            document.getElementById('createSchemeModal').classList.add('hidden');
            document.getElementById('schemeForm').reset();
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('border-purple-500');
            });
            // Clear rewards container when closing create modal
            document.getElementById('rewardsContainer').innerHTML = '';
        }

        // Form submission
        document.getElementById('schemeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Add firm_id to form data
            formData.append('firm_id', '<?php echo $firm_id; ?>');

            // Collect reward data
            const rewards = [];
            document.querySelectorAll('#rewardsContainer .reward-item').forEach(rewardItem => {
                const rank = rewardItem.querySelector('[name^="rewards"][name$="[rank]"]').value;
                const prize_name = rewardItem.querySelector('[name^="rewards"][name$="[prize_name]"]').value;
                const quantity = rewardItem.querySelector('[name^="rewards"][name$="[quantity]"]').value;
                const description = rewardItem.querySelector('[name^="rewards"][name$="[description]"]').value;
                rewards.push({ rank, prize_name, quantity, description });
            });

            // Append rewards as a JSON string
            formData.append('rewards', JSON.stringify(rewards));

            fetch('create_scheme.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message and reload page
                    alert('Scheme created successfully!');
                    window.location.reload();
                } else {
                    alert(data.message || 'Error creating scheme');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating scheme');
            });
        });

        // Function to add a new reward input field set
        function addRewardField() {
            const container = document.getElementById('rewardsContainer');
            const rewardIndex = container.children.length;
            const rewardFieldHTML = `
                <div class="reward-item p-2 border border-gray-200 rounded-lg space-y-2">
                    <div class="flex justify-between items-center">
                         <h5 class="text-xs font-semibold text-gray-700">Reward #\${rewardIndex + 1}</h5>
                         <button type="button" onclick="removeRewardField(this)" class="text-red-500 hover:text-red-700 text-sm"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Rank</label>
                            <input type="number" name="rewards[\${rewardIndex}][rank]" class="w-full px-2 py-1 text-xs border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" min="1" required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Prize Name</label>
                            <input type="text" name="rewards[\${rewardIndex}][prize_name]" class="w-full px-2 py-1 text-xs border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Quantity</label>
                            <input type="number" name="rewards[\${rewardIndex}][quantity]" class="w-full px-2 py-1 text-xs border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" min="1" value="1" required>
                        </div>
                    </div>
                     <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Description (Optional)</label>
                        <textarea name="rewards[\${rewardIndex}][description]" rows="1" class="w-full px-2 py-1 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"></textarea>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rewardFieldHTML);
        }

        // Function to remove a reward input field set
        function removeRewardField(button) {
            button.closest('.reward-item').remove();
        }

        // Function to add a new reward input field set for editing
        function addEditRewardField(reward = {}) {
            const container = document.getElementById('editRewardsContainer');
            const rewardIndex = container.children.length;
            const rewardFieldHTML = `
                <div class="reward-item p-2 border border-gray-200 rounded-lg space-y-2">
                    <div class="flex justify-between items-center">
                         <h5 class="text-xs font-semibold text-gray-700">Reward #\${rewardIndex + 1}</h5>
                         <button type="button" onclick="removeRewardField(this)" class="text-red-500 hover:text-red-700 text-sm\"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Rank</label>
                            <input type="number" name="rewards[\${rewardIndex}][rank]" class="w-full px-2 py-1 text-xs border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" min="1" value="\${reward.rank || ''}" required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Prize Name</label>
                            <input type="text" name="rewards[\${rewardIndex}][prize_name]" class="w-full px-2 py-1 text-xs border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" value="\${reward.prize_name || ''}" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Quantity</label>
                            <input type="number" name="rewards[\${rewardIndex}][quantity]" class="w-full px-2 py-1 text-xs border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" min="1" value="\${reward.quantity || '1'}" required>
                        </div>
                    </div>
                     <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Description (Optional)</label>
                        <textarea name="rewards[\${rewardIndex}][description]" rows="1" class="w-full px-2 py-1 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">\${reward.description || ''}</textarea>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rewardFieldHTML);
        }

        // Function to open the edit scheme modal and populate data
        function openEditSchemeModal(schemeId) {
            console.log('Opening edit modal for schemeId:', schemeId);
            // Clear previous rewards
            document.getElementById('editRewardsContainer').innerHTML = '';

            // Fetch scheme details
            fetch(`fetch_scheme_details.php?scheme_id=\${schemeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const scheme = data.scheme;
                        const form = document.getElementById('editSchemeForm');

                        // Populate scheme details
                        form.querySelector('#editSchemeId').value = scheme.id;
                        form.querySelector('[name="scheme_name"]').value = scheme.scheme_name;
                        form.querySelector('[name="status"]').value = scheme.status;
                        form.querySelector('[name="start_date"]').value = scheme.start_date; // Assuming date format is YYYY-MM-DD
                        form.querySelector('[name="end_date"]').value = scheme.end_date;     // Assuming date format is YYYY-MM-DD
                        form.querySelector('[name="min_purchase_amount"]').value = scheme.min_purchase_amount;
                        form.querySelector('[name="entry_fee"]').value = scheme.entry_fee;
                        form.querySelector('[name="description"]').value = scheme.description;
                        form.querySelector('[name="terms_conditions"]').value = scheme.terms_conditions;
                        form.querySelector('#editAutoEntryOnPurchase').checked = scheme.auto_entry_on_purchase == 1;
                        form.querySelector('#editAutoEntryOnRegistration').checked = scheme.auto_entry_on_registration == 1;

                        // Populate rewards
                        if (data.rewards && data.rewards.length > 0) {
                            data.rewards.forEach(reward => {
                                addEditRewardField(reward);
                            });
                        }

                        // Show the modal
                        document.getElementById('editSchemeModal').classList.remove('hidden');
                    } else {
                        alert('Error fetching scheme details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching scheme details.');
                });
        }

        // Function to close the edit scheme modal
        function closeEditSchemeModal() {
            document.getElementById('editSchemeModal').classList.add('hidden');
            document.getElementById('editSchemeForm').reset();
            document.getElementById('editRewardsContainer').innerHTML = ''; // Clear rewards
        }

        // Form submission for editing
        document.getElementById('editSchemeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            // Collect reward data (similar to create form)
            const rewards = [];
            document.querySelectorAll('#editRewardsContainer .reward-item').forEach(rewardItem => {
                 const rank = rewardItem.querySelector('[name^="rewards"][name$="[rank]"]').value;
                 const prize_name = rewardItem.querySelector('[name^="rewards"][name$="[prize_name]"]').value;
                 const quantity = rewardItem.querySelector('[name^="rewards"][name$="[quantity]"]').value;
                 const description = rewardItem.querySelector('[name^="rewards"][name$="[description]"]').value;
                 rewards.push({ rank, prize_name, quantity, description });
            });

            // Append rewards as a JSON string
            formData.append('rewards', JSON.stringify(rewards));

            // Append firm_id (assuming you need it for the update endpoint)
            formData.append('firm_id', '<?php echo $firm_id; ?>');

            fetch('update_scheme.php', { // We will create this file next
                method: 'POST', // Or 'PUT' if your server framework supports it and you configure it
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Scheme updated successfully!');
                    window.location.reload(); // Reload to see changes
                } else {
                    alert(data.message || 'Error updating scheme');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating scheme');
            });
        });

        // Function to confirm and delete a scheme
        function confirmDeleteScheme(schemeId) {
            if (confirm('Are you sure you want to delete this scheme? This action cannot be undone.')) {
                deleteScheme(schemeId);
            }
        }

        // Function to delete a scheme via AJAX
        function deleteScheme(schemeId) {
             const firmId = <?php echo $firm_id; ?>; // Get firm_id from PHP variable
            fetch('delete_scheme.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'scheme_id=' + schemeId + '&firm_id=' + firmId // Send scheme_id and firm_id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Scheme deleted successfully!');
                    window.location.reload(); // Reload to see changes
                } else {
                    alert(data.message || 'Error deleting scheme');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting scheme.');
            });
        }

    </script>
</body>
</html>
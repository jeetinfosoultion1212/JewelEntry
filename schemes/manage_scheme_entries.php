<?php
// Enhanced Compact Lottery Schemes Management Page with Advanced Winner Selection

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

// Handle AJAX requests
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_scheme_details':
            $scheme_id = $_GET['scheme_id'] ?? null;
            if ($scheme_id) {
                // Fetch scheme details
                $schemeQuery = "SELECT * FROM schemes WHERE id = ? AND firm_id = ?";
                $schemeStmt = $conn->prepare($schemeQuery);
                $schemeStmt->bind_param("ii", $scheme_id, $firm_id);
                $schemeStmt->execute();
                $schemeResult = $schemeStmt->get_result();
                $scheme = $schemeResult->fetch_assoc();

                // Fetch rewards
                $rewardsQuery = "SELECT * FROM scheme_rewards WHERE scheme_id = ? ORDER BY rank ASC";
                $rewardsStmt = $conn->prepare($rewardsQuery);
                $rewardsStmt->bind_param("i", $scheme_id);
                $rewardsStmt->execute();
                $rewardsResult = $rewardsStmt->get_result();
                $rewards = [];
                while ($reward = $rewardsResult->fetch_assoc()) {
                    $rewards[] = $reward;
                }

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'scheme' => $scheme,
                    'rewards' => $rewards
                ]);
                exit();
            }
            break;

        case 'get_available_prizes':
            $scheme_id = $_GET['scheme_id'] ?? null;
            if ($scheme_id) {
                // Get available prizes (not yet won)
                $availablePrizesQuery = "SELECT sr.* FROM scheme_rewards sr 
                                       LEFT JOIN scheme_winners sw ON sr.id = sw.reward_id 
                                       WHERE sr.scheme_id = ? 
                                       GROUP BY sr.id 
                                       HAVING COUNT(sw.id) < sr.quantity 
                                       ORDER BY sr.rank ASC";
                $stmt = $conn->prepare($availablePrizesQuery);
                $stmt->bind_param("i", $scheme_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $availablePrizes = [];
                while ($prize = $result->fetch_assoc()) {
                    $wonCount = 0;
                    $countQuery = "SELECT COUNT(*) as won_count FROM scheme_winners WHERE reward_id = ?";
                    $countStmt = $conn->prepare($countQuery);
                    $countStmt->bind_param("i", $prize['id']);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    if ($countRow = $countResult->fetch_assoc()) {
                        $wonCount = $countRow['won_count'];
                    }
                    
                    $prize['remaining_quantity'] = $prize['quantity'] - $wonCount;
                    if ($prize['remaining_quantity'] > 0) {
                        $availablePrizes[] = $prize;
                    }
                }

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'prizes' => $availablePrizes
                ]);
                exit();
            }
            break;

        case 'save_winner':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $scheme_id = $_POST['scheme_id'] ?? null;
                $customer_id = $_POST['customer_id'] ?? null;
                $reward_id = $_POST['reward_id'] ?? null;
                $entry_id = $_POST['entry_id'] ?? null;

                if ($scheme_id && $customer_id && $reward_id && $entry_id) {
                    // Insert winner into database
                    $insertWinnerQuery = "INSERT INTO scheme_winners (scheme_id, customer_id, reward_id, entry_id, firm_id, selected_at, selected_by) 
                                        VALUES (?, ?, ?, ?, ?, NOW(), ?)";
                    $stmt = $conn->prepare($insertWinnerQuery);
                    $stmt->bind_param("iiiiii", $scheme_id, $customer_id, $reward_id, $entry_id, $firm_id, $user_id);
                    
                    if ($stmt->execute()) {
                        // Get winner details for response
                        $winnerDetailsQuery = "SELECT sw.*, 
                                             CONCAT_WS(' ', c.FirstName, c.LastName) as customer_name,
                                             c.PhoneNumber as customer_contact,
                                             sr.prize_name, sr.rank, sr.description as prize_description,
                                             se.entry_number
                                             FROM scheme_winners sw
                                             JOIN customer c ON sw.customer_id = c.id
                                             JOIN scheme_rewards sr ON sw.reward_id = sr.id
                                             JOIN scheme_entries se ON sw.entry_id = se.id
                                             WHERE sw.id = ?";
                        $detailStmt = $conn->prepare($winnerDetailsQuery);
                        $detailStmt->bind_param("i", $stmt->insert_id);
                        $detailStmt->execute();
                        $winnerDetails = $detailStmt->get_result()->fetch_assoc();

                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'winner' => $winnerDetails,
                            'message' => 'Winner saved successfully!'
                        ]);
                        exit();
                    }
                }
                
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to save winner']);
                exit();
            }
            break;
    }
}

// Get the scheme ID from the URL parameter
$scheme_id = $_GET['scheme_id'] ?? null;
$scheme = null;
$schemeEntries = [];
$schemeRewards = [];
$schemeWinners = [];

if ($scheme_id) {
    // Fetch scheme details
    $schemeQuery = "SELECT * FROM schemes WHERE id = ? AND firm_id = ?";
    $schemeStmt = $conn->prepare($schemeQuery);
    $schemeStmt->bind_param("ii", $scheme_id, $firm_id);
    $schemeStmt->execute();
    $schemeResult = $schemeStmt->get_result();
    $scheme = $schemeResult->fetch_assoc();

    if (!$scheme) {
        $_SESSION['error'] = 'Scheme not found or you do not have access.';
        header("Location: schemes.php");
        exit();
    }

    // Fetch scheme rewards
    $rewardsQuery = "SELECT * FROM scheme_rewards WHERE scheme_id = ? ORDER BY rank ASC";
    $rewardsStmt = $conn->prepare($rewardsQuery);
    $rewardsStmt->bind_param("i", $scheme_id);
    $rewardsStmt->execute();
    $rewardsResult = $rewardsStmt->get_result();
    while ($reward = $rewardsResult->fetch_assoc()) {
        $schemeRewards[] = $reward;
    }

    // Fetch existing winners
    $winnersQuery = "SELECT sw.*, 
                     CONCAT_WS(' ', c.FirstName, c.LastName) as customer_name,
                     c.PhoneNumber as customer_contact,
                     sr.prize_name, sr.rank, sr.description as prize_description,
                     se.entry_number
                     FROM scheme_winners sw
                     JOIN customer c ON sw.customer_id = c.id
                     JOIN scheme_rewards sr ON sw.reward_id = sr.id
                     JOIN scheme_entries se ON sw.entry_id = se.id
                     WHERE sw.scheme_id = ? AND sw.firm_id = ?
                     ORDER BY sr.rank ASC, sw.selected_at ASC";
    $winnersStmt = $conn->prepare($winnersQuery);
    $winnersStmt->bind_param("ii", $scheme_id, $firm_id);
    $winnersStmt->execute();
    $winnersResult = $winnersStmt->get_result();
    while ($winner = $winnersResult->fetch_assoc()) {
        $schemeWinners[] = $winner;
    }

    // Fetch all entries for this scheme with customer details
    $entriesQuery = "SELECT se.*, CONCAT_WS(' ', c.FirstName, c.LastName) as customer_name, 
                     c.PhoneNumber as customer_contact, c.Email as customer_email,
                     c.City as customer_city
                     FROM scheme_entries se 
                     JOIN customer c ON se.customer_id = c.id 
                     JOIN schemes s ON se.scheme_id = s.id
                     WHERE se.scheme_id = ? AND s.firm_id = ?
                     ORDER BY se.entry_date DESC";
    $entriesStmt = $conn->prepare($entriesQuery);
    $entriesStmt->bind_param("ii", $scheme_id, $firm_id);
    $entriesStmt->execute();
    $entriesResult = $entriesStmt->get_result();

    while ($entry = $entriesResult->fetch_assoc()) {
        $schemeEntries[] = $entry;
    }
} else {
    $_SESSION['error'] = 'No scheme specified.';
    header("Location: schemes.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title><?php echo htmlspecialchars($scheme['scheme_name']); ?> - Lottery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
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
                    },
                    animation: {
                        'spin-slow': 'spin 3s linear infinite',
                        'bounce-slow': 'bounce 2s infinite',
                        'pulse-fast': 'pulse 1s infinite',
                        'wiggle': 'wiggle 1s ease-in-out infinite',
                        'float': 'float 3s ease-in-out infinite',
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
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .winner-animation {
            animation: bounce 0.5s ease-in-out infinite alternate;
        }
        @keyframes roulette {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes wiggle {
            0%, 7% { transform: rotateZ(0); }
            15% { transform: rotateZ(-15deg); }
            20% { transform: rotateZ(10deg); }
            25% { transform: rotateZ(-10deg); }
            30% { transform: rotateZ(6deg); }
            35% { transform: rotateZ(-4deg); }
            40%, 100% { transform: rotateZ(0); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .roulette-spin {
            animation: roulette 0.1s linear infinite;
        }
        .prize-glow {
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.5);
            animation: pulse 2s infinite;
        }
        .winner-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            animation: float 3s ease-in-out infinite;
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
                    <button onclick="history.back()" class="w-8 h-8 gradient-primary rounded-lg flex items-center justify-center shadow-sm">
                        <i class="fas fa-arrow-left text-white text-xs"></i>
                    </button>
                    <div>
                        <h1 class="text-sm font-bold text-gray-800 leading-tight"><?php echo $userInfo['FirmName']; ?></h1>
                        <p class="text-xs text-primary-600 font-medium leading-tight">Lottery Management</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p class="text-xs font-semibold text-gray-800 leading-tight"><?php echo $userInfo['Name']; ?></p>
                        <p class="text-xs text-primary-600 leading-tight"><?php echo $userInfo['Role']; ?></p>
                    </div>
                    <div class="w-8 h-8 gradient-gold rounded-lg flex items-center justify-center shadow-sm overflow-hidden">
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
        <!-- Compact Scheme Header -->
        <div class="gradient-primary rounded-xl p-3 text-white shadow-lg">
            <div class="flex justify-between items-start mb-2">
                <div class="flex-1">
                    <h1 class="text-base font-bold mb-1 leading-tight"><?php echo htmlspecialchars($scheme['scheme_name']); ?></h1>
                    <div class="flex items-center space-x-2">
                        <span class="bg-white/20 px-2 py-0.5 rounded-full text-xs font-medium">
                            <?php echo ucfirst($scheme['status']); ?>
                        </span>
                        <span class="bg-white/20 px-2 py-0.5 rounded-full text-xs font-medium">
                            <i class="fas fa-users mr-1"></i><?php echo count($schemeEntries); ?>
                        </span>
                        <?php if (count($schemeWinners) > 0): ?>
                        <span class="bg-green-500/80 px-2 py-0.5 rounded-full text-xs font-medium">
                            <i class="fas fa-trophy mr-1"></i><?php echo count($schemeWinners); ?> Winners
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex space-x-1">
                    <button onclick="openEditSchemeModal(<?php echo $scheme['id']; ?>)" class="bg-white/20 hover:bg-white/30 p-2 rounded-lg transition-all">
                        <i class="fas fa-edit text-sm"></i>
                    </button>
                    <?php if (count($schemeEntries) > 0): ?>
                    <button onclick="openWinnerModal()" class="bg-white/20 hover:bg-white/30 p-2 rounded-lg transition-all animate-wiggle">
                        <i class="fas fa-trophy text-sm"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="bg-white/10 rounded-lg p-2">
                    <p class="text-white/70 text-xs">Entry Fee</p>
                    <p class="text-sm font-bold">₹<?php echo number_format($scheme['entry_fee']); ?></p>
                </div>
                <div class="bg-white/10 rounded-lg p-2">
                    <p class="text-white/70 text-xs">Min Purchase</p>
                    <p class="text-sm font-bold">₹<?php echo number_format($scheme['min_purchase_amount']); ?></p>
                </div>
            </div>
        </div>

        <!-- Winners Section -->
        <?php if (!empty($schemeWinners)): ?>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="gradient-success p-2.5 flex items-center justify-between">
                <h2 class="text-sm font-bold text-white flex items-center">
                    <i class="fas fa-crown mr-1.5 text-xs"></i>Winners Announced
                </h2>
                <span class="bg-white/20 text-white px-2 py-0.5 rounded-full text-xs font-medium">
                    <?php echo count($schemeWinners); ?>
                </span>
            </div>
            <div class="p-2 space-y-2">
                <?php foreach ($schemeWinners as $winner): ?>
                <div class="winner-card rounded-lg p-3 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center font-bold">
                                <?php echo $winner['rank']; ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm"><?php echo htmlspecialchars($winner['customer_name']); ?></h3>
                                <p class="text-xs opacity-90"><?php echo htmlspecialchars($winner['customer_contact']); ?></p>
                                <p class="text-xs opacity-75">Entry #<?php echo $winner['entry_number']; ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="bg-white/20 rounded-lg p-2">
                                <p class="font-bold text-sm"><?php echo htmlspecialchars($winner['prize_name']); ?></p>
                                <?php if (!empty($winner['prize_description'])): ?>
                                <p class="text-xs opacity-90"><?php echo htmlspecialchars($winner['prize_description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Compact Rewards Section -->
        <?php if (!empty($schemeRewards)): ?>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="gradient-gold p-2.5 flex items-center justify-between">
                <h2 class="text-sm font-bold text-white flex items-center">
                    <i class="fas fa-trophy mr-1.5 text-xs"></i>Prizes
                </h2>
                <span class="bg-white/20 text-white px-2 py-0.5 rounded-full text-xs font-medium">
                    <?php echo count($schemeRewards); ?>
                </span>
            </div>
            <div class="p-2 space-y-1.5">
                <?php foreach ($schemeRewards as $reward): ?>
                <?php
                // Count how many of this prize have been won
                $wonCount = 0;
                foreach ($schemeWinners as $winner) {
                    if ($winner['rank'] == $reward['rank']) {
                        $wonCount++;
                    }
                }
                $isFullyWon = $wonCount >= $reward['quantity'];
                ?>
                <div class="flex items-center justify-between p-2 bg-gradient-to-r from-gold-50 to-primary-50 rounded-lg border border-gold-100 <?php echo $isFullyWon ? 'opacity-50' : ''; ?>">
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 gradient-gold rounded-full flex items-center justify-center text-white font-bold text-xs">
                            <?php echo $reward['rank']; ?>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 text-xs leading-tight">
                                <?php echo htmlspecialchars($reward['prize_name']); ?>
                                <?php if ($isFullyWon): ?>
                                <i class="fas fa-check-circle text-green-500 ml-1"></i>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($reward['description'])): ?>
                                <p class="text-xs text-gray-600 leading-tight"><?php echo htmlspecialchars($reward['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="bg-gold-100 text-gold-800 px-1.5 py-0.5 rounded-full text-xs font-medium">
                        <?php echo $wonCount; ?>/<?php echo $reward['quantity']; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Compact Participants Section -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="gradient-primary p-2.5 flex justify-between items-center">
                <h2 class="text-sm font-bold text-white flex items-center">
                    <i class="fas fa-users mr-1.5 text-xs"></i>Participants (<?php echo count($schemeEntries); ?>)
                </h2>
                <div class="flex space-x-1">
                    <?php if (count($schemeEntries) > 0): ?>
                    <button onclick="openWinnerModal()" class="bg-white/20 hover:bg-white/30 p-1.5 rounded-lg transition-all animate-pulse" title="Select Winner">
                        <i class="fas fa-crown text-xs"></i>
                    </button>
                    <?php endif; ?>
                    <button onclick="toggleView()" class="bg-white/20 hover:bg-white/30 p-1.5 rounded-lg transition-all">
                        <i id="viewToggleIcon" class="fas fa-th-large text-xs"></i>
                    </button>
                </div>
            </div>
            
            <?php if (!empty($schemeEntries)): ?>
            <!-- Compact List View -->
            <div id="listView" class="divide-y divide-gray-100">
                <?php foreach ($schemeEntries as $entry): ?>
                <div class="p-2.5 hover:bg-gray-50 transition-colors compact-card">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-8 h-8 gradient-primary rounded-lg flex items-center justify-center text-white font-bold text-xs">
                                <?php echo strtoupper(substr($entry['customer_name'], 0, 2)); ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-800 text-xs leading-tight truncate"><?php echo htmlspecialchars($entry['customer_name']); ?></h3>
                                <p class="text-xs text-gray-600 leading-tight">
                                    <i class="fas fa-phone mr-1 text-primary-500"></i><?php echo htmlspecialchars($entry['customer_contact']); ?>
                                </p>
                                <p class="text-xs text-gray-500 leading-tight">
                                    <i class="fas fa-calendar mr-1 text-primary-500"></i><?php echo date('d M Y, h:i A', strtotime($entry['entry_date'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="bg-primary-100 text-primary-800 px-1.5 py-0.5 rounded-full text-xs font-semibold">
                                #<?php echo $entry['entry_number']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Compact Grid View -->
            <div id="gridView" class="hidden p-2 grid grid-cols-1 gap-2">
                <?php foreach ($schemeEntries as $entry): ?>
                <div class="bg-gradient-to-br from-white to-gray-50 rounded-lg p-2.5 border border-gray-200 compact-card">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center space-x-2">
                            <div class="w-7 h-7 gradient-primary rounded-lg flex items-center justify-center text-white font-bold text-xs">
                                <?php echo strtoupper(substr($entry['customer_name'], 0, 2)); ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-gray-800 text-xs leading-tight truncate"><?php echo htmlspecialchars($entry['customer_name']); ?></h3>
                                <p class="text-xs text-gray-600 leading-tight truncate"><?php echo htmlspecialchars($entry['customer_contact']); ?></p>
                            </div>
                        </div>
                        <span class="bg-primary-100 text-primary-800 px-1.5 py-0.5 rounded-full text-xs font-semibold">
                            #<?php echo $entry['entry_number']; ?>
                        </span>
                    </div>
                    
                    <div class="space-y-1 text-xs">
                        <?php if (!empty($entry['customer_email'])): ?>
                        <p class="text-gray-600 truncate"><i class="fas fa-envelope mr-1 text-primary-500"></i><?php echo htmlspecialchars($entry['customer_email']); ?></p>
                        <?php endif; ?>
                        <p class="text-gray-600"><i class="fas fa-calendar mr-1 text-primary-500"></i><?php echo date('d M Y, h:i A', strtotime($entry['entry_date'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-6 text-center">
                <div class="w-12 h-12 gradient-primary rounded-full flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-users text-white"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 mb-1">No Participants Yet</h3>
                <p class="text-gray-600 text-xs">Participants will appear here once they join.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Enhanced Winner Selection Modal -->
    <div id="winnerModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[90] hidden">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-2xl">
            <div class="text-center">
                <!-- Prize Selection Phase -->
                <div id="prizeSelectionPhase">
                    <div class="w-16 h-16 gradient-gold rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                        <i class="fas fa-trophy text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Select Prize to Draw</h3>
                    <p class="text-gray-600 mb-6 text-sm">Choose which prize you want to select a winner for:</p>
                    
                    <div id="availablePrizes" class="space-y-3 mb-6">
                        <!-- Available prizes will be loaded here -->
                    </div>
                </div>

                <!-- Spinning Phase -->
                <div id="spinningPhase" class="hidden">
                    <div class="w-20 h-20 gradient-primary rounded-full flex items-center justify-center mx-auto mb-4 roulette-spin">
                        <i class="fas fa-users text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">🎲 Drawing Winner...</h3>
                    <div id="currentPrizeInfo" class="bg-gold-50 rounded-lg p-3 mb-4">
                        <p class="text-sm font-semibold text-gold-800">Prize: <span id="spinningPrizeName"></span></p>
                        <p class="text-xs text-gold-600">Rank: <span id="spinningPrizeRank"></span></p>
                    </div>
                    <div class="text-center">
                        <div id="participantCounter" class="text-2xl font-bold text-primary-600 mb-2">0</div>
                        <p class="text-sm text-gray-600">Participants in the draw</p>
                    </div>
                </div>

                <!-- Winner Display Phase -->
                <div id="winnerDisplayPhase" class="hidden">
                    <div class="w-20 h-20 gradient-success rounded-full flex items-center justify-center mx-auto mb-4 winner-animation">
                        <span id="winnerInitials" class="font-bold text-2xl text-white"></span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">🎉 Winner Selected! 🎉</h3>
                    
                    <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-xl p-4 mb-4">
                        <div class="prize-glow bg-gold-100 rounded-lg p-3 mb-3">
                            <h4 class="font-bold text-gold-800" id="wonPrizeName"></h4>
                            <p class="text-xs text-gold-600" id="wonPrizeDescription"></p>
                            <p class="text-xs text-gold-600">Rank: <span id="wonPrizeRank"></span></p>
                        </div>
                        
                        <div class="text-center">
                            <p id="winnerName" class="text-xl font-bold text-gray-800"></p>
                            <p id="winnerContact" class="text-sm text-gray-600"></p>
                            <p id="winnerEntry" class="text-xs text-gray-500 mt-1"></p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col space-y-3">
                    <button id="continueDrawBtn" onclick="continueDrawing()" class="hidden w-full gradient-primary text-white py-3 px-6 rounded-xl font-semibold hover:opacity-90 transition-all">
                        <i class="fas fa-forward mr-2"></i>Continue Drawing
                    </button>
                    <button id="finishDrawBtn" onclick="finishDrawing()" class="hidden w-full gradient-success text-white py-3 px-6 rounded-xl font-semibold hover:opacity-90 transition-all">
                        <i class="fas fa-check mr-2"></i>Finish Drawing
                    </button>
                    <button onclick="closeWinnerModal()" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-700 py-3 px-6 rounded-xl font-semibold transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Fixed Bottom Navigation -->
    <nav class="glass-nav fixed bottom-0 left-0 right-0 shadow-lg z-40">
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
    const participants = <?php echo json_encode($schemeEntries); ?>;
    const schemeId = <?php echo $scheme_id; ?>;
    let availablePrizes = [];
    let currentPrize = null;
    let selectedWinner = null;

    // Toggle between grid and list view
    function toggleView() {
        const gridView = document.getElementById('gridView');
        const listView = document.getElementById('listView');
        const toggleIcon = document.getElementById('viewToggleIcon');
        
        if (gridView.classList.contains('hidden')) {
            gridView.classList.remove('hidden');
            listView.classList.add('hidden');
            toggleIcon.className = 'fas fa-list text-xs';
        } else {
            gridView.classList.add('hidden');
            listView.classList.remove('hidden');
            toggleIcon.className = 'fas fa-th-large text-xs';
        }
    }

    // Winner Selection Functions
    function openWinnerModal() {
        console.log('Opening winner modal...');
        const modal = document.getElementById('winnerModal');
        if (modal) {
            modal.classList.remove('hidden');
            resetWinnerModal();
            loadAvailablePrizes();
        } else {
            console.error('Winner modal not found');
        }
    }

    function closeWinnerModal() {
        console.log('Closing winner modal...');
        const modal = document.getElementById('winnerModal');
        if (modal) {
            modal.classList.add('hidden');
            resetWinnerModal();
        }
    }

    function resetWinnerModal() {
        console.log('Resetting winner modal...');
        const prizePhase = document.getElementById('prizeSelectionPhase');
        const spinPhase = document.getElementById('spinningPhase');
        const winnerPhase = document.getElementById('winnerDisplayPhase');
        const continueBtn = document.getElementById('continueDrawBtn');
        const finishBtn = document.getElementById('finishDrawBtn');

        if (prizePhase) prizePhase.classList.remove('hidden');
        if (spinPhase) spinPhase.classList.add('hidden');
        if (winnerPhase) winnerPhase.classList.add('hidden');
        if (continueBtn) continueBtn.classList.add('hidden');
        if (finishBtn) finishBtn.classList.add('hidden');
        
        currentPrize = null;
        selectedWinner = null;
    }

    function loadAvailablePrizes() {
        console.log('Loading available prizes...');
        
        // Show loading state
        const container = document.getElementById('availablePrizes');
        if (container) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin mx-auto mb-2"></div>
                    <p class="text-sm text-gray-600">Loading available prizes...</p>
                </div>
            `;
        }

        fetch(`?action=get_available_prizes&scheme_id=${schemeId}`)
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Available prizes data:', data);
                if (data.success) {
                    availablePrizes = data.prizes || [];
                    displayAvailablePrizes();
                } else {
                    console.error('Error in response:', data);
                    showPrizeLoadError();
                }
            })
            .catch(error => {
                console.error('Error loading prizes:', error);
                showPrizeLoadError();
            });
    }

    function showPrizeLoadError() {
        const container = document.getElementById('availablePrizes');
        if (container) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <p class="text-sm text-red-600 mb-3">Error loading prizes</p>
                    <button onclick="loadAvailablePrizes()" class="bg-primary-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-primary-600">
                        Try Again
                    </button>
                </div>
            `;
        }
    }

    function displayAvailablePrizes() {
        console.log('Displaying available prizes:', availablePrizes);
        const container = document.getElementById('availablePrizes');
        
        if (!container) {
            console.error('Available prizes container not found');
            return;
        }

        if (availablePrizes.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">All Prizes Distributed!</h3>
                    <p class="text-gray-600 text-sm">All prizes for this scheme have been awarded to winners.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = availablePrizes.map(prize => `
            <button onclick="selectPrizeForDraw(${prize.id})" 
                    class="w-full bg-gradient-to-r from-gold-50 to-primary-50 border-2 border-gold-200 rounded-lg p-4 hover:border-gold-400 transition-all prize-card">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 gradient-gold rounded-full flex items-center justify-center text-white font-bold">
                            ${prize.rank}
                        </div>
                        <div class="text-left">
                            <h4 class="font-bold text-gray-800">${prize.prize_name}</h4>
                            ${prize.description ? `<p class="text-xs text-gray-600">${prize.description}</p>` : ''}
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="bg-gold-100 text-gold-800 px-2 py-1 rounded-full text-xs font-medium">
                            ${prize.remaining_quantity || prize.quantity} left
                        </span>
                    </div>
                </div>
            </button>
        `).join('');
    }

    function selectPrizeForDraw(prizeId) {
        console.log('Selecting prize for draw:', prizeId);
        currentPrize = availablePrizes.find(p => p.id == prizeId);
        if (!currentPrize) {
            console.error('Prize not found:', prizeId);
            return;
        }

        // Hide prize selection, show spinning phase
        const prizePhase = document.getElementById('prizeSelectionPhase');
        const spinPhase = document.getElementById('spinningPhase');
        
        if (prizePhase) prizePhase.classList.add('hidden');
        if (spinPhase) spinPhase.classList.remove('hidden');

        // Update spinning phase info
        const prizeNameEl = document.getElementById('spinningPrizeName');
        const prizeRankEl = document.getElementById('spinningPrizeRank');
        
        if (prizeNameEl) prizeNameEl.textContent = currentPrize.prize_name;
        if (prizeRankEl) prizeRankEl.textContent = currentPrize.rank;

        // Start the enhanced spinning animation
        startEnhancedSpinning();
    }

    function startEnhancedSpinning() {
        console.log('Starting enhanced spinning...');
        
        if (participants.length === 0) {
            alert('No participants available for selection!');
            return;
        }

        let counter = 0;
        const maxCount = participants.length;
        const counterElement = document.getElementById('participantCounter');
        
        // Animate counter for 5 seconds
        const spinDuration = 5000; // 5 seconds
        const intervalTime = 50; // Update every 50ms
        const totalSteps = spinDuration / intervalTime;
        let currentStep = 0;

        const spinInterval = setInterval(() => {
            // Random participant count animation
            counter = Math.floor(Math.random() * maxCount) + 1;
            if (counterElement) {
                counterElement.textContent = counter;
            }
            currentStep++;

            if (currentStep >= totalSteps) {
                clearInterval(spinInterval);
                selectFinalWinner();
            }
        }, intervalTime);
    }

    function selectFinalWinner() {
        console.log('Selecting final winner...');
        
        // Select random winner
        const randomIndex = Math.floor(Math.random() * participants.length);
        selectedWinner = participants[randomIndex];

        // Trigger confetti if available
        if (typeof confetti !== 'undefined') {
            confetti({
                particleCount: 100,
                spread: 70,
                origin: { y: 0.6 }
            });
        }

        // Show winner display phase
        const spinPhase = document.getElementById('spinningPhase');
        const winnerPhase = document.getElementById('winnerDisplayPhase');
        
        if (spinPhase) spinPhase.classList.add('hidden');
        if (winnerPhase) winnerPhase.classList.remove('hidden');

        // Update winner display
        const initialsEl = document.getElementById('winnerInitials');
        const nameEl = document.getElementById('winnerName');
        const contactEl = document.getElementById('winnerContact');
        const entryEl = document.getElementById('winnerEntry');
        
        if (initialsEl) initialsEl.textContent = selectedWinner.customer_name.substring(0, 2).toUpperCase();
        if (nameEl) nameEl.textContent = selectedWinner.customer_name;
        if (contactEl) contactEl.textContent = selectedWinner.customer_contact;
        if (entryEl) entryEl.textContent = `Entry #${selectedWinner.entry_number} • ${new Date(selectedWinner.entry_date).toLocaleDateString()}`;
        
        // Update prize info
        const prizeNameEl = document.getElementById('wonPrizeName');
        const prizeDescEl = document.getElementById('wonPrizeDescription');
        const prizeRankEl = document.getElementById('wonPrizeRank');
        
        if (prizeNameEl) prizeNameEl.textContent = currentPrize.prize_name;
        if (prizeDescEl) prizeDescEl.textContent = currentPrize.description || '';
        if (prizeRankEl) prizeRankEl.textContent = currentPrize.rank;

        // Save winner to database
        saveWinnerToDatabase();

        // Show appropriate buttons after a delay
        setTimeout(() => {
            const continueBtn = document.getElementById('continueDrawBtn');
            const finishBtn = document.getElementById('finishDrawBtn');
            
            // For now, always show continue button - we'll check for more prizes when clicked
            if (continueBtn) continueBtn.classList.remove('hidden');
            if (finishBtn) finishBtn.classList.remove('hidden');
        }, 1000);
    }

    function saveWinnerToDatabase() {
        console.log('Saving winner to database...');
        
        const formData = new FormData();
        formData.append('scheme_id', schemeId);
        formData.append('customer_id', selectedWinner.customer_id);
        formData.append('reward_id', currentPrize.id);
        formData.append('entry_id', selectedWinner.id);

        fetch('?action=save_winner', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Save winner response:', data);
            if (data.success) {
                console.log('Winner saved successfully:', data.winner);
            } else {
                console.error('Failed to save winner:', data.message);
                alert('Failed to save winner: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error saving winner:', error);
            alert('Error saving winner');
        });
    }

    function continueDrawing() {
        console.log('Continue drawing...');
        // Reset for next prize selection
        resetWinnerModal();
        loadAvailablePrizes();
    }

    function finishDrawing() {
        console.log('Finish drawing...');
        
        // Show completion message and reload page
        if (typeof confetti !== 'undefined') {
            confetti({
                particleCount: 200,
                spread: 100,
                origin: { y: 0.6 }
            });
        }
        
        setTimeout(() => {
            alert('🎉 Drawing completed! The page will refresh to show the results.');
            window.location.reload();
        }, 1000);
    }

    // Close modals on outside click
    window.onclick = function(event) {
        const winnerModal = document.getElementById('winnerModal');
        if (event.target === winnerModal) {
            closeWinnerModal();
        }
    };

    // Auto-hide notifications
    setTimeout(() => {
        const notifications = document.querySelectorAll('.fixed.top-16.right-3');
        notifications.forEach(notification => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        });
    }, 4000);

    // Debug: Log when page loads
    console.log('Page loaded. Participants:', participants.length, 'Scheme ID:', schemeId);
</script>
</body>
</html>

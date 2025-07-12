<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle AJAX requests for view counter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'increment_view') {
    header('Content-Type: application/json');
    
    $licence_no = $_POST['licence_no'] ?? '';
    $jeweller_name = $_POST['jeweller_name'] ?? '';
    $page_type = $_POST['page_type'] ?? 'jewellers_page';
    
    if (!empty($licence_no)) {
        try {
            // Database connection
            $servername = "localhost";
            $username = "u176143338_CnGFg";
            $password = "1Bi9t52LyV";
            $dbname = "u176143338_VGe2Q";
            
            $conn = new mysqli($servername, $username, $password, $dbname);
            
            if ($conn->connect_error) {
                echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
                exit;
            }
            
            // Create Trak_page table if not exists
            $conn->query("CREATE TABLE IF NOT EXISTS Trak_page (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_name VARCHAR(255) NOT NULL,
                jeweller_name VARCHAR(255) NULL,
                licence_no VARCHAR(255) NULL,
                visit_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL
            )");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Insert a new row for every visit
            $insert_stmt = $conn->prepare("INSERT INTO Trak_page (page_name, jeweller_name, licence_no, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            if ($insert_stmt) {
                $insert_stmt->bind_param('sssss', $page_type, $jeweller_name, $licence_no, $ip_address, $user_agent);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            
            // Count total page views for this page/jeweller
            $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM Trak_page WHERE page_name = ? AND licence_no = ?");
            $count_stmt->bind_param('ss', $page_type, $licence_no);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $view_count = ($count_result && $row = $count_result->fetch_assoc()) ? $row['cnt'] : 1;
            $count_stmt->close();
            
            $conn->close();
            echo json_encode(['status' => 'success', 'view_count' => $view_count]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Licence number is empty']);
    }
    exit;
}

// Initialize parameters from URL
$licence_no = isset($_GET['licence_no']) ? $_GET['licence_no'] : null;
$request_no = isset($_GET['request_no']) ? $_GET['request_no'] : null;

// Database connection
$servername = "localhost";
$username = "u176143338_CnGFg";
$password = "1Bi9t52LyV";
$dbname = "u176143338_VGe2Q";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Variables for the view and jeweller details
$jeweller_details = null;
$job_details = [];
$request_info = null;
$transaction_info = null;
$has_huid_data = false;

// If request_no is provided, fetch details
if ($request_no) {
    // Get request details
    $request_query = "
        SELECT 
            jc.licence_no,
            jc.request_no,
            jc.date_of_request,
            jc.purity,
            SUM(jc.pcs) AS total_pcs,
            SUM(jc.weight) AS total_weight,
            jc.bill_no
        FROM job_cards jc
        WHERE jc.request_no = ?
        GROUP BY jc.licence_no, jc.request_no, jc.date_of_request, jc.purity, jc.bill_no
    ";
    
    $stmt = $conn->prepare($request_query);
    $stmt->bind_param("s", $request_no);
    $stmt->execute();
    $request_result = $stmt->get_result();
    
    if ($request_result->num_rows > 0) {
        $request_info = $request_result->fetch_assoc();
        $licence_no = $request_info['licence_no']; // Set licence_no from the request
        
        // Fetch job details for this request
        $jobs_query = "
            SELECT 
                job_no,
                item,
                pcs,
                weight,
                huid_pcs,
                fail_pcs
            FROM job_cards
            WHERE request_no = ?
            ORDER BY job_no
        ";
        
        $stmt = $conn->prepare($jobs_query);
        $stmt->bind_param("s", $request_no);
        $stmt->execute();
        $jobs_result = $stmt->get_result();
        
        while ($job = $jobs_result->fetch_assoc()) {
            $job_details[] = $job;
        }
        
        // Fetch transaction info for this request
        $transaction_query = "
            SELECT 
                total_amount,
                payment_status,
                payment_date,
                payment_mode
            FROM transactions
            WHERE request_no = ?
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($transaction_query);
        $stmt->bind_param("s", $request_no);
        $stmt->execute();
        $transaction_result = $stmt->get_result();
        
        if ($transaction_result->num_rows > 0) {
            $transaction_info = $transaction_result->fetch_assoc();
        }
        
        // Check if HUID data exists for this request
        $huid_query = "
            SELECT COUNT(*) as count
            FROM huid_data
            WHERE request_no = ?
        ";
        
        $stmt = $conn->prepare($huid_query);
        $stmt->bind_param("s", $request_no);
        $stmt->execute();
        $huid_result = $stmt->get_result();
        $huid_count = $huid_result->fetch_assoc()['count'];
        $has_huid_data = ($huid_count > 0);
    } else {
        die("No request found with the provided request number.");
    }
    $stmt->close();
}

// If no licence_no is available after all checks, redirect to index
if (!$licence_no) {
    header('Location: index.php');
    exit();
}

// Fetch jeweller details
$details_query = "
    SELECT j.Jewellers_Name, j.Party_type, j.Address1, j.Address2, j.City, j.Contact_no,
           j.O_Bal, j.C_Bal, j.Logo, j.Validity_date, j.Rate, j.PAN, j.GST, j.State, j.STCODE
    FROM jewellers j
    WHERE j.licence_no = ?
";
$stmt = $conn->prepare($details_query);
$stmt->bind_param("s", $licence_no);
$stmt->execute();
$details_result = $stmt->get_result();

if ($details_result->num_rows === 0) {
    die("No jeweller found with the provided licence number.");
}

$jeweller_details = $details_result->fetch_assoc();
$stmt->close();

// Process contact update if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact'])) {
    $new_contact = $_POST['new_contact'];
    
    // Update in jewellers table
    $update_query = "UPDATE jewellers SET Contact_no = ? WHERE licence_no = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ss", $new_contact, $licence_no);
    $stmt->execute();
    $stmt->close();
    
    // Refresh the page to show updated info
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit();
}

$conn->close();

// Get greeting based on time of day (India timezone)
function getGreeting() {
    // Set timezone to India Standard Time
    date_default_timezone_set('Asia/Kolkata');
    $hour = (int)date('H');
    
    if ($hour >= 5 && $hour < 12) {
        return "Good morning";
    } elseif ($hour >= 12 && $hour < 17) {
        return "Good afternoon";
    } else {
        return "Good evening";
    }
}

$greeting = getGreeting();
$jeweller_name = htmlspecialchars($jeweller_details['Jewellers_Name'] ?? 'Jeweller');
$has_contact_info = !empty($jeweller_details['Contact_no']) && $jeweller_details['Contact_no'] != 'NULL' && $jeweller_details['Contact_no'] != 'undefined';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mahalaxmi Hallmarking Centre</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <style>
        :root {
            --gold-light: #f6e6b4;
            --gold-medium: #d5be82;
            --gold-dark: #b39c5e;
            --primary-color: #4f46e5;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }
        
        body {
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior: none;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            transition: all 0.2s ease;
        }
        
        .modal-content {
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        /* Gradient backgrounds */
        .bg-gold-gradient {
            background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold-dark) 100%);
        }
        
        .bg-blue-gradient {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
        }
        
        /* Button styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #3b82f6 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover, .btn-primary:focus {
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.5);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            transition: all 0.3s ease;
        }
        
        .btn-success:hover, .btn-success:focus {
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.5);
            transform: translateY(-1px);
        }
        
        .btn-disabled {
            background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        /* Card styles */
        .card {
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.15);
        }
        
        /* Scrollbar */
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }
        
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #d1d5db;
            border-radius: 10px;
        }
        
        /* Badge styles */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            font-size: 0.65rem;
            font-weight: 600;
            border-radius: 9999px;
        }
        
        .badge-success {
            color: white;
            background-color: var(--success-color);
        }
        
        .badge-warning {
            color: white;
            background-color: var(--warning-color);
        }
        
        .badge-danger {
            color: white;
            background-color: var(--danger-color);
        }
        
        /* Animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0px); }
        }
        
        .float {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .shimmer-effect {
            position: relative;
            overflow: hidden;
        }
        
        .shimmer-effect::after {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transform: rotate(30deg);
            animation: shimmer 3s infinite linear;
        }
        
        /* Glowing effect */
        @keyframes glow {
            0% { box-shadow: 0 0 5px rgba(79, 70, 229, 0.1); }
            50% { box-shadow: 0 0 20px rgba(79, 70, 229, 0.3); }
            100% { box-shadow: 0 0 5px rgba(79, 70, 229, 0.1); }
        }
        
        .glow {
            animation: glow 2s infinite;
        }
        
        /* Info bar */
        .info-bar {
            background: linear-gradient(to right, #f0f9ff, #e0f2fe, #f0f9ff);
            border-left: 3px solid #3b82f6;
        }
        
        /* Alert dialog styling */
        #alertOverlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
        }
        
        .alert-dialog {
            animation: alertIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        @keyframes alertIn {
            0% { transform: scale(0.9); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        /* Improved table styles */
        .fancy-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .fancy-table thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            z-index: 10;
        }
        
        .fancy-table tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .fancy-table tbody tr td {
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
            transition: all 0.2s ease;
        }
        
        /* Chip styles */
        .chip {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 500;
            background-color: #f3f4f6;
            color: #4b5563;
        }
        
        .chip-icon {
            margin-right: 0.25rem;
            font-size: 0.7rem;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <!-- Alert Dialog -->
    <div id="alertOverlay" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div id="alertDialog" class="alert-dialog bg-white rounded-xl shadow-xl max-w-xs w-full mx-3 overflow-hidden">
            <div class="bg-red-50 p-4 flex items-start">
                <div class="bg-red-100 rounded-full p-2 mr-3">
                    <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-red-700">HUID Details Not Available</h3>
                    <p class="text-sm text-red-600 mt-1">HUID details have not been uploaded yet for this request in Hallmark Pro App</p>
                </div>
            </div>
            <div class="p-4 border-t border-gray-100">
               
                <div class="info-bar p-3 mb-3 rounded-lg">
                    <p class="text-xs text-gray-700"><i class="fas fa-info-circle text-blue-500 mr-1"></i> You can also find HUID details in manakonline . you can login with your credentials at <a href="https://manakonline.com" class="text-blue-600 font-medium">manakonline.com</a>.</p>
                </div>
                <button id="closeAlert" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                    Okay, I understand
                </button>
            </div>
        </div>
    </div>

    <!-- JewelEntry App Promotion Modal -->
    <div id="jewelEntryModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-sm w-full mx-4 overflow-hidden transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
            <!-- Header with gradient -->
            <div class="bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 p-6 text-center relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-purple-100/20 to-pink-100/20"></div>
                <div class="relative z-10">
                    <div class="inline-flex items-center justify-center w-20 h-20 mb-4">
                        <img src="admin/uploads/logo.png" alt="JewelEntry Logo" class="w-full h-full object-contain" style="background: transparent;" />
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">JewelEntry</h2>
                    <p class="text-sm text-gray-600 mb-1">Your Complete Jewelry Business ERP</p>
                    <div class="flex items-center justify-center space-x-1 text-xs text-gray-500">
                        <i class="fas fa-star text-yellow-400"></i>
                        <span>Trusted by 500+ Firms</span>
                    </div>
                </div>
            </div>
            
            <!-- Major Modules -->
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-blue-50">
                        <i class="fas fa-qrcode text-blue-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">Digital Certificates</span>
                    </div>
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-green-50">
                        <i class="fas fa-boxes text-green-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">Smart Inventory</span>
                    </div>
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-purple-50">
                        <i class="fas fa-chart-line text-purple-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">Analytics & Reports</span>
                    </div>
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-orange-50">
                        <i class="fas fa-users text-orange-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">CRM & Staff</span>
                    </div>
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-pink-50">
                        <i class="fas fa-file-invoice text-pink-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">Billing & Invoicing</span>
                    </div>
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-yellow-50">
                        <i class="fas fa-cogs text-yellow-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">Customizable Settings</span>
                    </div>
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-indigo-50">
                        <i class="fas fa-coins text-indigo-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">Gold Scheme</span>
                    </div>
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-red-50">
                        <i class="fas fa-hand-holding-usd text-red-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">Loans</span>
                    </div>
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-teal-50">
                        <i class="fas fa-hammer text-teal-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">Karigars</span>
                    </div>
                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-amber-50">
                        <i class="fas fa-tray text-amber-500 text-sm"></i>
                        <span class="text-xs font-medium text-gray-700">Tray Management</span>
                    </div>
                </div>
                <a href="https://jewelentry.prosenjittechhub.com/" target="_blank" class="block w-full py-3 px-4 bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-semibold rounded-xl text-center transition-all duration-300 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-rocket mr-2"></i>
                    Get Started Free
                </a>
                <button id="closeJewelEntryModal" class="block w-full py-2 px-4 text-gray-500 hover:text-gray-700 font-medium rounded-lg transition-colors">
                    Maybe Later
                </button>
            </div>
        </div>
    </div>

    <!-- Contact Number Modal -->
   

    <!-- Main Content -->
    <div class="pb-6 px-3 max-w-lg mx-auto">
        <?php if ($request_info): ?>
        <!-- Request Details View -->
        <div class="card animate__animated animate__fadeIn" style="margin-top: 0.75rem;">
            <!-- Header with gold gradient -->
            <div class="bg-gold-gradient p-3 flex items-center justify-between border-b border-gold-dark/30">
                <div>
                    <h2 class="font-bold text-lg text-gray-800 flex items-center">
                        <span class="animate__animated animate__tada animate__delay-1s">👋</span>
                        <span class="ml-1.5"><?php echo $jeweller_name; ?></span>
                    </h2>
                    <div class="flex items-center mt-0.5">
                        <span class="text-xs text-gray-700 mr-2">Request #<?php echo htmlspecialchars($request_info['request_no']); ?></span>
                        <span class="chip">
                            <i class="fa-solid fa-calendar-check chip-icon"></i>
                            <?php echo date('d M Y', strtotime($request_info['date_of_request'])); ?>
                        </span>
                    </div>
                </div>
                <div class="shimmer-effect">
                    <div class="bg-white/80 rounded-full p-1.5 shadow-sm">
                        <i class="fas fa-medal text-amber-500 text-lg"></i>
                    </div>
                </div>
            </div>
            
            <!-- Key details -->
            <div class="px-3 py-2 grid grid-cols-3 gap-2 bg-gray-50 text-xs">
                <div>
                    <p class="text-gray-500">Date</p>
                    <p class="font-semibold text-gray-800"><?php echo date('d-m-Y', strtotime($request_info['date_of_request'])); ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Purity</p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($request_info['purity']); ?></p>
                </div>
                <div>
                    <?php if (!empty($request_info['bill_no'])): ?>
                    <p class="text-gray-500">Bill No</p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($request_info['bill_no']); ?></p>
                    <?php else: ?>
                    <p class="text-gray-500">Items</p>
                    <p class="font-semibold text-gray-800"><?php echo count($job_details); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Job details -->
            <div class="max-h-44 overflow-auto custom-scrollbar">
                <table class="min-w-full fancy-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Item</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Pcs</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Weight</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($job_details as $job): ?>
                        <tr>
                            <td class="px-3 py-2 text-xs text-gray-900"><?php echo htmlspecialchars($job['item']); ?></td>
                            <td class="px-3 py-2 text-xs text-gray-900 text-right"><?php echo htmlspecialchars($job['pcs']); ?></td>
                            <td class="px-3 py-2 text-xs text-gray-900 text-right"><?php echo htmlspecialchars($job['weight']); ?> g</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Totals -->
            <div class="bg-gray-100 px-3 py-2 border-t border-gray-200">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-medium text-gray-600">Total Items</span>
                    <span class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($request_info['total_pcs']); ?> pcs</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs font-medium text-gray-600">Total Weight</span>
                    <span class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($request_info['total_weight']); ?> gm</span>
                </div>
            </div>
            
            <?php if ($transaction_info): ?>
            <!-- Payment info -->
            <div class="p-3 bg-blue-gradient">
                <div class="flex justify-between items-center mb-1">
                    <div class="flex items-center">
                        <i class="fas fa-rupee-sign text-blue-600 mr-1.5"></i>
                        <span class="text-xs font-medium text-gray-700">Total Amount</span>
                    </div>
                    <span class="font-bold text-blue-700">₹<?php echo number_format($transaction_info['total_amount'], 2); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <i class="fas fa-circle-check text-blue-600 mr-1.5"></i>
                        <span class="text-xs font-medium text-gray-700">Status</span>
                    </div>
                    <?php if ($transaction_info['payment_status'] == 'paid'): ?>
                    <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Paid</span>
                    <?php else: ?>
                    <span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i> Unpaid</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($transaction_info['payment_status'] == 'paid'): ?>
                <div class="mt-1.5 pt-1.5 border-t border-blue-200/50 grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-gray-600">Date</span>
                        <p class="font-medium text-gray-800"><?php echo date('d-m-Y', strtotime($transaction_info['payment_date'])); ?></p>
                    </div>
                    <div>
                        <span class="text-gray-600">Mode</span>
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($transaction_info['payment_mode']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Thank You Note -->
            <div class="px-3 py-2 flex items-center border-t border-b border-gray-100 info-bar">
                <div class="text-blue-600 mr-2 float">
                    <i class="fas fa-heart-circle-check text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700">Thank you for your business!</p>
                    <p class="text-xs text-gray-500">We appreciate your trust in our services ✨</p>
                </div>
            </div>
            
            <!-- Action buttons -->
            <div class="p-3 space-y-2">
                <a href="Jewellerspage.php?licence_no=<?php echo urlencode($licence_no); ?>" class="block w-full py-2.5 px-4 btn-primary text-center font-medium rounded-lg text-white shadow-sm flex items-center justify-center">
                    <i class="fas fa-tachometer-alt mr-1.5"></i> Go to Dashboard
                </a>
                
                <!-- Always show HUID button but disable if no data -->
                <button onclick="checkHuidData()" class="block w-full py-2.5 px-4 <?php echo $has_huid_data ? 'btn-success' : 'btn-disabled'; ?> text-center font-medium rounded-lg text-white shadow-sm flex items-center justify-center">
                    <i class="fas fa-id-card mr-1.5"></i> View HUID Details
                    <?php if (!$has_huid_data): ?>
                    <i class="fas fa-lock ml-1.5 text-xs"></i>
                    <?php endif; ?>
                </button>
            </div>
        </div>
        
        <?php else: ?>
        <!-- No Request Information -->
        <div class="card p-4 text-center animate__animated animate__fadeIn" style="margin-top: 2rem;">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gold-gradient mb-3 shadow-lg animate__animated animate__pulse animate__infinite">
                <i class="fas fa-gem text-white text-xl"></i>
            </div>
            <h2 class="text-xl font-bold mb-1 text-gray-800"><?php echo $greeting; ?>, <?php echo $jeweller_name; ?></h2>
            <p class="text-gray-600 text-sm mb-4">Welcome to Mahalaxmi Hallmarking Centre</p>
            
            <!-- View Counter for Welcome Page -->
            <div class="mb-4">
                <span class="chip" id="welcomeViewCounterChip">
                    <i class="fas fa-eye chip-icon"></i>
                    <span id="welcomeViewCountValue">-</span> page visits
                </span>
            </div>
            
            <div class="glow rounded-lg overflow-hidden">
                <a href="jewellers_pannel.php?licence_no=<?php echo urlencode($licence_no); ?>" class="block py-2.5 px-4 btn-primary text-center font-medium text-white">
                    View Dashboard <i class="fas fa-arrow-right ml-1.5"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Page Views above footer -->
        <div class="w-full flex justify-center mt-6 mb-2">
            <span class="chip bg-blue-50 text-blue-700 text-sm font-semibold" id="footerPageViews">
                <i class="fas fa-eye chip-icon"></i>
                <span id="footerPageViewsValue">-</span> Page Views
            </span>
        </div>
        
        <!-- Company Logo & Credits -->
      <div class="mt-6 text-center px-4">
    <div class="flex flex-col items-center space-y-2 text-gray-500 text-xs sm:text-sm">
        
        <!-- Logo Image -->
        <img src="icons/mhc.png" alt="Mahalaxmi Hallmarking Centre Logo" class="w-20 h-20 object-contain mb-1" />

        <!-- Main Title -->
        <div class="flex items-center">
            <i class="fas fa-shield-halved text-blue-500 mr-2"></i>
            <span class="font-semibold text-base sm:text-lg">Mahalaxmi Hallmarking Centre &copy; <?php echo date('Y'); ?></span>
        </div>

        <!-- Powered By -->
        <div class="flex items-center text-[11px] sm:text-xs text-gray-400">
            <i class="fas fa-code text-green-500 mr-2"></i>
            <span>Powered by Hallmark Pro App | Developed & Maintained by 
                <a href="https://prosenjittechhub.com/" target="_blank" class="text-green-600 hover:underline font-semibold">
                    Prosenjit Tech Hub
                </a>
            </span>
        </div>
    </div>
</div>

</div>

</div>

    </div>

    <script>
        // Page view counter function
        function incrementViewCounter() {
            const licenceNo = "<?php echo addslashes($licence_no); ?>";
            const jewellerName = "<?php echo addslashes($jeweller_details['Jewellers_Name'] ?? ''); ?>";
            const hasRequestInfo = <?php echo $request_info ? 'true' : 'false'; ?>;
            const pageType = hasRequestInfo ? 'request_details' : 'welcome_page';
            
            if (!licenceNo) return;
            
            console.log('Incrementing page view counter for:', jewellerName, 'Page type:', pageType);
            
            const formData = new FormData();
            formData.append('action', 'increment_view');
            formData.append('licence_no', licenceNo);
            formData.append('jeweller_name', jewellerName);
            formData.append('page_type', pageType);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('View counter response:', data);
                if (data.status === 'success') {
                    // Update both possible view counter elements
                    const viewCountElement = document.getElementById('viewCountValue');
                    const viewCounterChip = document.getElementById('viewCounterChip');
                    const welcomeViewCountElement = document.getElementById('welcomeViewCountValue');
                    const welcomeViewCounterChip = document.getElementById('welcomeViewCounterChip');
                    
                    if (viewCountElement && viewCounterChip) {
                        viewCountElement.textContent = data.view_count;
                        viewCounterChip.style.display = 'inline-flex';
                    }
                    
                    if (welcomeViewCountElement && welcomeViewCounterChip) {
                        welcomeViewCountElement.textContent = data.view_count;
                        welcomeViewCounterChip.style.display = 'inline-flex';
                    }
                    
                    console.log('Page view count updated to:', data.view_count);
                    const footerPageViewsValue = document.getElementById('footerPageViewsValue');
                    if (footerPageViewsValue) {
                        footerPageViewsValue.textContent = data.view_count;
                    }
                } else {
                    console.error('View counter error:', data.message);
                }
            })
            .catch(error => {
                console.error('Error incrementing view counter:', error);
            });
        }

        // Show JewelEntry modal
        function showJewelEntryModal() {
            console.log('Attempting to show JewelEntry modal...');
            const modal = document.getElementById('jewelEntryModal');
            const modalContent = document.getElementById('modalContent');
            if (!modal || !modalContent) {
                console.error('Modal or modalContent not found!');
                return;
            }
            modal.classList.remove('hidden');
            // Animate in immediately with smooth transition
            requestAnimationFrame(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            });
            console.log('Modal should now be visible.');
        }

        // Hide JewelEntry modal
        function hideJewelEntryModal() {
            const modal = document.getElementById('jewelEntryModal');
            const modalContent = document.getElementById('modalContent');
            
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // HUID data check function
        function checkHuidData() {
            const hasHuidData = <?php echo $has_huid_data ? 'true' : 'false'; ?>;
            const requestNo = "<?php echo $request_no; ?>";
            
            if (hasHuidData) {
                // Add loading animation
                document.body.classList.add('cursor-wait');
                const btn = event.currentTarget;
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1.5"></i> Loading...';
                
                setTimeout(() => {
                    window.location.href = "https://prosenjittechhub.com/jewelentry/huid_data.php?request_no=" + encodeURIComponent(requestNo);
                }, 500);
            } else {
                // Show alert dialog for no HUID data
                document.getElementById('alertOverlay').classList.remove('hidden');
            }
        }

        // Close alert dialog
        document.getElementById('closeAlert').addEventListener('click', function() {
            document.getElementById('alertOverlay').classList.add('hidden');
        });

        // Close JewelEntry modal
        document.getElementById('closeJewelEntryModal').addEventListener('click', function() {
            hideJewelEntryModal();
        });

        // Close modal when clicking outside
        document.getElementById('jewelEntryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideJewelEntryModal();
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Increment page view counter
            incrementViewCounter();
            // Show JewelEntry modal immediately for quick access
            setTimeout(() => {
                console.log('Triggering JewelEntry modal immediately...');
                showJewelEntryModal();
            }, 200);
        });
    </script>
</body>
</html>
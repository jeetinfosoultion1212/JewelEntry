<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['id'])) {
   header("Location: login.php");
   exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
   error_log("Database connection failed: " . $conn->connect_error);
   die("Connection failed: " . $conn->connect_error);
}

$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.City
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();
$defaultImage = 'public/uploads/user.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Assign New Loan - JewelEntry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <link rel="stylesheet" href="css/add.css">
    <style>
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }
        
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .toast.success {
            background-color: #10B981;
        }
        
        .toast.error {
            background-color: #EF4444;
        }
        
        .toast i {
            font-size: 18px;
        }

        /* Specific styles for new_loan_assignment.php */
        #collateralItemsDisplayContainer {
            border: 1px dashed #ccc;
            padding: 10px;
            border-radius: 8px;
            min-height: 50px;
            margin-top: 10px;
            background-color: #f9f9f9;
        }
        .collateral-item-display {
            background-color: #e0f2f7; /* Light blue background */
            border: 1px solid #b3e5fc; /* Slightly darker blue border */
            border-radius: 5px;
            padding: 8px;
            margin-bottom: 5px;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .collateral-item-display span {
            color: #2196f3; /* Blue text */
            font-weight: 500;
        }
        .collateral-item-display .remove-display-item {
            background: none;
            border: none;
            color: #ef5350; /* Red for remove button */
            cursor: pointer;
            font-size: 1.1rem;
        }
        .section-card.disabled {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body data-firm-id="<?php echo $firm_id; ?>" class="text-gray-800">
    <!-- Header -->
    <div class="header-gradient p-1 text-white shadow-lg sticky top-0 z-50">
        <div class="enhanced-header">
            <div class="flex items-center">
                <i class="fas fa-gem mr-2 text-xl"></i>
                <div>
                    <div class="font-bold text-sm"><?php echo htmlspecialchars($userInfo['FirmName']); ?></div>
                    <div class="text-xs opacity-80">JewelEntry 0.02</div>
                </div>
            </div>
            <div class="user-info">
                <div class="text-right">
                    <div class="font-medium text-xs"><?php echo htmlspecialchars($userInfo['Name']); ?></div>
                    <div class="text-xs opacity-80"><?php echo htmlspecialchars($userInfo['Role']); ?></div>
                </div>
                <img src="<?php echo !empty($userInfo['image_path']) ? htmlspecialchars($userInfo['image_path']) : 'assets/default-avatar.png'; ?>"
                    alt="User" class="user-avatar">
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Main Content for New Loan Assignment -->
    <div id="new-loan-assignment-content" class="form-container w-full lg:w-3/4 mx-auto p-4">
        <div class="bg-white p-2 rounded-lg shadow-sm mb-5 hover-card">
            <form id="newLoanForm">
                <!-- Step 1: Customer Details -->
                <div class="section-card source-section bg-gradient-to-br from-blue-50 to-blue-100 mb-4 relative" style="--input-focus-color: #3b82f6;">
                    <div class="section-title text-blue-700">
                        <i class="fas fa-user-tie"></i> Step 1: Select Customer
                    </div>
                    <div class="field-container relative">
                        <input type="text"
                            id="customerNameSearch"
                            class="input-field font-xs font-bold py-1 pl-6 pr-10 w-full bg-white border border-blue-200 hover:border-blue-300 focus:border-blue-400 rounded-md"
                            placeholder="Search customer by name or phone...">
                        <i class="fas fa-user field-icon text-blue-500"></i>
                        <button type="button" class="absolute right-0 top-0 h-full px-3 flex items-center justify-center text-blue-600 hover:text-blue-800" onclick="showCustomerModal()">
                            <i class="fas fa-plus"></i>
                        </button>
                        <div id="customerSearchDropdown" class="customer-dropdown">
                            <!-- Customer list will appear here -->
                        </div>
                    </div>
                    <div id="selectedCustomerInfoDisplay" class="bg-gradient-to-br from-white via-green-50 to-green-100 p-2 rounded-xl border-2 border-dashed border-green-300 shadow-sm mt-2 hidden">
                        <div class="text-sm font-semibold text-green-700 mb-1 flex items-center gap-2">
                            <i class="fas fa-info-circle text-green-500"></i>
                            <span>Selected Customer:</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                            <div><span class="text-gray-500">Name:</span> <span id="customerNameDisplay" class="font-medium ml-1">-</span></div>
                            <div><span class="text-gray-500">Phone:</span> <span id="customerPhoneDisplay" class="font-medium ml-1">N/A</span></div>
                        </div>
                        <!-- Hidden input to hold the selected customer ID -->
                        <input type="hidden" id="selectedCustomerId" name="customer_id" value="">
                    </div>
                </div>

                <!-- Step 2: Collateral Details -->
                <div id="collateralDetailsSection" class="section-card collateral-theme mb-4 disabled" style="--input-focus-color: #10b981;">
                    <div class="section-title text-green-800 flex justify-between items-center">
                        <span><i class="fas fa-gem"></i> Step 2: Collateral Details</span>
                        <button type="button" id="addCollateralItemInputBtn" class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-3 rounded-md flex items-center">
                            <i class="fas fa-plus mr-1"></i> Add Item
                        </button>
                    </div>
                    <div id="collateralItemInputArea" class="mb-4">
                        <!-- Input fields for a single collateral item will be dynamically loaded here by JS -->
                    </div>
                    <div id="collateralItemsDisplayContainer">
                        <!-- Added collateral items will be displayed here dynamically by JS -->
                        <p class="text-gray-500 text-center">No collateral items added yet.</p>
                    </div>
                    <div class="mt-4 text-right">
                        <span class="font-bold text-gray-700">Total Collateral Value:</span>
                        <span id="totalCollateralValueDisplay" class="font-bold text-lg text-green-600">Rs. 0.00</span>
                    </div>
                </div>

                <!-- Step 3: Loan Details -->
                <div id="loanDetailsSection" class="section-card loan-theme mb-4 disabled" style="--input-focus-color: #8b5cf6;">
                    <div class="section-title text-purple-800">
                        <i class="fas fa-money-bill-wave"></i> Step 3: Loan Details
                    </div>
                    <div class="field-grid grid-cols-2 gap-2">
                        <div class="field-col">
                            <div class="field-label">Loan Amount (Rs)</div>
                            <div class="field-container">
                                <input type="number"
                                    id="loanAmount"
                                    name="loanAmount"
                                    class="input-field font-xs font-bold py-1 pl-6 bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md w-full"
                                    placeholder="Enter amount" step="0.01">
                                <i class="fas fa-rupee-sign field-icon text-purple-500"></i>
                            </div>
                        </div>

                        <div class="field-col">
                            <div class="field-label">Interest Rate (%)</div>
                            <div class="field-container">
                                <input type="number"
                                    id="interestRate"
                                    name="interestRate"
                                    class="input-field font-xs font-bold py-1 pl-6 bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md w-full"
                                    placeholder="Enter rate" step="0.01">
                                <i class="fas fa-percent field-icon text-purple-500"></i>
                            </div>
                        </div>

                        <div class="field-col">
                            <div class="field-label">Duration (Months)</div>
                            <div class="field-container">
                                <input type="number"
                                    id="loanDuration"
                                    name="loanDuration"
                                    class="input-field font-xs font-bold py-1 pl-6 bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md w-full"
                                    placeholder="Enter duration">
                                <i class="fas fa-calendar-alt field-icon text-purple-500"></i>
                            </div>
                        </div>

                        <div class="field-col">
                            <div class="field-label">Start Date</div>
                            <div class="field-container">
                                <input type="date"
                                    id="startDate"
                                    name="startDate"
                                    class="input-field font-xs font-bold py-1 pl-6 pr-1 appearance-none bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md w-full">
                                <i class="fas fa-calendar field-icon text-purple-500"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="createLoanBtn"
                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-medium py-2 px-4 rounded-lg shadow-sm hover:shadow-md transition-all duration-200">
                    <i class="fas fa-save mr-2"></i>Create Loan
                </button>
            </form>
        </div>
    </div>

    <!-- Camera Modal (Keep as is, shared with loans.js functionality) -->
    <div id="cameraModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl p-4 w-11/12 max-w-md">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold">Capture Image</h3>
                <button id="closeCameraModal" class="text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <div class="relative w-full overflow-hidden rounded-md bg-gray-200 mb-3" style="padding-top: 75%;">
                <video id="cameraFeed" class="absolute top-0 left-0 w-full h-full object-cover" autoplay playsinline></video>
                <canvas id="cameraCanvas" class="absolute top-0 left-0 w-full h-full object-cover hidden"></canvas>
            </div>
            <div class="flex justify-center space-x-3">
                <button id="startCamera" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md text-sm"><i class="fas fa-play mr-2"></i>Start Camera</button>
                <button id="takePicture" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-md text-sm hidden"><i class="fas fa-camera mr-2"></i>Take Picture</button>
                <button id="retakePicture" class="bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded-md text-sm hidden"><i class="fas fa-redo mr-2"></i>Retake</button>
                <button id="savePicture" class="bg-purple-500 hover:bg-purple-600 text-white py-2 px-4 rounded-md text-sm hidden"><i class="fas fa-save mr-2"></i>Save</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast hidden">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage">Success!</span>
    </div>

    <!-- Footer / Bottom Navigation -->
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

    <!-- JavaScript for this page -->
    <script src="js/new_loan_assignment.js"></script>
</body>
</html>
<?php $conn->close(); ?> 
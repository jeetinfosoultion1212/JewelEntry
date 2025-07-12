<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

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
// Additional debugging for database connection
if ($conn->connect_error) {
   error_log("Database connection failed: " . $conn->connect_error);
   die("Connection failed: " . $conn->connect_error);
}
error_log("Database connection successful");

// Debug function to log errors without breaking JSON responses
function debug_log($message, $data = null) {
  $log_file = 'loan_debug.log'; // Changed log file name
  $timestamp = date('Y-m-d H:i:s');
  $log_message = "[$timestamp] $message";
  
  if ($data !== null) {
      $log_message .= ": " . json_encode($data);
  }
  
  file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

// Function to save captured image
function saveBase64Image($base64Image, $loanId) {
  try {
      $uploadDir = 'uploads/loans/';
      
      // Create directory if it doesn't exist
      if (!file_exists($uploadDir)) {
          mkdir($uploadDir, 0777, true);
      }
      
      // Remove the data URI scheme part
      $base64Image = preg_replace('#^data:image/\w+;base64,#i', '', $base64Image);
      
      // Decode the base64 string
      $imageData = base64_decode($base64Image);
      
      // Generate unique filename
      $newFileName = 'loan_' . $loanId . '_' . time() . '_captured.jpg';
      $targetFilePath = $uploadDir . $newFileName;
      
      // Save the image
      if (file_put_contents($targetFilePath, $imageData)) {
          return $targetFilePath;
      } else {
          return false;
      }
  } catch (Exception $e) {
      debug_log("Error saving captured image", $e->getMessage());
      return false;
  }
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
$defaultImage = 'public/uploads/user.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Jewelry Management System</title>
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

    <!-- Tab Menu -->
    <div class="container mx-auto px-4 py-8">
        <!-- Tab Navigation -->
        <div class="flex border-b border-gray-200 mb-6">
            <button class="tab-btn active px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600" data-tab="loans-list">
                <i class="fas fa-list-ul mr-2"></i>Loans List
            </button>
            <button class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" data-tab="new-loan">
                <i class="fas fa-plus-circle mr-2"></i>New Loan
            </button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Loans List Tab -->
            <div id="loans-list" class="tab-pane active">
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <!-- Search and Filter -->
                    <div class="flex flex-wrap gap-4 mb-4">
                        <div class="flex-1 min-w-[200px]">
                            <input type="text" id="loanSearch" placeholder="Search by customer name, phone or loan ID..." 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex gap-2">
                            <select id="statusFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Status</option>
                                <option value="ACTIVE">Active</option>
                                <option value="COMPLETED">Completed</option>
                                <option value="OVERDUE">Overdue</option>
                            </select>
                            <button id="refreshLoans" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Loans Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loan Details</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EMI Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="loansTableBody">
                                <!-- Loans will be loaded here dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- New Loan Tab -->
            <div id="new-loan" class="tab-pane hidden">
                <div class="form-container w-full lg:w-3/4 mx-auto p-4">
                    <div class="bg-white p-2 rounded-lg shadow-sm mb-5 hover-card">
                        <form id="loanForm">
                            <!-- Customer Search Section -->
                            <div class="section-card source-section bg-gradient-to-br from-blue-50 to-blue-100 mb-1 relative" style="--input-focus-color: #3b82f6;">
                                <div class="section-title text-blue-700">
                                    <i class="fas fa-user-tie"></i> Customer Details
                                </div>
                                <div class="field-container relative">
                                    <input type="text"
                                        id="customerName"
                                        class="input-field font-xs font-bold py-1 pl-6 pr-10 w-full bg-white border border-blue-200 hover:border-blue-300 focus:border-blue-400 rounded-md"
                                        placeholder="Search customer...">
                                    <i class="fas fa-user field-icon text-blue-500"></i>
                                    <button type="button" class="absolute right-0 top-0 h-full px-3 flex items-center justify-center text-blue-600 hover:text-blue-800" onclick="showCustomerModal()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <div id="customerDropdown" class="customer-dropdown">
                                        <!-- Customer list will appear here -->
                                    </div>
                                </div>
                                <!-- Customer Info Display (read-only) - Compact View -->
                                <div id="customerInfoDisplay" class="bg-gradient-to-br from-white via-green-50 to-green-100 p-2 rounded-xl border-2 border-dashed border-green-300 shadow-sm mt-2 hidden">
                                    <div class="text-sm font-semibold text-green-700 mb-1 flex items-center gap-2">
                                        <i class="fas fa-info-circle text-green-500"></i>
                                        <span>Customer Info</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                                        <div><span class="text-gray-500">Name:</span> <span id="customerNameDisplay" class="font-medium ml-1">-</span></div>
                                        <div><span class="text-gray-500">Phone:</span> <span id="customerPhoneDisplay" class="font-medium ml-1">-</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Loan Details Section -->
                            <div class="section-card loan-theme mb-1" style="--input-focus-color: #8b5cf6;">
                                <div class="section-title text-purple-800">
                                    <i class="fas fa-money-bill-wave"></i> Loan Details
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

                            <!-- Collateral Details Section -->
                            <div class="section-card collateral-theme mb-1" style="--input-focus-color: #10b981;">
                                <div class="section-title text-green-800 flex justify-between items-center">
                                    <span><i class="fas fa-gem"></i> Collateral Details</span>
                                    <button type="button" id="addCollateralItemBtn" class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-3 rounded-md flex items-center">
                                        <i class="fas fa-plus mr-1"></i> Add Item
                                    </button>
                                </div>
                                <div id="collateralItemsContainer">
                                    <!-- Collateral Item Template will be inserted here by JavaScript -->
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
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast hidden">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage">Success!</span>
    </div>

    <!-- Camera Modal -->
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

    <style>
        .tab-btn {
            transition: all 0.3s ease;
        }
        .tab-btn.active {
            color: #2563eb;
            border-bottom: 2px solid #2563eb;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .loan-card {
            transition: all 0.3s ease;
        }
        .loan-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons and panes
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                
                // Add active class to clicked button and corresponding pane
                button.classList.add('active');
                document.getElementById(button.dataset.tab).classList.add('active');
            });
        });

        // Function to load loans
        function loadLoans() {
            const searchTerm = document.getElementById('loanSearch').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            fetch(`api/get_loans.php?search=${searchTerm}&status=${statusFilter}`)
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('loansTableBody');
                    tableBody.innerHTML = '';
                    
                    data.forEach(loan => {
                        const row = document.createElement('tr');
                        row.className = 'loan-card hover:bg-gray-50';
                        
                        // Calculate EMI status
                        const today = new Date();
                        const nextDueDate = new Date(loan.next_emi_due_date);
                        const isOverdue = nextDueDate < today && loan.status === 'ACTIVE';
                        
                        row.innerHTML = `
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">${loan.customer_name}</div>
                                        <div class="text-sm text-gray-500">${loan.customer_phone}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-900">Loan #${loan.id}</div>
                                <div class="text-sm text-gray-500">Amount: ₹${loan.principal_amount}</div>
                                <div class="text-sm text-gray-500">EMI: ₹${loan.emi_amount}/month</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        ${isOverdue ? 'bg-red-100 text-red-800' : 
                                        loan.status === 'COMPLETED' ? 'bg-green-100 text-green-800' : 
                                        'bg-blue-100 text-blue-800'}">
                                        ${isOverdue ? 'Overdue' : loan.status}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500">
                                    Next EMI: ${loan.next_emi_due_date}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <button onclick="viewLoanDetails(${loan.id})" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button onclick="recordPayment(${loan.id})" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-money-bill-wave"></i> Pay
                                </button>
                            </td>
                        `;
                        
                        tableBody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error loading loans:', error);
                    showToast('error', 'Failed to load loans');
                });
        }

        // Load loans on page load
        document.addEventListener('DOMContentLoaded', loadLoans);

        // Search and filter functionality
        document.getElementById('loanSearch').addEventListener('input', loadLoans);
        document.getElementById('statusFilter').addEventListener('change', loadLoans);
        document.getElementById('refreshLoans').addEventListener('click', loadLoans);

        // View loan details
        function viewLoanDetails(loanId) {
            window.location.href = `loan_details.php?id=${loanId}`;
        }

        // Record payment
        function recordPayment(loanId) {
            window.location.href = `record_payment.php?loan_id=${loanId}`;
        }
    </script>
</body>
</html>
<?php $conn->close(); ?> 
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set header to JSON for all AJAX responses
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    try {
        if ($_POST['action'] === 'add_loan') {
            // Get form data
            $customerId = $_POST['customerId'] ?? null; // Assume customerId will be passed
            $loanAmount = $_POST['loanAmount'];
            $interestRate = $_POST['interestRate'];
            $loanDuration = $_POST['loanDuration'];
            $startDate = $_POST['startDate'];
            $itemType = $_POST['itemType'];
            $itemWeight = $_POST['itemWeight'];
            $collateralDescription = $_POST['collateralDescription'] ?? null;
            
            // Handle multiple collateral items
            $collateralItems = $_POST['collateralItems'] ?? [];
            
            // Calculate total collateral value (for loan table)
            $totalCollateralValue = 0;
            foreach ($collateralItems as $item) {
                $totalCollateralValue += (float)($item['calculatedValue'] ?? 0);
            }

            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert into loans table
                $sql = "INSERT INTO loans (
                    firm_id, customer_id, loan_amount, interest_rate, loan_duration_months, 
                    start_date, collateral_value, created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed for loans table: " . $conn->error);
                }

                $stmt->bind_param(
                    "iiddddis",
                    $firm_id, $customerId, $loanAmount, $interestRate, $loanDuration,
                    $startDate, $totalCollateralValue, $user_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed for loans table: " . $stmt->error);
                }
                $loanId = $conn->insert_id;
                
                // Insert into loan_collateral_items table for each item
                foreach ($collateralItems as $index => $item) {
                    $materialType = $item['materialType'] ?? '';
                    $purity = $item['purity'] ?? 0;
                    $weight = $item['weight'] ?? 0;
                    $ratePerGram = $item['ratePerGram'] ?? 0;
                    $calculatedValue = $item['calculatedValue'] ?? 0;
                    $description = $item['description'] ?? null;
                    
                    // Handle image upload for each collateral item
                    $imagePath = null;
                    if (isset($_FILES['collateralItems']['name'][$index]['image']) && $_FILES['collateralItems']['error'][$index]['image'] === UPLOAD_ERR_OK) {
                        $fileTmpPath = $_FILES['collateralItems']['tmp_name'][$index]['image'];
                        $fileName = $_FILES['collateralItems']['name'][$index]['image'];
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        $newFileName = 'loan_' . $loanId . '_item_' . $index . '_' . uniqid() . '.' . $fileExtension;
                        $uploadDir = 'uploads/loans/collateral/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $dest_path = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $imagePath = $dest_path;
                        } else {
                            throw new Exception("Failed to move uploaded image for collateral item " . $index);
                        }
                    } elseif (isset($_POST['collateralItems'][$index]['image_data']) && !empty($_POST['collateralItems'][$index]['image_data'])) {
                        // Handle base64 image data from camera capture
                        $imageData = $_POST['collateralItems'][$index]['image_data'];
                        $imagePath = saveBase64Image($imageData, $loanId . '_item_' . $index); // Pass loan ID and item index for unique name
                        if (!$imagePath) {
                            throw new Exception("Failed to save captured image for collateral item " . $index);
                        }
                    }

                    $itemSql = "INSERT INTO loan_collateral_items (
                        loan_id, material_type, purity, weight, rate_per_gram, calculated_value, description, image_path, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                    )";
                    
                    $itemStmt = $conn->prepare($itemSql);
                    if (!$itemStmt) {
                        throw new Exception("Prepare failed for collateral item " . $index . ": " . $conn->error);
                    }

                    $itemStmt->bind_param(
                        "isddddss",
                        $loanId, $materialType, $purity, $weight, $ratePerGram, $calculatedValue, $description, $imagePath
                    );
                    
                    if (!$itemStmt->execute()) {
                        throw new Exception("Execute failed for collateral item " . $index . ": " . $itemStmt->error);
                    }
                }
                
                $conn->commit();
                
                $response['success'] = true;
                $response['message'] = "Loan created successfully!";
                $response['data'] = [
                    'loan_id' => $loanId
                ];
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            
        } elseif ($_POST['action'] === 'get_loans') {
            // TODO: Implement fetching loan list
            $response['success'] = true;
            $response['message'] = 'Loans list fetched (placeholder).';
            $response['data'] = [];
        }
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = "Error: " . $e->getMessage();
        debug_log("Error in AJAX handler", $e->getMessage());
    }
    
    echo json_encode($response);
    exit;
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
    <div class="mb-1">
        <div class="flex border-b border-gray-200 bg-white sticky top-8 z-40">
            <button id="formTab" class="tab-btn flex-1 py-2 px-2 text-center font-medium text-xs tab-active">
                <i class="fas fa-file-invoice mr-1"></i> New Loan
            </button>
            <button id="listTab" class="tab-btn flex-1 py-2 px-2 text-center font-medium text-xs text-gray-500 hover:text-gray-700">
                <i class="fas fa-list-ul mr-1"></i> Loan List
            </button>
        </div>
    </div>

    <!-- New Loan Tab Content -->
    <div id="new-loan" class="tab-content">
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
                    <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-medium py-2 px-4 rounded-lg shadow-sm hover:shadow-md transition-all duration-200">
                        <i class="fas fa-save mr-2"></i>Create Loan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Loan List Tab Content -->
    <div id="loan-list" class="tab-content" hidden>
        <div class="p-4">
            <!-- Search and Filter -->
            <div class="flex gap-2 mb-4">
                <div class="flex-1 relative">
                    <input type="text"
                        id="searchLoans"
                        class="w-full text-xs h-8 pl-8 pr-4 bg-white border border-gray-200 rounded-md"
                        placeholder="Search loans..." />
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <button class="px-3 py-1 bg-white border border-gray-200 rounded-md text-xs text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>

            <!-- Loans List -->
            <div id="loansList" class="space-y-3">
                <div class="text-center text-gray-500 py-10">No loans to display.</div>
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

    <!-- JavaScript -->
  <script src="js/loans.js"></script>
</body>
</html>
<?php $conn->close(); ?> 
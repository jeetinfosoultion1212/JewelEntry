<?php
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
if ($conn->connect_error) {
   die("Connection failed: " . $conn->connect_error);
}

// Fetch user and firm details
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Jewelry Management System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
 <link rel="stylesheet" href="css/add-stock.css" />
</head>
<body class="pb-20">
  <!-- Header -->
  <div class="header-gradient p-3 text-white font-bold shadow-lg">
 <div class="flex items-center justify-between">
   <div class="flex items-center space-x-2">
 <i class="fas fa-gem text-white-600 text-lg"></i>
 <span class="font-medium text-sm"><?php echo htmlspecialchars($userInfo['FirmName']); ?></span>
 
</div>

   <div class="flex items-center gap-3">
    
    
     <div class="text-right text-xs">
       <div class="font-medium"><?php echo htmlspecialchars($userInfo['Name']); ?></div>
       <div class="text-white/80"><?php echo htmlspecialchars($userInfo['Role']); ?></div>
     </div>
     <div class="w-8 h-8 rounded-full bg-white/20 overflow-hidden">
       <?php if (!empty($userInfo['image_path'])): ?>
         <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="Profile" class="w-full h-full object-cover">
       <?php else: ?>
         <div class="w-full h-full flex items-center justify-center text-white">
           <i class="fas fa-user"></i>
         </div>
       <?php endif; ?>
     </div>
   </div>
   
 </div>
</div>

  <div class="bg-gradient-to-r from-blue-50 to-purple-50 p-1">
    <!-- Enhanced Stats Section -->
    <div class="bg-gradient-to-r from-blue-50/80 to-indigo-50/80 p-1.5 border-b border-blue-100/50">
     

      <div id="stockStatsSummary" class="flex gap-1.5 pb-0.5 overflow-x-auto scrollbar-thin scrollbar-thumb-blue-200/50 scrollbar-track-transparent">
        <!-- Stats cards will be dynamically inserted here -->
      </div>
    </div>
  </div>

  <!-- Tab Navigation -->
  <div class="flex justify-around items-center bg-white p-2 shadow-sm sticky-tabs hidden">
    <button class="tab-btn active flex items-center justify-center" data-tab="add-stock">
      <i class="fas fa-boxes mr-1 text-green-500"></i> Stock
    </button>
    <button class="tab-btn flex items-center justify-center" data-tab="items-list">
      <i class="fas fa-list mr-1 text-purple-500"></i> Item List
    </button>
    <button class="tab-btn flex items-center justify-center" data-tab="entry-form">
      <i class="fas fa-plus-circle mr-1 text-blue-500"></i> Item
    </button>
  </div>

  <!-- Add Stock Tab -->
  <div id="add-stock" class="tab-content active">
    <div class="p-1 compact-form">
      <!-- Material Details Section -->
      <div class="section-card bg-amber-100">
        <div class="section-title text-amber-800 mb-1 text-sm font-bold">
          <i class="fas fa-boxes"></i> Material Details
        </div>
        <div class="field-row">
          <div class="field-col">
            <div class="field-label">Material Type</div>
            <div class="field-container">
              <select id="stockMetalType" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-amber-200 rounded-md" onchange="loadStockNames()">
                <option value="">Select Metal</option>
                <option value="Gold">Gold</option>
                <option value="Silver">Silver</option>
                <option value="Platinum">Platinum</option>
              </select>
              <i class="fas fa-coins field-icon text-amber-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Stock Name</div>
            <div class="field-container">
              <input type="text" id="stockName" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md" placeholder="Enter stock name">
              <i class="fas fa-box field-icon text-amber-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Quantity</div>
            <div class="field-container">
              <input type="number" id="stockQuantity" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md" value="1" min="1">
              <i class="fas fa-hashtag field-icon text-amber-500"></i>
            </div>
          </div>
        </div>

        <div class="field-row">
          <div class="field-col">
            <div class="field-label">Purity</div>
            <div class="field-container">
              <select id="stockPurity" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-amber-200 rounded-md" onchange="handlePuritySelection()">
                <option value="">Select Purity</option>
                <option value="99.99">24K (99.99%)</option>
                <option value="92.0">22K (92.0%)</option>
                <option value="84.0">20K (84.0%)</option>
                <option value="76.0">18K (76.0%)</option>
                <option value="59.0">14K (59.0%)</option>
                <option value="custom">Custom Purity</option>
              </select>
              <i class="fas fa-certificate field-icon text-amber-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Custom Purity</div>
            <div class="field-container">
              <input type="number" id="customPurity" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md hidden" placeholder="Enter custom purity" step="0.01" min="0" max="100" />
              <i class="fas fa-percentage field-icon text-amber-500"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Current Inventory Section -->
      <div class="section-card bg-blue-100">
        <div class="section-title text-blue-800 mb-2 text-sm font-bold">
          <i class="fas fa-chart-line"></i> Current Inventory
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div class="flex items-center">
            <i class="fas fa-box text-blue-500 mr-2"></i>
            <span class="text-xs">Current Stock: <span id="currentStock" class="font-medium">0.00g</span></span>
          </div>
          <div class="flex items-center">
            <i class="fas fa-warehouse text-blue-500 mr-2"></i>
            <span class="text-xs">Remaining: <span id="remainingStock" class="font-medium">0.00g</span></span>
          </div>
        </div>
      </div>

      <!-- Price Details Section -->
      <div class="section-card bg-green-100 p-3 mb-3 rounded-lg">
        <div class="section-title text-green-800 mb-2 text-sm font-bold">
          <i class="fas fa-calculator"></i> Price Details
        </div>
        <div class="field-row">
          <div class="field-col">
            <div class="field-label">Weight (g)</div>
            <div class="field-container">
              <input type="number" id="stockWeight" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
                  placeholder="Enter weight" step="0.01" />
              <i class="fas fa-weight-scale field-icon text-green-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Market Rate (24K/gm)</div>
            <div class="field-container">
              <input type="number" id="stockRate" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
                  placeholder="Enter base rate" step="0.01" />
              <i class="fas fa-tag field-icon text-green-500"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Purchase Details Section -->
      <div class="section-card bg-purple-100 p-3 mb-3 rounded-lg">
        <div class="section-title text-purple-800 mb-2 text-sm font-bold">
          <i class="fas fa-shopping-cart"></i> Purchase Details
        </div>
        <div class="field-row">
          <div class="field-col">
            <label class="inline-flex items-center">
              <input type="checkbox" id="isPurchase" class="form-checkbox h-5 w-5 text-purple-600" onchange="togglePurchaseFields()">
              <span class="ml-2 text-sm">Record as Purchase</span>
            </label>
          </div>
        </div>

        <div id="purchaseFields" class="hidden">
          <div class="field-row">
            <div class="field-col">
              <div class="field-label">Supplier</div>
              <div class="field-container">
                <select id="supplier" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-purple-200 rounded-md">
                  <option value="">Select Supplier</option>
                </select>
                <i class="fas fa-user-tie field-icon text-purple-500"></i>
              </div>
            </div>
            <div class="field-col">
              <div class="field-label">Buying Rate Per Gram</div>
              <div class="field-container">
                <input type="number" id="buyingPurity" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" 
                    placeholder="Rate based on purity" step="0.01" min="0" />
                <i class="fas fa-rupee-sign field-icon text-purple-500"></i>
              </div>
              <div class="text-[10px] text-purple-600 mt-0.5">
                Auto-calculated based on purity. Can be modified.
              </div>
            </div>
          </div>

          <div class="field-row">
            <div class="field-col">
              <div class="field-label">Paid Amount</div>
              <div class="field-container">
                <input type="number" id="paidAmount" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" placeholder="Enter paid amount" step="0.01" min="0" />
                <i class="fas fa-rupee-sign field-icon text-purple-500"></i>
              </div>
            </div>
            <div class="field-col">
              <div class="field-label">Payment Mode</div>
              <div class="field-container">
                <select id="paymentMode" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md">
                  <option value="">Select Payment Mode</option>
                  <option value="Cash">Cash</option>
                  <option value="Bank">Bank</option>
                  <option value="UPI">UPI</option>
                  <option value="Cheque">Cheque</option>
                </select>
                <i class="fas fa-credit-card field-icon text-purple-500"></i>
              </div>
            </div>
            <div class="field-col">
              <div class="field-label">Payment Status</div>
              <div class="field-container">
                <input type="text" id="paymentStatus" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" readonly />
                <i class="fas fa-info-circle field-icon text-purple-500"></i>
              </div>
            </div>
            <div class="field-col">
              <div class="field-label">Invoice Number</div>
              <div class="field-container">
                <input type="text" id="invoiceNumber" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" placeholder="Enter invoice number" />
                <i class="fas fa-file-invoice field-icon text-purple-500"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Section -->
      <div class="section-card bg-gray-50 p-3 mb-3 rounded-lg">
        <div class="grid grid-cols-2 gap-2">
          <div class="flex items-center">
            <i class="fas fa-calculator text-gray-500 mr-2"></i>
            <span class="text-sm">Material Cost: <span id="stockMaterialCost" class="font-medium">₹0.00</span></span>
          </div>
          <div class="flex items-center justify-end">
            <i class="fas fa-coins text-gray-500 mr-2"></i>
            <span class="text-sm">Total: <span id="stockTotalPrice" class="font-medium text-green-600">₹0.00</span></span>
          </div>
          <div id="balanceContainer" class="col-span-2 hidden flex items-center">
            <i class="fas fa-balance-scale text-gray-500 mr-2"></i>
            <span class="text-sm">Balance: <span id="balanceAmount" class="font-medium">₹0.00</span></span>
          </div>
        </div>
      </div>

      <!-- Action Button -->
      <button id="addStock" class="w-full flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-2 px-4 rounded-md">
        <i class="fas fa-plus-circle mr-2"></i> Add Metal Stock
      </button>
    </div>
  </div>

 

  
  
<nav class="bottom-nav">
   <!-- Home -->
   <a href="main.php" class="nav-item">
     <i class="nav-icon fas fa-home"></i>
     <span class="nav-text">Home</span>
   </a>
   
  <a href="add.php" class="nav-item ">
     <i class="nav-icon fa-solid fa-gem"></i>
     <span class="nav-text">Add</span>
   </a>
    <a href="add-stock.php" class="nav-item active ">
     <i class="nav-icon fa-solid fa-store"></i>
     <span class="nav-text">Stock</span>
   </a>
 
 <a href="order.php" class="nav-item">
     <i class="nav-icon fa-solid fa-user-tag"></i>
     <span class="nav-text">Orders</span>
   </a>
  
 <a href="sale-entry.php" class="nav-item">
     <i class="nav-icon fas fa-shopping-cart"></i>
     <span class="nav-text">Sale</span>
   </a>
   <!-- Sales List -->
  

   <!-- Reports -->
   <a href="reports.php" class="nav-item">
     <i class="nav-icon fas fa-chart-pie"></i>
     <span class="nav-text">Reports</span>
   </a>
 </nav>
  <!-- JavaScript -->

  <!-- Add these before closing body tag -->
<script src="assets/js/stock-stats.js"></script>
<script src="assets/js/tabs.js"></script>
<script src="js/add-stock.js"></script>



</body>
</html>
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
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f5f7fa;
      overflow-x: hidden;
      max-width: 100%;
    }
    
    .header-gradient {
      background: linear-gradient(to right, #4361ee, #3a0ca3);
    }
    .stats-container {
      display: flex;
      overflow-x: auto;
      scrollbar-width: thin;
      scrollbar-color: rgba(155, 155, 155, 0.5) transparent;
      padding-bottom: 2px;
    }
    .stats-container::-webkit-scrollbar {
      height: 4px;
    }
    .stats-container::-webkit-scrollbar-track {
      background: transparent;
    }
    .stats-container::-webkit-scrollbar-thumb {
      background-color: rgba(155, 155, 155, 0.5);
      border-radius: 20px;
    }
    .stats-card {
      min-width: 110px;
      border-radius: 10px;
      transition: all 0.3s ease;
    }
    .tab-container {
      border-radius: 10px;
      overflow: hidden;
    }
    .sticky-tabs {
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .tab-btn {
      position: relative;
      transition: all 0.2s ease;
      font-weight: 500;
      font-size: 0.85rem;
      padding: 8px 12px;
    }
    .tab-btn.active {
      color: #4361ee;
      font-weight: 600;
    }
    .tab-btn.active::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: #4361ee;
    }
    .tab-content {
      display: none;
      padding: 0 10px;
      max-width: 100%;
    }
    .tab-content.active {
      display: block;
    }
    .table-container {
      border-radius: 2px;
      overflow-x: auto;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .table-row {
      transition: all 0.2s ease;
    }
    .table-row:hover {
      background-color: #f8fafc;
    }
    .action-btn {
      width: 28px;
      height: 28px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      transition: all 0.2s ease;
    }
    .action-btn:hover {
      transform: translateY(-2px);
    }
    .view-btn {
      background-color: #e0f2fe;
      color: #0284c7;
    }
    .edit-btn {
      background-color: #e0f7fa;
      color: #0891b2;
    }
    .delete-btn {
      background-color: #fee2e2;
      color: #ef4444;
    }
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
    }
    .modal-content {
      background-color: #fefefe;
      margin: 5% auto;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      width: 95%;
      max-width: 500px;
    }
    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }
    .close:hover {
      color: black;
    }
    
    /* Toast notification */
    #toast {
      z-index: 1100;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }
    
    /* Add Supplier Modal Styles */
    #addSupplierModal .modal-content {
      margin: 10% auto;
      max-width: 400px;
      animation: modalFadeIn 0.3s;
      border-radius: 10px;
      padding: 15px;
      background: white;
    }
    
    @keyframes modalFadeIn {
      from {opacity: 0; transform: translateY(-20px);}
      to {opacity: 1; transform: translateY(0);}
    }
    
    #addSupplierModal .close {
      color: #aaa;
      font-size: 24px;
    }
    
    #addSupplierModal .close:hover {
      color: #6366F1;
    }
    
    #addSupplierForm .field-row {
      margin-bottom: 8px;
    }
    
    #addSupplierForm .field-icon {
      color: #6366F1;
    }
    
    #addSupplierForm button[type="submit"] {
      background-color: #6366F1;
      color: white;
      border: none;
      border-radius: 5px;
      padding: 10px;
      font-weight: bold;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    
    #addSupplierForm button[type="submit"]:hover {
      background-color: #4F46E5;
    }
  
   /* Bottom Navigation */
   .bottom-nav {
     position: fixed;
     bottom: 0;
     left: 0;
     right: 0;
     background: linear-gradient(to bottom, rgba(255,255,255,0.95), rgba(255,255,255,1));
     padding: 0.5rem;
     display: flex;
     justify-content: space-around;
     align-items: center;
     border-top: 1px solid rgba(59, 130, 246, 0.1);
     backdrop-filter: blur(8px);
     box-shadow: 0 -4px 20px rgba(59, 130, 246, 0.15);
     z-index: 40;
   }

   .nav-item {
     display: flex;
     flex-direction: column;
     align-items: center;
     justify-content: center;
     padding: 0.5rem;
     border-radius: 0.75rem;
     transition: all 0.2s;
     min-width: 64px;
     color: #64748b;
     position: relative;
     cursor: pointer;
   }

   .nav-item.active {
     color: #3b82f6;
     background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1));
   }

   .nav-item:hover {
     color: #3b82f6;
     transform: translateY(-2px);
   }

   .nav-icon {
     font-size: 1.25rem;
     margin-bottom: 0.25rem;
   }

   .nav-text {
     font-size: 0.75rem;
     font-weight: 500;
   }

   .cart-badge {
     position: absolute;
     top: -5px;
     right: -5px;
     background: #ef4444;
     color: white;
     font-size: 0.65rem;
     font-weight: 600;
     min-width: 18px;
     height: 18px;
     padding: 0 4px;
     border-radius: 999px;
     border: 2px solid white;
     display: flex;
     align-items: center;
     justify-content: center;
   }
    .input-field {
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 8px 12px;
      width: 100%;
      transition: all 0.2s ease;
    }
    .input-field:focus {
      outline: none;
      border-color: #4361ee;
      box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }
    .btn-primary {
      background: #4361ee;
      color: white;
      border-radius: 8px;
      padding: 8px 16px;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    .btn-primary:hover {
      background: #3a56d4;
      transform: translateY(-2px);
    }
    .btn-secondary {
      background: #e5e7eb;
      color: #4b5563;
      border-radius: 8px;
      padding: 8px 16px;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    .btn-secondary:hover {
      background: #d1d5db;
      transform: translateY(-2px);
    }
    .section-card {
      border-radius: 10px;
      margin-bottom: 8px;
      padding: 8px;
    }
    .material-section {
      background-color: #fff8e1;
      border-color: #ffecb3;
    }
    .weight-section {
      background-color: #e3f2fd;
      border-color: #bbdefb;
    }
    .stone-section {
      background-color: #f3e5f5;
      border-color: #e1bee7;
    }
    .making-section {
      background-color: #e8f5e9;
      border-color: #c8e6c9;
    }
    .image-section {
      background-color: #eeeeee;
      border-color: #e0e0e0;
    }
    .section-title {
      font-size: 0.75rem;
      font-weight: 600;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
    }
    .section-title i {
      margin-right: 5px;
    }
    .field-row {
      display: flex;
      gap: 6px;
      margin-bottom: 6px;
      flex-wrap: wrap;
    }
    .field-col {
      flex: 1;
      min-width: 110px;
    }
    .field-label {
      font-size: 0.7rem;
      color: #4b5563;
      margin-bottom: 2px;
    }
    .field-input {
      width: 100%;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 4px 8px;
      font-size: 0.8rem;
    }
    .field-input:focus {
      outline: none;
      border-color: #4361ee;
      box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.1);
    }
    .field-select {
      width: 100%;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 4px 8px;
      font-size: 0.8rem;
      background-color: white;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 8px center;
      background-size: 16px;
    }
    .field-select:focus {
      outline: none;
      border-color: #4361ee;
      box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.1);
    }
    .field-icon {
      position: absolute;
      left: 6px;
      top: 50%;
      transform: translateY(-50%);
      color: #6b7280;
      font-size: 0.8rem;
    }
    .field-input-icon {
      padding-left: 24px;
    }
    .field-container {
      position: relative;
    }
    .compact-form .section-card {
      padding: 6px;
      margin-bottom: 6px;
    }
    .compact-form .field-row {
      gap: 4px;
      margin-bottom: 4px;
    }
    .compact-form .field-label {
      font-size: 0.65rem;
      margin-bottom: 1px;
    }
    .compact-form .field-input,
    .compact-form .field-select {
      padding: 3px 6px;
      font-size: 0.75rem;
    }
    .compact-form .section-title {
      font-size: 0.7rem;
      margin-bottom: 4px;
    }
    .suggestions-container {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 0.375rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      max-height: 200px;
      overflow-y: auto;
      z-index: 50;
    }
    .suggestions-container:empty {
      display: none;
    }
    .suggestions-container > div {
      padding: 0.5rem;
      cursor: pointer;
    }
    .suggestions-container > div:hover {
      background-color: #f3f4f6;
    }
    @media (max-width: 640px) {
      .field-row {
        flex-direction: row;
        gap: 6px;
      }
      .field-col {
        flex: 1;
        min-width: 90px;
        width: auto;
      }
      .modal-content {
        margin: 2% auto;
        padding: 15px;
        width: 95%;
      }
      .section-card {
        padding: 6px;
      }
      .preview-item {
        width: 60px;
        height: 60px;
      }
    }
    /* Custom flex classes for better field layout */
    .flex-1 {
      flex: 1;
    }
    .flex-2 {
      flex: 2;
    }
    .flex-3 {
      flex: 3;
    }
    .flex-auto {
      flex: auto;
    }
    .filter-dropdown:hover .filter-content {
      display: block;
    }
    /* Products tab styles */
    .product-card {
      border-radius: 10px;
      overflow: hidden;
      transition: all 0.3s ease;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .product-image {
      height: 180px;
      width: 100%;
      object-fit: cover;
    }
    .product-badge {
      position: absolute;
      top: 10px;
      right: 10px;
      padding: 4px 8px;
      border-radius: 20px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
    }
    /* Collapsible section styles */
    .collapsible-section {
      transition: all 0.3s ease;
    }
    .collapsible-content {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
    }
    .collapsible-content.expanded {
      max-height: 500px;
    }
    .collapsible-toggle {
      cursor: pointer;
    }
    .collapsible-toggle i {
      transition: transform 0.3s ease;
    }
    .collapsible-toggle.expanded i.fa-chevron-down {
      transform: rotate(180deg);
    }
    
    /* Add to your existing styles */
.stats-card {
    -webkit-tap-highlight-color: transparent;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.modal-content {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

/* Allow text selection only in the transaction table */
.transaction-table {
    user-select: text;
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
}

/* Add touch feedback for mobile */
@media (hover: none) {
    .stats-card:active {
        transform: scale(0.98);
    }
}

/* Improve scrolling on mobile */
.scrollable-content {
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
}
  </style>
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
              <select id="stockMetalType" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-amber-200 rounded-md" >
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
              <select id="stockPurity" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-amber-200 rounded-md" >
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
              <input type="checkbox" id="isPurchase" class="form-checkbox h-5 w-5 text-purple-600" >
              <span class="ml-2 text-sm">Record as Purchase</span>
            </label>
          </div>
        </div>

        <div id="purchaseFields" class="hidden">
          <div class="field-row">
            <div class="field-col">
              <div class="field-label">Supplier</div>
              <div class="field-container flex">
                <div class="flex-grow">
                  <select id="supplier" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-purple-200 rounded-md w-full">
                    <option value="">Select Supplier</option>
                  </select>
                  <i class="fas fa-user-tie field-icon text-purple-500"></i>
                </div>
                <button id="addSupplierBtn" class="ml-1 bg-purple-500 hover:bg-purple-600 text-white rounded-md h-7 w-7 flex items-center justify-center">
                  <i class="fas fa-plus text-xs"></i>
                </button>
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
  <!-- Add Supplier Modal -->
  
<div id="addSupplierModal" class="modal">
    <div class="modal-content bg-white rounded-lg shadow-xl max-w-md mx-auto mt-20 p-4">
      <div class="flex justify-between items-center mb-3 pb-2 border-b border-gray-200">
        <h3 class="text-sm font-semibold text-gray-700 flex items-center">
          <i class="fas fa-user-plus mr-2 text-blue-500 text-xs"></i>Add New Supplier
        </h3>
        <span class="close text-gray-400 hover:text-gray-600 text-lg cursor-pointer">&times;</span>
      </div>
      
      <form id="addSupplierForm" class="space-y-3">
        <!-- Supplier Name - Mandatory -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">
            Supplier Name <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input 
              type="text" 
              id="supplierName" 
              name="name" 
              class="w-full pl-8 pr-3 py-2 text-xs border border-gray-300 rounded-md focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none bg-gray-50" 
              placeholder="Enter supplier name"
              required
            >
            <i class="fas fa-building absolute left-2.5 top-2.5 text-gray-400 text-xs"></i>
          </div>
        </div>

        <!-- Two column layout for State and Phone -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">State</label>
            <div class="relative">
              <input 
                type="text" 
                id="supplierState" 
                name="state" 
                class="w-full pl-8 pr-3 py-2 text-xs border border-gray-300 rounded-md focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none bg-gray-50" 
                placeholder="State"
              >
              <i class="fas fa-map-marker-alt absolute left-2.5 top-2.5 text-gray-400 text-xs"></i>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Phone</label>
            <div class="relative">
              <input 
                type="tel" 
                id="supplierPhone" 
                name="phone" 
                class="w-full pl-8 pr-3 py-2 text-xs border border-gray-300 rounded-md focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none bg-gray-50" 
                placeholder="Phone number"
              >
              <i class="fas fa-phone absolute left-2.5 top-2.5 text-gray-400 text-xs"></i>
            </div>
          </div>
        </div>

        <!-- GST Number -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">GST Number</label>
          <div class="relative">
            <input 
              type="text" 
              id="supplierGst" 
              name="gst" 
              class="w-full pl-8 pr-3 py-2 text-xs border border-gray-300 rounded-md focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none bg-gray-50" 
              placeholder="GST number"
            >
            <i class="fas fa-receipt absolute left-2.5 top-2.5 text-gray-400 text-xs"></i>
          </div>
        </div>

        <!-- Address -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Address</label>
          <div class="relative">
            <textarea 
              id="supplierAddress" 
              name="address" 
              rows="2"
              class="w-full pl-8 pr-3 py-2 text-xs border border-gray-300 rounded-md focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none bg-gray-50 resize-none" 
              placeholder="Complete address"
            ></textarea>
            <i class="fas fa-map-marker-alt absolute left-2.5 top-2.5 text-gray-400 text-xs"></i>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
          <button 
            type="submit" 
            class="w-full flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium py-2.5 px-4 rounded-md transition-colors duration-200"
          >
            <i class="fas fa-save mr-2 text-xs"></i> 
            Save Supplier
          </button>
        </div>
      </form>
    </div>
</div>

  <!-- Toast Notification -->
  <div id="toast" class="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg transform transition-transform duration-300 translate-x-full flex items-center">
    <i class="fas fa-check-circle mr-2"></i>
    <span id="toastMessage">Supplier added successfully!</span>
  </div>

  <script src="js/add-stock.js"></script>
  <!-- Add these before closing body tag -->
<script src="assets/js/stock-stats.js"></script>
<script src="assets/js/tab.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tabs
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            switchTab(this.dataset.tab);
        });
    });

    // Load initial stats
    loadStockStats();
});
</script>
</body>
</html>
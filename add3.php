<?php
/**
 * Jewelry Inventory Management System
 * Complete implementation with FIFO inventory management
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$servername = "localhost";
$username = "u176143338_retailstore";
$password = "Rontik10@";
$dbname = "u176143338_retailstore";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


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
      padding-bottom: 4px;
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
    .cropper-container {
      width: 100%;
      max-width: 500px;
      margin: 0 auto;
    }
    .image-preview {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 8px;
    }
    .preview-item {
      position: relative;
      width: 70px;
      height: 70px;
      border-radius: 8px;
      overflow: hidden;
    }
    .preview-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .remove-image {
      position: absolute;
      top: 2px;
      right: 2px;
      background: rgba(255,255,255,0.7);
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 12px;
      color: #ef4444;
    }
    .bottom-nav {
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
    }
    .bottom-nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 6px 0;
      color: #6b7280;
      transition: all 0.2s ease;
      min-width: 60px;
    }
    .bottom-nav-item.active {
      color: #4361ee;
    }
    .bottom-nav-item:hover {
      color: #4361ee;
    }
    .bottom-nav-item i {
      font-size: 1.25rem;
      margin-bottom: 2px;
    }
    #moreMenu {
      min-width: 180px;
      border: 1px solid #e5e7eb;
      border-radius: 0.5rem;
    }
    #moreMenu a {
      text-decoration: none;
      color: #374151;
      font-size: 0.875rem;
    }
    #moreMenu a:hover {
      background-color: #f3f4f6;
    }
    #moreMenu i {
      width: 20px;
      text-align: center;
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
  </style>
</head>
<body class="pb-20">
  <!-- Header -->
  <div class="header-gradient p-3 text-center text-white font-bold shadow-lg flex items-center justify-center">
    <i class="fas fa-gem mr-2"></i> Jewelry Management System
  </div>

  <div class="bg-gradient-to-r from-blue-50 to-purple-50 p-2">
    <div class="flex items-center justify-between mb-1">
      <div class="text-xs font-semibold text-gray-700">
        <i class="fas fa-chart-pie text-blue-500 mr-1"></i>Stock Overview
      </div>
      <button onclick="loadStockStats()" class="text-xs text-blue-500 hover:text-blue-600">
        <i class="fas fa-sync-alt"></i>
      </button>
    </div>
    <div id="stockStatsSummary" class="flex overflow-x-auto gap-2 pb-1 scrollbar-thin">
      <!-- Stats will be loaded here -->
    </div>
  </div>
  <!-- Tab Navigation -->
  <div class="flex justify-around items-center bg-white p-2 shadow-sm sticky-tabs">
    <button class="tab-btn active flex items-center justify-center" data-tab="entry-form">
      <i class="fas fa-plus-circle mr-1 text-blue-500"></i> Item
    </button>
    <button class="tab-btn flex items-center justify-center" data-tab="items-list">
      <i class="fas fa-list mr-1 text-purple-500"></i> Item List
    </button>
    <button class="tab-btn flex items-center justify-center" data-tab="add-stock">
      <i class="fas fa-boxes mr-1 text-green-500"></i> Stock
    </button>
   
  </div>

  <!-- Entry Form Tab -->
  <div id="entry-form" class="tab-content active">
    <div class="p-1 compact-form">
      <!-- Source Selection Section -->
      <div class="section-card bg-blue-50 border border-blue-200">
        <div class="section-title text-blue-800">
          <i class="fas fa-file-invoice"></i> Source Selection
        </div>
        <div class="field-row">
          <div class="field-col">
            <div class="field-label">Source Type</div>
            <div class="field-container">
              <select id="sourceType" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-blue-200 rounded-md field-input-icon" onchange="handleSourceTypeChange()">
                <option value="Purchase" selected>Purchase</option>
                <option value="Manufacture">Manufacture</option>
                <option value="Other">Other</option>
              </select>
              <i class="fas fa-tag field-icon text-blue-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Source ID</div>
            <div class="field-container relative">
              <input type="text" id="sourceId" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="Type to search..." autocomplete="off" />
              <i class="fas fa-search field-icon text-blue-500"></i>
              <div id="sourceIdSuggestions" class="suggestions-container absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-50 max-h-48 overflow-y-auto hidden"></div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Material Details Section -->
      <div class="section-card material-section mb-1">
        <div class="section-title text-amber-800">
          <i class="fas fa-coins"></i> Material Details
        </div>
        <div class="field-grid grid-cols-3 gap-2">
          <div class="field-col">
            <div class="field-label">Product Name</div>
            <div class="field-container">
              <input type="text" id="productName" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-gray-200 rounded-md" placeholder="Type to search...">
              <i class="fas fa-tag field-icon text-blue-500"></i>
              <div id="productNameSuggestions" class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg hidden max-h-48 overflow-y-auto"></div>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">ID</div>
            <div class="field-container">
              <input type="text" id="productId" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-gray-200 rounded-md" readonly />
              <i class="fas fa-barcode field-icon text-blue-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Purity</div>
            <div class="field-container">
              <input type="number" id="purity" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md" placeholder="e.g. 92.0" step="0.1" min="0" max="100" />
              <i class="fas fa-certificate field-icon text-amber-500"></i>
            </div>
          </div>
        </div>

        <div class="field-grid grid-cols-3 gap-2 mt-2">
          <div class="field-col">
            <div class="field-label">Quantity</div>
            <div class="field-container">
              <input type="number" id="quantity" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" value="1" min="1" />
              <i class="fas fa-hashtag field-icon text-blue-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Tray No.</div>
            <div class="field-container">
              <input type="text" id="trayNumber" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" placeholder="Enter tray number" />
              <i class="fas fa-box field-icon text-green-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">HUID</div>
            <div class="field-container">
              <input type="text" id="huidCode" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="HUID (Optional)" />
              <i class="fas fa-fingerprint field-icon text-blue-500"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Weight Details Section -->
      <div class="section-card weight-section mb-1">
        <div class="section-title text-blue-800">
          <i class="fas fa-weight-scale"></i> Weight Details
        </div>
        <div class="field-grid grid-cols-3 gap-2">
          <div class="field-col">
            <div class="field-label">Gross Weight (g)</div>
            <div class="field-container">
              <input type="number" id="grossWeight" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="Gross wt" step="0.01" value="0" />
              <i class="fas fa-weight-scale field-icon text-blue-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Less Weight (g)</div>
            <div class="field-container">
              <input type="number" id="lessWeight" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="Less wt" step="0.01" value="0" />
              <i class="fas fa-minus-circle field-icon text-red-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Net Weight (g)</div>
            <div class="field-container">
              <input type="number" id="netWeight" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="Net wt" step="0.01" value="0" readonly />
              <i class="fas fa-balance-scale field-icon text-green-500"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Stone Details Section - Collapsible -->
      <div class="section-card stone-section collapsible-section">
        <div class="section-title text-purple-800 collapsible-toggle flex justify-between items-center" onclick="toggleSection('stoneSection')">
          <div>
            <i class="fas fa-gem"></i> Stone Details
          </div>
          <i class="fas fa-chevron-down text-purple-600"></i>
        </div>
        <div id="stoneSection" class="collapsible-content">
          <div class="field-row">
            <div class="field-col">
              <div class="field-label">Stone Type</div>
              <div class="field-container">
               <select id="stoneType" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-purple-200 rounded-md" onchange="toggleStoneFields()">
                  <option value="None" selected>None</option>
                  <option value="Diamond">Diamond</option>
                  <option value="Ruby">Ruby</option>
                  <option value="Emerald">Emerald</option>
                  <option value="Sapphire">Sapphire</option>
                  <option value="Pearl">Pearl</option>
                  <option value="Mixed">Mixed</option>
                  <option value="Other">Other</option>
                </select>
                <i class="fas fa-gem field-icon text-purple-500 mr-2"></i>
              </div>
            </div>
            <div class="field-col">
              <div class="field-label">Stone Weight (ct)</div>
              <div class="field-container">
                 <input type="number" id="stoneWeight" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" placeholder="Weight" step="0.01" disabled />
                <i class="fas fa-weight field-icon text-purple-500"></i>
              </div>
            </div>
            <div class="field-col">
              <div class="field-label">Stone Quality</div>
              <div class="field-container">
                <select id="stoneQuality" class="field-select" disabled>
                  <option value="">Select Quality</option>
                  <option value="VVS">VVS</option>
                  <option value="VS">VS</option>
                  <option value="SI">SI</option>
                  <option value="I1">I1</option>
                  <option value="I2">I2</option>
                  <option value="I3">I3</option>
                  <option value="AAA">AAA</option>
                  <option value="AA">AA</option>
                  <option value="A">A</option>
                  <option value="B">B</option>
                </select>
                <i class="fas fa-star field-icon text-amber-500"></i>
              </div>
            </div>
            <div class="field-col">
              <div class="field-label">Stone Price</div>
              <div class="field-container">
               <input type="number" id="stonePrice" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" placeholder="Price" step="0.01" disabled />
                <i class="fas fa-rupee-sign field-icon text-green-500"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Making Charge Section -->
      <div class="section-card making-section">
        <div class="section-title text-green-800">
          <i class="fas fa-hammer"></i> Making Charge
        </div>
        <div class="field-row">
          <div class="field-col">
            <div class="field-label">Making Charge</div>
            <div class="field-container">
               <input type="number" id="makingCharge" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" placeholder="Making charge" step="0.01" />
              <i class="fas fa-rupee-sign field-icon text-green-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Charge Type</div>
            <div class="field-container">
            <select id="makingChargeType" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-green-200 rounded-md">
                <option value="fixed" selected>Fixed Amount</option>
                <option value="percentage">Percentage</option>
              </select>
              <i class="fas fa-percent field-icon text-green-500"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Images Section -->
     <div class="bg-green-50 p-2 rounded-md border border-green-200 mb-2">
    <div class="flex justify-between items-center mb-1">
      <label class="text-xs font-semibold text-green-800 flex items-center">
        <i class="fas fa-images mr-1"></i>Images
      </label>
      <div class="flex gap-1">
        <label for="productImages" class="cursor-pointer flex items-center justify-center border border-green-300 rounded-md py-0.5 px-2 bg-white text-green-600 text-xs font-bold">
          <i class="fas fa-plus mr-1 text-xs"></i>Add
        </label>
        <button id="captureBtn" class="cursor-pointer flex items-center justify-center border border-blue-300 rounded-md py-0.5 px-2 bg-white text-blue-600 text-xs font-bold">
          <i class="fas fa-camera text-xs"></i>
        </button>
        <button id="cropBtn" class="cursor-pointer flex items-center justify-center border border-purple-300 rounded-md py-0.5 px-2 bg-white text-purple-600 text-xs font-bold">
          <i class="fas fa-crop-alt text-xs"></i>
        </button>
      </div>
      <input type="file" id="productImages" accept="image/*" multiple class="hidden" />
    </div>
    <div id="imagePreview" class="grid grid-cols-4 gap-1 bg-white p-1 rounded-md border border-gray-200 min-h-8"></div>
  </div>
      <div class="mb-2">
    <div class="relative">
      <textarea id="description" class="input-field text-xs font-bold py-1 pl-7 h-7 resize-none bg-white border border-gray-200 rounded-md" placeholder="Enter description, features or notes..."></textarea>
      <div class="absolute left-0 top-0 h-full w-6 flex items-center justify-center bg-blue-50 border-r border-gray-200 rounded-l-md">
        <i class="fas fa-comment-alt text-blue-500 text-xs"></i>
      </div>
    </div>
  </div>

      <!-- Action Buttons -->
      <button id="addItem" class="w-full flex items-center justify-center bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white text-xs font-bold py-2 mb-4 px-4 rounded-md shadow-sm">
    <i class="fas fa-plus-circle mr-1"></i> Add Jewelry Item
  </button>
    </div>
  </div>

  <!-- Items List Tab -->
  <div id="items-list" class="tab-content">
    <div class="p-2">
      <div class="flex justify-between items-center mb-3">
        <h2 class="text-lg font-semibold text-gray-800">Jewelry Items</h2>
        <div class="flex gap-2">
          <div class="relative">
            <input type="text" id="searchItems" placeholder="Search items..." class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <i class="fas fa-search absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          </div>
          <div class="relative filter-dropdown">
            <button class="flex items-center px-2 py-1 text-sm bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
              <i class="fas fa-filter mr-1 text-gray-600"></i>
              <span>Filter</span>
            </button>
            <div class="filter-content absolute right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-10 hidden w-48">
              <div class="p-2">
                <div class="mb-2">
                  <label class="block text-xs font-medium text-gray-700 mb-1">Jewelry Type</label>
                  <select id="filterType" class="w-full px-2 py-1 text-sm border border-gray-300 rounded-md">
                    <option value="">All Types</option>
                    <!-- Types will be loaded dynamically -->
                  </select>
                </div>
                <div class="mb-2">
                  <label class="block text-xs font-medium text-gray-700 mb-1">Material</label>
                  <select id="filterMaterial" class="w-full px-2 py-1 text-sm border border-gray-300 rounded-md">
                    <option value="">All Materials</option>
                    <option value="Gold">Gold</option>
                    <option value="Silver">Silver</option>
                    <option value="Platinum">Platinum</option>
                  </select>
                </div>
                <button id="applyFilter" class="w-full mt-2 px-3 py-1 text-xs bg-blue-500 text-white rounded-md hover:bg-blue-600">Apply</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3" id="itemsGrid">
        <!-- Items will be loaded here -->
      </div>
      
      <div id="noItemsMessage" class="hidden text-center py-8">
        <i class="fas fa-box-open text-gray-400 text-4xl mb-2"></i>
        <p class="text-gray-500">No jewelry items found</p>
        <button class="mt-3 px-4 py-2 bg-blue-500 text-white text-sm rounded-md hover:bg-blue-600" onclick="switchTab('entry-form')">
          Add New Item
        </button>
      </div>
    </div>
  </div>

  <!-- Add Stock Tab -->
  <div id="add-stock" class="tab-content">
    <div class="p-1 compact-form">
      <!-- Material Details Section -->
      <div class="section-card bg-amber-100">
        <div class="section-title text-amber-800 mb-2 text-sm font-bold">
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
              <input type="number" id="stockWeight" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" placeholder="Enter weight" step="0.01" />
              <i class="fas fa-weight-scale field-icon text-green-500"></i>
            </div>
          </div>
          <div class="field-col">
            <div class="field-label">Rate (per gram)</div>
            <div class="field-container">
              <input type="number" id="stockRate" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" placeholder="Enter rate" step="0.01" />
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
              <div class="field-label">Buying Purity (%)</div>
              <div class="field-container">
                <input type="number" id="buyingPurity" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" placeholder="Enter buying purity (e.g. 92.0)" step="0.01" min="0" max="100" />
                <i class="fas fa-percentage field-icon text-purple-500"></i>
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

  
   

  <!-- Bottom Navigation -->
  <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 bottom-nav flex justify-around items-center py-1 z-50">
    <a href="home.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : ''; ?>">
      <i class="fas fa-home text-lg"></i>
      <span class="text-xs">Home</span>
    </a>
    
    <a href="sale-entry.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sale.php' ? 'active' : ''; ?>">
      <i class="fas fa-tags text-lg"></i>
      <span class="text-xs">Sale</span>
    </a>

    <a href="add.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'add.php' ? 'active' : ''; ?>">
      <i class="fas fa-plus-circle text-lg"></i>
      <span class="text-xs">Stock</span>
    </a>

    <div class="bottom-nav-item" id="moreBtn">
      <i class="fas fa-ellipsis-h text-lg"></i>
      <span class="text-xs">More</span>
    </div>
  </div>

  <!-- More Menu Modal -->
  <div id="moreMenu" class="fixed bottom-16 right-0 bg-white rounded-lg shadow-lg p-2 mr-2 hidden z-50">
    <div class="space-y-2">
      <a href="settings.php" class="flex items-center space-x-2 p-2 hover:bg-gray-100 rounded">
        <i class="fas fa-cog text-gray-600"></i>
        <span class="text-sm">Settings</span>
      </a>
      <a href="profile.php" class="flex items-center space-x-2 p-2 hover:bg-gray-100 rounded">
        <i class="fas fa-user text-gray-600"></i>
        <span class="text-sm">Profile</span>
      </a>
      <a href="reports.php" class="flex items-center space-x-2 p-2 hover:bg-gray-100 rounded">
        <i class="fas fa-chart-bar text-gray-600"></i>
        <span class="text-sm">Reports</span>
      </a>
      <hr class="my-1">
      <a href="logout.php" class="flex items-center space-x-2 p-2 hover:bg-red-50 text-red-600 rounded">
        <i class="fas fa-sign-out-alt"></i>
        <span class="text-sm">Logout</span>
      </a>
    </div>
  </div>

  <!-- Image Cropper Modal -->
  <div id="cropperModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2 class="text-lg font-bold mb-3">Crop Image</h2>
      <div class="cropper-container">
        <img id="cropperImage" src="/placeholder.svg" alt="Image to crop">
      </div>
      <div class="flex justify-end mt-3 space-x-2">
        <button id="cancelCrop" class="btn-secondary">Cancel</button>
        <button id="applyCrop" class="btn-primary">Apply Crop</button>
      </div>
    </div>
  </div>

  <!-- Import Data Modal -->
  <div id="importModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2 class="text-lg font-bold mb-3">Import Items</h2>
      <div class="mb-3">
        <div class="flex mb-2">
          <button id="csvImportBtn" class="bg-blue-100 text-blue-600 p-2 rounded-lg text-sm mr-2">
            <i class="fas fa-file-csv mr-1"></i> Import CSV
          </button>
          <button id="pasteDataBtn" class="bg-green-100 text-green-600 p-2 rounded-lg text-sm">
            <i class="fas fa-paste mr-1"></i> Paste Data
          </button>
        </div>
        <input type="file" id="csvFileInput" accept=".csv" class="hidden">
        <textarea id="pasteDataArea" class="w-full h-32 border border-gray-300 rounded-lg p-2 text-sm" placeholder="Paste your data here in format:&#10;1 Ring 1 H9F13D 3.44&#10;2 Ring 1 FGGGY2 2.42&#10;..."></textarea>
      </div>
      <div class="overflow-x-auto mb-3">
        <table class="w-full border text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="border px-2 py-1 text-left">#</th>
              <th class="border px-2 py-1 text-left">Type</th>
              <th class="border px-2 py-1 text-left">Quantity</th>
              <th class="border px-2 py-1 text-left">HUID</th>
              <th class="border px-2 py-1 text-left">Weight</th>
            </tr>
          </thead>
          <tbody id="previewTableBody"></tbody>
        </table>
      </div>
      <div class="flex justify-end space-x-2">
        <button id="cancelImport" class="btn-secondary">Cancel</button>
        <button id="confirmImport" class="btn-primary bg-green-500 hover:bg-green-600">Import Items</button>
      </div>
    </div>
  </div>

  <!-- Edit Item Modal -->
 
  <!-- Product View Modal -->
  <div id="productViewModal" class="modal">
    <div class="modal-content max-w-lg">
      <span class="close">&times;</span>
      <h2 class="text-lg font-bold mb-3" id="productViewTitle">Product Details</h2>
      <div id="productViewContent">
        <!-- Content will be dynamically added here -->
      </div>
      <div class="flex justify-end mt-3">
        <button class="btn-secondary close-view">Close</button>
      </div>
    </div>
  </div>

  <!-- Hidden fields for batch information -->
  <div style="display: none;">
    <input type="hidden" id="batchTransactionId" value="">
    <input type="hidden" id="batchSupplierId" value="">
    <input type="hidden" id="batchSupplierName" value="">
  </div>

  <!-- JavaScript -->
  <script>
    // Tab switching functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Get all tab buttons and content sections
      const tabButtons = document.querySelectorAll('.tab-btn');
      const tabContents = document.querySelectorAll('.tab-content');

      // Function to switch tabs
      function switchTab(tabId) {
        // Hide all tab contents
        tabContents.forEach(content => {
          content.classList.remove('active');
        });

        // Remove active class from all tab buttons
        tabButtons.forEach(button => {
          button.classList.remove('active');
        });

        // Show selected tab content
        const selectedTab = document.getElementById(tabId);
        if (selectedTab) {
          selectedTab.classList.add('active');
        }

        // Add active class to selected tab button
        const selectedButton = document.querySelector(`[data-tab="${tabId}"]`);
        if (selectedButton) {
          selectedButton.classList.add('active');
        }
        
        // Load tab-specific data
        if (tabId === 'items-list') {
          loadJewelryItems();
        } else if (tabId === 'entry-form') {
          // Initialize jewelry type suggestions
          initJewelryTypeSuggestions();
        } else if (tabId === 'add-stock') {
          loadStockStats();
        }
      }

      // Add click event listeners to tab buttons
      tabButtons.forEach(button => {
        button.addEventListener('click', () => {
          const tabId = button.getAttribute('data-tab');
          switchTab(tabId);
        });
      });

      // Initialize with first tab active
      if (tabButtons.length > 0) {
        const firstTabId = tabButtons[0].getAttribute('data-tab');
        switchTab(firstTabId);
      }
      
      // Make switchTab function globally available
      window.switchTab = switchTab;
    });

    // Collapsible sections
    function toggleSection(sectionId) {
      const content = document.getElementById(sectionId);
      const toggle = content.previousElementSibling;
      
      content.classList.toggle('expanded');
      toggle.classList.toggle('expanded');
    }
    
    // Calculate net weight
    function calculateNetWeight() {
      const grossWeight = parseFloat(document.getElementById('grossWeight').value) || 0;
      const lessWeight = parseFloat(document.getElementById('lessWeight').value) || 0;
      const netWeight = grossWeight - lessWeight;
      
      document.getElementById('netWeight').value = netWeight > 0 ? netWeight.toFixed(3) : 0;
    }
    
    // Toggle stone fields based on stone type selection
    function toggleStoneFields() {
      const stoneType = document.getElementById('stoneType').value;
      const stoneFields = ['stoneWeight', 'stoneQuality', 'stonePrice'];
      
      stoneFields.forEach(field => {
        const element = document.getElementById(field);
        if (stoneType === 'None') {
          element.disabled = true;
          element.value = '';
        } else {
          element.disabled = false;
        }
      });
      
      // Expand stone section if a stone type is selected
      if (stoneType !== 'None') {
        document.getElementById('stoneSection').classList.add('expanded');
        document.querySelector('.collapsible-toggle').classList.add('expanded');
      }
    }
    
    // Source type change handler
    function handleSourceTypeChange() {
      const sourceType = document.getElementById('sourceType').value;
      const sourceIdInput = document.getElementById('sourceId');
      const sourceIdSuggestions = document.getElementById('sourceIdSuggestions');
      
      // Clear previous values
      sourceIdInput.value = '';
      sourceIdSuggestions.innerHTML = '';
      sourceIdSuggestions.classList.add('hidden');
      
      // Set placeholder based on source type
      if (sourceType === 'Purchase') {
        sourceIdInput.placeholder = "Type supplier ID or purity...";
        fetchPurchaseSources();
      } else if (sourceType === 'Manufacture') {
        sourceIdInput.placeholder = "Type order ID or purity...";
        fetchManufactureSources();
      } else {
        sourceIdInput.placeholder = "Type source ID...";
      }
    }
    
    // Fetch purchase sources
    function fetchPurchaseSources() {
      fetch('stock_functions.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getPurchaseSources'
      })
      .then(response => response.json())
      .then(data => {
        if (Array.isArray(data)) {
          setupSourceSuggestions(data);
        }
      })
      .catch(error => console.error('Error fetching purchase sources:', error));
    }
    
    // Fetch manufacture sources
    function fetchManufactureSources() {
      fetch('stock_functions.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getManufactureSources'
      })
      .then(response => response.json())
      .then(data => {
        if (Array.isArray(data)) {
          setupSourceSuggestions(data);
        }
      })
      .catch(error => console.error('Error fetching manufacture sources:', error));
    }
    
    // Setup source suggestions
    function setupSourceSuggestions(sources) {
      const sourceIdInput = document.getElementById('sourceId');
      const sourceIdSuggestions = document.getElementById('sourceIdSuggestions');
      
      sourceIdInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        if (searchTerm.length < 2) {
          sourceIdSuggestions.classList.add('hidden');
          return;
        }
        
        const filteredSources = sources.filter(source => {
          return source.id.toLowerCase().includes(searchTerm) || 
                 source.name.toLowerCase().includes(searchTerm) ||
                 source.purity.toString().includes(searchTerm);
        });
        
        if (filteredSources.length > 0) {
          renderSourceSuggestions(filteredSources);
          sourceIdSuggestions.classList.remove('hidden');
        } else {
          sourceIdSuggestions.classList.add('hidden');
        }
      });
    }
    
    // Render source suggestions
    function renderSourceSuggestions(sources) {
      const sourceIdSuggestions = document.getElementById('sourceIdSuggestions');
      sourceIdSuggestions.innerHTML = '';
      
      sources.forEach(source => {
        const div = document.createElement('div');
        div.className = 'p-2 hover:bg-gray-100 cursor-pointer';
        div.innerHTML = `
          <div class="font-medium">${source.id} - ${source.name}</div>
          <div class="text-xs text-gray-500">Purity: ${source.purity}% | Remaining: ${source.remaining}g</div>
        `;
        
        div.addEventListener('click', function() {
          document.getElementById('sourceId').value = source.id;
          document.getElementById('purity').value = source.purity;
          sourceIdSuggestions.classList.add('hidden');
        });
        
        sourceIdSuggestions.appendChild(div);
      });
    }
    
    // Initialize jewelry type suggestions
    function initJewelryTypeSuggestions() {
      const jewelryTypeInput = document.getElementById('jewelryType');
      const jewelryTypeSuggestions = document.getElementById('jewelryTypeSuggestions');
      
      jewelryTypeInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        if (searchTerm.length < 2) {
          jewelryTypeSuggestions.classList.add('hidden');
          return;
        }
        
        fetch('stock_functions.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=searchJewelryType&search_term=${encodeURIComponent(searchTerm)}`
        })
        .then(response => response.json())
        .then(data => {
          if (Array.isArray(data)) {
            renderJewelryTypeSuggestions(data);
            jewelryTypeSuggestions.classList.remove('hidden');
          }
        })
        .catch(error => console.error('Error searching jewelry types:', error));
      });
      
      // Generate product ID when jewelry type is selected
      jewelryTypeInput.addEventListener('change', function() {
        if (this.value.trim()) {
          generateProductId(this.value.trim());
        }
      });
    }
    
    // Render jewelry type suggestions
    function renderJewelryTypeSuggestions(types) {
      const jewelryTypeSuggestions = document.getElementById('jewelryTypeSuggestions');
      jewelryTypeSuggestions.innerHTML = '';
      
      types.forEach(type => {
        const div = document.createElement('div');
        div.className = 'p-2 hover:bg-gray-100 cursor-pointer';
        
        if (type.is_new) {
          div.innerHTML = `<div><span class="font-medium">${type.name}</span> <span class="text-xs text-green-600 ml-1">(Create New)</span></div>`;
        } else {
          div.innerHTML = `<div class="font-medium">${type.name}</div>`;
        }
        
        div.addEventListener('click', function() {
          document.getElementById('jewelryType').value = type.name;
          jewelryTypeSuggestions.classList.add('hidden');
          
          if (type.is_new) {
            createJewelryType(type.name);
          } else {
            generateProductId(type.name);
          }
        });
        
        jewelryTypeSuggestions.appendChild(div);
      });
    }
    
    // Create new jewelry type
    function createJewelryType(name) {
      fetch('stock_functions.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=createJewelryType&name=${encodeURIComponent(name)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          console.log(`Created new jewelry type: ${name} with ID: ${data.id}`);
          generateProductId(name);
        }
      })
      .catch(error => console.error('Error creating jewelry type:', error));
    }
    
    // Generate product ID
    function generateProductId(jewelryType) {
      fetch('stock_functions.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=generateProductId&jewelry_type=${encodeURIComponent(jewelryType)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.product_id) {
          document.getElementById('productId').value = data.product_id;
        }
      })
      .catch(error => console.error('Error generating product ID:', error));
    }
    
    // Load jewelry items for the items list tab
    function loadJewelryItems() {
      fetch('stock_functions.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getJewelryItems'
      })
      .then(response => response.json())
      .then(data => {
        renderJewelryItems(data);
      })
      .catch(error => console.error('Error loading jewelry items:', error));
    }
    
    // Render jewelry items
    function renderJewelryItems(items) {
      const itemsGrid = document.getElementById('itemsGrid');
      const noItemsMessage = document.getElementById('noItemsMessage');
      
      itemsGrid.innerHTML = '';
      
      if (items.length === 0) {
        noItemsMessage.classList.remove('hidden');
        return;
      }
      
      noItemsMessage.classList.add('hidden');
      
      items.forEach(item => {
        const card = document.createElement('div');
        card.className = 'product-card bg-white overflow-hidden';
        
        let statusBadge = '';
        if (item.status === 'active') {
          statusBadge = '<span class="product-badge bg-green-100 text-green-800">Active</span>';
        } else if (item.status === 'sold') {
          statusBadge = '<span class="product-badge bg-red-100 text-red-800">Sold</span>';
        }
        
        card.innerHTML = `
          <div class="relative">
            <img src="${item.image_path || '/placeholder.svg?height=180&width=300'}" alt="${item.product_name}" class="product-image">
            ${statusBadge}
          </div>
          <div class="p-3">
            <div class="flex justify-between items-start">
              <div>
                <h3 class="font-semibold text-sm">${item.product_name}</h3>
                <p class="text-xs text-gray-500">${item.jewelry_type} - ${item.product_id}</p>
              </div>
              <div class="text-right">
                <p class="font-bold text-sm">${item.net_weight}g</p>
                <p class="text-xs text-gray-500">${item.purity}% ${item.material_type}</p>
              </div>
            </div>
            <div class="flex justify-between items-center mt-2">
              <div class="text-xs text-gray-600">
                ${item.stone_type !== 'None' ? `<span class="bg-purple-100 text-purple-800 px-1.5 py-0.5 rounded-full text-xs">${item.stone_type}</span>` : ''}
              </div>
              <div class="flex space-x-1">
                <button class="action-btn view-btn" onclick="viewItem('${item.id}')">
                  <i class="fas fa-eye text-xs"></i>
                </button>
                <button class="action-btn edit-btn" onclick="editItem('${item.id}')">
                  <i class="fas fa-edit text-xs"></i>
                </button>
              </div>
            </div>
          </div>
        `;
        
        itemsGrid.appendChild(card);
      });
    }
    
    // View item details
    function viewItem(itemId) {
      fetch('stock_functions.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=getItemDetails&id=${itemId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.id) {
          showItemDetails(data);
        }
      })
      .catch(error => console.error('Error fetching item details:', error));
    }
    
    // Show item details in modal
    function showItemDetails(item) {
      const modal = document.getElementById('productViewModal');
      const title = document.getElementById('productViewTitle');
      const content = document.getElementById('productViewContent');
      
      title.textContent = `${item.product_name} (${item.product_id})`;
      
      content.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <img src="${item.image_path || '/placeholder.svg?height=300&width=300'}" alt="${item.product_name}" class="w-full h-64 object-cover rounded-lg">
            <div class="grid grid-cols-4 gap-2 mt-2">
              ${item.additional_images ? item.additional_images.map(img => 
                `<img src="${img}" alt="Additional view" class="w-full h-16 object-cover rounded-lg cursor-pointer hover:opacity-80">`
              ).join('') : ''}
            </div>
          </div>
          <div>
            <div class="grid grid-cols-2 gap-y-2 text-sm">
              <div class="font-medium">Material:</div>
              <div>${item.material_type}</div>
              
              <div class="font-medium">Jewelry Type:</div>
              <div>${item.jewelry_type}</div>
              
              <div class="font-medium">Purity:</div>
              <div>${item.purity}%</div>
              
              <div class="font-medium">Gross Weight:</div>
              <div>${item.gross_weight}g</div>
              
              <div class="font-medium">Net Weight:</div>
              <div>${item.net_weight}g</div>
              
              ${item.stone_type !== 'None' ? `
                <div class="font-medium">Stone Type:</div>
                <div>${item.stone_type}</div>
                
                <div class="font-medium">Stone Weight:</div>
                <div>${item.stone_weight}ct</div>
                
                <div class="font-medium">Stone Quality:</div>
                <div>${item.stone_quality || 'N/A'}</div>
              ` : ''}
              
              <div class="font-medium">Making Charge:</div>
              <div>${item.making_charge} (${item.making_charge_type})</div>
              
              <div class="font-medium">Status:</div>
              <div><span class="px-2 py-0.5 rounded-full text-xs ${item.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${item.status}</span></div>
              
              <div class="font-medium">Created:</div>
              <div>${new Date(item.created_at).toLocaleDateString()}</div>
            </div>
            
            ${item.description ? `
              <div class="mt-3">
                <div class="font-medium text-sm">Description:</div>
                <p class="text-sm text-gray-600 mt-1">${item.description}</p>
              </div>
            ` : ''}
          </div>
        </div>
      `;
      
      modal.style.display = 'block';
      
      // Close modal when clicking close button
      const closeButtons = modal.querySelectorAll('.close, .close-view');
      closeButtons.forEach(btn => {
        btn.onclick = function() {
          modal.style.display = 'none';
        }
      });
      
      // Close modal when clicking outside
      window.onclick = function(event) {
        if (event.target === modal) {
          modal.style.display = 'none';
        }
      }
    }
    
    // Add jewelry item
    document.getElementById('addItem').addEventListener('click', function() {
      // Validate required fields
      if (!validateJewelryForm()) {
        return;
      }
      
      // Prepare form data
      const formData = new FormData();
      formData.append('action', 'addJewelryItem');
      formData.append('source_type', document.getElementById('sourceType').value);
      formData.append('source_id', document.getElementById('sourceId').value);
      formData.append('material_type', document.getElementById('materialType').value);
      formData.append('jewelry_type', document.getElementById('jewelryType').value);
      formData.append('product_id', document.getElementById('productId').value);
      formData.append('product_name', document.getElementById('productName').value);
      formData.append('purity', document.getElementById('purity').value);
      formData.append('quantity', document.getElementById('quantity').value);
      formData.append('tray_no', document.getElementById('trayNumber').value);
      formData.append('huid_code', document.getElementById('huidCode').value);
      formData.append('gross_weight', document.getElementById('grossWeight').value);
      formData.append('less_weight', document.getElementById('lessWeight').value);
      formData.append('net_weight', document.getElementById('netWeight').value);
      formData.append('stone_type', document.getElementById('stoneType').value);
      
      if (document.getElementById('stoneType').value !== 'None') {
        formData.append('stone_weight', document.getElementById('stoneWeight').value);
        formData.append('stone_quality', document.getElementById('stoneQuality').value);
        formData.append('stone_price', document.getElementById('stonePrice').value);
      }
      
      formData.append('making_charge', document.getElementById('makingCharge').value);
      formData.append('making_charge_type', document.getElementById('makingChargeType').value);
      formData.append('description', document.getElementById('description').value);
      formData.append('update_inventory', document.getElementById('updateInventory').checked ? '1' : '0');
      
      // Add images if any
      const imagePreview = document.getElementById('imagePreview');
      const images = imagePreview.querySelectorAll('img');
      
      if (images.length > 0) {
        images.forEach((img, index) => {
          // Convert data URL to blob
          if (img.src.startsWith('data:')) {
            const blob = dataURLtoBlob(img.src);
            formData.append(`images[${index}]`, blob, `image_${index}.jpg`);
          }
        });
      }
      
      // Submit form
      fetch('stock_functions.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Jewelry item added successfully!');
          resetJewelryForm();
          // Switch to items list tab to show the new item
          switchTab('items-list');
        } else {
          alert(data.error || 'Failed to add jewelry item');
        }
      })
      .catch(error => {
        console.error('Error adding jewelry item:', error);
        alert('An error occurred while adding the jewelry item');
      });
    });
    
    // Validate jewelry form
    function validateJewelryForm() {
      const requiredFields = [
        { id: 'jewelryType', name: 'Jewelry Type' },
        { id: 'productName', name: 'Product Name' },
        { id: 'productId', name: 'Product ID' },
        { id: 'purity', name: 'Purity' },
        { id: 'grossWeight', name: 'Gross Weight' }
      ];
      
      for (const field of requiredFields) {
        const element = document.getElementById(field.id);
        if (!element.value.trim()) {
          alert(`${field.name} is required`);
          element.focus();
          return false;
        }
      }
      
      // Validate stone fields if stone type is not None
      if (document.getElementById('stoneType').value !== 'None') {
        const stoneFields = [
          { id: 'stoneWeight', name: 'Stone Weight' },
          { id: 'stoneQuality', name: 'Stone Quality' }
        ];
        
        for (const field of stoneFields) {
          const element = document.getElementById(field.id);
          if (!element.value.trim()) {
            alert(`${field.name} is required when stone type is selected`);
            element.focus();
            return false;
          }
        }
      }
      
      return true;
    }
    
    // Reset jewelry form
    function resetJewelryForm() {
      document.getElementById('sourceType').value = 'Purchase';
      document.getElementById('sourceId').value = '';
      document.getElementById('jewelryType').value = '';
      document.getElementById('productName').value = '';
      document.getElementById('productId').value = '';
      document.getElementById('purity').value = '';
      document.getElementById('quantity').value = '1';
      document.getElementById('trayNumber').value = '';
      document.getElementById('huidCode').value = '';
      document.getElementById('grossWeight').value = '';
      document.getElementById('lessWeight').value = '';
      document.getElementById('netWeight').value = '';
      document.getElementById('stoneType').value = 'None';
      document.getElementById('stoneWeight').value = '';
      document.getElementById('stoneQuality').value = '';
      document.getElementById('stonePrice').value = '';
      document.getElementById('makingCharge').value = '';
      document.getElementById('makingChargeType').value = 'fixed';
      document.getElementById('description').value = '';
      document.getElementById('imagePreview').innerHTML = '';
      
      // Disable stone fields
      toggleStoneFields();
    }
    
    // Convert data URL to Blob
    function dataURLtoBlob(dataURL) {
      const parts = dataURL.split(';base64,');
      const contentType = parts[0].split(':')[1];
      const raw = window.atob(parts[1]);
      const rawLength = raw.length;
      const uInt8Array = new Uint8Array(rawLength);
      
      for (let i = 0; i < rawLength; ++i) {
        uInt8Array[i] = raw.charCodeAt(i);
      }
      
      return new Blob([uInt8Array], { type: contentType });
    }
    
    // Stock Management Functions
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize variables
        const stockMetalType = document.getElementById('stockMetalType');
        const stockName = document.getElementById('stockName');
        const stockPurity = document.getElementById('stockPurity');
        const customPurity = document.getElementById('customPurity');
        const stockWeight = document.getElementById('stockWeight');
        const stockRate = document.getElementById('stockRate');
        const isPurchase = document.getElementById('isPurchase');
        const purchaseFields = document.getElementById('purchaseFields');
        const supplier = document.getElementById('supplier');
        const buyingPurity = document.getElementById('buyingPurity');
        const paidAmount = document.getElementById('paidAmount');
        const paymentMode = document.getElementById('paymentMode');
        const paymentStatus = document.getElementById('paymentStatus');
        const invoiceNumber = document.getElementById('invoiceNumber');
        const addStockBtn = document.getElementById('addStock');
        const stockQuantity = document.getElementById('stockQuantity');

        // Material Type Change Handler
        stockMetalType.addEventListener('change', function() {
            stockName.value = '';
            stockPurity.value = '';
            customPurity.value = '';
            customPurity.classList.add('hidden');
            resetStockDetails();
            loadStockNames();
        });

        // Stock Name Input Handler
        stockName.addEventListener('input', function() {
            if(stockMetalType.value && this.value) {
                stockPurity.value = '';
                customPurity.value = '';
                customPurity.classList.add('hidden');
                resetStockDetails();
            }
        });

        // Purity Selection Handler
        stockPurity.addEventListener('change', function() {
            handlePuritySelection();
        });

        // Custom Purity Input Handler
        customPurity.addEventListener('input', function() {
            if(stockMetalType.value && stockName.value && this.value) {
                updatePurityDetails(stockMetalType.value, this.value);
            }
        });

        // Weight and Rate Change Handlers
        stockWeight.addEventListener('input', calculatePurchaseAmount);
        stockRate.addEventListener('input', calculatePurchaseAmount);

        // Purchase Checkbox Handler
        isPurchase.addEventListener('change', function() {
            purchaseFields.style.display = this.checked ? 'block' : 'none';
            if(this.checked) {
                loadSuppliers();
            }
        });

        // Paid Amount Handler
        paidAmount.addEventListener('input', calculatePurchaseAmount);

        // Buying Purity Handler
        buyingPurity.addEventListener('input', calculatePurchaseAmount);

        // Add Stock Button Handler
        addStockBtn.addEventListener('click', handleAddStock);

        // Load stock stats on page load and after successful stock addition
        loadStockStats();

        function loadStockStats() {
            fetch('stock_functions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=getStockStats'
            })
            .then(handleFetchResponse)
            .then(data => {
                if(Array.isArray(data)) {
                    updateStockStats(data);
                }
            })
            .catch(handleError);
        }

        function updateStockStats(stats) {
            const container = document.getElementById('stockStatsSummary');
            container.innerHTML = '';

            stats.forEach(stat => {
                const card = document.createElement('div');
                card.className = 'bg-gray-50 rounded-lg p-2 text-xs';
                card.innerHTML = `
                    <div class="font-semibold text-gray-700">${stat.material_type} - ${stat.purity}%</div>
                    <div class="grid grid-cols-2 gap-1 mt-1">
                        <div class="text-gray-600">Items: ${stat.total_items || 0}</div>
                        <div class="text-gray-600">Stock: ${stat.total_stock || '0.00'}g</div>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        // Helper Functions
        function loadStockNames() {
            const materialType = stockMetalType.value;
            if(!materialType) return;

            fetch('stock_functions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=searchStockNames&material_type=${encodeURIComponent(materialType)}&search_term=`
            })
            .then(handleFetchResponse)
            .then(data => {
                if(Array.isArray(data)) {
                    updateStockNamesList(data);
                }
            })
            .catch(handleError);
        }

        function loadSuppliers() {
            fetch('stock_functions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=getSuppliers'
            })
            .then(handleFetchResponse)
            .then(data => {
                if(Array.isArray(data)) {
                    updateSuppliersList(data);
                }
            })
            .catch(handleError);
        }

        function handlePuritySelection() {
            if(stockPurity.value === 'custom') {
                customPurity.classList.remove('hidden');
                customPurity.focus();
            } else {
                customPurity.classList.add('hidden');
                customPurity.value = '';
                if(stockMetalType.value && stockPurity.value) {
                    updatePurityDetails(stockMetalType.value, stockPurity.value);
                }
            }
        }

        function updatePurityDetails(materialType, purity) {
            fetch('stock_functions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=getPurityStockDetails&material_type=${encodeURIComponent(materialType)}&purity=${encodeURIComponent(purity)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if(!data.error) {
                    document.getElementById('currentStock').textContent = (data.total_current_stock || 0) + 'g';
                    document.getElementById('remainingStock').textContent = (data.total_remaining_stock || 0) + 'g';
                    if(data.avg_rate > 0) {
                        stockRate.value = data.avg_rate;
                        calculatePurchaseAmount();
                    }
                }
            })
            .catch(handleError);
        }

        function calculatePurchaseAmount() {
            const quantity = parseInt(stockQuantity.value) || 1;
            const weight = parseFloat(stockWeight.value) || 0;
            const rate = parseFloat(stockRate.value) || 0;
            const buyingPurityValue = parseFloat(buyingPurity.value) || 0;
            const paidAmountValue = parseFloat(paidAmount.value) || 0;

            if(weight > 0 && rate > 0 && buyingPurityValue > 0) {
                const total = quantity * weight * rate * (buyingPurityValue / 99.99);
                document.getElementById('stockMaterialCost').textContent = '₹' + total.toFixed(2);
                document.getElementById('stockTotalPrice').textContent = '₹' + total.toFixed(2);

                // Update payment status
                let status = 'Due';
                if(paidAmountValue > 0) {
                    status = paidAmountValue >= total ? 'Paid' : 'Partial';
                }
                paymentStatus.value = status;

                if(isPurchase.checked) {
                    const balance = total - paidAmountValue;
                    document.getElementById('balanceContainer').style.display = 'flex';
                    document.getElementById('balanceAmount').textContent = '₹' + balance.toFixed(2);
                }
            }
        }

        function handleAddStock() {
            // Validate required fields
            if(!validateFields()) return;

            const formData = new FormData();
            formData.append('action', 'addStock');
            formData.append('firm_id', '1');
            formData.append('material_type', stockMetalType.value);
            formData.append('stock_name', stockName.value);
            formData.append('purity', stockPurity.value === 'custom' ? customPurity.value : stockPurity.value);
            formData.append('weight', stockWeight.value);
            formData.append('rate', stockRate.value);
            formData.append('quantity', stockQuantity.value);
            formData.append('is_purchase', isPurchase.checked ? '1' : '0');

            if(isPurchase.checked) {
                appendPurchaseData(formData);
            }

            fetch('stock_functions.php', {
                method: 'POST',
                body: formData
            })
            .then(handleFetchResponse)
            .then(data => {
                if(data.success) {
                    alert('Stock added successfully!');
                    resetStockForm();
                    loadStockStats(); // Reload stats after successful addition
                    const purity = stockPurity.value === 'custom' ? customPurity.value : stockPurity.value;
                    if(purity) {
                        updatePurityDetails(stockMetalType.value, purity);
                    }
                } else {
                    throw new Error(data.error || 'Failed to add stock');
                }
            })
            .catch(handleError);
        }

        // Utility Functions
        function handleFetchResponse(response) {
            if(!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server error: ${text}`);
                });
            }
            return response.json().then(data => {
                if(!data) {
                    throw new Error('Empty response from server');
                }
                return data;
            });
        }

        function handleError(error) {
            console.error('Operation failed:', error);
            alert(error.message || 'Operation failed. Please try again.');
        }

        function validateFields() {
            if(!stockMetalType.value || !stockName.value) {
                alert('Please select material type and enter stock name');
                return false;
            }

            const purity = stockPurity.value === 'custom' ? customPurity.value : stockPurity.value;
            if(!purity) {
                alert('Please select or enter purity');
                return false;
            }

            if(!stockWeight.value || !stockRate.value) {
                alert('Please enter weight and rate');
                return false;
            }

            if(isPurchase.checked) {
                if(!supplier.value) {
                    alert('Please select a supplier');
                    return false;
                }
                if(!buyingPurity.value) {
                    alert('Please enter buying purity');
                    return false;
                }
                if(parseFloat(paidAmount.value) > 0 && !paymentMode.value) {
                    alert('Please select payment mode');
                    return false;
                }
            }

            return true;
        }

        function appendPurchaseData(formData) {
            const weight = parseFloat(stockWeight.value) || 0;
            const rate = parseFloat(stockRate.value) || 0;
            const buyingPurityValue = parseFloat(buyingPurity.value) || 0;
            const total = weight * rate * (buyingPurityValue / 99.99);
            const paid = parseFloat(paidAmount.value) || 0;

            formData.append('supplier_id', supplier.value);
            formData.append('total_amount', total.toFixed(2));
            formData.append('paid_amount', paid.toFixed(2));
            if(paid > 0) {
                formData.append('payment_mode', paymentMode.value);
            }
            formData.append('invoice_number', invoiceNumber.value);
        }

        function updateStockNamesList(names) {
            const datalist = document.createElement('datalist');
            datalist.id = 'stockNamesList';
            names.forEach(name => {
                const option = document.createElement('option');
                option.value = name;
                datalist.appendChild(option);
            });
            
            const existingDatalist = document.getElementById('stockNamesList');
            if(existingDatalist) {
                existingDatalist.remove();
            }
            
            document.body.appendChild(datalist);
            stockName.setAttribute('list', 'stockNamesList');
        }

        function updateSuppliersList(suppliers) {
            supplier.innerHTML = '<option value="">Select Supplier</option>';
            suppliers.forEach(s => {
                supplier.innerHTML += `<option value="${s.id}">${s.name}</option>`;
            });
        }

        function resetStockForm() {
            stockName.value = '';
            stockPurity.value = '';
            customPurity.value = '';
            customPurity.classList.add('hidden');
            stockWeight.value = '';
            stockRate.value = '';
            isPurchase.checked = false;
            purchaseFields.style.display = 'none';
            buyingPurity.value = '';
            supplier.value = '';
            invoiceNumber.value = '';
            paidAmount.value = '';
            paymentMode.value = '';
            paymentStatus.value = '';
            resetStockDetails();
        }

        function resetStockDetails() {
            document.getElementById('currentStock').textContent = '0.00g';
            document.getElementById('remainingStock').textContent = '0.00g';
            document.getElementById('stockMaterialCost').textContent = '₹0.00';
            document.getElementById('stockTotalPrice').textContent = '₹0.00';
            document.getElementById('balanceContainer').style.display = 'none';
        }

        // Initialize
        stockName.setAttribute('autocomplete', 'off');
        stockName.setAttribute('list', 'stockNamesList');
    });

    // Initialize image upload and cropping functionality
    document.addEventListener('DOMContentLoaded', function() {
      const productImages = document.getElementById('productImages');
      const imagePreview = document.getElementById('imagePreview');
      const captureBtn = document.getElementById('captureBtn');
      const cropBtn = document.getElementById('cropBtn');
      const cropperModal = document.getElementById('cropperModal');
      const cropperImage = document.getElementById('cropperImage');
      const applyCropBtn = document.getElementById('applyCrop');
      const cancelCropBtn = document.getElementById('cancelCrop');
      
      let cropper;
      let currentImageIndex = -1;
      
      // Handle file selection
      productImages.addEventListener('change', function(e) {
        const files = e.target.files;
        
        if (files.length > 0) {
          Array.from(files).forEach(file => {
            if (file.type.match('image.*')) {
              const reader = new FileReader();
              
              reader.onload = function(e) {
                addImageToPreview(e.target.result);
              };
              
              reader.readAsDataURL(file);
            }
          });
        }
      });
      
      // Add image to preview
      function addImageToPreview(src) {
        const container = document.createElement('div');
        container.className = 'preview-item'; // This creates the container div for each image

        const img = document.createElement('img');
        img.src = src; // Sets the image source
        img.alt = 'Product image';
        img.style.cursor = 'pointer'; // Makes it visually clear it's clickable

        // Add click event to open image in cropper
        img.addEventListener('click', function(e) {
          e.preventDefault(); // <-- This should stop the browser's default action (like a form submission or navigation)
          e.stopPropagation(); // <-- This should stop the event from bubbling up to parent elements

          // Find the index of the clicked image in the preview
          const images = imagePreview.querySelectorAll('.preview-item img');
          images.forEach((previewImg, index) => {
            if (previewImg === img) {
              window.currentImageIndex = index; // Store the index of the clicked image
            }
          });

          // Show image in cropper
          const cropperModal = document.getElementById('cropperModal');
          const cropperImage = document.getElementById('cropperImage');

          if (cropperModal && cropperImage) {
            cropperImage.src = src; // Set the image source in the cropper modal
            cropperModal.style.display = 'block'; // Show the modal

            // Initialize cropper
            if (window.cropper) {
              window.cropper.destroy(); // Destroy previous cropper instance if it exists
            }

            window.cropper = new Cropper(cropperImage, { // Create a new cropper instance
              aspectRatio: 1,
              viewMode: 1,
              autoCropArea: 0.8,
              responsive: true
            });
          }
        });

        const removeBtn = document.createElement('div');
        removeBtn.className = 'remove-image'; // Create the remove button
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.addEventListener('click', function(e) {
          e.preventDefault(); // Prevent default action for remove button
          e.stopPropagation(); // Stop propagation for remove button
          container.remove(); // Remove the image container when clicked
          // The logic here to update currentImageIndex might need refinement,
          // but it's less critical for the crop button issue itself.
        });

        container.appendChild(img); // Add the image to its container
        container.appendChild(removeBtn); // Add the remove button to the container
        imagePreview.appendChild(container); // Add the container to the preview area
      }
      
      // Handle camera capture
      captureBtn.addEventListener('click', function() {
        // Check if device has camera access
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
          const videoModal = document.createElement('div');
          videoModal.className = 'modal';
          videoModal.style.display = 'block';
          
          const modalContent = document.createElement('div');
          modalContent.className = 'modal-content';
          
          const closeBtn = document.createElement('span');
          closeBtn.className = 'close';
          closeBtn.innerHTML = '&times;';
          
          const title = document.createElement('h2');
          title.className = 'text-lg font-bold mb-3';
          title.textContent = 'Capture Image';
          
          const video = document.createElement('video');
          video.className = 'w-full h-64 bg-black rounded-lg';
          video.autoplay = true;
          
          const captureContainer = document.createElement('div');
          captureContainer.className = 'flex justify-center mt-3';
          
          const captureImageBtn = document.createElement('button');
          captureImageBtn.className = 'btn-primary';
          captureImageBtn.innerHTML = '<i class="fas fa-camera mr-2"></i> Capture';
          
          captureContainer.appendChild(captureImageBtn);
          modalContent.appendChild(closeBtn);
          modalContent.appendChild(title);
          modalContent.appendChild(video);
          modalContent.appendChild(captureContainer);
          videoModal.appendChild(modalContent);
          document.body.appendChild(videoModal);
          
          // Get camera stream
          navigator.mediaDevices.getUserMedia({ video: true })
            .then(function(stream) {
              video.srcObject = stream;
              
              // Capture image
              captureImageBtn.addEventListener('click', function() {
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                
                const imageDataUrl = canvas.toDataURL('image/jpeg');
                addImageToPreview(imageDataUrl);
                
                // Stop camera stream and close modal
                stream.getTracks().forEach(track => track.stop());
                videoModal.remove();
              });
              
              // Close modal
              closeBtn.addEventListener('click', function() {
                stream.getTracks().forEach(track => track.stop());
                videoModal.remove();
              });
              
              window.addEventListener('click', function(event) {
                if (event.target === videoModal) {
                  stream.getTracks().forEach(track => track.stop());
                  videoModal.remove();
                }
              });
            })
            .catch(function(error) {
              alert('Error accessing camera: ' + error.message);
              videoModal.remove();
            });
        } else {
          alert('Your device does not support camera access');
        }
      });
      
      // Handle crop button click (the main "Crop" button)
      cropBtn.addEventListener('click', function() {
        const images = imagePreview.querySelectorAll('.preview-item img'); // Get all image elements in preview

        if (images.length === 0) {
          alert('Please add images first'); // Alert if no images are present
          return;
        }

        // If no image was previously clicked, default to the first one
        // This handles the case where the user clicks the Crop button without selecting an image first
        if (window.currentImageIndex === undefined || window.currentImageIndex === -1 || window.currentImageIndex >= images.length) {
           window.currentImageIndex = 0;
        }

        // Show the currently selected image (based on currentImageIndex) in cropper
        cropperImage.src = images[window.currentImageIndex].src; // Set cropper image source
        cropperModal.style.display = 'block'; // Show the cropper modal

        // Initialize cropper
        if (window.cropper) {
          window.cropper.destroy(); // Destroy existing cropper instance
        }

        window.cropper = new Cropper(cropperImage, { // Create new cropper instance for the selected image
          aspectRatio: 1,
          viewMode: 1,
          autoCropArea: 0.8,
          responsive: true
        });
      });
      
      // Apply crop button click handler
      applyCropBtn.addEventListener('click', function() {
        // Check if cropper instance exists and an image index is selected
        if (!window.cropper || window.currentImageIndex === -1) {
            console.error("Cropper not initialized or no image selected."); // Added for debugging
            return;
        }

        const croppedCanvas = window.cropper.getCroppedCanvas(); // Get the cropped canvas
        if (croppedCanvas) {
          const croppedImageDataUrl = croppedCanvas.toDataURL('image/jpeg'); // Get data URL of cropped image
          const images = imagePreview.querySelectorAll('.preview-item img'); // Get all preview images again

          // Update the source of the correct image in the preview using the stored index
          if (window.currentImageIndex >= 0 && window.currentImageIndex < images.length) {
            images[window.currentImageIndex].src = croppedImageDataUrl; // <-- This line updates the image
          } else {
              console.error("Invalid currentImageIndex:", window.currentImageIndex); // Added for debugging
          }

          window.cropper.destroy(); // Destroy the cropper instance
          window.cropper = null; // Clear the cropper variable
          cropperModal.style.display = 'none'; // Hide the modal
          window.currentImageIndex = -1; // Reset the index
        } else {
            console.error("Could not get cropped canvas."); // Added for debugging
        }
      });
      
      // Cancel crop
      cancelCropBtn.addEventListener('click', function() {
        if (cropper) {
          cropper.destroy();
          cropper = null;
        }
        cropperModal.style.display = 'none';
      });
      
      // Close modal when clicking on X
      document.querySelector('#cropperModal .close').addEventListener('click', function() {
        if (cropper) {
          cropper.destroy();
          cropper = null;
        }
        cropperModal.style.display = 'none';
      });
      
      // Close modal when clicking outside
      window.addEventListener('click', function(event) {
        if (event.target === cropperModal) {
          if (cropper) {
            cropper.destroy();
            cropper = null;
          }
          cropperModal.style.display = 'none';
        }
      });
    });
  </script>
</body>
</html>

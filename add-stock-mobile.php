<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config/config.php';
require_once __DIR__ . '/config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['id'])) {
   header("Location: login.php");
   exit();
}

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

// Fetch inventory metals for list and stats
$inventoryMetals = [];
$statsByPurity = [];
$purityToMetal = [];
$totalStock = 0;
$totalValue = 0;
$inventoryQuery = $conn->prepare("SELECT material_type, stock_name, purity, unit_measurement, current_stock, cost_price_per_gram, total_cost, last_updated FROM inventory_metals WHERE firm_id = ? ORDER BY material_type, purity DESC, stock_name");
$inventoryQuery->bind_param("i", $firm_id);
$inventoryQuery->execute();
$inventoryResult = $inventoryQuery->get_result();
while ($row = $inventoryResult->fetch_assoc()) {
    $inventoryMetals[] = $row;
    $purityKey = $row['purity'];
    if (!isset($statsByPurity[$purityKey])) {
        $statsByPurity[$purityKey] = ['stock' => 0, 'value' => 0];
        $purityToMetal[$purityKey] = $row['material_type'];
    }
    $statsByPurity[$purityKey]['stock'] += $row['current_stock'];
    $statsByPurity[$purityKey]['value'] += $row['total_cost'];
    $totalStock += $row['current_stock'];
    $totalValue += $row['total_cost'];
}

function getMetalColorClass($metal) {
    switch ($metal) {
        case 'Gold': return 'bg-yellow-100 border-yellow-400 text-yellow-700';
        case 'Silver': return 'bg-gray-100 border-gray-400 text-gray-700';
        case 'Diamond': return 'bg-blue-100 border-blue-400 text-blue-700';
        case 'Gems': return 'bg-green-100 border-green-400 text-green-700';
        case 'Stone': return 'bg-purple-100 border-purple-400 text-purple-700';
        case 'Copper': return 'bg-orange-100 border-orange-400 text-orange-700';
        default: return 'bg-indigo-100 border-indigo-400 text-indigo-700';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add Stock (Mobile)</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/add.css">
  <style>
    .section-card { padding: 0.75rem 0.75rem; border-radius: 1rem; box-shadow: 0 2px 8px 0 rgba(80,80,120,0.07); margin-bottom: 0.75rem; }
    .section-title { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; letter-spacing: 0.01em; }
    .section-title i { font-size: 0.75rem; }
    .input-label { font-size: 0.85em; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.15em; }
    .input-field { border-radius: 0.7em; border: 1px solid #e0e7ef; font-size: 1em; padding: 0.45em 0.7em; box-shadow: 0 1px 2px 0 rgba(80,80,120,0.04); transition: border 0.2s, box-shadow 0.2s; font-weight: bold; }
    .input-field:focus { border-color: #6366f1; box-shadow: 0 0 0 2px #a5b4fc33; outline: none; }
    .readonly-input { background: #f3f4f6 !important; font-style: italic; color: #6b7280; }
    .entry-type-pill { background: #eef2ff; border: 1.5px solid #6366f1; color: #3730a3; margin-right: 0.5em; transition: background 0.2s, color 0.2s; cursor: pointer; }
    .tab-btn {
      border-radius: 0.7em 0.7em 0 0;
      margin-right: 0.2em;
      font-weight: 600;
      color: #6366f1;
      background: #f3f4f6;
      border: none;
      padding: 0.5em 1.2em;
      transition: background 0.2s, color 0.2s;
    }
    .tab-btn.active {
      color: #fff;
      background: #6366f1;
      box-shadow: 0 2px 8px 0 rgba(99,102,241,0.10);
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    #supplierDropdown { box-shadow: 0 4px 16px 0 rgba(80,80,120,0.13); border: 1.5px solid #a7f3d0; }
    #addSupplierBtn { box-shadow: 0 2px 8px 0 rgba(34,197,94,0.13); border: 2px solid #fff; transition: background 0.2s, box-shadow 0.2s; }
    #addSupplierBtn:hover, #addSupplierBtn:focus { background: linear-gradient(90deg,#22d3ee,#22c55e); box-shadow: 0 4px 16px 0 rgba(34,197,94,0.18); }
    .bottom-nav { box-shadow: 0 -2px 12px 0 rgba(80,80,120,0.09); border-radius: 1.2em 1.2em 0 0; }
    .modal-content { border-radius: 1.2em; box-shadow: 0 4px 24px 0 rgba(80,80,120,0.13); }
    .font-medium { font-weight: 600; }
    .font-bold { font-weight: 700; }
    .fixed.bottom-0 button[type=submit] { box-shadow: 0 2px 12px 0 rgba(99,102,241,0.18); border-radius: 2em; font-size: 1.1em; }
    .fixed.bottom-0 { border-radius: 1.2em 1.2em 0 0; }
    /* Extra compactness for mobile */
    @media (max-width: 600px) {
      .section-card { padding: 0.5rem 0.5rem; }
      .input-field { font-size: 0.98em; padding: 0.38em 0.6em; font-weight: bold; }
      .section-title { font-size: 0.95em; }
    }
    .field-label { font-size: 0.85em; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.15em; }
    .field-container { position: relative; display: flex; align-items: center; }
    .field-icon { position: absolute; left: 0.75em; pointer-events: none; font-size: 1em; opacity: 0.7; }
    .input-field { padding-left: 2.2em !important; }
    .grid-cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5em; }
    .grid-cols-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5em; }
    @media (max-width: 600px) { .grid-cols-2 { grid-template-columns: 1fr 1fr; } .grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; } }
    .entry-type-pill.active {
      background: #6366f1 !important; /* Indigo-500 */
      color: #fff !important;
      border-color: #6366f1 !important;
      box-shadow: 0 2px 8px 0 rgba(99,102,241,0.10);
      transition: background 0.2s, color 0.2s, border 0.2s;
    }
    .modal { display: none; position: fixed; inset: 0; z-index: 50; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); }
    .modal.active { display: flex !important; }
    body.modal-open { overflow: hidden; }
    /* Modern GST Toggle */
    .gst-toggle-wrapper {
      display: flex;
      align-items: center;
      gap: 0.5em;
    }
    .gst-switch {
      position: relative;
      display: inline-block;
      width: 44px;
      height: 24px;
      vertical-align: middle;
    }
    .gst-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .gst-slider {
      position: absolute;
      cursor: pointer;
      top: 0; left: 0; right: 0; bottom: 0;
      background: #e5e7eb;
      border-radius: 24px;
      transition: background 0.3s;
      box-shadow: 0 2px 8px 0 rgba(80,80,120,0.10);
    }
    .gst-switch input:checked + .gst-slider {
      background: #22c55e;
    }
    .gst-slider:before {
      content: "";
      position: absolute;
      left: 4px; top: 4px;
      width: 16px; height: 16px;
      background: #fff;
      border-radius: 50%;
      transition: transform 0.3s;
      box-shadow: 0 1px 4px 0 rgba(80,80,120,0.10);
    }
    .gst-switch input:checked + .gst-slider:before {
      transform: translateX(20px);
      background: #fff url('data:image/svg+xml;utf8,<svg fill="%2322c55e" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M16.707 5.293a1 1 0 00-1.414 0L9 11.586 6.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l7-7a1 1 0 000-1.414z"/></svg>') no-repeat center/12px 12px;
    }
  </style>
</head>
<body class="pb-20">
  <!-- Mobile Header -->
  <div class="header-gradient p-2 text-white font-bold shadow rounded-b-xl">
    <div class="flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i class="fas fa-gem text-white-600 text-lg"></i>
        <span class="font-semibold text-base tracking-wide"><?php echo htmlspecialchars($userInfo['FirmName']); ?></span>
      </div>
      <div class="flex items-center gap-2">
        <div class="text-right text-xs">
          <div class="font-semibold text-sm"><?php echo htmlspecialchars($userInfo['Name']); ?></div>
          <div class="text-white/80 text-xs"><?php echo htmlspecialchars($userInfo['Role']); ?></div>
        </div>
        <div class="w-9 h-9 rounded-full bg-white/20 overflow-hidden border-2 border-white">
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

  <!-- Tab Navigation -->
  <div class="flex bg-white shadow-sm sticky top-0 z-10 text-xs justify-center gap-2">
    <button class="tab-btn" data-tab="add-stock"><i class="fas fa-plus-circle mr-1"></i> Add Stock</button>
    <button class="tab-btn" data-tab="stock-list"><i class="fas fa-list mr-1"></i> Stock List</button>
  </div>

  <!-- Tab Content: Add Stock -->
  <div id="add-stock" class="tab-content">
    <form id="inventoryMetalsForm" autocomplete="off" class="section-card mt-2 px-2 py-2 bg-white rounded-xl shadow border border-gray-100">
      <!-- Entry Type Toggle -->
      <div class="mb-1">
       
        <div class="flex gap-1 mt-0.5">
          <label class="flex items-center cursor-pointer">
            <input type="radio" name="entry_type" value="opening_stock" id="entryTypeOpening" class="sr-only" checked>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold border border-indigo-400 bg-indigo-50 mr-0.5 entry-type-pill">Opening</span>
          </label>
          <label class="flex items-center cursor-pointer">
            <input type="radio" name="entry_type" value="purchase" id="entryTypePurchase" class="sr-only">
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold border border-indigo-400 bg-indigo-50 entry-type-pill">Purchase</span>
          </label>
        </div>
      </div>
      <!-- Source Section -->
      <div class="mb-2">
        <div id="customSourceDiv">
          <label class="input-label text-xs font-medium text-gray-700 mb-1 block">Source Info</label>
          <input type="text" name="custom_source_info" id="customSourceInfo" class="input-field rounded-lg border-gray-300 text-xs py-1 px-2" placeholder="e.g. Initial Inventory, Migration">
        </div>
        <div id="supplierDiv" style="display:none;">
          <label class="input-label text-xs font-medium text-gray-700 mb-1 block">Supplier <span class="text-red-500">*</span></label>
          <div class="relative flex items-center">
            <i class="fas fa-truck field-icon text-green-400 left-3 absolute text-xs"></i>
            <input type="text" id="supplierInput" class="input-field pl-9 rounded-lg border-gray-300 text-xs py-1 px-2" placeholder="Type supplier name..." autocomplete="off">
            <input type="hidden" name="supplier_id" id="supplierIdHidden">
            <button type="button" id="addSupplierBtn" class="absolute right-2 top-1/2 -translate-y-1/2 bg-gradient-to-r from-green-400 to-green-600 text-white rounded-full w-7 h-7 flex items-center justify-center shadow border-2 border-white z-10 text-xs">
              <i class="fa fa-plus"></i>
            </button>
            <div id="supplierDropdown" class="bg-white border rounded shadow absolute w-full z-50" style="display:none; max-height:180px; overflow-y:auto; top:110%; left:0;"></div>
          </div>
        </div>
      </div>
      <!-- Material Section -->
      <div class="section-card bg-amber-100 border-amber-100 mb-2 rounded-xl shadow-xs p-2">
        <div class="section-title flex items-center gap-2 text-amber-800 text-sm font-bold mb-1">
          <i class="fas fa-gem text-amber-400"></i> Material Details
        </div>
        <div class="grid-cols-2 mb-1">
          <div>
            <div class="field-label">Material <span class="text-red-500">*</span></div>
            <div class="field-container">
              <select name="material_type" required class="input-field rounded-lg border-gray-300 text-xs py-1 px-2" id="materialTypeSelect">
                <option value="">Material</option>
                <option value="Gold">Gold</option>
                <option value="Silver">Silver</option>
                <option value="Gems">Gems</option>
                <option value="Stone">Stone</option>
                <option value="Diamond">Diamond</option>
                <option value="KD">KD</option>
                <option value="Copper">Copper</option>
              </select>
              <i class="fas fa-gem field-icon text-amber-400"></i>
            </div>
          </div>
          <div>
            <div class="field-label">Stock Name <span class="text-red-500">*</span></div>
            <div class="field-container">
              <input type="text" name="stock_name" required class="input-field rounded-lg border-gray-300 text-xs py-1 px-2" placeholder="Stock Name">
              <i class="fas fa-tag field-icon text-amber-400"></i>
            </div>
          </div>
        </div>
        <div class="grid-cols-3 mb-1">
          <div>
            <div class="field-label">HSN Code <span class="text-red-500">*</span></div>
            <div class="field-container">
              <input type="text" name="hsn_code" id="hsnCode" required class="input-field rounded-lg border-gray-300 text-xs py-1 px-2" placeholder="HSN Code">
              <i class="fas fa-barcode field-icon text-amber-400"></i>
            </div>
          </div>
          <div>
            <div class="field-label">Unit <span class="text-red-500">*</span></div>
            <div class="field-container">
              <select name="unit_measurement" id="unitSelect" required class="input-field rounded-lg border-gray-300 text-xs py-1 px-2">
                <option value="">Unit</option>
                <option value="gms">gms</option>
                <option value="carat">carat</option>
                <option value="pcs">pcs</option>
                <option value="custom">Custom</option>
              </select>
              <i class="fas fa-balance-scale field-icon text-amber-400"></i>
            </div>
            <input type="text" id="customUnitInput" name="custom_unit_measurement" placeholder="Custom unit" class="input-field mt-1 rounded-lg border-gray-300 text-xs py-1 px-2" style="display:none;">
          </div>
          <div>
            <div class="field-label">Purity (%) <span class="text-red-500">*</span></div>
            <div class="field-container">
              <input type="number" name="purity" step="0.01" min="0" max="100" placeholder="Purity %" required class="input-field rounded-lg border-gray-300 text-xs py-1 px-2">
              <i class="fas fa-certificate field-icon text-amber-400"></i>
            </div>
          </div>
        </div>
        <div class="grid-cols-2 mb-1">
          <div>
            <div class="field-label">Weight (g) <span class="text-red-500">*</span></div>
            <div class="field-container">
              <input type="number" name="weight" id="weight" step="0.001" placeholder="Weight (g)" required class="input-field rounded-lg border-gray-300 text-xs py-1 px-2">
              <i class="fas fa-weight-hanging field-icon text-amber-400"></i>
            </div>
          </div>
          <div>
            <div class="field-label">Quantity (pcs) <span class="text-red-500">*</span></div>
            <div class="field-container">
              <input type="number" name="quantity" id="quantity" min="1" step="1" placeholder="Quantity (pcs)" required class="input-field rounded-lg border-gray-300 text-xs py-1 px-2">
              <i class="fas fa-sort-numeric-up field-icon text-amber-400"></i>
            </div>
          </div>
        </div>
      </div>
      <!-- Pricing Section -->
      <div class="section-card bg-green-100 border-green-200 mb-2 rounded-lg shadow-xs p-2">
        <div class="section-title flex items-center gap-2 text-green-700 text-sm font-bold mb-1">
          <i class="fas fa-rupee-sign text-green-500"></i> Pricing Details
          <span class="ml-auto gst-toggle-wrapper">
            <span class="text-xs font-semibold text-gray-600">GST 3%</span>
            <label class="gst-switch">
              <input type="checkbox" id="gstToggle">
              <span class="gst-slider"></span>
            </label>
          </span>
        </div>
        <div class="grid-cols-3 mb-1">
          <div>
            <div class="field-label">Rate <span class="text-red-500">*</span></div>
            <div class="field-container">
              <input type="number" name="rate" id="rate" step="0.01" placeholder="Rate" required class="input-field rounded-lg border-gray-300 text-xs py-1 px-2">
              <i class="fas fa-rupee-sign field-icon text-green-500"></i>
            </div>
          </div>
          <div>
            <div class="field-label">Making %</div>
            <div class="field-container">
              <input type="number" name="making_charges" id="makingCharges" step="0.01" placeholder="Making %" class="input-field rounded-lg border-gray-300 text-xs py-1 px-2">
              <i class="fas fa-percent field-icon text-green-500"></i>
            </div>
          </div>
          <div>
            <div class="field-label">Cost/Gram</div>
            <div class="field-container">
              <input type="number" name="cost_price_per_gram" id="costPricePerGram" step="0.01" placeholder="Cost/Gram" readonly class="input-field readonly-input rounded-lg border-gray-200 text-xs py-1 px-2 bg-gray-100">
              <i class="fas fa-balance-scale field-icon text-green-500"></i>
            </div>
          </div>
        </div>
        <div class="grid-cols-2 mb-1">
          <div>
            <div class="field-label">Taxable Amount</div>
            <div class="field-container">
              <input type="number" name="total_taxable_amount" id="totalTaxableAmount" step="0.01" placeholder="Taxable Amount" readonly class="input-field readonly-input rounded-lg border-gray-200 text-xs py-1 px-2 bg-gray-100">
              <i class="fas fa-calculator field-icon text-green-500"></i>
            </div>
          </div>
          <div>
            <div class="field-label">Final Amount</div>
            <div class="field-container">
              <input type="number" name="final_amount" id="finalAmount" step="0.01" placeholder="Final Amount" readonly class="input-field readonly-input rounded-lg border-gray-200 text-sm py-1 px-2 bg-gray-100">
              <i class="fas fa-rupee-sign field-icon text-green-500"></i>
            </div>
          </div>
        </div>
      </div>
      <!-- Purchase Details Section -->
      <div id="purchaseDetailsSection" class="section-card bg-blue-100 border-blue-200 mb-2 rounded-lg shadow-xs p-2" style="display:none;">
        <div class="section-title text-blue-700 text-sm font-bold mb-1 flex items-center gap-2">
          <i class="fas fa-file-invoice"></i> Purchase Details
        </div>
        <div class="grid-cols-2 mb-1">
          <div>
            <div class="field-label">Invoice Number</div>
            <div class="field-container">
              <input type="text" name="invoice_number" class="input-field rounded-lg border-gray-300 text-xs py-1 px-2" placeholder="Invoice Number">
              <i class="fas fa-hashtag field-icon text-blue-400"></i>
            </div>
          </div>
          <div>
            <div class="field-label">Invoice Date</div>
            <div class="field-container">
              <input type="date" name="invoice_date" class="input-field rounded-lg border-gray-300 text-xs py-1 px-2">
              <i class="fas fa-calendar-alt field-icon text-blue-400"></i>
            </div>
          </div>
        </div>
        <div class="grid-cols-3 mb-1" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5em;">
          <div>
            <div class="field-label">Paid Amount</div>
            <div class="field-container">
              <input type="number" name="paid_amount" id="paidAmountPurchase" step="0.01" class="input-field rounded-lg border-gray-300 text-xs py-1 px-2" placeholder="Paid Amount">
              <i class="fas fa-rupee-sign field-icon text-blue-400"></i>
            </div>
          </div>
          <div>
            <div class="field-label">Payment Status</div>
            <div class="field-container">
              <input type="text" name="payment_status" id="paymentStatusPurchase" class="input-field rounded-lg border-gray-300 text-xs py-1 px-2 readonly-input bg-gray-100" placeholder="Status" readonly>
              <i class="fas fa-info-circle field-icon text-blue-400"></i>
            </div>
          </div>
          <div>
            <div class="field-label">Payment Mode</div>
            <div class="field-container">
              <select name="payment_mode" class="input-field rounded-lg border-gray-300 text-xs py-1 px-2">
                <option value="">Payment Mode</option>
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
                <option value="upi">UPI</option>
                <option value="other">Other</option>
              </select>
              <i class="fas fa-university field-icon text-blue-400"></i>
            </div>
          </div>
        </div>
        <div class="grid-cols-2 mb-1">
          <div>
            <div class="field-label">Transaction Reference</div>
            <div class="field-container">
              <input type="text" name="transaction_ref" class="input-field rounded-lg border-gray-300 text-xs py-1 px-2" placeholder="Transaction Reference (optional)">
              <i class="fas fa-receipt field-icon text-blue-400"></i>
            </div>
          </div>
        </div>
      </div>
      <!-- Submit Button -->
      <div class="w-full bg-gradient-to-r from-indigo-500 to-purple-500 p-2 flex justify-center shadow-lg rounded-xl mb-2">
        <button type="submit" class="max-w-xs w-full px-2 py-2 rounded-full font-bold text-white bg-gradient-to-r from-indigo-500 to-purple-500 shadow-lg hover:from-indigo-600 hover:to-purple-600 transform hover:scale-105 transition-all duration-200 flex items-center gap-2 text-base justify-center">
          <i class="fas fa-save"></i> Save Entry
        </button>
      </div>
    </form>
  </div>

  <!-- Tab Content: Stock List -->
  <div id="stock-list" class="tab-content">
    <div class="section-card mt-3">
      <div class="section-title text-green-700 text-base mb-2"><i class="fa-solid fa-warehouse"></i> Inventory Stats</div>
      <div class="overflow-x-auto w-full">
        <div class="flex flex-nowrap gap-x-2 py-1 px-1 min-w-max">
          <div class="flex flex-col items-center bg-white rounded-lg shadow border px-2 py-1 min-w-[90px]">
            <div class="text-[11px] text-gray-500">Total Stock</div>
            <div class="font-bold text-base text-indigo-700 leading-tight"><?php echo number_format($totalStock, 2); ?></div>
          </div>
          <div class="flex flex-col items-center bg-white rounded-lg shadow border px-2 py-1 min-w-[90px]">
            <div class="text-[11px] text-gray-500">Total Value</div>
            <div class="font-bold text-base text-indigo-700 leading-tight">₹<?php echo number_format($totalValue, 2); ?></div>
          </div>
          <?php foreach ($statsByPurity as $purity => $stat): 
            $metal = $purityToMetal[$purity] ?? '';
            $colorClass = getMetalColorClass($metal);
          ?>
          <div class="flex flex-col items-center rounded-lg shadow border px-2 py-1 min-w-[90px] <?php echo $colorClass; ?>">
            <div class="text-[11px] font-semibold">Purity <?php echo htmlspecialchars($purity); ?></div>
            <div class="font-bold text-[15px] leading-tight"><?php echo number_format($stat['stock'], 2); ?>g</div>
            <div class="text-[11px]">₹<?php echo number_format($stat['value'], 2); ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs md:text-xs">
          <thead class="bg-indigo-50">
            <tr>
              <th class="px-2 py-2 text-left font-bold text-indigo-700">Material</th>
              <th class="px-2 py-2 text-left font-bold text-indigo-700">Stock Name</th>
              <th class="px-2 py-2 text-left font-bold text-indigo-700">Purity</th>
              <th class="px-2 py-2 text-right font-bold text-indigo-700">Stock</th>

              <th class="px-2 py-2 text-right font-bold text-indigo-700">Remaining</th>
              <th class="px-2 py-2 text-left font-bold text-indigo-700">Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inventoryMetals as $row): ?>
            <tr class="border-b hover:bg-indigo-50/40">
              <td class="px-2 py-1 font-semibold flex items-center gap-1">
                <?php if ($row['material_type'] === 'Gold'): ?><span class="text-yellow-500"><i class="fa-solid fa-coins"></i></span><?php endif; ?>
                <?php if ($row['material_type'] === 'Silver'): ?><span class="text-gray-400"><i class="fa-solid fa-gem"></i></span><?php endif; ?>
                <?php if ($row['material_type'] === 'Diamond'): ?><span class="text-blue-400"><i class="fa-regular fa-gem"></i></span><?php endif; ?>
                <?php echo htmlspecialchars($row['material_type']); ?>
              </td>
              <td class="px-2 py-1"><?php echo htmlspecialchars($row['stock_name']); ?></td>
              <td class="px-2 py-1"><span class="inline-block rounded bg-blue-100 text-blue-700 px-2 py-0.5 text-xs font-bold"><?php echo htmlspecialchars($row['purity']); ?></span></td>
              <td class="px-2 py-1 text-right"><?php echo number_format($row['current_stock'], 2); ?></td>

              <td class="px-2 py-1 text-right"><?php echo isset($row['issued_stock']) ? number_format($row['current_stock'] - $row['issued_stock'], 2) : number_format($row['current_stock'], 2); ?></td>
              <td class="px-2 py-1 text-left text-gray-500"><?php echo date('d M Y', strtotime($row['last_updated'])); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($inventoryMetals)): ?>
            <tr><td colspan="7" class="text-center text-gray-400 py-4">No stock found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav">
    <a href="main.php" class="nav-item"><i class="nav-icon fas fa-home"></i><span class="nav-text">Home</span></a>
    <a href="add.php" class="nav-item"><i class="nav-icon fa-solid fa-gem"></i><span class="nav-text">Add</span></a>
    <a href="add-stock-mobile.php" class="nav-item active"><i class="nav-icon fa-solid fa-store"></i><span class="nav-text">Stock</span></a>
   
    <a href="sale-entry.php" class="nav-item"><i class="nav-icon fas fa-shopping-cart"></i><span class="nav-text">Sale</span></a>
    <a href="reports.php" class="nav-item"><i class="nav-icon fas fa-chart-pie"></i><span class="nav-text">Reports</span></a>
  </nav>

  <!-- Supplier Modal -->
  <div id="addSupplierModal" class="modal">
    <div class="modal-content bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full relative border border-gray-200">
      <button type="button" id="closeAddSupplierModal" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <h2 class="font-bold text-xl mb-6 text-indigo-700 flex items-center gap-3"><i class="fas fa-truck"></i> Add New Supplier</h2>
      <form id="addSupplierForm" class="flex flex-col gap-4">
        <div class="relative">
          <i class="fas fa-user field-icon text-indigo-400"></i>
          <input type="text" name="name" placeholder="Name *" required class="input-field pl-10">
        </div>
        <div class="relative">
          <i class="fas fa-id-card field-icon text-green-400"></i>
          <input type="text" name="contact_info" placeholder="Contact Info" class="input-field pl-10">
        </div>
        <div class="relative">
          <i class="fas fa-envelope field-icon text-blue-400"></i>
          <input type="email" name="email" placeholder="Email" class="input-field pl-10">
        </div>
        <div class="relative">
          <i class="fas fa-map-marker-alt field-icon text-pink-400"></i>
          <input type="text" name="address" placeholder="Address" class="input-field pl-10">
        </div>
        <div class="relative">
          <i class="fas fa-flag field-icon text-yellow-400"></i>
          <input type="text" name="state" placeholder="State" class="input-field pl-10">
        </div>
        <div class="relative">
          <i class="fas fa-phone field-icon text-green-400"></i>
          <input type="text" name="phone" placeholder="Phone" class="input-field pl-10">
        </div>
        <div class="relative">
          <i class="fas fa-receipt field-icon text-purple-400"></i>
          <input type="text" name="gst" placeholder="GST" class="input-field pl-10">
        </div>
        <div class="relative">
          <i class="fas fa-file-invoice-dollar field-icon text-orange-400"></i>
          <input type="text" name="payment_terms" placeholder="Payment Terms" class="input-field pl-10">
        </div>
        <div class="relative">
          <i class="fas fa-sticky-note field-icon text-gray-400"></i>
          <input type="text" name="notes" placeholder="Notes" class="input-field pl-10">
        </div>
        <div class="flex gap-2 mt-4">
          <button type="submit" class="flex-1 px-6 py-2 rounded-full font-bold text-white bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 shadow-lg text-lg flex items-center justify-center gap-2"><i class="fas fa-plus"></i> Add</button>
          <button type="button" id="cancelAddSupplierModal" class="flex-1 px-6 py-2 rounded-full font-bold text-gray-700 bg-gray-200 hover:bg-gray-300">Cancel</button>
        </div>
        <div id="addSupplierError" class="text-red-600 text-xs mt-1"></div>
      </form>
    </div>
  </div>

  <!-- Toast for supplier add success -->
  <div id="supplierToast" style="display:none; position:fixed; bottom:90px; left:50%; transform:translateX(-50%); z-index:9999;" class="bg-green-600 text-white px-6 py-3 rounded-full shadow-lg font-bold text-center text-lg flex items-center gap-2">
    <i class="fa-solid fa-circle-check"></i> Supplier added successfully!
  </div>

  <script>
  // Tab switching (improved)
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
      this.classList.add('active');
      document.getElementById(this.dataset.tab).classList.add('active');
    });
  });

  // Set initial state
  document.querySelector('.tab-btn[data-tab="add-stock"]').classList.add('active');
  document.getElementById('add-stock').classList.add('active');
  document.getElementById('stock-list').classList.remove('active');

  // Custom Unit logic
  var unitSelect = document.getElementById('unitSelect');
  var customUnitInput = document.getElementById('customUnitInput');
  if(unitSelect && customUnitInput) {
    unitSelect.addEventListener('change', function() {
      if (unitSelect.value === 'custom') {
        customUnitInput.style.display = 'block';
        customUnitInput.required = true;
      } else {
        customUnitInput.style.display = 'none';
        customUnitInput.required = false;
      }
    });
    var unitForm = unitSelect.closest('form');
    if(unitForm) {
      unitForm.addEventListener('submit', function(e) {
        if(unitSelect.value === 'custom') {
          if(customUnitInput.value.trim() !== '') {
            unitSelect.value = customUnitInput.value.trim();
          }
        }
      });
    }
  }

  // Pricing Section Calculations
  function updatePricing() {
    const rate = parseFloat(document.getElementById('rate').value) || 0;
    const makingCharges = parseFloat(document.getElementById('makingCharges').value) || 0;
    const weight = parseFloat(document.getElementById('weight').value) || 0;
    // Cost Price per Gram
    const costPerGram = rate + (rate * makingCharges / 100);
    document.getElementById('costPricePerGram').value = (rate ? costPerGram.toFixed(2) : '');
    // Total Taxable Amount
    const totalTaxable = costPerGram * weight;
    document.getElementById('totalTaxableAmount').value = (rate && weight ? totalTaxable.toFixed(2) : '');
    // GST
    const gstChecked = document.getElementById('gstToggle').checked;
    let finalAmount = totalTaxable;
    if (gstChecked) {
      finalAmount = totalTaxable + (totalTaxable * 0.03);
    }
    document.getElementById('finalAmount').value = (rate && weight ? finalAmount.toFixed(2) : '');
  }
  document.getElementById('rate').addEventListener('input', updatePricing);
  document.getElementById('makingCharges').addEventListener('input', updatePricing);
  document.getElementById('weight').addEventListener('input', updatePricing);
  document.getElementById('gstToggle').addEventListener('change', updatePricing);
  updatePricing();

  // Entry Type Toggle Logic
  const entryTypeOpening = document.getElementById('entryTypeOpening');
  const entryTypePurchase = document.getElementById('entryTypePurchase');
  const customSourceDiv = document.getElementById('customSourceDiv');
  const supplierDiv = document.getElementById('supplierDiv');
  const purchaseDetailsSection = document.getElementById('purchaseDetailsSection');
  const gstToggle = document.getElementById('gstToggle');
  function updateEntryTypeUI() {
    if (entryTypeOpening.checked) {
      if(customSourceDiv) customSourceDiv.style.display = '';
      if(supplierDiv) supplierDiv.style.display = 'none';
      if(purchaseDetailsSection) purchaseDetailsSection.style.display = 'none';
      // GST toggle off for opening
      if(gstToggle) gstToggle.checked = false;
    } else {
      if(customSourceDiv) customSourceDiv.style.display = 'none';
      if(supplierDiv) supplierDiv.style.display = '';
      if(purchaseDetailsSection) purchaseDetailsSection.style.display = '';
      // GST toggle ON for purchase
      if(gstToggle) gstToggle.checked = true;
    }
    // Update pricing visuals
    updatePricing();
  }
  entryTypeOpening.addEventListener('change', updateEntryTypeUI);
  entryTypePurchase.addEventListener('change', updateEntryTypeUI);
  updateEntryTypeUI();

  // AJAX form submission for inventoryMetalsForm
  const form = document.getElementById('inventoryMetalsForm');
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(form);
    // Add GST toggle value
    formData.append('gst', document.getElementById('gstToggle').checked ? 'true' : 'false');
    fetch('api/add_stock_entry.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessModal(data.message);
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(err => {
      alert('AJAX error: ' + err);
    });
  });
  // Success Modal
  function showSuccessModal(message) {
    let modal = document.getElementById('successModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'successModal';
      modal.innerHTML = `
      <div class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-lg p-8 max-w-sm w-full text-center">
          <div class="text-green-600 text-4xl mb-2"><i class="fa-solid fa-circle-check"></i></div>
          <div class="font-bold text-lg mb-2">${message}</div>
          <button id="closeSuccessModal" class="mt-4 px-6 py-2 rounded-full bg-green-500 text-white font-bold">OK</button>
        </div>
      </div>
      `;
      document.body.appendChild(modal);
    }
    document.getElementById('closeSuccessModal').onclick = function() {
      modal.remove();
      window.location.reload();
    };
  }

  // --- Supplier Autocomplete & Add Modal ---
  const supplierInput = document.getElementById('supplierInput');
  const supplierIdHidden = document.getElementById('supplierIdHidden');
  const supplierDropdown = document.getElementById('supplierDropdown');
  // Only declare these ONCE for both autocomplete and modal logic:
  const addSupplierBtn = document.getElementById('addSupplierBtn');
  const addSupplierModal = document.getElementById('addSupplierModal');
  const closeAddSupplierModal = document.getElementById('closeAddSupplierModal');
  const cancelAddSupplierModal = document.getElementById('cancelAddSupplierModal');
  const addSupplierForm = document.getElementById('addSupplierForm');
  const addSupplierError = document.getElementById('addSupplierError');
  // Remove any duplicate declarations below this point

  let supplierResults = [];
  let supplierDropdownVisible = false;
  let supplierDropdownIndex = -1;

  function showSupplierDropdown(items) {
    supplierDropdown.innerHTML = '';
    if (items.length === 0) {
      supplierDropdown.innerHTML = '<div class="px-3 py-2 text-gray-400">No suppliers found</div>';
    } else {
      items.forEach((sup, idx) => {
        const div = document.createElement('div');
        div.className = 'px-3 py-2 hover:bg-green-50 cursor-pointer' + (idx === supplierDropdownIndex ? ' bg-green-100' : '');
        div.innerHTML = `<span class="font-bold">${sup.name}</span><br><span class="text-xs text-gray-500">${sup.address ? sup.address : 'N.A'}</span>`;
        div.onclick = () => {
          supplierInput.value = sup.name;
          supplierIdHidden.value = sup.id;
          supplierDropdown.style.display = 'none';
          supplierDropdownVisible = false;
          supplierDropdownIndex = -1;
        };
        supplierDropdown.appendChild(div);
      });
    }
    supplierDropdown.style.display = 'block';
    supplierDropdownVisible = true;
  }

  supplierInput.addEventListener('input', function(e) {
    const q = supplierInput.value.trim();
    supplierIdHidden.value = '';
    supplierDropdownIndex = -1;
    if (q.length < 2) {
      supplierDropdown.style.display = 'none';
      supplierDropdownVisible = false;
      return;
    }
    fetch('api/search_suppliers.php?q=' + encodeURIComponent(q))
      .then(res => res.json())
      .then(data => {
        supplierResults = data;
        showSupplierDropdown(data);
      });
  });

  supplierInput.addEventListener('keydown', function(e) {
    if (e.key === ' ' && supplierInput.value.trim() === '') {
      e.preventDefault();
      fetch('api/search_suppliers.php?q=')
        .then(res => res.json())
        .then(data => {
          supplierResults = data;
          supplierDropdownIndex = -1;
          showSupplierDropdown(data);
        });
    }
    if (supplierDropdownVisible && (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter')) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        supplierDropdownIndex = (supplierDropdownIndex + 1) % supplierResults.length;
        showSupplierDropdown(supplierResults);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        supplierDropdownIndex = (supplierDropdownIndex - 1 + supplierResults.length) % supplierResults.length;
        showSupplierDropdown(supplierResults);
      } else if (e.key === 'Enter' && supplierDropdownIndex >= 0 && supplierDropdownIndex < supplierResults.length) {
        e.preventDefault();
        const sup = supplierResults[supplierDropdownIndex];
        supplierInput.value = sup.name;
        supplierIdHidden.value = sup.id;
        supplierDropdown.style.display = 'none';
        supplierDropdownVisible = false;
        supplierDropdownIndex = -1;
      }
    }
  });

  supplierInput.addEventListener('focus', function() {
    if (supplierResults.length > 0 && supplierInput.value.trim().length >= 2) {
      showSupplierDropdown(supplierResults);
    }
  });

  // --- Supplier Modal Modern UI & Functionality ---
  function openModal() {
    addSupplierModal.classList.add('active');
    addSupplierModal.style.display = 'flex';
    document.body.classList.add('modal-open');
  }
  function closeModal() {
    addSupplierModal.classList.remove('active');
    addSupplierModal.style.display = 'none';
    document.body.classList.remove('modal-open');
  }
  addSupplierBtn.addEventListener('click', openModal);
  closeAddSupplierModal.addEventListener('click', closeModal);
  if (cancelAddSupplierModal) cancelAddSupplierModal.addEventListener('click', closeModal);
  // Close modal when clicking outside modal-content
  addSupplierModal.addEventListener('click', function(e) {
    if (e.target === addSupplierModal) closeModal();
  });

  // Add Supplier AJAX
  addSupplierForm.addEventListener('submit', function(e) {
    e.preventDefault();
    addSupplierError.textContent = '';
    const formData = new FormData(addSupplierForm);
    fetch('api/add_supplier.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success && data.supplier) {
        supplierInput.value = data.supplier.name;
        supplierIdHidden.value = data.supplier.id;
        // Hide modal and reset form
        addSupplierModal.classList.remove('active');
        addSupplierModal.style.display = 'none';
        document.body.classList.remove('modal-open');
        addSupplierForm.reset();
        addSupplierError.textContent = '';
        // Show toast
        const toast = document.getElementById('supplierToast');
        toast.style.display = 'flex';
        setTimeout(() => { toast.style.display = 'none'; }, 2000);
        supplierResults = [data.supplier];
        supplierDropdown.style.display = 'none';
      } else {
        addSupplierError.textContent = data.message || 'Failed to add supplier.';
      }
    })
    .catch(() => {
      addSupplierError.textContent = 'Failed to add supplier.';
    });
  });

  // HSN code autofill based on material type
  const materialTypeSelect = document.getElementById('materialTypeSelect');
  const hsnCodeInput = document.getElementById('hsnCode');
  const hsnMap = {
    "Gold": "7108",
    "Silver": "7106",
    "Diamond": "7102",
    "Gems": "7103",
    "Stone": "7103",
    "KD": "7108",
    "Copper": "7408"
  };
  if (materialTypeSelect && hsnCodeInput) {
    materialTypeSelect.addEventListener('change', function() {
      const selected = materialTypeSelect.value;
      hsnCodeInput.value = hsnMap[selected] || '';
    });
    const selected = materialTypeSelect.value;
    hsnCodeInput.value = hsnMap[selected] || '';
  }

  // Entry Type Active State Toggle
  document.querySelectorAll('input[name="entry_type"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
      document.querySelectorAll('.entry-type-pill').forEach(function(pill) {
        pill.classList.remove('active');
      });
      if (radio.checked) {
        radio.nextElementSibling.classList.add('active');
      }
    });
  });
  // Set initial state on page load
  document.querySelectorAll('input[name="entry_type"]').forEach(function(radio) {
    if (radio.checked) {
      radio.nextElementSibling.classList.add('active');
    }
  });

  // --- Dynamic Payment Status for Purchase Details ---
  (function() {
    const paidAmountPurchase = document.getElementById('paidAmountPurchase');
    const paymentStatusPurchase = document.getElementById('paymentStatusPurchase');
    const finalAmountInput = document.getElementById('finalAmount');
    function updatePaymentStatusPurchase() {
      const paid = parseFloat(paidAmountPurchase.value) || 0;
      const total = parseFloat(finalAmountInput.value) || 0;
      let status = 'Unpaid';
      if (paid <= 0) status = 'Unpaid';
      else if (paid >= total && total > 0) status = 'Paid';
      else if (paid > 0 && paid < total) status = 'Partial';
      paymentStatusPurchase.value = status;
    }
    if (paidAmountPurchase && paymentStatusPurchase && finalAmountInput) {
      paidAmountPurchase.addEventListener('input', updatePaymentStatusPurchase);
      finalAmountInput.addEventListener('input', updatePaymentStatusPurchase);
      updatePaymentStatusPurchase();
    }
  })();
  </script>
</body>
</html>
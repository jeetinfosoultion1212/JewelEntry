<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Start session and include database config
session_start();
require 'config.php';
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

// Handle different URL parameter scenarios
if (isset($_GET['invoice_no'])) {
    $invoice_no = $_GET['invoice_no'];
    
    // If both ID and invoice_no are provided
    if (isset($_GET['id'])) {
        $invoice_id = $_GET['id'];
        
        // Query with both ID and invoice number
        $invoice_query = "SELECT js.*, c.FirstName, c.LastName, c.Address, c.City, c.State, c.PostalCode, 
                        c.PANNumber, c.GSTNumber, c.IsGSTRegistered, c.PhoneNumber, c.Email,
                        f.FirmName, f.Address AS FirmAddress, f.City AS FirmCity, f.State AS FirmState, 
                        f.PostalCode AS FirmPostalCode, f.PANNumber AS FirmPANNumber, 
                        f.GSTNumber AS FirmGSTNumber, f.IsGSTRegistered AS FirmIsGSTRegistered,
                        f.PhoneNumber AS FirmPhoneNumber, f.Email AS FirmEmail, f.Logo AS FirmLogo,
                        f.BankAccountNumber, f.BankName, f.IFSCCode, f.AccountType
                    FROM jewellery_sales js
                    JOIN Customer c ON js.customer_id = c.id
                    JOIN Firm f ON js.firm_id = f.id
                    WHERE js.id = ? AND js.invoice_no = ? AND js.firm_id = ?";
        
        $stmt = $conn->prepare($invoice_query);
        $stmt->bind_param("isi", $invoice_id, $invoice_no, $firm_id);
    } else {
        // Query with just invoice number
        $invoice_query = "SELECT js.*, c.FirstName, c.LastName, c.Address, c.City, c.State, c.PostalCode, 
                        c.PANNumber, c.GSTNumber, c.IsGSTRegistered, c.PhoneNumber, c.Email,
                        f.FirmName, f.Address AS FirmAddress, f.City AS FirmCity, f.State AS FirmState, 
                        f.PostalCode AS FirmPostalCode, f.PANNumber AS FirmPANNumber, 
                        f.GSTNumber AS FirmGSTNumber, f.IsGSTRegistered AS FirmIsGSTRegistered,
                        f.PhoneNumber AS FirmPhoneNumber, f.Email AS FirmEmail, f.Logo AS FirmLogo,
                        f.BankAccountNumber, f.BankName, f.IFSCCode, f.AccountType
                    FROM jewellery_sales js
                    JOIN Customer c ON js.customer_id = c.id
                    JOIN Firm f ON js.firm_id = f.id
                    WHERE js.invoice_no = ? AND js.firm_id = ?";
        
        $stmt = $conn->prepare($invoice_query);
        $stmt->bind_param("si", $invoice_no, $firm_id);
    }
} else if (isset($_GET['id'])) {
    // If only ID is provided
    $invoice_id = $_GET['id'];
    
    $invoice_query = "SELECT js.*, c.FirstName, c.LastName, c.Address, c.City, c.State, c.PostalCode, 
                    c.PANNumber, c.GSTNumber, c.IsGSTRegistered, c.PhoneNumber, c.Email,
                    f.FirmName, f.Address AS FirmAddress, f.City AS FirmCity, f.State AS FirmState, 
                    f.PostalCode AS FirmPostalCode, f.PANNumber AS FirmPANNumber, 
                    f.GSTNumber AS FirmGSTNumber, f.IsGSTRegistered AS FirmIsGSTRegistered,
                    f.PhoneNumber AS FirmPhoneNumber, f.Email AS FirmEmail, f.Logo AS FirmLogo,
                    f.BankAccountNumber, f.BankName, f.IFSCCode, f.AccountType
                FROM jewellery_sales js
                JOIN Customer c ON js.customer_id = c.id
                JOIN Firm f ON js.firm_id = f.id
                WHERE js.id = ? AND js.firm_id = ?";
    
    $stmt = $conn->prepare($invoice_query);
    $stmt->bind_param("ii", $invoice_id, $firm_id);
} else {
    // No valid parameters provided
    echo "<div class='bg-danger-light text-danger p-6 rounded-lg text-center shadow-elegant'>
            <h2 class='font-serif text-2xl mb-3'>Invoice Information Missing</h2>
            <p>Please provide a valid invoice number or ID.</p>
            <a href='dashboard.php' class='inline-block mt-4 bg-primary text-white px-4 py-2 rounded'>
                Return to Dashboard
            </a>
          </div>";
    exit();
}

// Execute the query
$stmt->execute();
$invoice_result = $stmt->get_result();

// Set invoice_id if we only had invoice_no
if (!isset($invoice_id) && $invoice_result->num_rows > 0) {
    $temp = $invoice_result->fetch_assoc();
    $invoice_id = $temp['id'];
    $invoice_no = $temp['invoice_no'];
    
    // Reset result pointer
    $invoice_result->data_seek(0);
}

if ($invoice_result->num_rows == 0) {
    echo "<div class='bg-danger-light text-danger p-6 rounded-lg text-center shadow-elegant'>
            <h2 class='font-serif text-2xl mb-3'>Invoice Not Found</h2>
            <p>The requested invoice was not found or you don't have permission to view it.</p>
            <a href='dashboard.php' class='inline-block mt-4 bg-primary text-white px-4 py-2 rounded'>
                Return to Dashboard
            </a>";
    exit();
}

$invoice = $invoice_result->fetch_assoc();

// Get invoice items with more detailed information
$items_query = "SELECT * FROM Jewellery_sales_items WHERE sale_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Format the numbers for display
function formatIndianRupee($num) {
    // Add null check
    if ($num === null) {
        return '₹0.00';
    }
    return '₹' . number_format((float)$num, 2);
}

// Format date - Fix for undefined invoice_date
function formatDate($date) {
    if (empty($date)) {
        return date("d-M-Y"); // Return current date if date is empty
    }
    return date("d-M-Y", strtotime($date));
}

// Convert number to words
function numberToWords($num) {
    $ones = array(
        0 => "Zero",
        1 => "One",
        2 => "Two",
        3 => "Three",
        4 => "Four",
        5 => "Five",
        6 => "Six",
        7 => "Seven",
        8 => "Eight",
        9 => "Nine",
        10 => "Ten",
        11 => "Eleven",
        12 => "Twelve",
        13 => "Thirteen",
        14 => "Fourteen",
        15 => "Fifteen",
        16 => "Sixteen",
        17 => "Seventeen",
        18 => "Eighteen",
        19 => "Nineteen"
    );
    $tens = array(
        2 => "Twenty",
        3 => "Thirty",
        4 => "Forty",
        5 => "Fifty",
        6 => "Sixty",
        7 => "Seventy",
        8 => "Eighty",
        9 => "Ninety"
    );
    $hundreds = array(
        "Hundred",
        "Thousand",
        "Lakh",
        "Crore"
    );

    $num = number_format($num, 2, '.', '');
    $num_arr = explode(".", $num);
    $wholenum = $num_arr[0];
    $decnum = $num_arr[1];
    $whole_arr = array_reverse(explode(",", $wholenum));
    krsort($whole_arr);
    $rettxt = "";

    foreach ($whole_arr as $key => $i) {
        if ($i < 20) {
            $rettxt .= $ones[$i];
        } elseif ($i < 100) {
            $rettxt .= $tens[substr($i, 0, 1)];
            if (substr($i, 1, 1) != '0') {
                $rettxt .= " " . $ones[substr($i, 1, 1)];
            }
        } else {
            $rettxt .= $ones[substr($i, 0, 1)] . " " . $hundreds[0];
            if (substr($i, 1, 1) != '0') {
                $rettxt .= " " . $tens[substr($i, 1, 1)];
            }
            if (substr($i, 2, 1) != '0') {
                $rettxt .= " " . $ones[substr($i, 2, 1)];
            }
        }
        
        if ($key > 0) {
            $rettxt .= " " . $hundreds[$key] . " ";
        }
    }
    
    if ($decnum > 0) {
        $rettxt .= " and ";
        if ($decnum < 20) {
            $rettxt .= $ones[$decnum];
        } elseif ($decnum < 100) {
            $rettxt .= $tens[substr($decnum, 0, 1)];
            if (substr($decnum, 1, 1) != '0') {
                $rettxt .= " " . $ones[substr($decnum, 1, 1)];
            }
        }
        $rettxt .= " Paise";
    }
    
    return $rettxt . " Only";
}

// Determine payment status class
$statusClass = '';
$statusText = '';
switch ($invoice['payment_status']) {
    case 'Paid':
        $statusClass = 'bg-green-500';
        $statusText = 'Paid';
        break;
    case 'pending':
        $statusClass = 'bg-danger';
        $statusText = 'Pending';
        break;
    case 'partial':
        $statusClass = 'bg-yellow-500';
        $statusText = 'Partial';
        break;
    default:
        $statusClass = 'bg-gray-500';
        $statusText = 'Unknown';
}

// Calculate old gold exchange values if needed
$hasOldGoldExchange = !empty($invoice['urd_amount']) && floatval($invoice['urd_amount']) > 0;
$oldGoldItems = [];
if ($hasOldGoldExchange) {
    // Query to get URD items
    $old_gold_query = "SELECT * FROM urd_items WHERE sale_id = ? AND status = 'exchanged'";
    $stmt = $conn->prepare($old_gold_query);
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $old_gold_result = $stmt->get_result();
    while ($old_item = $old_gold_result->fetch_assoc()) {
        $oldGoldItems[] = $old_item;
    }
}

// Calculate totals
$metal_total = 0;
$stone_total = 0;
$making_charges = 0;
$discount = $invoice['discount'] ?? 0;
$other_charges = $invoice['other_charges'] ?? 0;
$paid_amount = $invoice['total_paid_amount'] ?? 0;
$advance_amount = $invoice['advance_amount'] ?? 0;

foreach ($items as $item) {
    $metal_total += $item['metal_amount'] ?? 0;
    $stone_total += $item['stone_price'] ?? 0;
    $making_charges += $item['making_charges'] ?? 0;
}

$subtotal = $metal_total + $stone_total + $making_charges + $other_charges - $discount;
$gst_rate = 0.03; // 3% GST
$gst_amount = $subtotal * $gst_rate;
$grand_total = $subtotal + $gst_amount;
$due_amount = $grand_total - $paid_amount - $advance_amount;

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jewelry Receipt - <?php echo $invoice['invoice_no']; ?></title>
  <style>
    /* 80mm thermal paper styling */
    body { width:80mm; margin:0 auto; padding:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; font-size:11px; color:#000; }
    .receipt { padding:10px 6px; }

    /* Header */
    .header { text-align:center; margin-bottom:8px; }
    .logo { width:55px; margin:0 auto 5px; }
    .header h1 { margin:0; font-size:18px; font-weight:900; letter-spacing:2px; text-transform:uppercase; }
    .header p { margin:3px 0; font-size:9px; }

    /* Dividers */
    .separator { border-top:1px dashed #000; margin:6px 0; }

    /* Details Section */
    .details, .trx-details { width:100%; border-collapse:collapse; margin-bottom:8px; font-size:10px; }
    .details td, .trx-details td { padding:3px 0; }
    .details td:first-child, .trx-details td:first-child { width:50%; }

    /* Items Table - Enhanced */
    .items { width:100%; border-collapse:collapse; font-size:9px; }
    .items th, .items td { padding:3px 1px; vertical-align:top; }
    .items th { text-align:left; font-weight:700; border-bottom:1px solid #000; background:#f0f0f0; }
    .items td { text-align:right; }
    .items td.name { text-align:left; }
    .items td.center { text-align:center; }
    .item-detail { font-size:8px; color:#333; font-style:italic; }
    .item-code { font-family:monospace; }
    .item-specs { margin-top:2px; }
    .item-group { border-bottom:1px dotted #ccc; padding-bottom:5px; margin-bottom:5px; }
    .weight-details { font-size:8px; margin-top:2px; }
    .stone-details { font-size:8px; margin-top:2px; color:#555; }

    /* Summary */
    .summary { width:100%; border-collapse:collapse; margin-top:8px; font-size:10px; }
    .summary td { padding:3px 0; }
    .summary tr.total td { font-weight:800; border-top:1px solid #000; border-bottom:1px solid #000; }
    .summary td.label { text-align:left; }
    .summary td.value { text-align:right; }

    /* Footer Notes */
    .note { margin-top:8px; font-size:9px; line-height:1.3; }
    .note p { margin:3px 0; }

    /* Status Tag */
    .status-tag {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 3px;
      color: white;
      font-size: 9px;
      font-weight: bold;
    }
    .bg-green-500 { background-color: #10B981; }
    .bg-yellow-500 { background-color: #F59E0B; }
    .bg-danger { background-color: #EF4444; }
    .bg-gray-500 { background-color: #6B7280; }

    @media print { .no-print { display:none; } }
  </style>
</head>
<body>
  <div class="receipt">
    <!-- Header -->
    <div class="header">
      <?php if(!empty($invoice['FirmLogo'])): ?>
      <img src="<?php echo htmlspecialchars($invoice['FirmLogo']); ?>" alt="Logo" class="logo">
      <?php else: ?>
      <img src="img/default-logo.png" alt="Logo" class="logo">
      <?php endif; ?>
      <h1><?php echo htmlspecialchars($invoice['FirmName']); ?></h1>
      <p><?php echo htmlspecialchars($invoice['FirmAddress'] . ', ' . $invoice['FirmCity'] . ', ' . $invoice['FirmPostalCode']); ?></p>
      <p>GSTIN:<?php echo htmlspecialchars($invoice['FirmGSTNumber']); ?> | PAN:<?php echo htmlspecialchars($invoice['FirmPANNumber']); ?></p>
      <p>Ph: <?php echo htmlspecialchars($invoice['FirmPhoneNumber']); ?> | Email: <?php echo htmlspecialchars($invoice['FirmEmail']); ?></p>
    </div>
    <div class="separator"></div>

    <!-- Invoice & Customer -->
    <table class="details">
      <tr>
        <td><strong>Invoice:</strong> <?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
        <td style="text-align:right;"><strong>Date:</strong> <?php echo formatDate($invoice['invoice_date'] ?? ''); ?></td>
      </tr>
      <tr>
        <td><strong>Customer:</strong> <?php echo htmlspecialchars($invoice['FirstName'] . ' ' . $invoice['LastName']); ?></td>
        <td style="text-align:right;"><strong>ID:</strong> CUST-<?php echo htmlspecialchars($invoice['customer_id']); ?></td>
      </tr>
      <tr>
        <td><strong>Sales Rep:</strong> <?php echo htmlspecialchars($invoice['user_id'] . ' - ' . ($invoice['salesperson_name'] ?? 'Staff')); ?></td>
        <td style="text-align:right;"><strong>Terms:</strong> <?php echo $invoice['payment_terms'] ?? '30 Days'; ?></td>
      </tr>
    </table>

    <!-- Transaction Details -->
    <table class="trx-details">
      <tr>
        <td><strong>Payment Method:</strong> <?php echo htmlspecialchars($invoice['payment_method'] ?? 'Cash'); ?></td>
        <td style="text-align:right;">
          <strong>Status:</strong> 
          <span class="status-tag <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
        </td>
      </tr>
      <tr>
        <td><strong>Transaction ID:</strong> <?php echo htmlspecialchars($invoice['transaction_id'] ?? 'TXN-' . $invoice_id); ?></td>
        <td style="text-align:right;"><strong>Coupon:</strong> <?php echo htmlspecialchars($invoice['coupon_code'] ?? '-'); ?></td>
      </tr>
    </table>
    <div class="separator"></div>

    <!-- Enhanced Items Table with improved details -->
    <table class="items">
      <thead>
        <tr>
          <th style="width:36%;">Item</th>
          <th style="width:11%; text-align:center;">Gross/Net</th>
          <th style="width:8%; text-align:center;">Stone</th>
          <th style="width:6%; text-align:center;">QTY</th>
          <th style="width:19%; text-align:right;">Rate</th>
          <th style="width:20%; text-align:right;">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($items as $index => $item): ?>
        <tr class="<?php echo ($index < count($items) - 1) ? 'item-group' : ''; ?>">
          <td class="name">
            <strong><?php echo htmlspecialchars($item['product_name'] ?? 'Jewelry Item'); ?></strong>
            <div class="item-detail">
              <span class="item-code">SKU: <?php echo htmlspecialchars($item['product_id'] ?? 'Jewelry Item'); ?></strong>
              <span class="item-specs">
                <?php echo htmlspecialchars($item['purity'] ?? '22K'); ?> 
                <?php echo htmlspecialchars($item['metal_type'] ?? 'Gold'); ?> | 
              
              </span><br>
              
            </div>
          </td>
          <td class="center">
            <?php 
              $gross_weight = floatval($item['gross_weight'] ?? 0);
              $stone_weight = floatval($item['stone_weight'] ?? 0);
              $net_weight = $gross_weight - $stone_weight;
            ?>
            <div class="weight-details">
              G: <?php echo number_format($gross_weight, 2); ?><br>
              N: <?php echo number_format($net_weight, 2); ?>
            </div>
          </td>
          <td class="center">
            <?php if(!empty($item['stone_type']) || !empty($item['stone_quantity']) || !empty($item['stone_weight'])): ?>
            <div class="stone-details">
              <?php echo !empty($item['stone_quantity']) ? (int)$item['stone_quantity'] : '0'; ?><br>
              <?php echo !empty($item['stone_weight']) ? number_format((float)$item['stone_weight'], 2) : '0.00'; ?>
            </div>
            <?php else: ?>
            -
            <?php endif; ?>
          </td>
          <td class="center"><?php echo (int)($item['quantity'] ?? 1); ?></td>
          <td>
            <?php 
              $rate = $item['rate_per_gram'] ?? $item['purity_rate'] ?? 0;
              echo formatIndianRupee($rate);
            ?>
            <div class="item-detail"><?php echo htmlspecialchars($item['purity'] ?? '22K'); ?></div>
          </td>
          <td>
            <?php echo formatIndianRupee($item['metal_amount'] ?? 0); ?>
            <?php if(!empty($item['stone_price']) && $item['stone_price'] > 0): ?>
            <div class="item-detail">
              Stone: <?php echo formatIndianRupee($item['stone_price']); ?>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        
        <?php if(count($items) === 0): ?>
        <tr>
          <td colspan="6" style="text-align:center;">No items found for this invoice</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div class="separator"></div>

    <!-- Old Gold Exchange Section (if applicable) -->
    <?php if($hasOldGoldExchange && count($oldGoldItems) > 0): ?>
    <h3 style="margin:5px 0; font-size:12px;">Old Gold Exchange</h3>
    <table class="items">
      <thead>
        <tr>
          <th style="width:45%;">Item</th>
          <th style="width:15%; text-align:center;">Wt(gm)</th>
          <th style="width:20%; text-align:right;">Rate</th>
          <th style="width:20%; text-align:right;">Value</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($oldGoldItems as $oldItem): ?>
        <tr>
          <td class="name">
            <?php echo htmlspecialchars($oldItem['item_name'] ?? 'Old Gold Item'); ?>
            <div class="item-detail">
              <span class="item-specs"><?php echo htmlspecialchars($oldItem['purity'] ?? '22K'); ?> | <?php echo htmlspecialchars($oldItem['description'] ?? 'Used Jewelry'); ?></span>
            </div>
          </td>
          <td class="center"><?php echo number_format((float)($oldItem['weight'] ?? 0), 2); ?></td>
          <td style="text-align:right;"><?php echo formatIndianRupee($oldItem['rate_per_gram'] ?? 0); ?></td>
          <td style="text-align:right;"><?php echo formatIndianRupee($oldItem['total_value'] ?? 0); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="separator"></div>
    <?php endif; ?>

    <!-- Summary -->
    <table class="summary">
      <tr><td class="label">Metal Total</td><td class="value"><?php echo formatIndianRupee($metal_total); ?></td></tr>
      <tr><td class="label">Stone Total</td><td class="value"><?php echo formatIndianRupee($stone_total); ?></td></tr>
      <tr><td class="label">Making Chg</td><td class="value"><?php echo formatIndianRupee($making_charges); ?></td></tr>
      <tr><td class="label">Other Chg</td><td class="value"><?php echo formatIndianRupee($other_charges); ?></td></tr>
      <?php if($hasOldGoldExchange): ?>
      <tr><td class="label">Old Gold Value</td><td class="value"><?php echo formatIndianRupee($invoice['urd_amount'] ?? 0); ?></td></tr>
      <?php endif; ?>
      <tr><td class="label">Discount</td><td class="value"><?php echo formatIndianRupee($discount); ?></td></tr>
      <tr class="total"><td class="label">Subtotal</td><td class="value"><?php echo formatIndianRupee($subtotal); ?></td></tr>
      <tr><td class="label">GST (3%)</td><td class="value"><?php echo formatIndianRupee($gst_amount); ?></td></tr>
      <tr class="total"><td class="label">Grand Total</td><td class="value"><?php echo formatIndianRupee($grand_total); ?></td></tr>
      <tr><td class="label">Paid</td><td class="value"><?php echo formatIndianRupee($paid_amount); ?></td></tr>
      <tr><td class="label">Advance</td><td class="value"><?php echo formatIndianRupee($advance_amount); ?></td></tr>
      <tr class="total"><td class="label">Due</td><td class="value"><?php echo formatIndianRupee($due_amount); ?></td></tr>
    </table>

    <!-- Amount in Words -->
    <div style="margin-top:10px; font-size:10px;">
      <strong>Amount in words:</strong> <?php echo numberToWords($grand_total); ?>
    </div>

    <!-- Bank Details (if applicable) -->
    <?php if(!empty($invoice['BankAccountNumber'])): ?>
    <div style="margin-top:10px; font-size:10px; border:1px dashed #000; padding:5px;">
      <strong>Bank Details:</strong><br>
      Account: <?php echo htmlspecialchars($invoice['BankAccountNumber']); ?><br>
      Bank: <?php echo htmlspecialchars($invoice['BankName']); ?><br>
      IFSC: <?php echo htmlspecialchars($invoice['IFSCCode']); ?><br>
      Type: <?php echo htmlspecialchars($invoice['AccountType']); ?>
    </div>
    <?php endif; ?>

    <div class="note">
      <p><em>Note: Please settle outstanding amount within the agreed credit terms to avoid penalties.</em></p>
      <p>Warranty: 6 months against manufacturing defects</p>
      <p>Return Policy: 7 days exchange only with receipt</p>
      <p>Follow us: FB/IG @<?php echo strtolower(str_replace(' ', '', $invoice['FirmName'])); ?></p>
    </div>

    <div class="footer">
      <p style="text-align:center; margin-top:10px; font-style:italic; font-size:8px;">Thank you for your business!</p>
      <p class="no-print" style="text-align:center; margin-top:15px;">
        <button onclick="window.print()" style="padding:8px 15px; background:#0066cc; color:white; border:none; border-radius:4px; cursor:pointer;">
          Print Receipt
        </button>
        <a href="dashboard.php" style="display:inline-block; margin-left:10px; padding:8px 15px; background:#666; color:white; text-decoration:none; border-radius:4px;">
          Back to Dashboard
        </a>
      </p>
    </div>
  </div>
</body>
</html>
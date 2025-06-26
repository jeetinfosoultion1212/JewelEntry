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
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle different URL parameter scenarios
if (isset($_GET['invoice_no'])) {
    $invoice_no = $_GET['invoice_no'];
    
    if (isset($_GET['id'])) {
        $invoice_id = $_GET['id'];
        
        $invoice_query = "SELECT js.*, c.FirstName, c.LastName, c.Address, c.City, c.State, c.PostalCode, 
                        c.PANNumber, c.GSTNumber, c.IsGSTRegistered, c.PhoneNumber, c.Email,
                        f.FirmName, f.Address AS FirmAddress, f.City AS FirmCity, f.State AS FirmState, 
                        f.PostalCode AS FirmPostalCode, f.PANNumber AS FirmPANNumber, 
                        f.GSTNumber AS FirmGSTNumber, f.IsGSTRegistered AS FirmIsGSTRegistered,
                        f.PhoneNumber AS FirmPhoneNumber, f.Email AS FirmEmail, f.Logo AS FirmLogo,
                        f.BankAccountNumber, f.BankName, f.IFSCCode, f.AccountType,
                        jsi.stone_type, jsi.stone_weight, jsi.stone_price
                    FROM jewellery_sales js
                    JOIN customer c ON js.customer_id = c.id
                    JOIN Firm f ON js.firm_id = f.id
                    LEFT JOIN Jewellery_sales_items jsi ON js.id = jsi.sale_id
                    WHERE js.id = ? AND js.invoice_no = ? AND js.firm_id = ?";
        
        $stmt = $conn->prepare($invoice_query);
        $stmt->bind_param("isi", $invoice_id, $invoice_no, $firm_id);
    } else {
        $invoice_query = "SELECT js.*, c.FirstName, c.LastName, c.Address, c.City, c.State, c.PostalCode, 
                        c.PANNumber, c.GSTNumber, c.IsGSTRegistered, c.PhoneNumber, c.Email,
                        f.FirmName, f.Address AS FirmAddress, f.City AS FirmCity, f.State AS FirmState, 
                        f.PostalCode AS FirmPostalCode, f.PANNumber AS FirmPANNumber, 
                        f.GSTNumber AS FirmGSTNumber, f.IsGSTRegistered AS FirmIsGSTRegistered,
                        f.PhoneNumber AS FirmPhoneNumber, f.Email AS FirmEmail, f.Logo AS FirmLogo,
                        f.BankAccountNumber, f.BankName, f.IFSCCode, f.AccountType,
                        jsi.stone_type, jsi.stone_weight, jsi.stone_price
                    FROM jewellery_sales js
                    JOIN customer c ON js.customer_id = c.id
                    JOIN Firm f ON js.firm_id = f.id
                    LEFT JOIN Jewellery_sales_items jsi ON js.id = jsi.sale_id
                    WHERE js.invoice_no = ? AND js.firm_id = ?";
        
        $stmt = $conn->prepare($invoice_query);
        $stmt->bind_param("si", $invoice_no, $firm_id);
    }
} else if (isset($_GET['id'])) {
    $invoice_id = $_GET['id'];
    
    $invoice_query = "SELECT js.*, c.FirstName, c.LastName, c.Address, c.City, c.State, c.PostalCode, 
                    c.PANNumber, c.GSTNumber, c.IsGSTRegistered, c.PhoneNumber, c.Email,
                    f.FirmName, f.Address AS FirmAddress, f.City AS FirmCity, f.State AS FirmState, 
                    f.PostalCode AS FirmPostalCode, f.PANNumber AS FirmPANNumber, 
                    f.GSTNumber AS FirmGSTNumber, f.IsGSTRegistered AS FirmIsGSTRegistered,
                    f.PhoneNumber AS FirmPhoneNumber, f.Email AS FirmEmail, f.Logo AS FirmLogo,
                    f.BankAccountNumber, f.BankName, f.IFSCCode, f.AccountType,
                    jsi.stone_type, jsi.stone_weight, jsi.stone_price
                FROM jewellery_sales js
                JOIN customer c ON js.customer_id = c.id
                JOIN Firm f ON js.firm_id = f.id
                LEFT JOIN Jewellery_sales_items jsi ON js.id = jsi.sale_id
                WHERE js.id = ? AND js.firm_id = ?";
    
    $stmt = $conn->prepare($invoice_query);
    $stmt->bind_param("ii", $invoice_id, $firm_id);
} else {
    echo "<div style='background: #fee; color: #c33; padding: 20px; text-align: center; border-radius: 8px; margin: 20px;'>
            <h2>Invoice Information Missing</h2>
            <p>Please provide a valid invoice number or ID.</p>
            <a href='dashboard.php' style='display: inline-block; margin-top: 10px; background: #1a365d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>
                Return to Dashboard
            </a>
          </div>";
    exit();
}

// Execute the query
$stmt->execute();
$invoice_result = $stmt->get_result();

if (!isset($invoice_id) && $invoice_result->num_rows > 0) {
    $temp = $invoice_result->fetch_assoc();
    $invoice_id = $temp['id'];
    $invoice_no = $temp['invoice_no'];
    $invoice_result->data_seek(0);
}

if ($invoice_result->num_rows == 0) {
    echo "<div style='background: #fee; color: #c33; padding: 20px; text-align: center; border-radius: 8px; margin: 20px;'>
            <h2>Invoice Not Found</h2>
            <p>The requested invoice was not found or you don't have permission to view it.</p>
            <a href='dashboard.php' style='display: inline-block; margin-top: 10px; background: #1a365d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>
                Return to Dashboard
            </a>
          </div>";
    exit();
}

$invoice = $invoice_result->fetch_assoc();

// Get invoice items
$items_query = "SELECT * FROM Jewellery_sales_items WHERE sale_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Safe function to handle null values
function safeHtml($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

// Format the numbers for display
function formatIndianRupee($num) {
    return '₹' . number_format(floatval($num ?? 0), 2);
}

// Format date
function formatDate($date) {
    return $date ? date("M d, Y", strtotime($date)) : '';
}

// Fixed Convert number to words function
function numberToWords($num) {
    $ones = array(
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen'
    );
    
    $tens = array(
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
    );
    
    if ($num == 0) return 'Zero Rupees Only';
    
    $num = floatval($num);
    $rupees = floor($num);
    $paise = round(($num - $rupees) * 100);
    
    $result = '';
    
    // Convert rupees
    if ($rupees >= 10000000) { // Crores
        $crores = floor($rupees / 10000000);
        $result .= convertHundreds($crores) . ' Crore ';
        $rupees %= 10000000;
    }
    
    if ($rupees >= 100000) { // Lakhs
        $lakhs = floor($rupees / 100000);
        $result .= convertHundreds($lakhs) . ' Lakh ';
        $rupees %= 100000;
    }
    
    if ($rupees >= 1000) { // Thousands
        $thousands = floor($rupees / 1000);
        $result .= convertHundreds($thousands) . ' Thousand ';
        $rupees %= 1000;
    }
    
    if ($rupees > 0) {
        $result .= convertHundreds($rupees);
    }
    
    $result = trim($result) . ' Rupees';
    
    if ($paise > 0) {
        $result .= ' and ' . convertHundreds($paise) . ' Paise';
    }
    
    return $result . ' Only';
}

function convertHundreds($num) {
    $ones = array(
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen'
    );
    
    $tens = array(
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
    );
    
    $result = '';
    
    if ($num >= 100) {
        $result .= $ones[floor($num / 100)] . ' Hundred ';
        $num %= 100;
    }
    
    if ($num >= 20) {
        $result .= $tens[floor($num / 10)] . ' ';
        $num %= 10;
    }
    
    if ($num > 0) {
        $result .= $ones[$num] . ' ';
    }
    
    return trim($result);
}

// Calculate old gold exchange values if needed
$hasOldGoldExchange = !empty($invoice['urd_amount']) && floatval($invoice['urd_amount']) > 0;
$oldGoldItems = [];
if ($hasOldGoldExchange) {
    $old_gold_query = "SELECT * FROM urd_items WHERE sale_id = ? AND status = 'exchanged'";
    $stmt = $conn->prepare($old_gold_query);
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $old_gold_result = $stmt->get_result();
    while ($old_item = $old_gold_result->fetch_assoc()) {
        $oldGoldItems[] = $old_item;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safeHtml($invoice['FirmName']); ?> - Invoice #<?php echo safeHtml($invoice['invoice_no']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #374151;
            background: #f9fafb;
        }
        
        .invoice-container {
            max-width: 210mm;
            margin: 10px auto;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            overflow: hidden;
        }
        
        /* Compact Header */
        .invoice-header {
            background: white;
            padding: 15px 20px 10px 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .company-logo {
            width: 40px;
            height: 40px;
            background: #1f2937;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }
        
        .company-logo img {
            max-width: 35px;
            max-height: 35px;
            border-radius: 4px;
        }
        
        .company-details h1 {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 2px;
        }
        
        .company-details p {
            color: #6b7280;
            font-size: 10px;
        }
        
        .invoice-title {
            text-align: center;
            flex: 1;
        }
        
        .invoice-title h2 {
            font-size: 24px;
            font-weight: 300;
            color: #10b981;
            margin-bottom: 5px;
        }
        
        .invoice-meta {
            text-align: right;
            min-width: 150px;
        }
        
        .invoice-meta-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 11px;
        }
        
        .invoice-meta-item .label {
            color: #6b7280;
            margin-right: 15px;
        }
        
        .invoice-meta-item .value {
            color: #1f2937;
            font-weight: 600;
        }
        
        .bis-badge {
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        /* Compact Content */
        .invoice-content {
            padding: 0 20px 20px 20px;
        }
        
        /* Compact Billing Section */
        .billing-section {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .billing-card {
            flex: 1;
            background: #ecfdf5;
            border: 1px solid #d1fae5;
            border-radius: 6px;
            padding: 12px;
        }
        
        .billing-card h3 {
            font-size: 12px;
            font-weight: 600;
            color: #10b981;
            margin-bottom: 8px;
        }
        
        .billing-info {
            font-size: 10px;
            line-height: 1.4;
        }
        
        .billing-info .company-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
            font-size: 11px;
        }
        
        .billing-info .address-line {
            color: #4b5563;
            margin-bottom: 2px;
        }
        
        .tax-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #d1fae5;
            font-size: 9px;
        }
        
        .tax-details .label {
            font-weight: 500;
            color: #374151;
        }
        
        .tax-details .value {
            color: #1f2937;
        }
        
        /* Compact Supply Info */
        .supply-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 8px 12px;
            background: #f9fafb;
            border-radius: 4px;
            font-size: 10px;
        }
        
        .supply-info .label {
            font-weight: 500;
            color: #374151;
        }
        
        .supply-info .value {
            color: #1f2937;
            font-weight: 600;
        }
        
        /* Compact Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .items-table th {
            background: #10b981;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: 600;
            font-size: 9px;
        }
        
        .items-table td {
            padding: 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
        }
        
        .items-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .font-medium {
            font-weight: 500;
        }
        
        /* Compact Summary Section */
        .summary-section {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .summary-left {
            flex: 1;
        }
        
        .summary-right {
            flex: 1;
            max-width: 300px;
        }
        
        /* Compact Bank Details */
        .bank-section {
            background: #ecfdf5;
            border: 1px solid #d1fae5;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
        }
        
        .bank-section h3 {
            font-size: 12px;
            font-weight: 600;
            color: #10b981;
            margin-bottom: 8px;
        }
        
        .bank-details {
            display: flex;
            gap: 15px;
        }
        
        .bank-info {
            flex: 2;
        }
        
        .bank-info-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 4px;
            font-size: 9px;
        }
        
        .bank-info-grid .label {
            font-weight: 500;
            color: #374151;
        }
        
        .bank-info-grid .value {
            color: #1f2937;
        }
        
        .qr-section {
            flex: 1;
            text-align: center;
        }
        
        .qr-code {
            width: 60px;
            height: 60px;
            background: #f3f4f6;
            border: 2px dashed #9ca3af;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 5px auto;
            font-size: 8px;
            color: #6b7280;
        }
        
        .qr-label {
            font-size: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        /* Compact Payment Summary */
        .payment-summary {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 10px;
        }
        
        .summary-row.total {
            border-top: 2px solid #10b981;
            margin-top: 8px;
            padding-top: 8px;
            font-weight: 700;
            font-size: 12px;
            color: #1f2937;
        }
        
        .summary-row .label {
            color: #374151;
        }
        
        .summary-row .value {
            color: #1f2937;
            font-weight: 500;
        }
        
        .summary-row .value.negative {
            color: #ef4444;
        }
        
        /* Compact Amount in Words */
        .amount-words {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 4px;
            padding: 8px;
            margin-top: 10px;
            font-size: 9px;
        }
        
        .amount-words .label {
            font-weight: 600;
            color: #92400e;
            margin-bottom: 3px;
        }
        
        .amount-words .value {
            color: #1f2937;
            font-style: italic;
        }
        
        /* Compact Terms */
        .terms-section {
            margin-top: 15px;
            padding: 10px;
            background: #f9fafb;
            border-radius: 4px;
        }
        
        .terms-section h3 {
            font-size: 11px;
            font-weight: 600;
            color: #10b981;
            margin-bottom: 6px;
        }
        
        .terms-list {
            list-style: none;
            padding: 0;
        }
        
        .terms-list li {
            margin-bottom: 3px;
            font-size: 9px;
            color: #4b5563;
            position: relative;
            padding-left: 12px;
        }
        
        .terms-list li:before {
            content: "•";
            color: #10b981;
            font-weight: bold;
            position: absolute;
            left: 0;
        }
        
        /* Compact Signature Section */
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        
        .signature-box {
            text-align: center;
            flex: 1;
            margin: 0 10px;
        }
        
        .signature-line {
            height: 40px;
            border-bottom: 1px solid #9ca3af;
            margin-bottom: 5px;
        }
        
        .signature-label {
            font-size: 9px;
            font-weight: 500;
            color: #374151;
        }
        
        .signature-sublabel {
            font-size: 8px;
            color: #6b7280;
            margin-top: 2px;
        }
        
        /* Compact Footer */
        .invoice-footer {
            background: #f9fafb;
            padding: 10px 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
        }
        
        .footer-contact {
            margin-bottom: 5px;
        }
        
        .footer-contact span {
            margin: 0 10px;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                font-size: 10px;
            }
            
            .invoice-container {
                box-shadow: none;
                max-width: none;
                width: 100%;
                margin: 0;
                border-radius: 0;
            }
            
            .print-hidden {
                display: none !important;
            }
            
            .invoice-header,
            .invoice-content {
                padding-left: 15px;
                padding-right: 15px;
            }
        }
        
        @page {
            size: A4;
            margin: 10mm;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 15px 0;
            padding: 15px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-outline {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- New Header Layout -->
        <div class="invoice-header">
            <!-- Centered Firm Info -->
            <div class="firm-header-center" style="text-align:center; margin-bottom:10px;">
                <div class="company-logo" style="margin:0 auto;">
                    <?php if (!empty($invoice['FirmLogo'])): ?>
                        <img src="<?php echo safeHtml($invoice['FirmLogo']); ?>" alt="Logo">
                    <?php else: ?>
                        <?php echo strtoupper(substr($invoice['FirmName'] ?? 'JW', 0, 2)); ?>
                    <?php endif; ?>
                </div>
                <div class="company-details">
                    <h1><?php echo safeHtml($invoice['FirmName'], 'JEWELLERY STORE'); ?></h1>
                    <p>Premium Jewellery Collection</p>
                </div>
            </div>
            <!-- Flex row: Invoice meta left, Billing right -->
            <div class="header-flex-row" style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px;">
                <!-- Invoice Meta Info -->
                <div class="invoice-meta" style="min-width:180px;">
                    <div class="invoice-meta-item">
                        <span class="label">Invoice #</span>
                        <span class="value"><?php echo safeHtml($invoice['invoice_no'], 'NG08'); ?></span>
                    </div>
                    <div class="invoice-meta-item">
                        <span class="label">Invoice Date</span>
                        <span class="value"><?php echo formatDate($invoice['sale_date']); ?></span>
                    </div>
                    <div class="invoice-meta-item">
                        <span class="label">Due Date</span>
                        <span class="value"><?php echo formatDate(date('Y-m-d', strtotime(($invoice['sale_date'] ?? date('Y-m-d')) . ' + 7 days'))); ?></span>
                    </div>
                    <div class="bis-badge">BIS</div>
                </div>
                <!-- Billing Section Side by Side -->
                <div class="billing-section" style="flex:1; display:flex; gap:15px;">
                    <div class="billing-card">
                        <h3>Billed by</h3>
                        <div class="billing-info">
                            <div class="company-name"><?php echo safeHtml($invoice['FirmName'], 'N/A'); ?></div>
                            <div class="address-line"><?php echo safeHtml($invoice['FirmAddress'], 'Address not available'); ?></div>
                            <div class="address-line">
                                <?php 
                                $firmLocation = trim(
                                    safeHtml($invoice['FirmCity'], '') . 
                                    ($invoice['FirmCity'] ? ', ' : '') . 
                                    safeHtml($invoice['FirmState'], '') . 
                                    ($invoice['FirmPostalCode'] ? ' - ' . safeHtml($invoice['FirmPostalCode']) : '')
                                );
                                echo $firmLocation ?: 'Location not available';
                                ?>
                            </div>
                            <div class="tax-details">
                                <span class="label">GSTIN</span>
                                <span class="value"><?php echo safeHtml($invoice['FirmGSTNumber'], 'N/A'); ?></span>
                                <span class="label">PAN</span>
                                <span class="value"><?php echo safeHtml($invoice['FirmPANNumber'], 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="billing-card">
                        <h3>Billed to</h3>
                        <div class="billing-info">
                            <div class="company-name">
                                <?php echo trim(safeHtml($invoice['FirstName'], '') . ' ' . safeHtml($invoice['LastName'], '')) ?: 'demo'; ?>
                            </div>
                            <div class="address-line"><?php echo safeHtml($invoice['Address'], 'Address not available'); ?></div>
                            <div class="address-line">
                                <?php 
                                $customerLocation = trim(
                                    safeHtml($invoice['City'], '') . 
                                    ($invoice['City'] ? ', ' : '') . 
                                    safeHtml($invoice['State'], '') . 
                                    ($invoice['PostalCode'] ? ' - ' . safeHtml($invoice['PostalCode']) : '')
                                );
                                echo $customerLocation ?: 'Location not available';
                                ?>
                            </div>
                            <div class="tax-details">
                                <span class="label">GSTIN</span>
                                <span class="value"><?php echo safeHtml($invoice['GSTNumber'], 'N/A'); ?></span>
                                <span class="label">PAN</span>
                                <span class="value"><?php echo safeHtml($invoice['PANNumber'], 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Compact Content -->
        <div class="invoice-content">
            <!-- Compact Place of Supply -->
            <div class="supply-info">
                <div>
                    <span class="label">Place of Supply</span>
                    <span class="value"><?php echo safeHtml($invoice['FirmState'], 'N/A'); ?></span>
                </div>
                <div>
                    <span class="label">Country of Supply</span>
                    <span class="value">India</span>
                </div>
            </div>
            
            <!-- Compact Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item #/Item description</th>
                        <th>HSN</th>
                        <th>Qty.</th>
                        <th>Purity</th>
                        <th>Rate/Gm</th>
                        <th>Net Wt</th>
                        <th>Labour</th>
                        <th>Stone</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 1;
                    foreach ($items as $item): 
                    ?>
                    <tr>
                        <td><?php echo $count . '. ' . safeHtml($item['product_name'], 'RING'); ?></td>
                        <td class="text-center">7113</td>
                        <td class="text-center">1</td>
                        <td class="text-center"><?php echo safeHtml($item['purity'], '92.00'); ?>kt</td>
                        <td class="text-right">₹<?php echo number_format(floatval($item['purity_rate'] ?? 9192.18), 2); ?></td>
                        <td class="text-right"><?php echo safeHtml($item['net_weight'], '3.600'); ?>g</td>
                        <td class="text-right">₹<?php echo number_format(floatval($item['making_charges'] ?? 3309.18), 2); ?></td>
                        <td class="text-right">₹<?php echo number_format(floatval($item['stone_price'] ?? 0), 2); ?></td>
                        <td class="text-right font-medium">₹<?php echo number_format(floatval($item['total'] ?? 36436.03), 2); ?></td>
                    </tr>
                    <?php 
                        $count++;
                    endforeach; 
                    
                    if (empty($items)): 
                    ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px; color: #6b7280;">No items found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Compact Summary Section -->
            <div class="summary-section">
                <!-- Left Side - Bank Details -->
                <div class="summary-left">
                    <div class="bank-section">
                        <h3>Bank & Payment Details</h3>
                        <div class="bank-details">
                            <div class="bank-info">
                                <div class="bank-info-grid">
                                    <span class="label">Account Holder Name</span>
                                    <span class="value"><?php echo safeHtml($invoice['FirmName'], 'Mahalaxmi HM'); ?></span>
                                    <span class="label">Account Number</span>
                                    <span class="value"><?php echo safeHtml($invoice['BankAccountNumber'], 'N/A'); ?></span>
                                    <span class="label">IFSC</span>
                                    <span class="value"><?php echo safeHtml($invoice['IFSCCode'], 'N/A'); ?></span>
                                    <span class="label">Account Type</span>
                                    <span class="value"><?php echo safeHtml($invoice['AccountType'], 'Current'); ?></span>
                                    <span class="label">Bank</span>
                                    <span class="value"><?php echo safeHtml($invoice['BankName'], 'N/A'); ?></span>
                                    <span class="label">UPI</span>
                                    <span class="value">mahalaxmihm@upi</span>
                                </div>
                            </div>
                            <div class="qr-section">
                                <div class="qr-label">UPI - Scan to Pay</div>
                                <div class="qr-code">QR CODE</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Payment Summary -->
                <div class="summary-right">
                    <div class="payment-summary">
                        <div class="summary-row">
                            <span class="label">Sub Total</span>
                            <span class="value">₹<?php echo number_format(floatval($invoice['subtotal'] ?? 36436.03), 2); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="label">Making Charges</span>
                            <span class="value">₹<?php echo number_format(floatval($invoice['total_making_charges'] ?? 3309.18), 2); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="label">Discount(0.0%)</span>
                            <span class="value negative">- ₹0.00</span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="label">Taxable Amount</span>
                            <span class="value">₹<?php echo number_format(floatval($invoice['grand_total'] ?? 36436.03) * 0.85, 2); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="label">CGST</span>
                            <span class="value">₹<?php echo number_format(floatval($invoice['grand_total'] ?? 36436.03) * 0.075, 2); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="label">SGST</span>
                            <span class="value">₹<?php echo number_format(floatval($invoice['grand_total'] ?? 36436.03) * 0.075, 2); ?></span>
                        </div>
                        
                        <div class="summary-row total">
                            <span class="label">Total</span>
                            <span class="value">₹<?php echo number_format(floatval($invoice['grand_total'] ?? 36436.03), 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Compact Amount in Words -->
                    <div class="amount-words">
                        <div class="label">Invoice Total (in words)</div>
                        <div class="value"><?php echo numberToWords(floatval($invoice['grand_total'] ?? 36436.03)); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Compact Terms and Conditions -->
            <div class="terms-section">
                <h3>Terms and Conditions</h3>
                <ul class="terms-list">
                    <li>Please pay within 15 days from the date of invoice, overdue interest @ 14% will be charged on delayed payments.</li>
                    <li>Please quote invoice number when remitting funds.</li>
                    <li>All disputes are subject to local jurisdiction only.</li>
                </ul>
            </div>
            
            <!-- Compact Signature Section -->
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">
                        <?php echo trim(safeHtml($invoice['FirstName'], '') . ' ' . safeHtml($invoice['LastName'], '')) ?: 'demo'; ?>
                    </div>
                    <div class="signature-sublabel">Customer Signature</div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line" style="border: 2px dashed #9ca3af; border-bottom: none; border-radius: 50%; width: 50px; height: 40px; margin: 0 auto;"></div>
                    <div class="signature-label">Official Seal</div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">For <?php echo safeHtml($invoice['FirmName'], 'Mahalaxmi HM'); ?></div>
                    <div class="signature-sublabel">Authorized Signatory</div>
                </div>
            </div>
        </div>
        
        <!-- Compact Footer -->
        <div class="invoice-footer">
            <div class="footer-contact">
                <span>For any enquiries, email us on <?php echo safeHtml($invoice['FirmEmail'], 'info@mahalaxmihm.com'); ?></span>
                <span>or call us on <?php echo safeHtml($invoice['FirmPhoneNumber'], '+91 98103 59334'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons print-hidden">
        <button onclick="window.print()" class="btn btn-primary">
            🖨️ Print Invoice
        </button>
        <button onclick="window.history.back()" class="btn btn-secondary">
            ← Back
        </button>
        <a href="download_invoice.php?id=<?php echo $invoice_id ?? ''; ?>&invoice_no=<?php echo urlencode($invoice_no ?? ''); ?>" class="btn btn-outline">
            📥 Download PDF
        </a>
    </div>
    
    <script>
        // Print optimization
        window.addEventListener('beforeprint', function() {
            document.body.style.fontSize = '9px';
        });
        
        window.addEventListener('afterprint', function() {
            document.body.style.fontSize = '11px';
        });
        
        // Auto-focus print dialog on Ctrl+P
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
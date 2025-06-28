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
    die("Connection failed: " . $conn->connect_error);
}

// Handle invoice retrieval logic
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
    echo "<div class='bg-red-100 text-red-700 p-6 rounded-lg text-center m-8'>
            <h2 class='text-xl font-bold mb-2'>Invoice Information Missing</h2>
            <p>Please provide a valid invoice number or ID.</p>
            <a href='dashboard.php' class='inline-block mt-4 bg-emerald-700 text-white px-4 py-2 rounded'>Return to Dashboard</a>
          </div>";
    exit();
}

$stmt->execute();
$invoice_result = $stmt->get_result();

if (!isset($invoice_id) && $invoice_result->num_rows > 0) {
    $temp = $invoice_result->fetch_assoc();
    $invoice_id = $temp['id'];
    $invoice_no = $temp['invoice_no'];
    $invoice_result->data_seek(0);
}

if ($invoice_result->num_rows == 0) {
    echo "<div class='bg-red-100 text-red-700 p-6 rounded-lg text-center m-8'>
            <h2 class='text-xl font-bold mb-2'>Invoice Not Found</h2>
            <p>The requested invoice was not found or you don't have permission to view it.</p>
            <a href='dashboard.php' class='inline-block mt-4 bg-emerald-700 text-white px-4 py-2 rounded'>Return to Dashboard</a>
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

// Helper functions
function safeHtml($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

function formatIndianRupee($num) {
    return '₹' . number_format(floatval($num ?? 0), 2);
}

function formatDate($date) {
    return $date ? date("d/m/Y", strtotime($date)) : '';
}

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
    
    if ($rupees >= 10000000) {
        $crores = floor($rupees / 10000000);
        $result .= convertHundreds($crores) . ' Crore ';
        $rupees %= 10000000;
    }
    if ($rupees >= 100000) {
        $lakhs = floor($rupees / 100000);
        $result .= convertHundreds($lakhs) . ' Lakh ';
        $rupees %= 100000;
    }
    if ($rupees >= 1000) {
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

// Check for old gold exchange
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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .invoice-container { box-shadow: none !important; margin: 0 !important; }
            .page-break { page-break-inside: avoid; }
        }
        .invoice-header { border-bottom: 3px solid #047857; }
        .logo-placeholder { background: linear-gradient(135deg, #047857 0%, #065f46 100%); }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="invoice-container max-w-4xl mx-auto bg-white shadow-lg my-4">
        
        <!-- Professional Header -->
        <div class="invoice-header px-6 py-4">
            <div class="flex items-center justify-between">
                <!-- Left Logo -->
                <div class="flex-shrink-0">
                    <?php if (!empty($invoice['FirmLogo'])): ?>
                        <img src="<?php echo safeHtml($invoice['FirmLogo']); ?>" alt="Logo" class="w-16 h-16 object-contain rounded-lg border">
                    <?php else: ?>
                        <div class="logo-placeholder w-16 h-16 text-white flex items-center justify-center text-xl font-bold rounded-lg">
                            <?php echo strtoupper(substr($invoice['FirmName'] ?? 'JW', 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Center - Firm Details -->
                <div class="text-center flex-1 mx-4">
                    <h1 class="text-2xl font-bold text-gray-800 mb-1"><?php echo safeHtml($invoice['FirmName'], 'MAHALAXMI HM'); ?></h1>
                    <p class="text-sm text-gray-600 mb-1">SHOP NO 1-2, LALGOLA BAZAR NEAR LAXMI NARAYAN MANDIR</p>
                    <p class="text-sm text-gray-600 mb-2">LALGOLA, MURSHIDABAD, WEST BENGAL - 742148</p>
                    <div class="flex items-center justify-center space-x-4 text-xs text-gray-500">
                        <span>📞 <?php echo safeHtml($invoice['FirmPhoneNumber'], '9810359334'); ?></span>
                        <span>✉️ <?php echo safeHtml($invoice['FirmEmail'], 'MAHALAXMIHC@GMAIL.COM'); ?></span>
                    </div>
                </div>

                <!-- Right - Certifications & Details -->
                <div class="flex-shrink-0 text-right">
                    <div class="text-xs text-gray-600 mb-2">
                        <div>GSTIN: <span class="font-semibold"><?php echo safeHtml($invoice['FirmGSTNumber'], 'N/A'); ?></span></div>
                        <div>PAN: <span class="font-semibold"><?php echo safeHtml($invoice['FirmPANNumber'], 'N/A'); ?></span></div>
                    </div>
                    <img src="uploads/bis.png" alt="BIS Certified" class="w-14 h-10 mx-auto opacity-80">
                </div>
            </div>
        </div>

        <!-- Invoice Details Row -->
        <div class="px-6 py-4 bg-gray-50 border-b">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-emerald-700">INVOICE</h2>
                    <p class="text-sm text-gray-600">Invoice #: <span class="font-semibold"><?php echo safeHtml($invoice['invoice_no']); ?></span></p>
                </div>
                <div class="text-right text-sm">
                    <p class="text-gray-600">Date: <span class="font-semibold"><?php echo formatDate($invoice['sale_date']); ?></span></p>
                    <p class="text-gray-600">Due Date: <span class="font-semibold"><?php echo formatDate(date('Y-m-d', strtotime(($invoice['sale_date'] ?? date('Y-m-d')) . ' + 7 days'))); ?></span></p>
                </div>
            </div>
        </div>

        <!-- Customer Details -->
        <div class="px-6 py-4 border-b">
            <div class="bg-emerald-50 rounded-lg p-4">
                <h3 class="font-semibold text-emerald-700 mb-2">Bill To:</h3>
                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <p class="font-bold text-lg text-gray-800"><?php echo trim(safeHtml($invoice['FirstName'], '') . ' ' . safeHtml($invoice['LastName'], '')); ?></p>
                        <p class="text-sm text-gray-600"><?php echo safeHtml($invoice['Address']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo safeHtml($invoice['City']); ?>, <?php echo safeHtml($invoice['State']); ?> - <?php echo safeHtml($invoice['PostalCode']); ?></p>
                        <p class="text-sm text-gray-600">Phone: <?php echo safeHtml($invoice['PhoneNumber']); ?></p>
                    </div>
                    <div class="text-sm text-gray-600">
                        <?php if (!empty($invoice['GSTNumber'])): ?>
                            <p>GSTIN: <span class="font-semibold"><?php echo safeHtml($invoice['GSTNumber']); ?></span></p>
                        <?php endif; ?>
                        <?php if (!empty($invoice['PANNumber'])): ?>
                            <p>PAN: <span class="font-semibold"><?php echo safeHtml($invoice['PANNumber']); ?></span></p>
                        <?php endif; ?>
                        <?php if (!empty($invoice['Email'])): ?>
                            <p>Email: <?php echo safeHtml($invoice['Email']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="px-6 py-4">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-emerald-600 text-white">
                        <th class="border border-emerald-600 px-2 py-2 text-left">#</th>
                        <th class="border border-emerald-600 px-2 py-2 text-left">Item Description</th>
                        <th class="border border-emerald-600 px-2 py-2 text-center">HSN</th>
                        <th class="border border-emerald-600 px-2 py-2 text-center">Qty</th>
                        <th class="border border-emerald-600 px-2 py-2 text-right">Gross Wt</th>
                        <th class="border border-emerald-600 px-2 py-2 text-right">Net Wt</th>
                        <th class="border border-emerald-600 px-2 py-2 text-center">Purity</th>
                        <th class="border border-emerald-600 px-2 py-2 text-right">Rate/g</th>
                        <th class="border border-emerald-600 px-2 py-2 text-right">Making</th>
                        <th class="border border-emerald-600 px-2 py-2 text-right">Stone</th>
                        <th class="border border-emerald-600 px-2 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 1;
                    $total_items = 0;
                    $total_gross_weight = 0;
                    $total_net_weight = 0;
                    foreach ($items as $item): 
                        $stonePrice = floatval($item['stone_price'] ?? 0);
                        $total_items += intval($item['quantity'] ?? 1);
                        $total_gross_weight += floatval($item['gross_weight'] ?? 0);
                        $total_net_weight += floatval($item['net_weight'] ?? 0);
                    ?>
                    <tr class="<?php echo $count % 2 == 0 ? 'bg-gray-50' : 'bg-white'; ?>">
                        <td class="border border-gray-300 px-2 py-2 text-center"><?php echo $count; ?></td>
                        <td class="border border-gray-300 px-2 py-2">
                            <div class="font-medium"><?php echo safeHtml($item['product_name'] ?? 'N/A'); ?></div>
                            <div class="text-xs text-gray-500">
                                ID: <?php echo safeHtml($item['product_id'] ?? '—'); ?> | 
                                HUID: <?php echo safeHtml($item['huid_code'] ?? '—'); ?>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-2 py-2 text-center">7113</td>
                        <td class="border border-gray-300 px-2 py-2 text-center"><?php echo safeHtml($item['quantity'] ?? 1); ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-right"><?php echo number_format(floatval($item['gross_weight'] ?? 0), 3); ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-right"><?php echo number_format(floatval($item['net_weight'] ?? 0), 3); ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-center"><?php echo safeHtml($item['purity'] ?? '—'); ?>K</td>
                        <td class="border border-gray-300 px-2 py-2 text-right">₹<?php echo number_format(floatval($item['purity_rate'] ?? 0), 2); ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-right">₹<?php echo number_format(floatval($item['making_charges'] ?? 0), 2); ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-right">
                            <?php echo $stonePrice > 0 ? '₹' . number_format($stonePrice, 2) : '—'; ?>
                        </td>
                        <td class="border border-gray-300 px-2 py-2 text-right font-semibold">₹<?php echo number_format(floatval($item['total'] ?? 0), 2); ?></td>
                    </tr>
                    <?php $count++; endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-semibold">
                        <td colspan="3" class="border border-gray-300 px-2 py-2 text-right">TOTALS:</td>
                        <td class="border border-gray-300 px-2 py-2 text-center"><?php echo $total_items; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-right"><?php echo number_format($total_gross_weight, 3); ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-right"><?php echo number_format($total_net_weight, 3); ?></td>
                        <td colspan="5" class="border border-gray-300 px-2 py-2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Old Gold Exchange Table (if applicable) -->
        <?php if ($hasOldGoldExchange && !empty($oldGoldItems)): ?>
        <div class="px-6 py-4">
            <h3 class="font-semibold text-amber-700 mb-2">Old Gold Exchange (URD Items)</h3>
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-amber-100">
                        <th class="border border-amber-300 px-2 py-2 text-left">Item</th>
                        <th class="border border-amber-300 px-2 py-2 text-right">Gross Wt</th>
                        <th class="border border-amber-300 px-2 py-2 text-right">Less Wt</th>
                        <th class="border border-amber-300 px-2 py-2 text-right">Net Wt</th>
                        <th class="border border-amber-300 px-2 py-2 text-center">Purity</th>
                        <th class="border border-amber-300 px-2 py-2 text-right">Rate</th>
                        <th class="border border-amber-300 px-2 py-2 text-right">Fine Wt</th>
                        <th class="border border-amber-300 px-2 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oldGoldItems as $urd): ?>
                    <tr class="bg-amber-50">
                        <td class="border border-amber-300 px-2 py-2"><?php echo safeHtml($urd['item_name']); ?></td>
                        <td class="border border-amber-300 px-2 py-2 text-right"><?php echo safeHtml($urd['gross_weight']); ?></td>
                        <td class="border border-amber-300 px-2 py-2 text-right"><?php echo safeHtml($urd['less_weight']); ?></td>
                        <td class="border border-amber-300 px-2 py-2 text-right"><?php echo safeHtml($urd['net_weight']); ?></td>
                        <td class="border border-amber-300 px-2 py-2 text-center"><?php echo safeHtml($urd['purity']); ?>K</td>
                        <td class="border border-amber-300 px-2 py-2 text-right">₹<?php echo number_format(floatval($urd['rate']), 2); ?></td>
                        <td class="border border-amber-300 px-2 py-2 text-right"><?php echo safeHtml($urd['fine_weight']); ?></td>
                        <td class="border border-amber-300 px-2 py-2 text-right font-semibold">₹<?php echo number_format(floatval($urd['total_amount']), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Bottom Section: Bank Details and Summary -->
        <div class="px-6 py-4 border-t">
            <div class="grid grid-cols-2 gap-6">
                
                <!-- Compact Bank Details -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-emerald-700 mb-3">Bank Details & Payment</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Account Name:</span>
                            <span class="font-medium"><?php echo safeHtml($invoice['FirmName']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Account No:</span>
                            <span class="font-medium"><?php echo safeHtml($invoice['BankAccountNumber']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">IFSC:</span>
                            <span class="font-medium"><?php echo safeHtml($invoice['IFSCCode']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Bank:</span>
                            <span class="font-medium"><?php echo safeHtml($invoice['BankName']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">UPI ID:</span>
                            <span class="font-medium">mahalaxmihm@upi</span>
                        </div>
                    </div>
                    
                    <!-- QR Code Placeholder -->
                    <div class="mt-3 text-center">
                        <div class="text-xs font-medium text-gray-600 mb-1">Scan to Pay</div>
                        <div class="w-16 h-16 border-2 border-dashed border-gray-400 rounded mx-auto flex items-center justify-center text-gray-400 text-xs">
                            QR
                        </div>
                    </div>
                </div>

                <!-- Compact Summary -->
                <div class="bg-white rounded-lg border-2 border-emerald-200 p-4">
                    <h3 class="font-semibold text-emerald-700 mb-3">Invoice Summary</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span class="font-medium"><?php echo formatIndianRupee($invoice['subtotal']); ?></span>
                        </div>
                        
                        <?php if (floatval($invoice['discount']) > 0 || floatval($invoice['manual_discount']) > 0 || floatval($invoice['coupon_discount']) > 0 || floatval($invoice['loyalty_discount']) > 0): ?>
                        <div class="flex justify-between text-red-600">
                            <span>Total Discount:</span>
                            <span class="font-medium">-<?php echo formatIndianRupee(floatval($invoice['discount']) + floatval($invoice['manual_discount']) + floatval($invoice['coupon_discount']) + floatval($invoice['loyalty_discount'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($hasOldGoldExchange): ?>
                        <div class="flex justify-between text-amber-600">
                            <span>Old Gold Exchange:</span>
                            <span class="font-medium">-<?php echo formatIndianRupee($invoice['urd_amount']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        $is_gst_applicable = isset($invoice['is_gst_applicable']) ? intval($invoice['is_gst_applicable']) : 0;
                        ?>
                        <?php if ($is_gst_applicable == 1): ?>
                        <?php 
                        // Calculate CGST/SGST if not present
                        $cgst = isset($invoice['cgst']) ? floatval($invoice['cgst']) : 0;
                        $sgst = isset($invoice['sgst']) ? floatval($invoice['sgst']) : 0;
                        $gst_total = $cgst + $sgst;
                        if ($gst_total == 0 && isset($invoice['gst_amount'])) {
                            $gst_total = floatval($invoice['gst_amount']);
                            $cgst = $sgst = $gst_total / 2;
                        }
                        $cgst_rate = isset($invoice['cgst_rate']) ? $invoice['cgst_rate'] : '1.5';
                        $sgst_rate = isset($invoice['sgst_rate']) ? $invoice['sgst_rate'] : '1.5';
                        ?>
                        <div class="border-t pt-2">
                            <div class="flex justify-between text-xs">
                                <span>CGST (<?php echo safeHtml($cgst_rate); ?>%):</span>
                                <span><?php echo formatIndianRupee($cgst); ?></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span>SGST (<?php echo safeHtml($sgst_rate); ?>%):</span>
                                <span><?php echo formatIndianRupee($sgst); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="border-t-2 border-emerald-200 pt-2 mt-2">
                            <div class="flex justify-between text-lg font-bold text-emerald-700">
                                <span>Grand Total:</span>
                                <span><?php echo formatIndianRupee($invoice['grand_total']); ?></span>
                            </div>
                        </div>
                        
                        <?php if (floatval($invoice['paid_amount']) > 0): ?>
                        <div class="flex justify-between text-green-600">
                            <span>Paid Amount:</span>
                            <span class="font-medium"><?php echo formatIndianRupee($invoice['paid_amount']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (floatval($invoice['balance_amount']) > 0): ?>
                        <div class="flex justify-between text-red-600 font-medium">
                            <span>Balance Due:</span>
                            <span><?php echo formatIndianRupee($invoice['balance_amount']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Amount in Words -->
        <div class="px-6 py-3 bg-gray-50 border-t">
            <div class="text-sm">
                <span class="font-semibold text-gray-700">Amount in Words: </span>
                <span class="italic"><?php echo numberToWords($invoice['grand_total']); ?></span>
            </div>
        </div>

        <!-- Terms & Conditions -->
        <div class="px-6 py-4 border-t">
            <div class="grid grid-cols-2 gap-6 text-xs">
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Terms & Conditions:</h4>
                    <ul class="space-y-1 text-gray-600">
                        <li>• All payments must be made within 7 days of invoice date</li>
                        <li>• Goods once sold will not be taken back or exchanged</li>
                        <li>• All disputes are subject to local jurisdiction</li>
                        <li>• Interest @ 24% per annum will be charged on overdue amounts</li>
                        <li>• Hallmark certification as per BIS standards</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Important Notes:</h4>
                    <ul class="space-y-1 text-gray-600">
                        <li>• Please verify all details before making payment</li>
                        <li>• Keep this invoice for warranty claims</li>
                        <li>• Gold rate: As per current market rate</li>
                        <li>• Making charges are non-refundable</li>
                        <li>• Subject to price verification</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="px-6 py-4 border-t">
            <div class="flex justify-between items-end">
                <div class="text-center">
                    <div class="border-t border-gray-400 w-32 mx-auto mb-1"></div>
                    <p class="text-xs text-gray-600">Customer Signature</p>
                </div>
                <div class="text-center">
                    <div class="mb-4">
                        <img src="uploads/signature.png" alt="Signature" class="w-24 h-12 mx-auto opacity-70" style="display: none;">
                    </div>
                    <div class="border-t border-gray-400 w-32 mx-auto mb-1"></div>
                    <p class="text-xs text-gray-600">Authorized Signatory</p>
                    <p class="text-xs font-medium text-gray-700"><?php echo safeHtml($invoice['FirmName']); ?></p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-emerald-700 text-white text-center py-3 text-xs">
            <p>Thank you for your business! | Generated on <?php echo date('d/m/Y H:i:s'); ?> | For queries: <?php echo safeHtml($invoice['FirmPhoneNumber']); ?></p>
        </div>
    </div>

    <!-- Print & Action Buttons -->
    <div class="no-print fixed bottom-4 right-4 space-x-2">
        <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg shadow-lg transition-colors">
            🖨️ Print Invoice
        </button>
        <button onclick="window.history.back()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg shadow-lg transition-colors">
            ← Back
        </button>
    </div>

    <!-- Print Optimization Script -->
    <script>
        // Optimize for printing
        window.addEventListener('beforeprint', function() {
            document.body.style.backgroundColor = 'white';
        });
        
        // Add smooth transitions
        document.addEventListener('DOMContentLoaded', function() {
            const invoiceContainer = document.querySelector('.invoice-container');
            invoiceContainer.style.opacity = '0';
            invoiceContainer.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                invoiceContainer.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                invoiceContainer.style.opacity = '1';
                invoiceContainer.style.transform = 'translateY(0)';
            }, 100);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            if (e.key === 'Escape') {
                window.history.back();
            }
        });
    </script>
</body>
</html>
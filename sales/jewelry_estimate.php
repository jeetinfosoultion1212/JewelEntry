<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
   header("Location: login.php");
   exit();
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];
include('phpqrcode/qrlib.php');

// Database connection
include('config/db_pdo.php');

// Get invoice number from URL parameter
if (isset($_GET['invoice_no'])) {
    $invoice_no = $_GET['invoice_no'];
} elseif (isset($_GET['id'])) {
    // Fetch invoice_no using id
    $stmt = $pdo->prepare("SELECT invoice_no FROM jewellery_sales WHERE id = :id AND firm_id = :firm_id");
    $stmt->execute([
        'id' => $_GET['id'],
        'firm_id' => $firm_id
    ]);
    $row = $stmt->fetch();
    if ($row && !empty($row['invoice_no'])) {
        $invoice_no = $row['invoice_no'];
    } else {
        die("No sale found for the given ID.");
    }
} else {
    die("Invoice number or ID is required.");
}

// Fetch sale details
$stmt = $pdo->prepare("
    SELECT 
        js.*,
        c.FirstName, c.LastName, c.Email, c.PhoneNumber, c.Address, c.City, c.State, c.PostalCode, 
        c.PANNumber, c.GSTNumber, c.IsGSTRegistered
    FROM jewellery_sales js
    JOIN customer c ON js.customer_id = c.id
    WHERE js.invoice_no = :invoice_no
    AND js.firm_id = :firm_id
");

$stmt->execute([
    'invoice_no' => $invoice_no,
    'firm_id' => $firm_id
]);

$saleDetails = $stmt->fetch();

if (!$saleDetails) {
    die("No sale found for the given invoice number.");
}

// Fetch sale items
$stmt = $pdo->prepare("
    SELECT 
        jsi.*,
        ji.jewelry_type, ji.material_type, ji.image_path
    FROM Jewellery_sales_items jsi
    LEFT JOIN jewellery_items ji ON jsi.product_id = ji.id
    WHERE jsi.sale_id = :sale_id
    ORDER BY jsi.id
");

$stmt->execute(['sale_id' => $saleDetails['id']]);
$saleItems = $stmt->fetchAll();

// Fetch URD items for this sale
$stmt = $pdo->prepare("
    SELECT * FROM urd_items 
    WHERE sale_id = :sale_id 
    AND firm_id = :firm_id
    ORDER BY id
");

$stmt->execute([
    'sale_id' => $saleDetails['id'],
    'firm_id' => $firm_id
]);
$urdItems = $stmt->fetchAll();

// Fetch firm details
$stmt = $pdo->prepare("
    SELECT 
        FirmName, Email, Address, City, State, PostalCode,
        PANNumber, GSTNumber, BISRegistrationNumber,
        BankName, BankAccountNumber, IFSCCode, Logo
    FROM Firm 
    WHERE id = :firm_id
");
$stmt->execute(['firm_id' => $firm_id]);
$firmDetails = $stmt->fetch();

if (!$firmDetails) {
    die("Firm details not found.");
}

// Set firm logo path
$firmLogo = !empty($firmDetails['Logo']) ? $firmDetails['Logo'] : 'uploads/logo.png';

// Combine address components
$mainAddress = trim($firmDetails['Address']);
$cityStatePin = trim($firmDetails['City'] . ', ' . $firmDetails['State'] . ' - ' . $firmDetails['PostalCode']);

// Fetch payment methods from jewellery_payments for this sale
$paymentMethods = [];
$stmt = $pdo->prepare("SELECT payment_type, amount FROM jewellery_payments WHERE sale_id = :sale_id AND reference_type = 'sale' AND Firm_id = :firm_id");
$stmt->execute(['sale_id' => $saleDetails['id'], 'firm_id' => $firm_id]);
while ($row = $stmt->fetch()) {
    $paymentMethods[] = $row;
}

// Calculate totals
$total_metal_amount = $saleDetails['total_metal_amount'];
$total_stone_amount = $saleDetails['total_stone_amount'];
$total_making_charges = $saleDetails['total_making_charges'];
$total_other_charges = $saleDetails['total_other_charges'];
$total_discount = $saleDetails['discount'] + $saleDetails['coupon_discount'] + $saleDetails['loyalty_discount'] + $saleDetails['manual_discount'];
$urd_amount = $saleDetails['urd_amount'];
$subtotal = $saleDetails['subtotal'];
$grand_total = $saleDetails['grand_total'];
$paid_amount = $saleDetails['total_paid_amount'];
$due_amount = $saleDetails['due_amount'];

// Format customer name
$customerName = trim($saleDetails['FirstName'] . ' ' . $saleDetails['LastName']);
$customerAddress = $saleDetails['Address'] ?: 'N/A';
$customerCity = $saleDetails['City'] ?: 'N/A';
$customerState = $saleDetails['State'] ?: 'N/A';
$customerPAN = $saleDetails['PANNumber'] ?: 'N/A';
$customerGST = $saleDetails['GSTNumber'] ?: 'N/A';
$customerPhone = $saleDetails['PhoneNumber'] ?: 'N/A';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jewelry Estimate / Quotation</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f8f6f0 0%, #ede8dc 100%); font-size: 12px; margin: 0; padding: 20px; }
        .invoice-container { width: 210mm; min-height: auto; background: white; box-shadow: 0 5px 20px rgba(0,0,0,0.1); padding: 4mm; border: 5px solid #d4af37; border-radius: 12px; margin: 0 auto; }
        .invoice-container table { border-collapse: collapse; }
        .invoice-container th, .invoice-container td { border: 1px solid #e5e7eb; }
        .invoice-container thead th { border-bottom: 2px solid #4f46e5; }
        .elegant-header { background: linear-gradient(135deg, #fffbf0 0%, #f7f3e7 50%, #f0ead6 100%); border-bottom: 2px solid #d4af37; position: relative; overflow: hidden; margin: -4mm -4mm 2mm -4mm; padding: 16mm 6mm 10mm 6mm; }
        .elegant-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, #d4af37, #ffd700, #d4af37); }
        .company-name { font-family: 'Playfair Display', serif; color: #8b4513; letter-spacing: 1px; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .golden-accent { color: #d4af37; }
        .info-card { background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%); border: 1px solid #e0e0e0; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .customer-card { background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%); border-left: 3px solid #4f46e5; }
        .invoice-card { background: linear-gradient(135deg, #fffbf0 0%, #fef7e7 100%); border-left: 3px solid #d4af37; }
        .table-header { background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); color: white; }
        .table-row:nth-child(even) { background: linear-gradient(135deg, #fafbff 0%, #f5f6ff 100%); }
        .amount-section { background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%); border: 1px solid #e0e4ff; border-radius: 6px; }
        .total-section { background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); color: white; border-radius: 6px; }
        .decorative-line { height: 1px; background: linear-gradient(90deg, transparent, #d4af37, transparent); margin: 5px 0; }
        .logo-frame { background: white; border: 2px solid #d4af37; border-radius: 50%; padding: 4px; box-shadow: 0 2px 8px rgba(212, 175, 55, 0.2); }
        .signature-section { border-top: 1px solid #e5e7eb; background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%); }
        .footer-elegant { background: linear-gradient(135deg, #1f2937 0%, #111827 100%); color: white; position: relative; margin: 2mm -4mm -4mm -4mm; padding: 3mm 2mm; }
        .footer-elegant::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, #d4af37, #ffd700, #d4af37); }
        .qr-frame { background: white; border: 2px solid #d4af37; border-radius: 4px; padding: 2px; }
        .table-fixed-height { height: 340px; display: flex; flex-direction: column; }
        .table-fixed-height table { width: 100%; height: 100%; border-collapse: separate; border-spacing: 0; display: flex; flex-direction: column; }
        .table-fixed-height thead, .table-fixed-height tfoot { flex: 0 0 auto; display: table; width: 100%; table-layout: fixed; }
        .table-fixed-height tbody { flex: 1 1 auto; display: block; overflow-y: auto; width: 100%; }
        .table-fixed-height tbody tr { display: table; width: 100%; table-layout: fixed; }
        .table-section-border { border: none; border-radius: 0; overflow: hidden; margin-bottom: 1rem; background: #fff; width: 100%; padding: 0; }
        .table-section-border table { border: 2px solid #bdbdbd; border-radius: 8px; border-collapse: separate; border-spacing: 0; overflow: hidden; }
        @page { size: A4; margin: 5mm; }
        @media print { body { background: white !important; margin: 0; padding: 0 !important; font-size: 11px; } .invoice-container { box-shadow: none !important; margin: 0 !important; width: 100% !important; max-width: none !important; min-height: auto !important; padding: 2mm !important; border: 1px solid #d4af37 !important; border-radius: 0 !important; page-break-inside: avoid; } .no-print { display: none !important; } .elegant-header { margin: -2mm -2mm 2mm -2mm !important; padding: 3mm !important; } .footer-elegant { margin: 2mm -2mm -2mm -2mm !important; padding: 2mm 1mm !important; } .table-fixed-height { height: 290px !important; } .table-fixed-height tbody { overflow-y: visible !important; } * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } .table-section-border, .amount-section, .signature-section { page-break-inside: avoid; } } .terms-section { page-break-inside: avoid; } @media print { .terms-section { font-size: 9px !important; } }
    </style>
</head>
<body class="p-2">
    <div id="invoice-content" class="invoice-container mx-auto relative">
        <!-- Header Section -->
        <div class="elegant-header">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="logo-frame">
                    <img src="<?= $firmLogo ?>" alt="Firm Logo" class="w-12 h-12 rounded-full object-contain" />
                </div>
                <!-- Company Info -->
                <div class="flex-1 text-center mx-4">
                    <h1 class="company-name text-2xl font-bold mb-1"><?= htmlspecialchars(strtoupper($firmDetails['FirmName'])) ?></h1>
                    <p class="text-gray-700 text-xs mb-0"><?= htmlspecialchars($mainAddress) ?></p>
                    <p class="text-gray-700 text-xs mt-0"><?= htmlspecialchars($cityStatePin) ?></p>
                </div>
                <!-- BIS Logo -->
                <div class="logo-frame">
                    <img src="uploads/bis.png" alt="BIS Logo" class="w-12 h-12 rounded-full object-contain" />
                </div>
            </div>
        </div>
        <!-- Invoice Title -->
        <div class="text-center py-1 relative">
            <div class="decorative-line"></div>
            <h2 class="text-base font-bold text-gray-800 mb-1">ESTIMATE / QUOTATION</h2>
            <div class="decorative-line"></div>
        </div>
        <!-- Customer & Invoice Info -->
        <div class="grid grid-cols-2 gap-3 mb-1">
            <!-- Customer Details -->
            <div class="info-card customer-card p-3">
                <h3 class="text-xs font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-1">
                    <span class="text-indigo-600">👤</span> Customer Details
                </h3>
                <div class="space-y-1">
                    <div>
                        <p class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($customerName) ?></p>
                        <p class="text-xs text-gray-600"><?= htmlspecialchars($customerAddress) ?>, <?= htmlspecialchars($customerCity) ?></p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="font-medium text-gray-700">PAN No:</p>
                            <p class="text-gray-600"><?= htmlspecialchars($customerPAN) ?></p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-700">Phone:</p>
                            <p class="text-gray-600"><?= htmlspecialchars($customerPhone) ?></p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-700">GST No:</p>
                            <p class="text-gray-600"><?= htmlspecialchars($customerGST) ?></p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-700">State:</p>
                            <p class="text-gray-600"><?= htmlspecialchars($customerState) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Invoice Details -->
            <div class="info-card invoice-card p-3">
                <h3 class="text-xs font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-1">
                    <span class="golden-accent">📄</span> Estimate Details
                </h3>
                <div class="space-y-1">
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="font-medium text-gray-700">Estimate No:</p>
                            <p class="font-semibold text-sm golden-accent">#<?= htmlspecialchars($invoice_no) ?></p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-700">Date:</p>
                            <p class="text-gray-800 font-medium"><?= date('d-m-Y', strtotime($saleDetails['sale_date'])) ?></p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-700">Payment Status:</p>
                            <p class="text-gray-800 font-semibold">
                                <?= htmlspecialchars($saleDetails['payment_status']) ?>
                            </p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-700">Payment Method:</p>
                            <p class="text-gray-800">
                                <?php if (!empty($paymentMethods)): ?>
                                    <?php foreach ($paymentMethods as $pm): ?>
                                        <span><?= htmlspecialchars(ucwords($pm['payment_type'])) ?> (₹<?= number_format($pm['amount'], 2) ?>)</span><br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-700">S.A.C:</p>
                            <p class="text-gray-800">998346</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Items Table -->
        <div class="table-section-border" style="position:relative;">
            <!-- BIS Watermark -->
            <img src="uploads/bis.png" alt="BIS Watermark" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);opacity:0.08;width:260px;height:auto;z-index:0;pointer-events:none;" />
            <div class="table-fixed-height mb-3" style="position:relative;z-index:1;">
                <table class="w-full text-xs h-full">
                    <thead class="table-header">
                        <tr>
                            <th class="px-2 py-2 text-left font-semibold">S.No</th>
                            <th class="px-2 py-2 text-left font-semibold">Item </th>
                            <th class="px-1 py-2 text-center font-semibold">Purity</th>
                            <th class="px-1 py-2 text-center font-semibold">Net Wt</th>
                            <th class="px-1 py-2 text-center font-semibold">Rate/Gm</th>
                            <th class="px-2 py-2 text-right font-semibold">Metal Amt</th>
                            <th class="px-2 py-2 text-right font-semibold">Making</th>
                            <th class="px-1 py-2 text-right font-semibold">Stone</th>
                            <th class="px-2 py-2 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $index = 1;
                        $total_net_weight = 0;
                        $total_metal_amt = 0;
                        $total_making = 0;
                        $total_stone = 0;
                        $total_total = 0;
                        foreach ($saleItems as $item):
                            $total_net_weight += $item['net_weight'];
                            $total_metal_amt += $item['metal_amount'];
                            $total_making += $item['making_charges'];
                            $total_stone += $item['stone_price'];
                            $total_total += $item['total'];
                            
                            // Purity logic
                            $purity = $item['purity'];
                            if ($purity >= 91.6 && $purity <= 92.5) {
                                $purity_display = "22K916";
                            } else {
                                $purity_display = $purity . "%";
                            }
                        ?>
                        <tr class="table-row border-b border-gray-100">
                            <td class="px-2 py-2 font-medium"><?= $index++ ?></td>
                            <td class="px-2 py-2">
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($item['jewelry_type'] ?? '') ?></div>
                                <?php if (!empty($item['huid_code'])): ?>
                                    <div class="text-blue-700 text-xs font-semibold mt-1">HUID: <?= htmlspecialchars($item['huid_code']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-1 py-2 text-center font-medium"><?= htmlspecialchars($purity_display) ?></td>
                            <td class="px-1 py-2 text-center font-medium"><?= number_format($item['net_weight'], 3) ?></td>
                            <td class="px-1 py-2 text-center"><?= number_format($item['purity_rate'], 2) ?></td>
                            <td class="px-2 py-2 text-right font-medium"><?= number_format($item['metal_amount'], 2) ?></td>
                            <td class="px-2 py-2 text-right"><?= number_format($item['making_charges'], 2) ?></td>
                            <td class="px-1 py-2 text-right"><?= number_format($item['stone_price'], 2) ?></td>
                            <td class="px-2 py-2 text-right font-bold"><?= number_format($item['total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php foreach ($urdItems as $urdItem):
                            $total_net_weight += $urdItem['net_weight'];
                            $total_metal_amt += $urdItem['total_amount'];
                            $total_total += $urdItem['total_amount'];
                        ?>
                        <tr class="table-row border-b border-gray-100">
                            <td class="px-2 py-2 font-medium"><?= $index++ ?></td>
                            <td class="px-2 py-2">
                                <div class="font-medium text-orange-700"><?= htmlspecialchars($urdItem['item_name']) ?></div>
                                <div class="text-xs text-orange-500">(Used Gold Exchange)</div>
                            </td>
                            <td class="px-1 py-2 text-center font-medium">N/A</td>
                            <td class="px-1 py-2 text-center font-medium"><?= number_format($urdItem['net_weight'], 3) ?></td>
                            <td class="px-1 py-2 text-center">-</td>
                            <td class="px-2 py-2 text-right font-medium"><?= number_format($urdItem['total_amount'], 2) ?></td>
                            <td class="px-2 py-2 text-right">0.00</td>
                            <td class="px-1 py-2 text-right">0.00</td>
                            <td class="px-2 py-2 text-right font-bold"><?= number_format($urdItem['total_amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-indigo-50 font-bold">
                            <td class="px-2 py-2 text-right" colspan="2">Total</td>
                            <td class="px-1 py-2 text-center">-</td>
                            <td class="px-1 py-2 text-center"><?= number_format($total_net_weight, 3) ?></td>
                            <td class="px-1 py-2 text-center">-</td>
                            <td class="px-2 py-2 text-right"><?= number_format($total_metal_amt, 2) ?></td>
                            <td class="px-2 py-2 text-right"><?= number_format($total_making, 2) ?></td>
                            <td class="px-1 py-2 text-right"><?= number_format($total_stone, 2) ?></td>
                            <td class="px-2 py-2 text-right"><?= number_format($total_total, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <!-- Summary Section -->
        <div class="grid grid-cols-3 gap-3 mb-1">
            <!-- Item Summary -->
            <div class="amount-section p-1">
                <h3 class="text-xs font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-1">
                    <span class="text-indigo-600">📊</span> Item Summary
                </h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Items:</span>
                        <span class="font-semibold"><?= count($saleItems) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Weight:</span>
                        <span class="font-semibold"><?= number_format(array_sum(array_column($saleItems, 'net_weight')), 3) ?> gms</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">URD Items:</span>
                        <span class="font-semibold text-orange-600"><?= count($urdItems) ?></span>
                    </div>
                </div>
            </div>
            <!-- Bank Details -->
            <div class="amount-section p-1">
                <h3 class="text-xs font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-1">
                    <span class="golden-accent">🏦</span> Bank Details
                </h3>
                <div class="flex justify-between items-center">
                    <div class="space-y-1 text-xs flex-1">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Bank:</span>
                            <span class="font-medium"><?= htmlspecialchars($firmDetails['BankName'] ?? 'N/A') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">A/c No:</span>
                            <span class="font-medium"><?= htmlspecialchars($firmDetails['BankAccountNumber'] ?? 'N/A') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">IFSC:</span>
                            <span class="font-medium"><?= htmlspecialchars($firmDetails['IFSCCode'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Amount Details -->
            <div class="amount-section p-1">
                <h3 class="text-xs font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-1">
                    <span class="golden-accent">💰</span> Amount Details
                </h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Amount:</span>
                        <span class="font-medium">₹<?= number_format($total_metal_amount + $total_stone_amount + $total_making_charges + $total_other_charges, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Other Charges:</span>
                        <span class="font-medium">₹<?= number_format($total_other_charges, 2) ?></span>
                    </div>
                    <?php if ($urd_amount > 0): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">URD Credit:</span>
                        <span class="font-medium text-orange-600">-₹<?= number_format($urd_amount, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($total_discount > 0): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Discount:</span>
                        <span class="font-medium text-green-600">-₹<?= number_format($total_discount, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between border-t border-gray-200 pt-1">
                        <span class="text-gray-600">Sub Total:</span>
                        <span class="font-semibold">₹<?= number_format($subtotal, 2) ?></span>
                    </div>
                </div>
                <div class="total-section p-1 mt-1">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold">Grand Total:</span>
                        <span class="text-sm font-bold">₹<?= number_format($grand_total, 2) ?></span>
                    </div>
                    <?php if ($saleDetails['payment_status'] == 'Partial'): ?>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-xs font-bold">Paid:</span>
                            <span class="text-xs font-bold">₹<?= number_format($paid_amount, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-xs font-bold text-red-600">Due:</span>
                            <span class="text-xs font-bold text-red-600">₹<?= number_format($due_amount, 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Terms and Conditions (Compact) -->
        <div class="terms-section p-1 mb-1 text-xs rounded border border-gray-200 bg-gray-50" style="font-size:10px; page-break-inside: avoid;">
            <h3 class="font-semibold mb-1 text-gray-700">Terms &amp; Conditions</h3>
            <ul class="list-disc pl-4 space-y-0.5">
                <li>This is an estimate/quotation and not a final bill.</li>
                <li>Prices are subject to change without prior notice.</li>
                <li>Goods once sold will not be taken back or exchanged.</li>
                <li>All disputes are subject to [Your City] jurisdiction only.</li>
                <li>Ensure to check the items and estimate before leaving the counter.</li>
            </ul>
        </div>
        <!-- Signature Section -->
        <div class="signature-section py-1 mb-1">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <p class="text-xs font-semibold text-gray-700 mb-1">Customer Signature:</p>
                    <div class="border-b border-gray-300 h-8"></div>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold text-gray-700 mb-1">Authorized Signature:</p>
                    <div class="border-b border-gray-300 h-8"></div>
                </div>
            </div>
        </div>
        <!-- Footer -->
        <div class="footer-elegant flex items-center justify-between">
            <!-- Left: Logo -->
            <div class="logo-frame">
                <img src="<?= $firmLogo ?>" alt="Firm Logo" class="w-10 h-10 rounded-full object-contain" />
            </div>
            <!-- Center: Details -->
            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="text-xs text-white bg-gray-900 bg-opacity-80 rounded px-3 py-1 mb-0.5 font-medium" style="line-height:1.3;">
                    <span class="font-semibold">PAN:</span> <?= htmlspecialchars($firmDetails['PANNumber'] ?? 'NA') ?>
                    <span class="mx-2">|</span>
                    <span class="font-semibold">GSTIN:</span> <?= htmlspecialchars($firmDetails['GSTNumber'] ?? 'NA') ?>
                    <span class="mx-2">|</span>
                    <span class="font-semibold">License:</span> <?= htmlspecialchars($firmDetails['BISRegistrationNumber'] ?? 'NA') ?>
                </div>
                <div class="text-xs text-white bg-gray-900 bg-opacity-70 rounded px-2 py-0.5 mt-0.5" style="line-height:1.2;">
                    <span class="font-semibold">Contact:</span> <?= htmlspecialchars($firmDetails['Email']) ?><?php if ($customerPhone): ?>, <?= htmlspecialchars($customerPhone) ?><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Action Buttons -->
    <div class="no-print flex flex-col sm:flex-row justify-center items-center gap-3 mt-4 w-full px-2">
        <button onclick="window.print()" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold shadow-lg transition-all duration-200 text-sm mb-2 sm:mb-0">
            🖨️ Print Estimate
        </button>
        <button onclick="exportToPDF()" class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold shadow-lg transition-all duration-200 text-sm mb-2 sm:mb-0">
            📄 Export PDF
        </button>
        <a href="sale-list.php" class="w-full sm:w-auto bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-semibold shadow-lg transition-all duration-200 text-sm">
            ← Back to Sales
        </a>
    </div>
    <script>
        function exportToPDF() {
            const element = document.getElementById('invoice-content');
            const buttonsToHide = document.querySelectorAll('.no-print');
            buttonsToHide.forEach(btn => btn.style.display = 'none');
            html2canvas(element, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                height: element.scrollHeight,
                windowWidth: element.scrollWidth
            }).then(canvas => {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                const imgData = canvas.toDataURL('image/png');
                const imgWidth = 210;
                const pageHeight = 297;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                pdf.save('jewelry-estimate.pdf');
                buttonsToHide.forEach(btn => btn.style.display = '');
            });
        }
        // Automatically open print dialog on page load
        window.addEventListener('DOMContentLoaded', function() {
            window.print();
        });
    </script>
</body>
</html> 
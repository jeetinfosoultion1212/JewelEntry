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
                        f.BankAccountNumber, f.BankName, f.IFSCCode, f.AccountType,
                        jsi.stone_type, jsi.stone_weight, jsi.stone_price
                    FROM jewellery_sales js
                    JOIN Customer c ON js.customer_id = c.id
                    JOIN Firm f ON js.firm_id = f.id
                    LEFT JOIN Jewellery_sales_items jsi ON js.id = jsi.sale_id
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
                        f.BankAccountNumber, f.BankName, f.IFSCCode, f.AccountType,
                        jsi.stone_type, jsi.stone_weight, jsi.stone_price
                    FROM jewellery_sales js
                    JOIN Customer c ON js.customer_id = c.id
                    JOIN Firm f ON js.firm_id = f.id
                    LEFT JOIN Jewellery_sales_items jsi ON js.id = jsi.sale_id
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
                    f.BankAccountNumber, f.BankName, f.IFSCCode, f.AccountType,
                    jsi.stone_type, jsi.stone_weight, jsi.stone_price
                FROM jewellery_sales js
                JOIN Customer c ON js.customer_id = c.id
                JOIN Firm f ON js.firm_id = f.id
                LEFT JOIN Jewellery_sales_items jsi ON js.id = jsi.sale_id
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

// Format the numbers for display
function formatIndianRupee($num) {
    return '₹' . number_format($num, 2);
}

// Format date
function formatDate($date) {
    return date("M d, Y", strtotime($date));
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

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $invoice['FirmName']; ?> - Invoice #<?php echo $invoice['invoice_no']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                fontFamily: {
                    'serif': ['Cormorant Garamond', 'serif'],
                    'sans': ['Montserrat', 'sans-serif'],
                },
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#473C53',
                            light: '#6D5D7D',
                        },
                        secondary: {
                            DEFAULT: '#F9F6F9',
                            dark: '#EEE8ED'
                        },
                        accent: {
                            DEFAULT: '#D4B78E',
                            light: '#F6F0E4',
                            dark: '#B79B6C'
                        },
                        danger: {
                            DEFAULT: '#B05353',
                            light: '#EBCBCB'
                        },
                        border: {
                            DEFAULT: '#E3DDE2',
                            light: '#F5F2F4',
                        },
                    },
                    boxShadow: {
                        'elegant': '0 4px 20px rgba(0, 0, 0, 0.05)',
                        'soft': '0 2px 10px rgba(0, 0, 0, 0.03)'
                    }
                }
            }
        }
    </script>
    
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        
        @media print {
            html, body {
                width: 210mm;
                height: 297mm;
                margin: 2 2 2 2;
                padding: 0;
            }
            
            .print-hidden {
                display: none !important;
            }
            
            .shadow-elegant, .shadow-soft {
                box-shadow: none !important;
            }
            
            .page-break {
                page-break-after: always;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 w-[210mm] mx-auto">
    <div class="relative bg-white shadow-elegant overflow-hidden print:shadow-none">
        <!-- Decorative Elements -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-accent via-accent-dark to-accent"></div>
        
        <!-- Watermark -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 -rotate-45 font-serif text-[80px] text-accent/5 whitespace-nowrap pointer-events-none z-10">
            <?php echo strtoupper($invoice['FirmName']); ?>
        </div>
        
        <!-- Status Badge -->
        <div class="absolute top-[40px] right-[-35px] <?php echo $statusClass; ?> text-white px-10 py-1 text-xs uppercase tracking-wider rotate-45 font-semibold shadow-soft">
            <?php echo $statusText; ?>
        </div>
        
        <!-- Invoice Header -->
        <div class="bg-gradient-to-r from-primary to-primary-light text-white p-4 flex justify-between items-center">
            <div>
                <h1 class="font-serif text-2xl font-bold text-accent tracking-wide mb-1">INVOICE</h1>
                <div class="flex items-center gap-1 text-xs mb-0.5">
                    <i data-feather="file-text" class="w-3 h-3 text-accent-light"></i>
                    <p class="font-medium tracking-wide">Invoice# <?php echo $invoice['invoice_no']; ?></p>
                </div>
                <div class="flex items-center gap-1 text-xs mb-0.5">
                    <i data-feather="calendar" class="w-3 h-3 text-accent-light"></i>
                    <p class="opacity-90">Issue: <?php echo formatDate($invoice['sale_date']); ?></p>
                </div>
                <div class="flex items-center gap-1 text-xs">
                    <i data-feather="clock" class="w-3 h-3 text-accent-light"></i>
                    <p class="opacity-90">Due: <?php echo formatDate(date('Y-m-d', strtotime($invoice['sale_date'] . ' + 7 days'))); ?></p>
                </div>
            </div>
            <div class="text-right">
                <div class="bg-white rounded-full p-2 inline-flex items-center justify-center shadow-elegant border border-accent">
                    <?php if (!empty($invoice['FirmLogo'])): ?>
                        <img src="<?php echo $invoice['FirmLogo']; ?>" alt="<?php echo $invoice['FirmName']; ?> Logo" class="h-12">
                    <?php else: ?>
                        <div class="inline-block h-12 w-12 flex items-center justify-center font-serif text-xl font-bold text-primary">
                            <?php echo substr($invoice['FirmName'], 0, 2); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="font-serif text-lg font-bold text-accent tracking-wider mt-1"><?php echo strtoupper($invoice['FirmName']); ?></p>
                <p class="text-xs text-accent-light">Fine Jewelry Boutique</p>
            </div>
        </div>
        
        <div class="p-4">
            <!-- Billing Information -->
            <div class="flex justify-between gap-4 mb-4">
                <div class="flex-1 bg-secondary-dark bg-opacity-40 p-3 rounded-md border border-border shadow-soft">
                    <h3 class="font-serif text-primary text-xs font-semibold mb-1 flex items-center gap-1 border-b border-border pb-1">
                        <i data-feather="home" class="w-3 h-3 text-accent-dark"></i>
                        <span>Billed by</span>
                    </h3>
                    <div class="text-xs">
                        <p class="font-semibold text-primary"><?php echo $invoice['FirmName']; ?></p>
                        <p class="text-xs"><?php echo $invoice['FirmAddress']; ?></p>
                        <p class="text-xs"><?php echo $invoice['FirmCity'] . ', ' . $invoice['FirmState'] . ' - ' . $invoice['FirmPostalCode']; ?></p>
                        <div class="grid grid-cols-2 gap-x-1 gap-y-0.5 mt-1 text-xs bg-white p-1 rounded border border-border-light">
                            <span class="font-medium text-primary">GSTIN:</span>
                            <span><?php echo $invoice['FirmGSTNumber'] ?: 'N/A'; ?></span>
                            <span class="font-medium text-primary">PAN:</span>
                            <span><?php echo $invoice['FirmPANNumber'] ?: 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="flex-1 bg-secondary-dark bg-opacity-40 p-3 rounded-md border border-border shadow-soft">
                    <h3 class="font-serif text-primary text-xs font-semibold mb-1 flex items-center gap-1 border-b border-border pb-1">
                        <i data-feather="user" class="w-3 h-3 text-accent-dark"></i>
                        <span>Billed to</span>
                    </h3>
                    <div class="text-xs">
                        <p class="font-semibold text-primary"><?php echo $invoice['FirstName'] . ' ' . $invoice['LastName']; ?></p>
                        <p class="text-xs"><?php echo $invoice['Address']; ?></p>
                        <p class="text-xs"><?php echo $invoice['City'] . ', ' . $invoice['State'] . ' - ' . $invoice['PostalCode']; ?></p>
                        <div class="grid grid-cols-2 gap-x-1 gap-y-0.5 mt-1 text-xs bg-white p-1 rounded border border-border-light">
                            <span class="font-medium text-primary">GSTIN:</span>
                            <span><?php echo $invoice['GSTNumber'] ?: 'N/A'; ?></span>
                            <span class="font-medium text-primary">PAN:</span>
                            <span><?php echo $invoice['PANNumber'] ?: 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- New Purchase Table -->
            <div class="mb-3">
                <h2 class="font-serif text-primary text-sm font-semibold mb-1 flex items-center gap-1">
                    <i data-feather="shopping-bag" class="w-4 h-4 text-accent-dark"></i>
                    <span>New Purchase</span>
                </h2>
                <div class="overflow-x-auto mb-2 border border-border rounded-md shadow-soft">
                    <table class="w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium rounded-tl-md">#</th>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Item</th>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Purity</th>
                                <?php 
                                // Check if any item has stone details
                                $hasStoneDetails = false;
                                foreach ($items as $item) {
                                    if (!empty($item['stone_type']) && 
                                        !empty($item['stone_weight']) && 
                                        floatval($item['stone_weight']) > 0 && 
                                        floatval($item['stone_price']) > 0) {
                                        $hasStoneDetails = true;
                                        break;
                                    }
                                }
                                
                                if ($hasStoneDetails): 
                                ?>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Stone Type</th>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Stone Wt</th>
                                <?php endif; ?>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Rate/Gm</th>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Net wt</th>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Gross</th>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Labour</th>
                                <?php if ($hasStoneDetails): ?>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Stone Chrg</th>
                                <?php endif; ?>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium">Add. Cost</th>
                                <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1.5 text-xs font-medium rounded-tr-md">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 1;
                            foreach ($items as $item): 
                                $bgClass = $count % 2 == 0 ? 'bg-secondary/50' : '';
                            ?>
                            <tr class="<?php echo $bgClass; ?>">
                                <td class="p-1 border-b border-border-light"><?php echo $count; ?></td>
                                <td class="p-1 border-b border-border-light font-medium"><?php echo $item['product_name']; ?></td>
                                <td class="p-1 border-b border-border-light"><?php echo $item['purity']; ?>kt</td>
                                <?php if ($hasStoneDetails): ?>
                                <td class="p-1 border-b border-border-light">
                                    <?php echo (!empty($item['stone_type']) && floatval($item['stone_weight']) > 0) ? $item['stone_type'] : '-'; ?>
                                </td>
                                <td class="p-1 border-b border-border-light">
                                    <?php echo (floatval($item['stone_weight']) > 0) ? $item['stone_weight'] . ' ct' : '-'; ?>
                                </td>
                                <?php endif; ?>
                                <td class="p-1 border-b border-border-light"><?php echo $item['purity_rate']; ?></td>
                                <td class="p-1 border-b border-border-light"><?php echo $item['net_weight']; ?></td>
                                <td class="p-1 border-b border-border-light"><?php echo $item['gross_weight']; ?></td>
                                <td class="p-1 border-b border-border-light"><?php echo $item['making_charges']; ?></td>
                                <?php if ($hasStoneDetails): ?>
                                <td class="p-1 border-b border-border-light">
                                    <?php echo (floatval($item['stone_price']) > 0) ? $item['stone_price'] : '-'; ?>
                                </td>
                                <?php endif; ?>
                                <td class="p-1 border-b border-border-light"><?php echo $item['other_charges']; ?></td>
                                <td class="p-1 border-b border-border-light text-right font-medium"><?php echo formatIndianRupee($item['total']); ?></td>
                            </tr>
                            <?php 
                                $count++;
                            endforeach; 
                            
                            // If no items found
                            if (empty($items)): 
                            ?>
                            <tr>
                                <td colspan="<?php echo $hasStoneDetails ? '12' : '9'; ?>" class="p-1 text-center">No items found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Layout with Summary & Details -->
            <div class="flex gap-4">
                <!-- Left column -->
                <div class="w-3/5">
                    <?php if ($hasOldGoldExchange): ?>
                    <h2 class="font-serif text-primary text-sm font-semibold mb-1 flex items-center gap-1">
                        <i data-feather="repeat" class="w-4 h-4 text-accent-dark"></i>
                        <span>Old Gold Exchange</span>
                    </h2>
                    <div class="overflow-x-auto border border-border rounded-md shadow-soft mb-3">
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1 text-xs font-medium rounded-tl-md">#</th>
                                    <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1 text-xs font-medium">Item</th>
                                    <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1 text-xs font-medium">Gross Wt</th>
                                    <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1 text-xs font-medium">Less Wt</th>
                                    <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1 text-xs font-medium">Net Wt</th>
                                    <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1 text-xs font-medium">Purity</th>
                                    <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1 text-xs font-medium">Rate</th>
                                    <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1 text-xs font-medium">Fine Wt</th>
                                    <th class="bg-gradient-to-r from-primary to-primary-light text-white text-left p-1 text-xs font-medium rounded-tr-md">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (!empty($oldGoldItems)):
                                    $count = 1;
                                    foreach ($oldGoldItems as $item): 
                                ?>
                                <tr class="<?php echo $count % 2 == 0 ? 'bg-secondary/50' : ''; ?>">
                                    <td class="p-1 border-b border-border-light"><?php echo $count; ?></td>
                                    <td class="p-1 border-b border-border-light"><?php echo $item['item_name']; ?></td>
                                    <td class="p-1 border-b border-border-light"><?php echo number_format($item['gross_weight'], 3); ?></td>
                                    <td class="p-1 border-b border-border-light"><?php echo number_format($item['less_weight'], 3); ?></td>
                                    <td class="p-1 border-b border-border-light"><?php echo number_format($item['net_weight'], 3); ?></td>
                                    <td class="p-1 border-b border-border-light"><?php echo $item['purity']; ?>kt</td>
                                    <td class="p-1 border-b border-border-light"><?php echo number_format($item['rate'], 2); ?></td>
                                    <td class="p-1 border-b border-border-light"><?php echo number_format($item['fine_weight'], 3); ?></td>
                                    <td class="p-1 border-b border-border-light text-right"><?php echo formatIndianRupee($item['total_amount']); ?></td>
                                </tr>
                                <?php 
                                    $count++;
                                    endforeach;
                                endif;
                                ?>
                                <tr class="bg-accent-light/30">
                                    <td class="p-1" colspan="7"></td>
                                    <td class="p-1 font-bold">Total</td>
                                    <td class="p-1 text-right font-bold"><?php echo formatIndianRupee($invoice['urd_amount']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Bank & Payment Details -->
                    <div class="flex gap-3 mt-2">
                        <div class="w-3/5">
                            <h2 class="font-serif text-primary text-xs font-semibold mb-1 flex items-center gap-1">
                                <i data-feather="credit-card" class="w-3 h-3 text-accent-dark"></i>
                                <span>Bank Details</span>
                            </h2>
                            <div class="grid grid-cols-2 bg-secondary p-2 rounded-md text-xs border border-border-light shadow-soft">
                                <span class="font-medium text-primary">Account:</span>
                                <span><?php echo $invoice['FirmName']; ?></span>
                                
                                <span class="font-medium text-primary">A/C No:</span>
                                <span><?php echo $invoice['BankAccountNumber']; ?></span>
                                
                                <span class="font-medium text-primary">IFSC:</span>
                                <span><?php echo $invoice['IFSCCode']; ?></span>
                                
                                <span class="font-medium text-primary">Type:</span>
                                <span><?php echo $invoice['AccountType']; ?></span>
                                
                                <span class="font-medium text-primary">Bank:</span>
                                <span><?php echo $invoice['BankName']; ?></span>
                                
                                <span class="font-medium text-primary">UPI:</span>
                                <span><?php echo strtolower(str_replace(' ', '', $invoice['FirmName'])) . '@' . strtolower(str_replace(' ', '', $invoice['BankName'])); ?></span>
                            </div>
                        </div>
                        <div class="w-2/5 text-center">
                            <h2 class="font-serif text-primary text-xs font-semibold mb-1 flex items-center gap-1 justify-center">
                                <i data-feather="smartphone" class="w-3 h-3 text-accent-dark"></i>
                                <span>Scan to Pay</span>
                            </h2>
                            <div class="inline-block p-1 border border-accent rounded-md bg-white shadow-soft">
                                <img src="uploads/jewelry/qr.png" alt="UPI QR Code" class="w-16 h-16">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Terms and Declaration -->
                   
                </div>
                
                <!-- Right column -->
                <div class="w-2/5">
                    <h2 class="font-serif text-primary text-sm font-semibold mb-1 flex items-center gap-1">
                        <i data-feather="clipboard" class="w-4 h-4 text-accent-dark"></i>
                        <span>Summary</span>
                    </h2>
                    <div class="bg-secondary rounded-md p-2 mb-3 border border-border-light shadow-soft">
                        <div class="flex justify-between py-1 border-b border-dashed border-border text-xs">
                            <span>Sub-total Including GST</span>
                            <span class="font-medium"><?php echo formatIndianRupee($invoice['subtotal']); ?></span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-dashed border-border text-xs">
                            <span>Making Charges</span>
                            <span class="font-medium"><?php echo formatIndianRupee($invoice['total_making_charges']); ?></span>
                        </div>
                        <?php if (!empty($invoice['stone_price'])): ?>
                        <div class="flex justify-between py-1 border-b border-dashed border-border text-xs">
                            <span>Stone Charges</span>
                            <span class="font-medium"><?php echo formatIndianRupee($invoice['stone_price']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between py-1 border-b border-dashed border-border text-xs">
                            <span>Discount</span>
                            <span class="font-medium text-danger">- <?php echo formatIndianRupee($invoice['discount']); ?></span>
                        </div>
                        <?php if (!empty($invoice['urd_amount'])): ?>
                        <div class="flex justify-between py-1 border-b border-dashed border-border text-xs">
                            <span>Old Gold Exchange (URD)</span>
                            <span class="font-medium text-danger">- <?php echo formatIndianRupee($invoice['urd_amount']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between pt-1 mt-1 border-t border-primary text-sm font-semibold">
                            <span>Total </span>
                            <span class="text-primary"><?php echo formatIndianRupee($invoice['grand_total']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Final Amount -->
                    <div class="bg-gradient-to-r from-primary to-primary-light text-white p-2 rounded-md shadow-elegant">
                        <div class="flex justify-between py-0.5 border-b border-dashed border-white/30 text-xs">
                            <span>Final Amount:</span>
                            <span><?php echo formatIndianRupee($invoice['grand_total']); ?></span>
                        </div>
                        <div class="flex justify-between py-0.5 border-b border-dashed border-white/30 text-xs">
                            <span>Amount Paid:</span>
                            <span><?php echo formatIndianRupee($invoice['total_paid_amount']); ?></span>
                        </div>
                        <div class="flex justify-between pt-1 mt-0.5 border-t border-white/60 text-sm font-bold">
                            <span>Due Amount:</span>
                            <span><?php echo formatIndianRupee($invoice['due_amount']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Amount in Words -->
                    <div class="bg-accent-light p-2 rounded-md mt-3 text-xs flex items-start border border-accent/30 shadow-soft">
                        <i data-feather="message-circle" class="w-3 h-3 mr-1 text-accent-dark mt-0.5 flex-shrink-0"></i>
                        <div>
                            <span class="font-semibold text-primary">Amount In Words:</span>
                            <span><?php echo numberToWords($invoice['grand_total']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Signatures -->
                  
                </div>
            </div>
            <div class="w-full border-t border-border pt-4 mb-4">
                <div class="flex justify-between items-start gap-8">
                    <!-- Customer Section -->
                    <div class="flex-1">
                        <div class="h-24 flex items-end justify-center border-b-2 border-primary/30">
                            <!-- Signature space -->
                        </div>
                        <div class="text-center mt-2">
                            <p class="text-sm font-medium text-primary"><?php echo $invoice['FirstName'] . ' ' . $invoice['LastName']; ?></p>
                            <p class="text-xs text-gray-500">Customer Signature</p>
                        </div>
                        <div class="mt-2 text-[10px] text-gray-600 text-center">
                            <p>I hereby acknowledge the receipt of items mentioned above and agree to the terms & conditions.</p>
                        </div>
                    </div>

                    <!-- Seal/Stamp Section -->
                    <div class="flex-1 flex flex-col items-center">
                        <div class="h-24 w-24 border-2 border-dashed border-primary/30 rounded-full flex items-center justify-center">
                            <p class="text-[10px] text-gray-400">Official Seal</p>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Company Seal</p>
                    </div>

                    <!-- Authority Section -->
                    <div class="flex-1">
                        <div class="h-24 flex items-end justify-center border-b-2 border-primary/30">
                            <?php if (!empty($invoice['FirmLogo'])): ?>
                                <img src="path/to/signature.png" alt="Digital Signature" class="h-16 opacity-80">
                            <?php endif; ?>
                        </div>
                        <div class="text-center mt-2">
                            <p class="text-sm font-medium text-primary">For <?php echo $invoice['FirmName']; ?></p>
                            <p class="text-xs text-gray-500">Authorized Signatory</p>
                        </div>
                        <div class="mt-2 text-[10px] text-gray-600 text-center">
                            <p>This is a computer-generated document and bears the authorized digital signature.</p>
                        </div>
                    </div>
                </div>
                <!-- Additional Notes -->
                        <div class="mt-4 text-[10px] text-center text-gray-500 border-t border-border pt-2">
                            <p class="font-medium">Important Notice</p>
                            <p>This document is protected under applicable laws. Any unauthorized modification or reproduction is strictly prohibited.</p>
                            <p>For verification or queries, please contact our customer service with invoice number: <span class="font-medium"><?php echo $invoice['invoice_no']; ?></span></p>
                        </div>
            </div>
            <!-- Footer -->
            <div class="mt-3 text-center border-t border-border pt-2">
                <div class="flex justify-center items-center mb-1">
                    <div class="bg-primary text-white p-0.5 rounded mr-1">
                        <i data-feather="mail" class="w-3 h-3"></i>
                    </div>
                    <span class="text-xs"><?php echo $invoice['FirmEmail']; ?></span>
                    <div class="bg-primary text-white p-0.5 rounded ml-2 mr-1">
                        <i data-feather="phone" class="w-3 h-3"></i>
                    </div>
                    <span class="text-xs"><?php echo $invoice['FirmPhoneNumber']; ?></span>
                </div>
                <p class="text-[9px] text-gray-500">This is a computer-generated invoice and does not require a physical signature.</p>
                <p class="text-[9px] text-gray-500">© <?php echo date('Y'); ?> <?php echo $invoice['FirmName']; ?> - All Rights Reserved</p>
            </div>
        </div>
    </div>
    
    <div class="flex items-center justify-center mt-4 mb-6 gap-3 print-hidden">
        <button onclick="window.print()" class="bg-primary text-white px-3 py-1.5 rounded flex items-center gap-1 hover:bg-primary-light transition text-sm">
            <i data-feather="printer" class="w-4 h-4"></i>
            Print Invoice
        </button>
        <button onclick="window.history.back()" class="bg-gray-200 text-gray-800 px-3 py-1.5 rounded flex items-center gap-1 hover:bg-gray-300 transition text-sm">
            <i data-feather="arrow-left" class="w-4 h-4"></i>
            Back
        </button>
        <button onclick="location.href='download_invoice.php?id=<?php echo $invoice_id; ?>&invoice_no=<?php echo $invoice_no; ?>'" class="bg-accent text-white px-3 py-1.5 rounded flex items-center gap-1 hover:bg-accent-dark transition text-sm">
            <i data-feather="download" class="w-4 h-4"></i>
            Download PDF
        </button>
    </div>
    
    <script>
        // Initialize Feather icons
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace({ width: 16, height: 16 });
            
            // Add print media query
            const mediaQueryList = window.matchMedia('print');
            mediaQueryList.addListener(function(mql) {
                if (mql.matches) {
                    // Entering print mode
                    document.querySelectorAll('[data-feather]').forEach(icon => {
                        // Make icons slightly smaller for print
                        feather.replace(icon, { width: 12, height: 12 });
                    });
                    
                    // Adjust font sizes for print
                    document.body.classList.add('print-mode');
                } else {
                    // Exiting print mode
                    document.querySelectorAll('[data-feather]').forEach(icon => {
                        feather.replace(icon, { width: 16, height: 16 });
                    });
                    
                    document.body.classList.remove('print-mode');
                }
            });
        });
        
        // Add print styles dynamically
        window.onbeforeprint = function() {
            // Add print-specific styles
            const style = document.createElement('style');
            style.textContent = `
                @media print {
                    body {
                        width: 210mm !important;
                        height: 297mm !important;
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                    .print-hidden {
                        display: none !important;
                    }
                    .shadow-elegant, .shadow-soft {
                        box-shadow: none !important;
                    }
                    
                    /* Adjust font sizes for print */
                    .text-2xl { font-size: 1.25rem !important; }
                    .text-xl { font-size: 1.1rem !important; }
                    .text-lg { font-size: 1rem !important; }
                    .text-sm { font-size: 0.75rem !important; }
                    .text-xs { font-size: 0.7rem !important; }
                    
                    /* Adjust spacing for print */
                    .p-4 { padding: 0.75rem !important; }
                    .p-3 { padding: 0.5rem !important; }
                    .p-2 { padding: 0.35rem !important; }
                    .p-1 { padding: 0.25rem !important; }
                    
                    .mb-4 { margin-bottom: 0.75rem !important; }
                    .mb-3 { margin-bottom: 0.5rem !important; }
                    .mb-2 { margin-bottom: 0.35rem !important; }
                    .mb-1 { margin-bottom: 0.25rem !important; }
                    
                    /* Enhanced print quality */
                   * {
                        -webkit-print-color-adjust: exact !important;
                        color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                }
            `;
            document.head.appendChild(style);
        };
        
        window.onafterprint = function() {
            // Clean up any print-specific styles
            document.querySelectorAll('style').forEach(style => {
                if (style.textContent.includes('@media print')) {
                    style.remove();
                }
            });
        };
    </script>
</body>
</html>
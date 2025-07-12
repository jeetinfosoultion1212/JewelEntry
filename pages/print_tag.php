<?php
// Keep errors hidden in production
ini_set('display_errors', 0);
error_reporting(0);

// Set headers
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");

// Check if QR library exists before requiring it
if (file_exists('phpqrcode/qrlib.php')) {
    require_once 'phpqrcode/qrlib.php';
}

// Database connection parameters
$host = 'localhost';
$db   = 'u176143338_retailstore';
$user = 'u176143338_retailstore';
$pass = 'Rontik10@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Try connecting to database
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Silently fail but set empty data
    $jewellery_data = [];
    $pdo = null;
}

// Get the product_id or id from the query parameters
$productId = isset($_GET['product_id']) ? $_GET['product_id'] : (isset($_GET['id']) ? $_GET['id'] : null);

// If we have a valid database connection
if ($pdo !== null && $productId) {
    try {
        // Update the SQL query to select from jewellery_items and join with Firm
        $stmt = $pdo->prepare("
            SELECT jewellery_items.*, Firm.FirmName 
            FROM jewellery_items 
            LEFT JOIN Firm ON jewellery_items.firm_id = Firm.id 
            WHERE jewellery_items.product_id = :product_id OR jewellery_items.id = :id
        ");
        $stmt->execute(['product_id' => $productId, 'id' => $productId]);
        $jewellery_data = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $jewellery_data = [];
    }
} else {
    $jewellery_data = [];
}

// Create QR codes directory if it doesn't exist
$qrcodeDir = 'qrcodes/';
if (!empty($jewellery_data) && !is_dir($qrcodeDir)) {
    @mkdir($qrcodeDir, 0777, true);
}

// Generate QR codes for each jewellery item
if (!empty($jewellery_data) && class_exists('QRcode')) {
    foreach ($jewellery_data as $row) {
        $qrcodeFile = $qrcodeDir . $row['product_id'] . '.png';
        
        // Check if QR data exists
        $qrData = isset($row['pair_id']) ? $row['pair_id'] : $row['product_id'];
        
        // Only generate if file doesn't exist
        if (!file_exists($qrcodeFile)) {
            try {
                QRcode::png($qrData, $qrcodeFile, QR_ECLEVEL_L, 3);
            } catch (Exception $e) {
                // Log error or continue silently
            }
        }
    }
}

// Function to convert purity value to karat
function getPurityInKarat($purity) {
    $purity = floatval($purity);
    
    if ($purity >= 91.5 && $purity <= 92.5) {
        return "22K";
    } elseif ($purity >= 75.5 && $purity <= 76.5) {
        return "18K";
    } elseif ($purity >= 58.5 && $purity <= 59.5) {
        return "14K";
    } elseif ($purity >= 83.5 && $purity <= 84.5) {
        return "20K";
    } else {
        return $purity . "%"; // Default to percentage if no match
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print HUID Tags</title>
    <style>
        @font-face {
            font-family: 'PrinterOptimized';
            src: url('fonts/ArialNarrow.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @page {
            size: 54mm 14mm;
            margin: 0;
            bleed: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'PrinterOptimized', 'Arial Narrow', 'Arial', 'Helvetica', sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .tag {
            width: 54mm;
            height: 14mm;
            page-break-after: always;
            page-break-inside: avoid;
            position: relative;
            display: flex;
            justify-content: space-between; /* Added */
            background: white;
            border: 0.1mm solid #eee;
        }

        .left-section {
            width: 29mm; /* Reduced from 31mm */
            margin-left: 1.5mm;
            padding-right: 1.5mm;
            border-right: 0.2mm solid #ddd;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            height: 100%;
        }

        .right-section {
            width: 23mm;
            display: grid;
            grid-template-columns: 1fr auto;
            grid-template-rows: auto 1fr auto;
            align-items: center;
            height: 100%;
            padding: 0.5mm;
            gap: 0.2mm;
        }

        .detail-row {
            font-size: 4.5pt;  /* reduced from 5pt */
            line-height: 1.2;
            margin-bottom: 0.2mm;
        }

        .label {
            font-weight: 500;
            color: #444;
            min-width: 7mm;
        }

        .value {
            font-family: 'Verdana', 'Tahoma', sans-serif;
            font-weight: 700;
            letter-spacing: 0.05mm;
            font-size: 5pt;
            color: #000;
        }

        .huid-value {
            font-family: 'Verdana', 'Tahoma', sans-serif;
            font-weight: 800;
            letter-spacing: 0.1mm;
        }

        .qrcode {
            width: 11mm;
            height: 9mm;
            grid-column: 1;
            grid-row: 2;
            margin: 0;
        }

        .qrcode img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .company-name {
            font-size: 4pt;
            text-align: center;
            width: 100%;
            grid-column: 1;
            grid-row: 3;
            margin: 0;
            padding-top: 0.2mm;
        }

        .error-message {
            background-color: #ffecec;
            border: 1px solid #f5aca6;
            color: #cc0000;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            text-align: center;
        }
        
        .weight-section {
            display: block; /* Changed from flex to block */
        }
        
        .product-title {
            font-size: 5.5pt;  /* reduced from 6pt */
            padding-bottom: 0.2mm;
            margin-bottom: 0.2mm;
            border-bottom: 0.2mm solid #eee;
            font-weight: 700;
            color: #000;
        }
        
        .product-id {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            font-size: 4pt;
            line-height: 1;
            text-align: center;
            grid-column: 2;
            grid-row: 1 / span 3;
            height: 100%;
            margin-left: 0.5mm;
            white-space: nowrap;
        }

        .huid-container {
            font-size: 4pt;
            text-align: center;
            width: 100%;
            grid-column: 1;
            grid-row: 1;
            margin: 0;
            padding-bottom: 0.2mm;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .tag {
                break-inside: avoid;
                break-after: always;
                background: white;
                margin: 0;
                padding: 0;
            }
            
            @page {
                size: 54mm 14mm;
                margin: 0;
                bleed: 0;
            }
        }

    </style>
</head>
<body>
    <?php if (!empty($jewellery_data)): ?>
        <?php foreach ($jewellery_data as $row): ?>
        <div class="tag">
            <div class="left-section">
                <div class="product-title"><?php echo htmlspecialchars($row['product_name'] ?? 'N/A'); ?></div>
                
                <div class="detail-row">
                    <span class="label">Purity</span>:
                    <span class="value"><?php echo htmlspecialchars(getPurityInKarat($row['purity'] ?? '0')); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="label">G.wt</span>:
                    <span class="value"><?php echo htmlspecialchars($row['gross_weight'] ?? 'N/A'); ?></span>
                </div>

                <?php if (!empty($row['net_weight'])): ?>
                <div class="detail-row">
                    <span class="label">N.wt</span>:
                    <span class="value"><?php echo htmlspecialchars($row['net_weight'] ?? 'N/A'); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($row['stone_weight']) && $row['stone_weight'] != '0'): ?>
                <div class="detail-row">
                    <span class="label">D.wt</span>:
                    <span class="value huid-value"><?php echo htmlspecialchars($row['stone_weight']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="right-section">
                <div class="product-id">ID: <?php echo htmlspecialchars($row['product_id'] ?? 'N/A'); ?></div>
                
                <div class="qrcode">
                    <?php 
                    $qrImagePath = $qrcodeDir . $row['product_id'] . '.png';
                    if (file_exists($qrImagePath)): 
                    ?>
                        <img src="<?php echo $qrImagePath; ?>" alt="QR Code">
                    <?php else: ?>
                        <div style="text-align:center;font-size:6pt;">QR Code<br>Not Available</div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($row['huid_code']) && $row['huid_code'] != '0'): ?>
                <div class="huid-container">
                    <span class="value huid-value"><?php echo htmlspecialchars($row['huid_code']); ?></span>
                </div>
                <?php endif; ?>

                <div class="company-name"><?php echo htmlspecialchars($row['FirmName'] ?? 'N/A'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="error-message">No product data found for the provided ID.</div>
    <?php endif; ?>
</body>
</html>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    header("Location: login.php");
    exit();
}
$current_firm_id = $_SESSION['firmID'];
$user_id = $_SESSION['id']; // Get user ID for fetching user details

// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jewelentrypro";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Fetch User Details for Header ---
$userInfo = array();
$sql_user = "SELECT u.Name, u.Role, u.image_path FROM Firm_Users u WHERE u.id = ? LIMIT 1";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows > 0) {
    $userInfo = $result_user->fetch_assoc();
} else {
    // Provide default user details if not found
    $userInfo = array(
        'Name' => 'User',
        'Role' => 'Guest',
        'image_path' => null // Or a path to a default user image
    );
}

// --- FETCH PRICE CONFIGURATION FIRST (FIXED) ---
$priceConfig = array();
$sql_price = "SELECT p.*, 
    DATE_FORMAT(p.effective_date, '%d %b %Y') as formatted_date
    FROM jewellery_price_config p
    INNER JOIN (
        SELECT material_type, purity, MAX(effective_date) as max_date
        FROM jewellery_price_config 
        WHERE material_type IN ('Gold', 'Silver') 
        AND purity IN (99.99, 999.90)
        AND firm_id = ?
        GROUP BY material_type, purity
    ) latest ON p.material_type = latest.material_type 
    AND p.purity = latest.purity 
    AND p.effective_date = latest.max_date
    ORDER BY p.material_type, p.purity DESC";

$stmt_price = $conn->prepare($sql_price);
$stmt_price->bind_param("i", $current_firm_id);
$stmt_price->execute();
$result_price = $stmt_price->get_result();

if ($result_price->num_rows > 0) {
    while($row = $result_price->fetch_assoc()) {
        $purityKey = (string)$row['purity'];
        $priceConfig[$row['material_type']][$purityKey] = array(
            'rate' => $row['rate'],
            'date' => $row['formatted_date']
        );
    }
}

// Initialize default values if not set
if (!isset($priceConfig['Gold']['99.99'])) {
    if (!isset($priceConfig['Gold'])) $priceConfig['Gold'] = [];
    $priceConfig['Gold']['99.99'] = array('rate' => 7500, 'date' => date('d M Y'));
}
if (!isset($priceConfig['Silver']['999.90'])) {
     if (!isset($priceConfig['Silver'])) $priceConfig['Silver'] = [];
    $priceConfig['Silver']['999.90'] = array('rate' => 95, 'date' => date('d M Y'));
}

// --- NOW FETCH PRODUCT DATA WITH CORRECT PRICE CALCULATIONS ---
$productsData = array();

$sql_products = "SELECT ji.*, ji.Tray_no as tray_no, GROUP_CONCAT(jpi.image_url) as image_urls,
                 SUBSTRING(ji.description, 1, 100) as short_description 
                 FROM jewellery_items ji 
                 LEFT JOIN jewellery_product_image jpi ON ji.id = jpi.product_id 
                 WHERE ji.status = 'Available' AND ji.firm_id = $current_firm_id
                 GROUP BY ji.id 
                 ORDER BY ji.created_at DESC 
                 LIMIT 20";

$result_products = $conn->query($sql_products);

if ($result_products && $result_products->num_rows > 0) {
    while($row = $result_products->fetch_assoc()) {
        $row['images'] = $row['image_urls'] ? explode(',', $row['image_urls']) : [];
        unset($row['image_urls']);

        // Parse numeric purity
        $purityNumeric = floatval($row['purity']);

        // Purity label logic
        if ($purityNumeric >= 91.6 && $purityNumeric <= 92.0) {
            $purityLabel = '22K';
        } elseif ($purityNumeric >= 75.5 && $purityNumeric <= 77.0) {
            $purityLabel = '18K';
        } elseif ($purityNumeric >= 58.5 && $purityNumeric <= 59.5) {
            $purityLabel = '14K';
        } elseif ($purityNumeric >= 83.3 && $purityNumeric <= 84.0) {
            $purityLabel = '20K';
        } else {
            $purityLabel = $purityNumeric . 'K';
        }

        // Calculate price correctly
        if ($row['material_type'] === 'Gold') {
            $fineRate = isset($priceConfig['Gold']['99.99']['rate']) ? $priceConfig['Gold']['99.99']['rate'] : 7500;
            $goldRate = $fineRate * ($purityNumeric / 99.99);
        } elseif ($row['material_type'] === 'Silver') {
            $fineRate = isset($priceConfig['Silver']['999.90']['rate']) ? $priceConfig['Silver']['999.90']['rate'] : 95;
            $goldRate = $fineRate * ($purityNumeric / 999.90);
        } else {
            $goldRate = 0;
        }

        $row['rate_per_gram'] = $goldRate;
        $row['marketPrice'] = $row['net_weight'] * $goldRate;
        $row['makingCharges'] = $row['making_charge_type'] == 'percentage' 
            ? ($row['marketPrice'] * $row['making_charge'] / 100)
            : $row['making_charge'];
        $row['totalPrice'] = $row['marketPrice'] + $row['makingCharges'];
        
        // Add additional product details
        $row['name'] = $row['product_name'];
        $row['purity'] = $purityLabel;
        $row['netWeight'] = $row['net_weight'];
        $row['grossWeight'] = $row['gross_weight'];
        $row['stoneWeight'] = $row['stone_weight'] > 0 ? $row['stone_weight'] . ' ' . $row['stone_unit'] : null;
        $row['tray_no'] = isset($row['tray_no']) ? $row['tray_no'] : '';
        
        $productsData[$row['id']] = $row;
    }
}

// --- Fetch Product Statistics ---
$productStats = array();
$sql_stats = "SELECT 
    material_type,
    CASE 
        WHEN purity BETWEEN 91.6 AND 93.0 THEN '22K'
        WHEN purity BETWEEN 75.5 AND 77.0 THEN '18K'
        WHEN purity BETWEEN 58.5 AND 59.5 THEN '14K'
        WHEN purity BETWEEN 83.3 AND 84.0 THEN '20K'
        ELSE 'Other'
    END as purity_range,
    COUNT(*) as total_items,
    SUM(gross_weight) as total_weight
FROM jewellery_items 
WHERE status = 'Available'
AND firm_id = ?
GROUP BY material_type, purity_range
ORDER BY material_type, purity_range";

$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $current_firm_id);
$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();

if ($result_stats->num_rows > 0) {
    while($row = $result_stats->fetch_assoc()) {
        $productStats[$row['material_type']][$row['purity_range']] = array(
            'count' => $row['total_items'],
            'weight' => $row['total_weight']
        );
    }
}

// --- Fetch Firm Details ---
$firmDetails = array();
// It's good practice to select specific columns instead of '*'
$sql_firm = "SELECT FirmName, Address,  city, state, PostalCode, PhoneNumber FROM Firm WHERE id = ? LIMIT 1";
$stmt_firm = $conn->prepare($sql_firm);
$stmt_firm->bind_param("i", $current_firm_id);
$stmt_firm->execute();
$result_firm = $stmt_firm->get_result();

if ($result_firm->num_rows > 0) {
    $firmDetails = $result_firm->fetch_assoc();
} else {
    // Provide default firm details if not found, or handle as an error
    $firmDetails = array(
        'FirmName' => 'Your Company Name',
        'address' => '123 Default Street',
        'city' => 'Default City',
        'phone_number' => '000-000-0000'
    );
}


$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jewellers Wala - Professional Catalog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            overscroll-behavior-y: contain; /* Prevents pull-to-refresh on body scroll */
        }
        .font-playfair { font-family: 'Playfair Display', serif; }
        .font-inter { font-family: 'Inter', sans-serif; }
        
        .product-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        
        .gold-gradient {
            background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #b8860b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .slide-in {
            transform: translateX(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            visibility: hidden; /* Hide when not active */
        }
        
        .slide-in.active {
            transform: translateX(0);
            visibility: visible; /* Show when active */
        }
        
        .slide-out { /* This is for the product list */
            transform: translateX(0); /* Initially visible */
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            visibility: visible;
        }
        
        .slide-out:not(.active) { /* When product list is NOT active (details are) */
            transform: translateX(-100%);
            visibility: hidden;
        }

        
        .jewelry-shimmer {
            background: linear-gradient(45deg, #f8f9fa 25%, #e9ecef 50%, #f8f9fa 75%);
            background-size: 200% 200%;
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% -200%; }
            100% { background-position: 200% 200%; }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-3px); }
        }
        
        .catalog-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .qr-container { /* General QR container, ensure bg for visibility */
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .glass-effect {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        }

        .spec-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(5px);
        }

        .price-highlight {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #000;
        }

        /* Catalog Export Styles */
        #catalog-card-export-container {
            position: fixed;
            left: -9999px;
            top: -9999px;
            z-index: -1;
            width: 400px;
            background: white;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        #catalog-card-export {
            width: 100%;
            background: linear-gradient(135deg, #FDF5EC 0%, #FFF9F0 100%);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            font-family: 'Inter', sans-serif;
        }

        .catalog-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            color: #5D4037;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .image-container-export {
            width: 100%;
            height: 240px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 16px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .image-container-export img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-name-export {
            font-size: 20px;
            font-weight: 600;
            color: #2C1810;
            text-align: center;
            margin-bottom: 16px;
            font-family: 'Playfair Display', serif;
        }

        .specs-export {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .specs-export p {
            margin: 8px 0;
            font-size: 14px;
            color: #5D4037;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .price-export {
            font-size: 16px !important;
            font-weight: 600;
            border-top: 1px solid rgba(93, 64, 55, 0.1);
            padding-top: 12px;
            margin-top: 12px;
        }

        .price-value-export {
            color: #B8860B;
            font-size: 18px;
        }

        .footer-export {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 16px;
        }

        .company-details-export {
            flex: 1;
        }

        .company-name-export {
            font-weight: 700;
            font-size: 16px;
            color: #5D4037;
            margin-bottom: 4px;
        }

        #export-card-date {
            font-size: 12px;
            color: #8D6E63;
        }

        .qr-code-container-export {
            width: 72px;
            height: 72px;
            background: white;
            padding: 4px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        /* Toast styles */
        .toast {
            transform: translateY(100%);
            opacity: 0;
            transition: all 0.3s ease-in-out;
        }

        .toast-info { 
            background-color: #2196F3; 
        }

        .toast-success { 
            background-color: #4CAF50; 
        }

        .toast-warning { 
            background-color: #FFC107; 
            color: #000; 
        }

        .toast-error { 
            background-color: #F44336; 
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .truncate-2-lines {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
            padding: 10px;
        }
        .product-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px #0001;
            padding: 10px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .product-image {
            position: relative;
            width: 80px;
            height: 80px;
            margin-bottom: 8px;
        }
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            background: #f5f5f5;
        }
        .category-badge {
            position: absolute;
            bottom: -10px;
            right: -10px;
            background: #f0c419;
            color: #fff;
            border-radius: 50%;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 1px 4px #0002;
        }
     
        .category-filter-minimal {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 6px;
            scrollbar-width: thin;
            scrollbar-color: #e0e0e0 #fff;
        }
        .category-filter-minimal::-webkit-scrollbar {
            height: 4px;
        }
        .category-filter-minimal::-webkit-scrollbar-thumb {
            background: #e0e0e0;
            border-radius: 2px;
        }
        .category-filter-btn {
            background: #f5f5f5;
            border-radius: 16px;
            padding: 6px 16px;
            font-size: 14px;
            color: #444;
            border: none;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .category-filter-btn.active, .category-filter-btn:hover {
            background: #1a7f37;
            color: #fff;
        }
        .filter-bar-minimal {
            background: #fff;
            border-radius: 10px;
            padding: 6px 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            font-size: 13px;
            margin-bottom: 10px;
        }
        .filter-select-minimal {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 3px 10px;
            font-size: 13px;
            background: #fafafa;
            color: #444;
            outline: none;
        }
        .filter-range-minimal {
            width: 70px;
            accent-color: #1a7f37;
        }
        .filter-label-minimal {
            font-size: 12px;
            color: #888;
            min-width: 24px;
            text-align: center;
        }
        @media (max-width: 600px) {
            .filter-bar-minimal { flex-direction: column; gap: 6px; padding: 6px 4px; }
            .filter-range-minimal { width: 50px; }
        }
        .filter-icon-btn-minimal {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 6px 16px;
            font-size: 15px;
            color: #1a7f37;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .filter-icon-btn-minimal:hover {
            background: #1a7f37;
            color: #fff;
        }
        .filter-modal-minimal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1001;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            min-width: 280px;
            max-width: 95vw;
            width: 340px;
            padding: 18px 18px 12px 18px;
            display: flex;
            flex-direction: column;
            transition: opacity 0.2s;
            overflow-y: auto;
            max-height: 90vh;
        }
        .filter-modal-content-minimal {
            width: 100%;
            position: relative;
            z-index: 1002;
        }
        .filter-select-minimal {
            max-width: 100%;
            width: 100%;
            z-index: 1100;
            position: relative;
        }
        .filter-modal-minimal select, .filter-modal-minimal option {
            max-width: 100vw;
            word-break: break-all;
        }
        @media (max-width: 600px) {
            .filter-modal-minimal { min-width: 90vw; width: 98vw; padding: 10px 6px 8px 6px; }
        }
        .purity-badge-minimal {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #ffd700;
            color: #222;
            font-size: 12px;
            font-weight: 700;
            border-radius: 12px;
            padding: 2px 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            z-index: 2;
        }
        .making-badge-minimal {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #fff;
            color: #1a7f37;
            font-size: 11px;
            font-weight: 600;
            border-radius: 12px;
            padding: 2px 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            z-index: 2;
            border: 1px solid #e0e0e0;
        }
        .product-image-minimal {
            position: relative;
        }
        .product-grid-minimal {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
    gap: 10px;
    padding: 12px;
    background: #f8fafc;
}

.product-card-minimal {
    background: #ffffff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.25s ease;
    position: relative;
    border: 1px solid #e2e8f0;
    cursor: pointer;
}

.product-card-minimal:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    border-color: #cbd5e1;
}

.product-image-minimal {
    position: relative;
    width: 100%;
    height: 110px;
    overflow: hidden;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
}

.product-image-minimal img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card-minimal:hover .product-image-minimal img {
    transform: scale(1.06);
}

.purity-badge-minimal {
    position: absolute;
    top: 6px;
    left: 6px;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
    z-index: 3;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.making-badge-minimal {
    position: absolute;
    top: 6px;
    right: 6px;
    background: rgba(15, 23, 42, 0.85);
    color: white;
    padding: 2px 5px;
    border-radius: 4px;
    font-size: 8px;
    font-weight: 600;
    z-index: 3;
    backdrop-filter: blur(4px);
    max-width: 45px;
    text-align: center;
    line-height: 1.1;
}

.favorite-icon-minimal {
    position: absolute;
    bottom: 6px;
    right: 6px;
    width: 24px;
    height: 24px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 3;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.favorite-icon-minimal:hover {
    background: white;
    color: #ef4444;
    transform: scale(1.1);
}

.product-info-minimal {
    padding: 10px;
}

.product-title-minimal {
    font-size: 12px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 5px;
    line-height: 1.3;
    text-align: left;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.product-meta-minimal {
    margin-bottom: 6px;
}

.product-weight-minimal {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    color: #475569;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    margin-bottom: 4px;
}

.product-weight-minimal::before {
    content: "⚖";
    margin-right: 3px;
    font-size: 9px;
    opacity: 0.7;
}

.product-price-minimal {
    font-size: 13px;
    font-weight: 700;
    color: #059669;
    text-align: left;
    margin-top: 2px;
}

.product-price-minimal::before {
    content: "₹";
    font-size: 11px;
    margin-right: 1px;
}

/* Mobile Optimizations */
@media (max-width: 480px) {
    .product-grid-minimal {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        padding: 8px;
    }
    
    .product-image-minimal {
        height: 100px;
    }
    
    .product-info-minimal {
        padding: 8px;
    }
    
    .product-title-minimal {
        font-size: 11px;
        margin-bottom: 4px;
    }
    
    .product-weight-minimal {
        font-size: 9px;
        padding: 1px 5px;
    }
    
    .product-price-minimal {
        font-size: 12px;
    }
    
    .purity-badge-minimal,
    .making-badge-minimal {
        font-size: 8px;
        padding: 1px 4px;
    }
    
    .favorite-icon-minimal {
        width: 20px;
        height: 20px;
        font-size: 10px;
    }
}

@media (min-width: 481px) and (max-width: 640px) {
    .product-grid-minimal {
        grid-template-columns: repeat(3, 1fr);
        gap: 9px;
    }
}

@media (min-width: 641px) and (max-width: 768px) {
    .product-grid-minimal {
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    .product-grid-minimal {
        grid-template-columns: repeat(5, 1fr);
        gap: 11px;
    }
}

@media (min-width: 1025px) {
    .product-grid-minimal {
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
    }
}

/* Enhanced Hover Effects */
.product-card-minimal:hover .product-title-minimal {
    color: #3b82f6;
}

.product-card-minimal:hover .product-price-minimal {
    transform: translateX(2px);
}

/* Loading State */
.product-image-minimal img[src*="no_image"] {
    opacity: 0.6;
    filter: grayscale(0.2);
}

.glass-badge {
    background: rgba(255,255,255,0.5);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.2);
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

/* New styles for rate and price on same line */
.price-rate-container {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 8px;
}

.price-section {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.product-price-minimal {
    font-size: 13px;
    font-weight: 700;
    color: #059669;
    margin: 0;
}

.product-rate-minimal {
    font-size: 10px;
    color: #64748b;
    font-weight: 500;
}

.product-rate-minimal::before {
    content: "@₹";
    font-size: 9px;
    margin-right: 1px;
}

/* Action icons container */
.action-icons-minimal {
    display: flex;
    justify-content: space-around;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
}

.action-icon-minimal {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.action-icon-minimal:hover {
    transform: scale(1.1);
}

.action-icon-view {
    color: #3b82f6;
    background: #eff6ff;
    border-color: #bfdbfe;
}

.action-icon-view:hover {
    background: #dbeafe;
    color: #2563eb;
}

.action-icon-edit {
    color: #f59e0b;
    background: #fffbeb;
    border-color: #fed7aa;
}

.action-icon-edit:hover {
    background: #fef3c7;
    color: #d97706;
}

.action-icon-delete {
    color: #ef4444;
    background: #fef2f2;
    border-color: #fecaca;
}

.action-icon-delete:hover {
    background: #fee2e2;
    color: #dc2626;
}

/* --- Professional Export Card Styles --- */
.pro-export-card {
    max-width: 400px;
    background: linear-gradient(135deg, #fdf6ec 0%, #fff9f0 100%);
    border-radius: 24px;
    padding: 24px 18px 18px 18px;
    box-shadow: 0 6px 32px rgba(0,0,0,0.10);
    font-family: 'Inter', sans-serif;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
}
.pro-export-card:before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: url('https://www.transparenttextures.com/patterns/diamond-upholstery.png');
    opacity: 0.07;
    pointer-events: none;
    z-index: 0;
}
.pro-firm-header {
    text-align: center;
    margin-bottom: 10px;
    position: relative;
    z-index: 1;
}
.pro-firm-name {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    font-weight: 700;
    color: #b8860b;
    letter-spacing: 2px;
    text-transform: uppercase;
    display: inline-block;
    margin-bottom: 2px;
}
.pro-image-container {
    width: 100%;
    height: 210px;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 18px;
    background: linear-gradient(135deg, #fffbe6 0%, #f5e7c4 100%);
    box-shadow: 0 2px 12px rgba(184,134,11,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #ffe082;
    position: relative;
    z-index: 1;
}
.pro-image-gold {
    box-shadow: 0 0 0 4px #ffe082, 0 2px 12px rgba(184,134,11,0.12);
}
.pro-image-container img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 16px;
    background: #fff;
}
.pro-product-name {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    font-weight: 700;
    color: #3e2723;
    text-align: center;
    margin-bottom: 14px;
    letter-spacing: 1px;
    text-transform: uppercase;
    position: relative;
    z-index: 1;
}
.pro-specs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 12px;
    background: #fff8e1;
    border-radius: 12px;
    padding: 12px 10px 6px 10px;
    margin-bottom: 14px;
    font-size: 13px;
    position: relative;
    z-index: 1;
}
.pro-spec-label {
    font-size: 11px;
    color: #a1887f;
    font-weight: 500;
    display: block;
}
.pro-spec-value {
    font-size: 14px;
    font-weight: 600;
    color: #222;
    display: block;
}
.pro-price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 18px;
    font-weight: bold;
    color: #b8860b;
    background: linear-gradient(90deg, #fffde7 60%, #fff9c4 100%);
    border-radius: 10px;
    padding: 10px 16px;
    margin-bottom: 16px;
    box-shadow: 0 1px 4px #b8860b11;
    position: relative;
    z-index: 1;
}
.pro-price-label {
    font-size: 15px;
    color: #a1887f;
    font-weight: 600;
}
.pro-price-value {
    font-size: 22px;
    font-weight: 900;
    color: #fff;
    background: linear-gradient(90deg, #ffd700 0%, #b8860b 100%);
    border-radius: 8px;
    padding: 4px 18px;
    box-shadow: 0 2px 8px #b8860b33;
    border: 2px solid #ffe082;
    letter-spacing: 1px;
}
.pro-footer-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 10px;
    gap: 10px;
    position: relative;
    z-index: 1;
}
.pro-date-export {
    font-size: 12px;
    color: #8d6e63;
    font-weight: 500;
    margin-top: 8px;
}
.pro-qr-code {
    width: 72px;
    height: 72px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px #b8860b11;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffe082;
}
.pro-watermark {
    position: absolute;
    bottom: 10px;
    left: 0; right: 0;
    text-align: center;
    font-size: 11px;
    color: #b8860b33;
    font-family: 'Playfair Display', serif;
    letter-spacing: 2px;
    z-index: 0;
    pointer-events: none;
    user-select: none;
}
.tray-badge {
    display: inline-block;
    background: #fffde7;
    color: #b8860b;
    font-size: 11px;
    font-weight: 600;
    border-radius: 6px;
    padding: 2px 8px;
    margin: 2px 0 4px 0;
    letter-spacing: 1px;
}
    </style>
</head>
<body class="bg-gray-50 font-inter overflow-hidden"> <!-- Added overflow-hidden to body to prevent main page scroll when detail is open -->
    <!-- Original Header Design -->
    <header class="header-glass sticky top-0 z-50 shadow-md">
        <div class="px-3 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <!-- Back button -->
                    <button id="backBtn" aria-label="Go back" class="w-9 h-9 gradient-gold rounded-xl flex items-center justify-center shadow-lg floating" onclick="window.location.href = 'home.php';">
                        <i class="fas fa-arrow-left text-yellow-500 text-sm"></i>
                    </button>
                    <div>
                        <h1 class="text-sm font-bold text-gray-800"><?php echo $firmDetails['FirmName']; ?></h1>
                        <p class="text-xs text-gray-600 font-medium">Premium Collection</p>
                    </div>
                </div>
                <!-- User Info and Profile Image -->
                <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p id="headerUserName" class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($userInfo['Name']); ?></p>
                        <p id="headerUserRole" class="text-xs text-purple-600 font-medium"><?php echo htmlspecialchars($userInfo['Role']); ?></p>
                    </div>
                    <a href="profile.php" class="w-9 h-9 gradient-purple rounded-xl flex items-center justify-center shadow-lg overflow-hidden cursor-pointer relative transition-transform duration-200">
                        <?php 
                        $defaultImage = 'public/uploads/user.png';
                        if (!empty($userInfo['image_path']) && file_exists($userInfo['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="User Profile" class="w-full h-full object-cover">
                        <?php elseif (file_exists($defaultImage)): ?>
                            <img src="<?php echo htmlspecialchars($defaultImage); ?>" alt="Default User" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user-circle text-white text-sm"></i> <!-- Changed from user-crown to user-circle as seen in home/billing -->
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="relative overflow-hidden"> <!-- This relative container might need height management if children are all absolute -->
        <!-- Product List View -->
        <div id="productList" class="slide-out active overflow-y-auto" style="height: calc(100vh - 64px);"> <!-- This view will be translated out -->
            <main class="container mx-auto px-4 py-6" role="main">
                <!-- Filter Icon Button -->
                <button id="openFilterModalBtn" class="filter-icon-btn-minimal mb-2" aria-label="Open Filters">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <!-- Filter Modal -->
                <div id="filterModal" class="filter-modal-minimal hidden">
                    <div class="filter-modal-content-minimal">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-semibold text-lg">Filters</span>
                            <button id="closeFilterModalBtn" class="text-gray-500 hover:text-red-500 text-xl">&times;</button>
                        </div>
                        <form id="productFilterBar" class="filter-bar-minimal flex flex-col gap-2 items-stretch">
                            <select id="filterPurity" class="filter-select-minimal">
                                <option value="">All Purity</option>
                            </select>
                            <select id="filterStone" class="filter-select-minimal">
                                <option value="">All Stones</option>
                            </select>
                            <div class="filter-price-group flex items-center gap-1">
                                <span class="filter-label-minimal">₹</span>
                                <input type="range" id="filterPriceMin" min="0" max="1000000" step="100" value="0" class="filter-range-minimal">
                                <input type="range" id="filterPriceMax" min="0" max="1000000" step="100" value="1000000" class="filter-range-minimal">
                                <span id="filterPriceValue" class="filter-label-minimal"></span>
                            </div>
                        </form>
                        <button id="closeFilterModalBtn2" class="mt-3 w-full bg-green-600 text-white py-2 rounded-lg font-semibold">Apply Filters</button>
                    </div>
                </div>
                <!-- Overlay for modal -->
                <div id="filterModalOverlay" class="filter-modal-overlay-minimal hidden"></div>
                <!-- Category Filter Horizontal List -->
                <div id="categoryFilter" class="category-filter-minimal mb-4"></div>
               <div class="product-grid-minimal" id="productGridMinimal">
    <?php foreach ($productsData as $product): ?>
        <div class="product-card-minimal" 
             data-jewelry-type="<?= htmlspecialchars($product['jewelry_type']) ?>" 
             data-purity="<?= htmlspecialchars($product['purity']) ?>" 
             data-stone="<?= htmlspecialchars($product['stone_type']) ?>" 
             data-price="<?= (int)$product['marketPrice'] ?>"
             onclick="showProductDetails('<?= $product['id'] ?>')"
             style="cursor:pointer;">
            <div class="product-image-minimal">
                <img src="<?= !empty($product['images']) && !empty($product['images'][0]) ? $product['images'][0] : 'uploads/jewelry/no_image.png' ?>" 
                     alt="<?= htmlspecialchars($product['name']) ?>"
                     loading="lazy"
                     style="object-fit: contain; background: #f5f5f5;">
                <span class="purity-badge-minimal"><?= htmlspecialchars($product['purity']) ?></span>
                <span class="making-badge-minimal">
                    <?php if ($product['making_charge_type'] == 'percentage'): ?>
                        @<?= rtrim(rtrim(number_format($product['making_charge'], 1), '0'), '.') ?>%
                    <?php else: ?>
                        @₹<?= number_format($product['making_charge'], 0) ?>/g
                    <?php endif; ?>
                </span>
                <span class="favorite-icon-minimal" onclick="event.stopPropagation();">♡</span>
            </div>
            <div class="product-info-minimal">
                <div class="product-title-minimal">
                    <?= htmlspecialchars($product['name']) ?>
                    <span class="ml-1 px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 text-[10px] font-bold align-middle">
                        <?= $product['netWeight'] ?>g
                    </span>
                </div>
        <?php if (!empty($product['tray_no'])): ?>
            <div class="tray-badge"><?= htmlspecialchars($product['tray_no']) ?></div>
        <?php endif; ?>
                <div class="price-rate-container">
                    <div class="price-section">
                        <span class="product-price-minimal">₹<?= number_format(round($product['totalPrice'])) ?></span>
                        <span class="product-rate-minimal"><?= number_format(round($product['rate_per_gram'])) ?>/g</span>
                    </div>
                </div>
                <div class="action-icons-minimal">
                    <div class="action-icon-minimal action-icon-view" onclick="event.stopPropagation(); showProductDetails('<?= $product['id'] ?>')" title="View Details">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="action-icon-minimal action-icon-edit" onclick="event.stopPropagation(); editProduct('<?= $product['id'] ?>')" title="Edit Product">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="action-icon-minimal action-icon-delete" onclick="event.stopPropagation(); deleteProduct('<?= $product['id'] ?>')" title="Delete Product">
                        <i class="fas fa-trash"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
            </main>
        </div>

        <!-- Product Details View - MODIFIED FOR COMPACT UI & FLEX LAYOUT -->
        <div id="productDetails" class="slide-in fixed top-0 left-0 w-full h-screen bg-gradient-to-br from-yellow-50 via-white to-purple-50 z-40">
            <div class="container mx-auto max-w-md px-2 py-2 flex flex-col items-start h-full" style="min-height:100vh;">
                <div class="w-full bg-white rounded-2xl shadow-xl p-0 overflow-y-auto h-[90vh] flex flex-col">
                    <!-- Image Section -->
                    <div class="relative">
                        <div id="imageCarousel" class="flex overflow-x-auto snap-x snap-mandatory rounded-t-2xl">
                            <!-- Images populated by JS -->
                        </div>
                        <div class="absolute bottom-2 right-2 qr-container rounded-md p-1 shadow-lg bg-white">
                            <div id="qrcode" class="w-10 h-10"></div>
                        </div>
                        <div id="carouselDots" class="flex justify-center space-x-1.5 py-1.5"></div>
                    </div>
                    <!-- Product Info -->
                    <div class="p-4 flex-1">
                        <div class="flex items-center justify-between mb-2">
                            <h1 id="detailTitle" class="font-playfair font-bold text-xl text-gray-900"></h1>
                            <button class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 transition-all" id="detailWishlist">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <div class="flex flex-col items-center bg-gray-50 rounded-lg p-2">
                                <span class="text-xs text-gray-500">Gross Wt.</span>
                                <span class="font-semibold text-gray-900 text-sm" id="grossWeight"></span>
                            </div>
                            <div class="flex flex-col items-center bg-gray-50 rounded-lg p-2">
                                <span class="text-xs text-gray-500">Net Wt.</span>
                                <span class="font-semibold text-gray-900 text-sm" id="netWeight"></span>
                            </div>
                            <div class="flex flex-col items-center bg-gray-50 rounded-lg p-2">
                                <span class="text-xs text-gray-500">Purity</span>
                                <span class="font-semibold text-yellow-600 text-sm" id="purity"></span>
                            </div>
                            <div class="flex flex-col items-center bg-gray-50 rounded-lg p-2">
                                <span class="text-xs text-gray-500" id="stoneLabel">Stone Wt.</span>
                                <span class="font-semibold text-gray-900 text-sm" id="stoneWeight"></span>
                            </div>
                        </div>
                        <div class="bg-gradient-to-r from-yellow-50 via-yellow-100 to-yellow-50 p-3 rounded-lg border-l-4 border-yellow-400 mb-4">
                            <div id="priceBreakdown"></div>
                        </div>
                        <div class="rounded-lg p-3 text-white mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="flex items-center mb-1">
                                <i class="fas fa-store mr-1.5 text-xs"></i>
                                <h3 class="font-bold text-sm" id="firmNameDetails"></h3>
                            </div>
                            <p class="text-xs opacity-90 flex items-start mb-0.5" id="firmAddressDetails">
                                <i class="fas fa-map-marker-alt mr-1 mt-0.5 shrink-0"></i>
                                <span class="truncate-2-lines"></span>
                            </p>
                            <p class="text-xs opacity-90 flex items-center" id="firmPhoneDetails">
                                <i class="fas fa-phone mr-1 shrink-0"></i>
                                <span class="truncate"></span>
                            </p>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="flex flex-col items-center py-1.5 px-1 bg-gray-50 rounded-lg text-center">
                                <i class="fas fa-shipping-fast text-green-600 mb-0.5 text-sm"></i>
                                <span class="text-xs font-medium leading-tight">Free Ship</span>
                            </div>
                            <div class="flex flex-col items-center py-1.5 px-1 bg-gray-50 rounded-lg text-center">
                                <i class="fas fa-certificate text-blue-600 mb-0.5 text-sm"></i>
                                <span class="text-xs font-medium leading-tight">Certified</span>
                            </div>
                            <div class="flex flex-col items-center py-1.5 px-1 bg-gray-50 rounded-lg text-center">
                                <i class="fas fa-exchange-alt text-orange-600 mb-0.5 text-sm"></i>
                                <span class="text-xs font-medium leading-tight">Return</span>
                            </div>
                        </div>
                    </div>
                    <!-- Action Buttons -->
                    <div class="grid grid-cols-2 gap-3 p-4 border-t sticky bottom-0 bg-white z-10">
                        <button id="addToCartBtn" class="bg-blue-600 text-white py-2.5 px-4 rounded-lg font-semibold flex items-center justify-center text-sm active:bg-blue-700 transition-colors">
                            <i class="fas fa-shopping-cart mr-2"></i>Add to Cart
                        </button>
                        <button id="generateCatalogBtn" class="bg-purple-600 text-white py-2.5 px-4 rounded-lg font-semibold flex items-center justify-center text-sm active:bg-purple-700 transition-colors">
                            <i class="fas fa-file-pdf mr-2"></i>Generate
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

   
    



    
    <!-- Original Catalog Export Container -->
    <div id="catalog-card-export-container" aria-hidden="true">
        <div id="catalog-card-export" class="pro-export-card">
            <div class="pro-firm-header">
                <span id="export-card-company-name" class="pro-firm-name">KRISHNA JEWELLERS</span>
            </div>
            <div class="pro-image-container pro-image-gold">
                <img id="export-card-image" src="/placeholder.svg" alt="Product Image for Export">
            </div>
            <div id="export-card-product-name" class="pro-product-name">ELEGANT FLORAL RING</div>
            <div class="pro-specs-grid">
                <div>
                    <span class="pro-spec-label">Gold Purity</span>
                    <span id="export-card-purity" class="pro-spec-value">22K</span>
                </div>
                <div>
                    <span class="pro-spec-label">Weight</span>
                    <span id="export-card-weight" class="pro-spec-value">5.60g</span>
                </div>
                <div>
                    <span class="pro-spec-label">Stone</span>
                    <span id="export-card-stone" class="pro-spec-value">N/A</span>
                </div>
            </div>
            <div class="pro-price-row">
                <span class="pro-price-label">Price</span>
                <span id="export-card-price" class="pro-price-value">₹33,960</span>
            </div>
            <div class="pro-footer-row">
                <span id="export-card-date" class="pro-date-export">31 May 2025</span>
                <div id="export-card-qr-code" class="pro-qr-code"></div>
            </div>
            <div class="pro-watermark">Jewellers Wala - Premium Gold Collection</div>
        </div>
    </div>

    <!-- Crop Modal -->
    

    <script>
        let currentProductId = null;

        // Embed PHP data as JavaScript variables
        const products = <?php echo json_encode($productsData); ?>;
        const priceConfig = <?php echo json_encode($priceConfig); ?>;
        const firmDetails = <?php echo json_encode($firmDetails); ?>;

        let cartCount = 0;
        let wishlistCount = 0;
        let wishlistedItems = new Set();

        // Function to render the product list
        function renderProductList(productsToRender) {
            const productGrid = document.querySelector('#productList .product-grid');
            if (!productGrid) return;

            productGrid.innerHTML = ''; // Clear existing products

            for (const productId in productsToRender) {
                const product = productsToRender[productId];
                const isWishlisted = wishlistedItems.has(String(productId));
                const productCardHTML = `
                    <div class="product-card">
                        <div class="product-image">
                            <img src="${product.images && product.images.length > 0 ? product.images[0] : 'uploads/jewelry/no_image.png'}" alt="Jewellery Image">
                            <span class="category-badge">${product.purity}</span>
                        </div>
                        <div class="product-info">
                            <div class="product-title">${product.name}</div>
                            <div class="product-weight">${product.netWeight}g</div>
                            <div class="product-price">₹${product.marketPrice.toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                        </div>
                    </div>
                `;
                productGrid.insertAdjacentHTML('beforeend', productCardHTML);
            }
        }

        function showProductDetails(productId) {
            currentProductId = String(productId); // Ensure string for consistency
            const product = products[currentProductId];
            
            if (!product) {
                showToast('Product not found', 'error');
                return;
            }
            
            document.getElementById('detailTitle').textContent = product.name;
            
            document.getElementById('grossWeight').textContent = product.grossWeight + 'g';
            document.getElementById('netWeight').textContent = product.netWeight + 'g';
            document.getElementById('purity').textContent = product.purity;
            
            const stoneLabelEl = document.getElementById('stoneLabel');
            const stoneWeightEl = document.getElementById('stoneWeight');
            if (product.stoneWeight) {
                stoneLabelEl.textContent = 'Stone Wt.';
                stoneWeightEl.textContent = product.stoneWeight;
            } else {
                stoneLabelEl.textContent = 'HUID';
                stoneWeightEl.textContent = product.huid_code || 'N/A';
            }

            const priceContainer = document.getElementById('priceBreakdown');
            priceContainer.innerHTML = `
                <div class="flex justify-between text-xs">
                    <span class="text-gray-700">Gold Value:</span>
                    <span class="font-semibold text-gray-800">₹${product.marketPrice.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-gray-700">Making Charges:</span>
                    <span class="font-semibold text-gray-800">₹${product.makingCharges.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
                <div class="flex justify-between text-sm font-bold border-t pt-1 mt-1">
                    <span class="text-gray-900">Total Price:</span>
                    <span class="gold-gradient">₹${product.totalPrice.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
            `;
            
            if (product.images && product.images.length > 0) {
                updateImageCarousel(product.images);
            } else {
                updateImageCarousel(['https://via.placeholder.com/400x300?text=No+Image+Available']);
            }
            
            generateQRCode(currentProductId);
            updateWishlistButton(currentProductId);

            const firmNameEl = document.getElementById('firmNameDetails');
            const firmAddressSpan = document.getElementById('firmAddressDetails')?.querySelector('span');
            const firmPhoneSpan = document.getElementById('firmPhoneDetails')?.querySelector('span');

            if (firmDetails) {
                if (firmNameEl) firmNameEl.textContent = firmDetails.FirmName || 'N/A';
                
                let fullAddress = [firmDetails.address1, firmDetails.address2, firmDetails.city, firmDetails.state, firmDetails.pincode]
                            .filter(Boolean).join(', ');
                if (firmAddressSpan) firmAddressSpan.textContent = fullAddress || 'Address not available';
                
                if (firmPhoneSpan) firmPhoneSpan.textContent = firmDetails.phone_number || 'Phone not available';
            } else {
                if (firmNameEl) firmNameEl.textContent = 'Firm details not available';
                if (firmAddressSpan) firmAddressSpan.textContent = '';
                if (firmPhoneSpan) firmPhoneSpan.textContent = '';
            }
            
            showDetailsView();

            // Update back button to go to list
            const backBtn = document.getElementById('backBtn');
            if (backBtn) {
                backBtn.onclick = showProductList;
                // backBtn.classList.remove('hidden'); // Button is always visible now
            }
        }

        function updateImageCarousel(images) {
            const carousel = document.getElementById('imageCarousel');
            const dots = document.getElementById('carouselDots');
            
            carousel.innerHTML = '';
            dots.innerHTML = '';
            
            images.forEach((image, index) => {
                const slide = document.createElement('div');
                slide.className = 'flex-none w-full snap-center';
                slide.setAttribute('role', 'group');
                slide.setAttribute('aria-roledescription', 'slide');
                slide.setAttribute('aria-label', `Image ${index + 1} of ${images.length}`);
                slide.innerHTML = `
                    <img src="${image}" alt="Product Image ${index + 1}" 
                         class="w-full h-56 object-cover"> <!-- MODIFIED: h-56 -->
                `;
                carousel.appendChild(slide);
                
                const dot = document.createElement('button');
                dot.className = `w-2 h-2 rounded-full transition-all ${index === 0 ? 'bg-blue-600' : 'bg-gray-300'}`;
                dot.setAttribute('aria-label', `Go to image ${index + 1}`);
                dot.onclick = () => scrollToImage(index);
                dots.appendChild(dot);
            });
            if (images.length > 0) {
                setTimeout(() => scrollToImage(0), 0);
            }
        }
        
        function scrollToImage(index) {
            const carousel = document.getElementById('imageCarousel');
            if (!carousel || !carousel.children.length || !carousel.children[index]) return;
            const imageWidth = carousel.children[0].offsetWidth;
            carousel.scrollTo({ left: imageWidth * index, behavior: 'smooth' });
            
            const dotsContainer = document.getElementById('carouselDots');
            const dots = dotsContainer.querySelectorAll('button');
            dots.forEach((dot, i) => {
                dot.className = `w-2 h-2 rounded-full transition-all ${i === index ? 'bg-blue-600' : 'bg-gray-300'}`;
                dot.setAttribute('aria-current', i === index ? 'true' : 'false');
            });
        }
        
        function generateQRCode(productId) {
            const qrContainer = document.getElementById('qrcode');
            if (!qrContainer) return;
            
            qrContainer.innerHTML = '';
            
            const productUrl = `${window.location.origin}/product.php?id=${productId}`;
            try {
                if (typeof QRCode !== 'undefined') {
                    new QRCode(qrContainer, {
                        text: productUrl,
                        width: 40, 
                        height: 40,
                        margin: 0,
                        colorDark: '#000000',
                        colorLight: '#FFFFFF'
                    });
                } else {
                    console.error('QRCode library is not loaded.');
                    qrContainer.innerHTML = '<div class="text-red-500 text-xs text-center">QR Fail</div>';
                    showToast('QR Code library failed to load.', 'error', 5000);
                }
            } catch (error) {
                console.error('QR Code generation error:', error);
                qrContainer.innerHTML = '<div class="text-red-500 text-xs">QR Err</div>';
            }
        }

        function showDetailsView() {
            document.getElementById('productList').classList.remove('active');
            document.getElementById('productDetails').classList.add('active');
            document.body.style.overflow = 'hidden'; 
        }

        function showProductList() {
            document.getElementById('productDetails').classList.remove('active');
            document.getElementById('productList').classList.add('active');
            // Update back button to go to home
            const backBtn = document.getElementById('backBtn');
            if (backBtn) {
                backBtn.onclick = () => { window.location.href = 'home.php'; };
                // backBtn.classList.remove('hidden'); // Button is always visible now
            }
        }
        
        function toggleWishlist(button, productId) {
            const productIdStr = String(productId);
            event.stopPropagation(); 
            const icon = button.querySelector('i');
            let currentlyWishlisted = wishlistedItems.has(productIdStr);
            
            if (currentlyWishlisted) {
                wishlistedItems.delete(productIdStr);
                wishlistCount--;
                showToast('Removed from wishlist!');
            } else {
                wishlistedItems.add(productIdStr);
                wishlistCount++;
                showToast('Added to wishlist!');
            }
            
            const isNowWishlisted = wishlistedItems.has(productIdStr);
            button.classList.toggle('text-red-500', isNowWishlisted);
            button.setAttribute('aria-pressed', isNowWishlisted);
            if (icon) icon.className = `${isNowWishlisted ? 'fas' : 'far'} fa-heart text-xs`;
            
            document.getElementById('wishlist-count').textContent = wishlistCount;
            
            // Sync with the other button if it exists
            if (button.closest('#productDetails')) { // Clicked on detail page
                const cardButton = document.querySelector(`.product-card button[onclick*="'${productIdStr}'"]`);
                if (cardButton) {
                    cardButton.classList.toggle('text-red-500', isNowWishlisted);
                    cardButton.setAttribute('aria-pressed', isNowWishlisted);
                    const cardIcon = cardButton.querySelector('i');
                    if (cardIcon) cardIcon.className = `${isNowWishlisted ? 'fas' : 'far'} fa-heart text-xs`;
                }
            } else { // Clicked on product card
                if (currentProductId === productIdStr) {
                    updateWishlistButton(productIdStr);
                }
            }
        }
        
        function updateWishlistButton(productId) { // Only for detail view button
            const productIdStr = String(productId);
            const button = document.getElementById('detailWishlist');
            if(!button) return;
            const icon = button.querySelector('i');
            const isWishlisted = wishlistedItems.has(productIdStr);
            
            icon.className = isWishlisted ? 'fas fa-heart' : 'far fa-heart';
            button.classList.toggle('text-red-500', isWishlisted);
            button.setAttribute('aria-pressed', isWishlisted);
            
            // Ensure this button's click calls toggleWishlist correctly
            button.onclick = (event) => {
                event.stopPropagation();
                toggleWishlist(button, productIdStr);
            };
        }


        function addToCart() {
            if (currentProductId) {
                cartCount++;
                document.getElementById('cart-count').textContent = cartCount;
                showToast('Added to cart successfully!');
            }
        }

        function formatDateForCard() {
            const date = new Date();
            return date.toLocaleDateString('en-IN', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            });
        }

        // Add Toast Notification System
        function showToast(message, type = 'info', duration = 3000) {
            // Remove any existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());

            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast fixed bottom-4 right-4 px-4 py-2 rounded-lg text-white shadow-lg z-50 transform transition-all duration-300 translate-y-0 opacity-100 ${type ? 'toast-' + type : ''}`;
            toast.textContent = message;

            // Add to document
            document.body.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.style.transform = 'translateY(0)';
                toast.style.opacity = '1';
            }, 10);

            // Remove after duration
            setTimeout(() => {
                toast.style.transform = 'translateY(100%)';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        function generateCatalog() {
            if (!currentProductId) {
                showToast('No product selected', 'error');
                return;
            }

            // Check if html2canvas is loaded
            if (typeof html2canvas === 'undefined') {
                showToast('html2canvas library not loaded. Please refresh the page.', 'error');
                console.error('html2canvas is not defined');
                return;
            }

            const product = products[currentProductId];
            if (!product) {
                showToast('Product data not found', 'error');
                return;
            }

            showToast(`Generating catalog for ${product.name}...`, 'info');

            const exportImage = document.getElementById('export-card-image');
            const fallbackImage = 'uploads/jewelry/no_image.png';
            let imageToUse = product.images && product.images[0] ? product.images[0] : fallbackImage;
            let imageLoaded = false;

            // Set up the rest of the export card
            document.getElementById('export-card-product-name').textContent = product.name;
            document.getElementById('export-card-purity').textContent = product.purity;
            let netWeightFormatted = String(product.netWeight); 
            let baseWeight = netWeightFormatted.replace(/g$/i, '');
            let subGram = "00";
            const dotIndex = baseWeight.indexOf('.');
            if (dotIndex !== -1) {
                const decimalPart = baseWeight.substring(dotIndex + 1);
                subGram = (decimalPart + "00").substring(0, 2);
                baseWeight = baseWeight.substring(0, dotIndex);
            }
            document.getElementById('export-card-weight').textContent = `${baseWeight}.${subGram}g`;
            document.getElementById('export-card-stone').textContent = product.stoneWeight ? product.stoneWeight : 'N/A';
            const totalPrice = Math.round(product.totalPrice);
            document.getElementById('export-card-price').textContent = `₹${totalPrice.toLocaleString()}`;
            document.getElementById('export-card-company-name').textContent = firmDetails.FirmName || 'Your Company Name';
            document.getElementById('export-card-date').textContent = formatDateForCard();

            // Generate QR Code
            const qrCodeContainer = document.getElementById('export-card-qr-code');
            qrCodeContainer.innerHTML = '';
            const productUrl = `${window.location.origin}/product.php?id=${currentProductId}`;
            try {
                if (typeof QRCode !== 'undefined') {
                    new QRCode(qrCodeContainer, {
                        text: productUrl,
                        width: 52,
                        height: 52,
                        colorDark: "#5D4037",
                        colorLight: "#FDF5EC",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                } else {
                    console.error('QRCode library is not loaded.');
                    qrCodeContainer.innerHTML = '<div class="text-red-500 text-xs text-center">QR Fail</div>';
                    showToast('QR Code library failed to load.', 'error', 5000);
                }
            } catch (error) {
                console.error('QR Code generation error:', error);
                qrCodeContainer.innerHTML = '<div class="text-red-500 text-xs">QR Err</div>';
            }

            const elementToCapture = document.getElementById('catalog-card-export');
            const container = document.getElementById('catalog-card-export-container');
            
            // Make sure the container is visible for capture
            container.style.position = 'absolute';
            container.style.left = '0';
            container.style.top = '0';
            container.style.zIndex = '2000';
            container.style.visibility = 'visible';
            container.style.opacity = '1';

            // Wait for the export image to load before capturing
            exportImage.onload = function() {
                if (imageLoaded) return; // Prevent double firing
                imageLoaded = true;
                
                console.log('Starting html2canvas capture...');
                html2canvas(elementToCapture, {
                    allowTaint: true,
                    useCORS: true,
                    scale: 3,
                    backgroundColor: null,
                    logging: true, // Enable logging
                    onclone: function(clonedDoc) {
                        console.log('Document cloned successfully');
                    }
                }).then(canvas => {
                    console.log('Canvas generated successfully');
                    container.style.left = '-9999px';
                    container.style.top = '-9999px';
                    container.style.zIndex = '-1';
                    
                    canvas.toBlob(function(blob) {
                        if (!blob) {
                            console.error('Failed to create blob from canvas');
                            showToast('Failed to generate image', 'error');
                            return;
                        }
                        
                        const fileName = `${product.name.replace(/\s+/g, '-')}-catalog.png`;
                        const newFile = new File([blob], fileName, { type: "image/png" });
                        const shareText = `Check out: ${product.name} from ${firmDetails.FirmName || 'Our Store'}! ₹${totalPrice.toLocaleString()}`;
                        
                        if (navigator.share && navigator.canShare && navigator.canShare({ files: [newFile] })) {
                            navigator.share({
                                files: [newFile],
                                title: `${product.name} - Catalog`,
                                text: shareText
                            })
                            .then(() => showToast('Catalog shared successfully! Perfect for WhatsApp status!', 'success', 5000))
                            .catch((error) => {
                                if (error.name !== 'AbortError') {
                                    console.error('Error sharing:', error);
                                    showToast('Share failed. Downloading catalog...', 'info', 3000);
                                    downloadCanvasAsImage(canvas, fileName);
                                } else {
                                    showToast('Share cancelled by user.', 'warning');
                                }
                            });
                        } else {
                            showToast('Share not supported. Downloading catalog...', 'info', 3000);
                            downloadCanvasAsImage(canvas, fileName);
                        }
                    }, 'image/png', 1.0);
                }).catch(err => {
                    console.error("Error generating catalog image:", err);
                    container.style.left = '-9999px';
                    container.style.top = '-9999px';
                    container.style.zIndex = '-1';
                    showToast('Could not generate catalog image. Please try again.', 'error');
                });
            };

            exportImage.onerror = function() {
                console.error('Failed to load export image:', imageToUse);
                if (imageToUse !== fallbackImage) {
                    console.log('Trying fallback image...');
                    exportImage.src = fallbackImage;
                } else {
                    showToast('Failed to load export image.', 'error');
                    container.style.left = '-9999px';
                    container.style.top = '-9999px';
                    container.style.zIndex = '-1';
                }
            };

            console.log('Setting export image source:', imageToUse);
            exportImage.src = imageToUse;
        }

        function downloadCanvasAsImage(canvas, fileName) {
            const image = canvas.toDataURL("image/png", 1.0);
            const link = document.createElement('a');
            link.download = fileName;
            link.href = image;
            link.click();
        }

        // Event listeners
        document.getElementById('addToCartBtn').addEventListener('click', addToCart);
        document.getElementById('generateCatalogBtn').addEventListener('click', generateCatalog);

        document.addEventListener('DOMContentLoaded', function() {
            const header = document.querySelector('header');
            const productDetailsView = document.getElementById('productDetails');
            if (header && productDetailsView) {
                const headerHeight = header.offsetHeight;
                productDetailsView.style.paddingTop = `${headerHeight}px`;
                productDetailsView.style.height = '100vh'; // Occupy full viewport height, padding pushes content down
            }

            renderProductList(products);
        
            const carousel = document.getElementById('imageCarousel');
            if (carousel) {
                carousel.addEventListener('scroll', function() {
                    if (!this.children.length) return;
                    const scrollLeft = this.scrollLeft;
                    const imageWidth = this.children[0].offsetWidth;
                    if (imageWidth === 0) return; 
                    const currentIndex = Math.round(scrollLeft / imageWidth);
                    
                    const dotsContainer = document.getElementById('carouselDots');
                    const dots = dotsContainer.querySelectorAll('button');
                    dots.forEach((dot, i) => {
                        dot.className = `w-2 h-2 rounded-full transition-all ${i === currentIndex ? 'bg-blue-600' : 'bg-gray-300'}`;
                        dot.setAttribute('aria-current', i === currentIndex ? 'true' : 'false');
                    });
                });
            }

            // Initialize back button on page load to go to home.php
            const backBtn = document.getElementById('backBtn');
            if (backBtn) {
                 backBtn.onclick = () => { window.location.href = 'home.php'; };
                 // backBtn.classList.remove('hidden'); // Button is always visible now
            }

            // --- Category Filter Logic ---
            const productsArr = Object.values(products);
            const categorySet = new Set(productsArr.map(p => p.jewelry_type).filter(Boolean));
            const categories = ['All', ...Array.from(categorySet)];
            const categoryFilter = document.getElementById('categoryFilter');
            const productGrid = document.getElementById('productGridMinimal');

            // Render category buttons
            categoryFilter.innerHTML = '';
            categories.forEach(cat => {
                const btn = document.createElement('button');
                btn.className = 'category-filter-btn' + (cat === 'All' ? ' active' : '');
                btn.textContent = cat;
                btn.onclick = function() {
                    document.querySelectorAll('.category-filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    filterProductsByCategory(cat);
                };
                categoryFilter.appendChild(btn);
            });

            function filterProductsByCategory(category) {
                document.querySelectorAll('.product-card-minimal').forEach(card => {
                    const type = card.getAttribute('data-jewelry-type');
                    if (category === 'All' || type === category) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }

            // --- Filter Bar Logic ---
            const puritySet = new Set(productsArr.map(p => p.purity).filter(Boolean));
            const filterPurity = document.getElementById('filterPurity');
            puritySet.forEach(purity => {
                const opt = document.createElement('option');
                opt.value = purity;
                opt.textContent = purity;
                filterPurity.appendChild(opt);
            });
            // Populate Stone dropdown
            const stoneSet = new Set(productsArr.map(p => p.stone_type ? (p.stone_type.toLowerCase() === 'diamond' ? 'Diamond Jewellery' : p.stone_type) : '').filter(Boolean));
            const filterStone = document.getElementById('filterStone');
            stoneSet.forEach(stone => {
                const opt = document.createElement('option');
                opt.value = stone;
                opt.textContent = stone;
                filterStone.appendChild(opt);
            });
            // Price Range
            const priceMin = Math.min(...productsArr.map(p => p.marketPrice));
            const priceMax = Math.max(...productsArr.map(p => p.marketPrice));
            const filterPriceMin = document.getElementById('filterPriceMin');
            const filterPriceMax = document.getElementById('filterPriceMax');
            filterPriceMin.min = priceMin;
            filterPriceMin.max = priceMax;
            filterPriceMax.min = priceMin;
            filterPriceMax.max = priceMax;
            filterPriceMin.value = priceMin;
            filterPriceMax.value = priceMax;
            const filterPriceValue = document.getElementById('filterPriceValue');
            function updatePriceLabel() {
                filterPriceValue.textContent = `${Number(filterPriceMin.value).toLocaleString()} - ${Number(filterPriceMax.value).toLocaleString()}`;
            }
            updatePriceLabel();
            [filterPurity, filterStone, filterPriceMin, filterPriceMax].forEach(el => {
                el.addEventListener('change', filterProductsAjax);
                el.addEventListener('input', filterProductsAjax);
            });
            function filterProductsAjax() {
                const purity = filterPurity.value;
                const stone = filterStone.value;
                const minPrice = Number(filterPriceMin.value);
                const maxPrice = Number(filterPriceMax.value);
                updatePriceLabel();
                document.querySelectorAll('.product-card-minimal').forEach(card => {
                    const cardPurity = card.getAttribute('data-purity');
                    let cardStone = card.getAttribute('data-stone');
                    cardStone = cardStone && cardStone.toLowerCase() === 'diamond' ? 'Diamond Jewellery' : cardStone;
                    const cardPrice = Number(card.getAttribute('data-price'));
                    let show = true;
                    if (purity && cardPurity !== purity) show = false;
                    if (stone && cardStone !== stone) show = false;
                    if (cardPrice < minPrice || cardPrice > maxPrice) show = false;
                    card.style.display = show ? '' : 'none';
                });
            }

            // --- Filter Modal Logic ---
            const openFilterModalBtn = document.getElementById('openFilterModalBtn');
            const filterModal = document.getElementById('filterModal');
            const filterModalOverlay = document.getElementById('filterModalOverlay');
            const closeFilterModalBtn = document.getElementById('closeFilterModalBtn');
            const closeFilterModalBtn2 = document.getElementById('closeFilterModalBtn2');
            function openFilterModal() {
                filterModal.classList.remove('hidden');
                filterModalOverlay.classList.remove('hidden');
            }
            function closeFilterModal() {
                filterModal.classList.add('hidden');
                filterModalOverlay.classList.add('hidden');
            }
            openFilterModalBtn.addEventListener('click', openFilterModal);
            closeFilterModalBtn.addEventListener('click', closeFilterModal);
            closeFilterModalBtn2.addEventListener('click', function(e) { e.preventDefault(); closeFilterModal(); });
            filterModalOverlay.addEventListener('click', closeFilterModal);
        });

        // --- Cropper.js Integration for Image Preview ---

        let cropper = null;
        let currentCropImgContainer = null;

        // Open crop modal when clicking on a preview image
        document.addEventListener('click', function (e) {
          // Only trigger if clicking on a preview image inside imagePreview
          if (e.target.tagName === 'IMG' && e.target.closest('#imagePreview')) {
            e.preventDefault();
            const img = e.target;
            currentCropImgContainer = img.parentElement; // The .relative container

            // Set image in crop modal
            const cropImage = document.getElementById('cropImage');
            cropImage.src = img.src;

            // Show modal
            document.getElementById('cropModal').classList.remove('hidden');

            // Destroy previous cropper if any
            if (cropper) {
              cropper.destroy();
              cropper = null;
            }

            // Wait for image to load before initializing cropper
            cropImage.onload = function () {
              cropper = new Cropper(cropImage, {
                viewMode: 0, // fully free crop
                dragMode: 'crop',
                autoCropArea: 1,
                movable: true,
                zoomable: true,
                rotatable: true,
                scalable: true,
                responsive: true,
                background: false,
                modal: true,
                guides: true,
                highlight: true,
                cropBoxMovable: true,
                cropBoxResizable: true,
                aspectRatio: NaN // free aspect ratio
              });
            };
          }
        });

        // Close crop modal
        document.getElementById('closeCropModal').addEventListener('click', closeCropModal);
        document.getElementById('cropCancelBtn').addEventListener('click', closeCropModal);

        function closeCropModal() {
          document.getElementById('cropModal').classList.add('hidden');
          if (cropper) {
            cropper.destroy();
            cropper = null;
          }
          currentCropImgContainer = null;
        }

        // Apply crop and update preview
        document.getElementById('cropApplyBtn').addEventListener('click', function () {
          if (cropper && currentCropImgContainer) {
            const canvas = cropper.getCroppedCanvas({
              // You can set maxWidth/maxHeight if you want to limit output size
              // maxWidth: 800,
              // maxHeight: 800,
              imageSmoothingQuality: 'high'
            });
            const croppedDataUrl = canvas.toDataURL('image/jpeg', 0.95);

            // Update the preview image
            const img = currentCropImgContainer.querySelector('img');
            img.src = croppedDataUrl;

            // If this is a captured image, also update the hidden field
            if (currentCropImgContainer.querySelector('.fa-camera')) {
              const capturedImageField = document.getElementById('capturedImage');
              if (capturedImageField) {
                capturedImageField.value = croppedDataUrl;
              }
            }

            closeCropModal();
          }
        });

        // Product Action Functions
        function editProduct(productId) {
            // Redirect to edit page or open edit modal
            showToast('Edit functionality coming soon!', 'info');
            // You can implement this to redirect to an edit page:
            // window.location.href = `edit_product.php?id=${productId}`;
        }

        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                showToast('Delete functionality coming soon!', 'warning');
                // You can implement this to make an AJAX call to delete the product:
                // fetch(`api/delete_product.php?id=${productId}`, {
                //     method: 'DELETE'
                // }).then(response => response.json())
                // .then(data => {
                //     if (data.success) {
                //         showToast('Product deleted successfully!', 'success');
                //         // Remove the product card from DOM
                //         document.querySelector(`[data-product-id="${productId}"]`).remove();
                //     } else {
                //         showToast('Failed to delete product', 'error');
                //     }
                // });
            }
        }
    </script>
</body>
</html>
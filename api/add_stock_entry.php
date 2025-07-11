<?php
// Prevent PHP errors from breaking JSON responses
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Handle AJAX form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Validate required fields
    $required_fields = ['material_type', 'stock_name', 'purity', 'weight', 'rate', 'cost_price_per_gram', 'total_taxable_amount', 'final_amount'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit();
        }
    }
    
    // Extract form data
    $entryType = $_POST['entry_type'] ?? 'opening_stock';
    $isPurchase = ($entryType === 'purchase');
    $materialType = $_POST['material_type'];
    $stockName = $_POST['stock_name'];
    $purity = floatval($_POST['purity']);
    $unit = $_POST['unit_measurement'];
    $weight = floatval($_POST['weight']);
    $quantity = isset($_POST['quantity']) && is_numeric($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $rate = floatval($_POST['rate']);
    $makingCharges = floatval($_POST['making_charges'] ?? 0);
    $costPricePerGram = floatval($_POST['cost_price_per_gram']);
    $totalTaxableAmount = floatval($_POST['total_taxable_amount']);
    $finalAmount = floatval($_POST['final_amount']);
    $gst = isset($_POST['gst']) && $_POST['gst'] === 'true' ? 1 : 0;
    $sourceType = $entryType === 'purchase' ? 'purchase' : 'opening_stock';
    $sourceId = $entryType === 'purchase' ? (int)$_POST['supplier_id'] : 0;
    $customSourceInfo = $_POST['custom_source_info'] ?? '';
    $supplierId = $_POST['supplier_id'] ?? null;
    $invoiceNumber = $_POST['invoice_number'] ?? '';
    $invoiceDate = $_POST['invoice_date'] ?? date('Y-m-d');
    $paidAmount = floatval($_POST['paid_amount'] ?? 0);
    $paymentStatus = $_POST['payment_status'] ?? 'unpaid';
    $paymentMode = $_POST['payment_mode'] ?? '';
    $transactionRef = $_POST['transaction_ref'] ?? '';
    $hsnCode = $_POST['hsn_code'] ?? '';
    
    $debugLog = [];
    
    try {
        $conn->begin_transaction();
        
        // Check if inventory already exists
        $stmt = $conn->prepare("SELECT inventory_id, current_stock, total_cost FROM inventory_metals WHERE material_type = ? AND stock_name = ? AND purity = ? AND unit_measurement = ? AND firm_id = ?");
        $stmt->bind_param("ssdsi", $materialType, $stockName, $purity, $unit, $firm_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        
        if ($existing) {
            // Update existing inventory
            $newStock = $existing['current_stock'] + $weight;
            $newTotalCost = $existing['total_cost'] + $finalAmount;
            $newCostPerUnit = $newTotalCost / $newStock;
            
            $stmt = $conn->prepare("UPDATE inventory_metals SET current_stock = ?, remaining_stock = ?, cost_price_per_gram = ?, total_cost = ?, last_updated = NOW() WHERE inventory_id = ?");
            $stmt->bind_param("dddsi", $newStock, $newStock, $newCostPerUnit, $newTotalCost, $existing['inventory_id']);
            $stmt->execute();
            $inventoryId = $existing['inventory_id'];
            $debugLog[] = "Updated existing inventory ID $inventoryId";
            // --- STOCK LOG for update ---
            $logStmt = $conn->prepare("INSERT INTO jewellery_stock_log (firm_id, inventory_id, material_type, stock_name, purity, transaction_type, quantity_before, quantity_change, quantity_after, reference_type, reference_id, transaction_date, user_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $log_transaction_type = 'IN';
            $log_reference_type = $isPurchase ? 'Supplier' : 'Owner';
            $log_reference_id = $isPurchase ? (string)$supplierId : '0';
            $log_notes = $customSourceInfo;
            $log_quantity_before = $existing['current_stock'];
            $log_quantity_change = $weight;
            $log_quantity_after = $newStock;
            $log_transaction_date = $invoiceDate;
            $logStmt->bind_param(
                "iisssdiddssiss",
                $firm_id,
                $inventoryId,
                $materialType,
                $stockName,
                $purity,
                $log_transaction_type,
                $log_quantity_before,
                $log_quantity_change,
                $log_quantity_after,
                $log_reference_type,
                $log_reference_id,
                $log_transaction_date,
                $user_id,
                $log_notes
            );
            $logStmt->execute();
        } else {
            // Insert new inventory
            $stmt = $conn->prepare("INSERT INTO inventory_metals (material_type, stock_name, purity, current_stock, remaining_stock, cost_price_per_gram, unit_measurement, total_cost, source_type, minimum_stock_level, created_at, updated_at, firm_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
            $minStock = 10;
            $stmt->bind_param("ssdddsdsdii", $materialType, $stockName, $purity, $weight, $weight, $costPricePerGram, $unit, $finalAmount, $sourceType, $minStock, $firm_id);
            $stmt->execute();
            $inventoryId = $conn->insert_id;
            $debugLog[] = "Inserted new inventory ID $inventoryId";
            // --- STOCK LOG for insert ---
            $logStmt = $conn->prepare("INSERT INTO jewellery_stock_log (firm_id, inventory_id, material_type, stock_name, purity, transaction_type, quantity_before, quantity_change, quantity_after, reference_type, reference_id, transaction_date, user_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $log_transaction_type = 'IN';
            $log_reference_type = $isPurchase ? 'Supplier' : 'Owner';
            $log_reference_id = $isPurchase ? (string)$supplierId : '0';
            $log_notes = $customSourceInfo;
            $log_quantity_before = 0;
            $log_quantity_change = $weight;
            $log_quantity_after = $weight;
            $log_transaction_date = $invoiceDate;
            $logStmt->bind_param(
                "iisssdiddssiss",
                $firm_id,
                $inventoryId,
                $materialType,
                $stockName,
                $purity,
                $log_transaction_type,
                $log_quantity_before,
                $log_quantity_change,
                $log_quantity_after,
                $log_reference_type,
                $log_reference_id,
                $log_transaction_date,
                $user_id,
                $log_notes
            );
            $logStmt->execute();
        }
        
        // Insert into metal_purchases for both opening_stock and purchase
        $entryTypeStr = $isPurchase ? 'purchase' : 'opening_stock';
        $purchaseSourceType = $isPurchase ? 'Supplier' : 'Owner'; // Only Supplier/Customer/Owner allowed in enum
        $purchaseSourceId = $isPurchase ? (int)$supplierId : 0;
        $purchaseDate = $invoiceDate;
        $purchaseMaterialType = $materialType;
        $purchaseStockName = $stockName;
        $purchasePurity = $purity;
        $purchaseQuantity = $quantity; // Use quantity (pcs) for metal_purchases
        $purchaseRatePerGram = $rate;
        $purchaseTotalAmount = $finalAmount;
        $purchaseTransactionRef = $transactionRef;
        $purchasePaymentStatus = $isPurchase ? $paymentStatus : 'Unpaid';
        $purchaseInventoryId = $inventoryId;
        $purchaseFirmId = $firm_id;
        $purchaseWeight = $weight;
        $purchasePaidAmount = $isPurchase ? (int)$paidAmount : 0;
        $purchasePaymentMode = $isPurchase ? $paymentMode : '';
        $purchaseInvoiceNumber = $invoiceNumber;
        
        $stmt = $conn->prepare("INSERT INTO metal_purchases (source_type, source_id, purchase_date, material_type, stock_name, purity, quantity, rate_per_gram, total_amount, transaction_reference, payment_status, inventory_id, created_at, updated_at, firm_id, weight, paid_amount, payment_mode, invoice_number, entry_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sisssdddssiiidisss",
            $purchaseSourceType,      // s
            $purchaseSourceId,        // i
            $purchaseDate,            // s
            $purchaseMaterialType,    // s
            $purchaseStockName,       // s
            $purchasePurity,          // d
            $purchaseQuantity,        // d
            $purchaseRatePerGram,     // d
            $purchaseTotalAmount,     // d
            $purchaseTransactionRef,  // s
            $purchasePaymentStatus,   // s
            $purchaseInventoryId,     // i
            $purchaseFirmId,          // i
            $purchaseWeight,          // d
            $purchasePaidAmount,      // i
            $purchasePaymentMode,     // s
            $purchaseInvoiceNumber,   // s
            $entryTypeStr             // s
        );
        $stmt->execute();
        $purchaseId = $conn->insert_id;
        $debugLog[] = "Inserted metal_purchases record for inventory ID $inventoryId, entry type $entryTypeStr";
        
        // Insert into purchase_items for purchase entries (single item)
        if ($isPurchase) {
            $itemGstPercent = isset($_POST['gst']) && $_POST['gst'] === 'true' ? 3.0 : 0.0;
            $itemGstAmount = $itemGstPercent > 0 ? ($totalTaxableAmount * $itemGstPercent / 100) : 0.0;
            
            $stmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, material_type, stock_name, purity, quantity, unit_measurement, rate_per_unit, total_amount, gst_percent, gst_amount, hsn_code, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param(
                "isssdidddds",
                $purchaseId,
                $materialType,
                $stockName,
                $purity,
                $quantity,
                $unit,
                $rate,
                $totalTaxableAmount,
                $itemGstPercent,
                $itemGstAmount,
                $hsnCode
            );
            $stmt->execute();
            $debugLog[] = "Inserted purchase_items record for purchase ID $purchaseId";
        }
        
        $conn->commit();
        $debugLog[] = "Transaction committed successfully";
        
        // Log the operation
        error_log("Stock entry saved - User: $user_id, Firm: $firm_id, Material: $materialType, Stock: $stockName, Amount: $finalAmount");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Stock entry saved successfully!', 
            'debug' => $debugLog,
            'inventory_id' => $inventoryId,
            'purchase_id' => $purchaseId
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        $debugLog[] = 'Error saving stock entry: ' . $e->getMessage();
        error_log("Stock entry error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error saving stock entry: ' . $e->getMessage(), 
            'debug' => $debugLog
        ]);
    }
} else {
    // Handle GET request - return form data or validation
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method. Use POST to submit stock entry.'
    ]);
}
?> 
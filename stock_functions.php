<?php
// filepath: c:\Users\HP\JewelEntry2.01\includes\Database.php
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $config = require('config/database.php');
        try {
            $this->conn = new mysqli(
                $config['db_host'],
                $config['db_user'],
                $config['db_pass'],
                $config['db_name']
            );
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

// Database connection
$database = Database::getInstance();
$conn = $database->getConnection();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to search stock names
function searchStockNames($materialType, $searchTerm = '') {
    global $conn;
    try {
        $searchTerm = "%" . $searchTerm . "%";
        $stmt = $conn->prepare("SELECT DISTINCT stock_name FROM inventory_metals 
                WHERE material_type = ? AND stock_name LIKE ?");
        $stmt->bind_param("ss", $materialType, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stockNames = array();
        while($row = $result->fetch_assoc()) {
            $stockNames[] = $row['stock_name'];
        }
        
        return json_encode($stockNames);
    } catch (Exception $e) {
        error_log("Error in searchStockNames: " . $e->getMessage());
        return json_encode(['error' => $e->getMessage()]);
    }
}

// Function to get total stock details for a specific purity
function getPurityStockDetails($materialType, $purity) {
    global $conn;
    $sql = "SELECT 
                SUM(current_stock) as total_current_stock,
                SUM(remaining_stock) as total_remaining_stock,
                COUNT(DISTINCT stock_name) as total_stock_names,
                GROUP_CONCAT(DISTINCT stock_name) as stock_names
            FROM inventory_metals 
            WHERE material_type = ? AND purity = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sd", $materialType, $purity);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($row = $result->fetch_assoc()) {
        // Get average rate for this purity
        $sql = "SELECT AVG(rate_per_gram) as avg_rate 
                FROM metal_purchases mp
                JOIN inventory_metals im ON mp.inventory_id = im.inventory_id
                WHERE im.material_type = ? AND im.purity = ?
                ORDER BY mp.purchase_date DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sd", $materialType, $purity);
        $stmt->execute();
        $rateResult = $stmt->get_result();
        $rateRow = $rateResult->fetch_assoc();
        
        $row['avg_rate'] = $rateRow['avg_rate'] ?? 0;
        return json_encode($row);
    }
    return json_encode(array('error' => 'No stock found'));
}

// Function to get suppliers
function getSuppliers() {
    global $conn;
    try {
        $result = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
        $suppliers = array();
        while($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }
        return json_encode($suppliers);
    } catch (Exception $e) {
        error_log("Error in getSuppliers: " . $e->getMessage());
        return json_encode(['error' => $e->getMessage()]);
    }
}

// Function to add stock
function addStock($data) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Set default values
         $firmId = $_SESSION['firmID'] ?? 1; // Get firm_id from session
        $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
        $weight = floatval($data['weight']);
        $rate = floatval($data['rate']);
        $totalAmount = $weight * $rate;
        $userId = $_SESSION['user_id'] ?? 1; // Get logged in user ID if available

        if ($data['is_purchase'] == '1') {
            // Purchase entry
            $supplierId = $data['supplier_id'];
            $paidAmount = floatval($data['paid_amount']);
            $paymentMode = ($paidAmount <= 0) ? '' : ($data['payment_mode'] ?? '');
            $invoiceNumber = $data['invoice_number'];
            $entryType = 'purchase';
        } else {
            // Direct entry
            $supplierId = 1; // Default supplier for direct entries
            $paidAmount = $totalAmount; // Fully paid for direct entries
            $paymentMode = 'Direct';
            $invoiceNumber = 'DIR-' . date('YmdHis');
            $entryType = 'direct';
        }

        // Create inventory record
        $inventoryStmt = $conn->prepare(
            "INSERT INTO inventory_metals (
                firm_id, material_type, stock_name, purity,
                current_stock, remaining_stock, unit_measurement,
                source_type
            ) VALUES (?, ?, ?, ?, ?, ?, 'grams', ?)"
        );

        $inventoryStmt->bind_param(
            "issddds",
            $firmId,
            $data['material_type'],
            $data['stock_name'],
            $data['purity'],
            $weight,
            $weight,
            $entryType
        );

        if (!$inventoryStmt->execute()) {
            throw new Exception("Failed to create inventory record: " . $inventoryStmt->error);
        }

        $inventoryId = $conn->insert_id;

        // Add stock log entry
        $stockLogStmt = $conn->prepare(
            "INSERT INTO jewellery_stock_log (
                 firm_id , inventory_id, material_type, stock_name, purity,
                transaction_type, quantity_before, quantity_change, quantity_after,
                reference_type, reference_id, user_id, notes
            ) VALUES (?, ?,?, ?, ?, 'IN', 0, ?, ?, ?, ?, ?, ?)"
        );

        $notes = "Initial stock entry via " . $entryType;
        $stockLogStmt->bind_param(
            "iissdddssss",
             $firmId,
            $inventoryId,
            $data['material_type'],
            $data['stock_name'],
            $data['purity'],
            $weight, // quantity_change
            $weight, // quantity_after
            $entryType, // reference_type
            $invoiceNumber, // reference_id
            $userId,
            $notes
        );

        if (!$stockLogStmt->execute()) {
            throw new Exception("Failed to create stock log entry: " . $stockLogStmt->error);
        }

        // Calculate payment status
        $paymentStatus = calculatePaymentStatus($totalAmount, $paidAmount);

        // *** Temporary Logging for Debugging ***
        $logData = "Payment Status: " . $paymentStatus . "\n";
        $logData .= "Total Amount: " . $totalAmount . "\n";
        $logData .= "Paid Amount: " . $paidAmount . "\n";
        $logFile = 'debug_stock_log.txt';
        file_put_contents($logFile, $logData, FILE_APPEND);
        // *** End Temporary Logging ***

        // Create purchase record
        $purchaseStmt = $conn->prepare(
            "INSERT INTO metal_purchases (
                firm_id, source_id, material_type, stock_name, purity,
                quantity, weight, rate_per_gram, total_amount, paid_amount,
                payment_mode, payment_status, invoice_number, entry_type,
                inventory_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $purchaseStmt->bind_param(
            "iisssdddddsssss",
            $firmId,
            $supplierId,
            $data['material_type'],
            $data['stock_name'],
            $data['purity'],
            $quantity,
            $weight,
            $rate,
            $totalAmount,
            $paidAmount,
            $paymentMode,
            $paymentStatus,
            $invoiceNumber,
            $entryType,
            $inventoryId
        );

        if (!$purchaseStmt->execute()) {
            throw new Exception("Failed to create purchase record: " . $purchaseStmt->error);
        }

        $purchaseId = $conn->insert_id;

        // If a payment was made, record it in the jewellery_payments table
        if ($paidAmount > 0) {
            $paymentStmt = $conn->prepare(
                "INSERT INTO jewellery_payments (
                    firm_id, reference_id, reference_type, party_type, party_id,
                    sale_id, payment_type, amount, payment_notes, reference_no,
                    remarks, created_at, transctions_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
            );
        
            $nullSaleId = null;
        
            // ✅ Define all string values as variables (DO NOT hardcode in bind_param)
            $referenceType = 'purchase';
            $partyType = 'supplier'; // corrected from 'suppiler'
            $paymentNotes = '';
            $remarks = '';
            $transactionsType = 'debit';
        
            $paymentStmt->bind_param(
                "iissisdsdsss", // Types: int, int, string, string, int, int|null, string, double, string, string, string, string
                $firmId,
                $purchaseId,
                $referenceType,
                $partyType,
                $supplierId,
                $nullSaleId,
                $paymentMode,
                $paidAmount,
                $paymentNotes,
                $invoiceNumber,
                $remarks,
                $transactionsType
            );
        
            if (!$paymentStmt->execute()) {
                throw new Exception("Failed to create payment record: " . $paymentStmt->error);
            }
        }
        

        // Update inventory record with source_id
        $updateStmt = $conn->prepare(
            "UPDATE inventory_metals SET source_id = ? WHERE inventory_id = ?"
        );
        $updateStmt->bind_param("ii", $purchaseId, $inventoryId);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update inventory record: " . $updateStmt->error);
        }

        // Commit transaction
        $conn->commit();
        
        return json_encode([
            'success' => true,
            'message' => 'Stock added successfully',
            'inventory_id' => $inventoryId,
            'purchase_id' => $purchaseId
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function calculatePaymentStatus($total, $paid) {
    if ($paid <= 0) return 'Unpaid';
    if ($paid >= $total) return 'Paid';
    return 'Partial';
}

// Function to get stock statistics for a specific purity
function getPurityStats($materialType, $purity) {
    global $conn;
    
    $stats = array();
    
    // Get total stock for this purity
    $sql = "SELECT 
                SUM(current_stock) as total_current_stock,
                SUM(remaining_stock) as total_remaining_stock,
                COUNT(DISTINCT stock_name) as total_stock_names
            FROM inventory_metals 
            WHERE material_type = ? AND purity = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sd", $materialType, $purity);
    $stmt->execute();
    $result = $stmt->get_result();
    $stock = $result->fetch_assoc();
    
    $stats['total_current_stock'] = $stock['total_current_stock'] ?? 0;
    $stats['total_remaining_stock'] = $stock['total_remaining_stock'] ?? 0;
    $stats['total_stock_names'] = $stock['total_stock_names'] ?? 0;
    
    // Get purchase history for this purity
    $sql = "SELECT 
                COUNT(*) as total_purchases,
                SUM(weight) as total_weight,
                AVG(rate_per_gram) as avg_rate,
                MAX(rate_per_gram) as max_rate,
                MIN(rate_per_gram) as min_rate
            FROM metal_purchases mp
            JOIN inventory_metals im ON mp.inventory_id = im.inventory_id
            WHERE im.material_type = ? AND im.purity = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sd", $materialType, $purity);
    $stmt->execute();
    $result = $stmt->get_result();
    $purchase = $result->fetch_assoc();
    
    $stats['total_purchases'] = $purchase['total_purchases'] ?? 0;
    $stats['total_weight'] = $purchase['total_weight'] ?? 0;
    $stats['avg_rate'] = $purchase['avg_rate'] ?? 0;
    $stats['max_rate'] = $purchase['max_rate'] ?? 0;
    $stats['min_rate'] = $purchase['min_rate'] ?? 0;
    
    return json_encode($stats);
}

function getStockStats() {
    global $conn;
    
    $query = "SELECT 
        ms.material_type,
        ms.purity,
        COUNT(DISTINCT ms.stock_name) as total_items,
        ROUND(SUM(ms.current_stock), 2) as total_stock,
        ROUND(SUM(ms.remaining_stock), 2) as remaining_stock,
        ROUND(SUM(ms.current_stock - ms.remaining_stock), 2) as issue_stock
    FROM inventory_metals ms
    WHERE ms.current_stock > 0
    GROUP BY ms.material_type, ms.purity
    ORDER BY ms.material_type, ms.purity DESC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        return ['error' => $conn->error];
    }
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = [
            'material_type' => $row['material_type'] ?? 'Unknown',
            'purity' => number_format($row['purity'], 2),
            'total_items' => intval($row['total_items']),
            'total_stock' => number_format($row['total_stock'], 2),
            'remaining_stock' => number_format($row['remaining_stock'], 2),
            'issue_stock' => number_format($row['issue_stock'], 2)
        ];
    }
    
    return $stats;
}


// Function to get available inventory
function getAvailableInventory($materialType = 'Gold') {
    global $conn;
    try {
        $sql = "SELECT 
                    i.inventory_id,
                    i.material_type,
                    i.stock_name,
                    i.purity,
                    i.remaining_stock,
                    p.source_id,
                    s.name as supplier_name,
                    p.weight as purchase_weight,
                    p.rate_per_gram,
                    p.purchase_date
                FROM inventory_metals i
                LEFT JOIN metal_purchases p ON i.inventory_id = p.inventory_id
                LEFT JOIN suppliers s ON p.source_id = s.id
                WHERE i.purity = ? AND i.remaining_stock > 0
                ORDER BY p.purchase_date DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $materialType);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $inventory = array();
        while($row = $result->fetch_assoc()) {
            $inventory[] = array(
                'inventory_id' => $row['inventory_id'],
                'stock_name' => $row['stock_name'],
                'purity' => $row['purity'],
                'remaining' => number_format($row['remaining_stock'], 3),
                'supplier' => $row['supplier_name'] ?? 'Direct Entry',
                'rate' => number_format($row['rate_per_gram'], 2),
                'purchase_date' => $row['purchase_date'] ? date('d-m-Y', strtotime($row['purchase_date'])) : '-'
            );
        }
        
        return json_encode($inventory);
    } catch (Exception $e) {
        error_log("Error in getAvailableInventory: " . $e->getMessage());
        return json_encode(['error' => $e->getMessage()]);
    }
}

// Function to save product images
function saveProductImages($productId, $images) {
    global $conn;
    try {
        $conn->begin_transaction();
        
        $stmt = $conn->prepare("INSERT INTO jewellery_product_image 
            (product_id, image_url, is_primary, firm_id) VALUES (?, ?, ?, 1)");
        
        foreach($images as $index => $image) {
            $isPrimary = ($index === 0) ? 1 : 0;
            
            // Generate unique filename
            $filename = uniqid() . '_' . $productId . '.jpg';
            $path = 'uploads/jewelry/' . $filename;
            
            // Save image file
            if(move_uploaded_file($image['tmp_name'], $path)) {
                $stmt->bind_param("ssi", $productId, $path, $isPrimary);
                if(!$stmt->execute()) {
                    throw new Exception("Failed to save image record");
                }
            }
        }
        
        $conn->commit();
        return json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in saveProductImages: " . $e->getMessage());
        return json_encode(['error' => $e->getMessage()]);
    }
}

// Function to get available purities
function getAvailablePurities() {
    global $conn;
    try {
        $sql = "SELECT DISTINCT purity 
                FROM inventory_metals 
                WHERE remaining_stock > 0 
                ORDER BY purity DESC";
        $result = $conn->query($sql);
        
        $purities = array();
        while($row = $result->fetch_assoc()) {
            $purities[] = $row['purity'];
        }
        return json_encode($purities);
    } catch (Exception $e) {
        return json_encode(['error' => $e->getMessage()]);
    }
}

// Function to get remaining stock for a specific purity
function getRemainingStock($purity) {
    global $conn;
    try {
        $sql = "SELECT SUM(remaining_stock) as remaining_stock 
                FROM inventory_metals 
                WHERE purity = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("d", $purity);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return json_encode([
            'remaining_stock' => $row['remaining_stock'] ?? 0
        ]);
    } catch (Exception $e) {
        return json_encode(['error' => $e->getMessage()]);
    }
}

// Function to get purity history
function getPurityHistory($materialType, $purity) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                im.inventory_id,
                im.stock_name,
                im.current_stock,
                im.remaining_stock,
                im.source_type,
                mp.purchase_date,
                mp.rate_per_gram,
                mp.total_amount,
                mp.payment_status,
                mp.invoice_number,
                s.name as supplier_name,
                jsl.transaction_type,
                jsl.quantity_change,
                jsl.transaction_date,
                jsl.notes
            FROM inventory_metals im
            LEFT JOIN metal_purchases mp ON im.inventory_id = mp.inventory_id
            LEFT JOIN suppliers s ON mp.source_id = s.id
            LEFT JOIN jewellery_stock_log jsl ON im.inventory_id = jsl.inventory_id
            WHERE im.material_type = ? AND im.purity = ?
            ORDER BY mp.purchase_date DESC, jsl.transaction_date DESC
        ");
        
        $stmt->bind_param("sd", $materialType, $purity);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [
            'summary' => [
                'total_purchases' => 0,
                'total_weight' => 0,
                'avg_rate' => 0,
                'current_stock' => 0,
                'remaining_stock' => 0,
                'total_transactions' => 0
            ],
            'transactions' => []
        ];
        
        $rates = [];
        
        while($row = $result->fetch_assoc()) {
            // Update summary
            if ($row['source_type'] === 'purchase') {
                $history['summary']['total_purchases']++;
                $history['summary']['total_weight'] += $row['current_stock'];
                $rates[] = $row['rate_per_gram'];
            }
            
            $history['summary']['current_stock'] = $row['current_stock'];
            $history['summary']['remaining_stock'] = $row['remaining_stock'];
            $history['summary']['total_transactions']++;
            
            // Add transaction details
            $history['transactions'][] = [
                'date' => $row['transaction_date'] ?? $row['purchase_date'],
                'stock_name' => $row['stock_name'],
                'type' => $row['transaction_type'] ?? 'PURCHASE',
                'quantity' => $row['quantity_change'] ?? $row['current_stock'],
                'rate' => $row['rate_per_gram'],
                'amount' => $row['total_amount'],
                'supplier' => $row['supplier_name'],
                'invoice' => $row['invoice_number'],
                'payment_status' => $row['payment_status'],
                'notes' => $row['notes']
            ];
        }
        
        // Calculate average rate
        if (count($rates) > 0) {
            $history['summary']['avg_rate'] = array_sum($rates) / count($rates);
        }
        
        return json_encode($history);
        
    } catch (Exception $e) {
        return json_encode(['error' => $e->getMessage()]);
    }
}

// Add to stock_functions.php
function getCurrentRate($materialType) {
    global $conn;

    $purityToFetch = '';
    if ($materialType === 'Gold') {
        $purityToFetch = '99.99';
    } else if ($materialType === 'Silver') {
        $purityToFetch = '999.9';
    } else {
        // Handle other material types or a default if necessary
        // For now, we'll return no rate if not Gold or Silver
        return ['success' => false, 'error' => 'No fine rate defined for this material type'];
    }

    $sql = "SELECT rate
            FROM jewellery_price_config
            WHERE material_type = ? AND purity = ?
            ORDER BY effective_date DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $materialType, $purityToFetch);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()) {
        // The fetched rate is for the 'fine' purity (99.99 or 999.9)
        // We can return this directly. The frontend JavaScript will use this as the base rate.
        return ['success' => true, 'rate' => $row['rate']];
    }

    return ['success' => false, 'error' => 'No rate found for the specified fine purity'];
}

// Add function to search suppliers for autosuggestion
function searchSuppliers($searchTerm) {
    global $conn;
    try {
        $searchTerm = "%" . $searchTerm . "%";
         $firmId = $_SESSION['firmID'] ?? 1; // Get firm_id from session
        $stmt = $conn->prepare("SELECT id, name FROM suppliers WHERE firm_id = ? AND name LIKE ? ORDER BY name LIMIT 10");
        $stmt->bind_param("is", $firmId, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();

        $suppliers = array();
        while($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }

        return json_encode($suppliers);

    } catch (Exception $e) {
        error_log("Error in searchSuppliers: " . $e->getMessage());
        return json_encode(['error' => $e->getMessage()]);
    }
}

// Handle AJAX requests
if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch($_POST['action']) {
            case 'searchStockNames':
                if(!isset($_POST['material_type'])) {
                    throw new Exception("Material type is required");
                }
                echo searchStockNames(
                    $_POST['material_type'],
                    $_POST['search_term'] ?? ''
                );
                break;
                
            case 'getPurityStockDetails':
                echo getPurityStockDetails(
                    $_POST['material_type'],
                    $_POST['purity']
                );
                break;
                
            case 'getSuppliers':
                echo getSuppliers();
                break;
                
            case 'addStock':
                echo addStock($_POST);
                break;
                
            case 'getPurityStats':
                echo getPurityStats(
                    $_POST['material_type'],
                    $_POST['purity']
                );
                break;
                
            case 'getStockStats':
                echo json_encode(getStockStats());
                break;
                
            case 'searchJewelryType':
                echo searchJewelryType($_POST['search_term']);
                break;
                
            case 'createJewelryType':
                echo createJewelryType($_POST['name'], $_POST['parent_id'] ?? null);
                break;
                
            case 'generateProductId':
                echo generateProductId($_POST['jewelry_type']);
                break;
                
            case 'getAvailableInventory':
                echo getAvailableInventory($_POST['material_type'] ?? 'Gold');
                break;
                
            case 'saveProductImages':
                echo saveProductImages($_POST['product_id'], $_FILES['images']);
                break;
                
            case 'addJewellery':
                echo json_encode(addJewellery($_POST));
                break;
                
            case 'getAvailablePurities':
                echo getAvailablePurities();
                break;

            case 'getRemainingStock':
                echo getRemainingStock($_POST['purity']);
                break;
                
            case 'getPurityHistory':
                echo getPurityHistory(
                    $_POST['material_type'],
                    $_POST['purity']
                );
                break;
                
            case 'getCurrentRate':
                echo json_encode(getCurrentRate($_POST['material_type']));
                break;
                
            case 'searchSuppliers':
                echo searchSuppliers($_POST['search_term']);
                break;
                
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        error_log("Error processing request: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>

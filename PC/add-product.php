<?php
// Prevent PHP errors from breaking JSON responses
ini_set('display_errors', 0);
error_reporting(0);

// Start session and include database config
session_start();
require_once __DIR__ . '/../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
   header("Location: login.php");
   exit();
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Establish database connection using config function
try {
    // $conn is already created in db_connect.php
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}

// Test query to verify database access
try {
    $testQuery = "SELECT COUNT(*) as count FROM jewellery_items WHERE firm_id = ?";
    $testStmt = $conn->prepare($testQuery);
    $testStmt->bind_param("i", $firm_id);
    $testStmt->execute();
    $testResult = $testStmt->get_result();
    $testRow = $testResult->fetch_assoc();
    error_log("Test query successful. Found " . $testRow['count'] . " jewelry items.");
} catch (Exception $e) {
    error_log("Test query failed: " . $e->getMessage());
}

// Debug function is now available from config.php

// Function to generate product ID
function generateProductId($conn, $jewelryType) {
    try {
        $prefix = strtoupper(substr($jewelryType, 0, 1));
        $sql = "SELECT COUNT(*) as count FROM jewellery_items WHERE jewelry_type = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $jewelryType);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)$row['count'];
        $newNum = $count + 1;
        $newId = $prefix . str_pad($newNum, 3, '0', STR_PAD_LEFT);
        return $newId;
    } catch (Exception $e) {
        debug_log("Error in generateProductId", $e->getMessage());
        return $prefix . '001';
    }
}

// Function to update inventory
function updateInventory($conn, $materialType, $purity, $weight, $firm_id) {
    try {
        // Check if there's enough inventory
        $sql = "SELECT inventory_id, remaining_stock FROM inventory_metals 
                WHERE material_type = ? AND purity = ? AND remaining_stock >= ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssd", $materialType, $purity, $weight);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $inventoryId = $row['inventory_id'];
            $newStock = $row['remaining_stock'] - $weight;
            
            // Update the inventory
            $updateSql = "UPDATE inventory_metals SET remaining_stock = ?, last_updated = NOW() 
                          WHERE inventory_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("di", $newStock, $inventoryId);
            $success = $updateStmt->execute();
            $updateStmt->close();
            
            return $success;
        } else {
            return false; // Not enough inventory
        }
    } catch (Exception $e) {
        debug_log("Error in updateInventory", $e->getMessage());
        return false;
    }
}

// Function to get inventory stats
function getInventoryStats($conn, $firm_id) {
    try {
        $sql = "SELECT purity, SUM(gross_weight) as total_stock 
                FROM  jewellery_items 
                WHERE firm_id = ? 
                GROUP BY purity";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $firm_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[$row['purity']] = $row['total_stock'];
        }
        
        return $stats;
    } catch (Exception $e) {
        debug_log("Error in getInventoryStats", $e->getMessage());
        return [];
    }
}

// Add this function at the top with other functions
function updateInventoryStock($conn, $materialType, $purity, $weight, $action = 'decrease') {
    try {
        $sql = "UPDATE inventory_metals SET 
                remaining_stock = remaining_stock " . ($action === 'decrease' ? '-' : '+') . " ?,
                last_updated = NOW() 
                WHERE material_type = ? AND purity = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dss", $weight, $materialType, $purity);
        return $stmt->execute();
    } catch (Exception $e) {
        debug_log("Error in updateInventoryStock", $e->getMessage());
        return false;
    }
}

// Function to get manufacturing orders
function getManufacturingOrders($conn, $search = '', $firm_id, $purity = null) {
    try {
        $sql = "SELECT mo.id, mo.karigar_id, k.name as karigar_name, mo.expected_weight, mo.purity_out, mo.status
                FROM manufacturing_orders mo
                JOIN karigars k ON mo.karigar_id = k.id
                WHERE mo.firm_id = ? AND (mo..purity LIKE ? OR k.name LIKE ?)";
        
        // Add purity filter if provided
        if ($purity !== null) {
            $sql .= " AND mo.purity = ?";
        }
        
        // Add status filter to show only pending or completed orders
        $sql .= " AND (mo.status = 'Pending' OR mo.status = 'Completed')";
        
        $sql .= " ORDER BY mo.id DESC LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $searchParam = "%$search%";
        
        if ($purity !== null) {
            $stmt->bind_param("issd", $firm_id, $searchParam, $searchParam, $purity);
        } else {
            $stmt->bind_param("iss", $firm_id, $searchParam, $searchParam);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        return $orders;
    } catch (Exception $e) {
        debug_log("Error in getManufacturingOrders", $e->getMessage());
        return [];
    }
}

// Function to get metal purchases with inventory details
function getMetalPurchases($conn, $search = '', $firm_id, $purity = null) {
    try {
        $sql = "SELECT mp.purchase_id, mp.invoice_number, mp.source_id, mp.material_type, mp.purity, mp.weight,
                im.inventory_id, im.remaining_stock, im.cost_price_per_gram,
                CASE 
                    WHEN mp.source_type = 'Supplier' THEN (SELECT name FROM suppliers WHERE id = mp.source_id)
                    ELSE 'Unknown'
                END as supplier_name
                FROM metal_purchases mp
                JOIN inventory_metals im ON mp.inventory_id = im.inventory_id
                WHERE mp.firm_id = ? AND (mp.purity LIKE ? OR mp.purchase_id LIKE ?)";
        
        // Add purity filter if provided
        if ($purity !== null) {
            $sql .= " AND mp.purity = ?";
        }
        
        // Only show items with remaining stock
        $sql .= " AND im.remaining_stock > 0";
        
        $sql .= " ORDER BY mp.purchase_id DESC LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $searchParam = "%$search%";
        
        if ($purity !== null) {
            $stmt->bind_param("issd", $firm_id, $searchParam, $searchParam, $purity);
        } else {
            $stmt->bind_param("iss", $firm_id, $searchParam, $searchParam);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $purchases = [];
        while ($row = $result->fetch_assoc()) {
            $purchases[] = $row;
        }
        
        return $purchases;
    } catch (Exception $e) {
        debug_log("Error in getMetalPurchases", $e->getMessage());
        return [];
    }
}

// Add this function to get trays with suggestions
function getTraysSuggestions($conn, $search = '', $firm_id) {
    try {
        $sql = "SELECT id, tray_number, tray_type, location, capacity 
                FROM Jewellery_trays
                WHERE tray_number LIKE ? AND firm_id = ?
                ORDER BY tray_number ASC LIMIT 10";
        $stmt = $conn->prepare($sql);
        $searchParam = "%$search%";
        $stmt->bind_param("si", $searchParam, $firm_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $trays = [];
        while ($row = $result->fetch_assoc()) {
            $trays[] = $row;
        }
        
        return $trays;
    } catch (Exception $e) {
        debug_log("Error in getTraysSuggestions", $e->getMessage());
        return [];
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set header to JSON for all AJAX responses
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    try {
        // Update the add_item function to handle the new source structure and inventory deduction

        if ($_POST['action'] === 'add_item') {
            // Get form data
            $sourceType = $_POST['sourceType'];
            $sourceId = $_POST['sourceId'] ?? null;
            $materialType = $_POST['materialType'];
            $purity = $_POST['purity'];
            $jewelryType = $_POST['jewelryType'];
            $productName = $_POST['productName'];
            $grossWeight = $_POST['grossWeight'];
            $lessWeight = $_POST['lessWeight'];
            $netWeight = $_POST['netWeight'];
            $trayNo = $_POST['trayNo'];
            $huidCode = $_POST['huidCode'];
            $stoneType = $_POST['stoneType'];
            $stoneWeight = $stoneType !== 'None' ? $_POST['stoneWeight'] : 0;
            $stoneUnit = $stoneType !== 'None' ? $_POST['stoneUnit'] : '';
            $stoneColor = $stoneType !== 'None' ? $_POST['stoneColor'] : '';
            $stoneClarity = $stoneType !== 'None' ? $_POST['stoneClarity'] : '';
            $stoneQuality = $stoneType !== 'None' ? $_POST['stoneQuality'] : '';
            $stonePrice = $stoneType !== 'None' ? $_POST['stonePrice'] : 0;
            $makingCharge = $_POST['makingCharge'];
            $makingChargeType = $_POST['makingChargeType'];
            $description = $_POST['description'];
            $quantity = $_POST['quantity'] ?? 1;
            $status = $_POST['status'] ?? 'Available';
            $inventoryId = $_POST['inventoryId'] ?? null;

            // Generate product ID based on jewelry type
            $product_id = generateProductId($conn, $jewelryType);

            // Check if inventory should be updated - only for Purchase source type
            $updateInventory = ($sourceType === 'Purchase' && $inventoryId);
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Check if jewelry type exists in JewelEntry_category
                $checkCatSql = "SELECT id FROM JewelEntry_category WHERE name = ?";
                $checkCatStmt = $conn->prepare($checkCatSql);
                $checkCatStmt->bind_param("s", $jewelryType);
                $checkCatStmt->execute();
                $checkCatResult = $checkCatStmt->get_result();
                
                // If jewelry type doesn't exist, add it
                if ($checkCatResult->num_rows === 0) {
                    $addCatSql = "INSERT INTO JewelEntry_category (name, description, created_at) VALUES (?, ?, NOW())";
                    $addCatStmt = $conn->prepare($addCatSql);
                    $catDesc = "Auto-created for " . $productName;
                    $addCatStmt->bind_param("ss", $jewelryType, $catDesc);
                    $addCatStmt->execute();
                }
                
                // Set supplier_id or karigar_id based on source type
                $supplierId = null;
                $karigarId = 0; // Set default value to 0
                
                if ($sourceType === 'Purchase') {
                    // For Purchase, get supplier_id from metal_purchases
                    $supplierSql = "SELECT source_id FROM metal_purchases WHERE purchase_id = ?";
                    $supplierStmt = $conn->prepare($supplierSql);
                    $supplierStmt->bind_param("i", $sourceId);
                    $supplierStmt->execute();
                    $supplierResult = $supplierStmt->get_result();
                    if ($supplierResult->num_rows > 0) {
                        $supplierId = $supplierResult->fetch_assoc()['source_id'];
                    }
                    $karigarId = 0;
                } elseif ($sourceType === 'Manufacturing Order') {
                    // For Manufacturing Order, get karigar_id from manufacturing_orders
                    $karigarSql = "SELECT karigar_id FROM manufacturing_orders WHERE id = ?";
                    $karigarStmt = $conn->prepare($karigarSql);
                    $karigarStmt->bind_param("i", $sourceId);
                    $karigarStmt->execute();
                    $karigarResult = $karigarStmt->get_result();
                    if ($karigarResult->num_rows > 0) {
                        $karigarId = $karigarResult->fetch_assoc()['karigar_id'];
                    }
                    $supplierId = 0;
                } else {
                    // Others
                    $supplierId = 0;
                    $karigarId = 0;
                }
                
                // Insert into jewellery_items table
                $sql = "INSERT INTO jewellery_items (
                    firm_id, product_id, jewelry_type, product_name, material_type, 
                    purity, Tray_no, huid_code, gross_weight, less_weight, net_weight, 
                    stone_type, stone_weight, stone_quality, stone_price, stone_unit, stone_color, stone_clarity,
                    making_charge, making_charge_type, description, status, 
                    quantity, supplier_id, karigar_id, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )";
                
                $stmt = $conn->prepare($sql);
                
                $stmt->bind_param(
                    "isssssssdddsdsdsssssssiis",
                    $firm_id, $product_id, $jewelryType, $productName, $materialType,
                    $purity, $trayNo, $huidCode, $grossWeight, $lessWeight, $netWeight,
                    $stoneType, $stoneWeight,$stoneQuality,$stonePrice,  $stoneUnit, $stoneColor, $stoneClarity,
                    $makingCharge, $makingChargeType, $description, $status,
                    $quantity, $supplierId, $karigarId
                );
                
                $stmt->execute();
                $jewelryItemId = $conn->insert_id;
                
                // Update inventory if it's a Purchase source type and inventory ID is provided
                if ($updateInventory) {
                    // Check if there's enough stock
                    $checkStockSql = "SELECT remaining_stock FROM inventory_metals WHERE inventory_id = ?";
                    $checkStockStmt = $conn->prepare($checkStockSql);
                    $checkStockStmt->bind_param("i", $inventoryId);
                    $checkStockStmt->execute();
                    $checkStockResult = $checkStockStmt->get_result();
                    
                    if ($checkStockResult->num_rows > 0) {
                        $remainingStock = $checkStockResult->fetch_assoc()['remaining_stock'];
                        
                        if ($remainingStock >= $netWeight) {
                            // Update inventory
                            $updateSql = "UPDATE inventory_metals SET 
                                          remaining_stock = remaining_stock - ?, 
                                          last_updated = NOW() 
                                          WHERE inventory_id = ?";
                            $updateStmt = $conn->prepare($updateSql);
                            $updateStmt->bind_param("di", $netWeight, $inventoryId);
                            $success = $updateStmt->execute();
                            $updateStmt->close();
                            
                            if (!$success) {
                                throw new Exception("Failed to update inventory.");
                            }
                            
                            // Add inventory transaction record
                            $transactionSql = "INSERT INTO inventory_transactions (
                                              inventory_id, transaction_type, quantity, reference_id, reference_type, 
                                              transaction_date, created_by, firm_id
                                            ) VALUES (?, 'Deduction', ?, ?, 'Jewelry Item', NOW(), ?, ?)";
                            $transactionStmt = $conn->prepare($transactionSql);
                            $transactionStmt->bind_param("idsii", $inventoryId, $netWeight, $jewelryItemId, $user_id, $firm_id);
                            $transactionStmt->execute();
                        } else {
                            throw new Exception("Not enough stock available. Required: {$netWeight}g, Available: {$remainingStock}g");
                        }
                    } else {
                        throw new Exception("Inventory record not found.");
                    }
                }
                
                // Handle image uploads if any
                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    $uploadDir = 'uploads/jewelry/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileCount = count($_FILES['images']['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        $fileName = $_FILES['images']['name'][$i];
                        $tmpName = $_FILES['images']['tmp_name'][$i];
                        $fileSize = $_FILES['images']['size'][$i];
                        $fileType = $_FILES['images']['type'][$i];
                        
                        // Generate unique filename
                        $newFileName = $product_id . '_' . time() . '_' . $i . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
                        $targetFilePath = $uploadDir . $newFileName;
                        
                        // Upload file
                        if (move_uploaded_file($tmpName, $targetFilePath)) {
                            // Insert into product_image table
                            $isPrimary = ($i === 0) ? 1 : 0; // First image is primary
                            
                            $imgSql = "INSERT INTO jewellery_product_image (product_id, image_url, is_primary) 
                                      VALUES (?, ?,  ?)";
                            $imgStmt = $conn->prepare($imgSql);
                            $imgStmt->bind_param("isi", $jewelryItemId, $targetFilePath,  $isPrimary);
                            $imgStmt->execute();
                        }
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                $response['success'] = true;
                $response['message'] = "Jewelry item added successfully!";
                $response['data'] = [
                    'id' => $jewelryItemId,
                    'product_id' => $product_id
                ];
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $response['message'] = "Error: " . $e->getMessage();
                debug_log("Error in add_item", $e->getMessage());
            }
            
        } elseif ($_POST['action'] === 'update_item') {
            try {
                // Get form data
                $itemId = $_POST['itemId'];
                $jewelryType = $_POST['jewelryType'];
                $productName = $_POST['productName'];
                $materialType = $_POST['materialType'];
                $purity = $_POST['purity'];
                $trayNo = $_POST['trayNo'];
                $huidCode = $_POST['huidCode'];
                $grossWeight = (float)$_POST['grossWeight'];
                $lessWeight = (float)$_POST['lessWeight'];
                $netWeight = (float)$_POST['netWeight'];
                $stoneType = $_POST['stoneType'];
                $stoneWeight = $stoneType !== 'None' ? (float)$_POST['stoneWeight'] : 0;
                $stoneQuality = $stoneType !== 'None' ? $_POST['stoneQuality'] : '';
                $stonePrice = $stoneType !== 'None' ? (float)$_POST['stonePrice'] : 0;
                $makingCharge = (float)$_POST['makingCharge'];
                $makingChargeType = $_POST['makingChargeType'];
                $description = $_POST['description'];
                $status = $_POST['status'] ?? 'Available';
                $quantity = (int)$_POST['quantity'];
                
                // Get source information
                $sourceType = $_POST['sourceType'] ?? 'Others';
                $sourceId = $_POST['sourceId'] ?? '';
                $inventoryId = $_POST['inventoryId'] ?? null;
                
                // Set supplier_id and karigar_id based on source type
                $supplierId = 0;
                $karigarId = 0;
                if ($sourceType === 'Purchase') {
                    $supplierId = (int)$sourceId;
                } elseif ($sourceType === 'Manufacturing Order') {
                    $karigarId = (int)$sourceId;
                }

                // Get the current item details before update
                $currentItemSql = "SELECT material_type, purity, net_weight, status, supplier_id, karigar_id FROM jewellery_items WHERE id = ?";
                $currentItemStmt = $conn->prepare($currentItemSql);
                $currentItemStmt->bind_param("i", $itemId);
                $currentItemStmt->execute();
                $currentItemResult = $currentItemStmt->get_result();
                $currentItem = $currentItemResult->fetch_assoc();

                // Begin transaction
                $conn->begin_transaction();

                // Check if inventory should be updated
                $updateInventory = isset($_POST['updateInventory']) && $_POST['updateInventory'] === 'on';
                
                // Handle inventory reversal for material/purity/weight changes
                $materialChanged = ($currentItem['material_type'] !== $materialType);
                $purityChanged = ($currentItem['purity'] != $purity);
                $weightChanged = ($currentItem['net_weight'] != $netWeight);
                
                // If any material properties changed and user wants to update inventory
                if ($updateInventory && ($materialChanged || $purityChanged || $weightChanged)) {
                    // Return old weight to inventory
                    if ($currentItem['net_weight'] > 0) {
                        updateInventoryStock(
                            $conn, 
                            $currentItem['material_type'], 
                            $currentItem['purity'], 
                            $currentItem['net_weight'], 
                            'increase'
                        );
                    }

                    // Deduct new weight from inventory (only if source is Purchase)
                    if ($sourceType === 'Purchase' && $inventoryId) {
                        $inventoryUpdated = updateInventoryStock(
                            $conn, 
                            $materialType, 
                            $purity, 
                            $netWeight, 
                            'decrease'
                        );

                        if (!$inventoryUpdated) {
                            throw new Exception("Not enough inventory available for this material and purity.");
                        }
                    }
                }

                // Update query
                $sql = "UPDATE jewellery_items SET 
                        jewelry_type = ?, 
                        product_name = ?, 
                        material_type = ?, 
                        purity = ?, 
                        Tray_no = ?, 
                        huid_code = ?, 
                        gross_weight = ?, 
                        less_weight = ?, 
                        net_weight = ?, 
                        stone_type = ?, 
                        stone_weight = ?, 
                        stone_quality = ?, 
                        stone_price = ?, 
                        making_charge = ?, 
                        making_charge_type = ?, 
                        description = ?, 
                        status = ?, 
                        quantity = ?, 
                        supplier_id = ?, 
                        karigar_id = ?,
                        updated_at = NOW()
                        WHERE id = ?";

                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param(
                    "ssssssdddsssdssssiiis",
                    $jewelryType, 
                    $productName, 
                    $materialType,
                    $purity, 
                    $trayNo, 
                    $huidCode,
                    $grossWeight, 
                    $lessWeight, 
                    $netWeight,
                    $stoneType, 
                    $stoneWeight, 
                    $stoneQuality, 
                    $stonePrice,
                    $makingCharge, 
                    $makingChargeType, 
                    $description,
                    $status, 
                    $quantity, 
                    $supplierId, 
                    $karigarId, 
                    $itemId
                );

                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }

                // Handle image uploads if any
                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    $uploadDir = 'uploads/jewelry/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
            
                    $fileCount = count($_FILES['images']['name']);
                    for ($i = 0; $i < $fileCount; $i++) {
                        $fileName = $_FILES['images']['name'][$i];
                        $tmpName = $_FILES['images']['tmp_name'][$i];
            
                        $pidSql = "SELECT product_id FROM jewellery_items WHERE id = ?";
                        $pidStmt = $conn->prepare($pidSql);
                        $pidStmt->bind_param("i", $itemId);
                        $pidStmt->execute();
                        $pidResult = $pidStmt->get_result();
                        $pidRow = $pidResult->fetch_assoc();
                        $product_id = $pidRow['product_id'];
            
                        $newFileName = $product_id . '_' . time() . '_' . $i . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
                        $targetFilePath = $uploadDir . $newFileName;
            
                        if (move_uploaded_file($tmpName, $targetFilePath)) {
                            $isPrimary = ($i === 0 && !isset($_POST['keep_images'])) ? 1 : 0;
                            $imgSql = "INSERT INTO jewellery_product_image (product_id, image_url, is_primary) VALUES (?, ?, ?)";
                            $imgStmt = $conn->prepare($imgSql);
                            $imgStmt->bind_param("isi", $itemId, $targetFilePath, $isPrimary);
                            $imgStmt->execute();
                        }
                    }
                }
            
                // Commit transaction
                $conn->commit();

                $response['success'] = true;
                $response['message'] = "Item updated successfully!";
                $response['data'] = ['id' => $itemId];

            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                error_log("Update Item Error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $response['success'] = false;
                $response['message'] = "Error updating item: " . $e->getMessage();
                debug_log("Error in update_item", $e->getMessage());
            }
        } elseif ($_POST['action'] === 'delete_item') {
            try {
                if (!isset($_POST['itemId']) || empty($_POST['itemId'])) {
                    throw new Exception("Item ID is required");
                }

                $itemId = intval($_POST['itemId']);
                    
                // Begin transaction
                $conn->begin_transaction();
                    
                try {
                    // Get current item data to return to inventory
                    $currentSql = "SELECT material_type, purity, net_weight, product_id FROM jewellery_items WHERE id = ? AND firm_id = ?";
                    $currentStmt = $conn->prepare($currentSql);
                    $currentStmt->bind_param("ii", $itemId, $firm_id);
                    $currentStmt->execute();
                    $currentResult = $currentStmt->get_result();
                        
                    if ($currentResult->num_rows === 0) {
                        throw new Exception("Item not found or you don't have permission to delete it.");
                    }
                        
                    $currentItem = $currentResult->fetch_assoc();
                        
                    // Return material to inventory if weight exists
                    if ($currentItem['net_weight'] > 0) {
                        $returnSql = "UPDATE inventory_metals SET 
                                     remaining_stock = remaining_stock + ?, 
                                     last_updated = NOW() 
                                     WHERE material_type = ? AND purity = ? AND firm_id = ?";
                        $returnStmt = $conn->prepare($returnSql);
                        $returnStmt->bind_param("dssi", $currentItem['net_weight'], $currentItem['material_type'], $currentItem['purity'], $firm_id);
                        if (!$returnStmt->execute()) {
                            throw new Exception("Failed to update inventory");
                        }
                    }
                        
                    // Delete images
                    $imgSql = "SELECT image_url FROM jewellery_product_image WHERE product_id = ?";
                    $imgStmt = $conn->prepare($imgSql);
                    $imgStmt->bind_param("i", $itemId);
                    $imgStmt->execute();
                    $imgResult = $imgStmt->get_result();
                        
                    while ($imgRow = $imgResult->fetch_assoc()) {
                        if (file_exists($imgRow['image_url'])) {
                            unlink($imgRow['image_url']);
                        }
                    }
                        
                    // Delete image records
                    $delImgSql = "DELETE FROM jewellery_product_image WHERE product_id = ?";
                    $delImgStmt = $conn->prepare($delImgSql);
                    $delImgStmt->bind_param("i", $itemId);
                    if (!$delImgStmt->execute()) {
                        throw new Exception("Failed to delete image records");
                    }
                        
                    // Delete item
                    $delSql = "DELETE FROM jewellery_items WHERE id = ? AND firm_id = ?";
                    $delStmt = $conn->prepare($delSql);
                    $delStmt->bind_param("ii", $itemId, $firm_id);
                    if (!$delStmt->execute()) {
                        throw new Exception("Failed to delete item");
                    }

                    if ($delStmt->affected_rows === 0) {
                        throw new Exception("Item not found or already deleted");
                    }
                        
                    // Commit transaction
                    $conn->commit();
                        
                    $response['success'] = true;
                    $response['message'] = "Item {$currentItem['product_id']} deleted successfully!";
                        
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    throw $e;
                }
            } catch (Exception $e) {
                $response['success'] = false;
                $response['message'] = "Error: " . $e->getMessage();
                error_log("Delete Item Error: " . $e->getMessage());
            }
        } elseif ($_POST['action'] === 'get_jewelry_types') {
            $search = $_POST['search'] ?? '';
            
            $sql = "SELECT id, name FROM JewelEntry_category WHERE name LIKE ? LIMIT 10";
            $stmt = $conn->prepare($sql);
            $searchParam = "%$search%";
            $stmt->bind_param("s", $searchParam);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $types = [];
            while ($row = $result->fetch_assoc()) {
                $types[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $types;
        } elseif ($_POST['action'] === 'get_suppliers') {
            $search = $_POST['search'] ?? '';
            
            $sql = "SELECT id, name, address FROM suppliers WHERE name LIKE ? LIMIT 10";
            $stmt = $conn->prepare($sql);
            $searchParam = "%$search%";
            $stmt->bind_param("s", $searchParam);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $suppliers = [];
            while ($row = $result->fetch_assoc()) {
                $suppliers[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $suppliers;
        } elseif ($_POST['action'] === 'get_karigars') {
            $search = $_POST['search'] ?? '';
            
            $sql = "SELECT id, name, address_line1 FROM karigars WHERE name LIKE ? LIMIT 10";
            $stmt = $conn->prepare($sql);
            $searchParam = "%$search%";
            $stmt->bind_param("s", $searchParam);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $karigars = [];
            while ($row = $result->fetch_assoc()) {
                $karigars[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $karigars;
        } elseif ($_POST['action'] === 'get_item_details') {
            $itemId = $_POST['itemId'];
            
            $sql = "SELECT ji.*, 
                    CASE 
                        WHEN ji.supplier_id IS NOT NULL AND ji.supplier_id > 0 THEN 'Supplier' 
                        WHEN ji.karigar_id IS NOT NULL AND ji.karigar_id > 0 THEN 'Karigar' 
                        ELSE 'Other' 
                    END as source_type,
                    s.name as supplier_name, s.address as supplier_address, 
                    k.name as karigar_name, k.address_line1 as karigar_address, k.city as karigar_city
                    FROM jewellery_items ji
                    LEFT JOIN suppliers s ON ji.supplier_id = s.id
                    LEFT JOIN karigars k ON ji.karigar_id = k.id
                    WHERE ji.id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = "Item not found.";
            } else {
                $item = $result->fetch_assoc();
                
                // Get images
                $imgSql = "SELECT id, image_url, is_primary FROM jewellery_product_image WHERE product_id = ?";
                $imgStmt = $conn->prepare($imgSql);
                $imgStmt->bind_param("i", $itemId);
                $imgStmt->execute();
                $imgResult = $imgStmt->get_result();
                
                $images = [];
                while ($imgRow = $imgResult->fetch_assoc()) {
                    $images[] = $imgRow;
                }
                
                $item['images'] = $images;
                
                $response['success'] = true;
                $response['data'] = $item;
            }
        } elseif ($_POST['action'] === 'get_items') {
            try {
                $search = $_POST['search'] ?? '';
                $materialFilter = $_POST['materialFilter'] ?? '';
                $typeFilter = $_POST['typeFilter'] ?? '';
                $sourceFilter = $_POST['sourceFilter'] ?? '';
                $statusFilter = $_POST['statusFilter'] ?? '';
                $page = $_POST['page'] ?? 1;
                $limit = $_POST['limit'] ?? 10;
                $offset = ($page - 1) * $limit;
                
                error_log("get_items request: search=$search, material=$materialFilter, type=$typeFilter, source=$sourceFilter, status=$statusFilter, page=$page, limit=$limit");
                
                // Build query conditions
                $conditions = ["ji.firm_id = ?"];
                $params = [$firm_id];
                $types = "i";
                
                if (!empty($search)) {
                    $conditions[] = "(ji.product_id LIKE ? OR ji.product_name LIKE ? OR ji.jewelry_type LIKE ?)";
                    $searchParam = "%$search%";
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                    $types .= "sss";
                }
                
                if (!empty($materialFilter)) {
                    $conditions[] = "ji.material_type = ?";
                    $params[] = $materialFilter;
                    $types .= "s";
                }
                
                if (!empty($typeFilter)) {
                    $conditions[] = "ji.jewelry_type = ?";
                    $params[] = $typeFilter;
                    $types .= "s";
                }
                
                if (!empty($sourceFilter)) {
                    if ($sourceFilter === 'Supplier') {
                        $conditions[] = "ji.supplier_id IS NOT NULL AND ji.supplier_id > 0";
                    } elseif ($sourceFilter === 'Karigar') {
                        $conditions[] = "ji.karigar_id IS NOT NULL AND ji.karigar_id > 0";
                    } elseif ($sourceFilter === 'Other') {
                        $conditions[] = "(ji.supplier_id IS NULL OR ji.supplier_id = 0) AND (ji.karigar_id IS NULL OR ji.karigar_id = 0)";
                    }
                }
                
                if (!empty($statusFilter)) {
                    $conditions[] = "ji.status = ?";
                    $params[] = $statusFilter;
                    $types .= "s";
                }
                
                $whereClause = implode(" AND ", $conditions);
                error_log("Where clause: $whereClause");
                
                // Count total items
                $countSql = "SELECT COUNT(*) as total FROM jewellery_items ji WHERE $whereClause";
                $countStmt = $conn->prepare($countSql);
                if (!$countStmt) {
                    throw new Exception("Prepare failed for count query: " . $conn->error);
                }
                
                $countStmt->bind_param($types, ...$params);
                if (!$countStmt->execute()) {
                    throw new Exception("Execute failed for count query: " . $countStmt->error);
                }
                
                $countResult = $countStmt->get_result();
                $totalItems = $countResult->fetch_assoc()['total'];
                error_log("Total items found: $totalItems");
                
                // Get items with pagination
                $sql = "SELECT ji.*, 
                        CASE 
                            WHEN ji.supplier_id IS NOT NULL AND ji.supplier_id > 0 THEN 'Supplier' 
                            WHEN ji.karigar_id IS NOT NULL AND ji.karigar_id > 0 THEN 'Karigar' 
                            ELSE 'Other' 
                        END as source_type,
                        s.name as supplier_name,
                        k.name as karigar_name,
                        (SELECT image_url FROM jewellery_product_image WHERE product_id = ji.id AND is_primary = 1 LIMIT 1) as image_url
                        FROM jewellery_items ji
                        LEFT JOIN suppliers s ON ji.supplier_id = s.id
                        LEFT JOIN karigars k ON ji.karigar_id = k.id
                        WHERE $whereClause
                        ORDER BY ji.created_at DESC
                        LIMIT ? OFFSET ?";
                
                $params[] = $limit;
                $params[] = $offset;
                $types .= "ii";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed for items query: " . $conn->error);
                }
                
                $stmt->bind_param($types, ...$params);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed for items query: " . $stmt->error);
                }
                
                $result = $stmt->get_result();
                
                $items = [];
                while ($row = $result->fetch_assoc()) {
                    $items[] = $row;
                }
                
                error_log("Retrieved " . count($items) . " items for page $page");
                
                // Get inventory stats
                $inventoryStats = getInventoryStats($conn, $firm_id);
                
                $response['success'] = true;
                $response['data'] = [
                    'items' => $items,
                    'total' => $totalItems,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => ceil($totalItems / $limit),
                    'inventoryStats' => $inventoryStats
                ];
            } catch (Exception $e) {
                error_log("Error in get_items: " . $e->getMessage());
                $response['success'] = false;
                $response['message'] = "Error retrieving items: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'get_manufacturing_orders') {
    $search = $_POST['search'] ?? '';
    $purity = isset($_POST['purity']) && !empty($_POST['purity']) ? floatval($_POST['purity']) : null;
    
    $orders = getManufacturingOrders($conn, $search, $firm_id, $purity);
    
    $response['success'] = true;
    $response['data'] = $orders;
} 
elseif ($_POST['action'] === 'get_metal_purchases') {
    $search = $_POST['search'] ?? '';
    $purity = isset($_POST['purity']) && !empty($_POST['purity']) ? floatval($_POST['purity']) : null;
    
    $purchases = getMetalPurchases($conn, $search, $firm_id, $purity);
    
    $response['success'] = true;
    $response['data'] = $purchases;
}
elseif ($_POST['action'] === 'get_tray_suggestions') {
    $search = $_POST['search'] ?? '';
    
    $trays = getTraysSuggestions($conn, $search, $firm_id);
    
    $response['success'] = true;
    $response['data'] = $trays;
}
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = "Error: " . $e->getMessage();
        debug_log("General error in AJAX handler", $e->getMessage());
    }
    
    // Return JSON response
    echo json_encode($response);
    exit;
}

// Add AJAX endpoint for next product_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_next_product_id') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'product_id' => '', 'message' => ''];
    try {
        if (!isset($_POST['jewelryType']) || empty($_POST['jewelryType'])) {
            throw new Exception('Jewelry type is required');
        }
        $jewelryType = $_POST['jewelryType'];
        $product_id = generateProductId($conn, $jewelryType);
        $response['success'] = true;
        $response['product_id'] = $product_id;
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

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

// Get jewelry types for dropdown
$jewelryTypesQuery = "SELECT DISTINCT jewelry_type FROM jewellery_items WHERE firm_id = ? ORDER BY jewelry_type";
$jewelryTypesStmt = $conn->prepare($jewelryTypesQuery);
$jewelryTypesStmt->bind_param("i", $firm_id);
$jewelryTypesStmt->execute();
$jewelryTypesResult = $jewelryTypesStmt->get_result();

$jewelryTypes = [];
while ($row = $jewelryTypesResult->fetch_assoc()) {
    $jewelryTypes[] = $row['jewelry_type'];
}

// Get material types for filter
$materialTypesQuery = "SELECT DISTINCT material_type FROM jewellery_items WHERE firm_id = ? ORDER BY material_type";
$materialTypesStmt = $conn->prepare($materialTypesQuery);
$materialTypesStmt->bind_param("i", $firm_id);
$materialTypesStmt->execute();
$materialTypesResult = $materialTypesStmt->get_result();

$materialTypes = [];
while ($row = $materialTypesResult->fetch_assoc()) {
    $materialTypes[] = $row['material_type'];
}

// Get status types for filter
$statusTypesQuery = "SELECT DISTINCT status FROM jewellery_items WHERE firm_id = ? ORDER BY status";
$statusTypesStmt = $conn->prepare($statusTypesQuery);
$statusTypesStmt->bind_param("i", $firm_id);
$statusTypesStmt->execute();
$statusTypesResult = $statusTypesStmt->get_result();

$statusTypes = [];
while ($row = $statusTypesResult->fetch_assoc()) {
    $statusTypes[] = $row['status'];
}

// Get inventory stats
$inventoryStats = getInventoryStats($conn, $firm_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add Product - Jewelry Management System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
  <link href="../css/dashboard.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Plus Jakarta Sans', Segoe UI sans-serif;
      background-color: #f8f9fa;
    }
    
    #main-content {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
    }
    
    #main-content.collapsed {
      margin-left: 80px;
    }
    
    #main-content.full {
      margin-left: 0;
    }
    
    @media (max-width: 768px) {
      #main-content {
        margin-left: 0;
      }
    }
    
    .hover-card {
      transition: all 0.3s ease;
    }
    
    .hover-card:hover {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    .tab-active {
      border-bottom: 2px solid #4f46e5;
      color: #4f46e5;
      font-weight: 500;
    }
    
    .section-card {
      padding: 0.75rem;
      border-radius: 0.5rem;
      margin-bottom: 0.5rem;
    }
    
    .section-title {
      font-size: 0.95rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }
    
    .field-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
    }
    
    .field-col {
      display: flex;
      flex-direction: column;
    }
    
    .field-label {
    font-weight: 800;
      font-size: 0.75rem;
      color:rgb(70, 67, 97);
      margin-bottom: 0.125rem;
    }
    
    .field-container {
      position: relative;
    }
    
    .field-icon {
      position: absolute;
      left: 0.5rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: 0.95rem;
    }
    
    .input-field {
    font-weight: 800;   
    width: 100%;
      font-size: 0.95rem;
    }
    
    .material-theme {
      background: linear-gradient(to bottom right, #fffbeb, #fef3c7);
    }
    
    .weight-theme {
      background: linear-gradient(to bottom right, #eff6ff, #dbeafe);
    }
    
    .stone-theme {
      background: linear-gradient(to bottom right, #f5f3ff, #ede9fe);
    }
    
    .making-theme {
      background: linear-gradient(to bottom right, #ecfdf5, #d1fae5);
    }
    
    .custom-scrollbar::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    
    .custom-scrollbar::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }
    
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: #c5c5c5;
      border-radius: 10px;
    }
    
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }
    
    .selected-item {
      background-color: #eff6ff;
      color: #1d4ed8;
      font-weight: 600;
    }
    
    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0, 0, 0, 0.5);
    }
    
    .modal-content {
      background-color: #fefefe;
      margin: 10% auto;
      padding: 1.25rem;
      border-radius: 0.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      width: 80%;
      max-width: 600px;
    }
    
    .close {
      color: #aaa;
      float: right;
      font-size: 1.25rem;
      font-weight: bold;
    }
    
    .close:hover,
    .close:focus {
      color: black;
      text-decoration: none;
      cursor: pointer;
    }
    
    .cropper-container {
      max-height: 400px;
      overflow: hidden;
    }
    
    .btn-primary {
      background-color: #4f46e5;
      color: white;
      padding: 0.375rem 0.75rem;
      border-radius: 0.375rem;
      font-size: 0.875rem;
      font-weight: 500;
    }
    
    .btn-primary:hover {
      background-color: #4338ca;
    }
    
    .btn-secondary {
      background-color: #e5e7eb;
      color: #374151;
      padding: 0.375rem 0.75rem;
      border-radius: 0.375rem;
      font-size: 0.875rem;
      font-weight: 500;
    }
    
    .btn-secondary:hover {
      background-color: #d1d5db;
    }
    
    /* Status badges */
    .status-available {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .status-pending {
      background-color: #fef3c7;
      color: #92400e;
    }
    
    .status-sold {
      background-color: #fee2e2;
      color: #b91c1c;
    }
    
    /* Alert Modal */
    .alert-modal {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 1100;
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      padding: 1.5rem;
      max-width: 400px;
      width: 90%;
    }
    
    .alert-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1050;
    }
    
    .alert-success {
      border-top: 4px solid #10b981;
    }
    
    .alert-error {
      border-top: 4px solid #ef4444;
    }
    
    .alert-warning {
      border-top: 4px solid #f59e0b;
    }
    
    .alert-info {
      border-top: 4px solid #3b82f6;
    }
    
    /* Item Details Modal */
    .item-details-modal {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 1000;
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      padding: 1.5rem;
      max-width: 800px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
    }
    
    /* Quick Note Section */
    .quick-note-section {
      background-color: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 0.5rem;
      padding: 0.75rem;
      margin-top: 0.5rem;
    }
    
    .quick-note-section.active {
      border-color: #93c5fd;
      background-color: #eff6ff;
    }
    
    /* Toast Notification */
    .toast-container {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 9999;
    }
    
    .toast {
      padding: 0.75rem 1rem;
      border-radius: 0.375rem;
      margin-bottom: 0.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      display: flex;
      align-items: center;
      animation: slideIn 0.3s ease-out forwards;
      max-width: 300px;
    }
    
    .toast-success {
      background-color: #d1fae5;
      border-left: 4px solid #10b981;
      color: #065f46;
    }
    
    .toast-error {
      background-color: #fee2e2;
      border-left: 4px solid #ef4444;
      color: #b91c1c;
    }
    
    .toast-warning {
      background-color: #fef3c7;
      border-left: 4px solid #f59e0b;
      color: #92400e;
    }
    
    .toast-info {
      background-color: #dbeafe;
      border-left: 4px solid #3b82f6;
      color: #1e40af;
    }
    
    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    
    @keyframes fadeOut {
      from {
        opacity: 1;
      }
      to {
        opacity: 0;
      }
    }
    
    /* Inventory Stats Card */
    .inventory-stats-card {
      background: linear-gradient(to right, #f0f9ff, #e0f2fe);
      border-radius: 0.5rem;
      padding: 0.75rem;
      margin-bottom: 1rem;
      border: 1px solid #bae6fd;
    }
    
    .stats-title {
      font-size: 0.75rem;
      font-weight: 600;
      color: #0369a1;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 0.5rem;
    }
    
    .stat-item {
      background-color: white;
      border-radius: 0.375rem;
      padding: 0.5rem;
      text-align: center;
      border: 1px solid #e5e7eb;
    }
    
    .stat-value {
      font-size: 0.875rem;
      font-weight: 600;
      color: #1e40af;
    }
    
    .stat-label {
      font-size: 0.7rem;
      color: #6b7280;
    }
  </style>
</head>
<body data-firm-id="<?php echo $firm_id; ?>" class="relative">
  <!-- Sidebar Overlay -->
  <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>
  <!-- Mobile Toggle Button -->
  <button id="mobile-toggle" class="mobile-toggle">
      <i class="ri-menu-line text-xl"></i>
  </button>
  <!-- Sidebar -->
  <aside id="sidebar" class="sidebar">
      <div class="logo-section">
          <div class="logo-container">
              <div class="logo-icon">
                  <i class="ri-vip-diamond-fill text-white text-xl"></i>
              </div>
              <div class="flex flex-col">
                  <span class="logo-text"><?php echo htmlspecialchars($userInfo['FirmName'] ?? ''); ?></span>
                  <span class="text-xs text-gray-400">Jewelry Store Management</span>
              </div>
          </div>
          <button id="collapse-toggle" class="text-gray-400 hover text-white transition-colors">
              <i class="ri-arrow-left-s-line text-xl"></i>
          </button>
      </div>
      <nav class="sidebar-nav">
          <div class="menu-category">Dashboard</div>
          <a href="dashborad.php" class="menu-item">
              <i class="ri-dashboard-line menu-icon"></i>
              <span class="menu-text">Overview</span>
              <div class="menu-tooltip">Store Overview</div>
          </a>
          <div class="menu-category">Inventory</div>
          <a href="add-stock.php" class="menu-item">
              <i class="ri-archive-line menu-icon"></i>
              <span class="menu-text">Add Stock</span>
              <div class="menu-tooltip">Manage Store Stock</div>
          </a>
          <a href="stock-entry.php" class="menu-item">
              <i class="ri-edit-2-line menu-icon"></i>
              <span class="menu-text">Stock Entry</span>
              <div class="menu-tooltip">Stock Entry Form</div>
          </a>
          <a href="add-product.php" class="menu-item active">
              <i class="ri-add-circle-line menu-icon"></i>
              <span class="menu-text">Add New Product</span>
              <div class="menu-tooltip">Add Jewelry to Stock</div>
          </a>
          <a href="inventory_reports.php" class="menu-item">
              <i class="ri-file-chart-line menu-icon"></i>
              <span class="menu-text">Stock Reports</span>
              <div class="menu-tooltip">View Stock Reports</div>
          </a>
          <div class="menu-category">Sales</div>
          <a href="sell.php" class="menu-item">
              <i class="ri-shopping-cart-line menu-icon"></i>
              <span class="menu-text">Sale</span>
              <div class="menu-tooltip">Manage Sales </div>
          </a>
          <a href="sales_reports.php" class="menu-item">
              <i class="ri-file-chart-line menu-icon"></i>
              <span class="menu-text">Sales Reports</span>
              <div class="menu-tooltip">View Sales Reports</div>
          </a>
          <div class="menu-category">Accounting</div>
          <a href="accounts.php" class="menu-item">
              <i class="ri-wallet-line menu-icon"></i>
              <span class="menu-text">Account Overview</span>
              <div class="menu-tooltip">Track Store Accounts</div>
          </a>
          <a href="transactions.php" class="menu-item">
              <i class="ri-money-dollar-circle-line menu-icon"></i>
              <span class="menu-text">Transactions</span>
              <div class="menu-tooltip">View Store Transactions</div>
          </a>
          <a href="expense_reports.php" class="menu-item">
              <i class="ri-file-chart-line menu-icon"></i>
              <span class="menu-text">Expenses</span>
              <div class="menu-tooltip">View Expenses</div>
          </a>
          <div class="menu-category">Customers</div>
          <a href="customer_list.php" class="menu-item">
              <i class="ri-group-line menu-icon"></i>
              <span class="menu-text">Customer List</span>
              <div class="menu-tooltip">Manage Customer Details</div>
          </a>
          <a href="customer_orders.php" class="menu-item">
              <i class="ri-file-list-line menu-icon"></i>
              <span class="menu-text">Customer Orders</span>
              <div class="menu-tooltip">View Customer Orders</div>
          </a>
          <div class="menu-category">Reports</div>
          <a href="daily_reports.php" class="menu-item">
              <i class="ri-calendar-check-line menu-icon"></i>
              <span class="menu-text">Daily Reports</span>
              <div class="menu-tooltip">View Daily Reports</div>
          </a>
          <a href="monthly_reports.php" class="menu-item">
              <i class="ri-calendar-line menu-icon"></i>
              <span class="menu-text">Monthly Reports</span>
              <div class="menu-tooltip">View Monthly Reports</div>
          </a>
          <div class="menu-category">Settings</div>
          <a href="settings.php" class="menu-item">
              <i class="ri-settings-3-line menu-icon"></i>
              <span class="menu-text">Store Settings</span>
              <div class="menu-tooltip">Manage Store Settings</div>
          </a>
          <a href="user_management.php" class="menu-item">
              <i class="ri-user-line menu-icon"></i>
              <span class="menu-text">User Management</span>
              <div class="menu-tooltip">Manage Store Users</div>
          </a>
      </nav>
  </aside>
  <!-- Main Content Area -->
  <main id="main-content" class="transition-all duration-300">
      <!-- Header -->
      <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
          <div class="flex flex-col md:flex-row md:items-center justify-between p-4 gap-4">
              <!-- Left Section -->
              <div class="flex items-center gap-4">
                  <button id="sidebar-toggle" class="p-2 hover:bg-gray-100 rounded-xl lg:hidden">
                      <i class="ri-menu-line text-xl"></i>
                  </button>
                  <div class="flex items-center gap-4">
                      <div class="flex-shrink-0">
                          <div id="logoToggle" class="flex-shrink-0 cursor-pointer">
                              <img src="../uploads/logo.png" alt="Logo" class="h-14 w-auto">
                          </div>
                      </div>
                      <div class="text-gray-600">
                          <span class="text-sm font-semibold"><?php echo htmlspecialchars($userInfo['FirmName'] ?? ''); ?></span>
                      </div>
                      <div class="relative flex-1 md:w-96">
                          <input type="text" placeholder="Search products, orders, customers..." class="header-search w-full pl-12 pr-4 py-2.5 text-sm focus:outline-none">
                          <i class="ri-search-line absolute left-4 top-3 text-gray-400"></i>
                      </div>
                  </div>
              </div>
              <!-- Right Section -->
              <div class="flex items-center gap-4">
                  <!-- Notifications -->
                  <div class="relative">
                      <button id="notifications-toggle" class="p-2 hover:bg-gray-100 rounded-xl relative">
                          <i class="ri-notification-3-line text-xl"></i>
                          <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                      </button>
                      <!-- Notifications Dropdown -->
                      <div id="notifications-dropdown" class="notification-dropdown">
                          <div class="p-4 border-b">
                              <h3 class="font-semibold">Notifications</h3>
                          </div>
                          <div class="max-h-[400px] overflow-y-auto">
                              <div class="p-4 border-b hover:bg-gray-50">
                                  <div class="flex items-start gap-3">
                                      <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                          <i class="ri-shopping-bag-line text-green-500"></i>
                                      </div>
                                      <div>
                                          <p class="text-sm font-medium">New order received</p>
                                          <p class="text-xs text-gray-500 mt-1">Order #45678 needs processing</p>
                                          <p class="text-xs text-gray-400 mt-1">5 minutes ago</p>
                                      </div>
                                  </div>
                              </div>
                              <div class="p-4 border-b hover:bg-gray-50">
                                  <div class="flex items-start gap-3">
                                      <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                                          <i class="ri-alert-line text-amber-500"></i>
                                      </div>
                                      <div>
                                          <p class="text-sm font-medium">Low stock alert</p>
                                          <p class="text-xs text-gray-500 mt-1">Diamond rings (2mm) running low</p>
                                          <p class="text-xs text-gray-400 mt-1">1 hour ago</p>
                                      </div>
                                  </div>
                              </div>
                          </div>
                          <div class="p-4 text-center">
                              <button class="text-sm text-amber-500 hover:text-amber-600">View All Notifications</button>
                          </div>
                      </div>
                  </div>
                  <!-- Quick Actions -->
                  <button class="p-2 hover:bg-gray-100 rounded-xl">
                      <i class="ri-add-circle-line text-xl"></i>
                  </button>
                  <!-- Profile -->
                  <div class="relative group">
                      <button class="flex items-center gap-3 p-2 hover:bg-gray-100 rounded-xl" onclick="toggleProfileMenu(event)">
                          <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center text-white font-semibold">
                              <?php echo strtoupper(substr($userInfo['Name'] ?? '', 0, 2)); ?>
                          </div>
                          <div class="hidden md:block text-left">
                              <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($userInfo['Name'] ?? ''); ?></p>
                              <p class="text-xs text-gray-500">Administrator</p>
                          </div>
                          <i class="ri-arrow-down-s-line text-gray-400"></i>
                      </button>
                      <!-- Enhanced Profile Dropdown Menu -->
                      <div id="profileMenu" class="hidden absolute right-0 mt-2 w-56 rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                          <div class="py-1">
                              <div class="px-4 py-3 border-b border-gray-100">
                                  <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($userInfo['Name'] ?? ''); ?></p>
                                  <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($userInfo['FirmName'] ?? ''); ?></p>
                              </div>
                              <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                  <i class="ri-user-line w-5 h-5 mr-3 text-gray-400"></i>
                                  My Profile
                              </a>
                              <div class="border-t border-gray-100 my-1"></div>
                              <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                  <i class="ri-logout-box-line w-5 h-5 mr-3 text-red-500"></i>
                                  Logout
                              </a>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </header>
                    <!-- Main Content Area -->
          <div class="w-full">
            <!-- Toast Container -->
            <div id="toastContainer" class="toast-container"></div>
            <!-- Desktop/Tablet Layout -->
            <div class="flex flex-col lg:flex-row gap-4">
                      <!-- Left Side - Entry Form -->
              <div class="form-container w-full lg:w-1/2">
                <div class="bg-white p-4 rounded-xl shadow-lg border border-gray-100 mb-4 hover-card">
          <div class="flex justify-between items-center mb-2">
            <h2 class="text-lg font-bold text-gray-800 flex items-center">
              <i class="fas fa-plus-circle text-blue-600 mr-2"></i> Add New Jewelry Product
            </h2>
            <!-- Removed Source Selection Tabs here -->
          </div>
          
          <form id="jewelryForm">
            <input type="hidden" id="itemId" name="itemId" value="">
            
            <!-- Source Details Section -->
            <div class="section-card source-section bg-gradient-to-br from-green-50 to-green-100 mb-2">
              <div class="section-title text-emerald-700 flex items-center justify-between">
                <div class="flex items-center">
                  <i class="fas fa-user-tie"></i> Source Details
                </div>
                <div id="sourcePreservedIndicator" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded hidden">
                  <i class="fas fa-lock mr-1"></i> Preserved
                </div>
              </div>
              <div class="field-row">
                <div class="field-col">
                  <div class="field-label">Source Type</div>
                  <div class="field-container">
                    <select id="sourceTypeSelect" name="sourceType" class="input-field font-medium py-0.5 pl-6 pr-1 appearance-none bg-white border border-gray-200 hover:border-emerald-300 focus:border-emerald-400 rounded-md">
                         <option value="Purchase">Purchase</option>
                      <option value="Manufacturing Order">Manufacturing Order</option>
                     
                      <option value="Others">Others / Manual Entry</option>
                    </select>
                    <i class="fas fa-tag field-icon text-emerald-500"></i>
                  </div>
                </div>
                <div class="field-col">
                  <div class="field-label">Source ID</div>
                  <div class="field-container relative">
                    <input type="text" 
                           id="sourceId" 
                           name="sourceId"
                           class="input-field font-medium py-0.5 pl-6 bg-white border border-gray-200 hover:border-emerald-300 focus:border-emerald-400 rounded-md" 
                           placeholder="Search source...">
                    <i class="fas fa-hashtag field-icon text-emerald-500"></i>
                    <div id="sourceIdSuggestions" 
                         class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg hidden max-h-48 overflow-y-auto custom-scrollbar">
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Source Info Display (read-only) -->
             <div id="sourceInfoDisplay" class="bg-gradient-to-br from-white via-blue-50 to-blue-100 p-1 rounded-xl border-2 border-dashed border-blue-300 shadow-sm mt-1 hidden">
  <div class="text-sm font-semibold text-blue-700 mb-1 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m4 4h.01M12 20a8 8 0 100-16 8 8 0 000 16z" />
      </svg>
      Source Information
    </div>
    <button id="resetSourceBtn" type="button" class="text-xs bg-red-100 text-red-700 hover:bg-red-200 px-2 py-0.5 rounded flex items-center gap-1 transition-colors">
      <i class="fas fa-times text-xs"></i> Reset
    </button>
  </div>
  <div class="grid grid-cols-2 sm:grid-cols-3 gap-1 text-sm text-gray-700">
    <div><span class="text-gray-500">Name:</span> <span id="sourceNameDisplay" class="font-sm ml-1">-</span></div>
    <div><span class="text-gray-500">Invoice No:</span> <span id="sourceInvoiceNoDisplay" class="font-sm ml-1">-</span></div>
    <div><span class="text-gray-500">Type:</span> <span id="sourceTypeDisplay" class="font-sm ml-1">-</span></div>
    <div><span class="text-gray-500">Material:</span> <span id="sourceMaterialDisplay" class="font-sm ml-1">-</span></div>
    <div><span class="text-gray-500">Purity:</span> <span id="sourcePurityDisplay" class="font-sm ml-1">-</span></div>
    <div><span class="text-gray-500">Weight:</span> <span id="sourceWeightDisplay" class="font-sm ml-1">-</span></div>
    <div><span class="text-gray-500"></span> <span id="sourceStatusDisplay" class="font-sm ml-1">-</span></div>
  </div>
</div>

              
              <!-- Hidden fields to store source data -->
              <input type="hidden" id="sourceTypeHidden" name="sourceTypeHidden" value="Manufacturing Order">
              <input type="hidden" id="sourceName" name="sourceName" value="">
              <input type="hidden" id="sourceLocation" name="sourceLocation" value="">
              <input type="hidden" id="sourceMaterialType" name="sourceMaterialType" value="">
              <input type="hidden" id="sourcePurity" name="sourcePurity" value="">
              <input type="hidden" id="sourceWeight" name="sourceWeight" value="">
              <input type="hidden" id="sourceInventoryId" name="sourceInventoryId" value="">
              
            
            </div>
            
            <!-- Form Grid Layout -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <!-- Material Details Section -->
              <div class="section-card material-section material-theme rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                <div class="section-title text-amber-800">
                  <i class="fas fa-coins"></i> Material
                </div>
                <div class="field-row">
                  <div class="field-col">
                    <div class="field-label">Material</div>
                    <div class="field-container">
                      <select id="materialType" name="materialType" class="input-field font-medium py-0.5 pl-6 pr-1 appearance-none bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md">
                        <option value="Gold" selected>Gold</option>
                        <option value="Silver">Silver</option>
                        <option value="Platinum">Platinum</option>
                        <option value="White Gold">White Gold</option>
                        <option value="Rose Gold">Rose Gold</option>
                      </select>
                      <i class="fas fa-coins field-icon text-amber-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Purity</div>
                    <div class="field-container">
                      <input type="number" 
                             id="purity" 
                             name="purity"
                             class="input-field font-medium py-0.5 pl-6 bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md" 
                             placeholder="e.g. 92.0" 
                             step="0.1" 
                             min="0" 
                             max="100" />
                      <i class="fas fa-certificate field-icon text-amber-500"></i>
                    </div>
                  </div>
                </div>
                <div class="field-row">
                  <div class="field-col">
                    <div class="field-label">Jewelry Type</div>
                    <div class="field-container relative">
                      <input type="text" 
                             id="jewelryType" 
                             name="jewelryType"
                             class="input-field font-medium py-0.5 pl-6 bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md" 
                             placeholder="Select...">
                      <i class="fas fa-ring field-icon text-amber-500"></i>
                      <div id="jewelryTypeSuggestions" 
                           class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg hidden max-h-48 overflow-y-auto custom-scrollbar">
                      </div>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Name</div>
                    <div class="field-container ">
                      <input type="text" 
                             id="productName" 
                             name="productName"
                             class="input-field font-medium py-0.5 pl-6 bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md" 
                             placeholder="Name...">
                      <i class="fas fa-tag field-icon text-amber-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Size <span class="text-xs text-gray-400">(optional)</span></div>
                    <div class="field-container">
                      <input type="text" id="size" name="size" class="input-field font-medium py-0.5 pl-6 bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md" placeholder="Size..." />
                      <i class="fas fa-ruler field-icon text-amber-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Cost per Gram</div>
                    <div class="field-container">
                      <input type="number" id="costPerGram" name="costPerGram" class="input-field font-medium py-0.5 pl-6 bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md" placeholder="Auto" readonly />
                      <i class="fas fa-rupee-sign field-icon text-amber-500"></i>
                    </div>
                  </div>
                </div>
               
              </div>
              
              <!-- Weight Details Section -->
              <div class="section-card weight-section weight-theme rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                <div class="section-title text-blue-800">
                  <i class="fas fa-weight-scale"></i> Weight
                </div>
                <div class="field-row">
                  <div class="field-col">
                    <div class="field-label">Gross Weight (g)</div>
                    <div class="field-container">
                      <input type="number" id="grossWeight" name="grossWeight" class="input-field font-medium py-0.5 pl-6 bg-white border border-blue-200 hover:border-blue-300 focus:border-blue-400 rounded-md" placeholder="Gross" step="0.01" />
                      <i class="fas fa-weight-scale field-icon text-blue-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Less Weight (g)</div>
                    <div class="field-container">
                      <input type="number" id="lessWeight" name="lessWeight" class="input-field font-medium py-0.5 pl-6 bg-white border border-blue-200 hover:border-blue-300 focus:border-blue-400 rounded-md" placeholder="Less" step="0.01" />
                      <i class="fas fa-minus-circle field-icon text-red-500"></i>
                    </div>
                  </div>
                </div>
                <div class="field-row">
                  <div class="field-col">
                    <div class="field-label">Net Weight (g)</div>
                    <div class="field-container">
                      <input type="number" id="netWeight" name="netWeight" class="input-field font-medium py-0.5 pl-6 bg-white border border-blue-200 hover:border-blue-300 focus:border-blue-400 rounded-md" placeholder="Net" step="0.01" readonly />
                      <i class="fas fa-balance-scale field-icon text-green-500"></i>
                    </div>
                  </div>
                <div class="field-col">
                  <div class="field-label">Quantity</div>
                  <div class="field-container">
                    <input type="number" id="quantity" name="quantity" class="input-field font-medium py-0.5 pl-6 bg-white border border-gray-200 hover:border-emerald-300 focus:border-emerald-400 rounded-md" placeholder="Quantity" value="1" min="1" />
                    <i class="fas fa-sort-amount-up field-icon text-blue-500"></i>
                  </div>
                </div>
                <!-- Removed Tray Number field from here -->
             
                  
                </div>
                
                <div class="filed-row">
                     <div class="field-col">
                    <div class="field-label">Tray No</div>
                    <div class="field-container">
                      <input type="text" id="trayNo" name="trayNo" class="input-field font-medium py-0.5 pl-6 bg-white border border-blue-200 hover:border-blue-300 focus:border-blue-400 rounded-md" placeholder="Tray Number" />
                      <i class="fas fa-box field-icon text-blue-500"></i>
                    </div>
                  </div>
                    
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
              <!-- Stone Details Section -->
             <div class="section-card stone-section stone-theme rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                <div class="section-title text-purple-800">
                  <i class="fas fa-gem"></i> Stone
                </div>
                <div class="field-row">
                  <div class="field-col">
                    <div class="field-label">Stone Type</div>
                    <div class="field-container">
                      <select id="stoneType" name="stoneType" class="input-field font-medium py-0.5 pl-6 pr-1 appearance-none bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md">
                        <option value="None" selected>None</option>
                        <option value="Diamond">Diamond</option>
                        <option value="Ruby">Ruby</option>
                        <option value="Emerald">Emerald</option>
                        <option value="Sapphire">Sapphire</option>
                        <option value="Pearl">Pearl</option>
                        <option value="Mixed">Mixed</option>
                        <option value="Other">Other</option>
                      </select>
                      <i class="fas fa-gem field-icon text-purple-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Stone Weight</div>
                    <div class="field-container flex">
                      <input type="number" id="stoneWeight" name="stoneWeight" class="input-field font-medium py-0.5 pl-6 bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-l-md" placeholder="Weight" step="0.01" disabled />
                      <select id="stoneUnit" name="stoneUnit" class="font-medium py-0.5 px-1 bg-white border border-l-0 border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-r-md text-xs w-16" disabled>
                        <option value="ct">ct</option>
                        <option value="ratti">ratti</option>
                      </select>
                      <i class="fas fa-weight field-icon text-purple-500"></i>
                    </div>
                  </div>
                </div>
                <div class="field-row">
                  <div class="field-col">
                    <div class="field-label">Stone Color</div>
                    <div class="field-container">
                      <select id="stoneColor" name="stoneColor" class="input-field font-medium py-0.5 pl-6 pr-1 appearance-none bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md" disabled>
                        <option value="">Select Color</option>
                        <option value="D">D (Colorless)</option>
                        <option value="E">E (Colorless)</option>
                        <option value="F">F (Colorless)</option>
                        <option value="G">G (Near Colorless)</option>
                        <option value="H">H (Near Colorless)</option>
                        <option value="I">I (Near Colorless)</option>
                        <option value="J">J (Near Colorless)</option>
                        <option value="K">K (Faint)</option>
                        <option value="L">L (Faint)</option>
                        <option value="M">M (Faint)</option>
                        <option value="Red">Red</option>
                        <option value="Blue">Blue</option>
                        <option value="Green">Green</option>
                        <option value="Yellow">Yellow</option>
                        <option value="Pink">Pink</option>
                        <option value="Purple">Purple</option>
                        <option value="Other">Other</option>
                      </select>
                      <i class="fas fa-palette field-icon text-purple-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Stone Clarity</div>
                    <div class="field-container">
                      <select id="stoneClarity" name="stoneClarity" class="input-field font-medium py-0.5 pl-6 pr-1 appearance-none bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md" disabled>
                        <option value="">Select Clarity</option>
                        <option value="FL">FL (Flawless)</option>
                        <option value="IF">IF (Internally Flawless)</option>
                        <option value="VVS1">VVS1 (Very Very Slightly Included 1)</option>
                        <option value="VVS2">VVS2 (Very Very Slightly Included 2)</option>
                        <option value="VS1">VS1 (Very Slightly Included 1)</option>
                        <option value="VS2">VS2 (Very Slightly Included 2)</option>
                        <option value="SI1">SI1 (Slightly Included 1)</option>
                        <option value="SI2">SI2 (Slightly Included 2)</option>
                        <option value="I1">I1 (Included 1)</option>
                        <option value="I2">I2 (Included 2)</option>
                        <option value="I3">I3 (Included 3)</option>
                      </select>
                      <i class="fas fa-search field-icon text-purple-500"></i>
                    </div>
                  </div>
                </div>
                <div class="field-row">
                  <div class="field-col">
                    <div class="field-label">Stone Quality</div>
                    <div class="field-container">
                      <select id="stoneQuality" name="stoneQuality" class="input-field font-medium py-0.5 pl-6 pr-1 appearance-none bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md" disabled>
                        <option value="">Select Quality</option>
                        <option value="VVS">VVS</option>
                        <option value="VS">VS</option>
                        <option value="SI">SI</option>
                        <option value="I1">I1</option>
                        <option value="I2">I2</option>
                        <option value="I3">I3</option>
                        <option value="AAA">AAA</option>
                        <option value="AA">AA</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                      </select>
                      <i class="fas fa-star field-icon text-amber-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Stone Price</div>
                    <div class="field-container">
                      <input type="number" id="stonePrice" name="stonePrice" class="input-field font-medium py-0.5 pl-6 bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md" placeholder="Price" step="0.01" disabled />
                      <i class="fas fa-rupee-sign field-icon text-green-500"></i>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Making Charge Section -->
              <div class="section-card making-section making-theme rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                <div class="section-title text-green-800">
                  <i class="fas fa-hammer"></i> Making
                </div>
                <div class="field-row">
                  <div class="field-col">
                    <div class="field-label">Making Charge</div>
                    <div class="field-container">
                      <input type="number" id="makingCharge" name="makingCharge" class="input-field font-medium py-0.5 pl-6 bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md" placeholder="Charge" step="0.01" />
                      <i class="fas fa-rupee-sign field-icon text-green-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Charge Type</div>
                    <div class="field-container">
                      <select id="makingChargeType" name="makingChargeType" class="input-field font-medium py-0.5 pl-6 pr-1 appearance-none bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md">
                        <option value="fixed" selected>Fixed Amount</option>
                        <option value="percentage">Percentage</option>
                      </select>
                      <i class="fas fa-percent field-icon text-green-500"></i>
                    </div>
                  </div>
                </div>
                
                <div class="field-row">
                  <div class="field-col">
                    <div class="field-label">Status</div>
                    <div class="field-container">
                      <select id="status" name="status" class="input-field font-medium py-0.5 pl-6 pr-1 appearance-none bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md">
                        <option value="Available" selected>Available</option>
                        <option value="Pending">Pending</option>
                        <option value="Sold">Sold</option>
                      </select>
                      <i class="fas fa-tag field-icon text-green-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">HUID CODE</div>
                    <div class="field-container relative">
                      <input type="text" 
                             id="huidCode" 
                             name="huidCode"
                             class="input-field font-medium py-0.5 pl-6 bg-white border border-blue-200 hover:border-blue-300 focus:border-blue-400 rounded-md" 
                             placeholder="HUID Code..">
                      <i class="fas fa-tag field-icon text-blue-500"></i>
                    </div>
                  </div>
                </div>
                
                <!-- Images and Description Mini Row -->
                <div class="flex gap-1 mt-1">
                  <div class="field-col">
                    <label for="productImages" class="cursor-pointer flex items-center justify-center border border-blue-300 rounded-md py-0.5 px-2 bg-white text-blue-600 text-xs font-semibold w-full hover:bg-blue-50 transition-colors">
                      <i class="fas fa-camera mr-1 text-xs"></i> Add Images
                    </label>
                    <input type="file" id="productImages" name="images[]" accept="image/*" multiple class="hidden" />
                  </div>
                  <div class="field-col">
                    <button id="quickNoteBtn" type="button" class="cursor-pointer flex items-center justify-center border border-green-300 rounded-md py-0.5 px-2 bg-white text-green-600 text-xs font-semibold w-full hover:bg-green-50 transition-colors">
                      <i class="fas fa-comment-alt mr-1 text-xs"></i> Add Note
                    </button>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Image Preview Row -->
            <div id="imagePreviewContainer" class="mt-2 bg-white p-1 rounded-md border border-gray-200 min-h-8">
              <div id="imagePreview" class="flex flex-wrap gap-1"></div>
            </div>
            
            <!-- Quick Note Section (Inline instead of modal) -->
            <div id="quickNoteSection" class="quick-note-section mt-2 hidden">
              <div class="flex justify-between items-center mb-1">
                <div class="text-xs font-medium text-gray-700">
                  <i class="fas fa-comment-alt mr-1"></i> Quick Note
                </div>
                <button id="closeQuickNote" type="button" class="text-gray-400 hover:text-gray-600">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <textarea id="description" name="description" class="w-full text-xs font-medium py-1 pl-2 bg-white border border-gray-200 hover:border-green-300 focus:border-green-400 rounded-md" 
                        placeholder="Enter description or notes..." style="height: 60px; resize: vertical;"></textarea>
            </div>
         
            <!-- Inventory Update Option (only visible during edit) -->
            <div id="inventoryUpdateOption" class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg hidden">
              <div class="flex items-center">
                <input type="checkbox" id="updateInventory" name="updateInventory" class="mr-2">
                <label for="updateInventory" class="text-sm font-medium text-yellow-800">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  Update inventory metals when material/purity/weight changes
                </label>
              </div>
              <p class="text-xs text-yellow-600 mt-1">
                This will return the old material to inventory and deduct the new material from available stock.
              </p>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex gap-3 mt-4">
              <button id="clearForm" type="button" class="flex-1 flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold py-3 px-4 rounded-lg transition-colors">
                <i class="fas fa-eraser mr-2"></i> Clear Form
              </button>
              <button id="addItem" type="button" class="flex-1 flex items-center justify-center bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white text-sm font-semibold py-3 px-4 rounded-lg shadow-sm transition-all hover:shadow">
                <i class="fas fa-plus-circle mr-2"></i> Add Product
              </button>
            </div>
          </form>
        </div>
      </div>
      
              <!-- Right Side - Items Details -->
              <div class="list-container w-full lg:w-1/2">
                <!-- Inventory Stats Card -->
                <div class="inventory-stats-card mb-4">
          <div class="stats-title">
            <i class="fas fa-chart-pie mr-1"></i> Jewellery  Stock Stats
          </div>
          <div class="stats-grid">
            <?php foreach ($inventoryStats as $material => $stock): ?>
            <div class="stat-item">
              <div class="stat-value"><?php echo number_format($stock, 2); ?>g</div>
              <div class="stat-label"><?php echo htmlspecialchars($material); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($inventoryStats)): ?>
            <div class="stat-item">
              <div class="stat-value">0.00g</div>
              <div class="stat-label">No inventory data</div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        
                <!-- Items List Card -->
                <div class="bg-white p-4 rounded-xl shadow-lg border border-gray-100 mb-4 hover-card">
          <div class="flex justify-between items-center mb-2">
            <h2 class="text-lg font-bold text-gray-800 flex items-center">
              <i class="fas fa-list text-indigo-600 mr-2"></i> Inventory Items
            </h2>
            
            <div class="flex items-center gap-2">
              <div class="relative">
                <input type="text" 
                       id="searchItems" 
                       class="input-field py-2 pl-10 bg-white border border-gray-200 hover:border-indigo-300 focus:border-indigo-400 rounded-lg text-sm" 
                       placeholder="Search items...">
                <i class="fas fa-search field-icon text-gray-500 text-sm"></i>
              </div>
              
              <button id="exportBtn" class="bg-green-100 text-green-700 hover:bg-green-200 text-sm font-medium py-2 px-3 rounded-lg flex items-center">
                <i class="fas fa-file-export mr-2"></i> Export
              </button>
            </div>
          </div>

          <!-- Filter Bar -->
          <div class="bg-gray-50 p-1 rounded-md mb-2 flex flex-wrap items-center gap-1 text-xs">
            <span class="text-gray-500 font-medium">Filter:</span>
            
            <select id="filterMaterial" class="bg-white border border-gray-200 rounded py-0.5 px-1 text-xs">
              <option value="">All Materials</option>
              <?php foreach ($materialTypes as $type): ?>
                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
              <?php endforeach; ?>
            </select>
            
            <select id="filterJewelryType" class="bg-white border border-gray-200 rounded py-0.5 px-1 text-xs">
              <option value="">All Types</option>
              <?php foreach ($jewelryTypes as $type): ?>
                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
              <?php endforeach; ?>
            </select>
            
            <select id="filterSource" class="bg-white border border-gray-200 rounded py-0.5 px-1 text-xs">
              <option value="">All Sources</option>
              <option value="Supplier">Supplier</option>
              <option value="Karigar">Karigar</option>
              <option value="Other">Other</option>
            </select>
            
            <select id="filterStatus" class="bg-white border border-gray-200 rounded py-0.5 px-1 text-xs">
              <option value="">All Status</option>
              <?php foreach ($statusTypes as $type): ?>
                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
              <?php endforeach; ?>
            </select>
            
            <button id="resetFilters" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-1 py-0.5 rounded text-xs flex items-center">
              <i class="fas fa-undo-alt mr-1 text-xs"></i> Reset
            </button>
            
            <div class="ml-auto">
              <span id="itemCount" class="bg-indigo-100 text-indigo-800 px-1.5 py-0.5 rounded-full text-xs font-medium">0 items</span>
            </div>
          </div>

          <!-- Table Container with Shadow Effect -->
          <div class="table-container overflow-x-auto custom-scrollbar max-h-[calc(100vh-300px)]">
            <table id="itemsTable" class="min-w-full border-collapse">
              <thead>
                <tr class="bg-gray-50 text-gray-600 text-xs leading-normal">
                  <th class="py-1 px-2 text-left border-b border-gray-200 w-12 sm:w-16">ID</th>
                  <th class="py-1 px-2 text-left border-b border-gray-200">Item</th>
                  <th class="py-1 px-2 text-left border-b border-gray-200 w-20">Weight</th>
                  <th class="py-1 px-2 text-left border-b border-gray-200 w-16">Source</th>
                  <th class="py-1 px-2 text-left border-b border-gray-200 w-16">Status</th>
                  <th class="py-1 px-2 text-center border-b border-gray-200 w-20">Actions</th>
                </tr>
              </thead>
              <tbody class="text-gray-600">
                <!-- Items will be populated here via JavaScript -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
          </div>
      </div>
  </div>
  </main>
  
  <!-- Alert Modal -->
  <div id="alertOverlay" class="alert-overlay hidden"></div>
  <div id="alertModal" class="alert-modal hidden">
    <div class="flex items-start">
      <div id="alertIcon" class="mr-3 text-xl"></div>
      <div class="flex-1">
        <h3 id="alertTitle" class="font-bold text-sm mb-1"></h3>
        <p id="alertMessage" class="text-sm text-gray-600"></p>
      </div>
    </div>
    <div class="flex justify-end mt-4">
      <button id="alertClose" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-1 px-3 rounded text-sm">
        Close
      </button>
    </div>
  </div>
  
  <!-- Item Details Modal -->
  <div id="itemDetailsOverlay" class="alert-overlay hidden"></div>
  
  
  <div id="itemDetailsModal" class="item-details-modal hidden">
    <div class="flex justify-between items-center mb-3">
      <h2 class="text-lg font-bold text-gray-800 flex items-center">
        <i class="fas fa-info-circle text-purple-600 mr-2"></i> 
        <span id="modalItemName">Item Details</span>
      </h2>
      <div class="flex items-center gap-2">
        <button id="modalEditBtn" class="bg-blue-100 text-blue-700 hover:bg-blue-200 text-xs font-medium py-1 px-2 rounded flex items-center">
          <i class="fas fa-edit mr-1"></i> Edit
        </button>
        <button id="modalPrintBtn" class="bg-purple-100 text-purple-700 hover:bg-purple-200 text-xs font-medium py-1 px-2 rounded flex items-center">
          <i class="fas fa-print mr-1"></i> Print
        </button>
        <button id="modalCloseBtn" class="text-gray-400 hover:text-gray-600 text-lg">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
    
    <!-- Item Details Content -->
    <div class="p-3 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-100">
      <!-- Item Header -->
      <div class="flex items-center mb-3">
        <div class="w-16 h-16 bg-white rounded-md overflow-hidden mr-3 border border-gray-200">
          <img id="modalItemImage" src="/placeholder.svg" alt="" class="w-full h-full object-cover">
        </div>
        <div>
          <h3 id="modalItemTitle" class="font-bold text-lg text-gray-800"></h3>
          <div class="flex items-center gap-2">
            <span id="modalItemId" class="text-sm text-gray-500"></span>
            <div class="h-3 w-px bg-gray-300"></div>
            <span id="modalItemDate" class="text-sm text-gray-500"></span>
          </div>
          <div class="mt-1">
            <span id="modalItemStatus" class="text-xs px-2 py-0.5 rounded-full"></span>
          </div>
        </div>
      </div>
      
      <!-- Details Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
        <!-- Material Column -->
        <div class="bg-white p-3 rounded-md border border-amber-200">
          <div class="text-amber-800 font-semibold mb-2 flex items-center">
            <i class="fas fa-coins mr-1 text-amber-500"></i> Material
          </div>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-gray-600">Type:</span>
              <span id="modalMaterial" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span id="modalHUID" class="font-medium"></span>
            </div>
          </div>
        </div>
        
        <!-- Weight Column -->
        <div class="bg-white p-3 rounded-md border border-blue-200">
          <div class="text-blue-800 font-semibold mb-2 flex items-center">
            <i class="fas fa-weight-scale mr-1 text-blue-500"></i> Weight
          </div>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-gray-600">Gross:</span>
              <span id="modalGrossWeight" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Less:</span>
              <span id="modalLessWeight" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Net:</span>
              <span id="modalNetWeight" class="font-medium"></span>
            </div>
          </div>
        </div>
        
        <!-- Source Column -->
        <div class="bg-white p-3 rounded-md border border-green-200">
          <div class="text-green-800 font-semibold mb-2 flex items-center">
            <i class="fas fa-user-tie mr-1 text-green-500"></i> Source
          </div>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-gray-600">Type:</span>
              <span id="modalSourceType" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Name:</span>
              <span id="modalSourceName" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span id="modalInvoiceNo" class="font-medium"></span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Additional Details Row -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 text-sm">
        <!-- Stone Details -->
        <div class="bg-white p-3 rounded-md border border-purple-200">
          <div class="text-purple-800 font-semibold mb-2 flex items-center">
            <i class="fas fa-gem mr-1 text-purple-500"></i> Stone Details
          </div>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-gray-600">Type:</span>
              <span id="modalStoneType" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Weight:</span>
              <span id="modalStoneWeight" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Quality:</span>
              <span id="modalStoneQuality" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Price:</span>
              <span id="modalStonePrice" class="font-medium"></span>
            </div>
          </div>
        </div>
        
        <!-- Making Charge -->
        <div class="bg-white p-3 rounded-md border border-green-200">
          <div class="text-green-800 font-semibold mb-2 flex items-center">
            <i class="fas fa-hammer mr-1 text-green-500"></i> Making Details
          </div>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-gray-600">Charge:</span>
              <span id="modalMakingCharge" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Type:</span>
              <span id="modalMakingType" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Tray No:</span>
              <span id="modalTrayNo" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Quantity:</span>
              <span id="modalQuantity" class="font-medium"></span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Item Images Gallery -->
      <div class="mt-3">
        <div class="text-sm text-gray-700 font-medium mb-2 flex items-center">
          <i class="fas fa-images mr-1 text-indigo-500"></i> Product Images
        </div>
        <div id="modalImageGallery" class="flex flex-wrap gap-2">
          <!-- Images will be populated here -->
        </div>
      </div>
      
      <!-- Notes Section -->
      <div class="mt-3 bg-white p-3 rounded-md border border-gray-200">
        <div class="text-sm text-gray-700 font-medium mb-2 flex items-center">
          <i class="fas fa-comment-alt mr-1 text-gray-500"></i> Notes
        </div>
        <p id="modalNotes" class="text-sm text-gray-700"></p>
      </div>
    </div>
  </div>
  
  <!-- Delete Confirmation Modal -->
  <div id="deleteModalOverlay" class="alert-overlay hidden"></div>
  <div id="deleteModal" class="alert-modal hidden">
    <div class="flex items-start mb-4">
      <div class="text-red-500 mr-3 text-xl">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <div class="flex-1">
        <h3 class="font-bold text-sm mb-1">Confirm Delete</h3>
        <p class="text-sm text-gray-600">Are you sure you want to delete this item? This action cannot be undone.</p>
      </div>
    </div>
    <div class="flex justify-end gap-2">
      <button id="cancelDelete" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-1 px-3 rounded text-sm">
        Cancel
      </button>
      <button id="confirmDelete" class="bg-red-500 hover:bg-red-600 text-white font-medium py-1 px-3 rounded text-sm">
        Delete
      </button>
    </div>
  </div>

  <script src="../js/add-product.js"></script>
</body>
</html>

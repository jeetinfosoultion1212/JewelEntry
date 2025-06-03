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
// Additional debugging for database connection
if ($conn->connect_error) {
   error_log("Database connection failed: " . $conn->connect_error);
   die("Connection failed: " . $conn->connect_error);
}
error_log("Database connection successful");
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

// Debug function to log errors without breaking JSON responses
function debug_log($message, $data = null) {
  $log_file = 'jewelry_debug.log';
  $timestamp = date('Y-m-d H:i:s');
  $log_message = "[$timestamp] $message";
  
  if ($data !== null) {
      $log_message .= ": " . json_encode($data);
  }
  
  file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

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

// Function to get manufacturing orders - FIXED THE SQL QUERY
function getManufacturingOrders($conn, $search = '', $firm_id, $purity = null) {
  try {
      $sql = "SELECT mo.id, mo.karigar_id, k.name as karigar_name, mo.expected_weight, mo.purity_out, mo.status
              FROM manufacturing_orders mo
              JOIN karigars k ON mo.karigar_id = k.id
              WHERE mo.firm_id = ? AND (mo.purity_out LIKE ? OR k.name LIKE ?)";
      
      // Add purity filter if provided
      if ($purity !== null) {
          $sql .= " AND mo.purity_out = ?";
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
              im.inventory_id, im.remaining_stock, 
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


// Function to save captured image
function saveBase64Image($base64Image, $productId) {
  try {
      $uploadDir = 'uploads/jewelry/';
      
      // Create directory if it doesn't exist
      if (!file_exists($uploadDir)) {
          mkdir($uploadDir, 0777, true);
      }
      
      // Remove the data URI scheme part
      $base64Image = preg_replace('#^data:image/\w+;base64,#i', '', $base64Image);
      
      // Decode the base64 string
      $imageData = base64_decode($base64Image);
      
      // Generate unique filename
      $newFileName = $productId . '_' . time() . '_captured.jpg';
      $targetFilePath = $uploadDir . $newFileName;
      
      // Save the image
      if (file_put_contents($targetFilePath, $imageData)) {
          return $targetFilePath;
      } else {
          return false;
      }
  } catch (Exception $e) {
      debug_log("Error saving captured image", $e->getMessage());
      return false;
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
          
          // Handle captured image if any
          $capturedImage = $_POST['capturedImage'] ?? null;

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
                  stone_type, stone_weight, stone_unit, stone_color, stone_clarity, stone_quality, stone_price,
                  making_charge, making_charge_type, description, status, 
                  quantity, supplier_id, karigar_id, created_at
              ) VALUES (
                  ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
              )";
              
              $stmt = $conn->prepare($sql);
              
              $stmt->bind_param(
                  "issssssddddssssssdssssiis",
                  $firm_id, $product_id, $jewelryType, $productName, $materialType,
                  $purity, $trayNo, $huidCode, $grossWeight, $lessWeight, $netWeight,
                  $stoneType, $stoneWeight, $stoneUnit, $stoneColor, $stoneClarity, $stoneQuality, $stonePrice,
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
              
              // Handle captured image if any
              if (!empty($capturedImage)) {
                  $imagePath = saveBase64Image($capturedImage, $product_id);
                  if ($imagePath) {
                      // Insert into product_image table
                      $imgSql = "INSERT INTO jewellery_product_image (product_id, image_url, is_primary) 
                                VALUES (?, ?, 1)";
                      $imgStmt = $conn->prepare($imgSql);
                      $imgStmt->bind_param("is", $jewelryItemId, $imagePath);
                      $imgStmt->execute();
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
                          $isPrimary = (empty($capturedImage) && $i === 0) ? 1 : 0; // First image is primary if no captured image
                          
                          $imgSql = "INSERT INTO jewellery_product_image (product_id, image_url, is_primary) 
                                    VALUES (?, ?, ?)";
                          $imgStmt = $conn->prepare($imgSql);
                          $imgStmt->bind_param("isi", $jewelryItemId, $targetFilePath, $isPrimary);
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
              $stoneUnit = $stoneType !== 'None' ? $_POST['stoneUnit'] : '';
              $stoneColor = $stoneType !== 'None' ? $_POST['stoneColor'] : '';
              $stoneClarity = $stoneType !== 'None' ? $_POST['stoneClarity'] : '';
              $stoneQuality = $stoneType !== 'None' ? $_POST['stoneQuality'] : '';
              $stonePrice = $stoneType !== 'None' ? (float)$_POST['stonePrice'] : 0;
              $makingCharge = (float)$_POST['makingCharge'];
              $makingChargeType = $_POST['makingChargeType'];
              $description = $_POST['description'];
              $status = $_POST['status'] ?? 'Available';
              $quantity = (int)$_POST['quantity'];
              
              // Handle captured image if any
              $capturedImage = $_POST['capturedImage'] ?? null;
              
              // Set supplier_id and karigar_id based on source type
              $supplierId = 0;
              $karigarId = 0;
              if ($_POST['sourceType'] === 'Supplier') {
                  $supplierId = (int)$_POST['sourceId'];
              } elseif ($_POST['sourceType'] === 'Karigar') {
                  $karigarId = (int)$_POST['sourceId'];
              }

              // Get the current item details before update
              $currentItemSql = "SELECT material_type, purity, net_weight, status, product_id FROM jewellery_items WHERE id = ?";
              $currentItemStmt = $conn->prepare($currentItemSql);
              $currentItemStmt->bind_param("i", $itemId);
              $currentItemStmt->execute();
              $currentItemResult = $currentItemStmt->get_result();
              $currentItem = $currentItemResult->fetch_assoc();

              // Check if inventory should be updated
              $updateInventory = isset($_POST['updateInventory']) && $_POST['updateInventory'] === 'on';

              // Begin transaction
              $conn->begin_transaction();

              // Update inventory if checkbox is checked and there are changes
              if ($updateInventory) {
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

                  // Deduct new weight from inventory
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
                      stone_unit = ?,
                      stone_color = ?,
                      stone_clarity = ?,
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
                  "ssssssdddssssssdsssiiisi",
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
                  $stoneUnit,
                  $stoneColor,
                  $stoneClarity,
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
              
              // Handle captured image if any
              if (!empty($capturedImage)) {
                  $imagePath = saveBase64Image($capturedImage, $currentItem['product_id']);
                  if ($imagePath) {
                      // Check if we should make this the primary image
                      $isPrimary = isset($_POST['make_captured_primary']) && $_POST['make_captured_primary'] === 'true' ? 1 : 0;
                      
                      // If this should be primary, update all other images to not be primary
                      if ($isPrimary) {
                          $updatePrimarySql = "UPDATE jewellery_product_image SET is_primary = 0 WHERE product_id = ?";
                          $updatePrimaryStmt = $conn->prepare($updatePrimarySql);
                          $updatePrimaryStmt->bind_param("i", $itemId);
                          $updatePrimaryStmt->execute();
                      }
                      
                      // Insert into product_image table
                      $imgSql = "INSERT INTO jewellery_product_image (product_id, image_url, is_primary) 
                                VALUES (?, ?, ?)";
                      $imgStmt = $conn->prepare($imgSql);
                      $imgStmt->bind_param("isi", $itemId, $imagePath, $isPrimary);
                      $imgStmt->execute();
                  }
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
          
                      $newFileName = $currentItem['product_id'] . '_' . time() . '_' . $i . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
                      $targetFilePath = $uploadDir . $newFileName;
          
                      if (move_uploaded_file($tmpName, $targetFilePath)) {
                          $isPrimary = ($i === 0 && !isset($_POST['keep_images']) && empty($capturedImage)) ? 1 : 0;
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
      elseif ($_POST['action'] === 'save_captured_image') {
          $capturedImage = $_POST['imageData'] ?? null;
          $productId = $_POST['productId'] ?? null;
          $itemId = $_POST['itemId'] ?? null;
          
          if (empty($capturedImage) || empty($productId)) {
              $response['success'] = false;
              $response['message'] = "Missing image data or product ID";
          } else {
              $imagePath = saveBase64Image($capturedImage, $productId);
              
              if ($imagePath) {
                  // If itemId is provided, this is for an existing item
                  if (!empty($itemId)) {
                      $isPrimary = isset($_POST['isPrimary']) && $_POST['isPrimary'] === 'true' ? 1 : 0;
                      
                      // If this should be primary, update all other images to not be primary
                      if ($isPrimary) {
                          $updatePrimarySql = "UPDATE jewellery_product_image SET is_primary = 0 WHERE product_id = ?";
                          $updatePrimaryStmt = $conn->prepare($updatePrimarySql);
                          $updatePrimaryStmt->bind_param("i", $itemId);
                          $updatePrimaryStmt->execute();
                      }
                      
                      // Insert into product_image table
                      $imgSql = "INSERT INTO jewellery_product_image (product_id, image_url, is_primary) 
                                VALUES (?, ?, ?)";
                      $imgStmt = $conn->prepare($imgSql);
                      $imgStmt->bind_param("isi", $itemId, $imagePath, $isPrimary);
                      $imgStmt->execute();
                      
                      $response['success'] = true;
                      $response['message'] = "Image captured and saved successfully!";
                      $response['data'] = [
                          'image_url' => $imagePath,
                          'is_primary' => $isPrimary
                      ];
                  } else {
                      // For new items, just return the path to be included in the form submission
                      $response['success'] = true;
                      $response['message'] = "Image captured successfully!";
                      $response['data'] = [
                          'image_url' => $imagePath
                      ];
                  }
              } else {
                  $response['success'] = false;
                  $response['message'] = "Failed to save captured image";
              }
          }
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
<title>Jewelry Management System</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<link rel="stylesheet" href="css/add.css">
</head>
<body data-firm-id="<?php echo $firm_id; ?>" class="text-gray-800">
<!-- Header -->
 <div class="header-gradient p-1 text-white shadow-lg sticky top-0 z-50">
  <div class="enhanced-header">
    <div class="flex items-center">
      <i class="fas fa-gem mr-2 text-xl"></i>
      <div>
        <div class="font-bold text-sm"><?php echo htmlspecialchars($userInfo['FirmName']); ?></div>
        <div class="text-xs opacity-80">JewelEntry 0.02</div>
      </div>
    </div>
    <div class="user-info">
      <div class="text-right">
        <div class="font-medium text-xs"><?php echo htmlspecialchars($userInfo['Name']); ?></div>
        <div class="text-xs opacity-80"><?php echo htmlspecialchars($userInfo['Role']); ?></div>
      </div>
      <img src="<?php echo !empty($userInfo['image_path']) ? htmlspecialchars($userInfo['image_path']) : 'assets/default-avatar.png'; ?>" 
           alt="User" class="user-avatar">
    </div>
  </div>
</div>


<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Tab Menu -->
<div class="mb-1">
  <div class="flex border-b border-gray-200 bg-white sticky top-8 z-40">
  <button id="formTab" class="tab-btn flex-1 py-2 px-2 text-center font-medium text-xs tab-active">
    <i class="fas fa-plus-circle mr-1"></i> Add Item
  </button>
  <button id="listTab" class="tab-btn flex-1 py-2 px-2 text-center font-medium text-xs text-gray-500 hover:text-gray-700">
    <i class="fas fa-list mr-1"></i> View Items
  </button>
</div>
</div>

<!-- Content Sections -->
<div class="content-sections">
  <!-- Form Section -->
  <div id="formSection" class="section-content">
    <div class="form-container w-full lg:w-3/4 mx-auto">
      <div class="bg-white p-2 rounded-lg shadow-sm mb-5 hover-card">
        <form id="jewelryForm">
          <input type="hidden" id="itemId" name="itemId" value="">
          <input type="hidden" id="capturedImage" name="capturedImage" value="">
          
          <!-- Source Details Section -->
          <div class="section-card source-section bg-gradient-to-br from-green-50 to-green-100 mb-1 relative">
            <div class="section-title text-emerald-700">
              <i class="fas fa-user-tie"></i> Source Details
            </div>
            <!-- Source Reset Button -->
            <button type="button" id="sourceResetBtn" class="source-reset-btn">
              <i class="fas fa-undo-alt text-xs"></i>
            </button>
            <!-- Combined Source Type and Source ID in one row -->
            <div class="flex flex-row gap-2 w-full">
              <!-- Source Type -->
              <div class="flex-1">
                <div class="field-label">Source Type</div>
                <div class="field-container">
                  <select id="sourceTypeSelect" name="sourceType" class="input-field font-xs font-bold py-1 pl-6 pr-1 appearance-none bg-white border border-gray-200 hover:border-emerald-300 focus:border-emerald-400 rounded-md w-full">
                    <option value="Purchase" selected>Purchase</option>
                    <option value="Manufacturing Order" >Manufacturing Order</option>
                    <option value="Others">Others / Manual Entry</option>
                  </select>
                  <i class="fas fa-tag field-icon text-emerald-500"></i>
                </div>
              </div>
              <!-- Source ID -->
              <div class="flex-1">
                <div class="field-label">Source ID</div>
                <div class="field-container relative">
                  <input type="text" 
                         id="sourceId" 
                         name="sourceId"
                         class="input-field font-xs font-bold py-1 pl-6 bg-white border border-gray-200 hover:border-emerald-300 focus:border-emerald-400 rounded-md w-full" 
                         placeholder="Search source...">
                  <i class="fas fa-hashtag field-icon text-emerald-500"></i>
                  <div id="sourceIdSuggestions" 
                       class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg hidden max-h-48 overflow-y-auto custom-scrollbar">
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Source Info Display (read-only) - Compact View -->
            <div id="sourceInfoDisplay" class="bg-gradient-to-br from-white via-blue-50 to-blue-100 p-2 rounded-xl border-2 border-dashed border-blue-300 shadow-sm mt-2 hidden">
              <div class="text-sm font-semibold text-blue-700 mb-1 flex items-center gap-2">
                <i class="fas fa-info-circle text-blue-500"></i>
                <span>Source Info</span>
              </div>
              <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                <div><span class="text-gray-500">Name:</span> <span id="sourceNameDisplay" class="font-medium ml-1">-</span></div>
                <div><span class="text-gray-500">Weight:</span> <span id="sourceWeightDisplay" class="font-medium ml-1">-</span></div>
                <div><span class="text-gray-500">Purity:</span> <span id="sourcePurityDisplay" class="font-medium ml-1">-</span></div>
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
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
            <!-- Material Details Section -->
            <div class="section-card material-section material-theme rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
              <div class="section-title text-amber-800">
                <i class="fas fa-coins"></i> Material
              </div>
              <!-- Two items per row layout for better space utilization -->
              <div class="grid grid-cols-3 gap-1">
                <!-- Material -->
                <div>
                  <div class="field-label">Material</div>
                  <div class="field-container">
                    <select id="materialType" name="materialType" class="input-field font-xs font-bold py-1 pl-6 pr-1 appearance-none bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md w-full">
                      <option value="Gold" selected>Gold</option>
                      <option value="Silver">Silver</option>
                      <option value="Platinum">Platinum</option>
                      <option value="White Gold">White Gold</option>
                      <option value="Rose Gold">Rose Gold</option>
                    </select>
                    <i class="fas fa-coins field-icon text-amber-500"></i>
                  </div>
                </div>
                
                <!-- Purity -->
                <div>
                  <div class="field-label">Purity</div>
                  <div class="field-container">
                    <input type="number" 
                           id="purity" 
                           name="purity"
                           class="input-field font-xs font-bold py-1 pl-6 bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md w-full" 
                           placeholder="e.g. 92.0" 
                           step="0.1" 
                           min="0" 
                           max="100" />
                    <i class="fas fa-certificate field-icon text-amber-500"></i>
                  </div>
                </div>
                
                <!-- Jewelry Type -->
                <div>
                  <div class="field-label">Jewelry Type</div>
                  <div class="field-container relative">
                    <input type="text" 
                           id="jewelryType" 
                           name="jewelryType"
                           class="input-field font-xs font-bold py-1 pl-6 bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md w-full" 
                           placeholder="Select...">
                    <i class="fas fa-ring field-icon text-amber-500"></i>
                    <div id="jewelryTypeSuggestions" 
                         class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg hidden max-h-48 overflow-y-auto custom-scrollbar">
                    </div>
                  </div>
                </div>
                
                <!-- Name -->
                <div>
                  <div class="field-label">Name</div>
                  <div class="field-container">
                    <input type="text" 
                           id="productName" 
                           name="productName"
                           class="input-field font-xs font-bold py-1 pl-6 bg-white border border-amber-200 hover:border-amber-300 focus:border-amber-400 rounded-md w-full" 
                           placeholder="Name...">
                    <i class="fas fa-tag field-icon text-amber-500"></i>
                  </div>
                </div>
              </div>
              
              <!-- Hidden Product ID field -->
              <input type="hidden" id="productIdDisplay" name="productIdDisplay" value="" />
            </div>
            
            <!-- Weight Details Section -->
            <div class="section-card weight-theme mb-1">
              <div class="section-title section-collapse flex justify-between items-center text-blue-800">
                <div><i class="fas fa-weight-scale mr-1"></i> Weight</div>
                <i class="fas fa-chevron-down text-xs collapse-icon"></i>
              </div>
              <div class="section-content">
                <div class="field-grid">
                  <div class="field-col">
                    <div class="field-label">Gross Weight (g)</div>
                    <div class="field-container">
                      <input type="number" id="grossWeight" name="grossWeight" class="input-field font-xs font-bold" placeholder="Gross" step="0.01">
                      <i class="fas fa-weight-scale field-icon text-blue-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Less Weight (g)</div>
                    <div class="field-container">
                      <input type="number" id="lessWeight" name="lessWeight" class="input-field font-xs font-bold" placeholder="Less" step="0.01">
                      <i class="fas fa-minus-circle field-icon text-red-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Net Weight (g)</div>
                    <div class="field-container">
                      <input type="number" id="netWeight" name="netWeight" class="input-field font-xs font-bold" placeholder="Net" step="0.01" readonly>
                      <i class="fas fa-balance-scale field-icon text-green-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Tray No</div>
                    <div class="field-container">
                      <input type="text" id="trayNo" name="trayNo" class="input-field font-xs font-bold" placeholder="Tray Number">
                      <i class="fas fa-box field-icon text-blue-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">HUID CODE</div>
                    <div class="field-container">
                      <input type="text" id="huidCode" name="huidCode" class="input-field font-xs font-bold" placeholder="HUID Code">
                      <i class="fas fa-fingerprint field-icon text-blue-500"></i>
                    </div>
                  </div>
                  <div class="field-col">
                    <div class="field-label">Quantity</div>
                    <div class="field-container">
                      <input type="number" id="quantity" name="quantity" class="input-field font-xs font-bold" placeholder="Qty" value="1" min="1">
                      <i class="fas fa-sort-amount-up field-icon text-blue-500"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
            
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 mt-1">
<!-- Stone Details Section (With collapsible functionality) -->
<div class="section-card stone-section stone-theme rounded-xl shadow-sm hover:shadow-md transition-all duration-300 mb-2 collapsed">
  <div class="section-title section-collapse flex justify-between items-center text-purple-800">
    <div><i class="fas fa-gem mr-1"></i> Stone</div>
    <i class="fas fa-chevron-down text-xs collapse-icon"></i>
  </div>
  <div class="section-content">
    <div class="field-grid">
      <div class="field-col">
        <div class="field-label">Stone Type</div>
        <div class="field-container">
          <select id="stoneType" name="stoneType" class="input-field font-xs font-bold">
            <option value="None" selected>None</option>
            <option value="Diamond">Diamond</option>
            <option value="Ruby">Ruby</option>
            <option value="Emerald">Emerald</option>
            <option value="Sapphire">Sapphire</option>
            <option value="Pearl">Pearl</option>
            <option value="Mixed">Mixed</option>
          </select>
          <i class="fas fa-gem field-icon text-purple-500"></i>
        </div>
      </div>
      <div class="field-col">
        <div class="field-label">Stone Weight</div>
        <div class="field-container flex">
          <input type="number" id="stoneWeight" name="stoneWeight" class="input-field font-xs font-bold " style="width: 70%" placeholder="Weight" step="0.01" disabled>
          <select id="stoneUnit" name="stoneUnit" class="text-xs p-1 bg-white border border-purple-200 rounded-r-md" style="width: 30%; height: 28px;" disabled>
            <option value="ct">ct</option>
            <option value="ratti">ratti</option>
          </select>
          <i class="fas fa-weight field-icon text-purple-500"></i>
        </div>
      </div>
      <div class="field-col">
        <div class="field-label">Stone Color</div>
        <div class="field-container">
          <select id="stoneColor" name="stoneColor" class="input-field" disabled>
            <option value="">Select Color</option>
            <option value="D">D (Colorless)</option>
            <option value="E">E (Colorless)</option>
            <option value="F">F (Colorless)</option>
            <option value="Red">Red</option>
            <option value="Blue">Blue</option>
          </select>
          <i class="fas fa-palette field-icon text-purple-500"></i>
        </div>
      </div>
      <div class="field-col">
        <div class="field-label">Stone Quality</div>
        <div class="field-container">
          <select id="stoneQuality" name="stoneQuality" class="input-field font-medium py-1 pl-6 pr-1 appearance-none bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md" disabled>
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
          <input type="number" id="stonePrice" name="stonePrice" class="input-field font-xs font-bold py-1 pl-6 bg-white border border-purple-200 hover:border-purple-300 focus:border-purple-400 rounded-md" placeholder="Price" step="0.01" disabled />
          <i class="fas fa-rupee-sign field-icon text-green-500"></i>
        </div>
      </div>
      <!-- Hidden field for stone clarity (removed from UI) -->
      <input type="hidden" id="stoneClarity" name="stoneClarity" value="">
    </div>
  </div>
</div>

<div class="section-card making-theme mb-2">
  <div class="section-title section-collapse flex justify-between items-center text-green-800">
    <div><i class="fas fa-hammer mr-1"></i> Making & Status</div>
    <i class="fas fa-chevron-down text-xs collapse-icon"></i>
  </div>
  <div class="section-content">
    <div class="field-grid">
      <div class="field-col">
        <div class="field-label">Making Charge</div>
        <div class="field-container">
          <input type="number" id="makingCharge" name="makingCharge" class="input-field font-xs font-bold " placeholder="Charge" step="0.01">
          <i class="fas fa-rupee-sign field-icon text-green-500"></i>
        </div>
      </div>
      <div class="field-col">
        <div class="field-label">Charge Type</div>
        <div class="field-container">
          <select id="makingChargeType" name="makingChargeType" class="input-field">
            <option value="fixed" selected>Fixed</option>
            <option value="percentage">Percentage</option>
          </select>
          <i class="fas fa-percent field-icon text-green-500"></i>
        </div>
      </div>
      <div class="field-col">
        <div class="field-label">Status</div>
        <div class="field-container">
          <select id="status" name="status" class="input-field">
            <option value="Available" selected>Available</option>
            <option value="Pending">Pending</option>
            <option value="Sold">Sold</option>
          </select>
          <i class="fas fa-tag field-icon text-green-500"></i>
        </div>
      </div>
      <div class="field-col hidden">
        <div class="field-label">Update Inventory</div>
        <div class="field-container flex items-center h-full">
          <label class="flex items-center cursor-pointer">
            <input type="checkbox" id="updateInventory" name="updateInventory" class="form-checkbox h-4 w-4 text-green-600 rounded border-green-300">
            <span class="ml-1 text-xs text-gray-700">Adjust stock</span>
          </label>
        </div>
      </div>
    </div>
    
    <!-- Quick action buttons -->
    <div class="flex gap-1 mt-2">
      <button type="button" id="addImagesBtn" class="action-button flex-1 flex items-center justify-center bg-blue-50 text-blue-600 border border-blue-200 rounded">
        <i class="fas fa-images mr-1 text-xs"></i> Images
      </button>
      <button type="button" id="captureImageBtn" class="action-button flex-1 flex items-center justify-center bg-purple-50 text-purple-600 border border-purple-200 rounded">
        <i class="fas fa-camera mr-1 text-xs"></i> Capture
      </button>
      <button type="button" id="quickNoteBtn" class="action-button flex-1 flex items-center justify-center bg-green-50 text-green-600 border border-green-200 rounded">
        <i class="fas fa-comment-alt mr-1 text-xs"></i> Note
      </button>
    </div>
    <input type="file" id="productImages" name="images[]" accept="image/*" multiple class="hidden" />
  </div>
</div>
</div>
          
          <!-- Image Preview Row - More Compact -->
          <div id="imagePreviewContainer" class="mt-3 bg-white p-2 rounded-md border border-gray-200 min-h-16">
            <div class="flex justify-between items-center mb-2">
              <div class="text-xs font-medium text-gray-700">
                <i class="fas fa-images mr-1"></i> Product Images
              </div>
              <button id="clearImages" type="button" class="text-xs text-gray-500 hover:text-gray-700">
                <i class="fas fa-trash-alt mr-1"></i> Clear All
              </button>
            </div>
            <div id="imagePreview" class="flex flex-wrap gap-2"></div>
          </div>
          
          <!-- Quick Note Section (Inline instead of modal) -->
          <div id="quickNoteSection" class="quick-note-section mt-3 hidden">
<div class="flex justify-between items-center mb-2">
  <div class="text-xs font-medium text-gray-700">
    <i class="fas fa-comment-alt mr-1"></i> Quick Note
  </div>
  <button id="closeQuickNote" type="button" class="text-gray-400 hover:text-gray-600">
    <i class="fas fa-times"></i>
  </button>
</div>
<textarea id="description" name="description" class="w-full text-xs font-medium py-2 pl-2 bg-white border border-gray-200 hover:border-green-300 focus:border-green-400 rounded-md" 
          placeholder="Enter description or notes..." style="height: 80px; resize: vertical;"></textarea>
</div>

       
          <!-- Action Buttons -->
          <div class="flex gap-3 mt-4">
            <button id="clearForm" type="button" class="flex-1 flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold py-2 px-4 rounded-md transition-colors">
              <i class="fas fa-eraser mr-2"></i> Clear
            </button>
            <button id="addItem" type="button" class="flex-1 flex items-center justify-center bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white text-sm font-bold py-2 px-4 rounded-md shadow-sm transition-all hover:shadow">
              <i class="fas fa-plus-circle mr-2"></i> Add Item
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- List Section -->
<!-- Enhanced Jewelry Inventory Section with Mobile-First Approach -->
<div id="listSection" class="section-content hidden">
<!-- Stats Card - Horizontal Scrollable on Mobile -->
<div class="stats-wrapper mb-2 overflow-x-auto">
  <div class="inventory-stats-card flex min-w-max">
    <div class="stats-title px-2 py-1 bg-indigo-100 text-indigo-700 rounded-l-lg flex items-center">
      <i class="fas fa-chart-pie mr-1"></i> Stock
    </div>
    <div class="stats-scroll flex">
      <?php foreach ($inventoryStats as $material => $stock): ?>
      <div class="stat-item px-3 py-1 bg-white border-r border-indigo-100">
        <div class="stat-value font-medium text-indigo-800"><?php echo number_format($stock, 2); ?>g</div>
        <div class="stat-label text-xs text-gray-600"><?php echo htmlspecialchars($material); ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($inventoryStats)): ?>
      <div class="stat-item px-3 py-1 bg-white">
        <div class="stat-value font-medium text-indigo-800">0.00g</div>
        <div class="stat-label text-xs text-gray-600">No inventory</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Items List Card -->
<div class="bg-white rounded-lg shadow-sm mb-2">
  <!-- Header with Search -->
  <div class="p-2 border-b flex flex-wrap items-center gap-2">
    <h2 class="text-sm font-bold text-gray-800 flex items-center mr-auto">
      <i class="fas fa-list text-indigo-600 mr-1"></i> Inventory Items
    </h2>
    
    <div class="relative flex-shrink-0">
      <input type="text" 
             id="searchItems" 
             class="input-field py-1 pl-6 pr-2 bg-white border border-gray-200 rounded-md text-xs w-28"
             placeholder="Search...">
      <i class="fas fa-search absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 text-xs"></i>
    </div>
    
    <button id="exportBtn" class="bg-green-100 text-green-700 hover:bg-green-200 text-xs font-medium py-1 px-2 rounded flex items-center flex-shrink-0">
      <i class="fas fa-file-export mr-1"></i> Export
    </button>
  </div>

  <!-- Compact Filter Bar - Horizontally Scrollable -->
  <div class="filter-scroll-container overflow-x-auto">
    <div class="bg-gray-50 p-2 flex items-center gap-1 text-xs min-w-max">
      <span class="text-gray-500 font-medium">Filter:</span>
      
      <select id="filterMaterial" class="bg-white border border-gray-200 rounded py-1 px-1 text-xs max-w-[85px]">
        <option value="">Materials</option>
        <?php foreach ($materialTypes as $type): ?>
          <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
        <?php endforeach; ?>
      </select>
      
      <select id="filterJewelryType" class="bg-white border border-gray-200 rounded py-1 px-1 text-xs max-w-[70px]">
        <option value="">Types</option>
        <?php foreach ($jewelryTypes as $type): ?>
          <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
        <?php endforeach; ?>
      </select>
      
      <select id="filterSource" class="bg-white border border-gray-200 rounded py-1 px-1 text-xs max-w-[70px]">
        <option value="">Source</option>
        <option value="Supplier">Supplier</option>
        <option value="Karigar">Karigar</option>
        <option value="Other">Other</option>
      </select>
      
      <select id="filterStatus" class="bg-white border border-gray-200 rounded py-1 px-1 text-xs max-w-[65px]">
        <option value="">Status</option>
        <?php foreach ($statusTypes as $type): ?>
          <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
        <?php endforeach; ?>
      </select>
      
      <button id="resetFilters" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded text-xs flex items-center">
        <i class="fas fa-undo-alt mr-1 text-xs"></i> Reset
      </button>
      
      <span id="itemCount" class="bg-indigo-100 text-indigo-800 px-2 py-1 rounded-full text-xs font-medium ml-1">0 items</span>
    </div>
  </div>

  <!-- Table Container - Horizontally Scrollable -->
  <div class="table-container overflow-x-auto custom-scrollbar max-h-[calc(100vh-220px)]">
    <table id="itemsTable" class="w-full border-collapse">
      <thead>
        <tr class="bg-gray-50 text-gray-600 text-xs leading-normal">
          <th class="py-2 px-2 text-left border-b border-gray-200 w-12">ID</th>
          <th class="py-2 px-2 text-left border-b border-gray-200 min-w-[120px]">Item</th>
          <th class="py-2 px-2 text-left border-b border-gray-200 w-16">Weight</th>
          <th class="py-2 px-2 text-left border-b border-gray-200 w-16">Source</th>
          <th class="py-2 px-2 text-center border-b border-gray-200 w-20">Actions</th>
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

<nav class="bottom-nav">
 <!-- Home -->
 <a href="home.php" class="nav-item">
   <i class="nav-icon fas fa-home"></i>
   <span class="nav-text">Home</span>
 </a>
 
<a href="add.php" class="nav-item active">
   <i class="nav-icon fa-solid fa-gem"></i>
   <span class="nav-text">Add</span>
 </a>
  <a href="add-stock.php" class="nav-item ">
   <i class="nav-icon fa-solid fa-store"></i>
   <span class="nav-text">Stock</span>
 </a>

<a href="order.php" class="nav-item">
   <i class="nav-icon fa-solid fa-user-tag"></i>
   <span class="nav-text">Orders</span>
 </a>

<a href="sale-entry.php" class="nav-item">
   <i class="nav-icon fas fa-shopping-cart"></i>
   <span class="nav-text">Sale</span>
 </a>
 <!-- Sales List -->


 <!-- Reports -->
 <a href="reports.php" class="nav-item">
   <i class="nav-icon fas fa-chart-pie"></i>
   <span class="nav-text">Reports</span>
 </a>
</nav>


<!-- Camera Capture Modal -->
<div id="cameraModal" class="camera-modal hidden">
  <div class="camera-container">
    <video id="cameraFeed" class="camera-feed" autoplay playsinline></video>
    <canvas id="captureCanvas" class="hidden"></canvas>
    <img id="capturePreview" class="hidden w-full h-auto" />
    
    <div class="camera-controls">
      <button id="switchCameraBtn" class="camera-button">
        <i class="fas fa-sync-alt"></i>
      </button>
      <button id="captureBtn" class="camera-button">
        <i class="fas fa-camera"></i>
      </button>
      <button id="acceptCaptureBtn" class="camera-button accept hidden">
        <i class="fas fa-check"></i>
      </button>
      <button id="retakeCaptureBtn" class="camera-button hidden">
        <i class="fas fa-redo"></i>
      </button>
      <button id="closeCameraBtn" class="camera-button cancel">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>
</div>





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
            <span class="text-gray-600">Purity:</span>
            <span id="modalPurity" class="font-medium"></span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">HUID:</span>
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
            <span class="text-gray-600">Invoice:</span>
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

<!-- Place the cropper modal here, just before </body> -->
<div id="cropperModal" class="cropper-modal hidden">
  <div class="cropper-container">
    <img id="cropperImage" src="" alt="Crop" style="max-width:100%; display:block; margin:auto;" />
  </div>
  <div class="cropper-controls mt-2">
    <button id="cropperCropBtn" class="bg-green-500 text-white px-3 py-1 rounded font-bold mr-2"><i class="fas fa-crop"></i> Crop</button>
    <button id="cropperRotateBtn" class="bg-blue-500 text-white px-3 py-1 rounded font-bold mr-2"><i class="fas fa-undo"></i> Rotate</button>
    <button id="cropperFlipBtn" class="bg-purple-500 text-white px-3 py-1 rounded font-bold mr-2"><i class="fas fa-arrows-alt-h"></i> Flip</button>
    <button id="cropperCancelBtn" class="bg-gray-400 text-white px-3 py-1 rounded font-bold"><i class="fas fa-times"></i> Cancel</button>
  </div>
</div>

<script src="js/add.js"></script>
</body>
</html>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require_once __DIR__ . '/../config/db_connect.php';
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

function getNextInvoiceNumber($conn, $firm_id, $is_gst = true) {
    // GST prefix is IN, non-GST prefix is NG
    $prefix = $is_gst ? 'IN' : 'NG';
    
    // Grab the highest numeric suffix (characters 3+) for this prefix
    $sql = "
        SELECT MAX(CAST(SUBSTRING(invoice_no, 3) AS UNSIGNED)) AS last_num
        FROM jewellery_sales
        WHERE invoice_no LIKE ? AND firm_id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL prepare failed in getNextInvoiceNumber: " . $conn->error);
        // Fallback to the first invoice
        return $prefix . str_pad(1, 3, '0', STR_PAD_LEFT);
    }
    
    $pattern = $prefix . '%';  // e.g. "IN%" or "NG%"
    $stmt->bind_param("si", $pattern, $firm_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        error_log("SQL execute/get_result failed in getNextInvoiceNumber: " . $conn->error);
        return $prefix . str_pad(1, 3, '0', STR_PAD_LEFT);
    }
    
    $row = $result->fetch_assoc();
    $nextNum = ($row['last_num'] ?? 0) + 1;
    
    $stmt->close();
    
    // Build final invoice_no, e.g. IN047 or NG004
    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

// Function to search customers
function searchCustomers($conn, $term, $firm_id) {
    $term = '%' . $conn->real_escape_string($term) . '%';
    
    $sql = "SELECT id, FirstName, LastName, PhoneNumber, Email 
            FROM customer
            WHERE (FirstName LIKE ? OR LastName LIKE ? OR PhoneNumber LIKE ?)
            AND firm_id = ?";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => "Prepare failed: " . $conn->error];
    }
    
    $stmt->bind_param("sssi", $term, $term, $term, $firm_id);
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => "Execute failed: " . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $customers = [];
    
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    $stmt->close();
    return ['success' => true, 'data' => $customers];
}

// Function to get customer details
function getCustomerDetails($conn, $customerId, $firm_id) {
    $sql = "SELECT * FROM customer WHERE id = ? AND firm_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => "Prepare failed: " . $conn->error];
    }
    
    $stmt->bind_param("ii", $customerId, $firm_id);
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => "Execute failed: " . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    
    $stmt->close();
    
    if ($customer) {
        return ['success' => true, 'data' => $customer];
    } else {
        return ['success' => false, 'error' => "Customer not found"];
    }
}

// Function to update customer
function updateCustomer($conn, $customerData, $firm_id) {
    $customerId = $customerData['customerId'];
    $firstName = $conn->real_escape_string($customerData['firstName']);
    $lastName = $conn->real_escape_string($customerData['lastName']);
    $email = $conn->real_escape_string($customerData['email']);
    $phone = $conn->real_escape_string($customerData['phone']);
    $address = $conn->real_escape_string($customerData['address']);
    $city = $conn->real_escape_string($customerData['city']);
    $state = $conn->real_escape_string($customerData['state'] ?? '');
    $postalCode = $conn->real_escape_string($customerData['postalCode'] ?? '');
    $panNumber = $conn->real_escape_string($customerData['panNumber'] ?? '');
    $aadhaarNumber = $conn->real_escape_string($customerData['aadhaarNumber'] ?? '');
    $dob = $customerData['dob'] ? "'" . $conn->real_escape_string($customerData['dob']) . "'" : "NULL";
    
    // Handle document upload if provided
    $docPath = NULL;
    
    if (isset($_FILES['docFile']) && $_FILES['docFile']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = $customerId . '_doc_' . time() . '.' . pathinfo($_FILES['docFile']['name'], PATHINFO_EXTENSION);
        $targetFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['docFile']['tmp_name'], $targetFile)) {
            $docPath = $fileName;
        }
    }
    
    // Handle customer image upload if provided
    $imagePath = NULL;
    
    if (isset($_FILES['customerImage']) && $_FILES['customerImage']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/customers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = $customerId . '_img_' . time() . '.' . pathinfo($_FILES['customerImage']['name'], PATHINFO_EXTENSION);
        $targetFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['customerImage']['tmp_name'], $targetFile)) {
            $imagePath = $fileName;
        }
    }
    
    // Build SQL query based on what's provided
    $sql = "UPDATE customer SET 
            FirstName = ?, 
            LastName = ?, 
            Email = ?, 
            PhoneNumber = ?, 
            Address = ?, 
            City = ?, 
            State = ?,
            PostalCode = ?,
            PANNumber = ?,
            AadhaarNumber = ?,
            DateOfBirth = $dob,
            UpdatedAt = NOW()";
    
    // Add image field if it was uploaded
    if ($imagePath) {
        $sql .= ", CustomerImage = '" . $conn->real_escape_string($imagePath) . "'";
    }
    
    $sql .= " WHERE id = ? AND firm_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in updateCustomer: " . $conn->error);
        return ['success' => false, 'error' => "Prepare failed: " . $conn->error];
    }
    
    $stmt->bind_param("sssssssssii", $firstName, $lastName, $email, $phone, $address, $city, $state, $postalCode, $panNumber, $aadhaarNumber, $customerId, $firm_id);
    
    if (!$stmt->execute()) {
        error_log("SQL Error in updateCustomer: " . $stmt->error);
        return ['success' => false, 'error' => "Execute failed: " . $stmt->error];
    }
    
    // If document was uploaded, store it in a separate table or update the document table
    if ($docPath) {
        $docType = $customerData['docType'] ?? 'Other';
        $docNumber = $customerData['docNumber'] ?? '';
        
        // Check if document already exists
        $checkSql = "SELECT id FROM customer_documents WHERE customer_id = ? AND document_type = ? AND firm_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("isi", $customerId, $docType, $firm_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing document
            $docRow = $checkResult->fetch_assoc();
            $docId = $docRow['id'];
            
            $updateDocSql = "UPDATE customer_documents SET 
                            document_path = ?, 
                            document_number = ?, 
                            updated_at = NOW() 
                            WHERE id = ?";
            
            $updateDocStmt = $conn->prepare($updateDocSql);
            $updateDocStmt->bind_param("ssi", $docPath, $docNumber, $docId);
            $updateDocStmt->execute();
            $updateDocStmt->close();
        } else {
            // Insert new document
            $insertDocSql = "INSERT INTO customer_documents (customer_id, firm_id, document_type, document_number, document_path, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            
            $insertDocStmt = $conn->prepare($insertDocSql);
            $insertDocStmt->bind_param("iisss", $customerId, $firm_id, $docType, $docNumber, $docPath);
            $insertDocStmt->execute();
            $insertDocStmt->close();
        }
        
        $checkStmt->close();
    }
    
    $stmt->close();
    return ['success' => true];
}

// Function to get customer balance (due and advance)
function getCustomerBalance($conn, $customerId, $firm_id) {
    // Get total due from jewellery_sales
    $dueSql = "SELECT COALESCE(SUM(due_amount), 0) as TotalDue 
               FROM jewellery_sales 
               WHERE customer_id = ? AND firm_id = ? AND payment_status != 'Paid'";
    
    $dueStmt = $conn->prepare($dueSql);
    if (!$dueStmt) {
        return ['success' => false, 'error' => "Prepare failed for due: " . $conn->error];
    }
    
    $dueStmt->bind_param("ii", $customerId, $firm_id);
    if (!$dueStmt->execute()) {
        return ['success' => false, 'error' => "Execute failed for due: " . $dueStmt->error];
    }
    
    $dueResult = $dueStmt->get_result();
    $dueRow = $dueResult->fetch_assoc();
    $totalDue = $dueRow['TotalDue'] ?? 0;
    
    $dueStmt->close();
    
    // Get total available advance from customer_orders (only unused advance)
    $advanceSql = "SELECT COALESCE(SUM(advance_amount - COALESCE(advance_used,0)), 0) as TotalAdvance 
                   FROM customer_orders 
                   WHERE customer_id = ? AND firm_id = ? AND advance_amount > COALESCE(advance_used,0)";
    
    $advanceStmt = $conn->prepare($advanceSql);
    if (!$advanceStmt) {
        return ['success' => false, 'error' => "Prepare failed for advance: " . $conn->error];
    }
    
    $advanceStmt->bind_param("ii", $customerId, $firm_id);
    if (!$advanceStmt->execute()) {
        return ['success' => false, 'error' => "Execute failed for advance: " . $advanceStmt->error];
    }
    
    $advanceResult = $advanceStmt->get_result();
    $advanceRow = $advanceResult->fetch_assoc();
    $totalAdvance = $advanceRow['TotalAdvance'] ?? 0;
    
    $advanceStmt->close();
    
    return [
        'success' => true,
        'due' => $totalDue,
        'advance' => $totalAdvance
    ];
}

// Function to search jewelry products
function searchJewellery($conn, $term, $firm_id) {
    $term = '%' . $conn->real_escape_string($term) . '%';
    
    // Prepared statement approach for better security and reliability
    $sql = "SELECT j.id, j.product_id, j.jewelry_type, j.product_name, 
                   j.material_type, j.purity, j.gross_weight, j.net_weight, 
                   j.making_charge, j.making_charge_type, j.huid_code, 
                   pc.rate AS rate_24k, j.wastage_percentage,
                   j.image_path, j.stone_type, j.stone_weight, j.stone_quality,
                   j.stone_price, j.less_weight, j.status, j.rate_per_gram
            FROM jewellery_items j
            LEFT JOIN jewellery_price_config pc ON j.material_type = pc.material_type 
                 AND pc.purity = '99.99' AND pc.effective_date <= CURDATE() AND pc.firm_id = ?
            WHERE (j.jewelry_type LIKE ? 
               OR j.huid_code LIKE ? 
               OR j.product_id LIKE ?
               OR j.product_name LIKE ?)
            AND j.firm_id = ?
            AND j.status = 'Available'
            GROUP BY j.id
            ORDER BY pc.effective_date DESC
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in searchJewellery: " . $conn->error);
        return ['success' => false, 'error' => "Prepare failed: " . $conn->error];
    }
    
    $stmt->bind_param("issssi", $firm_id, $term, $term, $term, $term, $firm_id);
    
    if (!$stmt->execute()) {
        error_log("Execute failed in searchJewellery: " . $stmt->error);
        return ['success' => false, 'error' => "Execute failed: " . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $products = [];
    
    while ($row = $result->fetch_assoc()) {
        // Ensure rate_per_gram is calculated using actual purity
        $purity = floatval($row['purity']);
        $rate_24k = floatval($row['rate_24k']);
        if (empty($row['rate_per_gram']) || $row['rate_per_gram'] == 0) {
            $row['rate_per_gram'] = ($purity / 100) * $rate_24k;
        }
        $products[] = $row;
    }
    
    $stmt->close();
    
    return ['success' => true, 'data' => $products];
}

// Function to get product by barcode/HUID
function getJewelleryByHUID($conn, $huid, $firm_id) {
    $huid = $conn->real_escape_string($huid);
    
    $sql = "SELECT j.id, j.product_id, j.jewelry_type, j.product_name, 
                  j.material_type, j.purity, j.gross_weight, j.net_weight, 
                  pc.rate AS rate_24k, j.huid_code, j.rate_per_gram,
                  j.making_charge, j.making_charge_type, j.wastage_percentage, 
                  j.image_path, j.stone_type, j.stone_weight, j.stone_quality,
                  j.stone_price, j.less_weight, j.status
            FROM jewellery_items j
            LEFT JOIN jewellery_price_config pc ON j.material_type = pc.material_type 
                 AND pc.purity = '99.99' AND pc.effective_date <= CURDATE() AND pc.firm_id = ?
            WHERE j.huid_code = ? AND j.firm_id = ? AND j.status = 'Available'
            ORDER BY pc.effective_date DESC
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in getJewelleryByHUID: " . $conn->error);
        return ['success' => false, 'error' => "Prepare failed: " . $conn->error];
    }
    
    $stmt->bind_param("isi", $firm_id, $huid, $firm_id);
    if (!$stmt->execute()) {
        error_log("Execute failed in getJewelleryByHUID: " . $stmt->error);
        return ['success' => false, 'error' => "Execute failed: " . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    $stmt->close();
    
    if ($product) {
        // Ensure rate_per_gram is calculated using actual purity
        $purity = floatval($product['purity']);
        $rate_24k = floatval($product['rate_24k']);
        if (empty($product['rate_per_gram']) || $product['rate_per_gram'] == 0) {
            $product['rate_per_gram'] = ($purity / 100) * $rate_24k;
        }
        return ['success' => true, 'data' => $product];
    } else {
        return ['success' => false, 'error' => "Product not found"];
    }
}

// Function to add new customer
function addCustomer($conn, $customerData, $firm_id) {
    $firstName = $conn->real_escape_string($customerData['firstName']);
    $lastName = $conn->real_escape_string($customerData['lastName']);
    $email = $conn->real_escape_string($customerData['email']);
    $phone = $conn->real_escape_string($customerData['phone']);
    $address = $conn->real_escape_string($customerData['address'] ?? '');
    $city = $conn->real_escape_string($customerData['city'] ?? '');
    $dob = $customerData['dob'] ? "'" . $conn->real_escape_string($customerData['dob']) . "'" : "NULL";
    
    $sql = "INSERT INTO customer (firm_id, FirstName, LastName, Email, PhoneNumber, Address, City, DateOfBirth, CreatedAt, UpdatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, $dob, NOW(), NOW())";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in addCustomer: " . $conn->error);
        return ['success' => false, 'error' => "Prepare failed: " . $conn->error];
    }
    
    $stmt->bind_param("issssss", $firm_id, $firstName, $lastName, $email, $phone, $address, $city);
    
    if (!$stmt->execute()) {
        error_log("SQL Error in addCustomer: " . $stmt->error);
        return ['success' => false, 'error' => "Execute failed: " . $stmt->error];
    }
    
    $customerId = $stmt->insert_id;
    $stmt->close();
    
    return ['success' => true, 'customerId' => $customerId, 'customerName' => $firstName . ' ' . $lastName];
}

// Function to add URD item
function addURDItem($conn, $urdData, $firm_id, $user_id) {
    $customerId = $urdData['customerId'];
    $itemName = $conn->real_escape_string($urdData['itemName']);
    $grossWeight = floatval($urdData['grossWeight']);
    $lessWeight = floatval($urdData['lessWeight']);
    $netWeight = floatval($urdData['netWeight']);
    $purity = floatval($urdData['purity']);
    $rate = floatval($urdData['rate']);
    $fineWeight = floatval($urdData['fineWeight']);
    $totalAmount = floatval($urdData['totalAmount']);
    $notes = $conn->real_escape_string($urdData['notes'] ?? '');
    
    // Handle image upload if provided
    $imagePath = NULL;
    
    if (isset($_FILES['urdImage']) && $_FILES['urdImage']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/urd/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = 'urd_' . time() . '_' . $customerId . '.' . pathinfo($_FILES['urdImage']['name'], PATHINFO_EXTENSION);
        $targetFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['urdImage']['tmp_name'], $targetFile)) {
            $imagePath = $fileName;
        }
    }
    
    $sql = "INSERT INTO urd_items (firm_id, user_id, customer_id, item_name, gross_weight, 
                                  less_weight, net_weight, purity, rate, fine_weight, 
                                  total_amount, image_data, received_date, status, 
                                  notes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Received', ?, NOW(), NOW())";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in addURDItem: " . $conn->error);
        return ['success' => false, 'error' => "Prepare failed: " . $conn->error];
    }
    
    $stmt->bind_param("iiisddddddsss", $firm_id, $user_id, $customerId, $itemName, $grossWeight, 
                     $lessWeight, $netWeight, $purity, $rate, $fineWeight, 
                     $totalAmount, $imagePath, $notes);
    
    if (!$stmt->execute()) {
        error_log("SQL Error in addURDItem: " . $conn->error);
        return ['success' => false, 'error' => "Execute failed: " . $stmt->error];
    }
    
    $urdId = $stmt->insert_id;
    $stmt->close();
    
    return ['success' => true, 'urdId' => $urdId];
}

// Function to create jewelry sale
function createJewellerySale($conn, $saleData, $firm_id, $user_id) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Validate data
        if (empty($saleData['customerId'])) {
            throw new Exception("Customer ID is required");
        }
        
        if (empty($saleData['items']) || !is_array($saleData['items'])) {
            throw new Exception("Items are required and must be an array");
        }
        
        // Insert into jewellery_sales table
        $sql = "INSERT INTO jewellery_sales (invoice_no, firm_id, customer_id, sale_date, 
                total_metal_amount, total_stone_amount, total_making_charges, 
                total_other_charges, discount, urd_amount, subtotal, 
                gst_amount, grand_total, total_paid_amount, advance_amount, 
                due_amount, payment_status, payment_method, is_gst_applicable, 
                notes, created_at, updated_at, user_id, coupon_discount, 
                loyalty_discount, manual_discount, coupon_code, transaction_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $invoiceNo = $saleData['invoiceNumber'];
        $saleDate = $saleData['saleDate'];
        $totalMetalAmount = floatval($saleData['totalMetalAmount']);
        $totalStoneAmount = floatval($saleData['totalStoneAmount']);
        $totalMakingCharges = floatval($saleData['totalMakingCharges']);
        $totalOtherCharges = floatval($saleData['totalOtherCharges'] ?? 0);
        $discount = floatval($saleData['discount'] ?? 0);
        $urdAmount = floatval($saleData['urdAmount'] ?? 0);
        $subtotal = floatval($saleData['subtotal']);
        $gstAmount = floatval($saleData['gstAmount']);
        $grandTotal = floatval($saleData['grandTotal']);
        $totalPaidAmount = floatval($saleData['paidAmount']);
        $advanceAmount = floatval($saleData['advanceAmount'] ?? 0);
        $dueAmount = $grandTotal - $totalPaidAmount - $advanceAmount;
        $paymentStatus = $dueAmount <= 0 ? 'Paid' : ($totalPaidAmount > 0 ? 'Partial' : 'Unpaid');
        $paymentMethod = $saleData['paymentMethod'];
        $isGstApplicable = $saleData['isGstApplicable'] ? 1 : 0;
        $notes = $saleData['notes'] ?? '';
        $couponDiscount = floatval($saleData['couponDiscount'] ?? 0);
        $loyaltyDiscount = floatval($saleData['loyaltyDiscount'] ?? 0);
        $manualDiscount = floatval($saleData['manualDiscount'] ?? 0);
        $couponCode = $saleData['couponCode'] ?? '';
        $transactionType = 'Sale';
        
        $stmt->bind_param("siisdddddddddddssisidddsss", 
            $invoiceNo,
            $firm_id,
            $saleData['customerId'],
            $saleDate,
            $totalMetalAmount,
            $totalStoneAmount,
            $totalMakingCharges,
            $totalOtherCharges,
            $discount,
            $urdAmount,
            $subtotal,
            $gstAmount,
            $grandTotal,
            $totalPaidAmount,
            $advanceAmount,
            $dueAmount,
            $paymentStatus,
            $paymentMethod,
            $isGstApplicable,
            $notes,
            $user_id,
            $couponDiscount,
            $loyaltyDiscount,
            $manualDiscount,
            $couponCode,
            $transactionType
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Sale insert failed: " . $stmt->error);
        }
        
        $saleId = $conn->insert_id;
        
        // Insert sales items
        foreach ($saleData['items'] as $item) {
            // Validate item data
            if (empty($item['productId'])) {
                throw new Exception("Product ID is required for each item");
            }
            
            $sql = "INSERT INTO Jewellery_sales_items (sale_id, product_id, product_name, huid_code, 
                    rate_24k, purity, purity_rate, gross_weight, less_weight, net_weight, 
                    metal_amount, stone_type, stone_weight, stone_price, making_type, 
                    making_rate, making_charges, total_charges, total, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $itemStmt = $conn->prepare($sql);
            if (!$itemStmt) {
                throw new Exception("Prepare failed for sales items: " . $conn->error);
            }
            
            $productName = $item['productName'];
            $huidCode = $item['huidCode'] ?? '';
            $rate24k = floatval($item['rate24k']);
            $purity = floatval($item['purity']);
            $purityRate = floatval($item['purityRate']);
            $grossWeight = floatval($item['grossWeight']);
            $lessWeight = floatval($item['lessWeight'] ?? 0);
            $netWeight = floatval($item['netWeight']);
            $metalAmount = floatval($item['metalAmount']);
            $stoneType = $item['stoneType'] ?? '';
            $stoneWeight = floatval($item['stoneWeight'] ?? 0);
            $stonePrice = floatval($item['stonePrice'] ?? 0);
            $makingType = $item['makingType'];
            $makingRate = floatval($item['makingRate']);
            $makingCharges = floatval($item['makingCharges']);
            $totalCharges = floatval($item['totalCharges']);
            $total = floatval($item['total']);
            
            $itemStmt->bind_param("iissddddddsddsddddd", 
                $saleId,
                $item['productId'],
                $productName,
                $huidCode,
                $rate24k,
                $purity,
                $purityRate,
                $grossWeight,
                $lessWeight,
                $netWeight,
                $metalAmount,
                $stoneType,
                $stoneWeight,
                $stonePrice,
                $makingType,
                $makingRate,
                $makingCharges,
                $totalCharges,
                $total
            );
            
            if (!$itemStmt->execute()) {
                throw new Exception("Sales item insert failed: " . $itemStmt->error);
            }
            
            // Update jewellery_items status to sold
            $updateSql = "UPDATE jewellery_items SET status = 'Sold' WHERE id = ? AND firm_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if (!$updateStmt) {
                throw new Exception("Prepare failed for status update: " . $conn->error);
            }
            
            $updateStmt->bind_param("ii", $item['productId'], $firm_id);
            
            if (!$updateStmt->execute()) {
                throw new Exception("Product status update failed: " . $updateStmt->error);
            }
            
            $updateStmt->close();
            $itemStmt->close();
        }
        
        // Insert URD items (if any)
        if (isset($saleData['urdItems']) && is_array($saleData['urdItems'])) {
            foreach ($saleData['urdItems'] as $urd) {
                $sql = "INSERT INTO urd_gold_items (sale_id, customer_id, item_name, gross_weight, less_weight, net_weight, purity, rate, fine_weight, total_amount, notes, created_at, firm_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
                $urdStmt = $conn->prepare($sql);
                if (!$urdStmt) {
                    throw new Exception("Prepare failed for URD item: " . $conn->error);
                }
                $urdStmt->bind_param(
                    "iissddddddsii",
                    $saleId,
                    $saleData['customerId'],
                    $urd['itemName'],
                    floatval($urd['grossWeight']),
                    floatval($urd['lessWeight']),
                    floatval($urd['netWeight']),
                    floatval($urd['purity']),
                    floatval($urd['rate']),
                    floatval($urd['fineWeight']),
                    floatval($urd['totalAmount']),
                    $urd['notes'],
                    $firm_id,
                    $user_id
                );
                if (!$urdStmt->execute()) {
                    throw new Exception("URD item insert failed: " . $urdStmt->error);
                }
                $urdStmt->close();
            }
        }
        
        // Handle payments
        if ($totalPaidAmount > 0 || $advanceAmount > 0) {
            // Process payment methods
            if (isset($saleData['paymentMethods']) && is_array($saleData['paymentMethods'])) {
                foreach ($saleData['paymentMethods'] as $payment) {
                    $paymentType = $payment['type'];
                    $amount = floatval($payment['amount']);
                    $referenceNo = $payment['reference'] ?? '';
                    
                    if ($amount <= 0) continue;
                    
                    $paymentSql = "INSERT INTO jewellery_payments (reference_id, reference_type, party_type, 
                                  party_id, sale_id, payment_type, amount, reference_no, 
                                  created_at, transctions_type, Firm_id) 
                                  VALUES (?, 'Sale', 'Customer', ?, ?, ?, ?, ?, NOW(), 'Credit', ?)";
                    
                    $paymentStmt = $conn->prepare($paymentSql);
                    if (!$paymentStmt) {
                        throw new Exception("Prepare failed for payment: " . $conn->error);
                    }
                    
                    $paymentStmt->bind_param("siidssi", 
                        $invoiceNo,
                        $saleData['customerId'],
                        $saleId,
                        $paymentType,
                        $amount,
                        $referenceNo,
                        $firm_id
                    );
                    
                    if (!$paymentStmt->execute()) {
                        throw new Exception("Payment insert failed: " . $paymentStmt->error);
                    }
                    
                    $paymentStmt->close();
                }
            } else {
                // Single payment method
                $paymentSql = "INSERT INTO jewellery_payments (reference_id, reference_type, party_type, 
                              party_id, sale_id, payment_type, amount, reference_no, 
                              created_at, transctions_type, Firm_id) 
                              VALUES (?, 'Sale', 'Customer', ?, ?, ?, ?, ?, NOW(), 'Credit', ?)";
                
                $paymentStmt = $conn->prepare($paymentSql);
                if (!$paymentStmt) {
                    throw new Exception("Prepare failed for payment: " . $conn->error);
                }
                
                $referenceNo = $saleData['paymentReference'] ?? '';
                
                $paymentStmt->bind_param("siidssi", 
                    $invoiceNo,
                    $saleData['customerId'],
                    $saleId,
                    $paymentMethod,
                    $totalPaidAmount,
                    $referenceNo,
                    $firm_id
                );
                
                if (!$paymentStmt->execute()) {
                    throw new Exception("Payment insert failed: " . $paymentStmt->error);
                }
                
                $paymentStmt->close();
            }
            
            // Handle advance payment if used
            if ($advanceAmount > 0) {
                // Fetch all eligible orders with available advance (FIFO)
                $fetchOrdersSql = "SELECT id, advance_amount, COALESCE(advance_used,0) as advance_used
                                   FROM customer_orders
                                   WHERE customer_id = ? AND firm_id = ? AND advance_amount > COALESCE(advance_used,0)
                                   ORDER BY id ASC";
                $fetchOrdersStmt = $conn->prepare($fetchOrdersSql);
                $fetchOrdersStmt->bind_param("ii", $saleData['customerId'], $firm_id);
                $fetchOrdersStmt->execute();
                $ordersResult = $fetchOrdersStmt->get_result();
                $orders = [];
                while ($row = $ordersResult->fetch_assoc()) {
                    $orders[] = $row;
                }
                $fetchOrdersStmt->close();

                $remainingAdvance = $advanceAmount;
                foreach ($orders as $order) {
                    $available = $order['advance_amount'] - $order['advance_used'];
                    if ($available <= 0) continue;
                    $toUse = min($available, $remainingAdvance);

                    $updateSql = "UPDATE customer_orders SET advance_used = advance_used + ?, advance_used_date = NOW() WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("di", $toUse, $order['id']);
                    if (!$updateStmt->execute()) {
                        throw new Exception("Advance update failed: " . $updateStmt->error);
                    }
                    $updateStmt->close();

                    $remainingAdvance -= $toUse;
                    if ($remainingAdvance <= 0) break;
                }

                // Add advance payment record
                $advancePaymentSql = "INSERT INTO jewellery_payments (reference_id, reference_type, party_type, 
                                    party_id, sale_id, payment_type, amount, reference_no, 
                                    created_at, transctions_type, Firm_id) 
                                    VALUES (?, 'Sale', 'Customer', ?, ?, 'Advance', ?, 'Advance Used', NOW(), 'Credit', ?)";
                $advancePaymentStmt = $conn->prepare($advancePaymentSql);
                if (!$advancePaymentStmt) {
                    throw new Exception("Prepare failed for advance payment: " . $conn->error);
                }
                $advancePaymentStmt->bind_param("siidi", 
                    $invoiceNo,
                    $saleData['customerId'],
                    $saleId,
                    $advanceAmount,
                    $firm_id
                );
                if (!$advancePaymentStmt->execute()) {
                    throw new Exception("Advance payment insert failed: " . $advancePaymentStmt->error);
                }
                $advancePaymentStmt->close();
            }
        }
        
        // Handle URD if used
        if ($urdAmount > 0 && isset($saleData['urdId'])) {
            $urdId = $saleData['urdId'];
            
            // Update URD item to link it to this sale
            $updateUrdSql = "UPDATE urd_items SET sale_id = ?, status = 'Used', updated_at = NOW() WHERE id = ? AND firm_id = ?";
            $updateUrdStmt = $conn->prepare($updateUrdSql);
            if (!$updateUrdStmt) {
                throw new Exception("Prepare failed for URD update: " . $conn->error);
            }
            
            $updateUrdStmt->bind_param("iii", $saleId, $urdId, $firm_id);
            
            if (!$updateUrdStmt->execute()) {
                throw new Exception("URD update failed: " . $updateUrdStmt->error);
            }
            
            $updateUrdStmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        return ['success' => true, 'saleId' => $saleId, 'invoiceNo' => $invoiceNo];
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Transaction Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Function to get current gold price for the specific firm
function getCurrentGoldPrice($conn, $materialType = 'Gold', $purity = '99.99', $firm_id) {
    $materialType = $conn->real_escape_string($materialType);
    $purity = $conn->real_escape_string($purity);
    
    $sql = "SELECT rate FROM jewellery_price_config 
            WHERE material_type = ? 
            AND purity = ? 
            AND effective_date <= CURDATE() 
            AND firm_id = ?
            ORDER BY effective_date DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in getCurrentGoldPrice: " . $conn->error);
        return 5500.00; // Default fallback price
    }
    
    $stmt->bind_param("ssi", $materialType, $purity, $firm_id);
    
    if (!$stmt->execute()) {
        error_log("Execute failed in getCurrentGoldPrice: " . $conn->error);
        return 5500.00; // Default fallback price
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Default fallback price
        return 5500.00; 
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['rate'];
}

// Function to calculate URD value
function calculateURDValue($conn, $weight, $purity, $firm_id) {
    $currentRate = getCurrentGoldPrice($conn, 'Gold', '99.99', $firm_id);
    
    // Calculate fine weight
    $fineWeight = ($weight * $purity) / 100;
    
    // Calculate value based on fine weight and current rate
    $value = $fineWeight * $currentRate;
    
    return [
        'success' => true,
        'weight' => $weight,
        'purity' => $purity,
        'rate' => $currentRate,
        'fineWeight' => $fineWeight,
        'value' => $value
    ];
}

// Function to get firm configuration
function getFirmConfig($conn, $firm_id) {
    $sql = "SELECT * FROM firm_configurations WHERE firm_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $firm_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $config = $result->fetch_assoc();
    $stmt->close();
    return $config;
}

// Assign welcome coupon to customer
function assignWelcomeCoupon($conn, $customerId, $firm_id, $couponCode) {
    // Check if already assigned
    $checkSql = "SELECT id FROM customer_assigned_coupons WHERE customer_id = ? AND coupon_code = ? AND firm_id = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("isi", $customerId, $couponCode, $firm_id);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $checkStmt->close();
        return ['success' => false, 'error' => 'Coupon already assigned'];
    }
    $checkStmt->close();
    // Assign coupon
    $insertSql = "INSERT INTO customer_assigned_coupons (customer_id, coupon_code, firm_id, assigned_at) VALUES (?, ?, ?, NOW())";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("isi", $customerId, $couponCode, $firm_id);
    if ($insertStmt->execute()) {
        $insertStmt->close();
        return ['success' => true];
    } else {
        $insertStmt->close();
        return ['success' => false, 'error' => 'Failed to assign coupon'];
    }
}

// Auto-insert scheme entry for customer (registration-based)
function autoInsertSchemeEntry($conn, $customerId, $firm_id) {
    $now = date('Y-m-d');
    // Find all active schemes for this firm with auto_entry_on_registration=1, within date range, and not already entered
    $sql = "SELECT s.id FROM schemes s
            WHERE s.firm_id = ?
              AND s.status = 'active'
              AND s.auto_entry_on_registration = 1
              AND s.start_date <= ?
              AND s.end_date >= ?
              AND NOT EXISTS (
                  SELECT 1 FROM scheme_entries se
                  WHERE se.scheme_id = s.id AND se.customer_id = ?
              )";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issi", $firm_id, $now, $now, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $schemeIds = [];
    while ($row = $result->fetch_assoc()) {
        $schemeIds[] = $row['id'];
    }
    $stmt->close();
    if (empty($schemeIds)) {
        return ['success' => false, 'error' => 'No eligible schemes for auto entry'];
    }
    $successCount = 0;
    foreach ($schemeIds as $schemeId) {
        $insertSql = "INSERT INTO scheme_entries (scheme_id, customer_id, entry_date, status, entry_method) VALUES (?, ?, NOW(), 'active', 'registration')";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("ii", $schemeId, $customerId);
        if ($insertStmt->execute()) {
            $successCount++;
        }
        $insertStmt->close();
    }
    if ($successCount > 0) {
        return ['success' => true, 'entries_created' => $successCount];
    } else {
        return ['success' => false, 'error' => 'Failed to create scheme entries'];
    }
}

// Get invoice page URL from config
function getInvoicePageUrl($conn, $firm_id, $isGst) {
    $config = getFirmConfig($conn, $firm_id);
    if ($isGst && !empty($config['gst_bill_page_url'])) {
        return $config['gst_bill_page_url'];
    } elseif (!$isGst && !empty($config['non_gst_bill_page_url'])) {
        return $config['non_gst_bill_page_url'];
    }
    // Fallback
    return $isGst ? 'invoice.php' : 'performa_invoice.php';
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_invoice_number':
            $isGst = isset($_POST['isGst']) ? filter_var($_POST['isGst'], FILTER_VALIDATE_BOOLEAN) : true;
            echo json_encode(['success' => true, 'invoiceNumber' => getNextInvoiceNumber($conn, $firm_id, $isGst)]);
            break;
            
        case 'search_customers':
            $term = $_POST['term'] ?? '';
            $result = searchCustomers($conn, $term, $firm_id);
            echo json_encode($result);
            break;
            
        case 'get_customer_details':
            $customerId = $_POST['customerId'] ?? 0;
            $result = getCustomerDetails($conn, $customerId, $firm_id);
            echo json_encode($result);
            break;
            
        case 'update_customer':
            $customerId = $_POST['customerId'] ?? 0;
            $customerData = [
                'customerId' => $customerId,
                'firstName' => $_POST['firstName'] ?? '',
                'lastName' => $_POST['lastName'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'address' => $_POST['address'] ?? '',
                'city' => $_POST['city'] ?? '',
                'state' => $_POST['state'] ?? '',
                'postalCode' => $_POST['postalCode'] ?? '',
                'panNumber' => $_POST['panNumber'] ?? '',
                'aadhaarNumber' => $_POST['aadhaarNumber'] ?? '',
                'dob' => $_POST['dob'] ?? null,
                'docType' => $_POST['docType'] ?? ''
            ];
            
            $result = updateCustomer($conn, $customerData, $firm_id);
            echo json_encode($result);
            break;
            
        case 'get_customer_balance':
            $customerId = $_POST['customerId'] ?? 0;
            $result = getCustomerBalance($conn, $customerId, $firm_id);
            echo json_encode($result);
            break;
            
        case 'search_products':
            $term = $_POST['term'] ?? '';
            $result = searchJewellery($conn, $term, $firm_id);
            echo json_encode($result);
            break;
            
        case 'get_product_by_barcode':
            $huid = $_POST['huid'] ?? '';
            $result = getJewelleryByHUID($conn, $huid, $firm_id);
            echo json_encode($result);
            break;
            
        case 'get_gold_price':
            $material = $_POST['material'] ?? 'Gold';
            $purity = $_POST['purity'] ?? '99.99';
            $rate = getCurrentGoldPrice($conn, $material, $purity, $firm_id);
            echo json_encode(['success' => true, 'rate' => $rate]);
            break;
            
        case 'add_customer':
            $customerData = [
                'firstName' => $_POST['firstName'] ?? '',
                'lastName' => $_POST['lastName'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'address' => $_POST['address'] ?? '',
                'city' => $_POST['city'] ?? '',
                'dob' => $_POST['dob'] ?? null
            ];
            
            $result = addCustomer($conn, $customerData, $firm_id);
            echo json_encode($result);
            break;
            
        case 'calculate_urd_value':
            $weight = floatval($_POST['weight'] ?? 0);
            $purity = floatval($_POST['purity'] ?? 0);
            $result = calculateURDValue($conn, $weight, $purity, $firm_id);
            echo json_encode($result);
            break;
            
        case 'add_urd_item':
            $urdData = [
                'customerId' => $_POST['customerId'] ?? 0,
                'itemName' => $_POST['itemName'] ?? '',
                'grossWeight' => floatval($_POST['grossWeight'] ?? 0),
                'lessWeight' => floatval($_POST['lessWeight'] ?? 0),
                'netWeight' => floatval($_POST['netWeight'] ?? 0),
                'purity' => floatval($_POST['purity'] ?? 0),
                'rate' => floatval($_POST['rate'] ?? 0),
                'fineWeight' => floatval($_POST['fineWeight'] ?? 0),
                'totalAmount' => floatval($_POST['totalAmount'] ?? 0),
                'notes' => $_POST['notes'] ?? ''
            ];
            
            $result = addURDItem($conn, $urdData, $firm_id, $user_id);
            echo json_encode($result);
            break;
            
        case 'generate_bill':
            // Turn off output buffering to catch PHP errors
            if (ob_get_level()) ob_end_clean();
            
            // Debug: Log received data
            error_log("Generate bill data: " . print_r($_POST, true));
            
            $customerId = $_POST['customerId'] ?? null;
            if (!$customerId) {
                echo json_encode(['success' => false, 'error' => 'Customer not selected']);
                break;
            }
            
            // Validate and sanitize input data
            try {
                $items = json_decode($_POST['items'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid items JSON: " . json_last_error_msg());
                }
                
                $paymentMethods = isset($_POST['paymentMethods']) ? json_decode($_POST['paymentMethods'], true) : null;
                if (isset($_POST['paymentMethods']) && json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid payment methods JSON: " . json_last_error_msg());
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                break;
            }
            
            $saleData = [
                'customerId' => intval($customerId),
                'invoiceNumber' => $_POST['invoiceNumber'] ?? getNextInvoiceNumber($conn, $firm_id, true),
                'saleDate' => $_POST['saleDate'] ?? date('Y-m-d'),
                'totalMetalAmount' => floatval($_POST['totalMetalAmount'] ?? 0),
                'totalStoneAmount' => floatval($_POST['totalStoneAmount'] ?? 0),
                'totalMakingCharges' => floatval($_POST['totalMakingCharges'] ?? 0),
                'totalOtherCharges' => floatval($_POST['totalOtherCharges'] ?? 0),
                'discount' => floatval($_POST['discount'] ?? 0),
                'urdAmount' => floatval($_POST['urdAmount'] ?? 0),
                'subtotal' => floatval($_POST['subtotal'] ?? 0),
                'gstAmount' => floatval($_POST['gstAmount'] ?? 0),
                'grandTotal' => floatval($_POST['grandTotal'] ?? 0),
                'paidAmount' => floatval($_POST['paidAmount'] ?? 0),
                'advanceAmount' => floatval($_POST['advanceAmount'] ?? 0),
                'paymentMethod' => $_POST['paymentMethod'] ?? 'cash',
                'paymentReference' => $_POST['paymentReference'] ?? '',
                'isGstApplicable' => isset($_POST['isGstApplicable']) ? filter_var($_POST['isGstApplicable'], FILTER_VALIDATE_BOOLEAN) : true,
                'notes' => $_POST['notes'] ?? '',
                'couponDiscount' => floatval($_POST['couponDiscount'] ?? 0),
                'loyaltyDiscount' => floatval($_POST['loyaltyDiscount'] ?? 0),
                'manualDiscount' => floatval($_POST['manualDiscount'] ?? 0),
                'couponCode' => $_POST['couponCode'] ?? '',
                'urdId' => $_POST['urdId'] ?? null,
                'items' => $items,
                'paymentMethods' => $paymentMethods
            ];
            
            // Fix: Make sure all required fields are present
            if ($saleData['grandTotal'] <= 0) {
                echo json_encode(['success' => false, 'error' => 'Total amount must be greater than zero']);
                break;
            }
            
            $result = createJewellerySale($conn, $saleData, $firm_id, $user_id);
            
            // If coupon used, mark as used
            if ($result['success'] && !empty($saleData['couponCode'])) {
                $couponCode = $saleData['couponCode'];
                $customerId = $saleData['customerId'];
                $saleId = $result['saleId'];
                // Lookup coupon_id from coupon_code
                $getIdSql = "SELECT id FROM coupons WHERE coupon_code = ? LIMIT 1";
                $getIdStmt = $conn->prepare($getIdSql);
                $getIdStmt->bind_param("s", $couponCode);
                $getIdStmt->execute();
                $getIdResult = $getIdStmt->get_result();
                $couponRow = $getIdResult->fetch_assoc();
                $couponId = $couponRow['id'] ?? 0;
                $getIdStmt->close();
                if ($couponId) {
                    $updateSql = "UPDATE customer_assigned_coupons SET status='used', last_used_date=NOW(), related_sale_id=? WHERE customer_id=? AND coupon_id=? AND firm_id=?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("iiii", $saleId, $customerId, $couponId, $firm_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
            
            // Ensure proper JSON output
            echo json_encode($result);
            break;
            
        case 'get_product_image':
            $productId = $_POST['product_id'] ?? 0;
            $sql = "SELECT image_path FROM jewellery_items WHERE id = ? AND firm_id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ii", $productId, $firm_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                if ($row && !empty($row['image_path'])) {
                    // Return the correct path for the image
                    $imageUrl = "/uploads/jewelry/" . $row['image_path'];
                    echo json_encode(['success' => true, 'image_url' => $imageUrl]);
                } else {
                    // Default fallback image
                    echo json_encode(['success' => true, 'image_url' => "/uploads/jewelry/no_image.png"]);
                }
            } else {
                echo json_encode(['success' => false, 'image_url' => "/uploads/jewelry/no_image.png", 'error' => $conn->error]);
            }
            break;
            
        case 'get_firm_config':
            $config = getFirmConfig($conn, $firm_id);
            if ($config) {
                echo json_encode([
                    'success' => true,
                    'config' => [
                        'non_gst_bill_page_url' => $config['non_gst_bill_page_url'],
                        'gst_bill_page_url' => $config['gst_bill_page_url'],
                        'coupon_code_apply_enabled' => (bool)$config['coupon_code_apply_enabled'],
                        'schemes_enabled' => (bool)$config['schemes_enabled'],
                        'gst_rate' => (float)$config['gst_rate'],
                        'loyalty_discount_percentage' => (float)$config['loyalty_discount_percentage'],
                        'welcome_coupon_enabled' => (bool)$config['welcome_coupon_enabled'],
                        'welcome_coupon_code' => $config['welcome_coupon_code'],
                        'post_purchase_coupon_enabled' => isset($config['post_purchase_coupon_enabled']) ? (bool)$config['post_purchase_coupon_enabled'] : false,
                        'auto_scheme_entry' => (bool)$config['auto_scheme_entry'],
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No configuration found']);
            }
            break;
            
        case 'assign_welcome_coupon':
            $customerId = $_POST['customerId'] ?? 0;
            $couponCode = $_POST['couponCode'] ?? '';
            if (!$customerId || !$couponCode) {
                echo json_encode(['success' => false, 'error' => 'Missing customer or coupon code']);
                break;
            }
            $result = assignWelcomeCoupon($conn, $customerId, $firm_id, $couponCode);
            echo json_encode($result);
            break;
        case 'auto_scheme_entry':
            $customerId = $_POST['customerId'] ?? 0;
            if (!$customerId) {
                echo json_encode(['success' => false, 'error' => 'Missing customer']);
                break;
            }
            $result = autoInsertSchemeEntry($conn, $customerId, $firm_id);
            echo json_encode($result);
            break;
        case 'get_invoice_page_url':
            $isGst = isset($_POST['isGst']) ? filter_var($_POST['isGst'], FILTER_VALIDATE_BOOLEAN) : true;
            $url = getInvoicePageUrl($conn, $firm_id, $isGst);
            echo json_encode(['success' => true, 'url' => $url]);
            break;
        case 'validate_coupon':
            $couponCode = $_POST['couponCode'] ?? '';
            $customerId = $_POST['customerId'] ?? 0;
            if (!$couponCode || !$customerId) {
                echo json_encode(['success' => false, 'error' => 'Missing coupon code or customer']);
                break;
            }
            // Corrected SQL to use coupon_id and coupon_code
            $sql = "SELECT cac.id, cac.status, c.discount_type, c.discount_value FROM customer_assigned_coupons cac JOIN coupons c ON cac.coupon_id = c.id WHERE cac.customer_id = ? AND c.coupon_code = ? AND cac.firm_id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $customerId, $couponCode, $firm_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ($row['status'] === 'used') {
                    echo json_encode(['success' => false, 'error' => 'Coupon already used']);
                } else {
                    $discount = 0;
                    $discountType = $row['discount_type'];
                    $discountValue = floatval($row['discount_value']);
                    if ($discountType === 'amount') {
                        $discount = $discountValue;
                    } else if ($discountType === 'percent') {
                        $discount = $discountValue; // send percent, let frontend handle
                    }
                    echo json_encode(['success' => true, 'discount' => $discount, 'discount_type' => $discountType]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid or unassigned coupon']);
            }
            $stmt->close();
            break;
        case 'fetch_customer_coupons':
            $customerId = $_POST['customerId'] ?? 0;
            if (!$customerId) {
                echo json_encode(['success' => false, 'error' => 'Missing customer']);
                break;
            }
            $sql = "SELECT cac.id, cac.coupon_code, cac.status, cac.assigned_at, c.discount_type, c.discount_value, c.expiry_date, c.description
                    FROM customer_assigned_coupons cac
                    JOIN coupons c ON cac.coupon_code = c.coupon_code
                    WHERE cac.customer_id = ? AND cac.firm_id = ?
                      AND (cac.status = 'active' OR cac.status IS NULL OR cac.status = '')
                      AND (c.expiry_date IS NULL OR c.expiry_date >= CURDATE())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $customerId, $firm_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $coupons = [];
            while ($row = $result->fetch_assoc()) {
                $coupons[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'coupons' => $coupons]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
    exit;
}

$currentDate = date('Y-m-d');
$invoiceNumber = getNextInvoiceNumber($conn, $firm_id, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jewelry Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../css/dashboard.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.0/html5-qrcode.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    },
                    colors: {
                        gold: {
                            50: '#FFF9E5',
                            100: '#FFF0BF',
                            200: '#FFE380',
                            300: '#FFD740',
                            400: '#FFC700',
                            500: '#FFB800',
                            600: '#E6A600',
                            700: '#CC9200',
                            800: '#B37F00',
                            900: '#996C00',
                        }
                    },
                    backgroundImage: {
                        'gradient-gold': 'linear-gradient(to right, #FFF9E5, #FFE380, #FFF9E5)'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        
        /* Sidebar and Header Styles */
        body { background-color: #f8f9fa; font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; }
        .header-gradient { background: linear-gradient(to right, #4f46e5, #7c3aed); }
        .sidebar { /* ...sidebar styles... */ }
        * { animation: none !important; transition: none !important; }

        /* --- BILL PAPER ENHANCEMENT --- */
        .bill-paper {
            background: #fff;
            width: 100%;
            min-height: calc(100vh - 64px); /* header height */
            margin: 0;
            padding: 0;
            border-radius: 0;
            box-shadow: none;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
        }
        .bill-paper > *:not(:last-child) { margin-bottom: 0; }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: #FFB800;
            border-radius: 4px;
        }
        
        /* Compact table */
        .compact-table th, .compact-table td {
            padding: 0.25rem 0.5rem;
        }
        
        /* Floating action button */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99;
            box-shadow: 0 4px 20px rgba(204, 146, 0, 0.3);
        }
        
        /* Input field focus effect */
        .input-focus-effect:focus {
            box-shadow: 0 0 0 2px rgba(255, 184, 0, 0.2);
        }
        
        #customerResults {
            position: absolute;
            top: calc(100% + 4px); /* Position it 4px below the input */
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 1000; /* Increased z-index to ensure it stays on top */
            max-height: 200px;
            overflow-y: auto;
            margin-top: 0; /* Remove any default margin */
        }

        /* Add this new CSS for the customer search container */
        .customer-search-container {
            position: relative; /* Add this to contain the absolute positioned results */
        }
        /* Loader */
        .loader {
            border-top-color: #FFB800;
            -webkit-animation: spinner 1.5s linear infinite;
            animation: spinner 1.5s linear infinite;
        }
        @-webkit-keyframes spinner {
            0% { -webkit-transform: rotate(0deg); }
            100% { -webkit-transform: rotate(360deg); }
        }
        @keyframes spinner {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Camera modal */
        .camera-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 75%; /* 4:3 Aspect Ratio */
            overflow: hidden;
            background-color: #000;
        }
        .camera-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .scan-area {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 10px;
            box-shadow: 0 0 0 4000px rgba(0, 0, 0, 0.3);
            z-index: 10;
        }
        .scan-line {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #FFB800;
            animation: scan 2s linear infinite;
        }
        @keyframes scan {
            0% { top: 0; }
            50% { top: 100%; }
            100% { top: 0; }
        }
    </style>
</head>
<body class="relative">
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
            <a href="add-product.php" class="menu-item">
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
            <a href="sell.php" class="menu-item active">
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
        <div class="bill-paper">
            <div class="container mx-auto px-4 py-4">
                <div class="max-w-full mx-0 px-0 py-0">
                    <!-- Header with GST/Estimate Radio Buttons -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-2 bg-gradient-gold p-3 rounded-xl shadow-md">
                        <div>
                            <h1 class="text-xl sm:text-xl font-bold text-gold-800 flex items-center">
                                <i class="fas fa-gem mr-2 text-gold-600"></i>
                                Jewelry Billing System
                            </h1>
                            <div class="flex items-center mt-1">
                                <div class="flex items-center space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="billType" value="gst" class="form-radio text-gold-600" checked>
                                        <span class="ml-1 text-xs text-gray-700">GST Invoice</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="billType" value="estimate" class="form-radio text-gold-600">
                                        <span class="ml-1 text-xs text-gray-700">Estimate</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 sm:mt-0 bg-white bg-opacity-80 px-3 py-1 rounded-lg shadow-sm">
                            <p class="text-xs text-gray-700"><span class="font-medium">Date:</span> <?php echo date('d M Y'); ?></p>
                            <p class="text-xs text-gray-700"><span class="font-medium">Time:</span> <?php echo date('h:i A'); ?></p>
                        </div>
                    </div>
            
            <form id="billingForm" class="space-y-3">
                <!-- Invoice and Customer Section (Combined) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-blue-100 p-3 rounded-lg shadow-sm">
                        <label for="invoiceNumber" class="block text-xs font-medium text-gray-700 mb-1">Invoice No</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-blue-600">
                                <i class="fas fa-file-invoice"></i>
                            </span>
                            <input type="text" class="w-full pl-8 pr-2 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm input-focus-effect bg-white bg-opacity-90" id="invoiceNumber" value="<?php echo $invoiceNumber; ?>" readonly>
                        </div>
                    </div>
                    <div class="bg-pink-100 p-3 rounded-lg shadow-sm">
                        <label for="invoiceDate" class="block text-xs font-medium text-gray-700 mb-1">Date</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-pink-600">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <input type="date" class="w-full pl-8 pr-2 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-pink-500 focus:border-pink-500 text-sm input-focus-effect bg-white bg-opacity-90" id="invoiceDate" value="<?php echo $currentDate; ?>">
                        </div>
                    </div>
                    <div class="bg-gold-100 p-3 rounded-lg shadow-sm">
                        <label for="customerSearch" class="block text-xs font-medium text-gray-700 mb-1">Customer</label>
                        <div class="customer-search-container"> <!-- Add this wrapper div -->
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-gold-600">
                                    <i class="fas fa-user-circle"></i>
                                </span>
                                <input type="text" class="w-full pl-8 pr-8 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-gold-500 focus:border-gold-500 text-sm input-focus-effect" id="customerSearch" placeholder="Search by name or phone">
                                <input id="customerId" class="hidden" name="customerId" value="">
                                <button type="button" id="quickCustomerBtn" class="absolute inset-y-0 right-0 px-2 flex items-center text-blue-500">
                                    <i class="fas fa-bolt"></i>
                                </button>
                            </div>
                            <div id="customerResults" class="hidden"></div> <!-- Move this inside the container -->
                        </div>
                    </div>
                </div>
                
                <!-- Customer Details (Only shown when customer is selected) -->
                <div id="customerDetails" class="p-3 bg-gold-50 rounded-lg shadow-sm border border-gold-200 hidden">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <div class="bg-gold-200 p-1 rounded-full mr-2">
                                <i class="fas fa-user-circle text-gold-700"></i>
                            </div>
                            <img id="customerImage" class="hidden h-12 w-12 rounded-full border-2 border-gold-300 mr-2" src="" alt="Customer Photo">
                            <span id="customerName" class="font-medium text-sm"></span>
                        </div>
                        <div class="flex space-x-2">
                            <button type="button" id="editCustomerBtn" class="text-blue-500 hover:text-blue-700 bg-blue-100 p-1 rounded-full">
                                <i class="fas fa-edit"></i>
                            </button>
                           
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        <div class="flex items-center bg-white bg-opacity-70 p-1 rounded-lg">
                            <i class="fas fa-phone text-gold-600 mr-1"></i>
                            <span id="customerPhone" class="text-gray-700"></span>
                        </div>
                        <div class="flex items-center bg-white bg-opacity-70 p-1 rounded-lg">
                            <i class="fas fa-envelope text-gold-600 mr-1"></i>
                            <span id="customerEmail" class="text-gray-700"></span>
                        </div>
                        <div class="flex items-center bg-white bg-opacity-70 p-1 rounded-lg">
                            <i class="fas fa-money-bill-wave text-red-600 mr-1"></i>
                            <span id="customerDue" class="text-red-700">Due: ₹0.00</span>
                        </div>
                        <div class="flex items-center bg-white bg-opacity-70 p-1 rounded-lg">
                            <i class="fas fa-piggy-bank text-green-600 mr-1"></i>
                            <span id="customerAdvance" class="text-green-700">Advance: ₹0.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Product Search Section -->
                <div class="bg-white p-3 rounded-lg shadow-sm">
                    <div class="flex justify-between items-center mb-2">
                        <h2 class="text-sm font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-box mr-1 text-gold-600"></i>
                            Product Information
                        </h2>
                        <div class="flex space-x-2">
                            <button type="button" id="scanBarcodeBtn" class="text-xs px-2 py-1 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg shadow-sm flex items-center">
                                <i class="fas fa-qrcode mr-1"></i> Scan
                            </button>
                            <button type="button" id="addManualBtn" class="text-xs px-2 py-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg shadow-sm flex items-center">
                                <i class="fas fa-plus mr-1"></i> Manual
                            </button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="relative">
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-gold-600">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="w-full pl-8 pr-2 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-gold-500 focus:border-gold-500 text-sm input-focus-effect" id="productSearch" placeholder="Search by type, ID or HUID">
                            </div>
                            <div id="productResults" class="absolute z-10 w-full mt-1 bg-white shadow-lg rounded-lg hidden max-h-40 overflow-y-auto border border-gray-200"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Products Table -->
                <div class="bg-white p-3 rounded-lg shadow-sm overflow-x-auto">
                    <div class="flex justify-between items-center mb-2">
                        <h2 class="text-sm font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-list mr-1 text-gold-600"></i>
                            Product List
                        </h2>
                        <div class="flex space-x-2">
                            <div class="bg-gold-100 text-gold-700 px-2 py-1 rounded-full text-xs font-medium">
                                <span id="itemCount">0</span> items
                            </div>
                            <button type="button" id="addURDBtn" class="text-xs px-2 py-1 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg shadow-sm flex items-center">
                                <i class="fas fa-recycle mr-1"></i> Add URD Gold
                            </button>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 compact-table rounded-lg overflow-hidden" id="productsTable">
                            <thead class="bg-gradient-gold">
                                <tr>
                                    <th scope="col" class="text-left text-xs font-medium text-gray-700 uppercase tracking-wider py-1 px-2">Product</th>
                                    <th scope="col" class="text-left text-xs font-medium text-gray-700 uppercase tracking-wider py-1 px-2">Purity</th>
                                    <th scope="col" class="text-left text-xs font-medium text-gray-700 uppercase tracking-wider py-1 px-2">Gross/Net</th>
                                    <th scope="col" class="text-left text-xs font-medium text-gray-700 uppercase tracking-wider py-1 px-2">Stone</th>
                                    <th scope="col" class="text-left text-xs font-medium text-gray-700 uppercase tracking-wider py-2 px-2">Rate/g</th>
                                    <th scope="col" class="text-left text-xs font-medium text-gray-700 uppercase tracking-wider py-2 px-2">Making</th>
                                    <th scope="col" class="text-left text-xs font-medium text-gray-700 uppercase tracking-wider py-2 px-2">Amount</th>
                                    <th scope="col" class="text-left text-xs font-medium text-gray-700 uppercase tracking-wider py-2 px-2">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <!-- Products will be added here dynamically -->
                                <tr id="emptyRow">
                                    <td colspan="8" class="px-3 py-4 text-center text-xs text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-box-open text-gray-400 text-xl mb-1"></i>
                                            <p>No products added yet.</p>
                                            <p class="text-xs text-gray-400">Search or scan to add products.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <!-- Sub Total -->
                                <tr class="bg-gray-50">
                                    <td colspan="6" class="px-3 py-2"></td>
                                    <td class="px-3 py-2 font-bold text-sm">Sub Total:</td>
                                    <td class="px-3 py-2 font-bold text-sm" id="subTotal">₹0.00</td>
                                </tr>

                                <!-- Making Charge -->
                                <tr class="bg-gray-50">
                                    <td colspan="6" class="px-3 py-2"></td>
                                    <td class="px-3 py-2 font-bold text-sm">Making Charge:</td>
                                    <td class="px-3 py-2 font-bold text-sm" id="makingChargeTotal">₹0.00</td>
                                </tr>

                                <!-- URD Gold -->
                                <tr id="urdRow" class="bg-purple-50 hidden">
                                    <td colspan="6" class="px-3 py-2"></td>
                                    <td class="px-3 py-2 font-bold text-sm">URD Gold:</td>
                                    <td class="px-3 py-2 font-bold text-sm text-purple-700" id="urdAmount">-₹0.00</td>
                                </tr>

                                <!-- Discount Section -->
                                <tr class="bg-green-50">
                                    <td colspan="6" class="px-3 py-2"></td>
                                    <td class="px-3 py-2 font-bold text-sm">
                                        <div class="flex items-center">
                                            <span>Loyalty Discount:</span>
                                            <input type="number" id="loyaltyDiscount" class="ml-1 w-16 px-1 py-0.5 border border-gray-300 rounded text-sm font-bold" value="0">
                                            <select id="loyaltyDiscountType" class="ml-1 px-1 py-0.5 border border-gray-300 rounded text-xs">
                                                <option value="percent">%</option>
                                                <option value="amount">₹</option>
                                            </select>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 font-bold text-sm text-green-700" id="loyaltyDiscountAmount">-₹0.00</td>
                                </tr>

                                <tr class="bg-green-50">
                                    <td colspan="6" class="px-3 py-2"></td>
                                    <td class="px-3 py-2 font-bold text-sm">
                                        <div class="flex items-center">
                                            <span>Coupon:</span>
                                            <input type="text" id="couponCode" class="ml-1 w-26 px-1 py-0.5 border border-gray-300 rounded font-bold text-sm" placeholder="Code">
                                            <button type="button" id="applyCoupon" class="ml-1 px-1 py-0.5 bg-blue-500 text-white rounded font-bold text-sm">Apply</button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 font-bold text-sm text-green-700" id="couponDiscountAmount">-₹0.00</td>
                                </tr>

                                <tr class="bg-green-50">
                                    <td colspan="6" class="px-3 py-2"></td>
                                    <td class="px-3 py-2 font-bold text-sm">
                                        <div class="flex items-center">
                                            <span>Manual Discount:</span>
                                            <input type="number" id="manualDiscount" class="ml-1 w-14 px-1 py-0.5 border border-gray-300 rounded font-bold text-sm" value="0">
                                            <select id="manualDiscountType" class="ml-1 px-1 py-0.5 border border-gray-300 rounded font-bold text-sm">
                                                <option value="percent">%</option>
                                                <option value="amount">₹</option>
                                            </select>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 font-medium text-xs text-green-700" id="manualDiscountAmount">-₹0.00</td>
                                </tr>

                                <!-- GST Amount (Only shown for GST bills) -->
                                <tr id="gstRow" class="bg-blue-50">
                                    <td colspan="6" class="px-3 py-2"></td>
                                    <td class="px-3 py-2 font-bold text-sm">GST Amount (3%):</td>
                                    <td class="px-3 py-2 font-bold text-sm" id="gstAmount">₹0.00</td>
                                </tr>

                                <!-- Grand Total -->
                                <tr class="bg-gold-50">
                                    <td colspan="6" class="px-3 py-2"></td>
                                    <td class="px-3 py-2 font-bold text-base">Grand Total:</td>
                                    <td class="px-3 py-2 font-bold text-base text-gold-700" id="grandTotal">₹0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <!-- Payment Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="bg-white p-3 rounded-lg shadow-sm md:col-span-2">
                        <div class="flex justify-between items-center mb-2">
                            <h2 class="text-sm font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-money-bill-wave mr-1 text-gold-600"></i>
                                Payment Information
                            </h2>
                            <button type="button" id="splitPaymentBtn" class="text-xs px-2 py-1 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg shadow-sm flex items-center">
                                <i class="fas fa-random mr-1"></i> Split Payment
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-3 col-span-1">
                                <div class="flex items-center mb-2">
                                    <span class="text-xs font-medium text-green-700 mr-2">Advance: <span id="availableAdvance" class="font-bold">₹0.00</span></span>
                                    <input type="checkbox" id="useAdvanceCheckbox" class="ml-2 mr-1 align-middle">
                                    <label for="useAdvanceCheckbox" class="text-xs text-gray-700">Use for this bill</label>
                                </div>
                                <div id="advanceInputRow" class="flex items-center mb-2 hidden">
                                    <span class="text-xs text-gray-600 mr-2">Advance to use:</span>
                                    <input type="number" step="0.01" class="w-24 px-2 py-1 border border-gray-300 rounded-md text-xs" id="advanceAmount" value="0">
                                </div>
                                <div class="flex items-center mb-2">
                                    <span class="text-xs font-medium text-blue-700">Net Payable:</span>
                                    <span id="netPayableAmount" class="ml-2 text-sm font-bold text-gold-700">₹0.00</span>
                                </div>
                                <div class="flex items-center mb-2">
                                    <span class="text-xs font-medium text-red-700">Due Amount:</span>
                                    <span id="dueAmount" class="ml-2 text-sm font-bold text-red-700">₹0.00</span>
                                </div>
                                <div class="flex items-center mb-2">
                                    <span class="text-xs font-medium text-gray-700">Payment Status:</span>
                                    <span id="paymentStatus" class="ml-2 text-sm font-medium text-gray-600">Unpaid</span>
                                </div>
                            </div>
                            <div>
                                <label for="paymentMethod" class="block text-xs font-medium text-gray-700 mb-1">Payment Method</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-gold-600">
                                        <i class="fas fa-credit-card"></i>
                                    </span>
                                    <select class="w-full pl-8 pr-8 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-gold-500 focus:border-gold-500 text-xs appearance-none input-focus-effect" id="paymentMethod">
                                        <option value="cash">Cash</option>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="Debit Card">Debit Card</option>
                                        <option value="Bank">Bank</option>
                                        <option value="upi">UPI</option>
                                        <option value="Cheque">Cheque</option>
                                        <option value="Wallet">Wallet</option>
                                    </select>
                                    <span class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-gray-500">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label for="paymentReference" class="block text-xs font-medium text-gray-700 mb-1">Reference/Transaction ID</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-gold-600">
                                        <i class="fas fa-hashtag"></i>
                                    </span>
                                    <input type="text" class="w-full pl-8 pr-2 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-gold-500 focus:border-gold-500 text-xs input-focus-effect" id="paymentReference">
                                </div>
                            </div>
                            <div>
                                <label for="paidAmount" class="block text-xs font-medium text-gray-700 mb-1">Paid Amount</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-gold-600">
                                        <i class="fas fa-rupee-sign"></i>
                                    </span>
                                    <input type="number" step="0.01" class="w-full pl-8 pr-2 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-gold-500 focus:border-gold-500 text-xs input-focus-effect" id="paidAmount" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <div class="space-y-2">
                            <label class="block text-xs font-medium text-gray-700">Notes</label>
                            <textarea id="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-gold-500 focus:border-gold-500 text-sm" placeholder="Add any additional notes here..."></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" id="resetBtn" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gold-500">
                        Reset
                    </button>
                    <button type="button" id="saveAsDraftBtn" class="px-4 py-2 border border-gold-300 rounded-md text-sm font-medium text-gold-700 bg-gold-50 hover:bg-gold-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gold-500">
                        Save as Draft
                    </button>
                    <button type="button" id="generateBillBtn" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gold-600 hover:bg-gold-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gold-500">
                        Generate Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Create Customer Modal -->
<div id="createCustomerModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b px-4 py-2">
            <h3 class="text-base font-semibold text-gray-900 flex items-center">
                <i class="fas fa-user-plus mr-1 text-gold-600"></i>
                Create New Customer
            </h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" id="closeCustomerModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="customerForm" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="firstName" class="block text-xs font-medium text-gray-700 mb-1">First Name*</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="firstName" required>
                    </div>
                    <div>
                        <label for="lastName" class="block text-xs font-medium text-gray-700 mb-1">Last Name*</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="lastName" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="phone" class="block text-xs font-medium text-gray-700 mb-1">Phone Number*</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="phone" required>
                    </div>
                    <div>
                        <label for="email" class="block text-xs font-medium text-gray-700 mb-1">Email*</label>
                        <input type="email" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="email" required>
                    </div>
                </div>
                <div>
                    <label for="address" class="block text-xs font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="address">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="city" class="block text-xs font-medium text-gray-700 mb-1">City</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="city">
                    </div>
                    <div>
                        <label for="dob" class="block text-xs font-medium text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="dob">
                    </div>
                </div>
            </form>
        </div>
        <div class="bg-gray-50 px-4 py-2 flex justify-end space-x-2 rounded-b-lg">
            <button type="button" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-xs" id="cancelCustomerBtn">Cancel</button>
            <button type="button" class="px-3 py-1 bg-gold-600 text-white rounded-md hover:bg-gold-700 text-xs" id="saveCustomerBtn">Save Customer</button>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="editCustomerModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b px-4 py-2">
            <h3 class="text-base font-semibold text-gray-900 flex items-center">
                <i class="fas fa-user-edit mr-1 text-gold-600"></i>
                Edit Customer
            </h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" id="closeEditCustomerModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="editCustomerForm" class="space-y-3">
                <input type="hidden" id="editCustomerId">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="editFirstName" class="block text-xs font-medium text-gray-700 mb-1">First Name*</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editFirstName" required>
                    </div>
                    <div>
                        <label for="editLastName" class="block text-xs font-medium text-gray-700 mb-1">Last Name*</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editLastName" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="editPhone" class="block text-xs font-medium text-gray-700 mb-1">Phone Number*</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editPhone" required>
                    </div>
                    <div>
                        <label for="editEmail" class="block text-xs font-medium text-gray-700 mb-1">Email*</label>
                        <input type="email" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editEmail" required>
                    </div>
                </div>
                <div>
                    <label for="editAddress" class="block text-xs font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editAddress">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="editCity" class="block text-xs font-medium text-gray-700 mb-1">City</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editCity">
                    </div>
                    <div>
                        <label for="editState" class="block text-xs font-medium text-gray-700 mb-1">State</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editState">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="editPostalCode" class="block text-xs font-medium text-gray-700 mb-1">Postal Code</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editPostalCode">
                    </div>
                    <div>
                        <label for="editDob" class="block text-xs font-medium text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editDob">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="editPanNumber" class="block text-xs font-medium text-gray-700 mb-1">PAN Number</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editPanNumber">
                    </div>
                    <div>
                        <label for="editAadhaarNumber" class="block text-xs font-medium text-gray-700 mb-1">Aadhaar Number</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editAadhaarNumber">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">ID Document</label>
                    <div class="flex items-center space-x-2">
                        <select class="px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editDocType">
                            <option value="aadhar">Aadhar Card</option>
                            <option value="pan">PAN Card</option>
                            <option value="voter">Voter ID</option>
                            <option value="driving">Driving License</option>
                            <option value="passport">Passport</option>
                        </select>
                        <input type="text" class="px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="editDocNumber" placeholder="Document Number">
                        <input type="file" id="editDocFile" class="hidden">
                        <label for="editDocFile" class="px-3 py-1 bg-blue-500 text-white rounded-md cursor-pointer text-xs hover:bg-blue-600">
                            Upload
                        </label>
                        <span id="editDocFileName" class="text-xs text-gray-500">No file chosen</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Customer Photo</label>
                    <div class="flex items-center space-x-2">
                        <input type="file" id="editCustomerImage" accept="image/*" class="hidden">
                        <label for="editCustomerImage" class="px-3 py-1 bg-blue-500 text-white rounded-md cursor-pointer text-xs hover:bg-blue-600">
                            Upload Photo
                        </label>
                        <span id="editImageFileName" class="text-xs text-gray-500">No file chosen</span>
                    </div>
                    <div id="imagePreview" class="mt-2 hidden">
                        <img id="customerImagePreview" class="h-20 w-20 object-cover rounded-full border-2 border-gold-300">
                    </div>
                </div>
            </form>
        </div>
        <div class="bg-gray-50 px-4 py-2 flex justify-end space-x-2 rounded-b-lg">
            <button type="button" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-xs" id="cancelEditCustomerBtn">Cancel</button>
            <button type="button" class="px-3 py-1 bg-gold-600 text-white rounded-md hover:bg-gold-700 text-xs" id="saveEditCustomerBtn">Update Customer</button>
        </div>
    </div>
</div>

<!-- Barcode Scanner Modal -->
<div id="barcodeScannerModal" class="fixed inset-0 bg-black bg-opacity-80 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
        <div class="flex justify-between items-center border-b px-4 py-2">
            <h3 class="text-base font-semibold text-gray-900 flex items-center">
                <i class="fas fa-qrcode mr-1 text-green-600"></i>
                Scan QR/Barcode
            </h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" id="closeScannerModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div class="camera-container">
                <video id="qrScanner" class="w-full h-full bg-black rounded-lg"></video>
                <div class="scan-area">
                    <div class="scan-line"></div>
                </div>
            </div>
            <div class="mt-3 text-center text-sm text-gray-600">
                <p>Position the QR code or barcode within the scanner area</p>
                <div id="scanResult" class="mt-2 font-medium text-green-600 hidden"></div>
            </div>
            <div class="mt-3 flex space-x-2">
                <div class="relative flex-grow">
                    <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-green-600">
                        <i class="fas fa-barcode"></i>
                    </span>
                    <input type="text" class="w-full pl-8 pr-2 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 text-sm input-focus-effect" id="modalBarcodeInput" placeholder="Enter barcode/HUID code">
                </div>
                <button type="button" class="px-2 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg shadow-sm" id="modalSubmitBarcodeBtn">
                    <i class="fas fa-check"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Manual Product Modal with Enhanced Stone Details -->
<div id="addManualProductModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b px-4 py-2">
            <h3 class="text-base font-semibold text-gray-900 flex items-center">
                <i class="fas fa-box-open mr-1 text-gold-600"></i>
                Add Product Manually
            </h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" id="closeManualModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="manualProductForm" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="manualProductType" class="block text-xs font-medium text-gray-700 mb-1">Product Type*</label>
                        <select class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualProductType" required>
                            <option value="">Select Type</option>
                            <option value="Ring">Ring</option>
                            <option value="Necklace">Necklace</option>
                            <option value="Bracelet">Bracelet</option>
                            <option value="Earrings">Earrings</option>
                            <option value="Pendant">Pendant</option>
                            <option value="Bangle">Bangle</option>
                            <option value="Chain">Chain</option>
                        </select>
                    </div>
                    <div>
                        <label for="manualMaterial" class="block text-xs font-medium text-gray-700 mb-1">Material*</label>
                        <select class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualMaterial" required>
                            <option value="">Select Material</option>
                            <option value="Gold">Gold</option>
                            <option value="Silver">Silver</option>
                            <option value="Platinum">Platinum</option>
                            <option value="Diamond">Diamond</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="manualPurity" class="block text-xs font-medium text-gray-700 mb-1">Purity*</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualPurity" required placeholder="e.g. 22K, 92.5%">
                    </div>
                    <div>
                        <label for="manualHUID" class="block text-xs font-medium text-gray-700 mb-1">HUID Code</label>
                        <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualHUID" placeholder="Optional">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="manualGrossWeight" class="block text-xs font-medium text-gray-700 mb-1">Gross Weight (g)*</label>
                        <input type="number" step="0.001" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualGrossWeight" required>
                    </div>
                    <div>
                        <label for="manualNetWeight" class="block text-xs font-medium text-gray-700 mb-1">Net Weight (g)*</label>
                        <input type="number" step="0.001" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualNetWeight" required>
                    </div>
                    <div>
                        <label for="manualLessWeight" class="block text-xs font-medium text-gray-700 mb-1">Less Weight (g)</label>
                        <input type="number" step="0.001" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualLessWeight" value="0">
                    </div>
                </div>
                <!-- Enhanced Stone Details Section -->
                <div class="border border-purple-200 rounded-lg p-2 bg-purple-50/30">
                    <h4 class="text-xs font-medium text-purple-700 mb-2">Stone Details</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="manualStoneDetails" class="block text-xs font-medium text-gray-700 mb-1">Stone Type</label>
                            <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="manualStoneDetails" placeholder="e.g. Diamond, Ruby">
                        </div>
                        <div>
                            <label for="manualStoneWeight" class="block text-xs font-medium text-gray-700 mb-1">Stone Weight (g)</label>
                            <input type="number" step="0.001" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="manualStoneWeight" value="0">
                        </div>
                        <div>
                            <label for="manualStonePrice" class="block text-xs font-medium text-gray-700 mb-1">Stone Price</label>
                            <input type="number" step="0.01" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="manualStonePrice" value="0">
                        </div>
                        <div>
                            <label for="manualStoneColor" class="block text-xs font-medium text-gray-700 mb-1">Stone Color</label>
                            <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="manualStoneColor" placeholder="e.g. White, Blue">
                        </div>
                        <div>
                            <label for="manualStoneQuality" class="block text-xs font-medium text-gray-700 mb-1">Stone Quality</label>
                            <select class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="manualStoneQuality">
                                <option value="">Select Quality</option>
                                <option value="VS">VS (Very Slightly Included)</option>
                                <option value="SI">SI (Slightly Included)</option>
                                <option value="I1">I1 (Included 1)</option>
                                <option value="I2">I2 (Included 2)</option>
                                <option value="I3">I3 (Included 3)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="manualRatePerGram" class="block text-xs font-medium text-gray-700 mb-1">Rate Per Gram*</label>
                        <input type="number" step="0.01" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualRatePerGram" required>
                    </div>
                    <div>
                        <label for="manualMakingCharge" class="block text-xs font-medium text-gray-700 mb-1">Making Charge*</label>
                        <input type="number" step="0.01" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualMakingCharge" required>
                    </div>
                </div>
                <div>
                    <label for="manualMakingChargeType" class="block text-xs font-medium text-gray-700 mb-1">Making Charge Type*</label>
                    <select class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs" id="manualMakingChargeType" required>
                        <option value="Fixed">Fixed</option>
                        <option value="Percentage">Percentage</option>
                        <option value="PerGram">Per Gram</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="bg-gray-50 px-4 py-2 flex justify-end space-x-2 rounded-b-lg">
            <button type="button" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-xs" id="cancelManualBtn">Cancel</button>
            <button type="button" class="px-3 py-1 bg-gold-600 text-white rounded-md hover:bg-gold-700 text-xs" id="saveManualBtn">Add Product</button>
        </div>
    </div>
</div>

<!-- Enhanced URD Gold Modal -->
<div id="urdGoldModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b px-4 py-2">
            <h3 class="text-base font-semibold text-gray-900 flex items-center">
                <i class="fas fa-recycle mr-1 text-purple-600"></i>
                Add URD Gold
            </h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" id="closeURDModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="urdGoldForm" class="space-y-3">
                <div>
                    <label for="urdItemName" class="block text-xs font-medium text-gray-700 mb-1">Item Name*</label>
                    <input type="text" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="urdItemName" required placeholder="e.g. Old Ring, Broken Chain">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="urdGrossWeight" class="block text-xs font-medium text-gray-700 mb-1">Gross Weight (g)*</label>
                        <input type="number" step="0.001" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="urdGrossWeight" required>
                    </div>
                    <div>
                        <label for="urdLessWeight" class="block text-xs font-medium text-gray-700 mb-1">Less Weight (g)</label>
                        <input type="number" step="0.001" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="urdLessWeight" value="0">
                    </div>
                    <div>
                        <label for="urdNetWeight" class="block text-xs font-medium text-gray-700 mb-1">Net Weight (g)</label>
                        <input type="number" step="0.001" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="urdNetWeight" readonly>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="urdPurity" class="block text-xs font-medium text-gray-700 mb-1">Purity (%)*</label>
                        <select class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="urdPurity" required>
                            <option value="">Select Purity</option>
                            <option value="99.9">24K (99.9%)</option>
                            <option value="91.6">22K (91.6%)</option>
                            <option value="75.0">18K (75.0%)</option>
                            <option value="58.5">14K (58.5%)</option>
                            <option value="41.7">10K (41.7%)</option>
                        </select>
                    </div>
                    <div>
                        <label for="urdFineWeight" class="block text-xs font-medium text-gray-700 mb-1">Fine Weight (g)</label>
                        <input type="number" step="0.001" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="urdFineWeight" readonly>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="urdRate" class="block text-xs font-medium text-gray-700 mb-1">Rate Per Gram</label>
                        <input type="number" step="0.01" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="urdRate" readonly>
                    </div>
                    <div>
                        <label for="urdValue" class="block text-xs font-medium text-gray-700 mb-1">Total Value</label>
                        <input type="number" step="0.01" class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="urdValue" readonly>
                    </div>
                </div>
                <div>
                    <label for="urdImage" class="block text-xs font-medium text-gray-700 mb-1">Item Image</label>
                    <div class="flex items-center space-x-2">
                        <input type="file" id="urdImage" accept="image/*" class="hidden">
                        <label for="urdImage" class="px-3 py-1 bg-purple-500 text-white rounded-md cursor-pointer text-xs hover:bg-purple-600">
                            <i class="fas fa-upload mr-1"></i> Upload
                        </label>
                        <button type="button" id="captureURDImage" class="px-3 py-1 bg-blue-500 text-white rounded-md text-xs hover:bg-blue-600">
                            <i class="fas fa-camera mr-1"></i> Capture
                        </button>
                        <span id="urdImageName" class="text-xs text-gray-500">No image chosen</span>
                    </div>
                    <div id="urdImagePreview" class="mt-2 hidden">
                        <img id="urdPreviewImg" class="h-32 w-full object-cover rounded-lg border border-purple-200">
                    </div>
                </div>
                <div>
                    <label for="urdDescription" class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                    <textarea class="w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-xs" id="urdDescription" rows="2" placeholder="Description of old gold items..."></textarea>
                </div>
            </form>
        </div>
        <div class="bg-gray-50 px-4 py-2 flex justify-end space-x-2 rounded-b-lg">
            <button type="button" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-xs" id="cancelURDBtn">Cancel</button>
            <button type="button" class="px-3 py-1 bg-purple-600 text-white rounded-md hover:bg-purple-700 text-xs" id="applyURDBtn">Apply URD Gold</button>
        </div>
    </div>
</div>

<!-- Split Payment Modal -->
<div id="splitPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg mx-4">
        <div class="flex justify-between items-center border-b px-4 py-2">
            <h3 class="text-base font-semibold text-gray-900 flex items-center">
                <i class="fas fa-random mr-1 text-gold-600"></i>
                Split Payment
            </h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" id="closeSplitModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div class="mb-3">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-xs font-medium">Total Amount:</span>
                    <span class="text-sm font-bold text-gold-700" id="splitTotalAmount">0.00</span>
                </div>
                <div class="flex justify-between items-center mb-1">
                    <span class="text-xs font-medium">Remaining Amount:</span>
                    <span class="text-xs font-medium text-red-600" id="splitRemainingAmount">0.00</span>
                </div>
            </div>
            
            <div id="paymentMethods" class="space-y-2">
                <!-- Payment method rows will be added here -->
                <div class="payment-method-row grid grid-cols-12 gap-2 items-center">
                    <div class="col-span-5">
                        <select class="w-full px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs payment-method-select">
                            <option value="cash">Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Bank">Net Banking</option>
                            <option value="upi">UPI</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Wallet">Wallet</option>
                        </select>
                    </div>
                    <div class="col-span-4">
                        <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs payment-reference" placeholder="Reference">
                    </div>
                    <div class="col-span-2">
                        <input type="number" step="0.01" class="w-full px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs payment-amount" placeholder="Amount">
                    </div>
                    <div class="col-span-1 flex justify-center">
                        <button type="button" class="text-red-500 hover:text-red-700 remove-payment-method">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <button type="button" class="mt-2 px-2 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 text-xs flex items-center" id="addPaymentMethodBtn">
                <i class="fas fa-plus mr-1"></i> Add Payment Method
            </button>
        </div>
        <div class="bg-gray-50 px-4 py-2 flex justify-end space-x-2 rounded-b-lg">
            <button type="button" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-xs" id="cancelSplitBtn">Cancel</button>
            <button type="button" class="px-3 py-1 bg-gold-600 text-white rounded-md hover:bg-gold-700 text-xs" id="applySplitBtn">Apply Split Payment</button>
        </div>
    </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar and header JS from add-stock.php (sidebar toggling, profile menu, etc.)
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const mobileToggle = document.getElementById('mobile-toggle');
    const collapseToggle = document.getElementById('collapse-toggle');
    const logoToggle = document.getElementById('logoToggle');
    let sidebarState = 'full';
    
    function updateSidebarState(newState) {
        if (window.innerWidth <= 768) return;
        sidebar.classList.remove('collapsed', 'hidden');
        mainContent.classList.remove('collapsed', 'full');
        sidebarOverlay.classList.remove('show');
        switch (newState) {
            case 'full':
                collapseToggle.innerHTML = '<i class="ri-arrow-left-s-line text-xl"></i>';
                break;
            case 'collapsed':
                sidebar.classList.add('collapsed');
                mainContent.classList.add('collapsed');
                collapseToggle.innerHTML = '<i class="ri-arrow-right-s-line text-xl"></i>';
                break;
            case 'hidden':
                sidebar.classList.add('hidden');
                mainContent.classList.add('full');
                collapseToggle.innerHTML = '<i class="ri-menu-line text-xl"></i>';
                break;
        }
        sidebarState = newState;
    }
    
    function toggleSidebar() {
        if (window.innerWidth <= 768) {
            toggleMobileSidebar();
        } else {
            const states = ['full', 'collapsed', 'hidden'];
            const idx = states.indexOf(sidebarState);
            const next = states[(idx + 1) % states.length];
            updateSidebarState(next);
        }
    }
    
    function toggleMobileSidebar() {
        sidebar.classList.toggle('sidebar-expanded');
        sidebarOverlay.classList.toggle('show');
        document.body.classList.toggle('overflow-hidden');
        const icon = sidebar.classList.contains('sidebar-expanded')
            ? 'ri-close-line'
            : 'ri-menu-line';
        mobileToggle.innerHTML = `<i class="${icon} text-xl"></i>`;
    }
    
    mobileToggle?.addEventListener('click', toggleMobileSidebar);
    sidebarOverlay?.addEventListener('click', toggleMobileSidebar);
    collapseToggle?.addEventListener('click', toggleSidebar);
    logoToggle?.addEventListener('click', toggleSidebar);
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768 &&
            sidebar.classList.contains('sidebar-expanded') &&
            !sidebar.contains(e.target) &&
            !mobileToggle.contains(e.target)
        ) {
            toggleMobileSidebar();
        }
    });
    
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('sidebar-expanded');
            sidebarOverlay.classList.remove('show');
            document.body.classList.remove('overflow-hidden');
            mobileToggle.innerHTML = '<i class="ri-menu-line text-xl"></i>';
            updateSidebarState(sidebarState);
        } else {
            sidebar.classList.remove('collapsed', 'hidden');
            mainContent.classList.remove('collapsed', 'full');
        }
    });
    
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'b') toggleSidebar();
    });
    
    updateSidebarState('full');
    
    // Notifications Toggle
    const notificationsToggle = document.getElementById('notifications-toggle');
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    notificationsToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationsDropdown.classList.toggle('show');
    });
    
    document.addEventListener('click', (e) => {
        if (!notificationsDropdown.contains(e.target)) {
            notificationsDropdown.classList.remove('show');
        }
    });
    
    function toggleProfileMenu(event) {
        event.stopPropagation();
        const menu = document.getElementById('profileMenu');
        const isHidden = menu.classList.contains('hidden');
        document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
            dropdown.classList.add('hidden');
        });
        menu.classList.toggle('hidden');
        if (isHidden) {
            menu.classList.add('animate-fadeIn');
        }
    }
    
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('profileMenu');
        const profileButton = event.target.closest('.group button');
        if (!profileButton && !menu.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });
    
    document.getElementById('profileMenu').addEventListener('click', function(event) {
        event.stopPropagation();
    });
    
    window.toggleProfileMenu = toggleProfileMenu;
});
</script>
<script src="../js/sell.js"></script>
</body>
</html>
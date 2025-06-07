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

// Handle AJAX requests
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Add new customer
    if ($action == 'addCustomer') {
        // Get POST data
        $firstName = $_POST['firstName'];
        $lastName = $_POST['lastName'] ?? '';
        $phone = $_POST['phone'];
        $email = $_POST['email'] ?? '';
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';
        $postalCode = $_POST['postalCode'] ?? '';
        $country = $_POST['country'] ?? 'India';
        $gst = $_POST['gst'] ?? '';
        
        // Validate required fields
        if (empty($firstName) || empty($phone)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Name and phone are required']);
            exit;
        }
        
        // Insert new customer
        $sql = "INSERT INTO Customer (FirmID, FirstName, LastName, PhoneNumber, Email, Address, City, State, PostalCode, Country, IsGSTRegistered, GSTNumber, CreatedAt) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $isGstRegistered = !empty($gst) ? 1 : 0;
        $stmt->bind_param("isssssssssiss", $firm_id, $firstName, $lastName, $phone, $email, $address, $city, $state, $postalCode, $country, $isGstRegistered, $gst);
        
        if ($stmt->execute()) {
            $customerId = $stmt->insert_id;
            $customer = [
                'id' => $customerId,
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'PhoneNumber' => $phone,
                'Email' => $email,
                'Address' => $address,
                'GSTNumber' => $gst,
                'due_amount' => 0,
                'completedOrders' => []
            ];
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'customer' => $customer]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to add customer: ' . $stmt->error]);
        }
        exit;
    }
    
    if ($action == 'getGoldRate') {
       // Get the latest 24K gold rate from jewellery_price_config
       $sql = "SELECT rate FROM jewellery_price_config 
               WHERE firm_id = ? AND material_type = 'Gold' AND purity = '24K' 
               ";
       
       $stmt = $conn->prepare($sql);
       $stmt->bind_param("i", $firm_id);
       $stmt->execute();
       $result = $stmt->get_result();
       
       if ($result && $result->num_rows > 0) {
           $row = $result->fetch_assoc();
           $rate = $row['rate'];
       } else {
           $rate = 9810; // Default rate if not found
       }
       
       header('Content-Type: application/json');
       echo json_encode(['rate' => $rate]);
       exit;
   }

    // Get Order Details
    if ($action == 'getOrderDetails') {
        $orderId = $_GET['id'] ?? 0;
        
        if (!$orderId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Order ID is required']);
            exit;
        }

        // Fetch order details with customer and karigar information
        $orderQuery = "SELECT o.*, 
                              c.FirstName, c.LastName, c.PhoneNumber, c.Email, c.Address,
                              k.name as karigar_name, k.phone_number as karigar_phone
                      FROM jewellery_customer_order o 
                      LEFT JOIN Customer c ON o.customer_id = c.id 
                      LEFT JOIN karigars k ON o.karigar_id = k.id
                      WHERE o.id = ? AND o.FirmID = ?";
        
        $orderStmt = $conn->prepare($orderQuery);
        $orderStmt->bind_param("ii", $orderId, $firm_id);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        
        if ($orderResult->num_rows === 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        $order = $orderResult->fetch_assoc();

        // Fetch order items
        $itemsQuery = "SELECT * FROM jewellery_order_items WHERE order_id = ?";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bind_param("i", $orderId);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();

        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = $item;
        }

        $order['items'] = $items;

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'order' => $order]);
        exit;
    }

  
  
  

if ($action == 'updateOrder') {
    // Get JSON data
    $json_data = file_get_contents('php://input');
    $orderData = json_decode($json_data, true);
    
    if (!$orderData) {
        echo json_encode(['success' => false, 'message' => 'Invalid data received']);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update main order
        $updateOrderSql = "UPDATE jewellery_customer_order 
                          SET order_status = ?, 
                              priority = ?,
                              advance_amount = ?,
                              remaining_amount = grand_total - ?,
                              updated_at = NOW()
                          WHERE id = ? AND FirmID = ?";
        
        $stmt = $conn->prepare($updateOrderSql);
        if (!$stmt) {
            throw new Exception("Order update prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssddii", 
            $orderData['order_status'],
            $orderData['priority'],
            $orderData['advance_amount'],
            $orderData['advance_amount'],
            $orderData['id'],
            $firm_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Order update execute failed: " . $stmt->error);
        }

        // Update each item
        foreach ($orderData['items'] as $item) {
            $updateItemSql = "UPDATE jewellery_order_items 
                             SET karigar_id = ?, 
                                 updated_at = NOW()
                             WHERE id = ? AND firm_id = ?";
            
            $stmt = $conn->prepare($updateItemSql);
            if (!$stmt) {
                throw new Exception("Item update prepare failed: " . $conn->error);
            }

            $stmt->bind_param("iii",
                $item['karigar_id'],
                $item['id'],
                $firm_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Item update execute failed: " . $stmt->error);
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error updating order: ' . $e->getMessage()]);
    }
    exit;
}

// Get all karigars
if ($action == 'getKarigars') {
    $sql = "SELECT id, name, phone_number, default_making_charge, charge_type 
            FROM karigars 
            WHERE firm_id = ? AND status = 'active'
            ORDER BY name";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $firm_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $karigars = [];
    while ($row = $result->fetch_assoc()) {
        $karigars[] = $row;
    }
    
    echo json_encode(['success' => true, 'karigars' => $karigars]);
    exit;
}

  
  
  
  
  
   

    // Search customers
    if ($action == 'searchCustomers') {
        $search = $_GET['term'] ?? '';
        $sql = "SELECT c.id, c.FirstName, c.LastName, c.PhoneNumber, c.Email, c.Address 
                FROM Customer c
                WHERE c.FirmID = ? AND (c.FirstName LIKE ? OR c.LastName LIKE ? OR c.PhoneNumber LIKE ?)
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $searchTerm = "%$search%";
        $stmt->bind_param("isss", $firm_id, $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($customers);
        exit;
    }
    

    // --- Process Order Action ---
    if ($action == 'processOrder') {
        // Log incoming request
        error_log("Received order processing request");
        
        // Get JSON data
        $json_data = file_get_contents('php://input');
        error_log("Received data: " . $json_data);
        
        // Decode JSON
        $orderData = json_decode($json_data, true);
        
        if ($orderData === null) {
            error_log("JSON decode error: " . json_last_error_msg());
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
            exit;
        }

        header('Content-Type: application/json');

        // Basic validation
        if (!isset($orderData['customerId'], $orderData['cartItems'], $orderData['advanceAmount'], $orderData['paymentMethod'], $orderData['grandTotal'], $orderData['customerPhoneNumber']) || empty($orderData['cartItems'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid order data provided.']);
            exit;
        }

        $customerId = $orderData['customerId'];
        $cartItems = $orderData['cartItems'];
        $advanceAmount = $orderData['advanceAmount'];
        $paymentMethod = $orderData['paymentMethod'];
        $grandTotal = $orderData['grandTotal'];
        $customerPhoneNumber = $orderData['customerPhoneNumber']; // Get phone number
        
        // Calculate remaining amount
        $remainingAmount = $grandTotal - $advanceAmount;

        // Determine payment status
        $paymentStatus = ($advanceAmount > 0 && $advanceAmount < $grandTotal) ? 'partial' : (($advanceAmount >= $grandTotal) ? 'completed' : 'pending');

        // Start a transaction
        $conn->begin_transaction();

        try {
            // Generate a simple order number (can be improved for uniqueness and format)
            $orderNumber = 'ORD-' . date('YmdHis') . rand(10, 99);

            // Fetch customer name for the response
            $customerNameQuery = "SELECT FirstName, LastName FROM Customer WHERE id = ?";
            $customerNameStmt = $conn->prepare($customerNameQuery);
            $customerNameStmt->bind_param("i", $customerId);
            $customerNameStmt->execute();
            $customerNameResult = $customerNameStmt->get_result();
            $customerInfo = $customerNameResult->fetch_assoc();
            $customerFullName = trim($customerInfo['FirstName'] . ' ' . $customerInfo['LastName']);

            // Read karigar ID from incoming data
            $karigarId = $orderData['karigarId'] ?? 0; // Use ID from frontend, default to 0 if not set

            // 1. Insert into jewellery_customer_order
            $insertOrderSql = "INSERT INTO jewellery_customer_order
                               (FirmID, order_number, customer_id, karigar_id, total_metal_amount,
                               total_making_charges, total_stone_amount, grand_total,
                               advance_amount, remaining_amount, payment_method, payment_status,
                               order_status, priority, notes, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmtOrder = $conn->prepare($insertOrderSql);

            // Calculate total amounts from cart items (should match frontend grandTotal)
            $totalMetalAmount = (float)array_sum(array_column($cartItems, 'metalAmount'));
            $totalMakingCharges = (float)array_sum(array_column($cartItems, 'makingCharges'));
            $totalStoneAmount = (float)array_sum(array_column($cartItems, 'stonePrice')); // Use stonePrice from items

            $orderPriority = 'normal'; // Default priority, assuming not set per order in frontend yet
            $orderNotes = ''; // Default notes, assuming not set per order yet (designCustomization is per item)
            $orderStatus = 'pending';

            $stmtOrder->bind_param("isiiiiddddsssss",
                $firm_id,
                $orderNumber,
                $customerId,
                $karigarId, // **Binding the hardcoded $karigarId = 0**
                $totalMetalAmount,
                $totalMakingCharges,
                $totalStoneAmount,
                $grandTotal,
                $advanceAmount,
                $remainingAmount,
                $paymentMethod,
                $paymentStatus,
                $orderStatus,
                $orderPriority,
                $orderNotes,
                
            );

            // Execute the order insertion and get the new order ID
            if (!$stmtOrder->execute()) {
                throw new Exception('Error inserting order: ' . $stmtOrder->error);
            }
            
            // Get the order ID after successful insertion
            $orderId = $stmtOrder->insert_id;
            
            if (!$orderId) {
                throw new Exception('Failed to obtain order ID after insertion');
            }

            // 2. Insert into jewellery_order_items
            $insertItemSql = "INSERT INTO jewellery_order_items
                              (order_id, firm_id, karigar_id, item_name, product_type, design_reference, metal_type, purity, gross_weight, less_weight, net_weight, metal_amount, stone_type, stone_quality, stone_size, stone_quantity, stone_weight, stone_unit, stone_price, stone_details, making_type, making_charge_input, making_charges, size_details, design_customization, total_estimate, created_at)
                              VALUES (?, ?, ?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,  ?, ?, ?, ?, NOW())";

            $stmtItem = $conn->prepare($insertItemSql);

            foreach ($cartItems as $item) {
                $item_name = $item['itemName'];
                $product_type = $item['productType'];
                $design_reference = $item['designReference'];
                $metal_type = $item['metalType'];
                $purity = $item['purity'];
                $gross_weight = $item['netWeight'];
                $less_weight = 0;
                $net_weight = $item['netWeight'];
                $metal_amount = $item['metalAmount'];
                $stone_type = $item['stoneType'];
                $stone_quality = $item['stoneQuality'];
                $stone_size = $item['stoneSize'];
                $stone_quantity = $item['stoneQuantity'];
                $stone_weight = $item['stoneWeight'];
                $stone_unit = $item['stoneUnit'];
                $stone_price = $item['stonePrice'];
                $stone_details = $item['stoneDetails'];
                $making_type = $item['makingType'];
                $making_charge_input = $item['makingChargeInput'];
                $making_charges = $item['makingCharges'];
                $size_details = $item['sizeDetails'];
                $design_customization = $item['designCustomization'];
                $total_estimate = $item['totalEstimate'];

                // Read karigar ID from the current item data
                $karigarId = $item['karigarId'] ?? 0; // Use ID from item, default to 0 if not set

                $stmtItem->bind_param("iiissssdddddssdiddssdssdsd",
                    $orderId,
                    $firm_id,
                    $karigarId,
                    $item_name,
                    $product_type,
                    $design_reference,
                    $metal_type,
                    $purity,
                    $gross_weight,
                    $less_weight,
                    $net_weight,
                    $metal_amount,
                    $stone_type,
                    $stone_quality,
                    $stone_size,
                    $stone_quantity,
                    $stone_weight,
                    $stone_unit,
                    $stone_price,
                    $stone_details,
                    $making_type,
                    $making_charge_input,
                    $making_charges,
                    $size_details,
                    $design_customization,
                    $total_estimate
                );

                if (!$stmtItem->execute()) {
                    throw new Exception('Error inserting order item: ' . $stmtItem->error);
                }

                // Get the inserted item ID
                $itemId = $stmtItem->insert_id;

                // Handle image uploads for this item
                if (isset($item['referenceImages']) && is_array($item['referenceImages'])) {
                    $uploadDir = 'uploads/orders/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    foreach ($item['referenceImages'] as $index => $imageData) {
                        // Check if the image data is a base64 string
                        if (strpos($imageData, 'data:image') === 0) {
                            // Extract the base64 image data
                            $imageData = explode(',', $imageData)[1];
                            $imageData = base64_decode($imageData);
                            
                            // Generate unique filename
                            $newFileName = $orderId . '_' . $itemId . '_' . time() . '_' . $index . '.jpg';
                            $targetFilePath = $uploadDir . $newFileName;
                            
                            // Save the image file
                            if (file_put_contents($targetFilePath, $imageData)) {
                                // Insert into order_images table
                                $isPrimary = ($index === 0) ? 1 : 0; // First image is primary
                                
                                $imgSql = "INSERT INTO order_images (order_item_id, image_path, is_primary) 
                                          VALUES (?, ?, ?)";
                                $imgStmt = $conn->prepare($imgSql);
                                $imgStmt->bind_param("isi", $itemId, $targetFilePath, $isPrimary);
                                $imgStmt->execute();
                            }
                        }
                    }
                }
            }

            // 3. Insert into jewellery_payments if advance > 0
            if ($advanceAmount > 0) {
                $insertPaymentSql = "INSERT INTO Jewellery_Payments_Details
                                     (Firm_id, reference_type, reference_id, party_type, party_id, sale_id, payment_type, amount,  reference_no, remarks, created_at )
                                     VALUES (?, ?, ?, ?, ?,  ?, ?, ?, ?, ?, NOW())";

                $stmtPayment = $conn->prepare($insertPaymentSql);

                // Payment details for the universal payments table
                $paymentReferenceType = 'order'; // Linking to an order
                $paymentReferenceId = $orderId; // The new order ID
                $paymentPartyType = 'customer'; // Paid by customer
                $paymentPartyId = $customerId; // The customer ID
                $paymentSaleId = $orderId; // Link to the sale/order
                $paymentType = 'credit'; // It's a credit for the business
                $paymentNotes = 'Advance payment for Order #' . $orderNumber;
                $paymentReferenceNo = ''; // Optional transaction reference
                $paymentRemarks = ''; // Optional remarks
                $paymentTransactionType = 'credit'; // Type of transaction - using correct enum value

                $stmtPayment->bind_param("isiiiisdss",
                    $firm_id,
                    $paymentReferenceType,
                    $paymentReferenceId,
                    $paymentPartyType,
                    $paymentPartyId,
                    $paymentSaleId,
                    $paymentType,
                    $advanceAmount,
                   
                    $paymentReferenceNo,
                    $paymentRemarks
                 
                );

                if (!$stmtPayment->execute()) {
                    throw new Exception('Error inserting payment: ' . $stmtPayment->error);
                }
            }

            // Commit the transaction
            $conn->commit();

            // Success response
            echo json_encode([
                'success' => true,
                'message' => 'Order processed successfully!',
                'order' => [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'customer_id' => $customerId,
                    'customer_name' => $customerFullName, // Include customer name
                    'customer_phone_number' => $customerPhoneNumber, // Include phone number
                    'grand_total' => $grandTotal,
                    'advance_amount' => $advanceAmount,
                    'remaining_amount' => $remainingAmount,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'order_status' => $orderStatus, // Using the actual status variable
                    'items' => $cartItems // Return items for modal display
                ]
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            error_log("Order Processing Error: " . $e->getMessage()); // Log the error on the server
            echo json_encode(['success' => false, 'message' => 'Error processing order: ' . $e->getMessage()]);
        }
        exit; // Exit after handling the AJAX request
    }

    // Get orders
    if ($action == 'getOrders') {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        $sql = "SELECT o.*, 
                       c.FirstName, c.LastName, c.PhoneNumber,
                       k.name as karigar_name
                FROM jewellery_customer_order o
                LEFT JOIN Customer c ON o.customer_id = c.id
                LEFT JOIN karigars k ON o.karigar_id = k.id
                WHERE o.FirmID = ?";
        
        $params = [$firm_id];
        $types = "i";
        
        if (!empty($search)) {
            $sql .= " AND (o.order_number LIKE ? OR c.FirstName LIKE ? OR c.LastName LIKE ? OR o.item_name LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= "ssss";
        }
        
        if (!empty($status) && $status !== 'all') {
            $sql .= " AND o.order_status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        error_log("Get Orders SQL: " . $sql); // Log the SQL query
        error_log("Get Orders Params: " . print_r($params, true)); // Log the parameters
        error_log("Get Orders Types: " . $types); // Log the types string

        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             error_log("Prepare failed: " . $conn->error); // Log prepare errors
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'message' => 'Database query preparation failed.']);
             exit;
        }

        // Check if the number of parameters matches the number of type specifiers
        if (count($params) !== strlen($types)) {
             error_log("Parameter count mismatch: Expected " . strlen($types) . ", got " . count($params));
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'message' => 'Database query parameter mismatch.']);
             exit;
        }

        $stmt->bind_param($types, ...$params);
        
         if ($stmt->execute() === false) {
             error_log("Execute failed: " . $stmt->error); // Log execute errors
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'message' => 'Database query execution failed.']);
             exit;
        }

        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            // Get order items
            $itemsSql = "SELECT * FROM jewellery_order_items WHERE order_id = ?";
            $itemsStmt = $conn->prepare($itemsSql);
            $itemsStmt->bind_param("i", $row['id']);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            
            $row['items'] = [];
            while ($item = $itemsResult->fetch_assoc()) {
                $row['items'][] = $item;
            }
            
            $orders[] = $row;
        }
        
        error_log("Fetched " . count($orders) . " orders."); // Log the number of orders fetched
        error_log("First order data (including items): " . print_r($orders[0] ?? 'No orders', true)); // Log data of the first order

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'orders' => $orders]);
        exit;
    }
if ($action == 'searchKarigars') {
    $search = $_GET['term'] ?? '';
    $sql = "SELECT k.id, k.name, k.phone_number, k.alternate_phone, k.email, 
                   k.address_line1, k.address_line2, k.city, k.state, k.default_making_charge, k.charge_type
            FROM karigars k
            WHERE k.firm_id = ? AND k.status = 'active' 
            AND (k.name LIKE ? OR k.phone_number LIKE ? OR k.alternate_phone LIKE ?)
            ORDER BY k.name
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $searchTerm = "%$search%";
    $stmt->bind_param("isss", $firm_id, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $karigars = [];
    while ($row = $result->fetch_assoc()) {
        $karigars[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($karigars);
    exit;
}

// Add karigar
if ($action == 'addKarigar') {
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $alternate_phone = $_POST['alternate_phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $address_line1 = $_POST['address_line1'] ?? '';
    $address_line2 = $_POST['address_line2'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $country = $_POST['country'] ?? 'India';
    $default_making_charge = $_POST['default_making_charge'] ?? 0;
    $charge_type = $_POST['charge_type'] ?? 'per_gram';
    $gst_number = $_POST['gst_number'] ?? '';
    $pan_number = $_POST['pan_number'] ?? '';
    
    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Name and phone number are required']);
        exit;
    }
    
    // Check if karigar already exists
    $checkSql = "SELECT id FROM karigars WHERE firm_id = ? AND (name = ? OR phone_number = ?)";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("iss", $firm_id, $name, $phone);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Karigar with this name or phone number already exists']);
        exit;
    }
    
    $sql = "INSERT INTO karigars (firm_id, name, phone_number, alternate_phone, email, address_line1, address_line2, 
                                  city, state, postal_code, country, default_making_charge, charge_type, gst_number, 
                                  pan_number, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssssssdss", $firm_id, $name, $phone, $alternate_phone, $email, $address_line1, 
                      $address_line2, $city, $state, $postal_code, $country, $default_making_charge, 
                      $charge_type, $gst_number, $pan_number);
    
    if ($stmt->execute()) {
        $karigar_id = $conn->insert_id;
        
        // Return the newly created karigar data
        $selectSql = "SELECT id, name, phone_number, alternate_phone, email, address_line1, address_line2, 
                             city, state, default_making_charge, charge_type FROM karigars WHERE id = ?";
        $selectStmt = $conn->prepare($selectSql);
        $selectStmt->bind_param("i", $karigar_id);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $karigar = $result->fetch_assoc();
        
        echo json_encode(['success' => true, 'message' => 'Karigar added successfully', 'karigar' => $karigar]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add karigar']);
    }
    exit;
}

 


   
   
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8" />
 <meta name="viewport" content="width=device-width, initial-scale=1.0" />
 <title>Jewelry Billing System</title>
 <script src="https://cdn.tailwindcss.com"></script>
 <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 <!-- Add QR Scanner library -->
 <script src="https://unpkg.com/html5-qrcode"></script>
 <link rel="stylesheet" href="css/order.css">

</head>
<body class="pb-20">
 <!-- Edit Mode Indicator -->
 <div id="editModeIndicator" class="edit-mode-indicator">
   <i class="fas fa-edit"></i> Edit Mode: Updating item in cart
 </div>

 <!-- Toast Notification -->
 <div id="toast" class="toast">
   <i class="fas fa-check-circle"></i>
   <span id="toastMessage">Success!</span>
 </div>

 <!-- Header -->
 <div class="header-gradient p-3 text-white font-bold shadow-lg">
 <div class="flex items-center justify-between">
   <div class="flex items-center space-x-2">
 <i class="fas fa-gem text-white-600 text-lg"></i>
 <span class="font-medium text-sm"><?php echo htmlspecialchars($userInfo['FirmName']); ?></span>
 
</div>

   <div class="flex items-center gap-3">
    
    
     <div class="text-right text-xs">
       <div class="font-medium"><?php echo htmlspecialchars($userInfo['Name']); ?></div>
       <div class="text-white/80"><?php echo htmlspecialchars($userInfo['Role']); ?></div>
     </div>
     <div class="w-8 h-8 rounded-full bg-white/20 overflow-hidden">
       <?php if (!empty($userInfo['image_path'])): ?>
         <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="Profile" class="w-full h-full object-cover">
       <?php else: ?>
         <div class="w-full h-full flex items-center justify-center text-white">
           <i class="fas fa-user"></i>
         </div>
       <?php endif; ?>
     </div>
   </div>
   
 </div>
</div>

 <!-- Compact Search Bar Section -->
 <!-- Manufacturing Order Form Section -->


<!-- Tab Menu -->
<div class="tab-navigation bg-white shadow-sm ">
  <div class="flex border-b">
    <button class="tab-button active flex-1 py-3 px-4 relative" data-tab="order-form">
      <div class="flex items-center justify-center gap-2">
        <i class="fas fa-file-invoice text-blue-600"></i>
        <span class="font-medium text-sm">Order Form</span>
      </div>
      <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600 transform scale-x-100 transition-transform"></div>
    </button>
    <button class="tab-button flex-1 py-3 px-4 relative" data-tab="order-list">
      <div class="flex items-center justify-center gap-2">
        <i class="fas fa-list-ul text-gray-600"></i>
        <span class="font-medium text-sm">Order List</span>
      </div>
      <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600 transform scale-x-0 transition-transform"></div>
    </button>
  </div>
</div>

<!-- Order Form Tab -->
<div id="order-form" class="tab-content active">
  <div id="orderFormContainer"> <!-- New container div -->
  <div class="p-1 compact-form">
 <div class="bg-pink-50 p-1 m-1 rounded-md shadow-sm">
  <div class="grid grid-cols-2 gap-2">
    <!-- Customer Search -->
    <div class="field-container">
      <input type="text" 
        id="customerName" 
        class="input-field text-xs  h-8 pl-7 pr-8 w-full bg-white border border-gray-200 rounded-md" 
        placeholder="Search customer..." />
      <i class="fas fa-user field-icon text-blue-500"></i>
      <button class="camera-btn" onclick="showCustomerModal()">
        <i class="fas fa-plus"></i>
      </button>
      <div id="customerDropdown" class="customer-dropdown">
        <!-- Customer list will appear here -->
      </div>
    </div>

    <!-- Karigar Search -->
    <div class="field-container">
      <input type="text" 
        id="karigarSearch" 
        class="input-field text-xs  h-8 pl-7 pr-8 w-full bg-white border border-gray-200 rounded-md" 
        placeholder="Search karigar..." />
      <i class="fas fa-user-tie field-icon text-blue-500"></i>
      <button class="camera-btn" type="button" onclick="showAddKarigarModal()"><i class="fas fa-plus"></i></button>
      <div id="karigarDropdown" class="karigar-dropdown"></div>
    </div>
  </div>
  
  <!-- Selection Details -->
  <div id="selectionDetails" class="selection-details hidden">
    <!-- Selection details will appear here -->
  </div>
  
  <!-- New Order Button -->

</div>   <!-- Order Header -->
    <div class="section-card order-header-section">
      <div class="section-title text-blue-800">
        <i class="fas fa-file-invoice"></i> Order Details
      </div>
      
      <div class="field-row">
        <div class="field-col hidden">
          <div class="field-label">Order No.</div>
          <div class="field-container">
            <input type="text" id="orderNo" class="input-field text-xs  py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="Auto-generated" readonly />
            <i class="fas fa-hashtag field-icon text-blue-500"></i>
          </div>
        </div>
        
        <div class="field-col">
          <div class="field-label">Item Name</div>
          <div class="field-container">
            <input type="text" id="itemName" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-gray-200 rounded-md" placeholder="Product name" />
            <i class="fas fa-tag field-icon text-blue-500"></i>
          </div>
        </div>
        
        <div class="field-col">
          <div class="field-label">Priority</div>
          <div class="field-container">
            <select id="priorityLevel" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-red-200 rounded-md">
              <option value="Normal">Normal</option>
              <option value="High">High</option>
              <option value="Urgent">Urgent</option>
            </select>
            <i class="fas fa-flag field-icon text-red-500"></i>
          </div>
        </div>
      </div>
      
      <div class="field-row">
        <div class="field-col">
          <div class="field-label">Product Type</div>
          <div class="field-container">
            <select id="productType" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-gray-200 rounded-md">
              <option value="">Select Type</option>
              <option value="Ring">Ring</option>
              <option value="Necklace">Necklace</option>
              <option value="Bracelet">Bracelet</option>
              <option value="Earring">Earring</option>
              <option value="Pendant">Pendant</option>
              <option value="Bangle">Bangle</option>
              <option value="Chain">Chain</option>
            </select>
            <i class="fas fa-list field-icon text-blue-500"></i>
          </div>
        </div>
        
        <div class="field-col">
          <div class="field-label">Design Reference</div>
          <div class="field-container">
            <input type="text" id="designReference" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-gray-200 rounded-md" placeholder="Enter reference" />
            <i class="fas fa-image field-icon text-blue-500"></i>
          </div>
        </div>
        
        <div class="field-col">
          <div class="field-label">Expected Delivery</div>
          <div class="field-container">
            <input type="date" id="expectedDelivery" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-gray-200 rounded-md" />
            <i class="fas fa-calendar-alt field-icon text-blue-500"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Metal Details Section -->
    <div class="section-card material-section">
  <div class="section-title text-amber-800">
    <i class="fas fa-coins"></i> Metal Details
  </div>
 
  <div class="field-row">
    <!-- 24K Gold Rate (₹) -->
    <div class="field-col">
      <div class="field-label">24K Gold Rate (₹)</div>
      <div class="field-container">
        <input type="number" 
          id="goldRate24k" 
          class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md" 
          placeholder="Enter 24K rate" 
          value="0"
          onchange="calculateMetalAmount()" />
        <i class="fas fa-rupee-sign field-icon text-amber-500"></i>
      </div>
    </div>

    <!-- Metal Type -->
    <div class="field-col">
      <div class="field-label">Metal Type</div>
      <div class="field-container">
        <select id="metalType" 
          class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md"
          onchange="calculateMetalAmount()">
          <option value="Gold">Gold</option>
          <option value="Silver">Silver</option>
          <option value="Platinum">Platinum</option>
        </select>
        <i class="fas fa-circle field-icon text-amber-500"></i>
      </div>
    </div>
    
    <!-- Purity -->
    <div class="field-col">
      <div class="field-label">Purity (% or KT)</div>
      <div class="field-container">
        <input type="number" 
          id="purity" 
          class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md" 
          placeholder="e.g. 92.0" 
          value="92.0"
          step="0.1" 
          min="0" 
          max="100"
          onchange="calculateMetalAmount()" />
        <i class="fas fa-percentage field-icon text-amber-500"></i>
      </div>
    </div>
  </div>
  
 
  <div class="field-row">
    <!-- Gross Weight -->
    <div class="field-col">
      <div class="field-label">Gross Weight (g)</div>
      <div class="field-container">
        <input type="number" 
          id="grossWeight" 
          class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" 
          placeholder="Gross wt" 
          step="0.01" 
          value="0"
          onchange="calculateNetWeight()" />
        <i class="fas fa-weight-scale field-icon text-blue-500"></i>
      </div>
    </div>
    
    <!-- Less Weight -->
    <div class="field-col">
      <div class="field-label">Less Weight (g)</div>
      <div class="field-container">
        <input type="number" 
          id="lessWeight" 
          class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-yellow-200 rounded-md" 
          placeholder="Less wt" 
          step="0.01" 
          value="0"
          onchange="calculateNetWeight()" />
        <i class="fas fa-minus-circle field-icon text-yellow-500"></i>
      </div>
    </div>
    
    <!-- Net Weight -->
    <div class="field-col">
      <div class="field-label">Net Weight (g)</div>
      <div class="field-container">
        <input type="number" 
          id="netWeight" 
          class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
          placeholder="Net wt" 
          step="0.01" 
          value="0"
          onchange="calculateMetalAmount()"
          readonly />
        <i class="fas fa-balance-scale field-icon text-green-500"></i>
      </div>
    </div>
  </div>

  <!-- Metal Amount -->
  <div class="field-row mt-2">
      <div class="field-col">
      <div class="field-label">Purity Rate (₹/g)</div>
      <div class="field-container">
        <input type="number" 
          id="purityRate" 
          class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md" 
          placeholder="Purity rate" 
          readonly />
        <i class="fas fa-rupee-sign field-icon text-amber-500"></i>
      </div>
    </div>
    <div class="field-col">
      <div class="field-label">Metal Amount (₹)</div>
      <div class="field-container">
        <input type="number" 
          id="metalAmount" 
          class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
          readonly />
        <i class="fas fa-rupee-sign field-icon text-green-500"></i>
      </div>
    </div>
  </div>
</div>

    <!-- Stone Details Section -->
   <div class="section-card stone-section">
        <div class="section-title text-purple-800 cursor-pointer" onclick="toggleStoneSection()">
        <i class="fas fa-gem"></i> Stone Details
          <i class="fas fa-chevron-down ml-2 transition-transform" id="stoneSectionIcon"></i>
      </div>
        <div id="stoneSectionContent" class="hidden">
      <div class="field-row">
        <div class="field-col">
          <div class="field-label">Stone Type</div>
          <div class="field-container">
            <select id="stoneType" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-purple-200 rounded-md">
              <option value="None">None</option>
              <option value="Diamond">Diamond</option>
              <option value="Ruby">Ruby</option>
              <option value="Emerald">Emerald</option>
              <option value="Sapphire">Sapphire</option>
            </select>
            <i class="fas fa-gem field-icon text-purple-500"></i>
          </div>
        </div>
        
        <div class="field-col">
          <div class="field-label">Stone Quality</div>
          <div class="field-container">
            <input type="text" 
              id="stoneQuality" 
              class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" 
              placeholder="Quality description" />
            <i class="fas fa-star field-icon text-purple-500"></i>
          </div>
        </div>
        
        <div class="field-col">
          <div class="field-label">Stone Size</div>
          <div class="field-container">
            <input type="text" 
              id="stoneSize" 
              class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" 
              placeholder="Size details" />
            <i class="fas fa-ruler field-icon text-purple-500"></i>
          </div>
        </div>
      </div>
      
      <div class="field-row">
        <div class="field-col">
          <div class="field-label">Stone Quantity</div>
          <div class="field-container">
            <input type="number" 
              id="stoneQuantity" 
              class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" 
              placeholder="Quantity" 
              value="0"
              min="0" />
            <i class="fas fa-hashtag field-icon text-purple-500"></i>
          </div>
        </div>
        
        <div class="field-col">
          <div class="field-label">Stone Weight</div>
          <div class="field-container relative">
            <input type="number" 
              id="stoneWeight" 
              class="input-field text-xs font-bold py-0.5 pl-7 pr-14 h-7 bg-white border border-purple-200 rounded-md" 
              placeholder="Stone weight" 
              value="0"
              step="0.01"
              min="0" />
            <i class="fas fa-weight-scale field-icon text-purple-500"></i>
            <select id="stoneUnit" class="absolute right-0 top-0 text-xs font-bold h-7 w-12 border-l border-purple-200 rounded-r-md bg-gray-50 text-center">
              <option value="ct">ct</option>
              <option value="ratti">ratti</option>
            </select>
          </div>
        </div>
        
            <div class="field-col">
              <div class="field-label">Stone Price (₹)</div>
              <div class="field-container">
                <input type="number" 
                  id="stonePrice" 
                  class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" 
                  placeholder="Stone price" 
                  value="0"
                  step="0.01"
                  min="0"
                  oninput="calculateTotalEstimate()" /> <!-- Changed to oninput for immediate feedback -->
                <i class="fas fa-rupee-sign field-icon text-purple-500"></i>
              </div>
            </div>
          </div>
          
          <div class="field-row">
        <div class="field-col">
          <div class="field-label">Additional Details</div>
          <div class="field-container">
            <input type="text" 
              id="stoneDetails" 
              class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" 
              placeholder="Additional details" />
            <i class="fas fa-info-circle field-icon text-purple-500"></i>
              </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Making Charges & Size Details Section -->
    <div class="section-card making-section">
      <div class="section-title text-green-800">
        <i class="fas fa-hammer"></i> Making Charges & Size Details
      </div>
      <div class="field-row">
        <div class="field-col">
          <div class="field-label">Making Type</div>
          <div class="field-container">
              <select id="makingType" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-green-200 rounded-md" onchange="calculateMakingCharges()">
              <option value="per_gram">Per Gram</option>
              <option value="percentage">Percentage</option>
              <option value="fixed">Fixed Amount</option>
            </select>
            <i class="fas fa-cog field-icon text-green-500"></i>
          </div>
        </div>
        
        <div class="field-col">
          <div class="field-label">Making Charge</div>
          <div class="field-container">
            <input type="number" 
              id="makingCharge" 
              class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
              placeholder="Amount" 
              value="0"
                step="1"
                oninput="calculateMakingCharges()" /> <!-- Changed to oninput -->
            <i class="fas fa-rupee-sign field-icon text-green-500"></i>
          </div>
        </div>
        
        <div class="field-col">
          <div class="field-label">Size Details</div>
          <div class="field-container">
            <input type="text" 
              id="sizeDetails" 
              class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
              placeholder="Size specifications" />
            <i class="fas fa-ruler-combined field-icon text-green-500"></i>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Design Customization Section -->
    <div class="section-card design-section">
      <div class="section-title text-indigo-800">
        <i class="fas fa-paint-brush"></i> Design Customization
      </div>
      <div class="field-row">
        <div class="field-col">
          <div class="field-label">Customization Details</div>
          <div class="field-container">
            <textarea id="designCustomization" class="input-field text-xs font-medium py-2 pl-7 pr-2 bg-white border border-indigo-200 rounded-md h-16" placeholder="Enter customer's specific requirements..."></textarea>
            <i class="fas fa-pencil-alt field-icon text-indigo-500 top-4"></i>
          </div>
        </div>
      </div>
      
      <div class="field-row">
        <div class="field-label">Reference Images</div>
        <div class="flex flex-col gap-2">
          <!-- Image preview container -->
          <div id="imagePreviewContainer" class="flex flex-wrap gap-2 min-h-[60px] p-2 bg-gray-50 rounded-lg"></div>
          
          <!-- Upload/Capture buttons -->
          <div class="flex gap-2">
            <label class="flex-1">
              <input type="file" id="imageUpload" multiple accept="image/*" class="hidden" />
              <div class="flex items-center justify-center gap-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg cursor-pointer hover:bg-blue-100">
                <i class="fas fa-upload"></i>
                <span class="text-sm font-medium">Upload Images</span>
              </div>
            </label>
            
            <button type="button" id="captureImage" class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-50 text-green-600 rounded-lg cursor-pointer hover:bg-green-100">
              <i class="fas fa-camera"></i>
              <span class="text-sm font-medium">Capture Image</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Camera Modal -->
    <div id="cameraModal" class="modal">
      <div class="modal-content p-4 max-w-lg w-11/12">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold">Capture Image</h3>
          <button onclick="closeCameraModal()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div id="cameraContainer" class="relative aspect-video bg-black rounded-lg overflow-hidden mb-4">
          <video id="cameraFeed" autoplay playsinline class="w-full h-full object-cover"></video>
        </div>
        
        <div class="flex justify-end gap-3">
          <button onclick="closeCameraModal()" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg">Cancel</button>
          <button onclick="takePicture()" class="px-4 py-2 text-white bg-blue-500 rounded-lg flex items-center gap-2">
            <i class="fas fa-camera"></i>
            Capture
          </button>
        </div>
      </div>
    </div>
    
    <!-- Production Status Section -->
    
    
  


    </div>
    
   

    <!-- Action Buttons -->
    <div class="flex items-center justify-between p-2 bg-white rounded-xl mb-2 shadow-sm border border-gray-100">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center">
          <i class="fas fa-clipboard-list text-blue-500"></i>
        </div>
        <div class="flex flex-col">
          <span class="text-xs text-gray-500">Order ID:</span>
          <span id="displayOrderId" class="text-sm font-bold text-blue-600 font-mono">NEW</span>
        </div>
      </div>
      <div class="flex gap-2">
        <button id="cancelOrder" 
          class="px-3 py-2 rounded-lg bg-red-100 text-red-600 text-sm font-medium hover:bg-red-200 active:scale-95 transition-all duration-200 flex items-center gap-2">
          <i class="fas fa-times"></i>
          <span>Cancel</span>
        </button>
        <button id="saveOrder" 
          class="px-4 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-medium hover:shadow-lg hover:shadow-blue-500/30 active:scale-95 transition-all duration-200 flex items-center gap-2">
          <i class="fas fa-save"></i>
            <span>Add to Cart</span>
        </button>
      </div>
    </div>
  </div> <!-- End of orderFormContainer -->
    
</div>
  
<!-- Cart View Section -->
<div id="cartView" class="section-card cart-section hidden"> <!-- Initially hidden -->
  <div class="section-title text-blue-800">
    <i class="fas fa-shopping-cart"></i> Cart View (<span id="cartItemCount">0</span> items)
  </div>

  <div class="cart-items-container max-h-48 overflow-y-auto mb-3">
    <table class="min-w-full bg-white border border-gray-200 rounded-md text-xs">
      <thead>
        <tr class="bg-gray-100 text-gray-600 uppercase text-left">
          <th class="py-1.5 px-2 border-b border-gray-200">Item</th>
          <th class="py-1.5 px-2 border-b border-gray-200 text-right">Weight</th>
          <th class="py-1.5 px-2 border-b border-gray-200 text-right">Amount</th>
          <th class="py-1.5 px-2 border-b border-gray-200 text-right">Making Chrg</th>
          <th class="py-1.5 px-2 border-b border-gray-200 text-right">Stone Price</th>
          <th class="py-1.5 px-2 border-b border-gray-200 text-right">Total</th>
          <th class="py-1.5 px-2 border-b border-gray-200 text-center">Actions</th>
        </tr>
      </thead>
      <tbody id="cartItemsTableBody">
        <!-- Cart items will be added here by JavaScript -->
      </tbody>
    </table>
  </div>
  
  <!-- Price Breakdown -->
  <div class="price-breakdown bg-gray-100 p-2 rounded-md text-xs">
    <div class="flex justify-between mb-1">
      <span class="text-gray-600">Total Metal Amount:</span>
      <span id="totalMetalAmount" class="font-semibold text-gray-800">₹0.00</span>
    </div>
    <div class="flex justify-between mb-1">
      <span class="text-gray-600">Total Making Charges:</span>
      <span id="totalMakingCharges" class="font-semibold text-gray-800">₹0.00</span>
    </div>
     <div class="flex justify-between mb-1">
       <span class="text-gray-600">Total Stone Price:</span>
       <span id="totalStoneAmount" class="font-semibold text-gray-800">₹0.00</span>
     </div>
    <div class="flex justify-between border-t border-gray-200 pt-2 mt-2 font-bold">
      <span class="text-gray-800">Estimated Grand Total:</span>
      <span id="estimatedGrandTotal" class="text-blue-600">₹0.00</span>
    </div>
  </div>

  <!-- Payment Details -->
  <div class="payment-details bg-white p-2 rounded-md shadow-sm border border-gray-100 mt-4">
    <div class="section-title text-blue-800 mb-3">
      <i class="fas fa-money-bill-wave"></i> Payment Details
    </div>
    <div class="grid grid-cols-2 gap-3">
      <!-- Advance Amount -->
      <div class="field-col">
        <div class="field-label">Advance Amount (₹)</div>
        <div class="field-container">
          <input type="number"
            id="advanceAmount"
            class="input-field text-xs font-bold py-0.5 pl-7 h-8 bg-gray-50 border border-blue-200 rounded-md w-full"
            placeholder="Enter advance"
            value="0"
            step="0.01"
            min="0" />
          <i class="fas fa-rupee-sign field-icon text-blue-500"></i>
        </div>
      </div>

      <!-- Payment Method -->
      <div class="field-col">
        <div class="field-label">Payment Method</div>
        <div class="field-container">
          <select id="paymentMethod"
            class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-8 bg-gray-50 border border-blue-200 rounded-md w-full appearance-none">
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="upi">UPI</option>
            <option value="bank_transfer">Bank Transfer</option>
          </select>
          <i class="fas fa-credit-card field-icon text-blue-500"></i>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Cart Action Buttons -->
  <div class="flex items-center justify-between p-2 bg-white rounded-xl mt-4 shadow-sm border border-gray-100">
    <button id="backToForm"
            class="px-3 py-2 rounded-lg bg-gray-100 text-gray-600 text-sm font-medium hover:bg-gray-200 active:scale-95 transition-all duration-200 flex items-center gap-2"
            onclick="showOrderForm()">
      <i class="fas fa-arrow-left"></i>
      <span>Back to Form</span>
    </button>
     <button id="clearCartButton"
            class="px-3 py-2 rounded-lg bg-red-100 text-red-600 text-sm font-medium hover:bg-red-200 active:scale-95 transition-all duration-200 flex items-center gap-2"
            onclick="clearCart()">
       <i class="fas fa-trash"></i>
       <span>Clear Cart</span>
     </button>
    <button id="processOrder"
            onclick="console.log('Process Order button clicked');"
            class="px-4 py-2 rounded-lg bg-gradient-to-r from-green-600 to-teal-600 text-white text-sm font-medium hover:shadow-lg hover:shadow-green-500/30 active:scale-95 transition-all duration-200 flex items-center gap-2">
      <i class="fas fa-file-invoice-dollar"></i>
      <span>Process Order</span>
    </button>
  </div>
</div>

<!-- Order List Tab Content -->
<div id="order-list" class="tab-content">
  <div class="p-2">
    <!-- Search and Filter Section -->
    <div class="bg-white rounded-lg shadow-sm p-2 mb-1">
      <div class="grid grid-cols-3 gap-2">
        <!-- Search Box -->
        <div class="relative col-span-2">
          <input type="text" 
            id="orderSearch" 
            class="w-full h-9 pl-9 pr-4 rounded-lg border-2 border-gray-100 focus:border-blue-500 text-xs"
            placeholder="Search by order number, customer name, or item..." />
          <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
        
        <!-- Filter Dropdown -->
        <div class="relative">
          <select id="orderFilter" 
            class="w-full h-9 pl-9 pr-4 rounded-lg border-2 border-gray-100 focus:border-blue-500 text-xs appearance-none">
            <option value="all">All Orders</option>
            <option value="pending">Pending</option>
            <option value="in-progress">In Progress</option>
            <option value="ready">Ready</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <i class="fas fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
      </div>
    </div>

    <!-- Orders List -->
    <div class="space-y-2 overflow-y-auto max-h-[calc(100vh-250px)]" id="ordersList">
      <!-- Orders will be loaded here dynamically -->
    </div>
  </div>
</div>
<div id="orderDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md max-h-screen overflow-hidden shadow-2xl">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 text-white relative">
                <h3 class="text-lg font-semibold">Order Details</h3>
                <button onclick="closeOrderModal()" class="absolute right-4 top-4 text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Modal Content - Scrollable -->
            <div class="overflow-y-auto max-h-[calc(100vh-120px)]">
                <div id="orderDetailsContent" class="p-6">
                    <!-- Loading state -->
                    <div id="orderLoading" class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl text-blue-600 mb-2"></i>
                        <p class="text-gray-600">Loading order details...</p>
                    </div>
                    
                    <!-- Error state -->
                    <div id="orderError" class="text-center py-8 hidden">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-500 mb-2"></i>
                        <p class="text-red-600">Failed to load order details</p>
                    </div>
                    
                    <!-- Order details content will be populated here -->
                    <div id="orderDetails" class="hidden">
                        <!-- Content populated by JavaScript -->
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="border-t bg-gray-50 px-6 py-4 flex gap-2">
                <button onclick="printOrder()" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg font-medium text-sm hover:bg-blue-700 flex items-center justify-center gap-2">
                    <i class="fas fa-print"></i>
                    Print
                </button>
                <button onclick="showShareModal(currentOrderData)" class="flex-1 bg-green-600 text-white py-2 px-4 rounded-lg font-medium text-sm hover:bg-green-700 flex items-center justify-center gap-2">
                    <i class="fas fa-share-alt"></i>
                    Share
                </button>
                <button onclick="closeOrderModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 px-4 rounded-lg font-medium text-sm hover:bg-gray-300">
                    Close
                </button>
            </div>
        </div>
    </div>
<div id="customerModal" class="modal">
    <div class="modal-content p-0 overflow-hidden max-w-lg w-11/12">
        <!-- Gradient Header -->
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="fas fa-user-plus text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-white text-lg font-semibold">Add New Customer</h3>
                        <p class="text-white/80 text-sm">Enter customer details</p>
                    </div>
                </div>
                <button onclick="closeCustomerModal()" class="text-white/80 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Enhanced Tab Navigation -->
        <div class="flex border-b border-gray-200">
            <button class="modal-tab active flex-1 py-3 px-4 text-center relative transition-all" data-tab="basic-info">
                <div class="flex items-center justify-center gap-2">
                    <i class="fas fa-user-circle text-blue-500"></i>
                    <span class="font-medium text-sm">Basic Info</span>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-500 transform scale-x-0 transition-transform origin-left group-active:scale-x-100"></div>
            </button>
            <button class="modal-tab flex-1 py-3 px-4 text-center relative transition-all" data-tab="additional-info">
                <div class="flex items-center justify-center gap-2">
                    <i class="fas fa-info-circle text-purple-500"></i>
                    <span class="font-medium text-sm">Additional Info</span>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-purple-500 transform scale-x-0 transition-transform origin-left group-active:scale-x-100"></div>
            </button>
        </div>

        <!-- Basic Info Content -->
        <div id="basic-info-content" class="modal-tab-content active p-4 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <!-- First Name -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">First Name*</label>
                    <div class="relative">
                        <input type="text" id="newCustomerFirstName"
                            class="w-full h-10 pl-10 pr-4 rounded-lg border-2 border-blue-100 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 transition-all text-sm"
                            placeholder="Enter first name" required />
                        <i class="fas fa-user absolute left-3 top-1/2 -translate-y-1/2 text-blue-500"></i>
                    </div>
                </div>

                <!-- Last Name -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Last Name</label>
                    <div class="relative">
                        <input type="text" id="newCustomerLastName"
                            class="w-full h-10 pl-10 pr-4 rounded-lg border-2 border-purple-100 focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 transition-all text-sm"
                            placeholder="Enter last name" />
                        <i class="fas fa-user absolute left-3 top-1/2 -translate-y-1/2 text-purple-500"></i>
                    </div>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Phone Number*</label>
                    <div class="relative">
                        <input type="tel" id="newCustomerPhone"
                            class="w-full h-10 pl-10 pr-4 rounded-lg border-2 border-green-100 focus:border-green-500 focus:ring-4 focus:ring-green-500/20 transition-all text-sm"
                            placeholder="Enter phone number" required />
                        <i class="fas fa-phone absolute left-3 top-1/2 -translate-y-1/2 text-green-500"></i>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <input type="email" id="newCustomerEmail"
                            class="w-full h-10 pl-10 pr-4 rounded-lg border-2 border-amber-100 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/20 transition-all text-sm"
                            placeholder="Enter email address" />
                        <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-amber-500"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Info Content -->
        <div id="additional-info-content" class="modal-tab-content p-4 space-y-4 hidden">
            <!-- Address -->
            <div class="form-group">
                <label class="block text-xs font-medium text-gray-700 mb-1">Address</label>
                <div class="relative">
                    <textarea id="newCustomerAddress" rows="2"
                        class="w-full pl-10 pr-4 py-2 rounded-lg border-2 border-teal-100 focus:border-teal-500 focus:ring-4 focus:ring-teal-500/20 transition-all text-sm"
                        placeholder="Enter full address"></textarea>
                    <i class="fas fa-location-dot absolute left-3 top-3 text-teal-500"></i>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- City -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">City</label>
                    <div class="relative">
                        <input type="text" id="newCustomerCity"
                            class="w-full h-10 pl-10 pr-4 rounded-lg border-2 border-indigo-100 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 transition-all text-sm"
                            placeholder="Enter city" />
                        <i class="fas fa-city absolute left-3 top-1/2 -translate-y-1/2 text-indigo-500"></i>
                    </div>
                </div>

                <!-- State -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">State</label>
                    <div class="relative">
                        <input type="text" id="newCustomerState"
                            class="w-full h-10 pl-10 pr-4 rounded-lg border-2 border-pink-100 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/20 transition-all text-sm"
                            placeholder="Enter state" />
                        <i class="fas fa-map absolute left-3 top-1/2 -translate-y-1/2 text-pink-500"></i>
                    </div>
                </div>

                <!-- Postal Code -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Postal Code</label>
                    <div class="relative">
                        <input type="text" id="newCustomerPostalCode"
                            class="w-full h-10 pl-10 pr-4 rounded-lg border-2 border-cyan-100 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/20 transition-all text-sm"
                            placeholder="Enter postal code" />
                        <i class="fas fa-mailbox absolute left-3 top-1/2 -translate-y-1/2 text-cyan-500"></i>
                    </div>
                </div>

                <!-- GST Number -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">GST Number</label>
                    <div class="relative">
                        <input type="text" id="newCustomerGst"
                            class="w-full h-10 pl-10 pr-4 rounded-lg border-2 border-orange-100 focus:border-orange-500 focus:ring-4 focus:ring-orange-500/20 transition-all text-sm"
                            placeholder="Enter GST number" />
                        <i class="fas fa-receipt absolute left-3 top-1/2 -translate-y-1/2 text-orange-500"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer with gradient button -->
        <div class="bg-gray-50 p-4 flex justify-end gap-3 border-t">
            <button onclick="closeCustomerModal()"
                class="px-4 py-2 rounded-lg border-2 border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-100 transition-all">
                Cancel
            </button>
            <button onclick="saveCustomer()"
                class="px-6 py-2 rounded-lg bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 text-white text-sm font-medium hover:opacity-90 transition-all focus:ring-4 focus:ring-blue-500/30 flex items-center gap-2">
                <i class="fas fa-save"></i>
                Save Customer
            </button>
        </div>
    </div>
</div>q
<div id="successModal" class="modal">
    <div class="modal-content p-0 overflow-hidden max-w-sm w-11/12">
        <!-- Gradient Header -->
        <div class="bg-gradient-to-r from-green-600 to-teal-600 p-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="fas fa-check text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-white text-lg font-semibold">Order Placed!</h3>
                        <p id="successOrderNumber" class="text-white/80 text-sm">#ORD-XXXX</p>
                    </div>
                </div>
                <button onclick="closeSuccessModal()" class="text-white/80 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
    </div>
</div>

        <!-- Modal Body -->
        <div class="p-4 space-y-3 text-sm text-gray-700">
            <p><span class="font-semibold">Customer:</span> <span id="successCustomerName"></span></p>
            <p><span class="font-semibold">Grand Total:</span> <span id="successGrandTotal"></span></p>
            <p><span class="font-semibold">Advance Paid:</span> <span id="successAdvanceAmount"></span></p>
            <p><span class="font-semibold">Remaining Amount:</span> <span id="successRemainingAmount"></span></p>
            <p><span class="font-semibold">Payment Method:</span> <span id="successPaymentMethod"></span></p>
            <div class="mt-4 pt-3 border-t border-gray-200">
                <h4 class="font-semibold mb-2">Items Ordered:</h4>
                <ul id="successOrderItemsList" class="list-disc list-inside space-y-1">
                    <!-- Items will be listed here -->
                </ul>
            </div>
        </div>

        <!-- Footer with buttons -->
        <div class="bg-gray-50 p-4 flex justify-end gap-3 border-t">
            <button onclick="closeSuccessModal()"
                class="px-4 py-2 rounded-lg border-2 border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-100 transition-all">
                Close
            </button>
            <a id="whatsappShareButton" href="#" target="_blank"
                class="px-6 py-2 rounded-lg bg-green-500 text-white text-sm font-medium hover:bg-green-600 transition-all flex items-center gap-2">
                <i class="fab fa-whatsapp"></i>
                Share Receipt
            </a>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div id="shareModal" class="modal">
    <div class="modal-content p-0 overflow-hidden max-w-sm w-11/12">
        <!-- Gradient Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="fas fa-share-alt text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-white text-lg font-semibold">Share Order</h3>
                        <p class="text-white/80 text-sm">Share order details with customer</p>
                    </div>
                </div>
                <button onclick="closeShareModal()" class="text-white/80 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="p-4 space-y-4">
            <div class="flex flex-col gap-3">
                <a id="whatsappShareButton" href="#" target="_blank" class="flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg font-medium text-sm transition-colors">
                    <i class="fab fa-whatsapp text-lg"></i>
                    Share via WhatsApp
                </a>
                <button onclick="printOrder()" class="flex items-center justify-center gap-2 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg font-medium text-sm transition-colors">
                    <i class="fas fa-print"></i>
                    Print Order
                </button>
            </div>
        </div>
    </div>
</div>

<div id="karigarModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Karigar</h3>
            <span class="close" onclick="closeKarigarModal()">
                <i class="fas fa-times"></i>
            </span>
        </div>
        
        <!-- Modal Tabs -->
        <div class="modal-tabs">
            <button class="modal-tab active" data-tab="basic-info">
                <span>Basic Info</span>
                <div class="h-0.5 bg-blue-500 scale-x-100 transition-transform duration-200"></div>
            </button>
            <button class="modal-tab" data-tab="work-details">
                <span>Work Details</span>
                <div class="h-0.5 bg-blue-500 scale-x-0 transition-transform duration-200"></div>
            </button>
        </div>
        
        <!-- Basic Info Tab -->
        <div id="basic-info-content" class="modal-tab-content active">
            <div class="form-grid">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" id="newKarigarName" placeholder="Enter karigar name" required>
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" id="newKarigarPhone" placeholder="Enter phone number" required>
                </div>
                <div class="form-group">
                    <label>Alternate Phone</label>
                    <input type="tel" id="newKarigarAlternatePhone" placeholder="Enter alternate phone">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="newKarigarEmail" placeholder="Enter email address">
                </div>
                <div class="form-group">
                    <label>Address Line 1</label>
                    <input type="text" id="newKarigarAddressLine1" placeholder="Enter address line 1">
                </div>
                <div class="form-group">
                    <label>Address Line 2</label>
                    <input type="text" id="newKarigarAddressLine2" placeholder="Enter address line 2">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" id="newKarigarCity" placeholder="Enter city">
                </div>
                <div class="form-group">
                    <label>State</label>
                    <input type="text" id="newKarigarState" placeholder="Enter state">
                </div>
                <div class="form-group">
                    <label>Postal Code</label>
                    <input type="text" id="newKarigarPostalCode" placeholder="Enter postal code">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" id="newKarigarCountry" placeholder="Enter country" value="India">
                </div>
            </div>
        </div>
        
        <!-- Work Details Tab -->
        <div id="work-details-content" class="modal-tab-content">
            <div class="form-grid">
                <div class="form-group">
                    <label>Default Making Charge</label>
                    <input type="number" id="newKarigarMakingCharge" placeholder="Enter making charge" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Charge Type</label>
                    <select id="newKarigarChargeType">
                        <option value="per_gram">Per Gram</option>
                        <option value="per_piece">Per Piece</option>
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>GST Number</label>
                    <input type="text" id="newKarigarGstNumber" placeholder="Enter GST number">
                </div>
                <div class="form-group">
                    <label>PAN Number</label>
                    <input type="text" id="newKarigarPanNumber" placeholder="Enter PAN number">
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeKarigarModal()">Cancel</button>
            <button type="button" class="btn-primary" onclick="saveKarigar()">Save Karigar</button>
        </div>
    </div>
</div>
 <!-- Bottom Navigation -->
 <nav class="bottom-nav">
   <!-- Home -->
   <a href="index.php" class="nav-item">
     <i class="nav-icon fas fa-home"></i>
     <span class="nav-text">Home</span>
   </a>
<a href="add.php" class="nav-item">
     <i class="nav-icon fa-solid fa-gem"></i>
     <span class="nav-text">Add</span>
   </a>
   <!-- Sell (Current Page) -->
   <a href="customer_order.php" class="nav-item active">
     <i class="nav-icon fas fa-tags"></i>
     <span class="nav-text">Sell</span>
   </a>

   <!-- Cart -->
   <div class="nav-item relative" onclick="showCartView()">
     <i class="nav-icon fas fa-shopping-cart"></i>
     <span class="cart-badge" id="bottomNavCartBadge">0</span>
   </div>

   <!-- Sales List -->
   <a href="sale-list.php" class="nav-item">
     <i class="nav-icon fas fa-clipboard-list"></i>
     <span class="nav-text">Sales</span>
   </a>

   <!-- Reports -->
   <a href="reports.php" class="nav-item">
     <i class="nav-icon fas fa-chart-pie"></i>
     <span class="nav-text">Reports</span>
   </a>
 </nav>
<!-- Order Edit Modal -->
<div id="orderEditModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-6 py-4 text-white relative">
            <h3 class="text-lg font-semibold">Edit Order</h3>
            <button onclick="closeEditModal()" class="absolute right-4 top-4 text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Content - Scrollable -->
        <div class="overflow-y-auto max-h-[calc(90vh-120px)]">
            <div id="orderEditContent" class="p-6 space-y-6">
                <!-- Order Status Section -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-4">
                    <h4 class="text-sm font-semibold text-gray-700">Order Status</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="field-container">
                            <label class="text-xs text-gray-600">Status</label>
                            <select id="editOrderStatus" class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm">
                                <option value="pending">Pending</option>
                                <option value="in progress">In Progress</option>
                                <option value="ready">Ready</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="field-container">
                            <label class="text-xs text-gray-600">Priority</label>
                            <select id="editOrderPriority" class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Items List -->
                <div class="space-y-4">
                    <h4 class="text-sm font-semibold text-gray-700">Order Items</h4>
                    <div id="editOrderItems" class="space-y-4">
                        <!-- Items will be populated here -->
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-4">
                    <h4 class="text-sm font-semibold text-gray-700">Payment Details</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="field-container">
                            <label class="text-xs text-gray-600">Total Amount</label>
                            <input type="number" id="editTotalAmount" class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm" readonly />
                        </div>
                        <div class="field-container">
                            <label class="text-xs text-gray-600">Advance Paid</label>
                            <input type="number" id="editAdvanceAmount" class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="border-t bg-gray-50 px-6 py-4 flex justify-end gap-3">
            <button onclick="closeEditModal()" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg text-sm font-medium hover:bg-gray-200">
                Cancel
            </button>
            <button onclick="saveOrderChanges()" class="px-6 py-2 text-white bg-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-700">
                Save Changes
            </button>
        </div>
    </div>
</div>
 <!-- Bottom Sheet -->

<script>

// Global variables
let cartItems = []; // Array to hold items in the cart
let referenceImages = [];
let stream = null;
let selectedCustomerId = null; // Variable to store the selected customer ID
let editingItemIndex = null; // New variable to store the index of the item being edited
 let currentOrderData = null;



// --- ORDER SUBMISSION (ADD/EDIT) ---
function submitOrder(isEdit = false) {
    // ...collect other form data...
    if (!selectedKarigarId) {
        showToast('Please select a karigar.', 'error');
        return;
    }
    const payload = {
        // ...other order data...
        karigarId: selectedKarigarId,
        // ...
    };
    // ...send payload to server...
}

// --- DEBOUNCE FUNCTION ---
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// --- REMOVE OR COMMENT OUT showKarigarModal BUTTON ---
// If you don't want to allow adding new karigars, remove the button or its onclick in the HTML.
// ... existing code ...

// Function to show toast notification
function showToast(message, type = 'success') {
  const toast = document.getElementById('toast');
  const toastMessage = document.getElementById('toastMessage');

  if (!toast || !toastMessage) {
      console.error("Toast elements not found.");
      return;
  }

  toast.className = 'toast show';
  if (type === 'error') {
    toast.style.backgroundColor = '#ef4444';
    const icon = toast.querySelector('.fas');
    if(icon) icon.className = 'fas fa-times-circle'; // Change icon to error
  } else if (type === 'info') {
     toast.style.backgroundColor = '#3b82f6'; // Use a different color for info
     const icon = toast.querySelector('.fas');
     if(icon) icon.className = 'fas fa-info-circle'; // Change icon to info
  }
  else {
    toast.style.backgroundColor = '#10b981';
    const icon = toast.querySelector('.fas');
    if(icon) icon.className = 'fas fa-check-circle'; // Change icon to success
  }

  toastMessage.textContent = message;

  setTimeout(() => {
    toast.classList.remove('show');
  }, 3000);
}

// Function to save or update item in cart
function saveOrUpdateItem() {
    console.log('saveOrUpdateItem function called'); // Debug log

    // Get form data
    const itemName = document.getElementById('itemName').value.trim();
    const metalType = document.getElementById('metalType').value;
    const purity = parseFloat(document.getElementById('purity').value) || 0;
    const netWeight = parseFloat(document.getElementById('netWeight').value) || 0;
    const metalAmount = parseFloat(document.getElementById('metalAmount').value) || 0; // Get calculated metal amount
    const makingType = document.getElementById('makingType').value;
    const makingChargeInput = parseFloat(document.getElementById('makingCharge').value) || 0; // Store input value
    const stoneType = document.getElementById('stoneType').value;
    const stoneWeight = parseFloat(document.getElementById('stoneWeight').value) || 0; // Stone weight from form
    const stonePrice = parseFloat(document.getElementById('stonePrice').value) || 0; // Get stone price
    const designReference = document.getElementById('designReference').value.trim();
    const expectedDelivery = document.getElementById('expectedDelivery').value;
    const designCustomization = document.getElementById('designCustomization').value.trim();
    const priority = document.getElementById('priorityLevel').value;
    const productType = document.getElementById('productType').value;
    const sizeDetails = document.getElementById('sizeDetails').value.trim(); // Get size details
    const stoneQuality = document.getElementById('stoneQuality').value.trim(); // Get stone quality
    const stoneSize = document.getElementById('stoneSize').value.trim(); // Get stone size
    const stoneQuantity = parseFloat(document.getElementById('stoneQuantity').value) || 0; // Get stone quantity
    const stoneUnit = document.getElementById('stoneUnit').value; // Get stone unit
    const stoneDetails = document.getElementById('stoneDetails').value.trim(); // Get stone details


    console.log('Collected form data for save/update:', {itemName, netWeight, stonePrice, selectedCustomerId, editingItemIndex}); // Debug log

    // Basic validation - Ensure required fields for an order item are present
    // Check if a customer is selected (using the stored selectedCustomerId)
    if (!selectedCustomerId || !itemName || netWeight <= 0) {
        console.log('Validation failed: Customer not selected, item name missing, or net weight invalid.'); // Debug log
        showToast('Please select a customer and enter item name and valid net weight (greater than 0).', 'error');
        return;
    }
    console.log('Validation passed'); // Debug log

    // Calculate Making Charges based on type using the dedicated function
    const makingCharges = calculateMakingCharges(); // Use the function to get the calculated value

    // Calculate Total Estimate for the item (Metal Amount + Making Charges + Stone Price)
    const totalEstimate = metalAmount + makingCharges + stonePrice;
    console.log(`Calculated Total Estimate for item: Metal=${metalAmount}, Making=${makingCharges}, Stone=${stonePrice}, Total=${totalEstimate.toFixed(2)}`);

    // Create or update item object with all relevant details
    const item = {
        id: editingItemIndex !== null ? cartItems[editingItemIndex].id : Date.now() + Math.random(), // Keep existing ID if editing, generate new one if adding
        customerId: selectedCustomerId, // Store the selected customer ID
        customerName: document.getElementById('customerName').value.trim(), // Store name for display
        itemName: itemName,
        priority: priority,
        productType: productType,
        designReference: designReference,
        expectedDelivery: expectedDelivery,
        metalType: metalType,
        purity: purity,
        netWeight: netWeight,
        metalAmount: metalAmount,
        stoneType: stoneType,
        stoneQuality: stoneQuality, // Added stone quality
        stoneSize: stoneSize, // Added stone size
        stoneQuantity: stoneQuantity, // Added stone quantity
        stoneWeight: stoneWeight,
        stoneUnit: stoneUnit, // Added stone unit
        stonePrice: stonePrice, // Store stone price
        stoneDetails: stoneDetails, // Added stone details
        makingType: makingType,
        makingChargeInput: makingChargeInput, // Store input value
        makingCharges: makingCharges, // Store the calculated making charges
        sizeDetails: sizeDetails, // Added size details
        designCustomization: designCustomization,
        referenceImages: [...referenceImages], // Clone the array of image data URLs
        totalEstimate: parseFloat(totalEstimate.toFixed(2))
    };
     console.log(editingItemIndex !== null ? 'Updating item in cart:' : 'Adding item to cart:', item); // Debug log: Log the item object

    if (editingItemIndex !== null) {
        // Update existing item
        cartItems[editingItemIndex] = item;
        showToast('Item updated successfully');
    } else {
        // Add new item
        cartItems.push(item);
    showToast('Item added to cart successfully');
    }

    console.log('Cart items after save/update:', [...cartItems]); // Log current cart state

    // Update cart display and summary
    renderCartItems();
    updateCartSummary();

    // Clear form and reset edit state
    clearForm();
    resetEditMode();

    // Switch to cart view
    showCartView();
}

// Function to clear the order form fields
function clearForm() {
     console.log('clearForm function called'); // Debug log
    document.getElementById('itemName').value = '';
    document.getElementById('priorityLevel').value = 'Normal';
    document.getElementById('productType').value = '';
    document.getElementById('designReference').value = '';
    document.getElementById('expectedDelivery').value = '';
    document.getElementById('metalType').value = 'Gold';
    document.getElementById('purity').value = '92.0'; // Reset to a default or empty
    document.getElementById('grossWeight').value = '0';
    document.getElementById('lessWeight').value = '0';
    document.getElementById('netWeight').value = '0.000'; // Net weight should be calculated or set based on inputs
    // Keep the gold rate as is, it's fetched once
    // document.getElementById('goldRate24k').value = '0';
    document.getElementById('metalAmount').value = '0.00';

    document.getElementById('stoneType').value = 'None';
    document.getElementById('stoneQuality').value = '';
    document.getElementById('stoneSize').value = '';
    document.getElementById('stoneQuantity').value = '0';
    document.getElementById('stoneWeight').value = '0';
    document.getElementById('stoneUnit').value = 'ct';
    document.getElementById('stonePrice').value = '0';
    document.getElementById('stoneDetails').value = '';

    document.getElementById('makingType').value = 'per_gram';
    document.getElementById('makingCharge').value = '0';
    document.getElementById('sizeDetails').value = '';
    document.getElementById('designCustomization').value = '';
    
    // Clear image previews and the referenceImages array
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    if (imagePreviewContainer) {
    imagePreviewContainer.innerHTML = '';
    }
    referenceImages = [];

    // Reset calculated fields to default/zero values
    document.getElementById('purityRate').value = '0.00';
    // The display order ID should remain 'NEW' or the current edit ID
    // document.getElementById('displayOrderId').textContent = 'NEW';

    // Recalculate defaults
    calculateNetWeight(); // This will trigger other calculations
}

// Function to implement edit functionality
function editItemInCart(index) {
    console.log('editItemInCart called for index:', index); // Debug log

    if (index >= 0 && index < cartItems.length) {
        const itemToEdit = cartItems[index];
        console.log('Item to edit:', itemToEdit); // Debug log

        // Populate the form with item details
        document.getElementById('itemName').value = itemToEdit.itemName;
        document.getElementById('priorityLevel').value = itemToEdit.priority;
        document.getElementById('productType').value = itemToEdit.productType;
        document.getElementById('designReference').value = itemToEdit.designReference;
        document.getElementById('expectedDelivery').value = itemToEdit.expectedDelivery; // Dates need proper handling if format differs
        document.getElementById('metalType').value = itemToEdit.metalType;
        document.getElementById('purity').value = itemToEdit.purity;
        // When editing, we populate net weight directly, assuming gross/less aren't stored per item
        document.getElementById('grossWeight').value = itemToEdit.netWeight; // Populate Gross with Net for simpler recalculation on form edit
        document.getElementById('lessWeight').value = '0'; // Assume less weight is 0 when editing an existing item
        document.getElementById('netWeight').value = itemToEdit.netWeight; // Set net weight directly
        // Gold rate should likely remain the globally fetched one
        // document.getElementById('goldRate24k').value = document.getElementById('goldRate24k').value || '0'; // Keep current gold rate or fetch? Keeping current for now.
        document.getElementById('metalAmount').value = itemToEdit.metalAmount; // Set calculated metal amount
        document.getElementById('stoneType').value = itemToEdit.stoneType;
        document.getElementById('stoneQuality').value = itemToEdit.stoneQuality; // Populate stone quality
        document.getElementById('stoneSize').value = itemToEdit.stoneSize; // Populate stone size
        document.getElementById('stoneQuantity').value = itemToEdit.stoneQuantity; // Populate stone quantity
        document.getElementById('stoneWeight').value = itemToEdit.stoneWeight; // Populate stone weight
        document.getElementById('stoneUnit').value = itemToEdit.stoneUnit; // Populate stone unit
        document.getElementById('stonePrice').value = itemToEdit.stonePrice; // Populate stone price
        document.getElementById('stoneDetails').value = itemToEdit.stoneDetails; // Populate stone details
        document.getElementById('makingType').value = itemToEdit.makingType;
        document.getElementById('makingCharge').value = itemToEdit.makingChargeInput; // Populate making charge input value
        document.getElementById('sizeDetails').value = itemToEdit.sizeDetails; // Populate size details
        document.getElementById('designCustomization').value = itemToEdit.designCustomization;

        // Handle images - clear current previews and add saved images
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        if (imagePreviewContainer) {
            imagePreviewContainer.innerHTML = '';
        }
        referenceImages = [...itemToEdit.referenceImages]; // Load saved images
         referenceImages.forEach(imageData => {
             addImageToPreview(imageData); // Add images to preview
         });


        // Change button text and store index
        const saveOrderButton = document.getElementById('saveOrder');
        if (saveOrderButton) {
            saveOrderButton.textContent = 'Update Item';
            saveOrderButton.classList.remove('from-blue-600', 'to-indigo-600');
            saveOrderButton.classList.add('from-green-600', 'to-teal-600'); // Optional: change color
             const icon = saveOrderButton.querySelector('.fas');
             if(icon) {
                 icon.className = 'fas fa-edit'; // Change icon
             }
        }
         // Update Cancel button text
         const cancelOrderButton = document.getElementById('cancelOrder');
         if (cancelOrderButton) {
             cancelOrderButton.querySelector('span').textContent = 'Cancel Edit';
         }

        editingItemIndex = index; // Store the index

        // Show edit mode indicator
        const editModeIndicator = document.getElementById('editModeIndicator');
        if(editModeIndicator) {
            editModeIndicator.classList.add('show');
        }


        // Switch to form view
        showOrderForm();

        // Recalculate totals after populating form to ensure display is correct
        // calculateNetWeight(); // This will chain to calculateMetalAmount and calculateTotalEstimate - already set netWeight directly
         calculateMetalAmount(); // Trigger recalculation based on populated metal details
    } else {
        console.error('Invalid index for editItemInCart:', index);
        showToast('Error: Could not find item to edit.', 'error');
    }
}

// Function to reset the form and exit edit mode
function resetEditMode() {
    console.log('resetEditMode called'); // Debug log
    editingItemIndex = null; // Clear the editing index

    // Reset button text and appearance
    const saveOrderButton = document.getElementById('saveOrder');
    if (saveOrderButton) {
        saveOrderButton.textContent = 'Add to Cart';
        saveOrderButton.classList.remove('from-green-600', 'to-teal-600');
        saveOrderButton.classList.add('from-blue-600', 'to-indigo-600'); // Revert color
         const icon = saveOrderButton.querySelector('.fas');
         if(icon) {
             icon.className = 'fas fa-save'; // Revert icon
         }
    }
     // Reset Cancel button text
     const cancelOrderButton = document.getElementById('cancelOrder');
     if (cancelOrderButton) {
         cancelOrderButton.querySelector('span').textContent = 'Cancel';
     }

    // Hide edit mode indicator
    const editModeIndicator = document.getElementById('editModeIndicator');
    if(editModeIndicator) {
        editModeIndicator.classList.remove('show');
    }
    console.log('Edit mode reset.');
}

// Function for the Cancel button
function cancelOrder() {
    console.log('cancelOrder function called'); // Debug log
    if (editingItemIndex !== null) {
        // If editing, confirm cancellation
        if (confirm('Are you sure you want to cancel editing this item? Your changes will be lost.')) {
            clearForm(); // Clear the form
            resetEditMode(); // Exit edit mode
            showToast('Editing cancelled', 'info');
            // If cart is not empty, switch back to cart view
            if (cartItems.length > 0) {
                showCartView();
            } else {
                 // If cart is empty, stay on the form view
                 showOrderForm(); // Ensure we are on the form view
            }
        }
    } else {
        // If not editing, just clear the form (with confirmation)
         if (confirm('Are you sure you want to clear the current form?')) {
             clearForm();
             showToast('Form cleared', 'info');
             showOrderForm(); // Ensure we are on the form view
         }
    }
}


// Function to render items in the cart table
function renderCartItems() {
    console.log('renderCartItems function called'); // Debug log
    const cartItemsTableBody = document.getElementById('cartItemsTableBody');
    const cartItemCountSpan = document.getElementById('cartItemCount');
    const bottomNavCartBadgeSpan = document.getElementById('bottomNavCartBadge');

    if (!cartItemsTableBody || !cartItemCountSpan || !bottomNavCartBadgeSpan) {
        console.error("Cart display elements not found.");
        return;
    }

    console.log('Cart items array state before rendering:', [...cartItems]); // Log array state

    cartItemsTableBody.innerHTML = ''; // Clear current items

    if (cartItems.length === 0) {
        cartItemsTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-gray-500">No items in cart yet. Add items using the form above.</td> <!-- Updated colspan -->
            </tr>
        `;
        console.log('Cart is empty, displaying placeholder row.');
        // Cart view visibility is now managed by showCartView/showOrderForm
    } else {
        cartItems.forEach((item, index) => {
            const row = document.createElement('tr');
            row.className = 'border-b border-gray-100 last:border-b-0 hover:bg-gray-50';
            row.innerHTML = `
                <td class="py-1.5 px-2 text-gray-700">
                    <div class="font-medium">${item.itemName}</div>
                    ${item.designReference ? `<div class="text-gray-500 text-xs">Ref: ${item.designReference}</div>` : ''}
                </td>
                <td class="py-1.5 px-2 text-right text-gray-700">${item.netWeight.toFixed(3)}g</td>
                <td class="py-1.5 px-2 text-right text-gray-700">₹${item.metalAmount.toFixed(2)}</td>
                <td class="py-1.5 px-2 text-right text-gray-700">₹${item.makingCharges.toFixed(2)}</td>
                <td class="py-1.5 px-2 text-right text-gray-700">₹${item.stonePrice.toFixed(2)}</td> <!-- Display stone price -->
                <td class="py-1.5 px-2 text-right font-semibold text-blue-600">₹${item.totalEstimate.toFixed(2)}</td>
                <td class="py-1.5 px-2 text-center">
                     <button class="text-blue-600 hover:text-blue-800 text-sm focus:outline-none mr-2" onclick="editItemInCart(${index})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="text-red-600 hover:text-red-800 text-sm focus:outline-none" onclick="removeItemFromCart(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            cartItemsTableBody.appendChild(row);
        });
        console.log(`${cartItems.length} items rendered in the cart.`);
        // Cart view visibility is now managed by showCartView/showOrderForm
    }

    // Update cart item count
    cartItemCountSpan.textContent = cartItems.length;
    bottomNavCartBadgeSpan.textContent = cartItems.length; // Update bottom nav badge
    console.log(`Cart count updated to: ${cartItems.length}`);
}

// Function to update cart summary totals
function updateCartSummary() {
    console.log('updateCartSummary function called'); // Debug log
    console.log('Current cartItems for summary calculation:', [...cartItems]); // Debug log

    let totalMetal = 0;
    let totalMaking = 0;
    let totalStone = 0; // Added total stone
    let grandTotal = 0;

    cartItems.forEach(item => {
        totalMetal += item.metalAmount;
        totalMaking += item.makingCharges;
        totalStone += item.stonePrice; // Added stone price to total
        grandTotal += item.totalEstimate; // This already includes stone price from addItemToCart
    });

    const totalMetalAmountSpan = document.getElementById('totalMetalAmount');
    const totalMakingChargesSpan = document.getElementById('totalMakingCharges');
    const totalStoneAmountSpan = document.getElementById('totalStoneAmount'); // Added stone total span
    const estimatedGrandTotalSpan = document.getElementById('estimatedGrandTotal');

     if (!totalMetalAmountSpan || !totalMakingChargesSpan || !totalStoneAmountSpan || !estimatedGrandTotalSpan) {
        console.error("Cart summary elements not found.");
        return;
    }


    totalMetalAmountSpan.textContent = `₹${totalMetal.toFixed(2)}`;
    totalMakingChargesSpan.textContent = `₹${totalMaking.toFixed(2)}`;
    totalStoneAmountSpan.textContent = `₹${totalStone.toFixed(2)}`; // Update stone total display
    estimatedGrandTotalSpan.textContent = `₹${grandTotal.toFixed(2)}`;

    console.log(`Summary updated: Metal=${totalMetal.toFixed(2)}, Making=${totalMaking.toFixed(2)}, Stone=${totalStone.toFixed(2)}, GrandTotal=${grandTotal.toFixed(2)}`);

    // Show/hide cart view is now handled by showCartView/showOrderForm based on user action
}

// Function to remove item from cart
function removeItemFromCart(index) {
    console.log('removeItemFromCart called for index:', index); // Debug log
    if (index >= 0 && index < cartItems.length) {
      if (confirm('Are you sure you want to remove this item from the cart?')) {
        const removedItem = cartItems.splice(index, 1); // Remove item
        console.log('Item removed:', removedItem); // Log removed item
        console.log('Cart items after removal:', [...cartItems]); // Log current cart state

        renderCartItems();
        updateCartSummary();
        showToast('Item removed from cart', 'error');

        // If cart becomes empty, automatically switch back to the form
        if (cartItems.length === 0) {
            showOrderForm();
        }
      }
    } else {
         console.error('Invalid index for removeItemFromCart:', index);
         showToast('Error: Could not find item to remove.', 'error');
    }
}

// Function to clear the entire cart
function clearCart() {
     console.log('clearCart function called'); // Debug log
    if (cartItems.length > 0) {
         if (confirm('Are you sure you want to clear the entire cart?')) {
            cartItems = []; // Empty the array
             console.log('Cart items after clearing:', [...cartItems]); // Log current cart state
            renderCartItems(); // Update the display (will show "No items")
            updateCartSummary(); // Reset totals to zero
            showOrderForm(); // Switch back to the order form
            showToast('Cart cleared', 'error');
         }
    } else {
        showToast('Cart is already empty', 'info');
    }
}

// Modal elements and functions
const customerModal = document.getElementById('customerModal');
const newCustomerFirstNameInput = document.getElementById('newCustomerFirstName');
const newCustomerLastNameInput = document.getElementById('newCustomerLastName');
const newCustomerPhoneInput = document.getElementById('newCustomerPhone');
const newCustomerEmailInput = document.getElementById('newCustomerEmail');
const newCustomerAddressInput = document.getElementById('newCustomerAddress');
const newCustomerCityInput = document.getElementById('newCustomerCity');
const newCustomerStateInput = document.getElementById('newCustomerState');
const newCustomerPostalCodeInput = document.getElementById('newCustomerPostalCode');
const newCustomerGstInput = document.getElementById('newCustomerGst');
const modalTabs = document.querySelectorAll('.modal-tab');
const modalTabContents = document.querySelectorAll('.modal-tab-content');
let activeTab = 'basic-info';

function setupModalTabs() {
  modalTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const tabId = tab.getAttribute('data-tab');
      modalTabs.forEach(t => t.classList.remove('active'));
      modalTabContents.forEach(c => c.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(`${tabId}-content`).classList.add('active');
      activeTab = tabId;
    });
  });
}

function showCustomerModal() {
  if (!customerModal) { console.error("Customer modal element not found."); return; }
  customerModal.style.display = 'flex';
  setupModalTabs();
  
  // Reset form fields
  if (newCustomerFirstNameInput) newCustomerFirstNameInput.value = '';
  if (newCustomerLastNameInput) newCustomerLastNameInput.value = '';
  if (newCustomerPhoneInput) newCustomerPhoneInput.value = '';
  if (newCustomerEmailInput) newCustomerEmailInput.value = '';
  if (newCustomerAddressInput) newCustomerAddressInput.value = '';
  if (newCustomerCityInput) newCustomerCityInput.value = '';
  if (newCustomerStateInput) newCustomerStateInput.value = '';
  if (newCustomerPostalCodeInput) newCustomerPostalCodeInput.value = '';
  if (newCustomerGstInput) newCustomerGstInput.value = '';
  
  // Reset tabs
  modalTabs.forEach(t => {
    t.classList.remove('active');
     const indicator = t.querySelector('.h-0\\.5'); // Use double escape for dot
        if (indicator) {
            indicator.classList.remove('scale-x-100');
            indicator.classList.add('scale-x-0');
        }
  });
  modalTabContents.forEach(c => c.classList.remove('active'));
  
  // Set first tab as active
  const firstTabButton = document.querySelector('.modal-tab[data-tab="basic-info"]');
  if(firstTabButton) {
      firstTabButton.classList.add('active');
       const indicator = firstTabButton.querySelector('.h-0\\.5'); // Use double escape for dot
        if (indicator) {
            indicator.classList.remove('scale-x-0');
            indicator.classList.add('scale-x-100');
        }
  }

  const firstTabContent = document.getElementById('basic-info-content');
  if(firstTabContent) {
    firstTabContent.classList.add('active');
  }
  activeTab = 'basic-info';
}

function closeCustomerModal() {
  if (!customerModal) { console.error("Customer modal element not found."); return; }
  customerModal.style.display = 'none';
}

function saveCustomer() {
  const firstName = newCustomerFirstNameInput ? newCustomerFirstNameInput.value.trim() : '';
  const lastName = newCustomerLastNameInput ? newCustomerLastNameInput.value.trim() : '';
  const phone = newCustomerPhoneInput ? newCustomerPhoneInput.value.trim() : '';
  const email = newCustomerEmailInput ? newCustomerEmailInput.value.trim() : '';
  const address = newCustomerAddressInput ? newCustomerAddressInput.value.trim() : '';
  const city = newCustomerCityInput ? newCustomerCityInput.value.trim() : '';
  const state = newCustomerStateInput ? newCustomerStateInput.value.trim() : '';
  const postalCode = newCustomerPostalCodeInput ? newCustomerPostalCodeInput.value.trim() : '';
  const gst = newCustomerGstInput ? newCustomerGstInput.value.trim() : '';

  
  if (!firstName || !phone) {
    showToast('First name and phone number are required', 'error');
    // Switch back to basic info tab if validation fails
    const basicInfoTabButton = document.querySelector('.modal-tab[data-tab="basic-info"]');
    if(basicInfoTabButton && !basicInfoTabButton.classList.contains('active')) {
         modalTabs.forEach(t => t.classList.remove('active'));
         modalTabContents.forEach(c => c.classList.remove('active'));
         basicInfoTabButton.classList.add('active');
         const indicator = basicInfoTabButton.querySelector('.h-0\\.5'); // Use double escape for dot
            if (indicator) {
                indicator.classList.remove('scale-x-0');
                indicator.classList.add('scale-x-100');
            }
         document.getElementById('basic-info-content').classList.add('active');
         activeTab = 'basic-info';
    }
    return;
  }
  
  const customerData = new FormData();
  customerData.append('firstName', firstName);
  customerData.append('lastName', lastName);
  customerData.append('phone', phone);
  customerData.append('email', email);
  customerData.append('address', address);
  customerData.append('city', city);
  customerData.append('state', state);
  customerData.append('postalCode', postalCode);
  customerData.append('country', 'India'); // Assuming India as default
  customerData.append('gst', gst);
  
  // Debug: Log customer data being sent
  console.log('Saving customer data:', {
    firstName, lastName, phone, email, address, city, state, postalCode, gst
  });
  
  fetch('order.php?action=addCustomer', {
    method: 'POST',
    body: customerData
  })
  .then(response => {
    console.log('Add customer response status:', response.status);
      if (!response.ok) {
        // Log response text for debugging non-JSON errors
        return response.text().then(text => { throw new Error(`HTTP error! status: ${response.status}, body: ${text}`); });
      }
    return response.json();
  })
  .then(data => {
    console.log('Add customer response data:', data); // Debug log
    if (data.success) {
      showToast('Customer added successfully');
      closeCustomerModal();
      
      // Select the newly added customer
      if (data.customer) {
      selectCustomer(data.customer); // Use the customer data returned from the server
    } else {
         console.error("Received success=true but no customer data in response.");
         showToast('Customer added, but failed to select automatically.', 'info');
      }
    } else {
      showToast(data.message || 'Failed to add customer', 'error');
    }
  })
  .catch(error => {
    console.error('Error adding customer:', error); // Log fetch error
    showToast('Failed to add customer', 'error');
  });
}

// Add customer search functionality
const customerNameInput = document.getElementById('customerName');
const customerDropdown = document.getElementById('customerDropdown');
const selectionDetailsDiv = document.getElementById('selectionDetails'); // Get the div to display details

function setupCustomerSearch() {
  if (!customerNameInput || !customerDropdown || !selectionDetailsDiv) {
      console.error("Customer search elements not found during setup.");
      return;
  }

  customerNameInput.addEventListener('input', debounce((e) => {
    const searchTerm = e.target.value.trim();
    if (searchTerm.length < 2) {
      customerDropdown.style.display = 'none';
      // Clear selected customer if search term is too short or empty
      selectedCustomerId = null;
      selectionDetailsDiv.classList.add('hidden');
      selectionDetailsDiv.innerHTML = ''; // Clear details display
        console.log('Search term too short, clearing selected customer.');
      return;
    }

    console.log('Searching for customers with term:', searchTerm); // Debug log

    fetch(`order.php?action=searchCustomers&term=${encodeURIComponent(searchTerm)}`)
      .then(response => {
          console.log('Customer search response status:', response.status);
          if (!response.ok) {
               return response.text().then(text => { throw new Error(`HTTP error! status: ${response.status}, body: ${text}`); });
          }
          return response.json();
      })
      .then(customers => {
          console.log('Customer search results:', customers); // Debug log
        customerDropdown.innerHTML = ''; // Clear previous results
        if (customers.length > 0) {
          customers.forEach(customer => {
              const customerItem = document.createElement('div');
              customerItem.className = 'customer-item p-2 hover:bg-gray-50 cursor-pointer';
              // Use an anonymous function to pass the customer object correctly
              customerItem.onclick = () => selectCustomer(customer);
              customerItem.innerHTML = `
              <div class="customer-name">${customer.FirstName} ${customer.LastName || ''}</div>
              <div class="customer-info">${customer.PhoneNumber}</div>
              `;
              customerDropdown.appendChild(customerItem);
          });
          customerDropdown.style.display = 'block';
            console.log(`${customers.length} customer results displayed.`);
        } else {
          customerDropdown.style.display = 'none';
            console.log('No customer results found.');
        }
      })
      .catch(error => {
        console.error('Error searching customers:', error); // Log fetch error
        showToast('Error searching customers', 'error');
      });
  }), 300); // Debounce delay

    // Hide dropdown when input loses focus, with a slight delay to allow click on dropdown item
    customerNameInput.addEventListener('blur', () => {
        setTimeout(() => {
            if (customerDropdown) customerDropdown.style.display = 'none';
        }, 200); // Small delay
    });

    // Prevent mousedown on dropdown from hiding it immediately on blur
    if (customerDropdown) {
        customerDropdown.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });
    }
}

function selectCustomer(customer) {
  if (!customerNameInput || !selectionDetailsDiv || !customerDropdown) {
      console.error("Customer select elements not found during selection.");
      return;
  }
  customerNameInput.value = `${customer.FirstName} ${customer.LastName || ''}`.trim(); // Handle missing last name
  customerDropdown.style.display = 'none';
  selectedCustomerId = customer.id; // Store the selected customer ID

  console.log('Customer selected:', customer); // Debug log
  
  // Show customer details
  selectionDetailsDiv.innerHTML = `
    <div class="detail-badge">
      <i class="fas fa-phone"></i>
      <span>${customer.PhoneNumber}</span>
    </div>
    ${customer.Email ? `
    <div class="detail-badge">
      <i class="fas fa-envelope"></i>
      <span>${customer.Email}</span>
    </div>
    ` : ''}
    ${(customer.due_amount && parseFloat(customer.due_amount) > 0) ? ` // Check if due_amount exists and is > 0
    <div class="due-badge">
      <i class="fas fa-exclamation-circle"></i>
      <span>Due: ₹${parseFloat(customer.due_amount).toFixed(2)}</span> // Format due amount
    </div>
    ` : ''}
  `;
  selectionDetailsDiv.classList.remove('hidden');
  console.log(`Selected customer ID: ${selectedCustomerId}`);
}


function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}
// Karigar modal elements and functions
const karigarModal = document.getElementById('karigarModal');
const newKarigarNameInput = document.getElementById('newKarigarName');
const newKarigarPhoneInput = document.getElementById('newKarigarPhone');
const newKarigarAlternatePhoneInput = document.getElementById('newKarigarAlternatePhone');
const newKarigarEmailInput = document.getElementById('newKarigarEmail');
const newKarigarAddressLine1Input = document.getElementById('newKarigarAddressLine1');
const newKarigarAddressLine2Input = document.getElementById('newKarigarAddressLine2');
const newKarigarCityInput = document.getElementById('newKarigarCity');
const newKarigarStateInput = document.getElementById('newKarigarState');
const newKarigarPostalCodeInput = document.getElementById('newKarigarPostalCode');
const newKarigarCountryInput = document.getElementById('newKarigarCountry');
const newKarigarMakingChargeInput = document.getElementById('newKarigarMakingCharge');
const newKarigarChargeTypeSelect = document.getElementById('newKarigarChargeType');
const newKarigarGstNumberInput = document.getElementById('newKarigarGstNumber');
const newKarigarPanNumberInput = document.getElementById('newKarigarPanNumber');
let selectedKarigarId = null;

function showAddKarigarModal() {
    if (!karigarModal) { 
        console.error("Karigar modal element not found."); 
        return; 
    }
    
    karigarModal.style.display = 'flex';
    setupKarigarModalTabs();
    
 // Reset form fields
    if (newKarigarNameInput) newKarigarNameInput.value = '';
    if (newKarigarPhoneInput) newKarigarPhoneInput.value = '';
    if (newKarigarAlternatePhoneInput) newKarigarAlternatePhoneInput.value = '';
    if (newKarigarEmailInput) newKarigarEmailInput.value = '';
    if (newKarigarAddressLine1Input) newKarigarAddressLine1Input.value = '';
    if (newKarigarAddressLine2Input) newKarigarAddressLine2Input.value = '';
    if (newKarigarCityInput) newKarigarCityInput.value = '';
    if (newKarigarStateInput) newKarigarStateInput.value = '';
    if (newKarigarPostalCodeInput) newKarigarPostalCodeInput.value = '';
    if (newKarigarCountryInput) newKarigarCountryInput.value = 'India';
    if (newKarigarMakingChargeInput) newKarigarMakingChargeInput.value = '';
    if (newKarigarChargeTypeSelect) newKarigarChargeTypeSelect.value = 'per_gram';
    if (newKarigarGstNumberInput) newKarigarGstNumberInput.value = '';
    if (newKarigarPanNumberInput) newKarigarPanNumberInput.value = '';
    
    // Reset tabs
    const modalTabs = karigarModal.querySelectorAll('.modal-tab');
    const modalTabContents = karigarModal.querySelectorAll('.modal-tab-content');
    
    modalTabs.forEach(t => {
        t.classList.remove('active');
        const indicator = t.querySelector('.h-0\\.5');
        if (indicator) {
            indicator.classList.remove('scale-x-100');
            indicator.classList.add('scale-x-0');
        }
    });
    modalTabContents.forEach(c => c.classList.remove('active'));
    
    // Set first tab as active
    const firstTabButton = karigarModal.querySelector('.modal-tab[data-tab="basic-info"]');
    if (firstTabButton) {
        firstTabButton.classList.add('active');
        const indicator = firstTabButton.querySelector('.h-0\\.5');
        if (indicator) {
            indicator.classList.remove('scale-x-0');
            indicator.classList.add('scale-x-100');
        }
    }
    
    const firstTabContent = karigarModal.querySelector('#basic-info-content');
    if (firstTabContent) {
        firstTabContent.classList.add('active');
    }
}
function closeKarigarModal() {
    if (!karigarModal) { 
        console.error("Karigar modal element not found."); 
        return; 
    }
    karigarModal.style.display = 'none';
}

function setupKarigarModalTabs() {
    const modalTabs = karigarModal.querySelectorAll('.modal-tab');
    const modalTabContents = karigarModal.querySelectorAll('.modal-tab-content');
    
    modalTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabId = tab.getAttribute('data-tab');
            modalTabs.forEach(t => t.classList.remove('active'));
            modalTabContents.forEach(c => c.classList.remove('active'));
            
            tab.classList.add('active');
            const indicator = tab.querySelector('.h-0\\.5');
            if (indicator) {
                indicator.classList.remove('scale-x-0');
                indicator.classList.add('scale-x-100');
            }
            
            const content = karigarModal.querySelector(`#${tabId}-content`);
            if (content) {
                content.classList.add('active');
            }
        });
    });
}

function saveKarigar() {
    const name = newKarigarNameInput ? newKarigarNameInput.value.trim() : '';
    const phone = newKarigarPhoneInput ? newKarigarPhoneInput.value.trim() : '';
    const alternatePhone = newKarigarAlternatePhoneInput ? newKarigarAlternatePhoneInput.value.trim() : '';
    const email = newKarigarEmailInput ? newKarigarEmailInput.value.trim() : '';
    const addressLine1 = newKarigarAddressLine1Input ? newKarigarAddressLine1Input.value.trim() : '';
    const addressLine2 = newKarigarAddressLine2Input ? newKarigarAddressLine2Input.value.trim() : '';
    const city = newKarigarCityInput ? newKarigarCityInput.value.trim() : '';
    const state = newKarigarStateInput ? newKarigarStateInput.value.trim() : '';
    const postalCode = newKarigarPostalCodeInput ? newKarigarPostalCodeInput.value.trim() : '';
    const country = newKarigarCountryInput ? newKarigarCountryInput.value.trim() : 'India';
    const makingCharge = newKarigarMakingChargeInput ? newKarigarMakingChargeInput.value : '';
    const chargeType = newKarigarChargeTypeSelect ? newKarigarChargeTypeSelect.value : 'per_gram';
    const gstNumber = newKarigarGstNumberInput ? newKarigarGstNumberInput.value.trim() : '';
    const panNumber = newKarigarPanNumberInput ? newKarigarPanNumberInput.value.trim() : '';
    
    if (!name || !phone) {
        showToast('Name and phone number are required', 'error');
        // Switch back to basic info tab if validation fails
        const basicInfoTabButton = karigarModal.querySelector('.modal-tab[data-tab="basic-info"]');
        if (basicInfoTabButton && !basicInfoTabButton.classList.contains('active')) {
            const modalTabs = karigarModal.querySelectorAll('.modal-tab');
            const modalTabContents = karigarModal.querySelectorAll('.modal-tab-content');
            
            modalTabs.forEach(t => t.classList.remove('active'));
            modalTabContents.forEach(c => c.classList.remove('active'));
            
            basicInfoTabButton.classList.add('active');
            const indicator = basicInfoTabButton.querySelector('.h-0\\.5');
            if (indicator) {
                indicator.classList.remove('scale-x-0');
                indicator.classList.add('scale-x-100');
            }
            
            const basicInfoContent = karigarModal.querySelector('#basic-info-content');
            if (basicInfoContent) {
                basicInfoContent.classList.add('active');
            }
        }
        return;
    }
    
    const karigarData = new FormData();
    karigarData.append('name', name);
    karigarData.append('phone', phone);
    karigarData.append('alternate_phone', alternatePhone);
    karigarData.append('email', email);
    karigarData.append('address_line1', addressLine1);
    karigarData.append('address_line2', addressLine2);
    karigarData.append('city', city);
    karigarData.append('state', state);
    karigarData.append('postal_code', postalCode);
    karigarData.append('country', country);
    karigarData.append('default_making_charge', makingCharge);
    karigarData.append('charge_type', chargeType);
    karigarData.append('gst_number', gstNumber);
    karigarData.append('pan_number', panNumber);
    
    console.log('Saving karigar data:', {
        name, phone, alternatePhone, email, addressLine1, addressLine2, city, state, 
        postalCode, country, makingCharge, chargeType, gstNumber, panNumber
    });
    
    fetch('order.php?action=addKarigar', {
        method: 'POST',
        body: karigarData
    })
    .then(response => {
        console.log('Add karigar response status:', response.status);
        if (!response.ok) {
            return response.text().then(text => { 
                throw new Error(`HTTP error! status: ${response.status}, body: ${text}`); 
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Add karigar response data:', data);
        if (data.success) {
            showToast('Karigar added successfully');
            closeKarigarModal();
            
            // Select the newly added karigar
            if (data.karigar) {
                selectKarigar(data.karigar);
            } else {
                console.error("Received success=true but no karigar data in response.");
                showToast('Karigar added, but failed to select automatically.', 'info');
            }
        } else {
            showToast(data.message || 'Failed to add karigar', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding karigar:', error);
        showToast('Failed to add karigar', 'error');
    });
}

// Karigar search functionality
const karigarSearchInput = document.getElementById('karigarSearch');
const karigarDropdown = document.getElementById('karigarDropdown');

function setupKarigarSearch() {
    if (!karigarSearchInput || !karigarDropdown) {
        console.error("Karigar search elements not found during setup.");
        return;
    }
    
    karigarSearchInput.addEventListener('input', debounce((e) => {
        const searchTerm = e.target.value.trim();
        if (searchTerm.length < 2) {
            karigarDropdown.style.display = 'none';
            selectedKarigarId = null;
            console.log('Search term too short, clearing selected karigar.');
            return;
        }
        
        console.log('Searching for karigars with term:', searchTerm);
        
        fetch(`order.php?action=searchKarigars&term=${encodeURIComponent(searchTerm)}`)
            .then(response => {
                console.log('Karigar search response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => { 
                        throw new Error(`HTTP error! status: ${response.status}, body: ${text}`); 
                    });
                }
                return response.json();
            })
            .then(karigars => {
                console.log('Karigar search results:', karigars);
                karigarDropdown.innerHTML = '';
                
                if (karigars.length > 0) {
                    karigars.forEach(karigar => {
                        const karigarItem = document.createElement('div');
                        karigarItem.className = 'karigar-item p-2 hover:bg-gray-50 cursor-pointer border-b border-gray-100';
                        karigarItem.onclick = () => selectKarigar(karigar);
                        karigarItem.innerHTML = `
                            <div class="karigar-name font-medium text-gray-800">${karigar.name}</div>
                            <div class="karigar-info text-sm text-gray-600">
                                <span class="inline-flex items-center mr-3">
                                    <i class="fas fa-phone text-xs mr-1"></i>
                                    ${karigar.phone_number}
                                </span>
                                ${karigar.address_line1 ? `
                                <span class="inline-flex items-center">
                                    <i class="fas fa-tools text-xs mr-1"></i>
                                    ${karigar.address_line1}
                                </span>
                                ` : ''}
                            </div>
                        `;
                        karigarDropdown.appendChild(karigarItem);
                    });
                    karigarDropdown.style.display = 'block';
                    console.log(`${karigars.length} karigar results displayed.`);
                } else {
                    karigarDropdown.style.display = 'none';
                    console.log('No karigar results found.');
                }
            })
            .catch(error => {
                console.error('Error searching karigars:', error);
                showToast('Error searching karigars', 'error');
            });
    }), 300);
    
    // Hide dropdown when input loses focus
    karigarSearchInput.addEventListener('blur', () => {
        setTimeout(() => {
            if (karigarDropdown) karigarDropdown.style.display = 'none';
        }, 200);
    });
    
    // Prevent mousedown on dropdown from hiding it immediately
    if (karigarDropdown) {
        karigarDropdown.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });
    }
}

function selectKarigar(karigar) {
    if (!karigarSearchInput || !karigarDropdown) {
        console.error("Karigar select elements not found during selection.");
        return;
    }
    
    karigarSearchInput.value = karigar.name;
    karigarDropdown.style.display = 'none';
    selectedKarigarId = karigar.id;
    
    console.log('Karigar selected:', karigar);
    console.log(`Selected karigar ID: ${selectedKarigarId}`);
    
    // You can add visual feedback here similar to customer selection
    // For example, show karigar details below the search field
}

// Add to your DOMContentLoaded event listener:
document.addEventListener('DOMContentLoaded', function() {
    // ... your existing code ...
    
    // Setup karigar search
    setupKarigarSearch();
    console.log('Karigar search setup complete.');
    
    // Setup close button for karigar modal
    const karigarModalElement = document.getElementById('karigarModal');
    if (karigarModalElement) {
        karigarModalElement.addEventListener('click', (event) => {
            if (event.target.closest('.modal .fa-times')) {
                closeKarigarModal();
            }
        });
        console.log('Karigar modal close button event listener attached.');
    } else {
        console.error("Karigar modal element not found.");
    }
});




// ... existing code ...


// Add event listeners when document loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded fired'); // Debug log

    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    function switchTab(tabName) {
        console.log('Switching to tab:', tabName); // Debug log
        // Hide all tab contents
        tabContents.forEach(content => {
            content.style.display = 'none';
            content.classList.remove('active');
        });

        // Deactivate all buttons
        tabButtons.forEach(btn => {
            btn.classList.remove('active');
            const indicator = btn.querySelector('.h-0\\.5'); // Use double escape for dot
            if (indicator) {
                indicator.classList.remove('scale-x-100');
                indicator.classList.add('scale-x-0');
            }
        });

        // Show selected tab content
        const selectedTab = document.getElementById(tabName);
        if (selectedTab) {
            selectedTab.style.display = 'block';
            selectedTab.classList.add('active');
        } else {
             console.error('Selected tab content not found:', tabName);
        }

        // Activate selected button
        const selectedButton = document.querySelector(`[data-tab="${tabName}"]`);
        if (selectedButton) {
            selectedButton.classList.add('active');
            const indicator = selectedButton.querySelector('.h-0\\.5'); // Use double escape for dot
            if (indicator) {
                indicator.classList.remove('scale-x-0');
                indicator.classList.add('scale-x-100');
            }
        } else {
             console.error('Selected tab button not found:', tabName);
        }
    }

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabName = button.dataset.tab;
            switchTab(tabName);
        });
    });

    // Set initial active tab
    switchTab('order-form');

    // Initially hide the cart view
    const cartViewElement = document.getElementById('cartView');
    if (cartViewElement) {
        cartViewElement.classList.add('hidden');
         console.log('Cart view initially hidden.');
    } else {
        console.error("Cart view element not found on DOMContentLoaded.");
    }

    renderCartItems(); // Render initial cart state (should be empty)
    updateCartSummary(); // Update summary (should be zero)

  
    // Setup customer modal related functions
    const saveCustomerButton = document.querySelector('button[onclick="saveCustomer()"]');
    if (saveCustomerButton) {
        // Remove the inline onclick to avoid duplicate calls
        saveCustomerButton.removeAttribute('onclick');
        // Add event listener
        saveCustomerButton.addEventListener('click', saveCustomer);
         console.log('Save Customer button event listener attached.');
    } else {
         console.error("Save Customer button not found.");
    }
  
    // Setup close button for customer modal
    // Use event delegation on the modal container
    const customerModalElement = document.getElementById('customerModal');
     if(customerModalElement) {
        customerModalElement.addEventListener('click', (event) => {
            // Check if the clicked element or its parent is the close button
            if (event.target.closest('.modal .fa-times')) {
                closeCustomerModal();
            }
        });
         console.log('Customer modal close button event listener attached.');
    } else {
         console.error("Customer modal element not found.");
    }

  
    // Setup customer search
    setupCustomerSearch();
     console.log('Customer search setup complete.');
  
    // Add event listener for the Add to Cart button
    const saveOrderButton = document.getElementById('saveOrder');
    if (saveOrderButton) {
        // Change event listener to call the new function
        // saveOrderButton.removeEventListener('click', addItemToCart); // Remove old listener if it exists
        saveOrderButton.addEventListener('click', saveOrUpdateItem); // Add new listener
        console.log('Save Order/Add to Cart button event listener attached.');
                } else {
         console.error("Save Order button element not found.");
    }

    // Add event listener for the Cancel button
    const cancelOrderButton = document.getElementById('cancelOrder');
    if (cancelOrderButton) {
        cancelOrderButton.addEventListener('click', cancelOrder);
        console.log('Cancel Order button event listener attached.');
    } else {
         console.error("Cancel Order button element not found.");
    }


    // Initialize calculations on input changes using debounce
    // Check if elements exist before adding listeners
    const grossWeightInput = document.getElementById('grossWeight');
    const lessWeightInput = document.getElementById('lessWeight');
    const purityInput = document.getElementById('purity');
    const metalTypeSelect = document.getElementById('metalType');
    const goldRateInput = document.getElementById('goldRate24k');
    const makingTypeSelect = document.getElementById('makingType');
    const makingChargeInput = document.getElementById('makingCharge');
    const netWeightInput = document.getElementById('netWeight'); // Add listener for manual changes
    const stonePriceInput = document.getElementById('stonePrice'); // Get stone price input


    if (grossWeightInput) grossWeightInput.addEventListener('input', debounce(calculateNetWeight, 100)); else console.error("grossWeight input not found.");
    if (lessWeightInput) lessWeightInput.addEventListener('input', debounce(calculateNetWeight, 100)); else console.error("lessWeight input not found.");
    if (purityInput) purityInput.addEventListener('input', debounce(calculateMetalAmount, 100)); else console.error("purity input not found.");
    if (metalTypeSelect) metalTypeSelect.addEventListener('change', debounce(calculateMetalAmount, 100)); else console.error("metalType select not found.");
    if (goldRateInput) goldRateInput.addEventListener('input', debounce(calculateMetalAmount, 100)); else console.error("goldRate24k input not found.");
    if (makingTypeSelect) makingTypeSelect.addEventListener('change', debounce(calculateMakingCharges, 100)); else console.error("makingType select not found.");
    // MAKING CHARGE INPUT SHOULD TRIGGER calculateMakingCharges, not calculateTotalEstimate
    if (makingChargeInput) makingChargeInput.addEventListener('input', debounce(calculateMakingCharges, 100)); else console.error("makingCharge input not found.");
    // Stone price input should trigger calculateTotalEstimate
    if (stonePriceInput) stonePriceInput.addEventListener('input', debounce(calculateTotalEstimate, 100)); else console.error("stonePrice input not found."); // Add listener for stone price

    // Add listener for netWeight input if it can be manually changed (though it's readonly)
    if (netWeightInput && !netWeightInput.readOnly) netWeightInput.addEventListener('input', debounce(calculateMetalAmount, 100)); // If it becomes editable, recalculate metal amount

     console.log('Calculation event listeners attached.');

    // Load gold rate from server when page loads
    fetchGoldRate();
    console.log('Gold rate fetch initiated.');
  
    // Setup image handling
    const imageUploadElement = document.getElementById('imageUpload');
    if (imageUploadElement) {
        imageUploadElement.addEventListener('change', handleImageUpload);
         console.log('Image upload event listener attached.');
    } else {
         console.error("imageUpload element not found.");
    }
  
    // Camera capture button handler
    const captureImageButton = document.getElementById('captureImage');
    if (captureImageButton) {
        captureImageButton.addEventListener('click', startCamera);
         console.log('Capture image button event listener attached.');
    } else {
        console.error("captureImage button not found.");
    }

    // Setup close button for camera modal
    const cameraModalElement = document.getElementById('cameraModal');
    if(cameraModalElement) {
        cameraModalElement.addEventListener('click', (event) => {
            // Check if the clicked element or its parent is the close button
            if (event.target.closest('.modal .fa-times')) {
                closeCameraModal();
            }
        });
         console.log('Camera modal close button event listener attached.');
    } else {
         console.error("Camera modal element not found.");
    }

     // Add event listener for bottom nav cart icon click
     const bottomNavCartItem = document.querySelector('.nav-item.relative[onclick="showCartView()"]');
     if (bottomNavCartItem) {
          // Remove old inline onclick and add event listener for consistency and reliability
          bottomNavCartItem.removeAttribute('onclick');
          bottomNavCartItem.addEventListener('click', (event) => {
              // Check if the clicked item is the cart item by looking for the cart icon or badge
              if (event.target.closest('.nav-item') && (event.target.closest('.nav-icon.fas.fa-shopping-cart') || event.target.closest('.cart-badge'))) {
                  console.log('Bottom nav cart icon clicked.');
                  showCartView();
              }
          });
          console.log('Bottom nav cart item event listener attached.');
     } else {
         console.error("Bottom nav cart item not found.");
     }

    // Add event listener for Process Order button
    const processOrderButton = document.getElementById('processOrder');
    if (processOrderButton) {
        processOrderButton.addEventListener('click', processOrder);
        console.log('Process Order button event listener attached');
    } else {
        console.error("Process Order button not found");
    }

    console.log('All DOMContentLoaded tasks completed.');
});

// --- New Functions for View Switching ---

// Function to show the order form and hide the cart view
function showOrderForm() {
    console.log('Attempting to show order form');
    const orderFormContainer = document.getElementById('orderFormContainer');
    const cartView = document.getElementById('cartView');

    if (orderFormContainer && cartView) {
        cartView.classList.add('hidden');
        orderFormContainer.classList.remove('hidden');
        console.log('Order form shown, cart view hidden.');
        // Optionally scroll to top of form
        // orderFormContainer.scrollIntoView({ behavior: 'smooth' });
    } else {
        console.error("Order form container or cart view element not found for showOrderForm.");
    }
}

// Function to show the cart view and hide the order form
function showCartView() {
    console.log('Attempting to show cart view');
    const orderFormContainer = document.getElementById('orderFormContainer');
    const cartView = document.getElementById('cartView');

     if (orderFormContainer && cartView) {
        orderFormContainer.classList.add('hidden');
        cartView.classList.remove('hidden');
        console.log('Cart view shown, order form hidden.');
        // Optionally scroll to top of cart
         // cartView.scrollIntoView({ behavior: 'smooth' });
    } else {
        console.error("Order form container or cart view element not found for showCartView.");
    }
}


// --- End New Functions ---


// Weight calculation functions
function calculateNetWeight() {
  const grossWeightInput = document.getElementById('grossWeight');
  const lessWeightInput = document.getElementById('lessWeight');
  const netWeightInput = document.getElementById('netWeight');

   if (!grossWeightInput || !lessWeightInput || !netWeightInput) {
       console.error("Weight input elements not found for calculateNetWeight.");
       return;
   }

  const grossWeight = parseFloat(grossWeightInput.value) || 0;
  const lessWeight = parseFloat(lessWeightInput.value) || 0;
  const netWeight = Math.max(0, grossWeight - lessWeight);
  netWeightInput.value = netWeight.toFixed(3);
   console.log(`Net Weight Calculated: Gross=${grossWeight}, Less=${lessWeight}, Net=${netWeight.toFixed(3)}`);
  calculateMetalAmount(); // Recalculate metal amount when net weight changes
}

function calculateMetalAmount() {
   console.log('calculateMetalAmount function called'); // Debug log
   const rate24kInput = document.getElementById('goldRate24k');
   const purityInput = document.getElementById('purity');
   const netWeightInput = document.getElementById('netWeight');
   const metalTypeSelect = document.getElementById('metalType');
   const metalAmountInput = document.getElementById('metalAmount');
   const purityRateElement = document.getElementById('purityRate');


   if (!rate24kInput || !purityInput || !netWeightInput || !metalTypeSelect || !metalAmountInput || !purityRateElement) {
        console.error("Metal calculation elements not found for calculateMetalAmount.");
        return;
   }

   const rate24k = parseFloat(rate24kInput.value) || 0;
   const purity = parseFloat(purityInput.value) || 0;
   const netWeight = parseFloat(netWeightInput.value) || 0;
   const metalType = metalTypeSelect.value;
  
  // Calculate rate based on purity
   let purityRate = 0;
   // Assume rate24k is only for Gold calculation
   if (metalType === 'Gold' && rate24k > 0) {
       purityRate = (rate24k * purity) / 100; // Purity is % or KT, convert to factor
   }
    // You would add logic here for other metal types if their rates are handled differently

   // Display the purity rate
    purityRateElement.value = purityRate.toFixed(2);

  
  // Calculate total metal amount
  const metalAmount = purityRate * netWeight;
  
   metalAmountInput.value = metalAmount.toFixed(2);

   console.log(`Metal Amount Calculated: Rate24k=${rate24k}, Purity=${purity}, PurityRate=${purityRate.toFixed(2)}, NetWeight=${netWeight.toFixed(3)}, MetalType=${metalType}, MetalAmount=${metalAmount.toFixed(2)}`);

   // Re-calculate estimated total as metal amount changed
    calculateTotalEstimate(); // Call calculateTotalEstimate here
}

// Function to calculate making charges (does not update a specific input, returns value)
function calculateMakingCharges() {
    console.log('calculateMakingCharges function called'); // Debug log
    const makingTypeSelect = document.getElementById('makingType');
    const makingChargeInput = document.getElementById('makingCharge');
    const netWeightInput = document.getElementById('netWeight');
    const metalAmountInput = document.getElementById('metalAmount'); // Need metal amount for percentage calculation


     if (!makingTypeSelect || !makingChargeInput || !netWeightInput || !metalAmountInput) {
        console.error("Making charge calculation elements not found for calculateMakingCharges.");
        return 0; // Return 0 if elements are missing
     }

    const makingType = makingTypeSelect.value;
    const makingChargeValue = parseFloat(makingChargeInput.value) || 0; // The value entered by the user
    const netWeight = parseFloat(netWeightInput.value) || 0;
    const metalAmount = parseFloat(metalAmountInput.value) || 0; // Get the calculated metal amount

    let calculatedMakingCharges = 0;
     if (makingType === 'per_gram') {
        calculatedMakingCharges = makingChargeValue * netWeight;
    } else if (makingType === 'percentage') {
         // Calculate percentage based on Metal Amount
         calculatedMakingCharges = (makingChargeValue / 100) * metalAmount; // Use metalAmount here
    } else if (makingType === 'fixed') {
        calculatedMakingCharges = makingChargeValue;
    }

    console.log(`Making Charges Calculated: Type=${makingType}, InputValue=${makingChargeValue}, BasedOnWeight=${netWeight.toFixed(3)}, BasedOnMetalAmount=${metalAmount.toFixed(2)}, Calculated Value=${calculatedMakingCharges.toFixed(2)}`);
    // Do NOT call calculateTotalEstimate here - it is called by calculateMetalAmount and stonePrice input
    return calculatedMakingCharges; // Return the calculated value
}


// Function to calculate the estimated total for a single item based on current form values
function calculateTotalEstimate() {
     console.log('calculateTotalEstimate function called'); // Debug log for total estimate calculation
     const metalAmountInput = document.getElementById('metalAmount');
     const stonePriceInput = document.getElementById('stonePrice');
     const makingTypeSelect = document.getElementById('makingType');
     const makingChargeInput = document.getElementById('makingCharge');
     const netWeightInput = document.getElementById('netWeight');
     const goldRateInput = document.getElementById('goldRate24k');
     const purityInput = document.getElementById('purity');


    if (!metalAmountInput || !stonePriceInput || !makingTypeSelect || !makingChargeInput || !netWeightInput || !goldRateInput || !purityInput) {
        console.error("Estimated total calculation elements not found for calculateTotalEstimate.");
        return 0; // Return 0 if elements are missing
     }

    // Ensure dependent calculations are up-to-date before calculating total
    // Recalculate metal amount if needed (e.g., purity or rate changed)
    // calculateMetalAmount(); // Avoid recursive trigger - calculateMetalAmount calls this function

    const metalAmount = parseFloat(metalAmountInput.value) || 0;
    const stonePrice = parseFloat(stonePriceInput.value) || 0;

    // Explicitly call calculateMakingCharges to get the value
    const makingCharges = calculateMakingCharges(); // This will calculate based on current form inputs


    const totalEstimate = metalAmount + makingCharges + stonePrice;

    // This calculated total is not displayed in the form itself, only when the item is added to the cart.
    console.log('Calculated Estimated Total for current item form data:', totalEstimate.toFixed(2));
    return totalEstimate; // Return the calculated total
}


// Function to fetch gold rate from server
function fetchGoldRate() {
  console.log('Fetching gold rate from server...');
  fetch('order.php?action=getGoldRate')
    .then(response => {
      console.log('Gold rate fetch response status:', response.status); // Log status
      if (!response.ok) {
        console.error('Gold rate fetch failed with status:', response.status);
         // Attempt to read response text even on error for better debugging
         return response.text().then(text => { throw new Error(`Network response was not ok, status: ${response.status}, body: ${text}`); });
      }
      return response.json();
    })
    .then(data => {
      console.log('Gold rate received:', data); // Log received data
      // Set the goldRate24k input value
      const goldRateInput = document.getElementById('goldRate24k');
      if (goldRateInput && data && data.rate !== undefined) { // Check for data and rate property
        goldRateInput.value = data.rate;
        console.log('Gold rate set to:', data.rate);
        showToast('Gold rate loaded successfully', 'success'); // Show success toast
        // Recalculate metal amount and estimated total after setting gold rate
        calculateMetalAmount(); // This will trigger calculateTotalEstimate
      } else {
        console.error('Gold rate input element not found or data format incorrect.', data);
        showToast('Failed to load gold rate: Invalid data', 'error'); // Show error toast for data issue
      }
    })
    .catch(error => {
      console.error('Error fetching gold rate:', error); // Log fetch error
      showToast('Failed to fetch gold rate', 'error'); // Show error toast for fetch issue
    });
}

// Image handling
// let referenceImages = []; // Already declared at the top
// let stream = null; // Already declared at the top

// Handle image upload
function handleImageUpload(event) {
  const files = event.target.files;
  const container = document.getElementById('imagePreviewContainer');

   if (!container) {
       console.error("Image preview container not found.");
       return;
   }

  
  for (let file of files) {
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      
      reader.onload = function(e) {
        addImageToPreview(e.target.result);
      };
      
      reader.readAsDataURL(file); // Read file as data URL
    } else {
        console.warn('Skipping non-image file:', file.name);
    }
  }
}

// Add image to preview and referenceImages array
function addImageToPreview(imageData) {
  const container = document.getElementById('imagePreviewContainer');
   if (!container) {
       console.error("Image preview container not found for addImageToPreview.");
       return;
   }
  const imageWrapper = document.createElement('div');
  imageWrapper.className = 'relative group';
  
  const img = document.createElement('img');
  img.src = imageData; // Set src to the data URL
  img.className = 'w-20 h-20 object-cover rounded-lg';
  
  const deleteButton = document.createElement('button');
  deleteButton.className = 'absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center z-10'; // Added z-index
  deleteButton.innerHTML = '<i class="fas fa-times text-xs"></i>';
  // Use an anonymous function to capture the correct imageData for removal
  deleteButton.onclick = () => {
    console.log('Removing image from preview.');
    imageWrapper.remove();
    // Remove the image data from the referenceImages array
    referenceImages = referenceImages.filter(img => img !== imageData);
    console.log("Image removed, current referenceImages count:", referenceImages.length);
    // updateReferenceImagesCount(); // If you have a count display somewhere
  };
  
  imageWrapper.appendChild(img);
  imageWrapper.appendChild(deleteButton);
  container.appendChild(imageWrapper);
  
  // Add the image data URL to the referenceImages array
  referenceImages.push(imageData);
  console.log("Image added, current referenceImages count:", referenceImages.length);

  // updateReferenceImagesCount(); // If you have a count display somewhere
}

// Camera handling functions
function startCamera() {
  console.log('Attempting to start camera.');
  const cameraModal = document.getElementById('cameraModal');
  const video = document.getElementById('cameraFeed');

   if (!cameraModal || !video) {
       console.error("Camera modal or video element not found for startCamera.");
       showToast('Camera elements not found.', 'error');
       return;
   }
  
  cameraModal.style.display = 'flex';
  
  // Stop any existing stream first
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    stream = null;
     console.log("Existing camera stream stopped.");
  }
  
  // Request access to the camera
  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
    .then(function(mediaStream) {
      stream = mediaStream;
      video.srcObject = mediaStream;
      // Optional: Play the video to show the feed
      video.play();
       console.log('Camera stream started successfully.');
    })
    .catch(function(err) {
      console.error("Error accessing camera:", err); // Log the actual error
      let errorMessage = 'Error accessing camera.';
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
          errorMessage = 'Camera access denied. Please grant permission in your browser settings.';
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
          errorMessage = 'No camera found on this device.';
      }
      showToast(errorMessage, 'error');
      closeCameraModal(); // Close modal on error
    });
}

function closeCameraModal() {
  console.log('Attempting to close camera modal.');
  const cameraModal = document.getElementById('cameraModal');
   if (!cameraModal) {
       console.error("Camera modal element not found for closeCameraModal.");
       return;
   }
  cameraModal.style.display = 'none';
  
  // Stop all tracks in the stream to turn off the camera light
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    stream = null;
     console.log("Camera stream stopped.");
  }
   console.log('Camera modal closed.');
}

function takePicture() {
  console.log('Attempting to take picture.');
  const video = document.getElementById('cameraFeed');
   if (!video) {
       console.error("Camera feed video element not found for takePicture.");
       showToast('Camera feed not available.', 'error');
       return;
   }
   if (video.readyState !== video.HAVE_ENOUGH_DATA) {
       console.warn('Video feed not ready yet.');
       showToast('Camera feed not ready yet, please wait.', 'info');
       return;
   }

  const canvas = document.createElement('canvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  if (canvas.width === 0 || canvas.height === 0) {
      console.error("Canvas dimensions are zero, cannot take picture.");
      showToast('Failed to take picture: Camera not ready.', 'error');
      return;
  }
  
  const context = canvas.getContext('2d');
  context.drawImage(video, 0, 0, canvas.width, canvas.height);
  
  // Get the image data as a JPEG Data URL
  const imageData = canvas.toDataURL('image/jpeg', 0.9); // Use JPEG with 90% quality
  
  console.log('Picture taken, adding to preview.');
  addImageToPreview(imageData); // Add the captured image to the preview

  closeCameraModal(); // Close the modal after taking the picture
}

// When saving the order, you can access the images from referenceImages array
function getOrderImages() {
  return referenceImages;
}

// Add these new functions for stone section
function toggleStoneSection() {
  const content = document.getElementById('stoneSectionContent');
  const icon = document.getElementById('stoneSectionIcon');

   if (!content || !icon) {
       console.error("Stone section elements not found for toggleStoneSection.");
       return;
   }

  if (content.classList.contains('hidden')) {
    content.classList.remove('hidden');
    icon.style.transform = 'rotate(180deg)';
      console.log('Stone section expanded.');
  } else {
    content.classList.add('hidden');
    icon.style.transform = 'rotate(0deg)';
       console.log('Stone section collapsed.');
  }
}

// Add this new function after the calculateNetWeight function
function convertStoneWeightToGrams(weight, unit) {
    // Conversion factors
    const CARAT_TO_GRAM = 0.2; // 1 carat = 0.2 grams
    const RATTI_TO_GRAM = 0.1215; // 1 ratti = 0.1215 grams (approximate)
    
    if (unit === 'ct') {
        return weight * CARAT_TO_GRAM;
    } else if (unit === 'ratti') {
        return weight * RATTI_TO_GRAM;
    }
    return 0;
}

// Add this new function to handle stone weight changes
function handleStoneWeightChange() {
    const stoneWeightInput = document.getElementById('stoneWeight');
    const stoneUnitSelect = document.getElementById('stoneUnit');
    const lessWeightInput = document.getElementById('lessWeight');
    
    if (!stoneWeightInput || !stoneUnitSelect || !lessWeightInput) {
        console.error("Stone weight elements not found.");
        return;
    }
    
    const stoneWeight = parseFloat(stoneWeightInput.value) || 0;
    const stoneUnit = stoneUnitSelect.value;
    
    // Convert stone weight to grams
    const stoneWeightInGrams = convertStoneWeightToGrams(stoneWeight, stoneUnit);
    
    // Update less weight field
    lessWeightInput.value = stoneWeightInGrams.toFixed(3);
    
    // Trigger net weight calculation
    calculateNetWeight();
}
 
 // Add these new functions below the existing JavaScript

// Function to handle the Process Order button click
async function processOrder() {
    console.log('processOrder function called'); // Debug log

    if (!selectedCustomerId) {
        showToast('Please select a customer before processing the order.', 'error');
        return;
    }

    if (cartItems.length === 0) {
        showToast('Cart is empty. Please add items to the cart first.', 'error');
        return;
    }

    const advanceAmountInput = document.getElementById('advanceAmount');
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const estimatedGrandTotalSpan = document.getElementById('estimatedGrandTotal');

     if (!advanceAmountInput || !paymentMethodSelect || !estimatedGrandTotalSpan) {
        console.error("Payment or total elements not found for processOrder.");
        showToast('Error: Required payment elements not found.', 'error');
        return;
     }

    const advanceAmount = parseFloat(advanceAmountInput.value) || 0;
    const paymentMethod = paymentMethodSelect.value;
    const grandTotalText = estimatedGrandTotalSpan.textContent.replace('₹', '').replace(',', ''); // Remove currency symbol and comma
    const grandTotal = parseFloat(grandTotalText) || 0;

     // Basic validation for advance amount
     if (advanceAmount < 0) {
         showToast('Advance amount cannot be negative.', 'error');
         return;
     }

    // if (advanceAmount > grandTotal) {
    //      // Decide if this is allowed or show a warning
    //     showToast('Advance amount cannot be more than the grand total.', 'warning');
    //     // return; // Uncomment to prevent processing if advance > total
    // }

    console.log('Processing order for customer ID:', selectedCustomerId);
    console.log('Cart items to process:', [...cartItems]);
    console.log('Payment details: Advance=', advanceAmount, ', Method=', paymentMethod, ', GrandTotal=', grandTotal);

    // Prepare data to send to the server
    const orderData = {
        customerId: selectedCustomerId,
        karigarId: selectedKarigarId, // <-- Add karigarId here for the main order
        cartItems: cartItems.map(item => ({
            ...item,
            // You can keep this here too, or remove if PHP only reads it from the main level
            // karigarId: selectedKarigarId
        })),
        advanceAmount: advanceAmount,
        paymentMethod: paymentMethod,
        grandTotal: grandTotal,
        customerPhoneNumber: selectionDetailsDiv.querySelector('.detail-badge span').textContent.trim()
    };
    console.log('Data sending to server:', orderData);

    try {
        const response = await fetch('order.php?action=processOrder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(orderData)
        });

        console.log('Process order response status:', response.status);

        if (!response.ok) {
             const errorText = await response.text(); // Read error response for debugging
            console.error('Server response not OK:', errorText);
            throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
        }

        const result = await response.json();
        console.log('Process order result:', result);

        if (result.success) {
            showToast('Order placed successfully!', 'success');
            // Show success modal with order details
            showSuccessModal(result.order); // Pass the order details from the backend response
            // Clear cart and form after successful order
            cartItems = []; // Empty the cart array
            renderCartItems(); // Update cart display
            updateCartSummary(); // Update summary
            clearForm(); // Clear the order form
             // Keep customer selected for potentially new order or clear? Let's keep for now.
             // selectedCustomerId = null;
             // selectionDetailsDiv.classList.add('hidden');
             // selectionDetailsDiv.innerHTML = '';
             // document.getElementById('customerName').value = '';
             resetEditMode(); // Ensure not in edit mode
             showOrderForm(); // Switch back to the form view
        } else {
            showToast(result.message || 'Failed to process order.', 'error');
        }

    } catch (error) {
        console.error('Error processing order:', error);
        showToast('An error occurred while processing the order.', 'error');
    }
}

// Add these JavaScript functions for the success modal
const successModal = document.getElementById('successModal');
const successOrderNumberSpan = document.getElementById('successOrderNumber');
const successCustomerNameSpan = document.getElementById('successCustomerName');
const successGrandTotalSpan = document.getElementById('successGrandTotal');
const successAdvanceAmountSpan = document.getElementById('successAdvanceAmount');
const successRemainingAmountSpan = document.getElementById('successRemainingAmount');
const successPaymentMethodSpan = document.getElementById('successPaymentMethod');
const successOrderItemsList = document.getElementById('successOrderItemsList');
const whatsappShareButton = document.getElementById('whatsappShareButton');

function showSuccessModal(orderDetails) {
    console.log('showSuccessModal called with details:', orderDetails);
    if (!successModal || !successOrderNumberSpan || !successCustomerNameSpan || 
        !successGrandTotalSpan || !successAdvanceAmountSpan || !successRemainingAmountSpan || 
        !successPaymentMethodSpan || !successOrderItemsList || !whatsappShareButton) {
        console.error("Success modal elements not found.");
        return;
    }

    // Set order details in modal
    successOrderNumberSpan.textContent = `#${orderDetails.order_number}`;
    successCustomerNameSpan.textContent = orderDetails.customer_name;
    successGrandTotalSpan.textContent = `₹${parseFloat(orderDetails.grand_total).toFixed(2)}`;
    successAdvanceAmountSpan.textContent = `₹${parseFloat(orderDetails.advance_amount).toFixed(2)}`;
    successRemainingAmountSpan.textContent = `₹${parseFloat(orderDetails.remaining_amount).toFixed(2)}`;
    successPaymentMethodSpan.textContent = orderDetails.payment_method;

    // Clear and populate items list
    successOrderItemsList.innerHTML = '';
    orderDetails.items.forEach(item => {
        const li = document.createElement('li');
        li.textContent = `${item.itemName} - ₹${item.totalEstimate.toFixed(2)}`;
        successOrderItemsList.appendChild(li);
    });

    // Update WhatsApp share link
    const whatsappMessage = encodeURIComponent(
        `Order Confirmation #${orderDetails.order_number}\n` +
        `Amount: ₹${orderDetails.grand_total}\n` +
        `Advance Paid: ₹${orderDetails.advance_amount}\n` +
        `Thank you for your order!`
    );
    whatsappShareButton.href = `https://wa.me/${orderDetails.customer_phone_number}?text=${whatsappMessage}`;

    // Show the modal
    successModal.style.display = 'flex';
}

// Add close function for success modal
function closeSuccessModal() {
    if (successModal) {
        successModal.style.display = 'none';
    }
}

// Function to load orders
function loadOrders() {
    console.log('loadOrders function called'); // Added log
    const searchTerm = document.getElementById('orderSearch').value;
    const statusFilter = document.getElementById('orderFilter').value;
    
    console.log(`Fetching orders with search: "${searchTerm}" and status: "${statusFilter}"`); // Added log

    // Show loading state
    const ordersListElement = document.getElementById('ordersList'); // Get the element
    if (ordersListElement) { // Check if element exists
        ordersListElement.innerHTML = `
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                <span class="ml-3 text-gray-600">Loading Orders...</span> <!-- Added loading text -->
            </div>
        `;
         console.log('Loading state displayed.'); // Added log
    } else {
        console.error('ordersList element not found!'); // Log error if element is missing
    }
    
    // Fetch orders from the server
    fetch(`order.php?action=getOrders&search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(statusFilter)}`)
        .then(response => {
            console.log('Fetch response received:', response.status, response.statusText); // Added log
            if (!response.ok) {
                console.error('Network response was not ok:', response.statusText); // Log network errors
                throw new Error(`Network response was not ok: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received from server:', data); // Added log - crucial for debugging
            if (data.success) {
                console.log(`Successfully fetched ${data.orders ? data.orders.length : 0} orders.`); // Added log
                renderOrders(data.orders);
            } else {
                console.error('API returned success: false', data.message); // Log API specific errors
                showError(data.message || 'Failed to load orders');
            }
        })
        .catch(error => {
            console.error('Error fetching orders:', error); // Log fetch errors
            showError('Error loading orders: ' + error.message);
        });
}

// Function to render orders
function renderOrders(orders) {
    console.log('renderOrders function called'); // Added log
    console.log('Orders data received for rendering:', orders); // Added log - crucial for debugging
    const ordersList = document.getElementById('ordersList');
    
     if (!ordersList) {
         console.error('ordersList element not found during rendering!'); // Log error if element is missing
         return;
     }

    if (!orders || orders.length === 0) {
        console.log('No orders to render, displaying empty state.'); // Added log
        ordersList.innerHTML = `
            <div class="text-center py-6">
                <div class="w-14 h-14 mx-auto mb-3 text-gray-400">
                    <i class="fas fa-clipboard-list text-3xl"></i>
                </div>
                <h3 class="text-base font-medium text-gray-900">No Orders Found</h3>
                <p class="text-sm text-gray-500 mt-1">There are no orders matching your search criteria.</p>
            </div>
        `;
        return;
    }
    
    console.log(`Rendering ${orders.length} orders.`); // Added log
    let html = '';
   orders.forEach(order => {
    const statusClass = getStatusClass(order.order_status);
    const customerName = `${order.FirstName} ${order.LastName}`.trim();
    const progress = calculateProgress(order);
    const totalItems = order.items ? order.items.length : 0;

    html += `
    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 text-xs">
        <div class="p-2">
            <!-- Header -->
            <div class="flex justify-between items-center mb-1">
                <div>
                    <span class="font-semibold text-gray-800">${customerName}</span>
                    <div class="text-[10px] text-gray-500">Order #${order.order_number}</div>
                </div>
                <div class="flex items-center gap-1">
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium ${statusClass}">
                        ${order.order_status}
                    </span>
                    <button onclick="showOrderActions(${order.id})" class="text-gray-400 hover:text-gray-600 p-0.5 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-ellipsis-v text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Items Preview -->
            <div class="space-y-1 mb-1">
                ${order.items ? order.items.map(item => `
                    <div class="border rounded p-1 bg-gray-50">
                        <div class="flex justify-between items-center text-[10px] text-gray-700">
                            <span class="truncate font-medium">${item.item_name} (${item.product_type})</span>
                            <span>${parseFloat(item.net_weight || 0).toFixed(2)}g</span>
                        </div>

                        ${item.stone_type ? `
                        <div class="text-[10px] text-gray-600 mt-0.5">
                            💎 ${item.stone_type} ${item.stone_size || ''}, Qty: ${item.stone_quantity || '-'}, ${item.stone_weight || '-'}${item.stone_unit || ''}
                        </div>
                        ` : ''}
                        
                        ${item.size_details ? `
                        <div class="text-[10px] text-gray-500">
                            📏 Size: ${item.size_details}
                        </div>
                        ` : ''}
                    </div>
                `).join('') : ''}
            </div>

            <!-- Summary Grid -->
            <div class="grid grid-cols-4 gap-2 mb-1 text-gray-700 text-[10px]">
                <div><span class="text-gray-500">Total</span><br><span class="font-medium">₹${parseFloat(order.grand_total || 0).toFixed(2)}</span></div>
                <div><span class="text-gray-500">Advance</span><br><span class="font-medium">₹${parseFloat(order.advance_amount || 0).toFixed(2)}</span></div>
                <div><span class="text-gray-500">Due Date</span><br><span class="font-medium">${formatDate(order.expected_delivery_date)}</span></div>
                <div>
                    <span class="text-gray-500">Progress</span>
                    <div class="flex items-center gap-1">
                        <div class="flex-1 h-1 bg-gray-200 rounded-full">
                            <div class="h-full bg-blue-500 rounded-full" style="width: ${progress}%"></div>
                        </div>
                        <span>${progress}%</span>
                    </div>
                </div>
            </div>

            <!-- Footer: Karigar & Actions -->
            <div class="flex justify-between items-center pt-1 border-t mt-1">
                <div class="text-xs font-semibold text-yellow-800 bg-yellow-100 px-2 py-0.5 rounded-full shadow-sm">
                    ${order.karigar_name || 'Not Assigned'}
                </div>
                <div class="flex gap-1">
                    <a href="view_order.php?id=${order.id}" class="btn-icon text-blue-600 hover:bg-blue-50"><i class="fas fa-eye"></i> View</a>
                    <button onclick="editOrder(${order.id})" class="btn-icon text-indigo-600 hover:bg-indigo-50"><i class="fas fa-edit"></i> Edit</button>
                    <button onclick="printOrder(${order.id})" class="btn-icon text-green-600 hover:bg-green-50"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>
        </div>
    </div>`;
});
    
    ordersList.innerHTML = html;
     console.log('Finished rendering orders.'); // Added log
}

// Helper function to get status class
function getStatusClass(status) {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'in-progress': 'bg-blue-100 text-blue-800',
        'ready': 'bg-green-100 text-green-800',
        'delivered': 'bg-gray-100 text-gray-800',
        'cancelled': 'bg-red-100 text-red-800'
    };
    // Ensure status is a string before converting to lower case
    return classes[String(status).toLowerCase()] || 'bg-gray-100 text-gray-800'; // Added String() conversion
}

// Helper function to calculate progress
function calculateProgress(order) {
    if (!order || !order.order_status) return 0; // Added check for order and status
    const status = String(order.order_status).toLowerCase(); // Ensure status is string and lowercase
    if (status === 'delivered') return 100;
    if (status === 'ready') return 75;
    if (status === 'in progress') return 50;
    if (status === 'pending') return 25;
    return 0;
}

// Helper function to format date
function formatDate(dateString) {
    if (!dateString) return 'Not set';
    try { // Added try-catch for invalid date strings
        const date = new Date(dateString);
        if (isNaN(date.getTime())) { // Check if date is valid
             return 'Invalid Date';
        }
         return date.toLocaleDateString('en-IN', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (e) {
        console.error('Error formatting date:', dateString, e); // Log date formatting errors
        return 'Error Date';
    }
}

// Show error message function (already exists, just making sure it's clear)
function showError(message) {
    console.error('Displaying error message:', message); // Added log
    const ordersList = document.getElementById('ordersList');
     if (!ordersList) {
         console.error('ordersList element not found for showError!'); // Log error
         return;
     }
    ordersList.innerHTML = `
        <div class="text-center py-8">
            <div class="w-16 h-16 mx-auto mb-4 text-red-400">
                <i class="fas fa-exclamation-circle text-4xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900">Error</h3>
            <p class="text-gray-500 mt-2">${message}</p>
        </div>
    `;
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded fired, setting up event listeners.'); // Added log
    // Load orders on page load
    loadOrders();
    
    // Add event listeners for search and filter
    const orderSearchInput = document.getElementById('orderSearch');
    const orderFilterSelect = document.getElementById('orderFilter');

    if (orderSearchInput) {
         orderSearchInput.addEventListener('input', debounce(loadOrders, 300));
         console.log('orderSearch input listener attached.'); // Added log
    } else {
         console.error('orderSearch input not found!'); // Log error
    }

    if (orderFilterSelect) {
        orderFilterSelect.addEventListener('change', loadOrders);
         console.log('orderFilter select listener attached.'); // Added log
    } else {
         console.error('orderFilter select not found!'); // Log error
    }

    // Add event listeners for tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

     // Ensure only one listener is added per button
    tabButtons.forEach(button => {
        // Remove any existing listeners to prevent duplicates from previous edits
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
    });
    const updatedTabButtons = document.querySelectorAll('.tab-button');

    updatedTabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabName = button.dataset.tab;
            console.log('Tab button clicked, switching to tab:', tabName); // Added log
            // Switch tab content visibility
            tabContents.forEach(content => {
                if (content.id === tabName) {
                    content.classList.add('active');
                    content.style.display = 'block';
                } else {
                    content.classList.remove('active');
                    content.style.display = 'none';
                }
            });

            // Update active tab button styling
            updatedTabButtons.forEach(btn => {
                btn.classList.remove('active');
                const indicator = btn.querySelector('.h-0\\.5'); // Use double escape for dot
                 if (indicator) {
                     indicator.classList.remove('scale-x-100');
                     indicator.classList.add('scale-x-0');
                 }
            });
            button.classList.add('active');
             const indicator = button.querySelector('.h-0\\.5'); // Use double escape for dot
              if (indicator) {
                  indicator.classList.remove('scale-x-0');
                  indicator.classList.add('scale-x-100');
              }

            // If switching to the order list tab, load orders
            if (tabName === 'order-list') {
                console.log('Switched to order-list tab, calling loadOrders().'); // Added log
                loadOrders();
            }
             // If switching to other tabs, you might want to clear or save form data
             if (tabName === 'order-form') {
                 console.log('Switched to order-form tab.'); // Added log
                 // Optional: Add logic here to handle the order form state
             }
        });
         console.log(`Tab button listener attached for: ${button.dataset.tab}`); // Added log
    });

    // Set initial active tab (should be handled by CSS and initial render, but ensure JS state matches)
    const initialActiveTabButton = document.querySelector('.tab-button.active');
    const initialActiveTabContentId = initialActiveTabButton ? initialActiveTabButton.dataset.tab : 'order-form';
    console.log('Setting initial active tab state based on DOM:', initialActiveTabContentId); // Added log
    tabContents.forEach(content => {
         if (content.id === initialActiveTabContentId) {
             content.classList.add('active');
             content.style.display = 'block';
         } else {
             content.classList.remove('active');
             content.style.display = 'none';
         }
     });
});
// --- Order Action Functions ---
// Function to view order details
async function viewOrderDetails(orderId) {
    // Show modal
    document.getElementById('orderDetailsModal').classList.remove('hidden');
    
    // Show loading state
    document.getElementById('orderLoading').classList.remove('hidden');
    document.getElementById('orderError').classList.add('hidden');
    document.getElementById('orderDetails').classList.add('hidden');
    
    try {
        // Fetch order details from PHP backend
        const response = await fetch(`?action=getOrderDetails&id=${orderId}`);
        const data = await response.json();
        
        if (data.success) {
            currentOrderData = data.order;
            displayOrderDetails(data.order);
        } else {
            throw new Error(data.message || 'Failed to fetch order details');
        }
    } catch (error) {
        console.error('Error fetching order details:', error);
        showOrderError();
    }
}

// Function to display order details
function displayOrderDetails(order) {
    // Hide loading, show content
    document.getElementById('orderLoading').classList.add('hidden');
    document.getElementById('orderError').classList.add('hidden');
    document.getElementById('orderDetails').classList.remove('hidden');
    
    // Format date
    const orderDate = new Date(order.created_at).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
    
    // Format currency
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            minimumFractionDigits: 0
        }).format(amount);
    };
    
    // Get status color
    const getStatusColor = (status) => {
        const colors = {
            'pending': 'bg-yellow-100 text-yellow-700',
            'in progress': 'bg-blue-100 text-blue-700',
            'completed': 'bg-green-100 text-green-700',
            'cancelled': 'bg-red-100 text-red-700'
        };
        return colors[status] || 'bg-gray-100 text-gray-700';
    };
    
    // Get payment status color
    const getPaymentStatusColor = (status) => {
        const colors = {
            'pending': 'bg-red-100 text-red-700',
            'partial': 'bg-yellow-100 text-yellow-700',
            'completed': 'bg-green-100 text-green-700'
        };
        return colors[status] || 'bg-gray-100 text-gray-700';
    };
    
    // Build items HTML (compact version)
    let itemsHTML = '';
    if (order.items && order.items.length > 0) {
        order.items.forEach((item, index) => {
            itemsHTML += `
                <div class="border rounded p-2 mb-2 bg-gray-50">
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-medium text-gray-800 text-sm">${item.name || 'Item ' + (index + 1)}</span>
                        <span class="text-sm font-semibold text-blue-600">${formatCurrency(item.price || item.total_estimate)}</span>
                    </div>
                    
                    <div class="flex justify-between text-xs text-gray-600">
                        <div class="flex gap-3">
                            ${item.metal_type ? `<span><strong>Metal:</strong> ${item.metal_type}${item.purity ? ` (${item.purity})` : ''}</span>` : ''}
                            ${item.net_weight ? `<span><strong>Weight:</strong> ${item.net_weight}g</span>` : ''}
                        </div>
                        <div class="flex gap-3">
                            ${item.stone_type ? `<span><strong>Stone:</strong> ${item.stone_type}</span>` : ''}
                            ${item.quantity ? `<span><strong>Qty:</strong> ${item.quantity}</span>` : ''}
                        </div>
                    </div>
                    
                    ${item.design_customization ? `
                        <div class="mt-1 p-1 bg-white rounded text-xs">
                            <span class="font-medium text-gray-700">Note:</span>
                            <span class="text-gray-600">${item.design_customization}</span>
                        </div>
                    ` : ''}
                </div>
            `;
        });
    } else {
        itemsHTML = '<p class="text-gray-500 text-sm text-center py-2">No items found</p>';
    }
    
    // Populate the modal content (compact version)
    const orderDetailsHTML = `
        <!-- Order Header - Compact -->
        <div class="bg-blue-50 rounded p-3 mb-3">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold text-gray-800">${order.order_number}</h2>
                    <p class="text-xs text-gray-600">${orderDate}</p>
                </div>
                <span class="px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(order.order_status)}">
                    ${order.order_status.replace('_', ' ').toUpperCase()}
                </span>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
            
            <!-- Customer Info -->
            <div class="bg-gray-50 rounded p-3">
                <h3 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1">
                    <i class="fas fa-user text-blue-600 text-xs"></i>
                    Customer
                </h3>
                <p class="font-medium text-gray-800 text-sm">${order.customer_name || order.FirstName + ' ' + (order.LastName || '')}</p>
                <p class="text-xs text-gray-600 flex items-center gap-1">
                    <i class="fas fa-phone text-xs"></i>
                    ${order.PhoneNumber}
                </p>
            </div>

            <!-- Payment Summary - Compact -->
            <div class="bg-gray-50 rounded p-3">
                <h3 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1">
                    <i class="fas fa-credit-card text-blue-600 text-xs"></i>
                    Payment
                </h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total</span>
                        <span class="font-semibold text-gray-800">${formatCurrency(order.grand_total)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Paid</span>
                        <span class="text-green-600 font-medium">${formatCurrency(order.advance_amount || 0)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Balance</span>
                        <span class="text-red-600 font-medium">${formatCurrency(order.remaining_amount || 0)}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1 border-t border-gray-200">
                        <span class="text-gray-600">Status</span>
                        <span class="px-1 py-0.5 rounded text-xs font-medium ${getPaymentStatusColor(order.payment_status)}">
                            ${order.payment_status.toUpperCase()}
                        </span>
                    </div>
                    ${order.payment_method ? `
                        <div class="flex justify-between">
                            <span class="text-gray-600">Method</span>
                            <span class="font-medium text-gray-800">${order.payment_method.toUpperCase()}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>

        <!-- Order Items - Compact -->
        <div class="mb-3">
            <h3 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1">
                <i class="fas fa-gem text-blue-600 text-xs"></i>
                Items (${order.items ? order.items.length : 0})
            </h3>
            <div class="max-h-48 overflow-y-auto">
                ${itemsHTML}
            </div>
        </div>

        <!-- Cost Breakdown & Notes Row -->
        <div class="grid grid-cols-1 ${order.notes ? 'md:grid-cols-2' : ''} gap-3">
            
            <!-- Cost Breakdown -->
            <div class="bg-gray-50 rounded p-3">
                <h3 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1">
                    <i class="fas fa-calculator text-blue-600 text-xs"></i>
                    Breakdown
                </h3>
                <div class="space-y-1 text-xs">
                    ${order.total_metal_amount ? `
                        <div class="flex justify-between">
                            <span class="text-gray-600">Metal</span>
                            <span class="text-gray-800">${formatCurrency(order.total_metal_amount)}</span>
                        </div>
                    ` : ''}
                    ${order.total_making_charges ? `
                        <div class="flex justify-between">
                            <span class="text-gray-600">Making</span>
                            <span class="text-gray-800">${formatCurrency(order.total_making_charges)}</span>
                        </div>
                    ` : ''}
                    ${order.total_stone_amount ? `
                        <div class="flex justify-between">
                            <span class="text-gray-600">Stone</span>
                            <span class="text-gray-800">${formatCurrency(order.total_stone_amount)}</span>
                        </div>
                    ` : ''}
                    <div class="flex justify-between font-semibold pt-1 border-t border-gray-200">
                        <span class="text-gray-700">Total</span>
                        <span class="text-gray-900">${formatCurrency(order.grand_total)}</span>
                    </div>
                </div>
            </div>

            <!-- Notes (if available) -->
            ${order.notes ? `
                <div class="bg-yellow-50 rounded p-3">
                    <h3 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1">
                        <i class="fas fa-sticky-note text-blue-600 text-xs"></i>
                        Notes
                    </h3>
                    <p class="text-xs text-gray-700">${order.notes}</p>
                </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('orderDetails').innerHTML = orderDetailsHTML;
}

// Function to show error state
function showOrderError() {
    document.getElementById('orderLoading').classList.add('hidden');
    document.getElementById('orderError').classList.remove('hidden');
    document.getElementById('orderDetails').classList.add('hidden');
}

// Function to close modal
function closeOrderModal() {
    document.getElementById('orderDetailsModal').classList.add('hidden');
    currentOrderData = null;
}

// Function to print order
function printOrder() {
    if (!currentOrderData) {
        alert('No order data available to print');
        return;
    }
    
    // Create a print window with order details
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Order ${currentOrderData.order_number}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
                .header { text-align: center; margin-bottom: 20px; }
                .section { margin-bottom: 15px; }
                .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .items { border-collapse: collapse; width: 100%; font-size: 11px; }
                .items th, .items td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                .items th { background-color: #f2f2f2; }
                .summary { background-color: #f9f9f9; padding: 10px; }
                .total { font-weight: bold; }
                @media print { body { margin: 10px; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Order Details</h2>
                <h3>${currentOrderData.order_number}</h3>
                <p>Date: ${new Date(currentOrderData.created_at).toLocaleDateString()}</p>
            </div>
            
            <div class="grid">
                <div class="section">
                    <h4>Customer Information</h4>
                    <p><strong>Name:</strong> ${currentOrderData.customer_name}</p>
                    <p><strong>Phone:</strong> ${currentOrderData.PhoneNumber}</p>
                </div>
                
                <div class="section">
                    <h4>Payment Summary</h4>
                    <p><strong>Total:</strong> ₹${currentOrderData.grand_total}</p>
                    <p><strong>Paid:</strong> ₹${currentOrderData.advance_amount || 0}</p>
                    <p><strong>Balance:</strong> ₹${currentOrderData.remaining_amount || 0}</p>
                    <p><strong>Status:</strong> ${currentOrderData.payment_status.toUpperCase()}</p>
                </div>
            </div>
            
            <div class="section">
                <h4>Order Items</h4>
                <table class="items">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Metal</th>
                            <th>Weight</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${currentOrderData.items.map(item => `
                            <tr>
                                <td>${item.name}</td>
                                <td>${item.metal_type || 'N/A'}</td>
                                <td>${item.net_weight || 'N/A'}g</td>
                                <td>₹${item.price || item.total_estimate}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            
            <div class="summary">
                <h4>Order Summary</h4>
                <p><strong>Order Status:</strong> ${currentOrderData.order_status.toUpperCase()}</p>
                <p><strong>Payment Status:</strong> ${currentOrderData.payment_status.toUpperCase()}</p>
                <p class="total"><strong>Grand Total: ₹${currentOrderData.grand_total}</strong></p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Close modal when clicking outside
document.getElementById('orderDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeOrderModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeOrderModal();
    }
});

// Update the editOrder function in order.js
function editOrder(orderId) {
    openEditModal(orderId);
}
// Item editing state management
let editingItems = {};

function toggleItemEdit(index) {
    console.log('Toggling edit mode for item:', index);
    
    // Get the item container
    const itemContainer = document.querySelector(`#editOrderItems > div:nth-child(${index + 1})`);
    if (!itemContainer) {
        console.error('Item container not found for index:', index);
        return;
    }

    // Toggle edit state
    editingItems[index] = !editingItems[index];
    
    if (editingItems[index]) {
        // Switch to edit mode
        const item = editingOrder.items[index];
        const currentHTML = itemContainer.innerHTML;
        
        itemContainer.innerHTML = `
            <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3">
                    <h5 class="font-medium text-gray-800">Edit Item</h5>
                    <button onclick="toggleItemEdit(${index})" class="text-blue-600 hover:text-blue-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <!-- Item Name -->
                    <div class="col-span-2">
                        <label class="text-xs text-gray-600">Item Name</label>
                        <input type="text" 
                               id="editItemName_${index}" 
                               value="${item.item_name || ''}"
                               class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm">
                    </div>
                    
                    <!-- Karigar Selection -->
                    <div>
                        <label class="text-xs text-gray-600">Assigned Karigar</label>
                        <select id="editKarigar_${index}" 
                                class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm">
                            <option value="">Select Karigar</option>
                            <!-- Karigars will be populated via JavaScript -->
                        </select>
                    </div>
                    
                    <!-- Item Status -->
                    <div>
                        <label class="text-xs text-gray-600">Status</label>
                        <select id="editItemStatus_${index}" 
                                class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm">
                            <option value="pending" ${item.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="in-progress" ${item.status === 'in progress' ? 'selected' : ''}>In Progress</option>
                            <option value="completed" ${item.status === 'completed' ? 'selected' : ''}>Completed</option>
                        </select>
                    </div>

                    <!-- Weight -->
                    <div>
                        <label class="text-xs text-gray-600">Weight (g)</label>
                        <input type="number" 
                               id="editWeight_${index}" 
                               value="${item.net_weight || 0}"
                               step="0.001"
                               class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm">
                    </div>
                    
                    <!-- Making Charges -->
                    <div>
                        <label class="text-xs text-gray-600">Making Charges (₹)</label>
                        <input type="number" 
                               id="editMakingCharges_${index}" 
                               value="${item.making_charges || 0}"
                               class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm">
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-3">
                    <label class="text-xs text-gray-600">Notes</label>
                    <textarea id="editNotes_${index}" 
                              class="w-full px-3 py-2 rounded-lg border-2 border-gray-200 text-sm"
                              rows="2">${item.design_customization || ''}</textarea>
                </div>

                <!-- Save Button -->
                <div class="mt-3 flex justify-end">
                    <button onclick="saveItemEdit(${index})" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        Save Changes
                    </button>
                </div>
            </div>
        `;

        // Load and populate karigars
        loadKarigars().then(karigars => {
            const karigarSelect = document.getElementById(`editKarigar_${index}`);
            if (karigarSelect) {
                karigars.forEach(karigar => {
                    const option = new Option(karigar.name, karigar.id);
                    karigarSelect.add(option);
                });
                karigarSelect.value = item.karigar_id || '';
            }
        });

    } else {
        // Switch back to view mode - refresh the item display
        populateEditForm(editingOrder);
    }
}

function saveItemEdit(index) {
    console.log('Saving changes for item:', index);
    
    const item = editingOrder.items[index];
    if (!item) {
        console.error('Item not found for index:', index);
        showToast('Error: Item not found', 'error');
        return;
    }

    // Get all the edited values
    const itemName = document.getElementById(`editItemName_${index}`).value;
    const karigarId = document.getElementById(`editKarigar_${index}`).value;
    const status = document.getElementById(`editItemStatus_${index}`).value;
    const weight = parseFloat(document.getElementById(`editWeight_${index}`).value) || 0;
    const makingCharges = parseFloat(document.getElementById(`editMakingCharges_${index}`).value) || 0;
    const notes = document.getElementById(`editNotes_${index}`).value;

    // Update the item in editingOrder
    editingOrder.items[index] = {
        ...item,
        item_name: itemName,
        karigar_id: karigarId,
        status: status,
        net_weight: weight,
        making_charges: makingCharges,
        design_customization: notes
    };

    // Exit edit mode and refresh display
    editingItems[index] = false;
    populateEditForm(editingOrder);
    showToast('Item updated successfully');
}
// Add to order.js file
let editingOrder = null;

function openEditModal(orderId) {
    console.log('Opening edit modal for order:', orderId);
    const modal = document.getElementById('orderEditModal');
    modal.classList.remove('hidden');

    // Fetch order details
    fetch(`order.php?action=getOrderDetails&id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                editingOrder = data.order;
                populateEditForm(data.order);
            } else {
                showToast('Failed to load order details', 'error');
            }
        })
        .catch(error => {
            console.error('Error fetching order details:', error);
            showToast('Error loading order details', 'error');
        });
}

function populateEditForm(order) {
    // Set order status and priority
    document.getElementById('editOrderStatus').value = order.order_status;
    document.getElementById('editOrderPriority').value = order.priority;

    // Populate items
    const itemsContainer = document.getElementById('editOrderItems');
    itemsContainer.innerHTML = order.items.map((item, index) => `
        <div class="bg-white border rounded-lg p-4 shadow-sm">
            <div class="flex justify-between items-center mb-3">
                <h5 class="font-medium text-gray-800">${item.item_name}</h5>
                <button onclick="toggleItemEdit(${index})" class="text-blue-600 hover:text-blue-700">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <!-- Karigar Selection -->
                <div class="field-container">
                    <label class="text-xs text-gray-600">Assigned Karigar</label>
                    <select id="editKarigar_${index}" class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm">
                        <option value="">Select Karigar</option>
                        <!-- Karigars will be populated via JavaScript -->
                    </select>
                </div>
                
                <!-- Item Details -->
                <div class="field-container">
                    <label class="text-xs text-gray-600">Status</label>
                    <select id="editItemStatus_${index}" class="w-full h-9 px-3 rounded-lg border-2 border-gray-200 text-sm">
                        <option value="pending" ${item.status === 'pending' ? 'selected' : ''}>Pending</option>
                        <option value="in-progress" ${item.status === 'in progress' ? 'selected' : ''}>In Progress</option>
                        <option value="completed" ${item.status === 'completed' ? 'selected' : ''}>Completed</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-3 gap-4">
                <div class="text-sm">
                    <span class="text-gray-600">Weight:</span>
                    <span class="font-medium">${item.net_weight}g</span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-600">Making:</span>
                    <span class="font-medium">₹${item.making_charges}</span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-600">Total:</span>
                    <span class="font-medium">₹${item.total_estimate}</span>
                </div>
            </div>
        </div>
    `).join('');

    // Populate payment details
    document.getElementById('editTotalAmount').value = order.grand_total;
    document.getElementById('editAdvanceAmount').value = order.advance_amount;

    // Load and populate karigars for each item
    loadKarigars().then(karigars => {
        order.items.forEach((item, index) => {
            const karigarSelect = document.getElementById(`editKarigar_${index}`);
            if (karigarSelect) {
                karigars.forEach(karigar => {
                    const option = new Option(karigar.name, karigar.id);
                    karigarSelect.add(option);
                });
                karigarSelect.value = item.karigar_id || '';
            }
        });
    });
}

function loadKarigars() {
    return fetch('order.php?action=getKarigars')
        .then(response => response.json())
        .then(data => data.karigars)
        .catch(error => {
            console.error('Error loading karigars:', error);
            return [];
        });
}





function closeEditModal() {
    const modal = document.getElementById('orderEditModal');
    modal.classList.add('hidden');
    editingOrder = null;
}

function saveOrderChanges() {
    if (!editingOrder) {
        showToast('No order being edited', 'error');
        return;
    }

    const updatedOrder = {
        id: editingOrder.id,
        order_status: document.getElementById('editOrderStatus').value,
        priority: document.getElementById('editOrderPriority').value,
        advance_amount: parseFloat(document.getElementById('editAdvanceAmount').value),
        items: editingOrder.items.map((item, index) => ({
            id: item.id,
            karigar_id: document.getElementById(`editKarigar_${index}`).value,
            status: document.getElementById(`editItemStatus_${index}`).value
        }))
    };

    fetch('order.php?action=updateOrder', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(updatedOrder)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Order updated successfully');
            closeEditModal();
            loadOrders(); // Refresh the orders list
        } else {
            showToast(data.message || 'Failed to update order', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating order:', error);
        showToast('Error updating order', 'error');
    });
}
// Function to view order details

// Function to edit an order




</script>







</body>
</html>
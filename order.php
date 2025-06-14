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
        $sql = "INSERT INTO Customer (firm_id, FirstName, LastName, PhoneNumber, Email, Address, City, State, PostalCode, Country, IsGSTRegistered, GSTNumber, CreatedAt) 
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
       // Get the latest gold rate from jewellery_price_config
       $sql = "SELECT rate FROM jewellery_price_config 
               WHERE firm_id = ? AND material_type = 'Gold' AND purity = '99.99' 
               ORDER BY effective_date DESC LIMIT 1";
       
       $stmt = $conn->prepare($sql);
       $stmt->bind_param("i", $firm_id);
       $stmt->execute();
       $result = $stmt->get_result();
       
       if ($result && $result->num_rows > 0) {
           $row = $result->fetch_assoc();
           $rate = $row['rate'];
       } else {
           $rate = 7500; // Default rate if not found
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
    // Log incoming request
    error_log("Received updateOrder request");
    
    // Get JSON data
    $json_data = file_get_contents('php://input');
    error_log("updateOrder - Raw data: " . $json_data);
    
    // Decode JSON
    $orderData = json_decode($json_data, true);
    
    if (!$orderData) {
        error_log("updateOrder - JSON decode error: " . json_last_error_msg());
        echo json_encode(['success' => false, 'message' => 'Invalid data received']);
        exit;
    }

    error_log("updateOrder - Decoded data: " . print_r($orderData, true));

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

        $orderStatus = $orderData['order_status'];
        $priority = $orderData['priority'];
        $advanceAmount = $orderData['advance_amount'];
        $orderId = $orderData['id'];

        error_log("updateOrder - Main Order SQL: " . $updateOrderSql);
        error_log("updateOrder - Main Order Params: Status=" . $orderStatus . ", Priority=" . $priority . ", Advance=" . $advanceAmount . ", OrderID=" . $orderId . ", FirmID=" . $firm_id);

        $stmt->bind_param("ssddii", 
            $orderStatus,
            $priority,
            $advanceAmount,
            $advanceAmount, // Remaining amount is grand_total - advance_amount
            $orderId,
            $firm_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Order update execute failed: " . $stmt->error);
        }
        error_log("updateOrder - Main Order updated rows: " . $stmt->affected_rows);

        // Update each item
        foreach ($orderData['items'] as $item) {
            $updateItemSql = "UPDATE jewellery_order_items 
                             SET karigar_id = ?, 
                                 item_status = ?, 
                                 updated_at = NOW()
                             WHERE id = ? AND firm_id = ?";
            
            $stmt = $conn->prepare($updateItemSql);
            if (!$stmt) {
                throw new Exception("Item update prepare failed: " . $conn->error);
            }

            $karigarId = $item['karigar_id'] ?? null;
            $itemStatus = $item['status'] ?? null;
            $itemId = $item['id'];

            error_log("updateOrder - Item SQL: " . $updateItemSql);
            error_log("updateOrder - Item Params: KarigarID=" . ($karigarId ?? 'NULL') . ", ItemStatus=" . ($itemStatus ?? 'NULL') . ", ItemID=" . $itemId . ", FirmID=" . $firm_id);

            $stmt->bind_param("isii",
                $karigarId,
                $itemStatus,
                $itemId,
                $firm_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Item update execute failed: " . $stmt->error);
            }
            error_log("updateOrder - Item updated rows: " . $stmt->affected_rows);
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("updateOrder - Error: " . $e->getMessage());
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
                WHERE c.firm_id = ? AND (c.FirstName LIKE ? OR c.LastName LIKE ? OR c.PhoneNumber LIKE ?)
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
            $itemsSql = "SELECT oi.*, k.name as karigar_name 
                         FROM jewellery_order_items oi
                         LEFT JOIN karigars k ON oi.karigar_id = k.id
                         WHERE oi.order_id = ?";
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
    error_log("Searching karigars for firmID: " . $firm_id); // Add this line
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
    header('Content-Type: application/json'); // Ensure JSON header is always sent for this action

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
    if (!$checkStmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed for check: ' . $conn->error]);
        exit;
    }
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
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed for insert: ' . $conn->error]);
        exit;
    }
    // Corrected bind_param string: issssssssssdsss (15 characters)
    $stmt->bind_param("issssssssssdsss", $firm_id, $name, $phone, $alternate_phone, $email, $address_line1, 
                      $address_line2, $city, $state, $postal_code, $country, $default_making_charge, 
                      $charge_type, $gst_number, $pan_number);
    
    if ($stmt->execute()) {
        $karigar_id = $conn->insert_id;
        
        // Return the newly created karigar data
        $selectSql = "SELECT id, name, phone_number, alternate_phone, email, address_line1, address_line2, 
                             city, state, default_making_charge, charge_type FROM karigars WHERE id = ?";
        $selectStmt = $conn->prepare($selectSql);
        if (!$selectStmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed for select: ' . $conn->error]);
            exit;
        }
        $selectStmt->bind_param("i", $karigar_id);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $karigar = $result->fetch_assoc();
        
        echo json_encode(['success' => true, 'message' => 'Karigar added successfully', 'karigar' => $karigar]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add karigar: ' . $stmt->error]);
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
        <i class="fas fa-coins"></i> <span id="materialDetailsTitle">Material Details</span>
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
                <button onclick="closeOrderModal()" class="absolute right-4 top-4 text-white/80 hover:text-white">
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
                            class="w-full h-10 pr-4 rounded-lg border-2 border-blue-100 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 transition-all text-sm"
                            placeholder="Enter first name" required />
                    </div>
                </div>

                <!-- Last Name -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Last Name</label>
                    <div class="relative">
                        <input type="text" id="newCustomerLastName"
                            class="w-full h-10 pr-4 rounded-lg border-2 border-purple-100 focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 transition-all text-sm"
                            placeholder="Enter last name" />
                    </div>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Phone Number*</label>
                    <div class="relative">
                        <input type="tel" id="newCustomerPhone"
                            class="w-full h-10 pr-4 rounded-lg border-2 border-green-100 focus:border-green-500 focus:ring-4 focus:ring-green-500/20 transition-all text-sm"
                            placeholder="Enter phone number" required />
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <input type="email" id="newCustomerEmail"
                            class="w-full h-10 pr-4 rounded-lg border-2 border-amber-100 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/20 transition-all text-sm"
                            placeholder="Enter email address" />
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
                        class="w-full pr-4 py-2 rounded-lg border-2 border-teal-100 focus:border-teal-500 focus:ring-4 focus:ring-teal-500/20 transition-all text-sm"
                        placeholder="Enter full address"></textarea>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- City -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">City</label>
                    <div class="relative">
                        <input type="text" id="newCustomerCity"
                            class="w-full h-10 pr-4 rounded-lg border-2 border-indigo-100 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 transition-all text-sm"
                            placeholder="Enter city" />
                    </div>
                </div>

                <!-- State -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">State</label>
                    <div class="relative">
                        <input type="text" id="newCustomerState"
                            class="w-full h-10 pr-4 rounded-lg border-2 border-pink-100 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/20 transition-all text-sm"
                            placeholder="Enter state" />
                    </div>
                </div>

                <!-- Postal Code -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Postal Code</label>
                    <div class="relative">
                        <input type="text" id="newCustomerPostalCode"
                            class="w-full h-10 pr-4 rounded-lg border-2 border-cyan-100 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/20 transition-all text-sm"
                            placeholder="Enter postal code" />
                    </div>
                </div>

                <!-- GST Number -->
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">GST Number</label>
                    <div class="relative">
                        <input type="text" id="newCustomerGst"
                            class="w-full h-10 pr-4 rounded-lg border-2 border-orange-100 focus:border-orange-500 focus:ring-4 focus:ring-orange-500/20 transition-all text-sm"
                            placeholder="Enter GST number" />
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
</div>

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

<div id="karigarModal" class="modal">
    <div class="modal-content p-0 overflow-hidden max-w-lg w-11/12">
        <!-- Gradient Header -->
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="fas fa-user-tie text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-white text-lg font-semibold">Add New Karigar</h3>
                        <p class="text-white/80 text-sm">Enter karigar details</p>
                    </div>
                </div>
                <button onclick="closeKarigarModal()" class="text-white/80 hover:text-white">
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
            <button class="modal-tab flex-1 py-3 px-4 text-center relative transition-all" data-tab="work-details">
                <div class="flex items-center justify-center gap-2">
                    <i class="fas fa-hammer text-orange-500"></i>
                    <span class="font-medium text-sm">Work Details</span>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-orange-500 transform scale-x-0 transition-transform origin-left group-active:scale-x-100"></div>
            </button>
        </div>
        
        <!-- Basic Info Tab -->
        <div id="basic-info-content" class="modal-tab-content active p-4 space-y-4 max-h-[calc(100vh-250px)] overflow-y-auto">
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Name *</label>
                    <div class="relative">
                        <input type="text" id="newKarigarName"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-blue-100 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 transition-all text-sm"
                            placeholder="Enter karigar name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Phone Number *</label>
                    <div class="relative">
                        <input type="tel" id="newKarigarPhone"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-green-100 focus:border-green-500 focus:ring-4 focus:ring-green-500/20 transition-all text-sm"
                            placeholder="Enter phone number" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Alternate Phone</label>
                    <div class="relative">
                        <input type="tel" id="newKarigarAlternatePhone"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-purple-100 focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 transition-all text-sm"
                            placeholder="Enter alternate phone">
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                    <div class="relative">
                        <input type="email" id="newKarigarEmail"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-amber-100 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/20 transition-all text-sm"
                            placeholder="Enter email address">
                    </div>
                </div>
                <div class="form-group col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Address Line 1</label>
                    <div class="relative">
                        <input type="text" id="newKarigarAddressLine1"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-teal-100 focus:border-teal-500 focus:ring-4 focus:ring-teal-500/20 transition-all text-sm"
                            placeholder="Enter address line 1">
                    </div>
                </div>
                <div class="form-group col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Address Line 2</label>
                    <div class="relative">
                        <input type="text" id="newKarigarAddressLine2"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-cyan-100 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/20 transition-all text-sm"
                            placeholder="Enter address line 2">
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">City</label>
                    <div class="relative">
                        <input type="text" id="newKarigarCity"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-indigo-100 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 transition-all text-sm"
                            placeholder="Enter city">
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">State</label>
                    <div class="relative">
                        <input type="text" id="newKarigarState"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-pink-100 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/20 transition-all text-sm"
                            placeholder="Enter state">
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Postal Code</label>
                    <div class="relative">
                        <input type="text" id="newKarigarPostalCode"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-orange-100 focus:border-orange-500 focus:ring-4 focus:ring-orange-500/20 transition-all text-sm"
                            placeholder="Enter postal code">
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Country</label>
                    <div class="relative">
                        <input type="text" id="newKarigarCountry"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-gray-100 focus:border-gray-500 focus:ring-4 focus:ring-gray-500/20 transition-all text-sm"
                            placeholder="Enter country" value="India">
                    </div>
                </div>
            </div>
            <!-- Karigar Basic Info Actions -->
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeKarigarModal()"
                    class="px-4 py-2 rounded-lg border-2 border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-100 transition-all">
                    Cancel
                </button>
                <button type="button" onclick="saveKarigar()"
                    class="px-6 py-2 rounded-lg bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 text-white text-sm font-medium hover:opacity-90 transition-all focus:ring-4 focus:ring-blue-500/30 flex items-center gap-2">
                    <i class="fas fa-save"></i>
                    Save Karigar
                </button>
            </div>
        </div>
        
        <!-- Work Details Tab -->
        <div id="work-details-content" class="modal-tab-content p-4 space-y-4 hidden max-h-[calc(100vh-250px)] overflow-y-auto">
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Default Making Charge</label>
                    <div class="relative">
                        <input type="number" id="newKarigarMakingCharge"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-green-100 focus:border-green-500 focus:ring-4 focus:ring-green-500/20 transition-all text-sm"
                            placeholder="Enter making charge" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Charge Type</label>
                    <div class="relative">
                        <select id="newKarigarChargeType"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-orange-100 focus:border-orange-500 focus:ring-4 focus:ring-orange-500/20 transition-all text-sm appearance-none">
                            <option value="per_gram">Per Gram</option>
                            <option value="per_piece">Per Piece</option>
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">GST Number</label>
                    <div class="relative">
                        <input type="text" id="newKarigarGstNumber"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-blue-100 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 transition-all text-sm"
                            placeholder="Enter GST number">
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-medium text-gray-700 mb-1">PAN Number</label>
                    <div class="relative">
                        <input type="text" id="newKarigarPanNumber"
                            class="w-full h-10 pl-4 rounded-lg border-2 border-purple-100 focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 transition-all text-sm"
                            placeholder="Enter PAN number">
                    </div>
                </div>
            </div>
            <!-- Karigar Work Details Actions -->
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeKarigarModal()"
                    class="px-4 py-2 rounded-lg border-2 border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-100 transition-all">
                    Cancel
                </button>
                <button type="button" onclick="saveKarigar()"
                    class="px-6 py-2 rounded-lg bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 text-white text-sm font-medium hover:opacity-90 transition-all focus:ring-4 focus:ring-blue-500/30 flex items-center gap-2">
                    <i class="fas fa-save"></i>
                    Save Karigar
                </button>
            </div>
        </div>
        
        <div class="bg-gray-50 p-4 flex justify-end gap-3 border-t hidden"> <!-- Hidden footer -->
        </div>
    </div>
</div>
 <!-- Bottom Navigation -->
 <nav class="bottom-nav">
   <!-- Home -->
   <a href="home.php" class="nav-item">
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



 <script src="js/order.js"></script>





</body>
</html>
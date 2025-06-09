<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start session and include database config

error_log("POST data: " . print_r($_POST, true));

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
if ($conn->connect_error) {
   die("Connection failed: " . $conn->connect_error);
}

// Enhanced subscription status check
$subscriptionQuery = "SELECT fs.*, sp.name as plan_name, sp.price, sp.duration_in_days, sp.features 
                    FROM firm_subscriptions fs 
                    JOIN subscription_plans sp ON fs.plan_id = sp.id 
                    WHERE fs.firm_id = ? AND fs.is_active = 1 
                    ORDER BY fs.end_date DESC LIMIT 1";
$subStmt = $conn->prepare($subscriptionQuery);
$subStmt->bind_param("i", $firm_id);
$subStmt->execute();
$subscription = $subStmt->get_result()->fetch_assoc();

// Enhanced subscription status variables
$isTrialUser = false;
$isPremiumUser = false;
$isExpired = false;
$daysRemaining = 0;
$subscriptionStatus = 'none';

if ($subscription) {
    $endDate = new DateTime($subscription['end_date']);
    $now = new DateTime();
    $isExpired = $now > $endDate;
    $daysRemaining = max(0, $now->diff($endDate)->days);
    
    if ($subscription['is_trial']) {
        $isTrialUser = true;
        $subscriptionStatus = $isExpired ? 'trial_expired' : 'trial_active';
    } else {
        $isPremiumUser = true;
        $subscriptionStatus = $isExpired ? 'premium_expired' : 'premium_active';
    }
} else {
    $subscriptionStatus = 'no_subscription';
    $isExpired = true; // If no subscription found, consider it expired
}

// Feature access control
$hasFeatureAccess = ($isPremiumUser && !$isExpired) || ($isTrialUser && !$isExpired);

// Debug logging
error_log("Subscription Status: " . $subscriptionStatus);
error_log("Is Expired: " . ($isExpired ? 'true' : 'false'));
error_log("Has Feature Access: " . ($hasFeatureAccess ? 'true' : 'false'));

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

// API endpoints for AJAX requests
if (isset($_GET['action'])) {
   $action = $_GET['action'];
   
   // NEW: Get firm configuration
   if ($action == 'getFirmConfiguration') {
       $firm_id_param = isset($_GET['firm_id']) ? (int)$_GET['firm_id'] : $firm_id;
       
       $sql = "SELECT * FROM firm_configurations WHERE firm_id = ?";
       $stmt = $conn->prepare($sql);
       $stmt->bind_param("i", $firm_id_param);
       $stmt->execute();
       $result = $stmt->get_result();
       
       if ($result && $result->num_rows > 0) {
           $config = $result->fetch_assoc();
           header('Content-Type: application/json');
           echo json_encode([
               'success' => true,
               'non_gst_bill_page_url' => $config['non_gst_bill_page_url'],
               'gst_bill_page_url' => $config['gst_bill_page_url'],
               'coupon_code_apply_enabled' => (bool)$config['coupon_code_apply_enabled'],
               'schemes_enabled' => (bool)$config['schemes_enabled'],
               'gst_rate' => (float)$config['gst_rate'],
               'loyalty_discount_percentage' => (float)$config['loyalty_discount_percentage'],
               'welcome_coupon_enabled' => (bool)$config['welcome_coupon_enabled'],
               'welcome_coupon_code' => $config['welcome_coupon_code']
           ]);
       } else {
           // Return default configuration
           header('Content-Type: application/json');
           echo json_encode([
               'success' => true,
               'non_gst_bill_page_url' => 'thermal_invoice.php',
               'gst_bill_page_url' => 'thermal_invoice.php',
               'coupon_code_apply_enabled' => true,
               'schemes_enabled' => true,
               'gst_rate' => 0.03,
               'loyalty_discount_percentage' => 0.02,
               'welcome_coupon_enabled' => true,
               'welcome_coupon_code' => 'WELCOME10'
           ]);
       }
       exit;
   }
   
   // NEW: Validate customer coupon
   if ($action == 'validateCustomerCoupon') {
    // Debug incoming parameters
    error_log("Validating coupon with params: " . json_encode($_GET));
    
    $coupon_code = $_GET['coupon_code'] ?? '';
    $customer_id = (int)($_GET['customerId'] ?? 0);
    $is_gst = (int)($_GET['isGst'] ?? 0);
    
    // Debug processed values
    error_log("Processed values - code: $coupon_code, customer: $customer_id, gst: $is_gst");
    
    if (empty($coupon_code) || $customer_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid parameters',
            'debug' => [
                'coupon_code' => $coupon_code,
                'customer_id' => $customer_id,
                'is_gst' => $is_gst
            ]
        ]);
        exit;
    }

    try {
        // Make sure $firm_id is properly defined - add this if missing
        if (!isset($firm_id) || $firm_id <= 0) {
            // Get firm_id from session or another source
            $firm_id = $_SESSION['firm_id'] ?? 1; // Adjust based on your session management
        }
        
        // Check if coupon exists and is active
        $couponQuery = "SELECT c.*, fc.coupon_code_apply_enabled 
                       FROM coupons c
                       LEFT JOIN firm_configurations fc ON fc.firm_id = c.firm_id
                       WHERE c.coupon_code = ? AND c.firm_id = ? AND c.is_active = 1 
                       AND c.start_date <= NOW() AND c.expiry_date >= NOW()";
        $couponStmt = $conn->prepare($couponQuery);
        $couponStmt->bind_param("si", $coupon_code, $firm_id);
        $couponStmt->execute();
        $couponResult = $couponStmt->get_result();
        
        if ($couponResult->num_rows === 0) {
            // Add debug information
            error_log("Coupon validation failed for code: $coupon_code, firm_id: $firm_id");
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code']);
            exit;
        }
        
        $coupon = $couponResult->fetch_assoc();
        
        // Check if coupon functionality is enabled for this firm
        if (!$coupon['coupon_code_apply_enabled']) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Coupon functionality is disabled']);
            exit;
        }
        
        // Check GST applicability
        if ($coupon['gst_applicability'] !== 'any') {
            $required_gst = ($coupon['gst_applicability'] === 'gst_only') ? 1 : 0;
            if ($is_gst !== $required_gst) {
                $gst_msg = $required_gst ? 'GST bills only' : 'non-GST bills only';
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "This coupon is valid for {$gst_msg}"]);
                exit;
            }
        }
        
        // Check if customer has this coupon assigned and available
        $assignmentQuery = "SELECT * FROM customer_assigned_coupons 
                           WHERE customer_id = ? AND coupon_id = ? AND status = 'available'
                           AND times_used < ?";
        $assignmentStmt = $conn->prepare($assignmentQuery);
        $assignmentStmt->bind_param("iii", $customer_id, $coupon['id'], $coupon['usage_limit_customer']);
        $assignmentStmt->execute();
        $assignmentResult = $assignmentStmt->get_result();
        
        if ($assignmentResult->num_rows === 0) {
            // Add debug information
            error_log("Customer assignment failed for customer_id: $customer_id, coupon_id: {$coupon['id']}");
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Coupon not available for this customer or usage limit exceeded']);
            exit;
        }
        
        // Return valid coupon details - FIXED: Use correct field names
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'coupon' => [
                'id' => $coupon['id'],
                'code' => $coupon['coupon_code'], // FIXED: Use coupon_code instead of code
                'type' => $coupon['discount_type'],
                'value' => (float)$coupon['discount_value'],
                'description' => $coupon['description'] ?: 
                    ($coupon['discount_type'] === 'percentage' ? 
                        $coupon['discount_value'] . '% off' : 
                        '₹' . $coupon['discount_value'] . ' off')
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Coupon validation error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error validating coupon: ' . $e->getMessage()]);
    }
    exit;
}
   
   // Search customers
   if ($action == 'searchCustomers') {
       $search = $_GET['term'];
       $sql = "SELECT c.id, c.FirstName, c.LastName, c.PhoneNumber, c.Email, c.Address 
               FROM customer c
               WHERE c.firm_id = ? AND (c.FirstName LIKE ? OR c.LastName LIKE ? OR c.PhoneNumber LIKE ?)
               LIMIT 10";
       
       $stmt = $conn->prepare($sql);
       $searchTerm = "%$search%";
       $stmt->bind_param("isss", $firm_id, $searchTerm, $searchTerm, $searchTerm);
       $stmt->execute();
       $result = $stmt->get_result();
       
       $customers = [];
       while ($row = $result->fetch_assoc()) {
           // Get customer due amount
           $dueQuery = "SELECT SUM(due_amount) as total_due FROM jewellery_sales 
                        WHERE customer_id = ? AND payment_status = 'partial'";
           $dueStmt = $conn->prepare($dueQuery);
           $dueStmt->bind_param("i", $row['id']);
           $dueStmt->execute();
           $dueResult = $dueStmt->get_result();
           $dueRow = $dueResult->fetch_assoc();
           $row['due_amount'] = $dueRow['total_due'] ?: 0;
           
           // Get customer completed orders with advance payment
           $ordersQuery = "SELECT id, order_no, item_name, advance_amount, total_estimated, status 
                          FROM customer_orders 
                          WHERE customer_id = ? AND status = 'Ready' AND advance_amount > 0";
           $ordersStmt = $conn->prepare($ordersQuery);
           $ordersStmt->bind_param("i", $row['id']);
           $ordersStmt->execute();
           $ordersResult = $ordersStmt->get_result();
           
           $row['completedOrders'] = [];
           while ($orderRow = $ordersResult->fetch_assoc()) {
               $row['completedOrders'][] = $orderRow;
           }
           
           $customers[] = $row;
       }
       
       header('Content-Type: application/json');
       echo json_encode($customers);
       exit;
   }
   
   // Get customer advance payments
   if ($action == 'getCustomerAdvancePayments') {
       $customerId = isset($_GET['customerId']) ? (int)$_GET['customerId'] : 0;
       
       if (!$customerId) {
           header('Content-Type: application/json');
           echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
           exit;
       }
       
       // Get customer orders with available advance payment
       $sql = "SELECT co.id, co.order_no, co.item_name, co.advance_amount, 
                      co.advance_used, co.created_at,
                      (co.advance_amount - COALESCE(co.advance_used, 0)) as available_amount
               FROM customer_orders co
               WHERE co.customer_id = ? 
                 AND co.status = 'Ready' 
                 AND co.advance_amount > COALESCE(co.advance_used, 0)
               ORDER BY co.created_at DESC";
       
       $stmt = $conn->prepare($sql);
       $stmt->bind_param("i", $customerId);
       $stmt->execute();
       $result = $stmt->get_result();
       
       $advancePayments = [];
       while ($row = $result->fetch_assoc()) {
           $advancePayments[] = $row;
       }
       
       header('Content-Type: application/json');
       echo json_encode(['success' => true, 'advancePayments' => $advancePayments]);
       exit;
   }
   
   // Search products
   if ($action == 'searchProducts') {
       $search = $_GET['term'];
       $sql = "SELECT id, product_id, jewelry_type, product_name, material_type, purity, 
                      huid_code, gross_weight, less_weight, net_weight, stone_type, 
                      stone_weight, stone_price, making_charge, making_charge_type, status
               FROM jewellery_items 
               WHERE firm_id = ? AND (product_id LIKE ? OR jewelry_type LIKE ? OR product_name LIKE ? OR gross_weight LIKE ?)
               LIMIT 500";
       
       $stmt = $conn->prepare($sql);
       $searchTerm = "%$search%";
       $stmt->bind_param("issss", $firm_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
       $stmt->execute();
       $result = $stmt->get_result();
       
       $products = [];
       while ($row = $result->fetch_assoc()) {
           $products[] = $row;
       }
       
       header('Content-Type: application/json');
       echo json_encode($products);
       exit;
   }
   
   // Get product by QR code
   if ($action == 'getProductByQR') {
       $productCode = $_GET['code'];
       $sql = "SELECT id, product_id, jewelry_type, product_name, material_type, purity, 
                      huid_code, gross_weight, less_weight, net_weight, stone_type, 
                      stone_weight, stone_price, making_charge, making_charge_type
               FROM jewellery_items 
               WHERE firm_id = ? AND product_id = ?
               LIMIT 1";
       
       $stmt = $conn->prepare($sql);
       $stmt->bind_param("is", $firm_id, $productCode);
       $stmt->execute();
       $result = $stmt->get_result();
       
       if ($result->num_rows > 0) {
           $product = $result->fetch_assoc();
           header('Content-Type: application/json');
           echo json_encode(['success' => true, 'product' => $product]);
       } else {
           header('Content-Type: application/json');
           echo json_encode(['success' => false, 'message' => 'Product not found']);
       }
       exit;
   }
   
   // Get gold rate from jewellery_price_config
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
   
   // Enhanced add customer with welcome coupon support
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
       
       try {
           $conn->begin_transaction();
           
           // Insert new customer
           $sql = "INSERT INTO customer (firm_id, FirstName, LastName, PhoneNumber, Email, Address, City, State, PostalCode, Country, IsGSTRegistered, GSTNumber, CreatedAt) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
           
           $stmt = $conn->prepare($sql);
           $isGstRegistered = !empty($gst) ? 1 : 0;
           $stmt->bind_param("isssssssssis", $firm_id, $firstName, $lastName, $phone, $email, $address, $city, $state, $postalCode, $country, $isGstRegistered, $gst);
           
           if (!$stmt->execute()) {
               throw new Exception('Failed to add customer: ' . $stmt->error);
           }
           
           $customerId = $stmt->insert_id;
           
           // Check for welcome coupon configuration
           $configQuery = "SELECT welcome_coupon_enabled, welcome_coupon_code FROM firm_configurations WHERE firm_id = ?";
           $configStmt = $conn->prepare($configQuery);
           $configStmt->bind_param("i", $firm_id);
           $configStmt->execute();
           $configResult = $configStmt->get_result();
           
           $welcomeCouponMessage = null;
           
           if ($configResult->num_rows > 0) {
               $config = $configResult->fetch_assoc();
               
               if ($config['welcome_coupon_enabled'] && !empty($config['welcome_coupon_code'])) {
                   // Find the welcome coupon
                   $couponQuery = "SELECT id, coupon_code, description FROM coupons 
                                  WHERE firm_id = ? AND coupon_code = ? AND is_active = 1";
                   $couponStmt = $conn->prepare($couponQuery);
                   $couponStmt->bind_param("is", $firm_id, $config['welcome_coupon_code']);
                   $couponStmt->execute();
                   $couponResult = $couponStmt->get_result();
                   
                   if ($couponResult->num_rows > 0) {
                       $coupon = $couponResult->fetch_assoc();
                       
                       // Assign welcome coupon to customer
                       $assignQuery = "INSERT INTO customer_assigned_coupons 
                                      (customer_id, coupon_id, assigned_date, status, times_used, firm_id) 
                                      VALUES (?, ?, NOW(), 'available', 0, ?)";
                       $assignStmt = $conn->prepare($assignQuery);
                       $assignStmt->bind_param("iii", $customerId, $coupon['id'], $firm_id);
                       
                       if ($assignStmt->execute()) {
                           $welcomeCouponMessage = "Customer added successfully! Welcome coupon '{$coupon['coupon_code']}' has been assigned for their next purchase.";
                       }
                   }
               }
           }
           
           // Check for active schemes and auto-enter customer
           checkSchemeParticipationOnPurchase($conn, $customerId, $firm_id, 0, 0); // Call the correctly named function with dummy values, as the function checks purchase amount internally.
           
           $conn->commit();
           
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
           
           $response = ['success' => true, 'customer' => $customer];
           if ($welcomeCouponMessage) {
               $response['welcomeCouponMessage'] = $welcomeCouponMessage;
           }
           
           header('Content-Type: application/json');
           echo json_encode($response);
           
       } catch (Exception $e) {
           $conn->rollback();
           header('Content-Type: application/json');
           echo json_encode(['success' => false, 'message' => $e->getMessage()]);
       }
       exit;
   }
   
   // Add new jewelry item
   if ($action == 'addJewelryItem') {
       // Get POST data
       $productName = $_POST['productName'];
       $jewelryType = $_POST['jewelryType'];
       $materialType = $_POST['materialType'];
       $purity = $_POST['purity'];
       $huidCode = $_POST['huidCode'] ?? '';
       $grossWeight = $_POST['grossWeight'];
       $lessWeight = $_POST['lessWeight'] ?? 0;
       $netWeight = $_POST['netWeight'];
       $stoneType = $_POST['stoneType'] ?? 'None';
       $stoneWeight = $_POST['stoneWeight'] ?? 0;
       $stonePrice = $_POST['stonePrice'] ?? 0;
       $makingChargeType = $_POST['makingChargeType'];
       $makingCharge = $_POST['makingCharge'];
       
       // Generate a unique product ID
       $productId = 'JW' . date('ymd') . rand(1000, 9999);
       
       // Insert new jewelry item
       $sql = "INSERT INTO jewellery_items (
                   firm_id, product_id, jewelry_type, product_name, material_type, 
                   purity, huid_code, gross_weight, less_weight, net_weight, 
                   stone_type, stone_weight, stone_price, making_charge, making_charge_type, 
                   supplier_id, created_at
               ) VALUES (
                   ?, ?, ?, ?, ?, 
                   ?, ?, ?, ?, ?, 
                   ?, ?, ?, ?, ?, 
                   1, NOW()
               )";
       
       $stmt = $conn->prepare($sql);
       $stmt->bind_param(
           "isssssddddsddss",
           $firm_id, $productId, $jewelryType, $productName, $materialType,
           $purity, $huidCode, $grossWeight, $lessWeight, $netWeight,
           $stoneType, $stoneWeight, $stonePrice, $makingCharge, $makingChargeType
       );
       
       if ($stmt->execute()) {
           $jewelryId = $stmt->insert_id;
           $jewelry = [
               'id' => $jewelryId,
               'product_id' => $productId,
               'product_name' => $productName,
               'jewelry_type' => $jewelryType,
               'material_type' => $materialType,
               'purity' => $purity,
               'huid_code' => $huidCode,
               'gross_weight' => $grossWeight,
               'less_weight' => $lessWeight,
               'net_weight' => $netWeight,
               'stone_type' => $stoneType,
               'stone_weight' => $stoneWeight,
               'stone_price' => $stonePrice,
               'making_charge' => $makingCharge,
               'making_charge_type' => $makingChargeType
           ];
           header('Content-Type: application/json');
           echo json_encode(['success' => true, 'jewelry' => $jewelry]);
       } else {
           header('Content-Type: application/json');
           echo json_encode(['success' => false, 'message' => 'Failed to add jewelry item: ' . $stmt->error]);
       }
       exit;
   }
   
   // Save URD details
   if ($action == 'saveUrdDetails') {
       // Get POST data
       $data = json_decode(file_get_contents('php://input'), true);
       
       if (!$data) {
           header('Content-Type: application/json');
           echo json_encode(['success' => false, 'message' => 'Invalid data received']);
           exit;
       }
       
       // Insert URD details
       $sql = "INSERT INTO urd_items (
                   firm_id, user_id, customer_id, sale_id, item_name, 
                   gross_weight, less_weight, net_weight, purity, rate, 
                   fine_weight, total_amount, image_data, received_date, 
                   status, process_type, notes, created_at
               ) VALUES (
                   ?, ?, ?, ?, ?, 
                   ?, ?, ?, ?, ?, 
                   ?, ?, ?, NOW(), 
                   'Received', 'Sale', ?, NOW()
               )";
       
       $stmt = $conn->prepare($sql);
       $stmt->bind_param(
           "iiiisddddddsss",
           $firm_id, $user_id, $data['customerId'], $data['saleId'], $data['itemName'],
           $data['grossWeight'], $data['lessWeight'], $data['netWeight'], $data['purity'], $data['rate'],
           $data['fineWeight'], $data['totalAmount'], $data['imageData'], $data['notes']
       );
       
       if ($stmt->execute()) {
           $urdId = $stmt->insert_id;
           header('Content-Type: application/json');
           echo json_encode(['success' => true, 'urdId' => $urdId]);
       } else {
           header('Content-Type: application/json');
           echo json_encode(['success' => false, 'message' => 'Failed to save URD details: ' . $stmt->error]);
       }
       exit;
   }
   
   // Get last invoice number based on GST status
   if ($action == 'getLastInvoiceNumber') {
       $isGstApplicable = isset($_GET['gst']) ? (int)$_GET['gst'] : 0;
       
       if ($isGstApplicable) {
           // Get last invoice with GST applicable
           $sql = "SELECT invoice_no FROM jewellery_sales 
                   WHERE is_gst_applicable = 1 
                   ORDER BY id DESC LIMIT 1";
       } else {
           // Get last invoice without GST
           $sql = "SELECT invoice_no FROM jewellery_sales 
                   WHERE is_gst_applicable = 0 
                   ORDER BY id DESC LIMIT 1";
       }
       
       $result = $conn->query($sql);
       $invoiceNo = '';
       
       if ($result && $result->num_rows > 0) {
           $invoiceNo = $result->fetch_assoc()['invoice_no'];
       }
       
       header('Content-Type: application/json');
       echo json_encode(['invoiceNo' => $invoiceNo]);
       exit;
   }
   
   // NEW: Get active schemes for customer
  if ($action == 'getActiveSchemes') {
    $sql = "SELECT s.*, 
                   (SELECT COUNT(*) FROM scheme_entries WHERE scheme_id = s.id) as total_entries
            FROM schemes s
            WHERE s.firm_id = ? AND s.status = 'active' 
            AND s.start_date <= NOW() AND s.end_date >= NOW()
            ORDER BY s.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $firm_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $schemes = [];
    while ($row = $result->fetch_assoc()) {
        $schemes[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'schemes' => $schemes]);
    exit;
}
   // Process checkout - Enhanced with scheme participation
   if ($action == 'processCheckout') {
       // Set content type to JSON at the beginning
       header('Content-Type: application/json');
       
       try {
           // Get session variables or set defaults
           $firm_id = $_SESSION['firm_id'] ?? 1;
           $user_id = $_SESSION['user_id'] ?? 1;
           
           // Start transaction
           $conn->begin_transaction();
           
           // Get POST data and decode JSON
           $rawData = file_get_contents('php://input');
           $data = json_decode($rawData, true);
           
           if (json_last_error() !== JSON_ERROR_NONE) {
               throw new Exception('Invalid JSON data received: ' . json_last_error_msg());
           }

           // Get invoice number from data
           $invoiceNo = $data['invoiceNo'];
           $isGstApplicable = $data['isGstApplicable'] ? 1 : 0;
           
           // Calculate total paid amount from payment methods (excluding advance payments)
           $totalPaidAmount = 0;
           $regularPayments = 0;
           
           foreach ($data['paymentMethods'] as $payment) {
               $totalPaidAmount += floatval($payment['amount']);
               
               // Count regular payments separately (non-advance)
               if ($payment['type'] !== 'advance_adjustment') {
                   $regularPayments += floatval($payment['amount']);
               }
           }
           
           $dueAmount = $data['grandTotal'] - $totalPaidAmount;
           
           // Extract discount information
           $couponDiscount = isset($data['couponDiscount']) ? $data['couponDiscount'] : 0.00;
           $loyaltyDiscount = isset($data['loyaltyDiscount']) ? $data['loyaltyDiscount'] : 0.00;
           $manualDiscount = isset($data['manualDiscount']) ? $data['manualDiscount'] : 0.00;
           $couponCode = isset($data['couponCode']) ? $data['couponCode'] : null;
           
           // Calculate advance payment amount correctly
           $advancePaymentAmount = 0;
           $advancePaymentOrders = []; // Store advance payment order IDs and amounts
           
           foreach ($data['paymentMethods'] as $payment) {
               if ($payment['type'] === 'advance_adjustment') {
                   $advancePaymentAmount += floatval($payment['amount']);
                   if (!empty($payment['orderId'])) {
                       $advancePaymentOrders[] = [
                           'id' => $payment['orderId'],
                           'amount' => floatval($payment['amount'])
                       ];
                   }
               }
           }
           
           // Determine payment status
           if ($totalPaidAmount <= 0) {
               $paymentStatus = 'Unpaid';
           } elseif ($dueAmount > 0) {
               $paymentStatus = 'Partial';
           } elseif ($dueAmount < 0) {
               $paymentStatus = 'Overpaid';
               $dueAmount = 0; // Avoid negative due
           } else {
               $paymentStatus = 'Paid';
           }
           
           // Convert payment methods to JSON for storage
           $paymentMethodsJson = json_encode($data['paymentMethods']);
           
           // Calculate GST amount only if GST is applicable
           $gstAmount = 0.00; // Default to 0
           if ($isGstApplicable == 1) {
               // Get firm's GST rate
               $gstRateQuery = "SELECT gst_rate FROM firm_configurations WHERE firm_id = ?";
               $gstRateStmt = $conn->prepare($gstRateQuery);
               $gstRateStmt->bind_param("i", $firm_id);
               $gstRateStmt->execute();
               $gstRateResult = $gstRateStmt->get_result();
               
               $gstRate = 0.03; // Default
               if ($gstRateResult->num_rows > 0) {
                   $gstRate = floatval($gstRateResult->fetch_assoc()['gst_rate']);
               }
               
               $gstAmount = round($data['subtotal'] * $gstRate, 2);
           }

           // Insert sale record with the correct advance_amount
           $sql = "INSERT INTO jewellery_sales (
               invoice_no, firm_id, customer_id, sale_date, 
               total_metal_amount, total_stone_amount, total_making_charges, 
               total_other_charges, discount, urd_amount, subtotal, 
               gst_amount, grand_total, payment_status, 
               is_gst_applicable, notes, total_paid_amount, due_amount,
               payment_method, user_id, coupon_discount, loyalty_discount,
               manual_discount, coupon_code, advance_amount
           ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";        

           $stmt = $conn->prepare($sql);
           if (!$stmt) {
               throw new Exception('Error preparing statement: ' . $conn->error);
           }

           $stmt->bind_param(
               "siiddddddddssssissdddssi",
               $invoiceNo,
               $firm_id,
               $data['customerId'],
               $data['totalMetal'],
               $data['totalStone'],
               $data['totalMaking'],
               $data['totalOther'],
               $data['discount'],
               $data['urdAmount'],
               $data['subtotal'],
               $gstAmount,
               $data['grandTotal'],
               $paymentStatus,
               $isGstApplicable,
               $data['notes'],
               $regularPayments,
               $dueAmount,
               $paymentMethodsJson,
               $user_id,
               $couponDiscount,
               $loyaltyDiscount,
               $manualDiscount,
               $couponCode,
               $advancePaymentAmount
           );
           
           if (!$stmt->execute()) {
               throw new Exception('Error executing sales insert: ' . $stmt->error);
           }
           $saleId = $stmt->insert_id;
           
           // Insert sale items
           $itemSql = "INSERT INTO Jewellery_sales_items (
               sale_id, product_id, product_name, huid_code,
               rate_24k, purity, purity_rate, gross_weight,
               less_weight, net_weight, metal_amount, stone_type,
               stone_weight, stone_price, making_type, making_rate,
               making_charges, hm_charges, other_charges, total_charges,
               total
           ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

           $itemStmt = $conn->prepare($itemSql);
           if (!$itemStmt) {
               throw new Exception('Error preparing item statement: ' . $conn->error);
           }

           foreach ($data['items'] as $item) {
               $itemStmt->bind_param(
                   "iissdddddddsddsdddddd",
                   $saleId,
                   $item['productId'],
                   $item['productName'],
                   $item['huidCode'],
                   $item['rate24k'],
                   $item['purity'],
                   $item['purityRate'],
                   $item['grossWeight'],
                   $item['lessWeight'],
                   $item['netWeight'],
                   $item['metalAmount'],
                   $item['stoneType'],
                   $item['stoneWeight'],
                   $item['stonePrice'],
                   $item['makingType'],
                   $item['makingRate'],
                   $item['makingCharges'],
                   $item['hmCharges'],
                   $item['otherCharges'],
                   $item['totalCharges'],
                   $item['total']
               );
               
               if (!$itemStmt->execute()) {
                   throw new Exception('Error executing item insert: ' . $itemStmt->error);
               }

               // Update item status to 'Sold'
               $updateItemSql = "UPDATE jewellery_items SET status = 'Sold' WHERE id = ?";
               $updateItemStmt = $conn->prepare($updateItemSql);
               if (!$updateItemStmt) {
                   throw new Exception('Error preparing update statement: ' . $conn->error);
               }
               
               $updateItemStmt->bind_param("i", $item['productId']);
               $updateItemStmt->execute();
           }
           
           // Insert payment records
           $paymentSql = "INSERT INTO jewellery_payments (
               sale_id, payment_type, amount, reference_no, 
               reference_id, party_type, reference_type, party_id, remarks, 
               created_at, transctions_type
           ) VALUES (?, ?, ?, ?, ?, 'customer', 'sale', ?, ?, NOW(), 'Credit')";

           $paymentStmt = $conn->prepare($paymentSql);
           if (!$paymentStmt) {
               throw new Exception('Error preparing payment statement: ' . $conn->error);
           }

           foreach ($data['paymentMethods'] as $payment) {
               $paymentType = $payment['type'];
               $paymentAmount = floatval($payment['amount']);
               $referenceNo = isset($payment['reference']) ? $payment['reference'] : '';
               $remarks = isset($payment['notes']) ? $payment['notes'] : '';
               
               // Skip zero amount payments
               if ($paymentAmount <= 0) {
                   continue;
               }
               
               $paymentStmt->bind_param(
                   "isdsiss",
                   $saleId,
                   $paymentType,
                   $paymentAmount,
                   $referenceNo,
                   $saleId,
                   $data['customerId'],
                   $remarks
               );
               
               if (!$paymentStmt->execute()) {
                   throw new Exception('Error executing payment insert: ' . $paymentStmt->error);
               }
               
               // If this is an advance payment adjustment, update the customer order
               if ($paymentType === 'advance_adjustment' && !empty($payment['orderId'])) {
                   $updateOrderSql = "UPDATE customer_orders 
                                     SET advance_used = COALESCE(advance_used, 0) + ?, 
                                         advance_used_date = NOW(),
                                         status = 'Delivered'
                                     WHERE id = ?";
                                     
                   $updateOrderStmt = $conn->prepare($updateOrderSql);
                   if (!$updateOrderStmt) {
                       throw new Exception('Error preparing order update: ' . $conn->error);
                   }
                   
                   $updateOrderStmt->bind_param("di", $paymentAmount, $payment['orderId']);
                   $result = $updateOrderStmt->execute();
                   
                   if (!$result) {
                       throw new Exception('Error updating order: ' . $updateOrderStmt->error);
                   }
               }
           }
           
           // Save URD details if provided
           if (!empty($data['urdDetails'])) {
               $urdSql = "INSERT INTO urd_items (
                   firm_id, user_id, customer_id, sale_id, item_name, 
                   gross_weight, less_weight, net_weight, purity, rate, 
                   fine_weight, total_amount, image_data, received_date, 
                   status, process_type, notes, created_at
               ) VALUES (
                   ?, ?, ?, ?, ?, 
                   ?, ?, ?, ?, ?, 
                   ?, ?, ?, NOW(), 
                   'Received', 'Sale', ?, NOW()
               )";
               
               $urdStmt = $conn->prepare($urdSql);
               if (!$urdStmt) {
                   throw new Exception('Error preparing URD statement: ' . $conn->error);
               }
               
               $urdDetails = $data['urdDetails'];
               
               $urdStmt->bind_param(
                   "iiiisddddddsss",
                   $firm_id, $user_id, $data['customerId'], $saleId, $urdDetails['itemName'],
                   $urdDetails['grossWeight'], $urdDetails['lessWeight'], $urdDetails['netWeight'], 
                   $urdDetails['purity'], $urdDetails['rate'], $urdDetails['fineWeight'], 
                   $urdDetails['totalAmount'], $urdDetails['imageData'], $urdDetails['notes']
               );
               
               if (!$urdStmt->execute()) {
                   throw new Exception('Error executing URD insert: ' . $urdStmt->error);
               }
           }
           
           // Mark coupon as used if applied
           if (!empty($couponCode) && $couponDiscount > 0) {
               $updateCouponQuery = "UPDATE customer_assigned_coupons 
                                    SET times_used = times_used + 1,   last_used_date = NOW()
                                    WHERE customer_id = ? AND coupon_id = (
                                        SELECT id FROM coupons WHERE coupon_code  = ? AND firm_id = ?
                                    )";
               $updateCouponStmt = $conn->prepare($updateCouponQuery);
               $updateCouponStmt->bind_param("isi", $data['customerId'], $couponCode, $firm_id);
               $updateCouponStmt->execute();
           }
           
           // Check for scheme participation based on purchase amount
           $schemeEntries = checkSchemeParticipationOnPurchase($conn, $data['customerId'], $firm_id, $data['grandTotal'], $saleId);
               
           // Commit transaction
           $conn->commit();
           
           // Return success response
           echo json_encode([
               'success' => true,
               'message' => 'Sale completed successfully',
               'invoiceNo' => $invoiceNo,
               'saleId' => $saleId,
               'advanceAmount' => $advancePaymentAmount,
               'regularPayment' => $regularPayments,
               'schemeEntries' => $schemeEntries
           ]);
           
       } catch (Exception $e) {
           // Rollback on error
           if ($conn && $conn->connect_errno == 0) {
               $conn->rollback();
           }
           
           // Log the error
           file_put_contents('sale_error_log.txt', 
               date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n" . 
               "Raw data: " . (isset($rawData) ? $rawData : 'Not available') . "\n", 
               FILE_APPEND);
           
           // Send error response
           echo json_encode([
               'success' => false,
               'message' => 'Error processing sale: ' . $e->getMessage()
           ]);
       }
       exit;
   }
   
   // NEW: Get customer coupons
   if ($action == 'getCustomerCoupons') {
       $customerId = isset($_GET['customerId']) ? (int)$_GET['customerId'] : 0;
       
       if (!$customerId) {
           echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
           exit;
       }

       try {
           // Get customer's assigned coupons
           $sql = "SELECT c.coupon_code as code, c.description, c.discount_type, 
                       c.discount_value, cac.times_used, c.usage_limit_customer
                FROM customer_assigned_coupons cac
                JOIN coupons c ON c.id = cac.coupon_id
                WHERE cac.customer_id = ? 
                AND cac.status = 'available'
                AND c.is_active = 1
                AND c.start_date <= NOW()
                AND c.expiry_date >= NOW()
                AND cac.times_used < c.usage_limit_customer";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $coupons = [];
        while ($row = $result->fetch_assoc()) {
            $coupons[] = [
                'code' => $row['code'],
                'description' => $row['description'] ?: 
                    ($row['discount_type'] === 'percentage' ? 
                        $row['discount_value'] . '% off' : 
                        '₹' . $row['discount_value'] . ' off'),
                'type' => $row['discount_type'],
                'value' => $row['discount_value'],
                'usageLeft' => $row['usage_limit_customer'] - $row['times_used']
            ];
        }

        echo json_encode(['success' => true, 'coupons' => $coupons]);
        
       } catch (Exception $e) {
           echo json_encode(['success' => false, 'message' => $e->getMessage()]);
       }
       exit;
   }
}


function checkSchemeParticipationOnPurchase($conn, $customerId, $firmId, $purchaseAmount, $saleId) {
    try {
        error_log("DEBUG: checkSchemeParticipationOnPurchase called with customerId=$customerId, firmId=$firmId, purchaseAmount=$purchaseAmount, saleId=$saleId");
        $schemeQuery = "SELECT s.id, s.scheme_name as title, s.min_purchase_amount, s.auto_entry_on_purchase, s.status, s.start_date, s.end_date
                       FROM schemes s 
                       WHERE s.firm_id = ? 
                       AND s.status = 'active'
                       AND s.auto_entry_on_purchase = 1
                       AND s.min_purchase_amount <= ?
                       AND s.start_date <= NOW() 
                       AND s.end_date >= NOW()
                       AND NOT EXISTS (
                           SELECT 1 FROM scheme_entries se 
                           WHERE se.scheme_id = s.id 
                           AND se.customer_id = ?
                       )";
        $stmt = $conn->prepare($schemeQuery);
        $stmt->bind_param("idi", $firmId, $purchaseAmount, $customerId);
        $stmt->execute();
        $result = $stmt->get_result();

        $schemeEntries = [];
        $numRows = $result->num_rows;
        error_log("DEBUG: Scheme check: found $numRows eligible schemes for customer $customerId, firm $firmId, amount $purchaseAmount");

        while ($scheme = $result->fetch_assoc()) {
            error_log("DEBUG: Eligible scheme found: " . json_encode($scheme));
            $entryQuery = "INSERT INTO scheme_entries 
                          (scheme_id, customer_id, entry_date, status, purchase_amount, sale_id) 
                          VALUES (?, ?, NOW(), 'active', ?, ?)";
            $entryStmt = $conn->prepare($entryQuery);
            $entryStmt->bind_param("iidi", $scheme['id'], $customerId, $purchaseAmount, $saleId);

            if ($entryStmt->execute()) {
                error_log("DEBUG: Scheme entry inserted: scheme_id={$scheme['id']}, customer_id=$customerId, sale_id=$saleId");
                $schemeEntries[] = [
                    'scheme_id' => $scheme['id'],
                    'scheme_title' => $scheme['title']
                ];
            } else {
                error_log("ERROR: Failed to insert scheme entry: " . $entryStmt->error);
            }
        }

        if (empty($schemeEntries)) {
            error_log("DEBUG: No scheme entries created for customer $customerId, sale $saleId.");
        }

        return $schemeEntries;

    } catch (Exception $e) {
        error_log("ERROR: Exception in scheme participation: " . $e->getMessage());
        return [];
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
 <link rel="stylesheet" href="css/sale.css">

 
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
 <div class="bg-pink-50 p-2 m-2 rounded-md shadow-sm">
   <div class="grid grid-cols-2 gap-2">
     <!-- Customer Search -->
     <div class="field-container">
       <input type="text" 
         id="customerName" 
         class="input-field text-xs font-medium h-8 pl-7 pr-8 w-full bg-white border border-gray-200 rounded-md" 
         placeholder="Search customer..." />
       <i class="fas fa-user field-icon text-blue-500"></i>
       <button class="camera-btn" onclick="showCustomerModal()">
         <i class="fas fa-plus"></i>
       </button>
       <div id="customerDropdown" class="customer-dropdown">
         <!-- Customer list will appear here -->
       </div>
     </div>

     <!-- Product Search -->
     <div class="field-container">
       <input type="text" 
         id="productSearch" 
         class="input-field text-xs font-medium h-8 pl-7 pr-8 w-full bg-white border border-gray-200 rounded-md" 
         placeholder="Search product..." />
       <i class="fas fa-search field-icon text-blue-500"></i>
       <button class="camera-btn" onclick="openQRScanner()">
         <i class="fas fa-camera"></i>
       </button>
       <div id="productDropdown" class="product-dropdown">
         <!-- Product list will appear here -->
       </div>
     </div>
   </div>
   
   <!-- Selection Details -->
   <div id="selectionDetails" class="selection-details hidden">
     <!-- Selection details will appear here -->
   </div>
   
   <!-- New Product Button -->
   <div class="mt-2 flex justify-end">
     <button id="newProductBtn" onclick="toggleNewProductMode()" class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-md text-xs font-medium flex items-center gap-1">
       <i class="fas fa-plus-circle"></i>
       <span>New Product</span>
     </button>
   </div>
 </div>

 <!-- Entry Form Tab -->
 <div id="entry-form" class="tab-content active">
   <div class="p-1 compact-form">
     <!-- New Product Indicator -->
     <div id="newProductIndicator" class="new-product-mode hidden">
       <div class="new-product-badge">
         <i class="fas fa-exclamation-circle"></i>
         <span>New Product Entry Mode</span>
       </div>
       
       <div class="field-row mb-2">
         <div class="field-col">
           <div class="field-label">Jewelry Type</div>
           <div class="field-container">
             <select id="jewelryType" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-red-200 rounded-md">
               <option value="">Select Type</option>
               <option value="Ring">Ring</option>
               <option value="Necklace">Necklace</option>
               <option value="Bracelet">Bracelet</option>
               <option value="Earring">Earring</option>
               <option value="Pendant">Pendant</option>
               <option value="Bangle">Bangle</option>
               <option value="Chain">Chain</option>
             </select>
             <i class="fas fa-list field-icon text-red-500"></i>
           </div>
         </div>
         
         <div class="field-col">
           <div class="field-label">Material Type</div>
           <div class="field-container">
             <select id="materialType" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-red-200 rounded-md">
               <option value="">Select Material</option>
               <option value="Gold">Gold</option>
               <option value="Silver">Silver</Silver>
               <option value="Platinum">Platinum</option>
               <option value="Diamond">Diamond</option>
             </select>
             <i class="fas fa-gem field-icon text-red-500"></i>
           </div>
         </div>
       </div>
     </div>
     
     <!-- Material Details Section -->
     <div class="section-card material-section bg">
       <div class="section-title text-amber-800">
         <i class="fas fa-coins y-3"></i> Material Details
       </div>
      
       <div class="field-row">
         <div class="field-col">
           <div class="field-label">Product Name</div>
           <div class="field-container">
             <input type="text" id="productName" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-gray-200 rounded-md" placeholder="name" />
             <i class="fas fa-tag field-icon text-blue-500"></i>
           </div>
         </div>
         
         <div class="field-col">
           <div class="field-label">HUID</div>
           <div class="field-container">
             <input type="text" id="huidCode" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="HUID (Optional)" />
             <i class="fas fa-fingerprint field-icon text-blue-500"></i>
           </div>
         </div>
       </div>
       
       <div class="field-row">
         <div class="field-col">
           <div class="field-label">24K Rate (₹/g)</div>
           <div class="field-container">
             <input type="number" id="rate24k" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md" placeholder="24K rate" value="9810" step="1" />
             <i class="fas fa-rupee-sign field-icon text-amber-500"></i>
           </div>
         </div>
         
         <div class="field-col">
           <div class="field-label">Purity</div>
           <div class="field-container">
             <input type="number" 
               id="purity" 
               class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md" 
               placeholder="e.g. 92.0" 
               value="0"
               step="0.1" 
               min="0" 
               max="100" />
             <i class="fas fa-certificate field-icon text-amber-500"></i>
           </div>
         </div>
         
         <div class="field-col">
           <div class="field-label">Purity Rate (₹/g)</div>
           <div class="field-container">
             <input type="number" id="purityRate" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-amber-200 rounded-md" placeholder="Calculated rate" value="8986" readonly />
             <i class="fas fa-rupee-sign field-icon text-amber-500"></i>
           </div>
         </div>
       </div>
     </div>

     <!-- Weight Details Section -->
     <div class="section-card weight-section">
       <div class="section-title text-blue-800">
         <i class="fas fa-weight-scale"></i> Weight Details
       </div>
       <div class="field-row">
         <div class="field-col">
           <div class="field-label">Gross Weight (g)</div>
           <div class="field-container">
             <input type="number" id="grossWeight" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="Gross wt" step="0.01" value="0" />
             <i class="fas fa-weight-scale field-icon text-blue-500"></i>
           </div>
         </div>
         <div class="field-col">
           <div class="field-label">Less Weight (g)</div>
           <div class="field-container">
             <input type="number" id="lessWeight" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="Less wt" step="0.01" value="0" />
             <i class="fas fa-minus-circle field-icon text-red-500"></i>
           </div>
         </div>
         <div class="field-col">
           <div class="field-label">Net Weight (g)</div>
           <div class="field-container">
             <input type="number" id="netWeight" class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" placeholder="Net wt" step="0.01" value="0" readonly />
             <i class="fas fa-balance-scale field-icon text-green-500"></i>
           </div>
         </div>
         <div class="field-col">
           <div class="field-label">Metal Amount (₹)</div>
           <div class="field-container">
             <input type="number" id="metalAmount" 
               class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-blue-200 rounded-md" 
               placeholder="Metal amount" value="0" readonly />
             <i class="fas fa-rupee-sign field-icon text-blue-500"></i>
           </div>
         </div>
       </div>
     </div>

     <!-- Stone Details Section -->
     <div class="section-card stone-section">
       <div class="section-title text-purple-800">
         <i class="fas fa-gem"></i> Stone Details
       </div>
       <div class="field-row">
         <div class="field-col">
           <div class="field-label">Stone Type</div>
           <div class="field-container">
             <select id="stoneType" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-purple-200 rounded-md">
               <option value="None">None</option>
               <option value="Diamond" selected>Diamond</option>
               <option value="Ruby">Ruby</option>
               <option value="Emerald">Emerald</option>
               <option value="Sapphire">Sapphire</option>
             </select>
             <i class="fas fa-gem field-icon text-purple-500"></i>
           </div>
         </div>
         
         <div class="field-col">
           <div class="field-label">Stone Weight</div>
           <div class="field-container">
             <input type="number" 
               id="stoneWeight" 
               class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-purple-200 rounded-md" 
               placeholder="Stone weight" 
               value="0"
               step="0.01"
               min="0" />
             <i class="fas fa-weight-scale field-icon text-purple-500"></i>
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
               step="100" />
             <i class="fas fa-rupee-sign field-icon text-purple-500"></i>
           </div>
         </div>
       </div>
     </div>

     <!-- Making Charges Section -->
     <div class="section-card making-section">
       <div class="section-title text-green-800">
         <i class="fas fa-hammer"></i> Making Charges
       </div>
       <div class="field-row">
         <div class="field-col">
           <div class="field-label">Making Type</div>
           <div class="field-container">
             <select id="makingType" class="input-field text-xs font-bold py-0.5 pl-7 pr-2 h-7 appearance-none bg-white border border-green-200 rounded-md">
               <option value="per_gram">Per Gram</option>
               <option value="percentage">Percentage</option>
               <option value="fixed">Fixed Amount</option>
             </select>
             <i class="fas fa-cog field-icon text-green-500"></i>
           </div>
         </div>
         
         <div class="field-col">
           <div class="field-label">Making Rate</div>
           <div class="field-container">
             <input type="number" 
               id="makingRate" 
               class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
               placeholder="Rate" 
               value="0"
               step="1" />
             <i class="fas fa-rupee-sign field-icon text-green-500"></i>
           </div>
         </div>
         
         <div class="field-col">
           <div class="field-label">Making Charges</div>
           <div class="field-container">
             <input type="number" 
               id="makingCharges" 
               class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
               placeholder="Total" 
               value="0"
               readonly />
             <i class="fas fa-calculator field-icon text-green-500"></i>
           </div>
         </div>
       </div>

       <div class="field-row">
         <div class="field-col">
           <div class="field-label">Hallmark Charges</div>
           <div class="field-container">
             <input type="number" 
               id="hmCharges" 
               class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
               placeholder="HM charges" 
               value="35"
               step="1" />
             <i class="fas fa-certificate field-icon text-green-500"></i>
           </div>
         </div>
         
         <div class="field-col">
           <div class="field-label">Other Charges</div>
           <div class="field-container">
             <input type="number" 
               id="otherCharges" 
               class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
               placeholder="Other charges" 
               value="0"
               step="1" />
             <i class="fas fa-plus-circle field-icon text-green-500"></i>
           </div>
         </div>
         
         <div class="field-col">
           <div class="field-label">Total Charges</div>
           <div class="field-container">
             <input type="number" 
               id="totalCharges" 
               class="input-field text-xs font-bold py-0.5 pl-7 h-7 bg-white border border-green-200 rounded-md" 
               placeholder="Total" 
               value="0"
               readonly />
             <i class="fas fa-equals field-icon text-green-500"></i>
           </div>
         </div>
       </div>
     </div>

     <!-- Floating total section -->
     <div class="flex items-center justify-between p-2 bg-white rounded-xl mb-2 shadow-sm border border-gray-100">
       <div class="flex items-center gap-2">
         <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center">
           <i class="fas fa-calculator text-blue-500"></i>
         </div>
         <div class="flex items-baseline gap-1">
           <span class="text-xs text-gray-500">Total:</span>
           <span id="floatingTotal" class="text-lg font-bold text-blue-600 font-mono">₹0.00</span>
         </div>
       </div>
       <button id="addToCart" 
         class="px-4 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-medium hover:shadow-lg hover:shadow-blue-500/30 active:scale-95 disabled:opacity-50 disabled:pointer-events-none transition-all duration-200 flex items-center gap-2"
         disabled>
         <i id="cartBtnIcon" class="fas fa-cart-plus"></i>
         <span id="addToCartText">Add to Cart</span>
       </button>
     </div>

     <!-- Cart Bottom Sheet -->
     <div id="cartBottomSheet" class="fixed bottom-0 left-0 right-0 transform translate-y-full transition-transform duration-300 bg-gradient-to-b from-gray-50 to-white shadow-xl rounded-t-2xl font-[Poppins] z-50 border-t-4 border-blue-500/30 shadow-[0_-5px_25px_-5px_rgba(59,130,246,0.5)]">
 <div class="p-4 max-h-[80vh] overflow-y-auto">
   <!-- Handle and Header -->
   <div class="absolute top-0 left-0 right-0 h-1 flex justify-center p-2">
     <div class="w-16 h-1.5 bg-blue-300 rounded-full"></div>
   </div>
   
   <div class="flex justify-between items-center mt-1 mb-3">
     <div class="flex items-center gap-2.5">
       <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center">
         <i class="fas fa-shopping-cart text-blue-600"></i>
       </div>
       <div>
         <h3 class="text-xs font-semibold text-blue-900">Shopping Cart</h3>
         <p class="text-xs text-blue-500" id="cartItemCount">0 items</p>
       </div>
     </div>
     <button id="closeBottomSheet" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500">
       <i class="fas fa-times"></i>
     </button>
   </div>

   <!-- Cart Items Table -->
   <div class="overflow-x-auto bg-white rounded-lg shadow-md mb-3 border border-blue-100">
     <table class="w-full text-xs">
       <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
         <tr class="text-blue-800">
           <th class="px-2 py-2 text-left font-medium border-b border-blue-100">Item</th>
           <th class="px-2 py-2 text-right font-medium border-b border-blue-100">Wt</th>
           <th class="px-2 py-2 text-right font-medium border-b border-blue-100">Amt</th>
           <th class="px-2 py-2 text-right font-medium border-b border-blue-100">M.Chrg</th>
           <th class="px-2 py-2 text-right font-medium border-b border-blue-100">Total</th>
           <th class="px-2 py-2 text-center font-medium w-24 border-b border-blue-100">Actions</th>
         </tr>
       </thead>
       <tbody id="cartItems" class="divide-y divide-white">
         <!-- Cart items will be dynamically added here -->
       </tbody>
     </table>
   </div>

   <!-- Price Breakdown -->
   <div id="priceBreakdownContainer" class="bg-blue-50/30 rounded-lg p-3 border border-blue-100">
     <!-- Price breakdown will be dynamically added here -->
   </div>


  <!-- GST Section -->
<div class="flex items-center justify-between bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 p-4 rounded-2xl mt-4 border border-purple-100 shadow-md">
 <div class="flex items-center gap-3">
   <label for="gstApplicable" class="relative inline-flex items-center cursor-pointer">
     <input type="checkbox" id="gstApplicable" class="sr-only peer">
     <div class="w-10 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-400 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-500"></div>
   </label>
   <span class="text-sm font-medium text-gray-700">GST (3%)</span>
 </div>
 <input type="number" id="gstAmount" 
   class="w-28 text-right text-sm font-semibold bg-transparent border-none p-0 text-purple-700" 
   value="0" readonly />
</div>

<!-- Grand Total -->
<div class="flex items-center justify-between bg-gradient-to-r from-purple-500 via-pink-500 to-red-400 p-2 rounded-xl mt-2 shadow-lg">
 <span class="font-semibold text-white text-base">Grand Total</span>
 <span id="grandTotal" class="text-xl font-bold text-white tracking-wide">₹0.00</span>
</div>

<!-- Action Buttons -->
<div class="flex gap-2 mt-2">
 <button id="clearCart" class="flex-1 px-5 py-3 bg-gradient-to-r from-rose-100 to-red-100 text-red-600 border border-red-200 rounded-2xl text-sm font-semibold hover:from-rose-200 hover:to-red-200 transition-all duration-200 shadow-md flex items-center justify-center gap-2">
   <i class="fas fa-trash-alt"></i>
   Clear Cart
 </button>
 <button id="proceedToCheckout" class="flex-1 px-5 py-3 bg-gradient-to-r from-green-400 via-blue-500 to-indigo-500 text-white rounded-2xl text-sm font-semibold hover:opacity-90 transition-all duration-200 shadow-lg flex items-center justify-center gap-2">
   <i class="fas fa-check-circle"></i>
   Checkout
 </button>
</div>

   </div>
 </div>
</div>
</div>
</div>

 <!-- Customer Modal -->
<!-- Updated Customer Modal HTML with enhanced UI -->
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
        <div id="additional-info-content" class="modal-tab-content p-4 space-y-4">
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
</div>

 <!-- QR Scanner Modal -->
 <div id="qrScannerModal" class="modal">
   <div class="modal-content p-0 overflow-hidden max-w-lg">
     <!-- Header -->
     <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-4 text-white">
       <div class="flex justify-between items-center">
         <div class="flex items-center gap-2">
           <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
             <i class="fas fa-qrcode text-white"></i>
           </div>
           <h3 class="text-lg font-semibold">Scan QR Code</h3>
         </div>
         <button onclick="closeQRScanner()" 
           class="w-8 h-8 rounded-full hover:bg-white/20 flex items-center justify-center transition-colors">
           <i class="fas fa-times text-white"></i>
         </button>
       </div>
     </div>

     <!-- Scanner Content -->
     <div class="p-4">
       <div id="qr-reader"></div>
       <div id="qr-reader-results" class="mt-4 text-center text-sm text-gray-600">
         Position the QR code in the scanner
       </div>
     </div>

     <!-- Footer -->
     <div class="bg-gray-50 p-4 flex justify-end gap-2 border-t">
       <button onclick="closeQRScanner()" 
         class="px-4 h-10 rounded-lg border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-100 hover:border-gray-300 transition-colors flex items-center gap-2">
         <i class="fas fa-times"></i>
         Cancel
       </button>
     </div>
   </div>
 </div>

 <!-- URD Details Modal -->
 <div id="urdModal" class="modal fixed inset-0 z-[200] items-center justify-center bg-black/50 hidden">
 <div class="modal-content p-0 overflow-hidden flex flex-col max-w-md">
   <!-- Header - Compact orange header -->
   <div class="bg-orange-500 p-2 text-white shrink-0">
     <div class="flex justify-between items-center">
       <div class="flex items-center gap-2">
         <i class="fas fa-exchange-alt text-white"></i>
         <h3 class="text-base font-semibold">Used Gold Exchange (URD)</h3>
       </div>
       <button onclick="closeUrdModal()" class="text-white">
         <i class="fas fa-times"></i>
       </button>
     </div>
   </div>

   <!-- Scrollable Content Area - More compact -->
   <div class="p-3 space-y-2 overflow-y-auto">
     <!-- Basic Details -->
     <div class="grid grid-cols-2 gap-3">
       <div>
         <label class="text-xs font-medium text-amber-800 block mb-1">Item Name*</label>
         <div class="relative">
           <input type="text" id="urdItemName" 
             class="w-full h-9 pl-8 pr-2 rounded-md border border-amber-200 focus:border-amber-500 text-sm" 
             placeholder="Enter item name" required />
           <i class="fas fa-tag absolute left-2.5 top-1/2 -translate-y-1/2 text-amber-500"></i>
         </div>
       </div>
       
       <div>
         <label class="text-xs font-medium text-amber-800 block mb-1">Gross Weight (g)*</label>
         <div class="relative">
           <input type="number" id="urdGrossWeight" 
             class="w-full h-9 pl-8 pr-2 rounded-md border border-amber-200 focus:border-amber-500 text-sm" 
             placeholder="Enter weight" step="0.01" required onchange="calculateUrdWeights()" />
           <i class="fas fa-weight-scale absolute left-2.5 top-1/2 -translate-y-1/2 text-amber-500"></i>
         </div>
       </div>
     </div>

     <!-- Weight Details -->
     <div class="grid grid-cols-2 gap-3">
       <div>
         <label class="text-xs font-medium text-amber-800 block mb-1">Less Weight (g)</label>
         <div class="relative">
           <input type="number" id="urdLessWeight" 
             class="w-full h-9 pl-8 pr-2 rounded-md border border-amber-200 focus:border-amber-500 text-sm" 
             placeholder="Enter less" step="0.01" value="0" onchange="calculateUrdWeights()" />
           <i class="fas fa-minus absolute left-2.5 top-1/2 -translate-y-1/2 text-amber-500"></i>
         </div>
       </div>

       <div>
         <label class="text-xs font-medium text-amber-800 block mb-1">Net Weight (g)</label>
         <div class="relative">
           <input type="number" id="urdNetWeight" 
             class="w-full h-9 pl-8 pr-2 rounded-md border border-amber-200 bg-amber-50/50 text-sm" 
             readonly />
           <i class="fas fa-scale-balanced absolute left-2.5 top-1/2 -translate-y-1/2 text-amber-500"></i>
         </div>
       </div>
     </div>

     <!-- Rate Details -->
     <div class="grid grid-cols-2 gap-3">
       <div>
         <label class="text-xs font-medium text-amber-800 block mb-1">Purity (%)</label>
         <div class="relative">
           <input type="number" id="urdPurity" 
             class="w-full h-9 pl-8 pr-2 rounded-md border border-amber-200 focus:border-amber-500 text-sm" 
             placeholder="Enter purity" step="0.1" onchange="calculateUrdValues('purity')" />
           <i class="fas fa-certificate absolute left-2.5 top-1/2 -translate-y-1/2 text-amber-500"></i>
         </div>
       </div>

       <div>
         <label class="text-xs font-medium text-amber-800 block mb-1">Rate per Gram</label>
         <div class="relative">
           <input type="number" id="urdRate" 
             class="w-full h-9 pl-8 pr-2 rounded-md border border-amber-200 focus:border-amber-500 text-sm" 
             placeholder="Current rate" step="1" onchange="calculateUrdValues('rate')" />
           <i class="fas fa-rupee-sign absolute left-2.5 top-1/2 -translate-y-1/2 text-amber-500"></i>
         </div>
       </div>
     </div>

     <!-- Totals Section - Simplified orange box -->
     <div class="bg-orange-500 rounded-md p-2 text-white">
       <div class="grid grid-cols-3 gap-2 mb-2">
         <div class="bg-orange-400/50 p-2 rounded-md text-center">
           <div class="text-xs">Fine Weight</div>
           <div id="urdFineWeight" class="font-semibold">0.000g</div>
         </div>
         <div class="bg-orange-400/50 p-2 rounded-md text-center">
           <div class="text-xs">Net Weight</div>
           <div id="urdNetWeightDisplay" class="font-semibold">0.000g</div>
         </div>
         <div class="bg-orange-400/50 p-2 rounded-md text-center">
           <div class="text-xs">Rate</div>
           <div id="urdRateDisplay" class="font-semibold">₹0</div>
         </div>
       </div>
       <div class="bg-orange-400/50 p-2 rounded-md flex justify-between items-center">
         <div class="text-sm">Total Amount</div>
         <div id="urdTotal" class="text-lg font-bold">₹0</div>
       </div>
     </div>

     <!-- Item Image -->
     <div>
       <label class="text-xs font-medium text-amber-800 block mb-1">Item Image</label>
       <div class="camera-preview hidden" id="cameraPreview">
         <video id="urdVideo" autoplay playsinline class="w-full h-32 object-cover rounded-md"></video>
         <div class="camera-controls mt-2 flex justify-center">
           <button class="camera-button bg-amber-100 p-2 rounded-full" onclick="captureUrdImage()">
             <i class="fas fa-camera text-amber-600"></i>
           </button>
         </div>
       </div>
       <button onclick="openUrdCamera()" 
         id="startCameraBtn"
         class="w-full h-9 pl-8 pr-2 rounded-md border border-amber-200 hover:bg-amber-50 text-sm text-left relative">
         <i class="fas fa-camera absolute left-2.5 top-1/2 -translate-y-1/2 text-amber-500"></i>
         Take Photo
       </button>
       <div id="urdImagePreview" class="hidden mt-2">
         <img id="urdCapturedImage" class="w-full h-32 object-cover rounded-md" />
         <button onclick="retakeUrdImage()" 
           class="mt-2 px-3 py-1 bg-red-100 text-red-600 rounded-md text-xs font-medium">
           Retake Image
         </button>
       </div>
     </div>
   </div>

   <!-- Footer -->
   <div class="bg-gray-50 p-2 flex justify-end gap-2 border-t">
     <button onclick="closeUrdModal()" 
       class="px-4 py-2 border border-gray-300 rounded-md text-gray-600 text-sm">
       Cancel
     </button>
     <button onclick="saveUrdItem()" 
       class="px-4 py-2 bg-orange-500 text-white rounded-md text-sm">
       Add URD Item
     </button>
   </div>
 </div>
</div>

 <!-- Checkout Modal -->
 <div id="checkoutModal" class="modal fixed inset-0 z-[200] items-center justify-center bg-black/50 hidden">
   <div class="modal-content p-0 overflow-hidden flex flex-col max-w-lg w-11/12 bg-white rounded-xl">
       <!-- Compact Header -->
       <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-3 text-white">
           <div class="flex justify-between items-center">
               <div class="flex items-center gap-2">
                   <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center">
                       <i class="fas fa-receipt text-white text-sm"></i>
                   </div>
                   <div>
                       <h3 class="text-base font-medium">Complete Sale</h3>
                       <p class="text-xs text-white/80">Finalize your transaction</p>
                   </div>
               </div>
               <button onclick="closeCheckoutModal()" 
                   class="w-7 h-7 rounded-full hover:bg-white/20 flex items-center justify-center transition-colors">
                   <i class="fas fa-times text-white text-sm"></i>
               </button>
           </div>
       </div>

       <!-- Scrollable Content Area -->
       <div class="p-3 overflow-y-auto space-y-3">
           <!-- Invoice & Customer Info Cards -->
           <div class="grid grid-cols-2 gap-2">
               <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 p-2 rounded-lg border border-blue-200">
                   <div class="text-[10px] font-medium text-blue-600 mb-0.5">Invoice Date</div>
                   <div id="invoiceDate" class="text-sm font-semibold text-blue-900"></div>
               </div>
               <div class="bg-gradient-to-br from-indigo-50 to-indigo-100/50 p-2 rounded-lg border border-indigo-200">
                   <div class="text-[10px] font-medium text-indigo-600 mb-0.5">Customer</div>
                   <div id="customerDetails" class="text-sm font-semibold text-indigo-900"></div>
               </div>
           </div>

           <!-- Invoice Number Display -->
           <div class="bg-gradient-to-br from-green-50 to-green-100/50 p-2 rounded-lg border border-green-200">
               <div class="text-[10px] font-medium text-green-600 mb-0.5">Invoice Number</div>
               <div id="invoiceNumber" class="text-sm font-semibold text-green-900"></div>
           </div>

           <!-- Amount Summary -->
           <div class="bg-gradient-to-br from-gray-50 to-gray-100/50 rounded-lg border border-gray-200">
               <div class="space-y-1.5 p-2">
                   <div class="flex justify-between items-center text-xs">
                       <span class="text-gray-600">Total Amount</span>
                       <div id="checkoutTotalAmount" class="font-medium text-gray-800"></div>
                   </div>
                   <div class="flex justify-between items-center text-xs">
                       <span class="text-gray-600">GST Amount</span>
                       <div id="checkoutGstAmount" class="font-medium text-blue-600"></div>
                   </div>
                   <div class="flex justify-between items-center text-xs">
                       <span class="text-gray-600">Discount</span>
                       <div id="checkoutDiscount" class="font-medium text-red-600"></div>
                   </div>
                   <div class="flex justify-between items-center text-xs">
                       <span class="text-gray-600">URD Amount</span>
                       <div id="checkoutUrdAmount" class="font-medium text-orange-600"></div>
                   </div>
               </div>
               <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-2 rounded-b-lg">
                   <div class="flex justify-between items-center">
                       <span class="text-xs font-medium text-white/90">Amount To Be Paid</span>
                       <span id="checkoutGrandTotal" class="text-base font-bold text-white"></span>
                   </div>
               </div>
           </div>

           <!-- Customer Orders Section (New) -->
          <!-- Customer Advance Payments Section -->
<div id="customerAdvanceSection" class="bg-gradient-to-br from-green-50 to-green-100/50 p-2 rounded-lg border border-green-200 mb-3 hidden">
    <div class="flex justify-between items-center">
        <h4 class="text-xs font-medium text-green-700">Available Advance Payments</h4>
        <div id="totalAdvanceAmount" class="text-xs font-bold text-green-700">₹0.00</div>
    </div>
    
    <div class="mt-2 max-h-32 overflow-y-auto">
        <div id="advancePaymentsList" class="space-y-2">
            <!-- Advance payments will be listed here -->
        </div>
    </div>
    
    <div class="mt-2 pt-2 border-t border-green-200">
        <div class="flex justify-between items-center">
            <span class="text-xs text-green-700">Total Selected:</span>
            <span id="selectedAdvanceAmount" class="text-xs font-bold text-green-700">₹0.00</span>
        </div>
    </div>
</div>

           <!-- Payment Methods -->
           <div class="space-y-2">
               <div class="flex items-center justify-between">
                   <h4 class="text-xs font-medium text-gray-700">Payment Methods</h4>
                   <button onclick="addPaymentMethod()" 
                       class="px-2 py-1 bg-blue-50 text-blue-600 rounded-md text-xs font-medium hover:bg-blue-100 transition-colors flex items-center gap-1">
                       <i class="fas fa-plus text-[10px]"></i>
                       Add Method
                   </button>
               </div>
               
               <!-- Payment Methods Container -->
               <div id="paymentMethodsContainer" class="space-y-2">
                   <!-- Payment methods will be rendered here -->
               </div>

               <!-- Payment Summary - Enhanced version -->
               <div class="grid grid-cols-2 gap-2 mt-2">
                   <div class="bg-green-50 p-2 rounded-lg border border-green-200">
                       <div class="text-[10px] font-medium text-green-600">Total Paid</div>
                       <div id="totalPaidAmount" class="text-sm font-bold text-green-700"></div>
                       <div class="text-[10px] text-green-500 mt-1" id="paymentBreakdown"></div>
                   </div>
                   <div class="bg-red-50 p-2 rounded-lg border border-red-200">
                       <div class="text-[10px] font-medium text-red-600 flex items-center gap-1">
                           Due Amount
                           <i class="fas fa-exclamation-circle text-red-500" id="dueWarningIcon"></i>
                       </div>
                       <div id="remainingAmount" class="text-sm font-bold text-red-700"></div>
                       <div class="text-[10px] text-red-500 mt-1">Will be added to customer dues</div>
                   </div>
               </div>
           </div>

           <!-- Notes Section - Enhanced -->
           <div class="space-y-1">
               <label class="text-xs font-medium text-gray-600">Notes</label>
               <textarea id="saleNotes" 
                   class="w-full p-2 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:border-blue-300 focus:ring-2 focus:ring-blue-100 transition-all"
                   rows="3"
                   placeholder="Add any notes about this sale..."></textarea>
               <div id="paymentSummaryNote" class="text-xs text-gray-500 mt-1"></div>
           </div>
       </div>

       <!-- Footer -->
       <div class="bg-gray-50 p-2 flex justify-end gap-2 border-t">
           <button onclick="closeCheckoutModal()" 
               class="px-4 py-2 border border-gray-200 rounded-lg text-gray-600 text-xs font-medium hover:bg-gray-100">
               Cancel
           </button>
           <button id="completeSaleBtn" onclick="processSale()"
               class="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg text-xs font-medium hover:opacity-90 disabled:opacity-50">
               Complete Sale
           </button>
       </div>
   </div>
</div>

 <!-- Active Schemes Marquee -->
<div id="activeSchemesMarquee" class="w-full mb-2 hidden">
  <div class="overflow-x-hidden whitespace-nowrap bg-gradient-to-r from-yellow-100 to-pink-100 rounded-lg border border-yellow-200 py-2 px-3">
    <span id="schemesMarqueeText" class="inline-block animate-marquee text-amber-700 font-semibold text-sm"></span>
  </div>
</div>
<!-- End Active Schemes Marquee -->

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
   <a href="sale-entry.php" class="nav-item active">
     <i class="nav-icon fas fa-tags"></i>
     <span class="nav-text">Sell</span>
   </a>

   <!-- Cart -->
   <div class="nav-item relative" onclick="showCart()">
     <i class="nav-icon fas fa-shopping-cart"></i>
     <span class="nav-text">Cart</span>
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
 <script src="js/sale.js"></script>

 <!-- Subscription Expiration Modal -->
 <div id="subscriptionExpiredModal" class="modal fixed inset-0 z-[200] items-center justify-center bg-black/50 hidden">
     <div class="modal-content p-0 overflow-hidden flex flex-col max-w-lg w-11/12 bg-white rounded-xl">
         <!-- Header -->
         <div class="bg-gradient-to-r from-red-600 to-red-700 p-4 text-white">
             <div class="flex justify-between items-center">
                 <div class="flex items-center gap-2">
                     <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                         <i class="fas fa-exclamation-triangle text-white"></i>
                     </div>
                     <div>
                         <h3 class="text-lg font-semibold">
                             <?php echo $isTrialUser ? 'Trial Expired' : 'Subscription Expired'; ?>
                         </h3>
                         <p class="text-sm text-white/80">
                             Your <?php echo $isTrialUser ? 'trial period' : 'subscription'; ?> has ended
                         </p>
                     </div>
                 </div>
             </div>
         </div>

         <!-- Content -->
         <div class="p-4 space-y-4">
             <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                 <div class="flex items-start gap-3">
                     <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                         <i class="fas fa-lock text-red-500"></i>
                     </div>
                     <div>
                         <h4 class="font-semibold text-red-800 mb-1">Access Restricted</h4>
                         <p class="text-sm text-red-700">
                             To continue using all features, please upgrade your plan or renew your subscription.
                         </p>
                     </div>
                 </div>
             </div>

             <div class="space-y-2">
                 <h4 class="font-medium text-gray-800">Available Plans:</h4>
                 <?php
                 $plansQuery = "SELECT * FROM subscription_plans WHERE is_active = 1 AND name != 'Trial' ORDER BY price ASC";
                 $plansResult = $conn->query($plansQuery);
                 while ($plan = $plansResult->fetch_assoc()):
                     $duration = (int)$plan['duration_in_days'];
                     $durationText = $duration == 30 ? '1 Month' : ($duration == 365 ? '1 Year' : $duration . ' Days');
                     $color = stripos($plan['name'], 'Premium') !== false ? 'green' : (stripos($plan['name'], 'Standard') !== false ? 'purple' : 'blue');
                 ?>
                 <div class="border border-gray-200 rounded-lg p-3 hover:border-<?php echo $color; ?>-300 transition-colors">
                     <div class="flex justify-between items-start">
                         <div>
                             <h5 class="font-bold text-<?php echo $color; ?>-700"><?php echo htmlspecialchars($plan['name']); ?></h5>
                             <p class="text-sm text-gray-600"><?php echo $durationText; ?></p>
                         </div>
                         <div class="text-right">
                             <span class="text-xl font-bold text-<?php echo $color; ?>-600">₹<?php echo number_format($plan['price']); ?></span>
                             <p class="text-xs text-gray-500">one-time</p>
                         </div>
                     </div>
                 </div>
                 <?php endwhile; ?>
             </div>
         </div>

         <!-- Footer -->
         <div class="bg-gray-50 p-4 flex justify-end gap-2 border-t">
             <a href="home.php" class="px-4 py-2 border border-gray-200 rounded-lg text-gray-600 text-sm font-medium hover:bg-gray-100">
                 Back to Home
             </a>
             <a href="subscription.php" class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg text-sm font-medium hover:opacity-90">
                 Upgrade Now
             </a>
         </div>
     </div>
 </div>

 <script>
     // Show subscription expired modal if subscription is expired
     <?php if ($isExpired): ?>
     document.addEventListener('DOMContentLoaded', function() {
         document.getElementById('subscriptionExpiredModal').classList.remove('hidden');
     });
     <?php endif; ?>
 </script>

</body>
</html>

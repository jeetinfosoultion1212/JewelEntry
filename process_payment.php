<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require 'config/config.php';

// Enable comprehensive error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'payment_errors.log');
ini_set('display_errors', 0); // Suppress direct output of errors to the browser for JSON responses

// Detect AJAX/fetch request
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false)
);

function logDebug($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] PAYMENT PROCESS: $message";
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data, JSON_PRETTY_PRINT);
    }
    error_log($logMessage);
}

function logError($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] PAYMENT ERROR: $message";
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data, JSON_PRETTY_PRINT);
    }
    error_log($logMessage);
}

logDebug("=== PAYMENT PROCESSING STARTED ===");
logDebug("POST Data Received", $_POST);
logDebug("Session Data", [
    'user_id' => $_SESSION['id'] ?? 'not set',
    'firm_id' => $_SESSION['firmID'] ?? 'not set'
]);

// Check if user is logged in and firm ID is set
if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    logError("Authentication failed - missing session data");
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}

$firm_id = $_SESSION['firmID'];
$user_id = $_SESSION['id'];
logDebug("Authentication successful", ['user_id' => $user_id, 'firm_id' => $firm_id]);

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    logError("Invalid request method", ['method' => $_SERVER["REQUEST_METHOD"]]);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    } else {
        header("Location: customer.php");
        exit();
    }
}

// Collect and validate input data
$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
$total_payment_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$payment_method_form = isset($_POST['method']) ? trim($_POST['method']) : '';
$payment_type_form = isset($_POST['type']) ? trim($_POST['type']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$allocated_amounts = isset($_POST['allocated_amount']) ? $_POST['allocated_amount'] : [];

// Sanitize inputs
$payment_method_form = htmlspecialchars($payment_method_form, ENT_QUOTES, 'UTF-8');
$payment_type_form = htmlspecialchars($payment_type_form, ENT_QUOTES, 'UTF-8');
$notes = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');

$created_at = date('Y-m-d H:i:s');

logDebug("Input data processed", [
    'customer_id' => $customer_id,
    'total_payment_amount' => $total_payment_amount,
    'payment_method' => $payment_method_form,
    'payment_type' => $payment_type_form,
    'notes' => $notes,
    'allocated_amounts_count' => count($allocated_amounts),
    'allocated_amounts' => $allocated_amounts
]);

// Enhanced validation
$validation_errors = [];

if ($customer_id <= 0) {
    $validation_errors[] = "Invalid customer ID";
}
if ($total_payment_amount <= 0) {
    $validation_errors[] = "Invalid payment amount";
}
if (empty($payment_method_form)) {
    $validation_errors[] = "Payment method is required";
}
if (empty($payment_type_form)) {
    $validation_errors[] = "Payment type is required";
}

if (!empty($validation_errors)) {
    logError("Validation failed", $validation_errors);
    $error_message = implode(", ", $validation_errors);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit();
    } else {
        header("Location: customer_details.php?id=" . $customer_id . "&error=" . urlencode($error_message));
        exit();
    }
}

logDebug("Validation passed successfully");

// Fixed values for payment record
$party_type = 'customer';
$transactions_type = 'credit';
$reference_no = 'PAY-' . date('YmdHis') . '-' . $customer_id;

logDebug("Payment reference generated", ['reference_no' => $reference_no]);

// Database connection with enhanced error handling
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    logError("Database connection failed", ['error' => $conn->connect_error]);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again.']);
        exit();
    } else {
        header("Location: customer_details.php?id=" . $customer_id . "&error=" . urlencode("Database connection failed. Please try again."));
        exit();
    }
}

// Set charset for proper handling of special characters
$conn->set_charset("utf8");
logDebug("Database connected successfully");

// Start transaction with enhanced error handling
if (!$conn->begin_transaction()) {
    logError("Failed to start transaction", ['error' => $conn->error]);
    $conn->close();
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Transaction initialization failed']);
        exit();
    } else {
        header("Location: customer_details.php?id=" . $customer_id . "&error=" . urlencode("Transaction initialization failed"));
        exit();
    }
}

logDebug("Database transaction started");

try {
    // Verify customer exists and belongs to firm
    $customerCheckQuery = "SELECT id, FirstName, LastName FROM customer WHERE id = ? AND firm_id  = ?";
    $customerCheckStmt = $conn->prepare($customerCheckQuery);
    if (!$customerCheckStmt) {
        throw new Exception("Customer verification query preparation failed: " . $conn->error);
    }
    
    $customerCheckStmt->bind_param("ii", $customer_id, $firm_id);
    $customerCheckStmt->execute();
    $customerResult = $customerCheckStmt->get_result();
    
    if ($customerResult->num_rows === 0) {
        throw new Exception("Customer not found or doesn't belong to this firm");
    }
    
    $customerInfo = $customerResult->fetch_assoc();
    $customerCheckStmt->close();
    
    logDebug("Customer verification successful", $customerInfo);

    if ($payment_type_form === 'Sale Due' && !empty($allocated_amounts)) {
        logDebug("Processing Sale Due with FIFO allocations");
        
        $totalAllocated = 0;
        $processedAllocations = [];
        $paymentRecordIds = [];
        
        foreach ($allocated_amounts as $sale_id => $allocated_amount) {
            $allocated_amount = floatval($allocated_amount);
            $sale_id = intval($sale_id);

            logDebug("Processing individual allocation", [
                'sale_id' => $sale_id,
                'allocated_amount' => $allocated_amount
            ]);

            if ($allocated_amount > 0 && $sale_id > 0) {
                // Fetch and lock current sale details
                $saleQuery = "SELECT due_amount, grand_total, payment_status FROM jewellery_sales WHERE id = ? AND customer_id = ? AND firm_id = ? FOR UPDATE";
                $saleStmt = $conn->prepare($saleQuery);
                if (!$saleStmt) {
                    throw new Exception("Sale query preparation failed: " . $conn->error);
                }
                
                $saleStmt->bind_param("iii", $sale_id, $customer_id, $firm_id);
                $saleStmt->execute();
                $saleResult = $saleStmt->get_result();
                $sale = $saleResult->fetch_assoc();
                $saleStmt->close();

                logDebug("Sale details retrieved", [
                    'sale_id' => $sale_id,
                    'sale_found' => $sale !== null,
                    'current_due' => $sale ? $sale['due_amount'] : 'N/A',
                    'current_status' => $sale ? $sale['payment_status'] : 'N/A'
                ]);

                if (!$sale) {
                    throw new Exception("Sale #$sale_id not found or doesn't belong to this customer/firm");
                }
                
                $current_due = floatval($sale['due_amount']);
                $grand_total = floatval($sale['grand_total']);
                
                // Enhanced validation
                if ($allocated_amount > $current_due + 0.01) { // Allow small floating point differences
                    throw new Exception("Allocated amount ₹" . number_format($allocated_amount, 2) . " exceeds due amount ₹" . number_format($current_due, 2) . " for Sale #$sale_id");
                }
                
                $new_due = max(0, $current_due - $allocated_amount); // Ensure non-negative
                $totalAllocated += $allocated_amount;

                // Calculate new total paid amount
                $current_paid = $grand_total - $current_due;
                $new_paid = $current_paid + $allocated_amount;

                logDebug("Payment calculation", [
                    'sale_id' => $sale_id,
                    'grand_total' => $grand_total,
                    'current_due' => $current_due,
                    'current_paid' => $current_paid,
                    'allocated_amount' => $allocated_amount,
                    'new_due' => $new_due,
                    'new_paid' => $new_paid
                ]);

                // Determine new payment status
                $new_status = ($new_due <= 0.01) ? 'Paid' : 'Partial';
                
                // Update jewellery_sales table with new due amount, status and total paid
                $updateSaleQuery = "UPDATE jewellery_sales SET 
                    due_amount = ?, 
                    payment_status = ?, 
                    total_paid_amount = ?,
                    updated_at = NOW() 
                    WHERE id = ? AND customer_id = ? AND firm_id = ?";
                $updateSaleStmt = $conn->prepare($updateSaleQuery);
                if (!$updateSaleStmt) {
                    throw new Exception("Sale update preparation failed: " . $conn->error);
                }

                $updateSaleStmt->bind_param("dsdiii", $new_due, $new_status, $new_paid, $sale_id, $customer_id, $firm_id);
                $updateSaleStmt->execute();
                
                logDebug("Sale update executed", [
                    'sale_id' => $sale_id,
                    'affected_rows' => $updateSaleStmt->affected_rows,
                    'new_status' => $new_status,
                    'new_due' => $new_due
                ]);
                
                if ($updateSaleStmt->affected_rows === 0) {
                    throw new Exception("Failed to update Sale #$sale_id - no rows affected");
                }
                $updateSaleStmt->close();

                // Insert detailed payment record
                $insertPaymentQuery = "INSERT INTO jewellery_payments (reference_id, reference_type, party_type, party_id, sale_id, payment_type, amount,  reference_no, remarks, created_at, transctions_type, Firm_id) VALUES (?, ?, ?, ?, ?,  ?, ?, ?, ?, ?, ?, ?)";
                $insertPaymentStmt = $conn->prepare($insertPaymentQuery);
                if (!$insertPaymentStmt) {
                    throw new Exception("Payment insert preparation failed: " . $conn->error);
                }

                $payment_remarks = "FIFO allocation for Sale #$sale_id";
                $reference_type = 'due_invoice';
                $insertPaymentStmt->bind_param("issiisdssssi",
                    $sale_id,                    // reference_id
                    $reference_type,             // reference_type
                    $party_type,                 // party_type
                    $customer_id,                // party_id
                    $sale_id,                    // sale_id
                    $payment_method_form,        // payment_type
                    $allocated_amount,           // amount
                    $reference_no,               // reference_no
                    $payment_remarks,            // remarks
                    $created_at,                 // created_at
                    $transactions_type,          // transctions_type
                    $firm_id                     // Firm_id
                );
                
                $insertPaymentStmt->execute();
                
                logDebug("Payment record inserted", [
                    'sale_id' => $sale_id,
                    'affected_rows' => $insertPaymentStmt->affected_rows,
                    'insert_id' => $insertPaymentStmt->insert_id,
                    'allocated_amount' => $allocated_amount
                ]);
                
                if ($insertPaymentStmt->affected_rows === 0) {
                    throw new Exception("Failed to insert payment record for Sale #$sale_id");
                }
                
                $paymentRecordIds[] = $insertPaymentStmt->insert_id;
                $insertPaymentStmt->close();
                
                $processedAllocations[] = [
                    'sale_id' => $sale_id,
                    'allocated_amount' => $allocated_amount,
                    'new_due' => $new_due,
                    'status' => $new_status
                ];
            }
        }
        
        logDebug("All allocations processed", [
            'total_allocated' => $totalAllocated,
            'payment_amount' => $total_payment_amount,
            'processed_count' => count($processedAllocations),
            'payment_record_ids' => $paymentRecordIds
        ]);
        
        // Final validation
        if (abs($totalAllocated - $total_payment_amount) > 0.01) {
            throw new Exception("Total allocated amount ₹" . number_format($totalAllocated, 2) . " doesn't match payment amount ₹" . number_format($total_payment_amount, 2));
        }
        
        logDebug("FIFO allocation validation passed");
        
    } else if ($payment_type_form === 'Scheme Installment') {
        logDebug("Processing Scheme Installment payment", [
            'customer_id' => $customer_id,
            'payment_amount' => $total_payment_amount,
            'allocations' => $allocated_amounts
        ]);

        // Get all allocations for scheme installments
        foreach ($allocated_amounts as $allocation_key => $allocated_amount) {
            if ($allocated_amount <= 0) continue;

            // Parse the allocation key which contains customer_plan_id-installment_number-installment_date
            $parts = explode('-', trim($allocation_key, '[]'));
            if (count($parts) !== 3) {
                throw new Exception("Invalid allocation key format for scheme installment");
            }

            $customer_plan_id = $parts[0];
            $installment_number = $parts[1];
            $installment_date = $parts[2];
            // Ensure date is in Y-m-d format
            $installment_date = date('Y-m-d', strtotime($installment_date));

            // Get scheme details
            $schemeQuery = "SELECT cgp.*, gp.plan_name, gp.min_amount_per_installment, gp.bonus_percentage 
                          FROM customer_gold_plans cgp 
                          JOIN gold_saving_plans gp ON cgp.plan_id = gp.id 
                          WHERE cgp.id = ? AND cgp.customer_id = ?";
            $schemeStmt = $conn->prepare($schemeQuery);
            $schemeStmt->bind_param("ii", $customer_plan_id, $customer_id);
            $schemeStmt->execute();
            $schemeResult = $schemeStmt->get_result();
            
            if ($schemeResult->num_rows === 0) {
                throw new Exception("Invalid scheme plan ID or customer mismatch");
            }
            
            $scheme = $schemeResult->fetch_assoc();
            $schemeStmt->close();

            // Fetch current gold rate for 99.99 purity from jewellery_price_config table, filtered by firm_id
            $goldRateConfigQuery = "SELECT rate FROM jewellery_price_config WHERE purity = '99.99' AND material_type = 'Gold' AND firm_id = ? ORDER BY effective_date DESC LIMIT 1";
            $goldRateConfigStmt = $conn->prepare($goldRateConfigQuery);
            if (!$goldRateConfigStmt) {
                throw new Exception("Gold rate query preparation failed: " . $conn->error);
            }
            $goldRateConfigStmt->bind_param("i", $firm_id);
            $goldRateConfigStmt->execute();
            $goldRateConfigResult = $goldRateConfigStmt->get_result();
            
            if ($goldRateConfigResult && $goldRateConfigResult->num_rows > 0) {
                $goldRateRow = $goldRateConfigResult->fetch_assoc();
                $currentGoldRate = floatval($goldRateRow['rate']);
                logDebug("Fetched current gold rate from jewellery_price_config", ['purity' => '99.99', 'rate' => $currentGoldRate, 'firm_id' => $firm_id]);
            } else {
                // Fallback or error if rate not found
                logError("Gold rate for 99.99 purity not found in jewellery_price_config for firm_id: " . $firm_id . ". Using fallback 1000.");
                $currentGoldRate = 1000; // Fallback to 1000 if not found
            }
            $goldRateConfigStmt->close();

            // Calculate new total amount paid
            $new_total_paid = $scheme['total_amount_paid'] + $allocated_amount;
            
            // Calculate gold accrued based on bonus percentage and fetched gold rate
            $bonus_percentage = $scheme['bonus_percentage'] / 100;
            $goldAccrued = ($allocated_amount * (1 + $bonus_percentage)) / $currentGoldRate; // Use fetched rate
            $new_total_gold = $scheme['total_gold_accrued'] + $goldAccrued;

            // Update scheme details
            $updateSchemeStmt = $conn->prepare("UPDATE customer_gold_plans 
                                              SET total_amount_paid = ?, 
                                                  total_gold_accrued = ?,
                                                  updated_at = CURDATE()
                                              WHERE id = ?");
            $updateSchemeStmt->bind_param("ddi", $new_total_paid, $new_total_gold, $customer_plan_id);
            $updateSchemeStmt->execute();
            
            if ($updateSchemeStmt->affected_rows === 0) {
                throw new Exception("Failed to update scheme details");
            }
            $updateSchemeStmt->close();

            // Insert payment record
            $reference_type = 'scheme_installment';
            // Ensure transactions_type is explicitly set to 'credit' for this payment right before binding
            $transactions_type_for_scheme = 'credit'; // Fresh variable for binding

            // Validate payment method
            if (empty($payment_method_form)) {
                throw new Exception("Payment method is required");
            }
            
            // Map payment method to numeric value if needed
            $payment_type = $payment_method_form; // Store the actual payment method string
            
            // Set remarks for this specific scheme installment
            $payment_remarks_for_scheme = "Scheme Installment Payment for " . htmlspecialchars($scheme['plan_name']);

            logDebug("Preparing scheme payment record for binding", [
                'reference_type' => $reference_type,
                'transactions_type' => $transactions_type_for_scheme, // Log the new variable
                'payment_type' => $payment_type,
                'payment_remarks' => $payment_remarks_for_scheme
            ]);
            
            $insertPaymentQuery = "INSERT INTO jewellery_payments (reference_id, reference_type, party_type, party_id, sale_id, payment_type, amount, payment_notes, reference_no, remarks, created_at, transctions_type, Firm_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertPaymentStmt = $conn->prepare($insertPaymentQuery);
            if (!$insertPaymentStmt) {
                throw new Exception("Payment insert preparation failed: " . $conn->error);
            }

            // Corrected bind_param types: issiisdsssssi
            $insertPaymentStmt->bind_param("issiisdsssssi",
                $customer_plan_id,           // reference_id (i)
                $reference_type,             // reference_type (s)
                $party_type,                 // party_type (s)
                $customer_id,                // party_id (i)
                $customer_plan_id,           // sale_id (i)
                $payment_type,               // payment_type (s)
                $allocated_amount,           // amount (d)
                $notes,                      // payment_notes (s)
                $reference_no,               // reference_no (s)
                $payment_remarks_for_scheme, // remarks (s) - Now correctly set inside loop
                $created_at,                 // created_at (s)
                $transactions_type_for_scheme, // transctions_type (s) - New variable
                $firm_id                     // Firm_id (i)
            );
            
            $executeSuccess = $insertPaymentStmt->execute();
            
            if (!$executeSuccess) {
                logError("Failed to execute scheme payment insertion", [
                    'stmt_error' => $insertPaymentStmt->error,
                    'stmt_errno' => $insertPaymentStmt->errno,
                    'customer_plan_id' => $customer_plan_id,
                    'reference_type' => $reference_type,
                    'party_type' => $party_type,
                    'customer_id' => $customer_id,
                    'payment_type' => $payment_type,
                    'allocated_amount' => $allocated_amount,
                    'notes' => $notes,
                    'reference_no' => $reference_no,
                    'payment_remarks' => $payment_remarks_for_scheme,
                    'created_at' => $created_at,
                    'transactions_type' => $transactions_type_for_scheme,
                    'firm_id' => $firm_id
                ]);
                throw new Exception("Failed to insert scheme payment record: " . $insertPaymentStmt->error);
            }
            
            logDebug("Scheme payment record inserted", [
                'customer_plan_id' => $customer_plan_id,
                'affected_rows' => $insertPaymentStmt->affected_rows,
                'insert_id' => $insertPaymentStmt->insert_id,
                'allocated_amount' => $allocated_amount
            ]);
            
            if ($insertPaymentStmt->affected_rows === 0) {
                throw new Exception("Failed to insert scheme payment record - no rows affected");
            }
            
            $paymentRecordIds[] = $insertPaymentStmt->insert_id;
            $insertPaymentStmt->close();

            logDebug('Updating gold_plan_installments', [
                'customer_plan_id' => $customer_plan_id,
                'installment_date' => $installment_date,
                'allocated_amount' => $allocated_amount
            ]);
            // Update the gold_plan_installments table for this installment
            $updateInstallmentStmt = $conn->prepare(
                "UPDATE gold_plan_installments 
                 SET amount_paid = ?, 
                     gold_credited_g = ?, 
                     payment_method = ?, 
                     receipt_number = ?, 
                     notes = ?, 
                     created_by = ?, 
                     created_at = NOW() 
                 WHERE customer_plan_id = ? 
                   AND payment_date = ?"
            );
            $receipt_number = $reference_no; // Or generate as needed
            $updateInstallmentStmt->bind_param(
                "dssssiss",
                $allocated_amount,           // amount_paid
                $goldAccrued,                // gold_credited_g
                $payment_method_form,        // payment_method
                $receipt_number,             // receipt_number
                $notes,                      // notes
                $user_id,                    // created_by
                $customer_plan_id,           // customer_plan_id
                $installment_date            // payment_date
            );
            $updateInstallmentStmt->execute();
            if ($updateInstallmentStmt->affected_rows === 0) {
                logError("Failed to update gold_plan_installments for paid installment", [
                    'customer_plan_id' => $customer_plan_id,
                    'installment_date' => $installment_date,
                    'allocated_amount' => $allocated_amount
                ]);
                // Optionally: throw new Exception("Failed to update gold_plan_installments for paid installment");
            }
            $updateInstallmentStmt->close();

            $processedAllocations[] = [
                'customer_plan_id' => $customer_plan_id,
                'plan_name' => $scheme['plan_name'],
                'allocated_amount' => $allocated_amount,
                'gold_accrued' => $goldAccrued,
                'new_total_paid' => $new_total_paid,
                'new_total_gold' => $new_total_gold
            ];
        }
    } else if ($payment_type_form === 'Loan EMI') {
        logDebug("Processing Loan EMI payment", [
            'customer_id' => $customer_id,
            'payment_amount' => $total_payment_amount,
            'notes' => $notes
        ]);

        // Handle other payment types (Loan EMI, Scheme Installment, etc.)
        $insertPaymentQuery = "INSERT INTO jewellery_payments (reference_id, reference_type, party_type, party_id, sale_id, payment_type, amount, payment_notes, reference_no, remarks, created_at, transctions_type, Firm_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertPaymentStmt = $conn->prepare($insertPaymentQuery);
        if (!$insertPaymentStmt) {
            throw new Exception("Payment insert preparation failed: " . $conn->error);
        }

        $reference_id = NULL;
        $sale_id = 0;
        $payment_remarks_general = "General payment - " . $payment_type_form; // New variable for general remarks
        $transactions_type_general = $transactions_type; // New variable for general transactions_type

        $insertPaymentStmt->bind_param("issisdsssssii",
            $reference_id,
            $payment_type_form,
            $party_type,
            $customer_id,
            $sale_id,
            $payment_method_form,
            $total_payment_amount,
            $notes,
            $reference_no,
            $payment_remarks_general, // Use new remarks variable
            $created_at,
            $transactions_type_general, // Use new transactions_type variable
            $firm_id
        );
        
        $insertPaymentStmt->execute();
        
        logDebug("General payment record inserted", [
            'affected_rows' => $insertPaymentStmt->affected_rows,
            'insert_id' => $insertPaymentStmt->insert_id,
            'amount' => $total_payment_amount
        ]);
        
        if ($insertPaymentStmt->affected_rows === 0) {
            throw new Exception("Failed to insert payment record");
        }
        $insertPaymentStmt->close();
    } else {
        logDebug("Processing non-Sale Due payment or no allocations");
        
        // Handle other payment types (Loan EMI, Scheme Installment, etc.)
        $insertPaymentQuery = "INSERT INTO jewellery_payments (reference_id, reference_type, party_type, party_id, sale_id, payment_type, amount, payment_notes, reference_no, remarks, created_at, transctions_type, Firm_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertPaymentStmt = $conn->prepare($insertPaymentQuery);
        if (!$insertPaymentStmt) {
            throw new Exception("Payment insert preparation failed: " . $conn->error);
        }

        $reference_id = NULL;
        $sale_id = 0;
        $payment_remarks_general = "General payment - " . $payment_type_form; // New variable for general remarks
        $transactions_type_general = $transactions_type; // New variable for general transactions_type

        $insertPaymentStmt->bind_param("issisdsssssii",
            $reference_id,
            $payment_type_form,
            $party_type,
            $customer_id,
            $sale_id,
            $payment_method_form,
            $total_payment_amount,
            $notes,
            $reference_no,
            $payment_remarks_general, // Use new remarks variable
            $created_at,
            $transactions_type_general, // Use new transactions_type variable
            $firm_id
        );
        
        $insertPaymentStmt->execute();
        
        logDebug("General payment record inserted", [
            'affected_rows' => $insertPaymentStmt->affected_rows,
            'insert_id' => $insertPaymentStmt->insert_id,
            'amount' => $total_payment_amount
        ]);
        
        if ($insertPaymentStmt->affected_rows === 0) {
            throw new Exception("Failed to insert payment record");
        }
        $insertPaymentStmt->close();
    }

    // Commit transaction
    if (!$conn->commit()) {
        throw new Exception("Failed to commit transaction: " . $conn->error);
    }
    
    logDebug("=== TRANSACTION COMMITTED SUCCESSFULLY ===");
    
    $success_message = "Payment of ₹" . number_format($total_payment_amount, 2) . " recorded successfully for " . $customerInfo['FirstName'] . " " . $customerInfo['LastName'];
    
    logDebug("Payment processing completed successfully", [
        'customer_name' => $customerInfo['FirstName'] . " " . $customerInfo['LastName'],
        'amount' => $total_payment_amount,
        'payment_type' => $payment_type_form,
        'payment_method' => $payment_method_form
    ]);
    
    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $success_message
    ]);
    exit();

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    logError("Transaction rolled back due to error", [
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'error_trace' => $e->getTraceAsString()
    ]);
    
    // Send JSON error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Payment processing failed: " . $e->getMessage()
    ]);
    exit();
}

$conn->close();
logDebug("=== PAYMENT PROCESSING ENDED ===");
?>
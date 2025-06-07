<?php
session_start();
require 'config/config.php';

// Enable comprehensive error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'payment_errors.log');

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
    'firm_id' => $_SESSION['firm_id'] ?? 'not set'
]);

// Check if user is logged in and firm ID is set
if (!isset($_SESSION['id']) || !isset($_SESSION['firm_id'])) {
    logError("Authentication failed - missing session data");
    header("Location: login.php");
    exit();
}

$firm_id = $_SESSION['firm_id'];
$user_id = $_SESSION['id'];
logDebug("Authentication successful", ['user_id' => $user_id, 'firm_id' => $firm_id]);

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    logError("Invalid request method", ['method' => $_SERVER["REQUEST_METHOD"]]);
    header("Location: customer.php");
    exit();
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
    header("Location: customer_details.php?id=" . $customer_id . "&error=" . urlencode($error_message));
    exit();
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
    header("Location: customer_details.php?id=" . $customer_id . "&error=" . urlencode("Database connection failed. Please try again."));
    exit();
}

// Set charset for proper handling of special characters
$conn->set_charset("utf8");
logDebug("Database connected successfully");

// Start transaction with enhanced error handling
if (!$conn->begin_transaction()) {
    logError("Failed to start transaction", ['error' => $conn->error]);
    $conn->close();
    header("Location: customer_details.php?id=" . $customer_id . "&error=" . urlencode("Transaction initialization failed"));
    exit();
}

logDebug("Database transaction started");

try {
    // Verify customer exists and belongs to firm
    $customerCheckQuery = "SELECT id, FirstName, LastName FROM customer WHERE id = ? AND firm_id = ?";
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
                
                // Enhanced validation
                if ($allocated_amount > $current_due + 0.01) { // Allow small floating point differences
                    throw new Exception("Allocated amount ₹" . number_format($allocated_amount, 2) . " exceeds due amount ₹" . number_format($current_due, 2) . " for Sale #$sale_id");
                }
                
                $new_due = max(0, $current_due - $allocated_amount); // Ensure non-negative
                $totalAllocated += $allocated_amount;

                logDebug("Allocation calculation completed", [
                    'sale_id' => $sale_id,
                    'current_due' => $current_due,
                    'allocated_amount' => $allocated_amount,
                    'new_due' => $new_due,
                    'total_allocated_so_far' => $totalAllocated
                ]);

                // Determine new payment status
                $new_status = ($new_due <= 0.01) ? 'Fully Paid' : 'Partial';
                
                // Update jewellery_sales table
                $updateSaleQuery = "UPDATE jewellery_sales SET due_amount = ?, payment_status = ?, updated_at = NOW() WHERE id = ? AND customer_id = ? AND firm_id = ?";
                $updateSaleStmt = $conn->prepare($updateSaleQuery);
                if (!$updateSaleStmt) {
                    throw new Exception("Sale update preparation failed: " . $conn->error);
                }

                $updateSaleStmt->bind_param("dsiii", $new_due, $new_status, $sale_id, $customer_id, $firm_id);
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
                $insertPaymentQuery = "INSERT INTO jewellery_payments (reference_id, reference_type, party_type, party_id, sale_id, payment_type, amount, payment_notes, reference_no, remarks, created_at, transctions_type, firm_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insertPaymentStmt = $conn->prepare($insertPaymentQuery);
                if (!$insertPaymentStmt) {
                    throw new Exception("Payment insert preparation failed: " . $conn->error);
                }

                $payment_remarks = "FIFO allocation for Sale #$sale_id";
                $insertPaymentStmt->bind_param("issiisdsssssii",
                    $sale_id,                    // reference_id
                    $payment_type_form,          // reference_type
                    $party_type,                 // party_type
                    $customer_id,                // party_id
                    $sale_id,                    // sale_id
                    $payment_method_form,        // payment_type
                    $allocated_amount,           // amount
                    $notes,                      // payment_notes
                    $reference_no,               // reference_no
                    $payment_remarks,            // remarks
                    $created_at,                 // created_at
                    $transactions_type,          // transctions_type
                    $firm_id                     // firm_id
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
        
    } else {
        logDebug("Processing non-Sale Due payment or no allocations");
        
        // Handle other payment types (Loan EMI, Scheme Installment, etc.)
        $insertPaymentQuery = "INSERT INTO jewellery_payments (reference_id, reference_type, party_type, party_id, sale_id, payment_type, amount, payment_notes, reference_no, remarks, created_at, transctions_type, firm_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertPaymentStmt = $conn->prepare($insertPaymentQuery);
        if (!$insertPaymentStmt) {
            throw new Exception("Payment insert preparation failed: " . $conn->error);
        }

        $reference_id = NULL;
        $sale_id = 0;
        $payment_remarks = "General payment - " . $payment_type_form;

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
            $payment_remarks,
            $created_at,
            $transactions_type,
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
    
    header("Location: customer_details.php?id=" . $customer_id . "&success=" . urlencode($success_message));

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    logError("Transaction rolled back due to error", [
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine()
    ]);
    
    $error_message = "Payment processing failed: " . $e->getMessage();
    header("Location: customer_details.php?id=" . $customer_id . "&error=" . urlencode($error_message));
}

$conn->close();
logDebug("=== PAYMENT PROCESSING ENDED ===");
?>

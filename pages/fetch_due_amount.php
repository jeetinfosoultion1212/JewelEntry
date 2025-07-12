<?php
session_start();
require 'config/config.php';

// Enable comprehensive error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'fetch_due_errors.log');

function logDebug($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] FETCH DUE: $message";
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data, JSON_PRETTY_PRINT);
    }
    error_log($logMessage);
}

function logError($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] FETCH DUE ERROR: $message";
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data, JSON_PRETTY_PRINT);
    }
    error_log($logMessage);
}

// Set proper JSON header
header('Content-Type: application/json; charset=utf-8');

logDebug("=== FETCH DUE AMOUNT REQUEST STARTED ===");
logDebug("POST Data", $_POST);
logDebug("Session Data", [
    'user_id' => $_SESSION['id'] ?? 'not set',
    'firm_id' => $_SESSION['firmID'] ?? 'not set'
]);

// Check authentication
if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    logError("Authentication failed");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$firm_id = $_SESSION['firmID'];
$user_id = $_SESSION['id'];
logDebug("Authentication successful", ['user_id' => $user_id, 'firm_id' => $firm_id]);

// Validate request method and parameters
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    logError("Invalid request method", ['method' => $_SERVER["REQUEST_METHOD"]]);
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

if (!isset($_POST['customer_id']) || !isset($_POST['payment_type'])) {
    logError("Missing required parameters", [
        'has_customer_id' => isset($_POST['customer_id']),
        'has_payment_type' => isset($_POST['payment_type'])
    ]);
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

$customer_id = intval($_POST['customer_id']);
$payment_type = trim($_POST['payment_type']);

// Validate inputs
if ($customer_id <= 0) {
    logError("Invalid customer ID", ['customer_id' => $customer_id]);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid customer ID']);
    exit();
}

if (empty($payment_type)) {
    logError("Empty payment type");
    http_response_code(400);
    echo json_encode(['error' => 'Payment type is required']);
    exit();
}

logDebug("Request validation passed", [
    'customer_id' => $customer_id,
    'payment_type' => $payment_type
]);

// Database connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    logError("Database connection failed", ['error' => $conn->connect_error]);
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$conn->set_charset("utf8");
logDebug("Database connected successfully");

// Verify customer exists and belongs to firm
$customerCheckQuery = "SELECT id, FirstName, LastName FROM customer WHERE id = ? AND firm_id = ?";
$customerCheckStmt = $conn->prepare($customerCheckQuery);

if (!$customerCheckStmt) {
    logError("Customer check query preparation failed", ['error' => $conn->error]);
    http_response_code(500);
    echo json_encode(['error' => 'Database query preparation failed']);
    $conn->close();
    exit();
}

$customerCheckStmt->bind_param("ii", $customer_id, $firm_id);
$customerCheckStmt->execute();
$customerResult = $customerCheckStmt->get_result();

if ($customerResult->num_rows === 0) {
    logError("Customer not found or access denied", [
        'customer_id' => $customer_id,
        'firm_id' => $firm_id
    ]);
    http_response_code(404);
    echo json_encode(['error' => 'Customer not found']);
    $customerCheckStmt->close();
    $conn->close();
    exit();
}

$customerInfo = $customerResult->fetch_assoc();
$customerCheckStmt->close();
logDebug("Customer verification successful", $customerInfo);

$due_amount = 0;
$outstanding_items = [];

try {
    switch ($payment_type) {
        case 'Sale Due':
            logDebug("Processing Sale Due request");
            
            // Fetch individual outstanding sales ordered by date (FIFO)
            $query = "SELECT id, sale_date, grand_total, due_amount, payment_status 
                     FROM jewellery_sales 
                     WHERE customer_id = ? AND firm_id = ? 
                     AND payment_status IN ('Unpaid','Partial') 
                     AND due_amount > 0.01 
                     ORDER BY sale_date ASC, id ASC";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Sale query preparation failed: " . $conn->error);
            }
            
            logDebug("Sale query prepared", ['query' => $query]);
            
            $stmt->bind_param("ii", $customer_id, $firm_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            logDebug("Sale query executed", ['num_rows' => $result->num_rows]);
            
            while ($row = $result->fetch_assoc()) {
                $item = [
                    'id' => intval($row['id']),
                    'date' => date('d M, Y', strtotime($row['sale_date'])),
                    'total' => number_format($row['grand_total'], 2),
                    'due' => number_format($row['due_amount'], 2, '.', ''),
                    'status' => $row['payment_status']
                ];
                $outstanding_items[] = $item;
                logDebug("Added outstanding sale item", $item);
            }
            $stmt->close();
            
            logDebug("Sale Due processing completed", [
                'total_items' => count($outstanding_items),
                'total_due' => array_sum(array_column($outstanding_items, 'due'))
            ]);
            break;
            
        case 'Loan EMI':
            logDebug("Processing Loan EMI request (loan_emi join)");
            // Fetch all EMIs for this customer from loan_emi, joining with loans
            $query = "SELECT le.id, le.loan_id, le.emi_number, le.due_date, le.amount, le.principal_component, le.interest_component, le.remaining_principal, le.status, le.created_at, IFNULL(lp.amount_paid, 0) as amount_paid
                      FROM loan_emi le
                      JOIN loans l ON le.loan_id = l.id
                      LEFT JOIN (
                        SELECT emi_id, SUM(amount) as amount_paid
                        FROM loan_emi_payments
                        GROUP BY emi_id
                      ) lp ON le.id = lp.emi_id
                      WHERE l.customer_id = ? AND le.status IN ('PENDING', 'PARTIAL', 'OVERDUE')
                      ORDER BY le.due_date ASC, le.id ASC";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("EMI list query preparation failed: " . $conn->error);
            }
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $due = floatval($row['amount']) - floatval($row['amount_paid']);
                if ($due < 0.01) continue; // Skip if already paid
                $outstanding_items[] = [
                    'emi_id' => $row['id'],
                    'loan_id' => $row['loan_id'],
                    'emi_number' => $row['emi_number'],
                    'due_date' => date('d M, Y', strtotime($row['due_date'])),
                    'amount' => floatval($row['amount']),
                    'principal_component' => floatval($row['principal_component']),
                    'interest_component' => floatval($row['interest_component']),
                    'amount_paid' => floatval($row['amount_paid']),
                    'remaining_principal' => floatval($row['remaining_principal']),
                    'due' => round($due, 2),
                    'status' => $row['status'],
                    'created_at' => $row['created_at']
                ];
            }
            $stmt->close();
            logDebug("Loan EMI list processing completed (loan_emi)", [
                'total_items' => count($outstanding_items)
            ]);
            break;
            
        case 'Scheme Installment':
            logDebug("Processing Scheme Installment request");

            // Fetch all due/unpaid installments from gold_plan_installments for this customer
            $query = "SELECT 
                        gpi.id AS installment_id,
                        gpi.customer_plan_id,
                        gpi.payment_date,
                        gpi.amount_paid,
                        gsp.min_amount_per_installment,
                        cgp.plan_id,
                        gsp.plan_name,
                        MONTH(gpi.payment_date) AS due_month,
                        YEAR(gpi.payment_date) AS due_year
                    FROM gold_plan_installments gpi
                    JOIN customer_gold_plans cgp ON gpi.customer_plan_id = cgp.id
                    JOIN gold_saving_plans gsp ON cgp.plan_id = gsp.id
                    WHERE cgp.customer_id = ?
                      AND (gpi.amount_paid IS NULL OR gpi.amount_paid < gsp.min_amount_per_installment)
                    ORDER BY gpi.payment_date ASC";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Installment query preparation failed: " . $conn->error);
            }
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $due = floatval($row['min_amount_per_installment']) - floatval($row['amount_paid']);
                if ($due < 0.01) continue; // Skip if already paid
                $outstanding_items[] = [
                    'installment_id' => $row['installment_id'],
                    'customer_plan_id' => $row['customer_plan_id'],
                    'plan_name' => $row['plan_name'],
                    'installment_date' => date('d M, Y', strtotime($row['payment_date'])),
                    'amount' => floatval($row['min_amount_per_installment']),
                    'due' => round($due, 2),
                    'paid_current_installment' => floatval($row['amount_paid']),
                    'due_month' => $row['due_month'],
                    'due_year' => $row['due_year'],
                    'status' => ($row['payment_date'] < date('Y-m-d')) ? 'due' : 'upcoming'
                ];
            }
            $stmt->close();
            logDebug("Scheme Installment processing completed", [
                'total_items' => count($outstanding_items)
            ]);
            break;
            
        case 'Loan Principal':
            logDebug("Processing Loan Principal request");

            // Fetch active loans for principal payment
            $query = "SELECT id, principal_amount, outstanding_amount, loan_date 
                     FROM loans 
                     WHERE customer_id = ? AND firm_id = ? 
                     AND current_status = 'active' AND outstanding_amount > 0.01
                     ORDER BY loan_date ASC, id ASC";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Loan principal query preparation failed: " . $conn->error);
            }

            logDebug("Loan principal query prepared", ['query' => $query]);

            $stmt->bind_param("ii", $customer_id, $firm_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $item = [
                    'id' => intval($row['id']),
                    'date' => date('d M, Y', strtotime($row['loan_date'])),
                    'principal_amount' => number_format($row['outstanding_amount'], 2, '.', ''), // Use outstanding_amount for due
                    'total_principal' => number_format($row['principal_amount'], 2),
                    'status' => 'active' // Or 'due', 'partial' based on outstanding
                ];
                $outstanding_items[] = $item;
                logDebug("Added outstanding loan principal item", $item);
            }
            $stmt->close();

            logDebug("Loan Principal processing completed", ['total_items' => count($outstanding_items)]);
            break;
            
        case 'Other':
            logDebug("Processing Other payment type");
            $due_amount = 0; // For 'Other', there's no predefined due amount
            break;
            
        default:
            logError("Invalid payment type requested", ['payment_type' => $payment_type]);
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payment type']);
            $conn->close();
            exit();
    }

    logDebug("Responding with outstanding items", ['count' => count($outstanding_items)]);
    if (!empty($outstanding_items)) {
        echo json_encode(['outstanding_items' => $outstanding_items]);
    } else {
        echo json_encode(['due_amount' => $due_amount]);
    }

} catch (Exception $e) {
    logError("Error fetching due amount", [
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'error_trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while fetching due amounts.']);
} finally {
    if ($conn) {
        $conn->close();
        logDebug("Database connection closed");
    }
}

logDebug("=== FETCH DUE AMOUNT REQUEST ENDED ===");
?>
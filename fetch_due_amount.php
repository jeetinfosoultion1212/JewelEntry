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
            logDebug("Processing Loan EMI request");
            
            // Fetch EMI due for current month
            $query = "SELECT SUM(amount) as emi_due 
                     FROM loan_emis 
                     WHERE customer_id = ? 
                     AND status = 'due' 
                     AND MONTH(due_date) = MONTH(CURDATE()) 
                     AND YEAR(due_date) = YEAR(CURDATE())";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("EMI query preparation failed: " . $conn->error);
            }
            
            logDebug("EMI query prepared");
            
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $due_amount = floatval($row['emi_due'] ?? 0);
                logDebug("EMI due amount calculated", ['due_amount' => $due_amount]);
            }
            $stmt->close();
            break;
            
        case 'Scheme Installment':
            logDebug("Processing Scheme Installment request");
            
            // Fetch active gold saving plans and their details
            $planQuery = "SELECT 
                            cgp.id AS customer_plan_id,
                            cgp.enrollment_date,
                            cgp.total_amount_paid,
                            gsp.plan_name,
                            gsp.duration_months,
                            gsp.min_amount_per_installment
                          FROM customer_gold_plans cgp 
                          JOIN gold_saving_plans gsp ON cgp.plan_id = gsp.id 
                          WHERE cgp.customer_id = ? AND cgp.current_status = 'active' 
                          ORDER BY cgp.enrollment_date ASC";
            
            $planStmt = $conn->prepare($planQuery);
            if (!$planStmt) {
                throw new Exception("Scheme query preparation failed: " . $conn->error);
            }
            
            $planStmt->bind_param("i", $customer_id);
            $planStmt->execute();
            $planResult = $planStmt->get_result();
            
            $current_overdue_group = null;

            while ($plan = $planResult->fetch_assoc()) {
                $customer_plan_id = $plan['customer_plan_id'];
                $enrollment_date = new DateTime($plan['enrollment_date']);
                $duration_months = $plan['duration_months'];
                $min_amount_per_installment = floatval($plan['min_amount_per_installment']);
                $total_amount_paid_on_plan = floatval($plan['total_amount_paid']);

                // Calculate how many installments have been notionally covered by total_amount_paid
                $covered_installments_count = floor($total_amount_paid_on_plan / $min_amount_per_installment);
                $remainder_paid = $total_amount_paid_on_plan - ($covered_installments_count * $min_amount_per_installment);

                $today = new DateTime();

                // Iterate through each installment period
                for ($i = 1; $i <= $duration_months; $i++) {
                    $installment_date = clone $enrollment_date;
                    $installment_date->modify("+$i months"); // Due date for i-th installment

                    $status = 'upcoming';
                    $due_for_this_installment = $min_amount_per_installment;
                    $paid_for_this_installment = 0;

                    if ($i <= $covered_installments_count) {
                        // This installment is fully paid by previous payments
                        $status = 'paid';
                        $due_for_this_installment = 0;
                        $paid_for_this_installment = $min_amount_per_installment;
                    } else if ($i == $covered_installments_count + 1 && $remainder_paid > 0) {
                        // This is the partially paid installment
                        $status = 'partial';
                        $paid_for_this_installment = $remainder_paid;
                        $due_for_this_installment = $min_amount_per_installment - $remainder_paid;
                    } else {
                        // Check if installment is due/overdue
                        if ($installment_date <= $today) {
                            $status = 'due';
                        }
                    }

                    // Only add if not fully paid, or if it's an upcoming installment we want to show
                    if ($due_for_this_installment > 0.01 || $status === 'upcoming' || $status === 'due' || $status === 'partial') {
                        $outstanding_items[] = [
                            'id' => $customer_plan_id,
                            'plan_name' => $plan['plan_name'],
                            'installment_number' => $i,
                            'installment_date' => $installment_date->format('d M, Y'),
                            'amount' => $min_amount_per_installment,
                            'due' => round($due_for_this_installment, 2),
                            'paid_current_installment' => round($paid_for_this_installment, 2),
                            'status' => $status
                        ];
                    }
                }
            }
            logDebug("Scheme Installment processing completed", [
                'total_items' => count($outstanding_items),
                'total_due_items' => array_sum(array_column($outstanding_items, 'due'))
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
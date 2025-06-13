<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config/config.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$firm_id = $_SESSION['firmID'];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

try {
    $query = "SELECT 
        l.id,
        l.principal_amount,
        l.interest_rate,
        l.loan_term_months,
        l.current_status as status,
        l.emi_amount,
        l.created_at,
        l.total_amount_paid,
        l.collateral_value,
        l.collateral_description,
        c.name as customer_name,
        c.phone as customer_phone,
        fu.Name as created_by_name,
        (
            SELECT due_date 
            FROM loan_emi 
            WHERE loan_id = l.id 
            AND status = 'PENDING' 
            ORDER BY due_date ASC 
            LIMIT 1
        ) as next_emi_due_date,
        (
            SELECT COUNT(*) 
            FROM loan_emi 
            WHERE loan_id = l.id 
            AND status = 'PENDING'
        ) as pending_emis,
        (
            SELECT COUNT(*) 
            FROM loan_emi 
            WHERE loan_id = l.id 
            AND status = 'PAID'
        ) as paid_emis
    FROM loans l
    JOIN customers c ON c.id = l.customer_id
    JOIN Firm_Users fu ON fu.id = l.created_by
    WHERE l.firm_id = ?";

    $params = [$firm_id];
    $types = "i";

    if (!empty($search)) {
        $query .= " AND (c.name LIKE ? OR c.phone LIKE ? OR l.id LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }

    if (!empty($status)) {
        $query .= " AND l.current_status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $query .= " ORDER BY l.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("API/get_loans.php prepare failed: " . $conn->error);
        throw new Exception("Database error (prepare): " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        error_log("API/get_loans.php execute failed: " . $stmt->error);
        throw new Exception("Database error (execute): " . $stmt->error);
    }

    $result = $stmt->get_result();
    $loans = [];

    while ($row = $result->fetch_assoc()) {
        // Format dates
        $row['created_at'] = date('Y-m-d', strtotime($row['created_at']));
        $row['next_emi_due_date'] = $row['next_emi_due_date'] ? date('Y-m-d', strtotime($row['next_emi_due_date'])) : null;
        
        // Calculate progress
        $row['progress'] = $row['loan_term_months'] > 0 ? 
            round(($row['paid_emis'] / $row['loan_term_months']) * 100) : 0;
        
        $loans[] = $row;
    }

    echo json_encode($loans);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?> 
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require '../config/config.php'; 
session_start();

$response = ['success' => false, 'message' => 'An error occurred.'];

if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    $response['message'] = 'User not authenticated.';
    echo json_encode($response);
    exit;
}

$firm_id = $_SESSION['firmID'];
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Validate dates
if (!DateTime::createFromFormat('Y-m-d', $start_date) || !DateTime::createFromFormat('Y-m-d', $end_date)) {
    $response['message'] = 'Invalid date format. Please use YYYY-MM-DD.';
    echo json_encode($response);
    exit;
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $data = [
        'summary' => [
            'total_revenue' => 0,
            'total_expenses' => 0,
            'items_sold_count' => 0,
            'items_sold_weight' => 0,
            'items_added_count' => 0,
            'items_added_weight' => 0,
        ],
        'cash_flow' => [
            'income' => [],
            'expenses' => [],
        ],
        'inventory' => [
            'stock_in' => [],
            'stock_out' => [],
        ],
        'balances' => [
            'cash' => ['in' => 0, 'out' => 0],
            'bank' => ['in' => 0, 'out' => 0],
            'upi'  => ['in' => 0, 'out' => 0]
        ],
        'charts' => [
            'sales_over_time' => []
        ]
    ];

    // --- SUMMARY ---
    // Total Revenue
    $revQuery = "SELECT COALESCE(SUM(grand_total), 0) as total_sales FROM jewellery_sales WHERE firm_id = ? AND DATE(created_at) BETWEEN ? AND ?";
    $revStmt = $conn->prepare($revQuery);
    $revStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $revStmt->execute();
    $data['summary']['total_revenue'] = $revStmt->get_result()->fetch_assoc()['total_sales'];

    // Total Expenses
    $expQuery = "SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses WHERE firm_id = ? AND DATE(date) BETWEEN ? AND ?";
    $expStmt = $conn->prepare($expQuery);
    $expStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $expStmt->execute();
    $data['summary']['total_expenses'] = $expStmt->get_result()->fetch_assoc()['total_expenses'];
    
    // Items Sold
    $soldQuery = "SELECT COUNT(jsi.id) as total_count, COALESCE(SUM(jsi.gross_weight), 0) as total_weight 
                  FROM jewellery_sales_items jsi 
                  JOIN jewellery_sales js ON jsi.sale_id = js.id 
                  WHERE js.firm_id = ? AND DATE(js.created_at) BETWEEN ? AND ?";
    $soldStmt = $conn->prepare($soldQuery);
    $soldStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $soldStmt->execute();
    $soldResult = $soldStmt->get_result()->fetch_assoc();
    $data['summary']['items_sold_count'] = $soldResult['total_count'];
    $data['summary']['items_sold_weight'] = $soldResult['total_weight'];

    // Items Added
    $addedQuery = "SELECT COUNT(id) as total_count, COALESCE(SUM(gross_weight), 0) as total_weight FROM jewellery_items WHERE firm_id = ? AND DATE(created_at) BETWEEN ? AND ?";
    $addedStmt = $conn->prepare($addedQuery);
    $addedStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $addedStmt->execute();
    $addedResult = $addedStmt->get_result()->fetch_assoc();
    $data['summary']['items_added_count'] = $addedResult['total_count'];
    $data['summary']['items_added_weight'] = $addedResult['total_weight'];


    // --- CASH FLOW DETAILS ---
    // Income
    $incomeQuery = "SELECT js.id, js.created_at, CONCAT(c.FirstName, ' ', c.LastName) AS customer_name, js.grand_total, js.payment_status 
                    FROM jewellery_sales js 
                    LEFT JOIN customer c ON js.customer_id = c.id 
                    WHERE js.firm_id = ? AND DATE(js.created_at) BETWEEN ? AND ?
                    ORDER BY js.created_at DESC";
    $incomeStmt = $conn->prepare($incomeQuery);
    $incomeStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $incomeStmt->execute();
    $incomeResult = $incomeStmt->get_result();
    while($row = $incomeResult->fetch_assoc()) {
        $data['cash_flow']['income'][] = $row;
    }

    // Expenses
    $expensesQuery = "SELECT date, category, amount, description FROM expenses WHERE firm_id = ? AND DATE(date) BETWEEN ? AND ? ORDER BY date DESC";
    $expensesStmt = $conn->prepare($expensesQuery);
    $expensesStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $expensesStmt->execute();
    $expensesResult = $expensesStmt->get_result();
    while($row = $expensesResult->fetch_assoc()) {
        $data['cash_flow']['expenses'][] = $row;
    }

    // --- INVENTORY DETAILS ---
    // Stock In
    $stockInQuery = "SELECT ji.product_id, ji.product_name, ji.created_at, ji.gross_weight, s.name as supplier_name
                     FROM jewellery_items ji 
                     LEFT JOIN suppliers s ON ji.supplier_id = s.id 
                     WHERE ji.firm_id = ? AND DATE(ji.created_at) BETWEEN ? AND ?
                     ORDER BY ji.created_at DESC";
    $stockInStmt = $conn->prepare($stockInQuery);
    $stockInStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $stockInStmt->execute();
    $stockInResult = $stockInStmt->get_result();
    while($row = $stockInResult->fetch_assoc()) {
        $data['inventory']['stock_in'][] = $row;
    }

    // Stock Out
    $stockOutQuery = "SELECT jsi.product_id, jsi.product_name, js.created_at, jsi.gross_weight, jsi.sale_id 
                      FROM jewellery_sales_items jsi 
                      JOIN jewellery_sales js ON jsi.sale_id = js.id 
                      WHERE js.firm_id = ? AND DATE(js.created_at) BETWEEN ? AND ?
                      ORDER BY js.created_at DESC";
    $stockOutStmt = $conn->prepare($stockOutQuery);
    $stockOutStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $stockOutStmt->execute();
    $stockOutResult = $stockOutStmt->get_result();
    while($row = $stockOutResult->fetch_assoc()) {
        $data['inventory']['stock_out'][] = $row;
    }

    // --- PAYMENT BALANCES ---
    // Payment IN (credits)
    $inQuery = "SELECT payment_type, SUM(amount) as total FROM jewellery_payments WHERE Firm_id = ? AND transctions_type = 'credit' AND DATE(created_at) BETWEEN ? AND ? GROUP BY payment_type";
    $inStmt = $conn->prepare($inQuery);
    $inStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $inStmt->execute();
    $inResult = $inStmt->get_result();
    while ($row = $inResult->fetch_assoc()) {
        $type = strtolower($row['payment_type']);
        $amt = floatval($row['total']);
        if ($type === 'cash') $data['balances']['cash']['in'] += $amt;
        elseif ($type === 'upi') $data['balances']['upi']['in'] += $amt;
        elseif (in_array($type, ['bank', 'bank_transfer', 'card'])) $data['balances']['bank']['in'] += $amt;
    }

    // Payment OUT (debits)
    $outQuery = "SELECT payment_type, SUM(amount) as total FROM jewellery_payments WHERE Firm_id = ? AND transctions_type = 'debit' AND DATE(created_at) BETWEEN ? AND ? GROUP BY payment_type";
    $outStmt = $conn->prepare($outQuery);
    $outStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $outStmt->execute();
    $outResult = $outStmt->get_result();
    while ($row = $outResult->fetch_assoc()) {
        $type = strtolower($row['payment_type']);
        $amt = floatval($row['total']);
        if ($type === 'cash') $data['balances']['cash']['out'] += $amt;
        elseif ($type === 'upi') $data['balances']['upi']['out'] += $amt;
        elseif (in_array($type, ['bank', 'bank_transfer', 'card'])) $data['balances']['bank']['out'] += $amt;
    }

    // --- CHART DATA ---
    // Sales over time for chart
    $salesChartQuery = "SELECT DATE(created_at) as sale_date, SUM(grand_total) as daily_sales 
                        FROM jewellery_sales 
                        WHERE firm_id = ? AND DATE(created_at) BETWEEN ? AND ?
                        GROUP BY DATE(created_at)
                        ORDER BY sale_date ASC";
    $salesChartStmt = $conn->prepare($salesChartQuery);
    $salesChartStmt->bind_param("iss", $firm_id, $start_date, $end_date);
    $salesChartStmt->execute();
    $salesChartResult = $salesChartStmt->get_result();
    while($row = $salesChartResult->fetch_assoc()) {
        $data['charts']['sales_over_time'][] = $row;
    }

    $conn->close();
    $response['success'] = true;
    $response['message'] = 'Data fetched successfully.';
    $response['data'] = $data;

} catch (Exception $e) {
    $response['message'] = 'Server Error: ' . $e->getMessage();
}

echo json_encode($response); 
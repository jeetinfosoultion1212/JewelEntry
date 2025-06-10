<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Initialize response array
$response = [
    'inventory_metals' => [],
    'jewellery_items' => [],
    'jewellery_sales' => [],
    'customer_orders' => [],
    'customers' => [],
    'karigars' => []
];

try {
    // 1. Load Inventory Metals
    $metalsQuery = "SELECT * FROM inventory_metals WHERE firm_id = ?";
    $metalsStmt = $conn->prepare($metalsQuery);
    $metalsStmt->bind_param("i", $firm_id);
    $metalsStmt->execute();
    $metalsResult = $metalsStmt->get_result();
    while ($row = $metalsResult->fetch_assoc()) {
        $response['inventory_metals'][] = $row;
    }

    // 2. Load Jewellery Items
    $itemsQuery = "SELECT * FROM jewellery_items WHERE firm_id = ?";
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bind_param("i", $firm_id);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    while ($row = $itemsResult->fetch_assoc()) {
        $response['jewellery_items'][] = $row;
    }

    // 3. Load Jewellery Sales
    $salesQuery = "SELECT * FROM jewellery_sales WHERE firm_id = ?";
    $salesStmt = $conn->prepare($salesQuery);
    $salesStmt->bind_param("i", $firm_id);
    $salesStmt->execute();
    $salesResult = $salesStmt->get_result();
    while ($row = $salesResult->fetch_assoc()) {
        $response['jewellery_sales'][] = $row;
    }

    // 4. Load Customer Orders
    $ordersQuery = "SELECT * FROM jewellery_customer_order WHERE firm_id = ?";
    $ordersStmt = $conn->prepare($ordersQuery);
    $ordersStmt->bind_param("i", $firm_id);
    $ordersStmt->execute();
    $ordersResult = $ordersStmt->get_result();
    while ($row = $ordersResult->fetch_assoc()) {
        $response['customer_orders'][] = $row;
    }

    // 5. Load Customers
    $customersQuery = "SELECT * FROM customer WHERE firm_id = ?";
    $customersStmt = $conn->prepare($customersQuery);
    $customersStmt->bind_param("i", $firm_id);
    $customersStmt->execute();
    $customersResult = $customersStmt->get_result();
    while ($row = $customersResult->fetch_assoc()) {
        $response['customers'][] = $row;
    }

    // 6. Load Karigars
    $karigarsQuery = "SELECT * FROM karigars WHERE firm_id = ?";
    $karigarsStmt = $conn->prepare($karigarsQuery);
    $karigarsStmt->bind_param("i", $firm_id);
    $karigarsStmt->execute();
    $karigarsResult = $karigarsStmt->get_result();
    while ($row = $karigarsResult->fetch_assoc()) {
        $response['karigars'][] = $row;
    }

    // Set response headers
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error loading data: ' . $e->getMessage()]);
}

// Close database connection
$conn->close();
?> 
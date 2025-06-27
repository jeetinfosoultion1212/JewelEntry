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

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $data = [
        'jewelry_stock' => [],
        'inventory_stock' => []
    ];

    // Grouped jewelry stock by purity and product type
    $jewelryQuery = "SELECT purity, jewelry_type, COUNT(*) as item_count, SUM(gross_weight) as total_gross_weight, SUM(net_weight) as total_net_weight FROM jewellery_items WHERE firm_id = ? GROUP BY purity, jewelry_type ORDER BY purity DESC, jewelry_type";
    $jewelryStmt = $conn->prepare($jewelryQuery);
    $jewelryStmt->bind_param("i", $firm_id);
    $jewelryStmt->execute();
    $jewelryResult = $jewelryStmt->get_result();
    while ($row = $jewelryResult->fetch_assoc()) {
        $data['jewelry_stock'][] = $row;
    }

    // Grouped inventory metals by purity and material_type
    $inventoryQuery = "SELECT purity, material_type, COUNT(*) as lot_count, SUM(current_stock) as total_stock, SUM(remaining_stock) as remaining_stock FROM inventory_metals WHERE firm_id = ? GROUP BY purity, material_type ORDER BY purity DESC, material_type";
    $inventoryStmt = $conn->prepare($inventoryQuery);
    $inventoryStmt->bind_param("i", $firm_id);
    $inventoryStmt->execute();
    $inventoryResult = $inventoryStmt->get_result();
    while ($row = $inventoryResult->fetch_assoc()) {
        $row['stock_type'] = 'unallocated'; // Inventory metals are unallocated/fine
        $data['inventory_stock'][] = $row;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'recent') {
        // Get 10 most recent additions/removals from jewellery_items and inventory_metals
        $recent = [];
        // Jewellery items (additions)
        $jewelrySql = "SELECT created_at as date, 'Jewelry' as type, material_type, purity, gross_weight as weight, 'Added' as action FROM jewellery_items WHERE firm_id = ? ORDER BY created_at DESC LIMIT 5";
        $jewelryStmt = $conn->prepare($jewelrySql);
        $jewelryStmt->bind_param("i", $firm_id);
        $jewelryStmt->execute();
        $jewelryResult = $jewelryStmt->get_result();
        while ($row = $jewelryResult->fetch_assoc()) {
            $recent[] = $row;
        }
        // Inventory metals (additions/removals)
        $inventorySql = "SELECT last_updated as date, 'Inventory' as type, material_type, purity, remaining_stock as weight, 'Updated' as action FROM inventory_metals WHERE firm_id = ? ORDER BY last_updated DESC LIMIT 5";
        $inventoryStmt = $conn->prepare($inventorySql);
        $inventoryStmt->bind_param("i", $firm_id);
        $inventoryStmt->execute();
        $inventoryResult = $inventoryStmt->get_result();
        while ($row = $inventoryResult->fetch_assoc()) {
            $recent[] = $row;
        }
        // Sort by date desc and limit to 10
        usort($recent, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
        $recent = array_slice($recent, 0, 10);
        $response['success'] = true;
        $response['data'] = ['recent_stock' => $recent];
        echo json_encode($response);
        exit;
    }

    $conn->close();
    $response['success'] = true;
    $response['message'] = 'Stock report data fetched successfully.';
    $response['data'] = $data;

} catch (Exception $e) {
    $response['message'] = 'Server Error: ' . $e->getMessage();
}

echo json_encode($response); 
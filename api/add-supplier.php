<?php
header('Content-Type: application/json');
require_once '../config/config.php';

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['name']) || empty($data['name'])) {
    echo json_encode(['success' => false, 'message' => 'Supplier name is required']);
    exit;
}

if (!isset($data['firm_id']) || empty($data['firm_id'])) {
    echo json_encode(['success' => false, 'message' => 'Firm ID is required']);
    exit;
}

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Prepare the SQL statement
$sql = "INSERT INTO suppliers (firm_id, name, contact_info, email, address, phone, tax_id, payment_terms, notes, date_added, last_updated) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("issssssss", 
    $data['firm_id'],
    $data['name'],
    $data['contact_info'],
    $data['email'],
    $data['address'],
    $data['phone'],
    $data['tax_id'],
    $data['payment_terms'],
    $data['notes']
);

// Execute the statement
if ($stmt->execute()) {
    $supplier_id = $conn->insert_id;
    echo json_encode([
        'success' => true,
        'message' => 'Supplier added successfully',
        'supplier_id' => $supplier_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error adding supplier: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?> 
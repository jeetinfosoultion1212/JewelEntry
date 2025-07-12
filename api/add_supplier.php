<?php
session_start();
require_once '../config/db_connect.php';
header('Content-Type: application/json');
if (!isset($_SESSION['firmID'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}
$firm_id = $_SESSION['firmID'];
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;
$name = trim($data['name'] ?? '');
$contact_info = trim($data['contact_info'] ?? '');
$email = trim($data['email'] ?? '');
$address = trim($data['address'] ?? '');
$state = trim($data['state'] ?? '');
$phone = trim($data['phone'] ?? '');
$gstin = trim($data['gst'] ?? '');
$payment_terms = trim($data['payment_terms'] ?? '');
$notes = trim($data['notes'] ?? '');
if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Supplier name is required.']);
    exit();
}
$stmt = $conn->prepare("INSERT INTO suppliers (firm_id, name, contact_info, email, address, state, phone, gstin, payment_terms, notes, date_added, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
$stmt->bind_param('isssssssss', $firm_id, $name, $contact_info, $email, $address, $state, $phone, $gstin, $payment_terms, $notes);
if ($stmt->execute()) {
    $id = $conn->insert_id;
    echo json_encode([
        'success' => true,
        'supplier' => [
            'id' => $id,
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'gst' => $gstin
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add supplier.']);
} 
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config/config.php';

header('Content-Type: application/json');

$scheme_id = $_POST['scheme_id'] ?? null;
$firm_id = $_SESSION['firmID'] ?? null;

if (empty($scheme_id) || !is_numeric($scheme_id) || empty($firm_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid scheme ID or firm.']);
    exit();
}

// Collect fields to update
$scheme_name = $_POST['scheme_name'] ?? '';
$status = $_POST['status'] ?? '';
$entry_fee = $_POST['entry_fee'] ?? 0;
$min_purchase_amount = $_POST['min_purchase_amount'] ?? 0;
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$description = $_POST['description'] ?? '';

// Update scheme
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$sql = "UPDATE schemes SET scheme_name=?, status=?, entry_fee=?, min_purchase_amount=?, start_date=?, end_date=?, description=? WHERE id=? AND firm_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssddsssii", $scheme_name, $status, $entry_fee, $min_purchase_amount, $start_date, $end_date, $description, $scheme_id, $firm_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Scheme updated successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update scheme.']);
}

$stmt->close();
$conn->close();
?> 
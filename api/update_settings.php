<?php
header('Content-Type: application/json');
session_start();
require '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);
$firm_id = $_SESSION['firmID'];

// Validate required fields
$required_fields = [
    'loyalty_discount_percentage',
    'non_gst_bill_page_url', 
    'gst_bill_page_url', 
    'welcome_coupon_code'
];

foreach ($required_fields as $field) {
    // Check if field is not set OR (if set) if it is an empty string after trimming whitespace
    if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

// Convert checkbox values to 1 or 0
$checkbox_fields = [
    'coupon_code_apply_enabled',
    'schemes_enabled',
    'welcome_coupon_enabled',
    'auto_scheme_entry'
];

foreach ($checkbox_fields as $field) {
    $data[$field] = isset($data[$field]) && $data[$field] ? '1' : '0';
}

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if configuration exists for this firm
$checkQuery = "SELECT id FROM firm_configurations WHERE firm_id = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("i", $firm_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    // Update existing configuration
    $updateQuery = "UPDATE firm_configurations SET 
                   loyalty_discount_percentage = ?,
                   non_gst_bill_page_url = ?,
                   gst_bill_page_url = ?,
                   coupon_code_apply_enabled = ?,
                   schemes_enabled = ?,
                   welcome_coupon_enabled = ?,
                   welcome_coupon_code = ?,
                   auto_scheme_entry = ?,
                   updated_at = CURRENT_TIMESTAMP
                   WHERE firm_id = ?";
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("dssiiisii", 
        $data['loyalty_discount_percentage'],
        $data['non_gst_bill_page_url'],
        $data['gst_bill_page_url'],
        $data['coupon_code_apply_enabled'],
        $data['schemes_enabled'],
        $data['welcome_coupon_enabled'],
        $data['welcome_coupon_code'],
        $data['auto_scheme_entry'],
        $firm_id
    );
} else {
    // Insert new configuration
    $insertQuery = "INSERT INTO firm_configurations 
                   (firm_id, loyalty_discount_percentage,
                    non_gst_bill_page_url, gst_bill_page_url, coupon_code_apply_enabled,
                    schemes_enabled, welcome_coupon_enabled, welcome_coupon_code,
                    auto_scheme_entry) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("idssiiisii", 
        $firm_id,
        $data['loyalty_discount_percentage'],
        $data['non_gst_bill_page_url'],
        $data['gst_bill_page_url'],
        $data['coupon_code_apply_enabled'],
        $data['schemes_enabled'],
        $data['welcome_coupon_enabled'],
        $data['welcome_coupon_code'],
        $data['auto_scheme_entry']
    );
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update settings']);
}

$stmt->close();
$conn->close();
?> 
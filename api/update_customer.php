<?php
session_start();
require 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$firm_id = $_SESSION['firmID'];

// Get customer ID from form
$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

if ($customer_id <= 0) {
    header("Location: customer.php?error=Invalid+customer+ID");
    exit();
}

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Prepare the base update query
$updateFields = array();
$params = array();
$types = "";

// Add fields to update
$fields = array(
    'first_name' => 'FirstName',
    'last_name' => 'LastName',
    'phone' => 'PhoneNumber',
    'email' => 'Email',
    'address' => 'Address',
    'city' => 'City',
    'state' => 'State',
    'pan' => 'PANNumber',
    'aadhaar' => 'AadhaarNumber'
);

foreach ($fields as $formField => $dbField) {
    if (isset($_POST[$formField])) {
        $updateFields[] = "$dbField = ?";
        $params[] = $_POST[$formField];
        $types .= "s"; // string type for all fields
    }
}

// Handle image upload if present
if (isset($_FILES['customer_image']) && $_FILES['customer_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/customer_images/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($_FILES['customer_image']['name'], PATHINFO_EXTENSION);
    $newFilename = uniqid('customer_') . '.' . $fileExtension;
    $uploadPath = $uploadDir . $newFilename;
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['customer_image']['tmp_name'], $uploadPath)) {
        $updateFields[] = "CustomerImage = ?";
        $params[] = $uploadPath;
        $types .= "s";
    }
}

// Add customer ID to params
$params[] = $customer_id;
$params[] = $firm_id;
$types .= "ii"; // integer type for customer_id and firm_id

// Prepare and execute the update query
if (!empty($updateFields)) {
    $query = "UPDATE customer SET " . implode(", ", $updateFields) . " WHERE id = ? AND FirmID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        header("Location: customer_details.php?id=" . $customer_id . "&success=Customer+updated+successfully");
    } else {
        header("Location: customer_details.php?id=" . $customer_id . "&error=Failed+to+update+customer");
    }
    $stmt->close();
} else {
    header("Location: customer_details.php?id=" . $customer_id . "&error=No+changes+to+update");
}

$conn->close();
?> 
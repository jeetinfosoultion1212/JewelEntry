<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Handle POST request for adding new expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = ['category', 'amount', 'date', 'payment_method'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit();
        }
    }

    // Sanitize and validate input
    $category = $conn->real_escape_string($_POST['category']);
    $amount = floatval($_POST['amount']);
    $description = isset($_POST['description']) ? $conn->real_escape_string($_POST['description']) : '';
    $date = $conn->real_escape_string($_POST['date']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);

    // Handle receipt upload if required
    $receipt_path = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../public/uploads/receipts/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed types: JPG, JPEG, PNG, PDF']);
            exit();
        }

        $file_name = uniqid() . '.' . $file_extension;
        $receipt_path = 'public/uploads/receipts/' . $file_name;

        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_dir . $file_name)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload receipt']);
            exit();
        }
    }

    // Insert expense into database
    $query = "INSERT INTO expenses (firm_id, category, amount, description, date, payment_method, created_by, receipt_path) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isdsssss", $firm_id, $category, $amount, $description, $date, $payment_method, $user_id, $receipt_path);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Expense added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add expense']);
    }

    $stmt->close();
}

// Handle GET request for fetching expenses
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT e.*, u.Name as created_by_name 
              FROM expenses e 
              JOIN Firm_Users u ON e.created_by = u.id 
              WHERE e.firm_id = ? 
              ORDER BY e.date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $firm_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    
    echo json_encode(['success' => true, 'expenses' => $expenses]);
    $stmt->close();
}

$conn->close();
?> 
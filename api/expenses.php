<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// DB Connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = trim($_POST['category']);
    $amount = $_POST['amount'];
    $description = $_POST['description'] ?? '';
    $date = $_POST['date'];
    $payment_method = $_POST['payment_method'];
    $reference_no = $_POST['reference_no'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (empty($category_name) || empty($amount) || empty($date) || empty($payment_method)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
        exit();
    }

    // Check if category exists
    $catQuery = "SELECT id FROM expense_categories WHERE firm_id = ? AND name = ?";
    $catStmt = $conn->prepare($catQuery);
    $catStmt->bind_param("is", $firm_id, $category_name);
    $catStmt->execute();
    $catResult = $catStmt->get_result();

    if ($catResult->num_rows === 0) {
        $insertCatQuery = "INSERT INTO expense_categories (firm_id, name) VALUES (?, ?)";
        $insertCatStmt = $conn->prepare($insertCatQuery);
        $insertCatStmt->bind_param("is", $firm_id, $category_name);
        if (!$insertCatStmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to create new category.']);
            exit();
        }
    }

    // Insert into expenses
    $insertExpenseQuery = "INSERT INTO expenses (firm_id, category, amount, description, date, payment_method, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertExpenseQuery);
    $stmt->bind_param("isdsssi", $firm_id, $category_name, $amount, $description, $date, $payment_method, $user_id);

    if ($stmt->execute()) {
        $expense_id = $stmt->insert_id;

        // Use description as payment_notes
        $payment_notes = $description;

        // Insert into jewellery_payments
        $insertPaymentQuery = "INSERT INTO jewellery_payments (
            reference_id, reference_type, party_type, party_id,
            payment_type, amount, payment_notes, reference_no,
            remarks, created_at, transctions_type, Firm_id
        ) VALUES (?, 'expense', 'other', NULL, ?, ?, ?, ?, ?, NOW(), 'debit', ?)";

        $paymentStmt = $conn->prepare($insertPaymentQuery);
        $paymentStmt->bind_param("isssssi",
            $expense_id,           // reference_id
            $payment_method,       // payment_type
            $amount,               // amount
            $payment_notes,        // payment_notes from description
            $reference_no,         // optional ref no
            $remarks,              // optional remarks
            $firm_id               // firm_id
        );

        if ($paymentStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Expense and payment log added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Expense saved but payment log failed.']);
        }

        $paymentStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add expense.']);
    }

    $stmt->close();
    $conn->close();
}

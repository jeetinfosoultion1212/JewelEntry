<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has a firm
if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

require_once __DIR__ . '/config/config.php';

// Collect and sanitize POST data
$firm_id = $_SESSION['firmID'];
$created_by = isset($_POST['created_by']) ? intval($_POST['created_by']) : $_SESSION['id'];
$plan_name = trim($_POST['plan_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$duration_months = intval($_POST['duration_months'] ?? 0);
$min_amount_per_installment = floatval($_POST['min_amount_per_installment'] ?? 0);
$installment_frequency = trim($_POST['installment_frequency'] ?? '');
$bonus_percentage = floatval($_POST['bonus_percentage'] ?? 0);
$status = trim($_POST['status'] ?? 'active');
$terms_conditions = trim($_POST['terms_conditions'] ?? '');

if (
    !$plan_name || !$duration_months || !$min_amount_per_installment ||
    !$installment_frequency || $status === ''
) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
    exit;
}

// DB connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    $stmt = $conn->prepare(
        "INSERT INTO gold_saving_plans 
        (firm_id, plan_name, description, duration_months, min_amount_per_installment, installment_frequency, bonus_percentage, status, terms_conditions, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    if (!$stmt) throw new Exception($conn->error);

    $stmt->bind_param(
        "issidssssi",
        $firm_id,
        $plan_name,
        $description,
        $duration_months,
        $min_amount_per_installment,
        $installment_frequency,
        $bonus_percentage,
        $status,
        $terms_conditions,
        $created_by
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Plan created successfully!']);
    } else {
        throw new Exception($stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error creating plan: ' . $e->getMessage()]);
}
$conn->close();
?>

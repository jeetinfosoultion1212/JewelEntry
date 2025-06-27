<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/db_connect.php';
header('Content-Type: application/json');

// Validate required POST fields
$required = ['firm_id', 'customer_id', 'plan_id', 'enrollment_date', 'maturity_date', 'created_by'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

$firm_id = $_POST['firm_id'];
$customer_id = $_POST['customer_id'];
$plan_id = $_POST['plan_id'];
$enrollment_date = $_POST['enrollment_date'];
$maturity_date = $_POST['maturity_date'];
$created_by = $_POST['created_by'];
$current_status = 'active';
$total_amount_paid = 0;
$total_gold_accrued = 0;
$notes = '';

$conn->begin_transaction();

try {
    // Insert into customer_gold_plans
    $stmt = $conn->prepare("INSERT INTO customer_gold_plans (firm_id, customer_id, plan_id, enrollment_date, maturity_date, current_status, total_amount_paid, total_gold_accrued, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("iiisssdds", $firm_id, $customer_id, $plan_id, $enrollment_date, $maturity_date, $current_status, $total_amount_paid, $total_gold_accrued, $notes);
    $stmt->execute();
    $customer_plan_id = $stmt->insert_id;
    $stmt->close();

    // Get plan details
    $planQ = $conn->prepare("SELECT duration_months, installment_frequency, min_amount_per_installment FROM gold_saving_plans WHERE id = ?");
    $planQ->bind_param("i", $plan_id);
    $planQ->execute();
    $planQ->bind_result($duration, $frequency, $min_amount);
    $planQ->fetch();
    $planQ->close();
    $duration = (int)$duration;

    // Calculate installment due dates (robust logic)
    $dates = [];
    $date = new DateTime($enrollment_date);
    $normalized_frequency = strtolower(trim($frequency));
    for ($i = 0; $i < $duration; $i++) {
        if ($i > 0) {
            if ($normalized_frequency == 'monthly') {
                $date->modify('+1 month');
            } elseif ($normalized_frequency == 'weekly') {
                $date->modify('+1 week');
            } elseif ($normalized_frequency == 'quarterly') {
                $date->modify('+3 months');
            }
        }
        $dates[] = $date->format('Y-m-d');
    }

    // Insert installments (default: unpaid, 0 amount/gold, payment_status Due, payment_date NULL, due_date set)
    $stmt2 = $conn->prepare("INSERT INTO gold_plan_installments (
        customer_plan_id, payment_date, amount_paid, payment_status, due_date, gold_credited_g, receipt_number, payment_method, notes, created_by, created_at
    ) VALUES (?, NULL, 0, 'Due', ?, 0, '', '', '', ?, NOW())");
    foreach ($dates as $due_date) {
        $stmt2->bind_param("iss", $customer_plan_id, $due_date, $created_by);
        $stmt2->execute();
    }
    $stmt2->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

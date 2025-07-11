<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/db_connect.php';
header('Content-Type: application/json');
session_start();

// Validate required POST fields
$required = ['customer_plan_id', 'due_date', 'amount_paid', 'payment_method', 'created_by'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

$customer_plan_id = $_POST['customer_plan_id'];
$due_date = $_POST['due_date'];
$amount_paid = floatval($_POST['amount_paid']);
$payment_method = $_POST['payment_method'];
$receipt_number = $_POST['receipt_number'] ?? '';
$notes = $_POST['notes'] ?? '';
$created_by = $_POST['created_by'];
$material_type = $_POST['material_type'] ?? 'Gold';
$purity = $_POST['purity'] ?? '22';
$gold_rate_per_gram = isset($_POST['gold_rate_per_gram']) ? floatval($_POST['gold_rate_per_gram']) : null;

$conn->begin_transaction();

try {
    // 1. Fetch the installment row to validate
    $stmt = $conn->prepare("SELECT id, amount_paid, payment_status FROM gold_plan_installments WHERE customer_plan_id = ? AND due_date = ? FOR UPDATE");
    $stmt->bind_param("is", $customer_plan_id, $due_date);
    $stmt->execute();
    $stmt->bind_result($installment_id, $existing_paid, $payment_status);
    if (!$stmt->fetch()) {
        $stmt->close();
        throw new Exception('Installment not found for this due date.');
    }
    $stmt->close();

    if ($payment_status !== 'Due') {
        throw new Exception('This installment is already paid or not due.');
    }

    // 2. Get gold rate if not provided
    if (!$gold_rate_per_gram) {
        $stmt = $conn->prepare("SELECT firm_id FROM customer_gold_plans WHERE id = ?");
        $stmt->bind_param("i", $customer_plan_id);
        $stmt->execute();
        $stmt->bind_result($firm_id);
        $stmt->fetch();
        $stmt->close();

        if (!$firm_id) {
            throw new Exception('Invalid customer_plan_id');
        }

        $stmt = $conn->prepare("SELECT rate FROM jewellery_price_config WHERE firm_id = ? AND material_type = ? AND purity = ? ORDER BY effective_date DESC LIMIT 1");
        $stmt->bind_param("iss", $firm_id, $material_type, $purity);
        $stmt->execute();
        $stmt->bind_result($rate);
        if ($stmt->fetch()) {
            $gold_rate_per_gram = floatval($rate);
        } else {
            $stmt->close();
            throw new Exception('Gold rate not found in price config.');
        }
        $stmt->close();
    }

    if ($gold_rate_per_gram <= 0) {
        throw new Exception('Invalid gold rate.');
    }

    $gold_credited_g = $amount_paid / $gold_rate_per_gram;

    // 3. Update the installment row
    $stmt = $conn->prepare("UPDATE gold_plan_installments SET amount_paid = ?, gold_credited_g = ?, payment_method = ?, receipt_number = ?, notes = ?, created_by = ?, created_at = NOW(), payment_date = NOW(), payment_status = 'Paid' WHERE id = ?");
    $stmt->bind_param("dssssii", $amount_paid, $gold_credited_g, $payment_method, $receipt_number, $notes, $created_by, $installment_id);
    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        $stmt->close();
        throw new Exception('Failed to update installment.');
    }
    $stmt->close();

    // 4. Update the parent plan totals
    $stmt = $conn->prepare("SELECT SUM(amount_paid), SUM(gold_credited_g) FROM gold_plan_installments WHERE customer_plan_id = ?");
    $stmt->bind_param("i", $customer_plan_id);
    $stmt->execute();
    $stmt->bind_result($total_paid, $total_gold);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE customer_gold_plans SET total_amount_paid = ?, total_gold_accrued = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ddi", $total_paid, $total_gold, $customer_plan_id);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to update customer gold plan totals.');
    }
    $stmt->close();

    // 5. Always fetch customer_id and firm_id from customer_gold_plans
    $stmt = $conn->prepare("SELECT customer_id, firm_id FROM customer_gold_plans WHERE id = ?");
    $stmt->bind_param("i", $customer_plan_id);
    $stmt->execute();
    $stmt->bind_result($customer_id, $firm_id);
    $stmt->fetch();
    $stmt->close();

    if (!$firm_id) {
        throw new Exception('Firm ID not found for this customer plan.');
    }

    // 6. Insert payment log into jewellery_payments (FIXED BLOCK)
    $reference_type = 'schemes_installment';
    $party_type = 'customer';
    $remarks = 'Gold Plan Installment Payment';
    $transactions_type = 'credit';

    $stmt = $conn->prepare("INSERT INTO jewellery_payments 
        (reference_id, reference_type, party_type, party_id, sale_id, payment_type, amount, payment_notes, reference_no, remarks, created_at, transctions_type, Firm_id)
        VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, NOW(), ?, ?)");

    $stmt->bind_param(
        "ississssssi",
        $installment_id,      // reference_id
        $reference_type,      // reference_type
        $party_type,          // party_type
        $customer_id,         // party_id
        $payment_method,      // payment_type
        $amount_paid,         // amount
        $notes,               // payment_notes
        $receipt_number,      // reference_no
        $remarks,             // remarks
        $transactions_type,   // transactions_type
        $firm_id              // Firm_id
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to log payment in jewellery_payments.');
    }
    $stmt->close();

    // Final commit and response
    $conn->commit();
    echo json_encode(['success' => true, 'gold_credited_g' => round($gold_credited_g, 4)]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>

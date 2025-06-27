<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

require_once __DIR__ . '/config/config.php';

$firm_id = $_SESSION['firmID'];
$plan_id = intval($_POST['plan_id'] ?? 0);
$plan_name = trim($_POST['plan_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$duration_months = intval($_POST['duration_months'] ?? 0);
$min_amount_per_installment = floatval($_POST['min_amount_per_installment'] ?? 0);
$installment_frequency = trim($_POST['installment_frequency'] ?? '');
$bonus_percentage = floatval($_POST['bonus_percentage'] ?? 0);
$status = trim($_POST['status'] ?? 'active');
$terms_conditions = trim($_POST['terms_conditions'] ?? '');

if (
    !$plan_id || !$plan_name || !$duration_months || !$min_amount_per_installment ||
    !$installment_frequency || $status === ''
) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Check if plan exists and belongs to this firm
$check = $conn->prepare("SELECT id FROM gold_saving_plans WHERE id = ? AND firm_id = ?");
$check->bind_param("ii", $plan_id, $firm_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Plan not found or access denied.']);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

try {
    $stmt = $conn->prepare(
        "UPDATE gold_saving_plans SET
            plan_name = ?, description = ?, duration_months = ?, min_amount_per_installment = ?,
            installment_frequency = ?, bonus_percentage = ?, status = ?, terms_conditions = ?, updated_at = NOW()
         WHERE id = ? AND firm_id = ?"
    );
    if (!$stmt) throw new Exception($conn->error);

    $stmt->bind_param(
        "ssidsssssi",
        $plan_name,
        $description,
        $duration_months,
        $min_amount_per_installment,
        $installment_frequency,
        $bonus_percentage,
        $status,
        $terms_conditions,
        $plan_id,
        $firm_id
    );

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Plan updated successfully!']);
    } else {
        throw new Exception('No changes made or update failed.');
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating plan: ' . $e->getMessage()]);
}
$conn->close();
?>
